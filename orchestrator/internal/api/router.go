package api

import (
	"context"
	"net/http"
	"time"

	"github.com/easyti/easydeploy/orchestrator/internal/api/handlers"
	"github.com/easyti/easydeploy/orchestrator/internal/api/middleware"
	"github.com/easyti/easydeploy/orchestrator/internal/config"
	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/easyti/easydeploy/orchestrator/internal/metrics"
	"github.com/easyti/easydeploy/orchestrator/internal/queue"
	"github.com/easyti/easydeploy/orchestrator/internal/repository"
	"github.com/easyti/easydeploy/orchestrator/internal/scheduler"
	"github.com/easyti/easydeploy/orchestrator/internal/traefik"
	"github.com/go-chi/chi/v5"
	chimiddleware "github.com/go-chi/chi/v5/middleware"
	"github.com/go-chi/cors"
)

type Server struct {
	cfg        *config.Config
	db         *database.DB
	queue      *queue.RedisQueue
	scheduler  *scheduler.Scheduler
	repo       *repository.Repository
	traefikGen *traefik.ConfigGenerator
	metrics    *metrics.Metrics
	server     *http.Server
}

func NewServer(
	cfg *config.Config,
	db *database.DB,
	q *queue.RedisQueue,
	sched *scheduler.Scheduler,
	repo *repository.Repository,
	traefikGen *traefik.ConfigGenerator,
	m *metrics.Metrics,
) *Server {
	return &Server{
		cfg:        cfg,
		db:         db,
		queue:      q,
		scheduler:  sched,
		repo:       repo,
		traefikGen: traefikGen,
		metrics:    m,
	}
}

