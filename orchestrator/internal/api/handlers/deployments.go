package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/easyti/easydeploy/orchestrator/internal/queue"
	"github.com/easyti/easydeploy/orchestrator/internal/repository"
	"github.com/easyti/easydeploy/orchestrator/internal/scheduler"
	"github.com/go-chi/chi/v5"
	"github.com/google/uuid"
)

type DeploymentHandler struct {
	db        *database.DB
	queue     *queue.RedisQueue
	scheduler *scheduler.Scheduler
	repo      *repository.Repository
}

func NewDeploymentHandler(db *database.DB, q *queue.RedisQueue, sched *scheduler.Scheduler, repo *repository.Repository) *DeploymentHandler {
	return &DeploymentHandler{
		db:        db,
		queue:     q,
		scheduler: sched,
		repo:      repo,
	}
}

type CreateDeploymentRequest struct {
	DeploymentID  string            `json:"deployment_id,omitempty"`
	ApplicationID string            `json:"application_id"`
	GitRepository string            `json:"git_repository"`
	GitBranch     string            `json:"git_branch"`
	CommitSHA     string            `json:"commit_sha,omitempty"`
	GitToken      string            `json:"git_token,omitempty"`
	Type          string            `json:"type"`
	BuildCommand  string            `json:"build_command"`
	StartCommand  string            `json:"start_command"`
	RootDirectory string            `json:"root_directory,omitempty"`
	Port          int               `json:"port"`
	Replicas      int               `json:"replicas"`
	CPULimit      int               `json:"cpu_limit"`
	MemoryLimit   int               `json:"memory_limit"`
	Environment   map[string]string `json:"environment"`
	HealthCheck   *queue.HealthCheck `json:"health_check"`
	CallbackURL   string            `json:"callback_url,omitempty"`
}

// List returns all deployments
func (h *DeploymentHandler) List(w http.ResponseWriter, r *http.Request) {
	applicationID := r.URL.Query().Get("application_id")

	query := `
		SELECT id, application_id, status, commit_sha, commit_message, image_name, image_tag,
		       triggered_by, error_message, started_at, completed_at, created_at
		FROM deployments
	`
	args := []interface{}{}

	if applicationID != "" {
		query += " WHERE application_id = $1"
		args = append(args, applicationID)
	}

	query += " ORDER BY created_at DESC LIMIT 50"

	rows, err := h.db.Pool().Query(r.Context(), query, args...)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to list deployments")
		return
	}
	defer rows.Close()

	var deployments []map[string]interface{}
	for rows.Next() {
		var d repository.Deployment
		if err := rows.Scan(
			&d.ID, &d.ApplicationID, &d.Status, &d.CommitSha, &d.CommitMessage,
			&d.ImageName, &d.ImageTag, &d.TriggeredBy, &d.ErrorMessage,
			&d.StartedAt, &d.CompletedAt, &d.CreatedAt,
		); err != nil {
			continue
		}
		deployments = append(deployments, map[string]interface{}{
			"id":             d.ID,
			"application_id": d.ApplicationID,
			"status":         d.Status,
			"commit_sha":     d.CommitSha,
			"commit_message": d.CommitMessage,
			"image_name":     d.ImageName,
			"image_tag":      d.ImageTag,
			"triggered_by":   d.TriggeredBy,
			"error_message":  d.ErrorMessage,
			"started_at":     d.StartedAt,
			"completed_at":   d.CompletedAt,
			"created_at":     d.CreatedAt,
		})
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"deployments": deployments,
	})
}

