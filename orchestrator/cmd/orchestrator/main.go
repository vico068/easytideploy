package main

import (
	"context"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/easyti/easydeploy/orchestrator/internal/api"
	"github.com/easyti/easydeploy/orchestrator/internal/config"
	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/easyti/easydeploy/orchestrator/internal/health"
	"github.com/easyti/easydeploy/orchestrator/internal/metrics"
	"github.com/easyti/easydeploy/orchestrator/internal/queue"
	"github.com/easyti/easydeploy/orchestrator/internal/repository"
	"github.com/easyti/easydeploy/orchestrator/internal/scheduler"
	"github.com/easyti/easydeploy/orchestrator/internal/traefik"
	"github.com/rs/zerolog"
	"github.com/rs/zerolog/log"
)

func main() {
	// Configure logging
	zerolog.TimeFieldFormat = zerolog.TimeFormatUnix
	if os.Getenv("APP_ENV") != "production" {
		log.Logger = log.Output(zerolog.ConsoleWriter{Out: os.Stderr})
	}

	log.Info().Msg("Starting EasyDeploy Orchestrator...")

	// Load configuration
	cfg, err := config.Load()
	if err != nil {
		log.Fatal().Err(err).Msg("Failed to load config")
	}

	// Create data directories
	if err := os.MkdirAll(cfg.DataDir, 0755); err != nil {
		log.Fatal().Err(err).Msg("Failed to create data directory")
	}

	// Connect to database
	db, err := database.Connect(cfg.DatabaseURL)
	if err != nil {
		log.Fatal().Err(err).Msg("Failed to connect to database")
	}
	defer db.Close()

	log.Info().Msg("Connected to database")

	// Connect to Redis
	q, err := queue.NewRedisQueue(cfg.RedisURL)
	if err != nil {
		log.Fatal().Err(err).Msg("Failed to connect to Redis")
	}
	defer q.Close()

	log.Info().Msg("Connected to Redis")

	// Initialize repository
	repo := repository.NewRepository(db)

	// Initialize scheduler
	sched, err := scheduler.New(db, q, cfg)
	if err != nil {
		log.Fatal().Err(err).Msg("Failed to create scheduler")
	}
	sched.Start()

	log.Info().Msg("Scheduler started")

	// Initialize health checker
	healthChecker := health.NewHealthChecker(db, cfg)
	healthChecker.OnContainerUnhealthy = func(containerID string) {
		log.Warn().Str("container", containerID).Msg("Container marked unhealthy")
	}
	healthChecker.OnContainerRecovered = func(containerID string) {
		log.Info().Str("container", containerID).Msg("Container recovered")
	}
	healthChecker.OnFailoverTriggered = func(oldID, newID string) {
		log.Warn().Str("old", oldID).Str("new", newID).Msg("Failover triggered")
	}
	healthChecker.Start(time.Duration(cfg.HealthCheckInterval) * time.Second)

	// Initialize server health checker
	serverHealthChecker := health.NewServerHealthChecker(db, cfg)
	serverHealthChecker.Start(1 * time.Minute)

	log.Info().Msg("Health checkers started")

	// Initialize Traefik config generator
	traefikGen := traefik.NewConfigGenerator(db, cfg)

	// Wire traefik generator to scheduler so deploy pipeline generates configs
	sched.SetTraefikGenerator(traefikGen)

	// Start watching for config changes
	ctx, cancel := context.WithCancel(context.Background())
	go traefikGen.WatchChanges(ctx, 10*time.Second)

	log.Info().Msg("Traefik config generator started")

	// Initialize metrics
	m := metrics.New("easydeploy")

	// Start metrics collector
	metricsCollector := metrics.NewCollector(db, q, m)
	metricsCollector.Start(30 * time.Second)

	log.Info().Msg("Metrics system started")

	// Start API server
	server := api.NewServer(cfg, db, q, sched, repo, traefikGen, m)
	go func() {
		if err := server.Start(); err != nil {
			log.Fatal().Err(err).Msg("Failed to start API server")
		}
	}()

	log.Info().Str("addr", cfg.ListenAddr).Msg("API server started")

	// Graceful shutdown
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	log.Info().Msg("Shutting down...")

	// Cancel background tasks
	cancel()

	// Stop health checkers
	healthChecker.Stop()
	serverHealthChecker.Stop()

	// Stop metrics collector
	metricsCollector.Stop()

	// Stop scheduler
	sched.Stop()

	// Shutdown API server
	shutdownCtx, shutdownCancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer shutdownCancel()

	if err := server.Shutdown(shutdownCtx); err != nil {
		log.Error().Err(err).Msg("Server shutdown error")
	}

	log.Info().Msg("Server stopped")
}
