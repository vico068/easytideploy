package scheduler

import (
	"context"
	"time"

	"github.com/easyti/easydeploy/orchestrator/pkg/proto"
	"github.com/google/uuid"
	"github.com/rs/zerolog/log"
)

// DatabaseContainer represents a container from the database
type DatabaseContainer struct {
	ID            string
	ApplicationID string
	ServerID      string
	Status        string
}

// DatabaseServer represents a server from the database
type DatabaseServer struct {
	ID     string
	Status string
}

// collectMetrics runs the metrics collection loop
func (s *Scheduler) collectMetrics() {
	defer s.wg.Done()
	ticker := time.NewTicker(30 * time.Second)
	defer ticker.Stop()

	log.Info().Msg("Metrics collector started")

	for {
		select {
		case <-ticker.C:
			s.collectServerAndContainerMetrics()
		case <-s.stopCh:
			log.Info().Msg("Metrics collector stopped")
			return
		}
	}
}

// collectServerAndContainerMetrics collects metrics from all servers and their containers
func (s *Scheduler) collectServerAndContainerMetrics() {
	ctx := context.Background()

	// 1. Get online servers
	servers, err := s.getOnlineServers()
	if err != nil {
		log.Error().Err(err).Msg("failed to get online servers")
		return
	}

	metricsCount := 0

	for _, server := range servers {
		// 2. Get agent client
		s.mu.RLock()
		client, ok := s.agentClients[server.ID]
		s.mu.RUnlock()

		if !ok {
			log.Warn().Str("server_id", server.ID).Msg("agent client not found")
			continue
		}

		// 3. Collect server stats
		serverStats, err := client.GetServerStats(ctx)
		if err != nil {
			log.Error().Str("server_id", server.ID).Err(err).Msg("failed to get server stats")
			continue
		}

		// 4. Persist server metrics
		if err := s.saveServerMetrics(server.ID, serverStats); err != nil {
			log.Error().Str("server_id", server.ID).Err(err).Msg("failed to save server metrics")
		} else {
			metricsCount++
		}

		// 5. Get running containers on this server
		containers, err := s.getRunningContainersByServer(server.ID)
		if err != nil {
			log.Error().Str("server_id", server.ID).Err(err).Msg("failed to get containers")
			continue
		}

		// 6. Collect stats for each container
		for _, container := range containers {
			containerStats, err := client.GetContainerStats(ctx, container.ID)
			if err != nil {
				log.Error().Str("container_id", container.ID).Err(err).Msg("failed to get container stats")
				continue
			}

			// 7. Persist container metrics
			if err := s.saveContainerMetrics(container, containerStats); err != nil {
				log.Error().Str("container_id", container.ID).Err(err).Msg("failed to save container metrics")
			} else {
				metricsCount++
			}
		}
	}

	log.Info().
		Int("servers", len(servers)).
		Int("metrics_saved", metricsCount).
		Msg("collected metrics")
}

// getOnlineServers retrieves all online servers from the database
func (s *Scheduler) getOnlineServers() ([]DatabaseServer, error) {
	query := `
		SELECT id, status
		FROM servers
		WHERE status = 'online'
		ORDER BY id
	`

	rows, err := s.db.Pool().Query(context.Background(), query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var servers []DatabaseServer
	for rows.Next() {
		var server DatabaseServer
		if err := rows.Scan(&server.ID, &server.Status); err != nil {
			return nil, err
		}
		servers = append(servers, server)
	}

	return servers, rows.Err()
}

// getRunningContainersByServer retrieves all running containers for a server
func (s *Scheduler) getRunningContainersByServer(serverID string) ([]DatabaseContainer, error) {
	query := `
		SELECT id, application_id, server_id, status
		FROM containers
		WHERE server_id = $1 AND status = 'running'
		ORDER BY id
	`

	rows, err := s.db.Pool().Query(context.Background(), query, serverID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var containers []DatabaseContainer
	for rows.Next() {
		var container DatabaseContainer
		if err := rows.Scan(&container.ID, &container.ApplicationID, &container.ServerID, &container.Status); err != nil {
			return nil, err
		}
		containers = append(containers, container)
	}

	return containers, rows.Err()
}

// saveServerMetrics persists server metrics to the database
func (s *Scheduler) saveServerMetrics(serverID string, stats interface{}) error {
	// Type assert to ServerStatsResponse
	st, ok := stats.(*proto.ServerStatsResponse)
	if !ok {
		return nil // Skip if wrong type
	}

	var memoryPercent, diskPercent float64
	if st.MemoryTotal > 0 {
		memoryPercent = (float64(st.MemoryUsage) / float64(st.MemoryTotal)) * 100
	}
	if st.DiskTotal > 0 {
		diskPercent = (float64(st.DiskUsage) / float64(st.DiskTotal)) * 100
	}

	query := `
		INSERT INTO resource_usages (
			id, server_id, cpu_percent, memory_percent, disk_percent,
			recorded_at, created_at, updated_at
		)
		VALUES ($1, $2, $3, $4, $5, NOW(), NOW(), NOW())
	`

	_, err := s.db.Pool().Exec(
		context.Background(),
		query,
		uuid.New().String(),
		serverID,
		st.CpuUsage,
		memoryPercent,
		diskPercent,
	)

	return err
}

// saveContainerMetrics persists container metrics to the database
func (s *Scheduler) saveContainerMetrics(container DatabaseContainer, stats interface{}) error {
	// Type assert to ContainerStatsResponse
	st, ok := stats.(*proto.ContainerStatsResponse)
	if !ok {
		return nil // Skip if wrong type
	}

	query := `
		INSERT INTO resource_usages (
			id, container_id, application_id, server_id,
			cpu_percent, memory_percent, memory_usage,
			network_rx, network_tx, disk_read, disk_write,
			recorded_at, created_at, updated_at
		)
		VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, NOW(), NOW(), NOW())
	`

	_, err := s.db.Pool().Exec(
		context.Background(),
		query,
		uuid.New().String(),
		container.ID,
		container.ApplicationID,
		container.ServerID,
		st.CpuPercent,
		st.MemoryPercent,
		st.MemoryUsage,
		st.NetworkRxBytes,
		st.NetworkTxBytes,
		st.BlockRead,
		st.BlockWrite,
	)

	return err
}