// Create creates a new deployment
func (h *DeploymentHandler) Create(w http.ResponseWriter, r *http.Request) {
	var req CreateDeploymentRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondError(w, http.StatusBadRequest, "invalid request body")
		return
	}

	if req.ApplicationID == "" {
		respondError(w, http.StatusBadRequest, "application_id is required")
		return
	}

	// Get application if no details provided
	if req.GitRepository == "" {
		app, err := h.repo.GetApplication(r.Context(), req.ApplicationID)
		if err != nil {
			respondError(w, http.StatusNotFound, "application not found")
			return
		}
		req.GitRepository = app.GitRepository
		req.GitBranch = app.GitBranch
		req.Type = app.Type
		req.BuildCommand = app.BuildCommand
		req.StartCommand = app.StartCommand
		req.Port = app.Port
		req.Replicas = app.Replicas
		req.CPULimit = app.CPULimit
		req.MemoryLimit = app.MemoryLimit
	}

	// Use provided deployment ID or generate new one
	deploymentID := req.DeploymentID
	if deploymentID == "" {
		deploymentID = uuid.New().String()
	}

	// Create deployment record only if not provided by panel
	// (panel-initiated deploys already have a deployment record)
	if req.DeploymentID == "" {
		insertQuery := `
			INSERT INTO deployments (id, application_id, status, commit_sha, triggered_by, created_at)
			VALUES ($1, $2, 'pending', $3, 'api', NOW())
		`
		if _, err := h.db.Pool().Exec(r.Context(), insertQuery, deploymentID, req.ApplicationID, req.CommitSHA); err != nil {
			respondError(w, http.StatusInternalServerError, "failed to create deployment")
			return
		}
	}

	// Get environment variables
	envVars := req.Environment
	if envVars == nil {
		envVars, _ = h.repo.GetEnvironmentVariablesAsMap(r.Context(), req.ApplicationID)
	}

	// Create build job
	job := &queue.BuildJob{
		DeploymentID:  deploymentID,
		ApplicationID: req.ApplicationID,
		GitRepository: req.GitRepository,
		GitBranch:     req.GitBranch,
		CommitSHA:     req.CommitSHA,
		GitToken:      req.GitToken,
		Type:          req.Type,
		BuildCommand:  req.BuildCommand,
		StartCommand:  req.StartCommand,
		RootDirectory: req.RootDirectory,
		Port:          req.Port,
		Replicas:      req.Replicas,
		CPULimit:      req.CPULimit,
		MemoryLimit:   req.MemoryLimit,
		Environment:   envVars,
		HealthCheck:   req.HealthCheck,
		CallbackURL:   req.CallbackURL,
	}

	if err := h.queue.Enqueue("builds", job); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to enqueue build")
		return
	}

	respondJSON(w, http.StatusCreated, map[string]string{
		"id":     deploymentID,
		"status": "pending",
	})
}

// Get returns a deployment by ID
func (h *DeploymentHandler) Get(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	deployment, err := h.repo.GetDeployment(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusNotFound, "deployment not found")
		return
	}

	// Get containers for this deployment
	containers, _ := h.repo.ListContainersByApplication(r.Context(), deployment.ApplicationID)
	var deploymentContainers []*repository.Container
	for _, c := range containers {
		if c.DeploymentID == id {
			deploymentContainers = append(deploymentContainers, c)
		}
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"deployment": deployment,
		"containers": deploymentContainers,
	})
}

// GetLogs returns logs for a deployment
func (h *DeploymentHandler) GetLogs(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	query := `SELECT build_logs FROM deployments WHERE id = $1`
	var buildLogs string
	if err := h.db.Pool().QueryRow(r.Context(), query, id).Scan(&buildLogs); err != nil {
		respondError(w, http.StatusNotFound, "deployment not found")
		return
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"id":   id,
		"logs": buildLogs,
		"type": "build",
	})
}

// Cancel cancels a deployment
func (h *DeploymentHandler) Cancel(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	// Only allow cancelling pending or building deployments
	query := `
		UPDATE deployments
		SET status = 'cancelled', completed_at = NOW()
		WHERE id = $1 AND status IN ('pending', 'building')
		RETURNING id
	`

	var updatedID string
	err := h.db.Pool().QueryRow(r.Context(), query, id).Scan(&updatedID)
	if err != nil {
		respondError(w, http.StatusBadRequest, "deployment cannot be cancelled")
		return
	}

	// Remove from queue if still pending
	h.queue.Remove("builds", id)

	respondJSON(w, http.StatusOK, map[string]string{
		"id":     id,
		"status": "cancelled",
	})
}

