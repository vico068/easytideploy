package health

import (
	"context"
	"fmt"
	"sync"
	"time"

	"github.com/easyti/easydeploy/orchestrator/internal/config"
	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/rs/zerolog/log"
)

// ContainerHealth represents a container's health status
type ContainerHealth struct {
	ContainerID      string
	DockerID         string
	ServerID         string
	AgentAddress     string
	ApplicationID    string
	DeploymentID     string
	Healthy          bool
	ConsecutiveFails int
	LastCheck        time.Time
	LastHealthy      time.Time
}

// HealthChecker monitors container health and triggers failover
type HealthChecker struct {
	db            *database.DB
	cfg           *config.Config
	containerPool map[string]*ContainerHealth
	mu            sync.RWMutex
	stopCh        chan struct{}
	wg            sync.WaitGroup

	// Callbacks for failover actions
	OnContainerUnhealthy func(containerID string)
	OnContainerRecovered func(containerID string)
	OnFailoverTriggered  func(containerID string, newContainerID string)
}

// NewHealthChecker creates a new HealthChecker
func NewHealthChecker(db *database.DB, cfg *config.Config) *HealthChecker {
	return &HealthChecker{
		db:            db,
		cfg:           cfg,
		containerPool: make(map[string]*ContainerHealth),
		stopCh:        make(chan struct{}),
	}
}

// Start begins the health checking process
func (h *HealthChecker) Start(interval time.Duration) {
	h.wg.Add(1)
	go h.runHealthCheckLoop(interval)

	log.Info().Dur("interval", interval).Msg("Health checker started")
}

// Stop stops the health checker
func (h *HealthChecker) Stop() {
	close(h.stopCh)
	h.wg.Wait()
	log.Info().Msg("Health checker stopped")
}

func (h *HealthChecker) runHealthCheckLoop(interval time.Duration) {
	defer h.wg.Done()

	ticker := time.NewTicker(interval)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			h.checkAllContainers()
		case <-h.stopCh:
			return
		}
	}
}

func (h *HealthChecker) checkAllContainers() {
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	// Get all running containers
	containers, err := h.getRunningContainers(ctx)
	if err != nil {
		log.Error().Err(err).Msg("Failed to get running containers for health check")
		return
	}

	// Check each container's health
	var wg sync.WaitGroup
	for _, container := range containers {
		wg.Add(1)
		go func(c *ContainerHealth) {
			defer wg.Done()
			h.checkContainerHealth(ctx, c)
		}(container)
	}
	wg.Wait()
}

