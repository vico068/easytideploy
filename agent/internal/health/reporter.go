package health

import (
	"bytes"
	"context"
	"encoding/json"
	"net/http"
	"sync"
	"time"

	"github.com/easyti/easydeploy/agent/internal/config"
	"github.com/easyti/easydeploy/agent/internal/docker"
	"github.com/rs/zerolog/log"
)

// Reporter sends heartbeats to the orchestrator
type Reporter struct {
	config          *config.Config
	docker          *docker.Client
	httpClient      *http.Client
	ticker          *time.Ticker
	stopCh          chan struct{}
	wg              sync.WaitGroup
	registered      bool
	lastHeartbeat   time.Time
	consecutiveFails int
}

// HeartbeatPayload is sent to the orchestrator
type HeartbeatPayload struct {
	ServerID      string  `json:"server_id"`
	CPUCores      int     `json:"cpu_cores"`
	CPUPercent    float64 `json:"cpu_percent"`
	MemoryTotal   int64   `json:"memory_total"`
	MemoryPercent float64 `json:"memory_percent"`
	DiskTotal     int64   `json:"disk_total"`
	DiskPercent   float64 `json:"disk_percent"`
	NetworkIn     int64   `json:"network_in"`
	NetworkOut    int64   `json:"network_out"`
	Containers    int     `json:"containers"`
	DockerVersion string  `json:"docker_version"`
	AgentVersion  string  `json:"agent_version"`
	Uptime        int64   `json:"uptime"` // seconds since start
}

// RegistrationPayload is sent for initial registration
type RegistrationPayload struct {
	ServerID     string            `json:"server_id"`
	ServerName   string            `json:"server_name"`
	IPAddress    string            `json:"ip_address"`
	AgentAddress string            `json:"agent_address"`
	CPUCores     int               `json:"cpu_cores"`
	MemoryTotal  int64             `json:"memory_total"`
	DiskTotal    int64             `json:"disk_total"`
	MaxContainers int              `json:"max_containers"`
	DockerVersion string           `json:"docker_version"`
	AgentVersion  string           `json:"agent_version"`
	Labels       map[string]string `json:"labels"`
}

var startTime = time.Now()

// NewReporter creates a new health reporter
func NewReporter(cfg *config.Config, dockerClient *docker.Client) *Reporter {
	return &Reporter{
		config: cfg,
		docker: dockerClient,
		httpClient: &http.Client{
			Timeout: time.Duration(cfg.HeartbeatTimeout) * time.Second,
		},
		stopCh: make(chan struct{}),
	}
}

// Start begins sending heartbeats
func (r *Reporter) Start() {
	interval := time.Duration(r.config.HeartbeatInterval) * time.Second
	r.ticker = time.NewTicker(interval)

	r.wg.Add(1)
	go func() {
		defer r.wg.Done()

		// Register on startup
		if err := r.register(); err != nil {
			log.Error().Err(err).Msg("Failed to register with orchestrator")
		}

		// Send initial heartbeat
		r.sendHeartbeat()

		for {
			select {
			case <-r.ticker.C:
				r.sendHeartbeat()
			case <-r.stopCh:
				return
			}
		}
	}()

	log.Info().
		Dur("interval", interval).
		Str("orchestrator", r.config.OrchestratorURL).
		Msg("Health reporter started")
}

// Stop stops the health reporter
func (r *Reporter) Stop() {
	if r.ticker != nil {
		r.ticker.Stop()
	}
	close(r.stopCh)
	r.wg.Wait()
	log.Info().Msg("Health reporter stopped")
}

// register registers this agent with the orchestrator
func (r *Reporter) register() error {
	if r.config.OrchestratorURL == "" {
		return nil
	}

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	// Get server stats
	stats := r.docker.GetServerStats(ctx)

	payload := RegistrationPayload{
		ServerID:      r.config.ServerID,
		ServerName:    r.config.ServerName,
		AgentAddress:  r.config.GRPCAddress,
		CPUCores:      stats.CPUCores,
		MemoryTotal:   stats.MemoryTotal,
		DiskTotal:     stats.DiskTotal,
		MaxContainers: r.config.MaxContainers,
		AgentVersion:  r.config.Version,
	}

	// Get Docker version
	info, err := r.getDockerInfo(ctx)
	if err == nil {
		payload.DockerVersion = info.ServerVersion
	}

	data, err := json.Marshal(payload)
	if err != nil {
		return err
	}

	url := r.config.OrchestratorURL + "/api/v1/servers"
	req, err := http.NewRequestWithContext(ctx, "POST", url, bytes.NewBuffer(data))
	if err != nil {
		return err
	}

	r.setHeaders(req)

	resp, err := r.httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode == http.StatusOK || resp.StatusCode == http.StatusCreated || resp.StatusCode == http.StatusConflict {
		r.registered = true
		log.Info().Str("server_id", r.config.ServerID).Msg("Registered with orchestrator")
		return nil
	}

	log.Warn().Int("status", resp.StatusCode).Msg("Registration response")
	return nil
}

