package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/easyti/easydeploy/orchestrator/internal/repository"
	"github.com/easyti/easydeploy/orchestrator/internal/scheduler"
	"github.com/go-chi/chi/v5"
	"github.com/google/uuid"
)

type ServerHandler struct {
	db        *database.DB
	scheduler *scheduler.Scheduler
	repo      *repository.Repository
}

func NewServerHandler(db *database.DB, sched *scheduler.Scheduler, repo *repository.Repository) *ServerHandler {
	return &ServerHandler{
		db:        db,
		scheduler: sched,
		repo:      repo,
	}
}

// List returns all servers
func (h *ServerHandler) List(w http.ResponseWriter, r *http.Request) {
	servers, err := h.repo.ListServers(r.Context())
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to list servers")
		return
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"servers": servers,
	})
}

// CreateServerRequest represents a server creation request
type CreateServerRequest struct {
	Name          string            `json:"name"`
	Hostname      string            `json:"hostname"`
	IPAddress     string            `json:"ip_address"`
	InternalIP    string            `json:"internal_ip"`
	AgentPort     int               `json:"agent_port"`
	MaxContainers int               `json:"max_containers"`
	Labels        map[string]string `json:"labels"`
}

// Create creates a new server
func (h *ServerHandler) Create(w http.ResponseWriter, r *http.Request) {
	var req CreateServerRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondError(w, http.StatusBadRequest, "invalid request body")
		return
	}

	if req.Name == "" || req.IPAddress == "" {
		respondError(w, http.StatusBadRequest, "name and ip_address are required")
		return
	}

	if req.AgentPort == 0 {
		req.AgentPort = 9090
	}
	if req.MaxContainers == 0 {
		req.MaxContainers = 50
	}

	id := uuid.New().String()
	agentAddress := fmt.Sprintf("%s:%d", req.IPAddress, req.AgentPort)

	query := `
		INSERT INTO servers (id, name, ip_address, agent_address, status, max_containers, created_at, updated_at)
		VALUES ($1, $2, $3, $4, 'offline', $5, NOW(), NOW())
	`
	if _, err := h.db.Pool().Exec(r.Context(), query, id, req.Name, req.IPAddress, agentAddress, req.MaxContainers); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to create server")
		return
	}

	respondJSON(w, http.StatusCreated, map[string]string{
		"id":   id,
		"name": req.Name,
	})
}

// Get returns a server by ID
func (h *ServerHandler) Get(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	server, err := h.repo.GetServer(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusNotFound, "server not found")
		return
	}

	respondJSON(w, http.StatusOK, server)
}

// Update updates a server
func (h *ServerHandler) Update(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	var req CreateServerRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondError(w, http.StatusBadRequest, "invalid request body")
		return
	}

	query := `
		UPDATE servers
		SET name = COALESCE(NULLIF($2, ''), name),
		    ip_address = COALESCE(NULLIF($3, ''), ip_address),
		    max_containers = COALESCE(NULLIF($4, 0), max_containers),
		    updated_at = NOW()
		WHERE id = $1
	`
	if _, err := h.db.Pool().Exec(r.Context(), query, id, req.Name, req.IPAddress, req.MaxContainers); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to update server")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{"id": id})
}

// Delete deletes a server
func (h *ServerHandler) Delete(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	// Check if server has running containers
	countQuery := `SELECT COUNT(*) FROM containers WHERE server_id = $1 AND status = 'running'`
	var count int
	h.db.Pool().QueryRow(r.Context(), countQuery, id).Scan(&count)

	if count > 0 {
		respondError(w, http.StatusBadRequest, "server has running containers, drain it first")
		return
	}

	query := `DELETE FROM servers WHERE id = $1`
	if _, err := h.db.Pool().Exec(r.Context(), query, id); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to delete server")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{"id": id, "status": "deleted"})
}

// Drain drains a server (moves all containers to other servers)
func (h *ServerHandler) Drain(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	// Mark server as draining
	updateQuery := `UPDATE servers SET status = 'draining', updated_at = NOW() WHERE id = $1`
	if _, err := h.db.Pool().Exec(r.Context(), updateQuery, id); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to update server status")
		return
	}

	// Get all running containers on this server
	containersQuery := `
		SELECT c.id, c.application_id, c.deployment_id
		FROM containers c
		WHERE c.server_id = $1 AND c.status = 'running'
	`
	rows, err := h.db.Pool().Query(r.Context(), containersQuery, id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to get containers")
		return
	}
	defer rows.Close()

	var containerCount int
	for rows.Next() {
		containerCount++
		// In a real implementation, we would:
		// 1. Create new containers on other servers
		// 2. Wait for them to be ready
		// 3. Update Traefik config
		// 4. Stop old containers
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"id":         id,
		"status":     "draining",
		"containers": containerCount,
	})
}

// Maintenance puts a server in maintenance mode
func (h *ServerHandler) Maintenance(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	var req struct {
		Enable bool `json:"enable"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		req.Enable = true // Default to enabling maintenance
	}

	status := "maintenance"
	if !req.Enable {
		status = "online"
	}

	query := `UPDATE servers SET status = $1, updated_at = NOW() WHERE id = $2`
	if _, err := h.db.Pool().Exec(r.Context(), query, status, id); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to update server status")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{
		"id":     id,
		"status": status,
	})
}

// ListContainers returns containers for a specific server
func (h *ServerHandler) ListContainers(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	query := `
		SELECT id, deployment_id, application_id, docker_container_id, name,
		       status, health_status, replica_index
		FROM containers
		WHERE server_id = $1
		ORDER BY created_at DESC
	`

	rows, err := h.db.Pool().Query(r.Context(), query, id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to get containers")
		return
	}
	defer rows.Close()

	var containers []map[string]interface{}
	for rows.Next() {
		var containerID, deploymentID, appID, dockerID, name, status, health string
		var replica int
		if err := rows.Scan(&containerID, &deploymentID, &appID, &dockerID, &name, &status, &health, &replica); err != nil {
			continue
		}
		containers = append(containers, map[string]interface{}{
			"id":                  containerID,
			"deployment_id":       deploymentID,
			"application_id":      appID,
			"docker_container_id": dockerID,
			"name":                name,
			"status":              status,
			"health_status":       health,
			"replica_index":       replica,
		})
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"containers": containers,
	})
}

// GetMetrics returns metrics for a specific server
func (h *ServerHandler) GetMetrics(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	query := `
		SELECT AVG(cpu_percent), AVG(memory_percent), AVG(disk_percent), SUM(network_in), SUM(network_out)
		FROM resource_usages
		WHERE server_id = $1 AND created_at > NOW() - INTERVAL '1 hour'
	`

	var cpuPercent, memoryPercent, diskPercent float64
	var networkIn, networkOut int64
	err := h.db.Pool().QueryRow(r.Context(), query, id).Scan(&cpuPercent, &memoryPercent, &diskPercent, &networkIn, &networkOut)
	if err != nil {
		cpuPercent = 0
		memoryPercent = 0
		diskPercent = 0
		networkIn = 0
		networkOut = 0
	}

	// Get container count
	countQuery := `SELECT COUNT(*) FROM containers WHERE server_id = $1 AND status = 'running'`
	var containerCount int
	h.db.Pool().QueryRow(r.Context(), countQuery, id).Scan(&containerCount)

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"server_id": id,
		"metrics": map[string]interface{}{
			"cpu_percent":    cpuPercent,
			"memory_percent": memoryPercent,
			"disk_percent":   diskPercent,
			"network_in":     networkIn,
			"network_out":    networkOut,
			"containers":     containerCount,
		},
	})
}
