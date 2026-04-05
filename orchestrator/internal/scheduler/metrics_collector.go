package scheduler

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"time"

	"github.com/easyti/easydeploy/orchestrator/internal/metrics"
	"github.com/easyti/easydeploy/orchestrator/pkg/proto"
	"github.com/google/uuid"
	"github.com/rs/zerolog/log"
)

// DatabaseContainer represents a container from the database
type DatabaseContainer struct {
	ID                string
	ApplicationID     string
	ServerID          string
	DockerContainerID string
	Status            string
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
			containerStats, err := client.GetContainerStats(ctx, container.DockerContainerID)
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

	// 8. Scrape Traefik HTTP metrics
	if s.traefikScraper != nil {
		httpMetrics, err := s.traefikScraper.Scrape(ctx)
		if err != nil {
			log.Error().Err(err).Msg("failed to scrape traefik metrics")
		} else if len(httpMetrics.Applications) > 0 {
			if err := s.saveHTTPMetrics(ctx, httpMetrics); err != nil {
				log.Error().Err(err).Msg("failed to save HTTP metrics")
			}
		}
	}
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
		SELECT id, application_id, server_id, docker_container_id, status
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
		if err := rows.Scan(&container.ID, &container.ApplicationID, &container.ServerID, &container.DockerContainerID, &container.Status); err != nil {
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

// httpMetricPayload represents a single HTTP metric for the panel callback
type httpMetricPayload struct {
	ApplicationID string `json:"application_id"`
	Requests2xx   int64  `json:"requests_2xx"`
	Requests3xx   int64  `json:"requests_3xx"`
	Requests4xx   int64  `json:"requests_4xx"`
	Requests5xx   int64  `json:"requests_5xx"`
	TotalRequests int64  `json:"total_requests"`
}

// httpMetricsBatchPayload is the batch sent to the panel
type httpMetricsBatchPayload struct {
	Timestamp   string              `json:"timestamp"`
	HTTPMetrics []httpMetricPayload `json:"http_metrics"`
}

// saveHTTPMetrics maps app slugs to IDs and sends HTTP metrics to the panel
func (s *Scheduler) saveHTTPMetrics(ctx context.Context, traefikMetrics *metrics.TraefikMetrics) error {
	// 1. Collect all slugs and map to application IDs
	slugs := make([]string, 0, len(traefikMetrics.Applications))
	for slug := range traefikMetrics.Applications {
		slugs = append(slugs, slug)
	}

	slugToID := make(map[string]string)
	for _, slug := range slugs {
		var appID string
		err := s.db.Pool().QueryRow(ctx, "SELECT id FROM applications WHERE slug = $1", slug).Scan(&appID)
		if err != nil {
			log.Warn().Str("slug", slug).Err(err).Msg("application not found for slug")
			continue
		}
		slugToID[slug] = appID
	}

	if len(slugToID) == 0 {
		return nil
	}

	// 2. Build payload
	payload := httpMetricsBatchPayload{
		Timestamp: time.Now().Format(time.RFC3339),
	}

	for slug, metric := range traefikMetrics.Applications {
		appID, ok := slugToID[slug]
		if !ok {
			continue
		}
		payload.HTTPMetrics = append(payload.HTTPMetrics, httpMetricPayload{
			ApplicationID: appID,
			Requests2xx:   metric.Requests2xx,
			Requests3xx:   metric.Requests3xx,
			Requests4xx:   metric.Requests4xx,
			Requests5xx:   metric.Requests5xx,
			TotalRequests: metric.TotalRequests,
		})
	}

	if len(payload.HTTPMetrics) == 0 {
		return nil
	}

	// 3. Send to panel
	if err := s.sendToPanelAPI("/api/internal/metrics/batch", payload); err != nil {
		return fmt.Errorf("send to panel: %w", err)
	}

	log.Info().
		Int("applications", len(payload.HTTPMetrics)).
		Msg("sent HTTP metrics to panel")

	return nil
}

// sendToPanelAPI sends a JSON payload to the panel API
func (s *Scheduler) sendToPanelAPI(path string, payload interface{}) error {
	panelURL := s.cfg.PanelURL
	if panelURL == "" {
		return fmt.Errorf("PANEL_URL not configured")
	}

	jsonData, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("marshal payload: %w", err)
	}

	req, err := http.NewRequestWithContext(context.Background(), http.MethodPost, panelURL+path, bytes.NewReader(jsonData))
	if err != nil {
		return fmt.Errorf("create request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+s.cfg.APIKey)

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return fmt.Errorf("request failed: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode >= 400 {
		return fmt.Errorf("panel returned status %d", resp.StatusCode)
	}

	return nil
}