func (h *HealthChecker) getRunningContainers(ctx context.Context) ([]*ContainerHealth, error) {
	query := `
		SELECT c.id, c.docker_container_id, c.server_id, s.agent_address, c.application_id, c.deployment_id
		FROM containers c
		JOIN servers s ON c.server_id = s.id
		WHERE c.status = 'running' AND s.status = 'online'
	`

	rows, err := h.db.Pool().Query(ctx, query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var containers []*ContainerHealth
	for rows.Next() {
		var c ContainerHealth
		if err := rows.Scan(&c.ContainerID, &c.DockerID, &c.ServerID, &c.AgentAddress, &c.ApplicationID, &c.DeploymentID); err != nil {
			continue
		}

		// Get or create from pool
		h.mu.Lock()
		if existing, ok := h.containerPool[c.ContainerID]; ok {
			existing.AgentAddress = c.AgentAddress // Update in case it changed
			c = *existing
		} else {
			c.Healthy = true
			c.LastHealthy = time.Now()
			h.containerPool[c.ContainerID] = &c
		}
		h.mu.Unlock()

		containers = append(containers, &c)
	}

	return containers, nil
}

func (h *HealthChecker) checkContainerHealth(ctx context.Context, container *ContainerHealth) {
	// Perform HTTP health check to the container
	healthy := h.performHealthCheck(ctx, container)

	h.mu.Lock()
	defer h.mu.Unlock()

	poolContainer, exists := h.containerPool[container.ContainerID]
	if !exists {
		return
	}

	poolContainer.LastCheck = time.Now()

	if healthy {
		// Container is healthy
		if !poolContainer.Healthy {
			// Container recovered
			poolContainer.Healthy = true
			poolContainer.ConsecutiveFails = 0
			poolContainer.LastHealthy = time.Now()

			h.updateContainerHealthStatus(ctx, container.ContainerID, "healthy")

			if h.OnContainerRecovered != nil {
				go h.OnContainerRecovered(container.ContainerID)
			}

			log.Info().Str("container", container.ContainerID).Msg("Container recovered")
		} else {
			poolContainer.LastHealthy = time.Now()
			poolContainer.ConsecutiveFails = 0
		}
	} else {
		// Container is unhealthy
		poolContainer.ConsecutiveFails++

		log.Warn().
			Str("container", container.ContainerID).
			Int("consecutive_fails", poolContainer.ConsecutiveFails).
			Msg("Container health check failed")

		if poolContainer.ConsecutiveFails >= h.cfg.HealthCheckFailThreshold {
			if poolContainer.Healthy {
				poolContainer.Healthy = false
				h.updateContainerHealthStatus(ctx, container.ContainerID, "unhealthy")

				if h.OnContainerUnhealthy != nil {
					go h.OnContainerUnhealthy(container.ContainerID)
				}
			}

			// Trigger failover if threshold exceeded
			if poolContainer.ConsecutiveFails >= h.cfg.FailoverThreshold {
				go h.triggerFailover(container)
				poolContainer.ConsecutiveFails = 0 // Reset to avoid repeated failovers
			}
		}
	}
}

func (h *HealthChecker) performHealthCheck(ctx context.Context, container *ContainerHealth) bool {
	// This would be a gRPC call to the agent to check container health
	// For now, we'll implement a simple check

	// Get application health check configuration
	var healthPath string
	var healthPort int

	query := `SELECT health_check_path, port FROM applications WHERE id = $1`
	err := h.db.Pool().QueryRow(ctx, query, container.ApplicationID).Scan(&healthPath, &healthPort)
	if err != nil {
		// Use default health check
		healthPath = "/health"
		healthPort = 8080
	}

	if healthPath == "" {
		healthPath = "/health"
	}

	// TODO: Make actual HTTP health check call via agent
	// For now, return true (would be replaced with actual gRPC call)
	_ = healthPath
	_ = healthPort

	return true
}

func (h *HealthChecker) updateContainerHealthStatus(ctx context.Context, containerID string, status string) {
	query := `
		UPDATE containers
		SET health_status = $1, health_checked_at = NOW(), updated_at = NOW()
		WHERE id = $2
	`
	if _, err := h.db.Pool().Exec(ctx, query, status, containerID); err != nil {
		log.Error().Err(err).Str("container", containerID).Msg("Failed to update container health status")
	}
}

func (h *HealthChecker) triggerFailover(container *ContainerHealth) {
	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Minute)
	defer cancel()

	log.Warn().
		Str("container", container.ContainerID).
		Str("application", container.ApplicationID).
		Msg("Triggering container failover")

	// Mark old container as failed
	updateQuery := `UPDATE containers SET status = 'failed', updated_at = NOW() WHERE id = $1`
	if _, err := h.db.Pool().Exec(ctx, updateQuery, container.ContainerID); err != nil {
		log.Error().Err(err).Msg("Failed to mark container as failed")
	}

	// Get deployment info for recreating container
	var imageName, imageTag string
	var port, cpuLimit, memoryLimit int
	var envVars map[string]string

	query := `
		SELECT d.image_name, d.image_tag, a.port, a.cpu_limit, a.memory_limit
		FROM deployments d
		JOIN applications a ON d.application_id = a.id
		WHERE d.id = $1
	`
	err := h.db.Pool().QueryRow(ctx, query, container.DeploymentID).Scan(
		&imageName, &imageTag, &port, &cpuLimit, &memoryLimit,
	)
	if err != nil {
		log.Error().Err(err).Msg("Failed to get deployment info for failover")
		return
	}

	// Get environment variables
	envQuery := `SELECT key, value FROM environment_variables WHERE application_id = $1`
	rows, err := h.db.Pool().Query(ctx, envQuery, container.ApplicationID)
	if err == nil {
		envVars = make(map[string]string)
		for rows.Next() {
			var key, value string
			if err := rows.Scan(&key, &value); err == nil {
				envVars[key] = value
			}
		}
		rows.Close()
	}

	// Select new server for failover (excluding current server)
	serverQuery := `
		SELECT s.id, s.agent_address
		FROM servers s
		WHERE s.status = 'online'
		  AND s.id != $1
		  AND (
		    SELECT COUNT(*) FROM containers c
		    WHERE c.server_id = s.id AND c.status = 'running'
		  ) < s.max_containers
		ORDER BY (
		    SELECT COUNT(*) FROM containers c
		    WHERE c.server_id = s.id AND c.status = 'running'
		)
		LIMIT 1
	`

	var newServerID, newAgentAddress string
	err = h.db.Pool().QueryRow(ctx, serverQuery, container.ServerID).Scan(&newServerID, &newAgentAddress)
	if err != nil {
		// No available server, try same server
		log.Warn().Msg("No alternative server available for failover, using same server")
		newServerID = container.ServerID
		newAgentAddress = container.AgentAddress
	}

	log.Info().
		Str("old_server", container.ServerID).
		Str("new_server", newServerID).
		Msg("Failover to new server")

	// Create new container (this would be a gRPC call to agent)
	// For now, just log it
	_ = imageName
	_ = imageTag
	_ = port
	_ = cpuLimit
	_ = memoryLimit
	_ = envVars
	_ = newAgentAddress

	// The actual container creation would be handled by the scheduler
	// This is just updating the record to trigger scheduling
	insertQuery := `
		INSERT INTO containers (id, deployment_id, application_id, server_id, name, status, created_at)
		VALUES (gen_random_uuid(), $1, $2, $3, $4, 'pending', NOW())
		RETURNING id
	`
	var newContainerID string
	err = h.db.Pool().QueryRow(ctx, insertQuery,
		container.DeploymentID,
		container.ApplicationID,
		newServerID,
		fmt.Sprintf("%s-failover-%d", container.ApplicationID, time.Now().Unix()),
	).Scan(&newContainerID)

	if err != nil {
		log.Error().Err(err).Msg("Failed to create failover container record")
		return
	}

	if h.OnFailoverTriggered != nil {
		go h.OnFailoverTriggered(container.ContainerID, newContainerID)
	}

	log.Info().
		Str("old_container", container.ContainerID).
		Str("new_container", newContainerID).
		Msg("Failover container created")

	// Remove old container from pool
	h.mu.Lock()
	delete(h.containerPool, container.ContainerID)
	h.mu.Unlock()
}

