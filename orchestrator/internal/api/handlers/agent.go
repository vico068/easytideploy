package handlers

import (
	"encoding/json"
	"net/http"
	"time"

	"github.com/easyti/easydeploy/orchestrator/internal/database"
)

// AgentHandler handles agent communication
type AgentHandler struct {
	db *database.DB
}

// NewAgentHandler creates a new AgentHandler
func NewAgentHandler(db *database.DB) *AgentHandler {
	return &AgentHandler{db: db}
}

// HeartbeatRequest represents a heartbeat request from an agent
type HeartbeatRequest struct {
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
}

// Heartbeat handles agent heartbeat
func (h *AgentHandler) Heartbeat(w http.ResponseWriter, r *http.Request) {
	var req HeartbeatRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondError(w, http.StatusBadRequest, "invalid request body")
		return
	}

	if req.ServerID == "" {
		respondError(w, http.StatusBadRequest, "server_id is required")
		return
	}

	// Update server heartbeat
	query := `
		UPDATE servers
		SET last_heartbeat = NOW(),
		    status = 'online',
		    cpu_cores = $2,
		    memory_total = $3,
		    disk_total = $4,
		    docker_version = $5,
		    updated_at = NOW()
		WHERE id = $1::uuid
	`

	result, err := h.db.Pool().Exec(r.Context(), query, req.ServerID, req.CPUCores, req.MemoryTotal, req.DiskTotal, req.DockerVersion)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to update heartbeat")
		return
	}

	if result.RowsAffected() == 0 {
		// Server doesn't exist, create it
		insertQuery := `
			INSERT INTO servers (id, name, ip_address, agent_address, status, cpu_cores, memory_total, disk_total, docker_version, last_heartbeat, created_at, updated_at)
			VALUES ($1::uuid, $2, $3, $4, 'online', $5, $6, $7, $8, NOW(), NOW(), NOW())
		`
		_, err = h.db.Pool().Exec(r.Context(), insertQuery,
			req.ServerID, req.ServerID, r.RemoteAddr, r.RemoteAddr+":9090",
			req.CPUCores, req.MemoryTotal, req.DiskTotal, req.DockerVersion,
		)
		if err != nil {
			respondError(w, http.StatusInternalServerError, "failed to register server")
			return
		}
	}

	// Record resource usage
	usageQuery := `
		INSERT INTO resource_usages (id, server_id, cpu_percent, memory_percent, disk_percent, network_in, network_out, created_at)
		VALUES (gen_random_uuid(), $1::uuid, $2, $3, $4, $5, $6, NOW())
	`
	h.db.Pool().Exec(r.Context(), usageQuery, req.ServerID, req.CPUPercent, req.MemoryPercent, req.DiskPercent, req.NetworkIn, req.NetworkOut)

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"status":    "ok",
		"timestamp": time.Now().Unix(),
	})
}