// sendHeartbeat sends a heartbeat to the orchestrator
func (r *Reporter) sendHeartbeat() {
	if r.config.OrchestratorURL == "" || r.config.ServerID == "" {
		return
	}

	ctx, cancel := context.WithTimeout(context.Background(), time.Duration(r.config.HeartbeatTimeout)*time.Second)
	defer cancel()

	// Get server stats
	stats := r.docker.GetServerStats(ctx)

	payload := HeartbeatPayload{
		ServerID:      r.config.ServerID,
		CPUCores:      stats.CPUCores,
		CPUPercent:    stats.CPUUsage,
		MemoryTotal:   stats.MemoryTotal,
		MemoryPercent: stats.MemoryPercent,
		DiskTotal:     stats.DiskTotal,
		DiskPercent:   stats.DiskPercent,
		NetworkIn:     stats.NetworkRxBytes,
		NetworkOut:    stats.NetworkTxBytes,
		Containers:    stats.ContainerCount,
		AgentVersion:  r.config.Version,
		Uptime:        int64(time.Since(startTime).Seconds()),
	}

	// Get Docker version
	info, err := r.getDockerInfo(ctx)
	if err == nil {
		payload.DockerVersion = info.ServerVersion
	}

	data, err := json.Marshal(payload)
	if err != nil {
		log.Error().Err(err).Msg("Failed to marshal heartbeat payload")
		return
	}

	url := r.config.OrchestratorURL + "/agent/heartbeat"
	req, err := http.NewRequestWithContext(ctx, "POST", url, bytes.NewBuffer(data))
	if err != nil {
		log.Warn().Err(err).Msg("Failed to create heartbeat request")
		return
	}

	r.setHeaders(req)

	resp, err := r.httpClient.Do(req)
	if err != nil {
		r.consecutiveFails++
		log.Warn().
			Err(err).
			Int("consecutive_fails", r.consecutiveFails).
			Msg("Failed to send heartbeat")

		// Re-register if too many failures
		if r.consecutiveFails >= 3 {
			r.registered = false
		}
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode == http.StatusOK {
		r.consecutiveFails = 0
		r.lastHeartbeat = time.Now()
		log.Debug().Msg("Heartbeat sent successfully")
	} else {
		r.consecutiveFails++
		log.Warn().
			Int("status", resp.StatusCode).
			Int("consecutive_fails", r.consecutiveFails).
			Msg("Heartbeat failed")
	}
}

// setHeaders sets common request headers
func (r *Reporter) setHeaders(req *http.Request) {
	req.Header.Set("Content-Type", "application/json")
	if r.config.OrchestratorAPIKey != "" {
		req.Header.Set("Authorization", "Bearer "+r.config.OrchestratorAPIKey)
	}
	req.Header.Set("X-Agent-ID", r.config.ServerID)
	req.Header.Set("X-Agent-Version", r.config.Version)
}

// DockerInfo holds Docker server info
type DockerInfo struct {
	ServerVersion string
	NCPU          int
	MemTotal      int64
}

// getDockerInfo gets Docker server info
func (r *Reporter) getDockerInfo(ctx context.Context) (*DockerInfo, error) {
	// This would call docker.Info() but we don't have it exposed
	// For now, return a placeholder
	return &DockerInfo{
		ServerVersion: "unknown",
	}, nil
}

// IsHealthy returns whether the reporter is functioning correctly
func (r *Reporter) IsHealthy() bool {
	if !r.registered {
		return false
	}

	// Check if last heartbeat was within 2x interval
	maxAge := time.Duration(r.config.HeartbeatInterval*2) * time.Second
	return time.Since(r.lastHeartbeat) < maxAge
}

// GetLastHeartbeat returns the time of the last successful heartbeat
func (r *Reporter) GetLastHeartbeat() time.Time {
	return r.lastHeartbeat
}

// GetConsecutiveFails returns the number of consecutive heartbeat failures
func (r *Reporter) GetConsecutiveFails() int {
	return r.consecutiveFails
}
