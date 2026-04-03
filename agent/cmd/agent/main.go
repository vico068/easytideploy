package main

import (
	"context"
	"encoding/json"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/easyti/easydeploy/agent/internal/config"
	"github.com/easyti/easydeploy/agent/internal/docker"
	agentgrpc "github.com/easyti/easydeploy/agent/internal/grpc"
	"github.com/easyti/easydeploy/agent/internal/health"
	"github.com/easyti/easydeploy/agent/internal/metrics"
	"github.com/rs/zerolog"
	"github.com/rs/zerolog/log"
)

func main() {
	// Load configuration first to set up logging
	cfg, err := config.Load()
	if err != nil {
		log.Fatal().Err(err).Msg("Failed to load config")
	}

	// Configure logging
	zerolog.TimeFieldFormat = zerolog.TimeFormatUnix

	// Set log level
	level, err := zerolog.ParseLevel(cfg.LogLevel)
	if err != nil {
		level = zerolog.InfoLevel
	}
	zerolog.SetGlobalLevel(level)

	// Use console output for non-production
	if !cfg.LogJSON {
		log.Logger = log.Output(zerolog.ConsoleWriter{Out: os.Stderr})
	}

	log.Info().
		Str("server_id", cfg.ServerID).
		Str("version", cfg.Version).
		Msg("Starting EasyDeploy Agent...")

	// Connect to Docker
	dockerClient, err := docker.NewClient()
	if err != nil {
		log.Fatal().Err(err).Msg("Failed to connect to Docker")
	}
	defer dockerClient.Close()

	log.Info().Msg("Connected to Docker daemon")

	// Initialize metrics
	m := metrics.New("easydeploy_agent", cfg.ServerID)
	m.SetAgentInfo(cfg.ServerID, cfg.Version)

	log.Info().Msg("Metrics system initialized")

	// Create context for graceful shutdown
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	// Start health reporter (sends heartbeat to orchestrator)
	healthReporter := health.NewReporter(cfg, dockerClient)
	go healthReporter.Start()

	log.Info().
		Str("orchestrator", cfg.OrchestratorURL).
		Dur("interval", time.Duration(cfg.HeartbeatInterval)*time.Second).
		Msg("Health reporter started")

	// Start gRPC server
	grpcServer := agentgrpc.NewServer(dockerClient, cfg)
	go func() {
		if err := grpcServer.Start(); err != nil {
			log.Fatal().Err(err).Msg("Failed to start gRPC server")
		}
	}()

	// Start HTTP server for health checks and metrics
	httpServer := startHTTPServer(cfg, dockerClient, healthReporter, m)

	log.Info().
		Str("grpc", cfg.GRPCAddress).
		Str("http", cfg.HTTPAddress).
		Msg("Agent is ready")

	// Wait for graceful shutdown signal
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	log.Info().Msg("Shutting down agent...")

	// Create shutdown context with timeout
	shutdownCtx, shutdownCancel := context.WithTimeout(ctx, 30*time.Second)
	defer shutdownCancel()

	// Stop components
	healthReporter.Stop()
	grpcServer.Stop()

	if err := httpServer.Shutdown(shutdownCtx); err != nil {
		log.Warn().Err(err).Msg("HTTP server shutdown error")
	}

	log.Info().Msg("Agent stopped gracefully")
}

