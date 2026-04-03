package handlers

import (
	"net/http"
	"strconv"

	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/easyti/easydeploy/orchestrator/internal/repository"
	"github.com/easyti/easydeploy/orchestrator/internal/scheduler"
	"github.com/go-chi/chi/v5"
)

type ContainerHandler struct {
	db        *database.DB
	scheduler *scheduler.Scheduler
	repo      *repository.Repository
}

func NewContainerHandler(db *database.DB, sched *scheduler.Scheduler, repo *repository.Repository) *ContainerHandler {
	return &ContainerHandler{
		db:        db,
		scheduler: sched,
		repo:      repo,
	}
}

// List returns all containers
func (h *ContainerHandler) List(w http.ResponseWriter, r *http.Request) {
	applicationID := r.URL.Query().Get("application_id")
	serverID := r.URL.Query().Get("server_id")
	status := r.URL.Query().Get("status")

	query := `
		SELECT id, deployment_id, application_id, server_id, docker_container_id, name,
		       status, health_status, replica_index, internal_port, host_port,
		       health_checked_at, created_at, updated_at
		FROM containers
		WHERE 1=1
	`
	args := []interface{}{}
	argNum := 1

	if applicationID != "" {
		query += " AND application_id = $" + strconv.Itoa(argNum)
		args = append(args, applicationID)
		argNum++
	}
	if serverID != "" {
		query += " AND server_id = $" + strconv.Itoa(argNum)
		args = append(args, serverID)
		argNum++
	}
	if status != "" {
		query += " AND status = $" + strconv.Itoa(argNum)
		args = append(args, status)
	}

	query += " ORDER BY created_at DESC"

	rows, err := h.db.Pool().Query(r.Context(), query, args...)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to list containers")
		return
	}
	defer rows.Close()

	var containers []map[string]interface{}
	for rows.Next() {
		var c repository.Container
		if err := rows.Scan(
			&c.ID, &c.DeploymentID, &c.ApplicationID, &c.ServerID, &c.DockerContainerID,
			&c.Name, &c.Status, &c.HealthStatus, &c.ReplicaIndex, &c.InternalPort, &c.HostPort,
			&c.HealthCheckedAt, &c.CreatedAt, &c.UpdatedAt,
		); err != nil {
			continue
		}
		containers = append(containers, map[string]interface{}{
			"id":                  c.ID,
			"deployment_id":       c.DeploymentID,
			"application_id":      c.ApplicationID,
			"server_id":           c.ServerID,
			"docker_container_id": c.DockerContainerID,
			"name":                c.Name,
			"status":              c.Status,
			"health_status":       c.HealthStatus,
			"replica_index":       c.ReplicaIndex,
			"internal_port":       c.InternalPort,
			"host_port":           c.HostPort,
			"created_at":          c.CreatedAt,
		})
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"containers": containers,
	})
}

// Get returns a container by ID
func (h *ContainerHandler) Get(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	container, err := h.repo.GetContainer(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusNotFound, "container not found")
		return
	}

	respondJSON(w, http.StatusOK, container)
}

// GetLogs returns logs for a container
func (h *ContainerHandler) GetLogs(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	linesStr := r.URL.Query().Get("lines")
	lines := 100
	if linesStr != "" {
		if l, err := strconv.Atoi(linesStr); err == nil {
			lines = l
		}
	}

	logs, err := h.scheduler.GetContainerLogs(r.Context(), id, lines)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to get logs")
		return
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"id":   id,
		"logs": logs,
	})
}

// GetStats returns stats for a container
func (h *ContainerHandler) GetStats(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	// Get recent resource usage
	query := `
		SELECT cpu_percent, memory_percent, network_in, network_out
		FROM resource_usages
		WHERE container_id = $1
		ORDER BY created_at DESC
		LIMIT 1
	`

	var cpuPercent, memoryPercent float64
	var networkIn, networkOut int64
	err := h.db.Pool().QueryRow(r.Context(), query, id).Scan(&cpuPercent, &memoryPercent, &networkIn, &networkOut)
	if err != nil {
		// Return zeros if no data
		cpuPercent = 0
		memoryPercent = 0
		networkIn = 0
		networkOut = 0
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"id": id,
		"stats": map[string]interface{}{
			"cpu_percent":    cpuPercent,
			"memory_percent": memoryPercent,
			"network_in":     networkIn,
			"network_out":    networkOut,
		},
	})
}

// Restart restarts a container
func (h *ContainerHandler) Restart(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	if err := h.scheduler.RestartContainer(r.Context(), id); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to restart container")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{
		"id":     id,
		"status": "restarting",
	})
}

// Stop stops a container
func (h *ContainerHandler) Stop(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	if err := h.scheduler.StopContainer(r.Context(), id); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to stop container")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{
		"id":     id,
		"status": "stopped",
	})
}

// Delete removes a container
func (h *ContainerHandler) Delete(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	if err := h.scheduler.RemoveContainer(r.Context(), id); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to remove container")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{
		"id":     id,
		"status": "deleted",
	})
}