// StreamLogs streams deployment logs via Server-Sent Events
func (h *DeploymentHandler) StreamLogs(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	// Check if deployment exists and get current status/logs
	query := `SELECT status, build_logs FROM deployments WHERE id = $1`
	var status, buildLogs string
	if err := h.db.Pool().QueryRow(r.Context(), query, id).Scan(&status, &buildLogs); err != nil {
		respondError(w, http.StatusNotFound, "deployment not found")
		return
	}

	// Set SSE headers
	w.Header().Set("Content-Type", "text/event-stream")
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Connection", "keep-alive")
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("X-Accel-Buffering", "no")

	flusher, ok := w.(http.Flusher)
	if !ok {
		respondError(w, http.StatusInternalServerError, "streaming not supported")
		return
	}

	// Send existing logs first (from database)
	if buildLogs != "" {
		lines := splitLines(buildLogs)
		for _, line := range lines {
			if line == "" {
				continue
			}
			sendSSE(w, flusher, "log", map[string]string{
				"line":  line,
				"stage": "build",
				"ts":    "",
			})
		}
	}

	// Send current status
	sendSSE(w, flusher, "status", map[string]string{
		"status": status,
	})

	// If deployment is already terminal, close the connection
	if isTerminalStatus(status) {
		sendSSE(w, flusher, "done", map[string]string{"status": status})
		return
	}

	// Subscribe to Redis Pub/Sub for live updates
	channel := "deploy-logs:" + id
	ctx := r.Context()
	pubsub := h.queue.Subscribe(ctx, channel)
	defer pubsub.Close()

	// Also get buffered messages that might have been published before we subscribed
	buffered, err := h.queue.GetBufferedMessages(channel)
	if err == nil {
		for _, msg := range buffered {
			var payload map[string]string
			if json.Unmarshal([]byte(msg), &payload) == nil {
				msgType := payload["type"]
				if msgType == "" {
					msgType = "log"
				}
				sendSSE(w, flusher, msgType, payload)

				// Check if terminal status
				if msgType == "status" && isTerminalStatus(payload["status"]) {
					sendSSE(w, flusher, "done", map[string]string{"status": payload["status"]})
					return
				}
			}
		}
	}

	// Listen for new messages
	ch := pubsub.Channel()
	for {
		select {
		case <-ctx.Done():
			return
		case msg, ok := <-ch:
			if !ok {
				return
			}

			var payload map[string]string
			if json.Unmarshal([]byte(msg.Payload), &payload) != nil {
				continue
			}

			msgType := payload["type"]
			if msgType == "" {
				msgType = "log"
			}
			sendSSE(w, flusher, msgType, payload)

			// Check if terminal status
			if msgType == "status" && isTerminalStatus(payload["status"]) {
				sendSSE(w, flusher, "done", map[string]string{"status": payload["status"]})
				return
			}
		}
	}
}

func sendSSE(w http.ResponseWriter, flusher http.Flusher, event string, data map[string]string) {
	jsonData, _ := json.Marshal(data)
	fmt.Fprintf(w, "event: %s\ndata: %s\n\n", event, jsonData)
	flusher.Flush()
}

func splitLines(s string) []string {
	var lines []string
	start := 0
	for i := 0; i < len(s); i++ {
		if s[i] == '\n' {
			lines = append(lines, s[start:i])
			start = i + 1
		}
	}
	if start < len(s) {
		lines = append(lines, s[start:])
	}
	return lines
}

func isTerminalStatus(status string) bool {
	return status == "running" || status == "failed" || status == "cancelled" || status == "rolled_back"
}

// Retry retries a failed deployment
func (h *DeploymentHandler) Retry(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	// Parse optional request body for git_token and callback_url
	var retryReq struct {
		GitToken    string `json:"git_token,omitempty"`
		CallbackURL string `json:"callback_url,omitempty"`
	}
	// Ignore decode errors - body is optional
	json.NewDecoder(r.Body).Decode(&retryReq)

	deployment, err := h.repo.GetDeployment(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusNotFound, "deployment not found")
		return
	}

	if deployment.Status != "failed" {
		respondError(w, http.StatusBadRequest, "only failed deployments can be retried")
		return
	}

	// Create new deployment
	newDeploymentID := uuid.New().String()
	insertQuery := `
		INSERT INTO deployments (id, application_id, status, commit_sha, commit_message, triggered_by, created_at)
		VALUES ($1, $2, 'pending', $3, $4, 'retry', NOW())
	`
	if _, err := h.db.Pool().Exec(r.Context(), insertQuery,
		newDeploymentID, deployment.ApplicationID, deployment.CommitSha, deployment.CommitMessage,
	); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to create retry deployment")
		return
	}

	// Get application config
	app, err := h.repo.GetApplication(r.Context(), deployment.ApplicationID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to get application")
		return
	}

	// Get environment variables
	envVars, _ := h.repo.GetEnvironmentVariablesAsMap(r.Context(), deployment.ApplicationID)

	// Queue build job
	job := &queue.BuildJob{
		DeploymentID:  newDeploymentID,
		ApplicationID: deployment.ApplicationID,
		GitRepository: app.GitRepository,
		GitBranch:     app.GitBranch,
		CommitSHA:     deployment.CommitSha,
		GitToken:      retryReq.GitToken,
		Type:          app.Type,
		BuildCommand:  app.BuildCommand,
		StartCommand:  app.StartCommand,
		RootDirectory: app.RootDirectory,
		Port:          app.Port,
		Replicas:      app.Replicas,
		CPULimit:      app.CPULimit,
		MemoryLimit:   app.MemoryLimit,
		Environment:   envVars,
		CallbackURL:   retryReq.CallbackURL,
	}

	if err := h.queue.Enqueue("builds", job); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to enqueue build")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{
		"id":              newDeploymentID,
		"original_id":     id,
		"status":          "pending",
	})
}