func (s *Server) Start() error {
	r := chi.NewRouter()

	// Middleware
	r.Use(chimiddleware.RequestID)
	r.Use(chimiddleware.RealIP)
	r.Use(middleware.SecurityHeaders)
	r.Use(middleware.Logger)
	r.Use(chimiddleware.Recoverer)
	r.Use(chimiddleware.Timeout(60 * time.Second))
	r.Use(middleware.RequestSizeLimit(10 << 20)) // 10MB max body
	r.Use(middleware.RateLimit(120))              // 120 req/min per IP

	// Metrics middleware
	if s.metrics != nil {
		r.Use(metrics.Middleware(s.metrics))
	}

	// CORS
	r.Use(cors.Handler(cors.Options{
		AllowedOrigins:   []string{"*"},
		AllowedMethods:   []string{"GET", "POST", "PUT", "DELETE", "OPTIONS"},
		AllowedHeaders:   []string{"Accept", "Authorization", "Content-Type", "X-CSRF-Token"},
		ExposedHeaders:   []string{"Link"},
		AllowCredentials: true,
		MaxAge:           300,
	}))

	// Health check (public)
	r.Get("/health", handlers.HealthCheck)

	// Prometheus metrics endpoint (public)
	r.Handle("/metrics", metrics.Handler())

	// Agent heartbeat endpoint
	r.Post("/agent/heartbeat", handlers.NewAgentHandler(s.db).Heartbeat)

	// Webhook endpoints (verified by signature)
	webhookHandler := handlers.NewWebhookHandler(s.db, s.queue, s.repo)
	r.Post("/webhooks/github", webhookHandler.HandleGitHub)
	r.Post("/webhooks/gitlab", webhookHandler.HandleGitLab)
	r.Post("/webhooks/bitbucket", webhookHandler.HandleBitbucket)

	// API v1 routes (protected)
	r.Route("/api/v1", func(r chi.Router) {
		r.Use(middleware.Auth(s.cfg.APIKey))

		// Applications
		appHandler := handlers.NewApplicationHandler(s.db, s.queue, s.scheduler, s.repo, s.traefikGen)
		r.Route("/applications", func(r chi.Router) {
			r.Get("/", appHandler.List)
			r.Post("/", appHandler.Create)
			r.Get("/{id}", appHandler.Get)
			r.Put("/{id}", appHandler.Update)
			r.Delete("/{id}", appHandler.Delete)
			r.Post("/{id}/deploy", appHandler.Deploy)
			r.Post("/{id}/scale", appHandler.Scale)
			r.Post("/{id}/stop", appHandler.Stop)
			r.Post("/{id}/restart", appHandler.Restart)
			r.Post("/{id}/rollback", appHandler.Rollback)
			r.Get("/{id}/logs", appHandler.GetLogs)
			r.Get("/{id}/metrics", appHandler.GetMetrics)
			r.Get("/{id}/stats", appHandler.GetStats)

			// Environment variables
			r.Get("/{id}/env", appHandler.GetEnvVars)
			r.Post("/{id}/env", appHandler.SetEnvVars)
			r.Delete("/{id}/env/{key}", appHandler.DeleteEnvVar)

			// Domains
			r.Get("/{id}/domains", appHandler.GetDomains)
			r.Post("/{id}/domains", appHandler.AddDomain)
			r.Delete("/{id}/domains/{domainId}", appHandler.RemoveDomain)

			// Deployments for specific app
			r.Get("/{id}/deployments", appHandler.ListDeployments)
			r.Get("/{id}/deployments/latest", appHandler.GetLatestDeployment)
		})

		// Deployments
		deployHandler := handlers.NewDeploymentHandler(s.db, s.queue, s.scheduler, s.repo)
		r.Route("/deployments", func(r chi.Router) {
			r.Get("/", deployHandler.List)
			r.Post("/", deployHandler.Create)
			r.Get("/{id}", deployHandler.Get)
			r.Get("/{id}/logs", deployHandler.GetLogs)
			r.Post("/{id}/cancel", deployHandler.Cancel)
			r.Post("/{id}/retry", deployHandler.Retry)
		})

		// Containers
		containerHandler := handlers.NewContainerHandler(s.db, s.scheduler, s.repo)
		r.Route("/containers", func(r chi.Router) {
			r.Get("/", containerHandler.List)
			r.Get("/{id}", containerHandler.Get)
			r.Get("/{id}/logs", containerHandler.GetLogs)
			r.Get("/{id}/stats", containerHandler.GetStats)
			r.Post("/{id}/restart", containerHandler.Restart)
			r.Post("/{id}/stop", containerHandler.Stop)
			r.Delete("/{id}", containerHandler.Delete)
		})

		// Servers
		serverHandler := handlers.NewServerHandler(s.db, s.scheduler, s.repo)
		r.Route("/servers", func(r chi.Router) {
			r.Get("/", serverHandler.List)
			r.Post("/", serverHandler.Create)
			r.Get("/{id}", serverHandler.Get)
			r.Put("/{id}", serverHandler.Update)
			r.Delete("/{id}", serverHandler.Delete)
			r.Post("/{id}/drain", serverHandler.Drain)
			r.Post("/{id}/maintenance", serverHandler.Maintenance)
			r.Get("/{id}/containers", serverHandler.ListContainers)
			r.Get("/{id}/metrics", serverHandler.GetMetrics)
		})

		// Proxy / Traefik
		proxyHandler := handlers.NewProxyHandler(s.cfg, s.db, s.traefikGen)
		r.Route("/proxy", func(r chi.Router) {
			r.Post("/sync", proxyHandler.Sync)
			r.Post("/sync/{appId}", proxyHandler.SyncApplication)
			r.Get("/config/{appId}", proxyHandler.GetConfig)
		})

		// Stats
		statsHandler := handlers.NewStatsHandler(s.repo)
		r.Route("/stats", func(r chi.Router) {
			r.Get("/", statsHandler.GetGlobal)
			r.Get("/applications/{id}", statsHandler.GetApplication)
		})
	})

	s.server = &http.Server{
		Addr:              s.cfg.ListenAddr,
		Handler:           r,
		ReadTimeout:       15 * time.Second,
		ReadHeaderTimeout: 5 * time.Second,
		WriteTimeout:      60 * time.Second,
		IdleTimeout:       120 * time.Second,
		MaxHeaderBytes:    1 << 20, // 1MB
	}

	return s.server.ListenAndServe()
}

func (s *Server) Shutdown(ctx context.Context) error {
	return s.server.Shutdown(ctx)
}