// startHTTPServer starts the HTTP server for health and metrics endpoints
func startHTTPServer(cfg *config.Config, dockerClient *docker.Client, healthReporter *health.Reporter, m *metrics.Metrics) *http.Server {
	mux := http.NewServeMux()

	// Health check endpoint
	mux.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")

		status := "healthy"
		httpStatus := http.StatusOK

		if !healthReporter.IsHealthy() {
			status = "degraded"
		}

		json.NewEncoder(w).Encode(map[string]interface{}{
			"status":    status,
			"server_id": cfg.ServerID,
			"version":   cfg.Version,
			"timestamp": time.Now().Unix(),
		})
		w.WriteHeader(httpStatus)
	})

	// Liveness probe
	mux.HandleFunc("/healthz", func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("ok"))
	})

	// Readiness probe
	mux.HandleFunc("/readyz", func(w http.ResponseWriter, r *http.Request) {
		// Check Docker connectivity
		ctx, cancel := context.WithTimeout(r.Context(), 2*time.Second)
		defer cancel()

		stats := dockerClient.GetServerStats(ctx)
		if stats == nil {
			w.WriteHeader(http.StatusServiceUnavailable)
			w.Write([]byte("docker unavailable"))
			return
		}

		w.WriteHeader(http.StatusOK)
		w.Write([]byte("ready"))
	})

	// Prometheus metrics endpoint
	mux.Handle("/metrics", metrics.Handler())

	// JSON metrics endpoint (for orchestrator)
	mux.HandleFunc("/metrics/json", func(w http.ResponseWriter, r *http.Request) {
		ctx, cancel := context.WithTimeout(r.Context(), 5*time.Second)
		defer cancel()

		stats := dockerClient.GetServerStats(ctx)

		// Update Prometheus metrics
		if stats != nil {
			m.SetSystemMetrics(
				stats.CPUUsage,
				float64(stats.MemoryUsage),
				stats.MemoryPercent,
				float64(stats.DiskUsage),
				stats.DiskPercent,
			)
			m.SetContainerCounts(stats.RunningCount, 0, stats.ContainerCount-stats.RunningCount)
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]interface{}{
			"server_id":       cfg.ServerID,
			"cpu_cores":       stats.CPUCores,
			"cpu_percent":     stats.CPUUsage,
			"memory_total":    stats.MemoryTotal,
			"memory_used":     stats.MemoryUsage,
			"memory_percent":  stats.MemoryPercent,
			"disk_total":      stats.DiskTotal,
			"disk_used":       stats.DiskUsage,
			"disk_percent":    stats.DiskPercent,
			"containers":      stats.ContainerCount,
			"running":         stats.RunningCount,
			"last_heartbeat":  healthReporter.GetLastHeartbeat().Unix(),
			"heartbeat_fails": healthReporter.GetConsecutiveFails(),
			"timestamp":       time.Now().Unix(),
		})
	})

	// Container list endpoint
	mux.HandleFunc("/containers", func(w http.ResponseWriter, r *http.Request) {
		ctx, cancel := context.WithTimeout(r.Context(), 10*time.Second)
		defer cancel()

		containers, err := dockerClient.ListManagedContainers(ctx)
		if err != nil {
			http.Error(w, err.Error(), http.StatusInternalServerError)
			return
		}

		w.Header().Set("Content-Type", "application/json")

		containerList := make([]map[string]interface{}, 0, len(containers))
		for _, c := range containers {
			containerList = append(containerList, map[string]interface{}{
				"id":      c.ID[:12],
				"name":    c.Names,
				"image":   c.Image,
				"status":  c.Status,
				"state":   c.State,
				"created": c.Created,
			})
		}

		json.NewEncoder(w).Encode(map[string]interface{}{
			"containers": containerList,
			"count":      len(containers),
		})
	})

	// Info endpoint
	mux.HandleFunc("/info", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]interface{}{
			"server_id":     cfg.ServerID,
			"server_name":   cfg.ServerName,
			"version":       cfg.Version,
			"grpc_address":  cfg.GRPCAddress,
			"orchestrator":  cfg.OrchestratorURL,
			"max_containers": cfg.MaxContainers,
			"tls_enabled":   cfg.TLSEnabled,
		})
	})

	server := &http.Server{
		Addr:         cfg.HTTPAddress,
		Handler:      mux,
		ReadTimeout:  15 * time.Second,
		WriteTimeout: 15 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	go func() {
		log.Info().Str("addr", cfg.HTTPAddress).Msg("HTTP server started")
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Error().Err(err).Msg("HTTP server error")
		}
	}()

	return server
}