// ServerHealthChecker monitors server health
type ServerHealthChecker struct {
	db     *database.DB
	cfg    *config.Config
	stopCh chan struct{}
	wg     sync.WaitGroup
}

// NewServerHealthChecker creates a new ServerHealthChecker
func NewServerHealthChecker(db *database.DB, cfg *config.Config) *ServerHealthChecker {
	return &ServerHealthChecker{
		db:     db,
		cfg:    cfg,
		stopCh: make(chan struct{}),
	}
}

// Start begins server health monitoring
func (s *ServerHealthChecker) Start(interval time.Duration) {
	s.wg.Add(1)
	go s.runHealthCheckLoop(interval)
}

// Stop stops the server health checker
func (s *ServerHealthChecker) Stop() {
	close(s.stopCh)
	s.wg.Wait()
}

func (s *ServerHealthChecker) runHealthCheckLoop(interval time.Duration) {
	defer s.wg.Done()

	ticker := time.NewTicker(interval)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			s.checkAllServers()
		case <-s.stopCh:
			return
		}
	}
}

func (s *ServerHealthChecker) checkAllServers() {
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	// Compare staleness in PostgreSQL time domain to avoid timezone skew
	query := `
		UPDATE servers
		SET status = 'offline', updated_at = NOW()
		WHERE status != 'offline'
		  AND last_heartbeat IS NOT NULL
		  AND last_heartbeat < (NOW() - INTERVAL '2 minutes')
		RETURNING id
	`
	rows, err := s.db.Pool().Query(ctx, query)
	if err != nil {
		log.Error().Err(err).Msg("Failed to get servers for health check")
		return
	}
	defer rows.Close()

	for rows.Next() {
		var serverID string
		if err := rows.Scan(&serverID); err != nil {
			continue
		}
		log.Warn().Str("server", serverID).Msg("Server heartbeat stale, marking as offline")
	}
}

func (s *ServerHealthChecker) markServerOffline(ctx context.Context, serverID string) {
	query := `UPDATE servers SET status = 'offline', updated_at = NOW() WHERE id = $1`
	if _, err := s.db.Pool().Exec(ctx, query, serverID); err != nil {
		log.Error().Err(err).Str("server", serverID).Msg("Failed to mark server as offline")
	}
}

// RegisterHeartbeat updates the server's last heartbeat
func RegisterHeartbeat(pool *pgxpool.Pool, serverID string, metrics *ServerMetrics) error {
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	query := `
		UPDATE servers
		SET last_heartbeat = NOW(),
		    cpu_cores = $2,
		    memory_total = $3,
		    disk_total = $4,
		    status = 'online',
		    updated_at = NOW()
		WHERE id = $1
	`

	_, err := pool.Exec(ctx, query, serverID, metrics.CPUCores, metrics.MemoryTotal, metrics.DiskTotal)
	if err != nil {
		return err
	}

	// Also insert into resource_usages for historical tracking
	usageQuery := `
		INSERT INTO resource_usages (id, server_id, cpu_percent, memory_percent, disk_percent, network_in, network_out, created_at)
		VALUES (gen_random_uuid(), $1, $2, $3, $4, $5, $6, NOW())
	`
	_, err = pool.Exec(ctx, usageQuery, serverID, metrics.CPUPercent, metrics.MemoryPercent, metrics.DiskPercent, metrics.NetworkIn, metrics.NetworkOut)

	return err
}

// ServerMetrics represents server-level metrics
type ServerMetrics struct {
	CPUCores      int
	CPUPercent    float64
	MemoryTotal   int64
	MemoryPercent float64
	DiskTotal     int64
	DiskPercent   float64
	NetworkIn     int64
	NetworkOut    int64
}

