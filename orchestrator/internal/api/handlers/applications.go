package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/easyti/easydeploy/orchestrator/internal/queue"
	"github.com/easyti/easydeploy/orchestrator/internal/repository"
	"github.com/easyti/easydeploy/orchestrator/internal/scheduler"
	"github.com/easyti/easydeploy/orchestrator/internal/traefik"
	"github.com/go-chi/chi/v5"
	"github.com/google/uuid"
)

type ApplicationHandler struct {
	db         *database.DB
	queue      *queue.RedisQueue
	scheduler  *scheduler.Scheduler
	repo       *repository.Repository
	traefikGen *traefik.ConfigGenerator
}

func NewApplicationHandler(
	db *database.DB,
	q *queue.RedisQueue,
	sched *scheduler.Scheduler,
	repo *repository.Repository,
	traefikGen *traefik.ConfigGenerator,
) *ApplicationHandler {
	return &ApplicationHandler{
		db:         db,
		queue:      q,
		scheduler:  sched,
		repo:       repo,
		traefikGen: traefikGen,
	}
}

// List returns all applications
func (h *ApplicationHandler) List(w http.ResponseWriter, r *http.Request) {
	userID := r.Header.Get("X-User-ID")
	if userID == "" {
		userID = "system" // Default for API key auth
	}

	apps, err := h.repo.ListApplications(r.Context(), userID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to list applications")
		return
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"applications": apps,
	})
}

// CreateApplicationRequest represents a create application request
type CreateApplicationRequest struct {
	Name             string            `json:"name"`
	GitRepository    string            `json:"git_repository"`
	GitBranch        string            `json:"git_branch"`
	Type             string            `json:"type"`
	RuntimeVersion   string            `json:"runtime_version"`
	BuildCommand     string            `json:"build_command"`
	StartCommand     string            `json:"start_command"`
	Port             int               `json:"port"`
	Replicas         int               `json:"replicas"`
	CPULimit         int               `json:"cpu_limit"`
	MemoryLimit      int               `json:"memory_limit"`
	HealthCheckPath  string            `json:"health_check_path"`
	AutoDeploy       bool              `json:"auto_deploy"`
	SSLEnabled       bool              `json:"ssl_enabled"`
	Environment      map[string]string `json:"environment"`
}

// Create creates a new application
func (h *ApplicationHandler) Create(w http.ResponseWriter, r *http.Request) {
	var req CreateApplicationRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondError(w, http.StatusBadRequest, "invalid request body")
		return
	}

	if req.Name == "" || req.GitRepository == "" {
		respondError(w, http.StatusBadRequest, "name and git_repository are required")
		return
	}

	// Set defaults
	if req.GitBranch == "" {
		req.GitBranch = "main"
	}
	if req.Port == 0 {
		req.Port = 8080
	}
	if req.Replicas == 0 {
		req.Replicas = 1
	}
	if req.CPULimit == 0 {
		req.CPULimit = 500
	}
	if req.MemoryLimit == 0 {
		req.MemoryLimit = 512
	}
	if req.HealthCheckPath == "" {
		req.HealthCheckPath = "/health"
	}

	// Generate slug from name
	slug := generateSlug(req.Name)

	id := uuid.New().String()
	userID := r.Header.Get("X-User-ID")
	if userID == "" {
		userID = "system"
	}

	query := `
		INSERT INTO applications (
			id, user_id, name, slug, git_repository, git_branch, type, runtime_version,
			build_command, start_command, port, replicas, cpu_limit, memory_limit,
			health_check_path, health_check_interval, auto_deploy, ssl_enabled, status,
			created_at, updated_at
		) VALUES (
			$1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, 'active', NOW(), NOW()
		)
	`

	_, err := h.db.Pool().Exec(r.Context(), query,
		id, userID, req.Name, slug, req.GitRepository, req.GitBranch, req.Type, req.RuntimeVersion,
		req.BuildCommand, req.StartCommand, req.Port, req.Replicas, req.CPULimit, req.MemoryLimit,
		req.HealthCheckPath, 30, req.AutoDeploy, req.SSLEnabled,
	)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to create application")
		return
	}

	// Save environment variables
	if len(req.Environment) > 0 {
		for key, value := range req.Environment {
			envQuery := `
				INSERT INTO environment_variables (id, application_id, key, value, is_secret, created_at, updated_at)
				VALUES (gen_random_uuid(), $1, $2, $3, false, NOW(), NOW())
			`
			h.db.Pool().Exec(r.Context(), envQuery, id, key, value)
		}
	}

	respondJSON(w, http.StatusCreated, map[string]interface{}{
		"id":   id,
		"slug": slug,
	})
}

// Get returns an application by ID
func (h *ApplicationHandler) Get(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	app, err := h.repo.GetApplication(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusNotFound, "application not found")
		return
	}

	respondJSON(w, http.StatusOK, app)
}

// Update updates an application
func (h *ApplicationHandler) Update(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	var req CreateApplicationRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondError(w, http.StatusBadRequest, "invalid request body")
		return
	}

	query := `
		UPDATE applications
		SET name = COALESCE(NULLIF($2, ''), name),
		    git_repository = COALESCE(NULLIF($3, ''), git_repository),
		    git_branch = COALESCE(NULLIF($4, ''), git_branch),
		    type = COALESCE(NULLIF($5, ''), type),
		    runtime_version = COALESCE(NULLIF($6, ''), runtime_version),
		    build_command = $7,
		    start_command = $8,
		    port = COALESCE(NULLIF($9, 0), port),
		    replicas = COALESCE(NULLIF($10, 0), replicas),
		    cpu_limit = COALESCE(NULLIF($11, 0), cpu_limit),
		    memory_limit = COALESCE(NULLIF($12, 0), memory_limit),
		    health_check_path = COALESCE(NULLIF($13, ''), health_check_path),
		    auto_deploy = $14,
		    ssl_enabled = $15,
		    updated_at = NOW()
		WHERE id = $1
	`

	_, err := h.db.Pool().Exec(r.Context(), query,
		id, req.Name, req.GitRepository, req.GitBranch, req.Type, req.RuntimeVersion,
		req.BuildCommand, req.StartCommand, req.Port, req.Replicas, req.CPULimit, req.MemoryLimit,
		req.HealthCheckPath, req.AutoDeploy, req.SSLEnabled,
	)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to update application")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{"id": id})
}

// Delete deletes an application
func (h *ApplicationHandler) Delete(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	// Stop all containers first
	containers, _ := h.repo.ListContainersByApplication(r.Context(), id)
	for _, c := range containers {
		h.scheduler.StopContainer(r.Context(), c.ID)
	}

	// Remove Traefik config
	h.traefikGen.RemoveConfig(id)

	// Delete application (cascades to related records)
	query := `DELETE FROM applications WHERE id = $1`
	_, err := h.db.Pool().Exec(r.Context(), query, id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to delete application")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{"id": id, "status": "deleted"})
}

// Deploy triggers a deployment for an application
func (h *ApplicationHandler) Deploy(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	// Parse optional request body for git_token and callback_url
	var req struct {
		GitToken    string `json:"git_token,omitempty"`
		CallbackURL string `json:"callback_url,omitempty"`
	}
	json.NewDecoder(r.Body).Decode(&req) // ignore errors, body is optional

	app, err := h.repo.GetApplication(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusNotFound, "application not found")
		return
	}

	// Create deployment
	deploymentID := uuid.New().String()
	deployQuery := `
		INSERT INTO deployments (id, application_id, status, triggered_by, created_at)
		VALUES ($1, $2, 'pending', 'manual', NOW())
	`
	if _, err := h.db.Pool().Exec(r.Context(), deployQuery, deploymentID, id); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to create deployment")
		return
	}

	// Get environment variables
	envVars, _ := h.repo.GetEnvironmentVariablesAsMap(r.Context(), id)

	// Use git_token from request body (decrypted by panel) if provided
	gitToken := req.GitToken

	// Queue build job
	job := queue.BuildJob{
		DeploymentID:  deploymentID,
		ApplicationID: id,
		GitRepository: app.GitRepository,
		GitBranch:     app.GitBranch,
		GitToken:      gitToken,
		Type:          app.Type,
		BuildCommand:  app.BuildCommand,
		StartCommand:  app.StartCommand,
		RootDirectory: app.RootDirectory,
		Port:          app.Port,
		Replicas:      app.Replicas,
		CPULimit:      app.CPULimit,
		MemoryLimit:   app.MemoryLimit,
		Environment:   envVars,
		CallbackURL:   req.CallbackURL,
	}

	if err := h.queue.Enqueue("builds", job); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to queue deployment")
		return
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"deployment_id": deploymentID,
		"status":        "pending",
	})
}

// Scale scales an application
func (h *ApplicationHandler) Scale(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	var req struct {
		Replicas int `json:"replicas"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondError(w, http.StatusBadRequest, "invalid request body")
		return
	}

	if req.Replicas < 0 || req.Replicas > 10 {
		respondError(w, http.StatusBadRequest, "replicas must be between 0 and 10")
		return
	}

	// Update application replicas
	query := `UPDATE applications SET replicas = $1, updated_at = NOW() WHERE id = $2`
	if _, err := h.db.Pool().Exec(r.Context(), query, req.Replicas, id); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to scale application")
		return
	}

	// Trigger redeployment to adjust container count
	// This would be handled by the scheduler

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"id":       id,
		"replicas": req.Replicas,
	})
}

// Stop stops all containers for an application
func (h *ApplicationHandler) Stop(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	containers, err := h.repo.ListContainersByApplication(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to get containers")
		return
	}

	for _, c := range containers {
		h.scheduler.StopContainer(r.Context(), c.ID)
	}

	// Update application status
	h.repo.UpdateApplicationStatus(r.Context(), id, "stopped")

	respondJSON(w, http.StatusOK, map[string]string{
		"id":     id,
		"status": "stopped",
	})
}

// Restart restarts all containers for an application
func (h *ApplicationHandler) Restart(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	containers, err := h.repo.ListContainersByApplication(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to get containers")
		return
	}

	for _, c := range containers {
		h.scheduler.RestartContainer(r.Context(), c.ID)
	}

	respondJSON(w, http.StatusOK, map[string]string{
		"id":     id,
		"status": "restarting",
	})
}

// Rollback rolls back to a previous deployment
func (h *ApplicationHandler) Rollback(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	var req struct {
		DeploymentID string `json:"deployment_id"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondError(w, http.StatusBadRequest, "invalid request body")
		return
	}

	// Get the target deployment
	deployment, err := h.repo.GetDeployment(r.Context(), req.DeploymentID)
	if err != nil {
		respondError(w, http.StatusNotFound, "deployment not found")
		return
	}

	if deployment.ApplicationID != id {
		respondError(w, http.StatusBadRequest, "deployment does not belong to this application")
		return
	}

	// Create new deployment based on old one
	newDeploymentID := uuid.New().String()
	query := `
		INSERT INTO deployments (id, application_id, status, commit_sha, commit_message, image_name, image_tag, triggered_by, created_at)
		VALUES ($1, $2, 'pending', $3, $4, $5, $6, 'rollback', NOW())
	`
	if _, err := h.db.Pool().Exec(r.Context(), query,
		newDeploymentID, id, deployment.CommitSha, "Rollback to "+deployment.CommitSha[:8],
		deployment.ImageName, deployment.ImageTag,
	); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to create rollback deployment")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{
		"id":                id,
		"deployment_id":     newDeploymentID,
		"rollback_from":     req.DeploymentID,
	})
}

// GetLogs returns logs for an application
func (h *ApplicationHandler) GetLogs(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	linesStr := r.URL.Query().Get("lines")
	lines := 100
	if linesStr != "" {
		if l, err := strconv.Atoi(linesStr); err == nil {
			lines = l
		}
	}

	containers, err := h.repo.ListContainersByApplication(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to get containers")
		return
	}

	allLogs := make(map[string]string)
	for _, c := range containers {
		logs, err := h.scheduler.GetContainerLogs(r.Context(), c.ID, lines)
		if err == nil {
			allLogs[c.Name] = logs
		}
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"id":   id,
		"logs": allLogs,
	})
}

// GetMetrics returns metrics for an application
func (h *ApplicationHandler) GetMetrics(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	query := `
		SELECT AVG(cpu_percent), AVG(memory_percent), SUM(network_in), SUM(network_out)
		FROM resource_usages ru
		JOIN containers c ON ru.container_id = c.id
		WHERE c.application_id = $1 AND ru.created_at > NOW() - INTERVAL '1 hour'
	`

	var cpuPercent, memPercent float64
	var networkIn, networkOut int64
	h.db.Pool().QueryRow(r.Context(), query, id).Scan(&cpuPercent, &memPercent, &networkIn, &networkOut)

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"id": id,
		"metrics": map[string]interface{}{
			"cpu_percent":    cpuPercent,
			"memory_percent": memPercent,
			"network_in":     networkIn,
			"network_out":    networkOut,
		},
	})
}

// GetStats returns statistics for an application
func (h *ApplicationHandler) GetStats(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	stats, err := h.repo.GetApplicationStats(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to get stats")
		return
	}

	respondJSON(w, http.StatusOK, stats)
}

// GetEnvVars returns environment variables for an application
func (h *ApplicationHandler) GetEnvVars(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	vars, err := h.repo.GetEnvironmentVariables(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to get environment variables")
		return
	}

	// Mask secret values
	for _, v := range vars {
		if v.IsSecret {
			v.Value = "********"
		}
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"environment": vars,
	})
}

// SetEnvVars sets environment variables for an application
func (h *ApplicationHandler) SetEnvVars(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	var req struct {
		Variables map[string]string `json:"variables"`
		Secrets   map[string]string `json:"secrets"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondError(w, http.StatusBadRequest, "invalid request body")
		return
	}

	// Upsert regular variables
	for key, value := range req.Variables {
		query := `
			INSERT INTO environment_variables (id, application_id, key, value, is_secret, created_at, updated_at)
			VALUES (gen_random_uuid(), $1, $2, $3, false, NOW(), NOW())
			ON CONFLICT (application_id, key) DO UPDATE
			SET value = $3, updated_at = NOW()
		`
		h.db.Pool().Exec(r.Context(), query, id, key, value)
	}

	// Upsert secrets
	for key, value := range req.Secrets {
		query := `
			INSERT INTO environment_variables (id, application_id, key, value, is_secret, created_at, updated_at)
			VALUES (gen_random_uuid(), $1, $2, $3, true, NOW(), NOW())
			ON CONFLICT (application_id, key) DO UPDATE
			SET value = $3, is_secret = true, updated_at = NOW()
		`
		h.db.Pool().Exec(r.Context(), query, id, key, value)
	}

	respondJSON(w, http.StatusOK, map[string]string{"status": "ok"})
}

// DeleteEnvVar deletes an environment variable
func (h *ApplicationHandler) DeleteEnvVar(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	key := chi.URLParam(r, "key")

	query := `DELETE FROM environment_variables WHERE application_id = $1 AND key = $2`
	if _, err := h.db.Pool().Exec(r.Context(), query, id, key); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to delete environment variable")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{"status": "deleted"})
}

// GetDomains returns domains for an application
func (h *ApplicationHandler) GetDomains(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	domains, err := h.repo.GetDomainsByApplication(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to get domains")
		return
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"domains": domains,
	})
}

// AddDomain adds a domain to an application
func (h *ApplicationHandler) AddDomain(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	var req struct {
		Domain    string `json:"domain"`
		IsPrimary bool   `json:"is_primary"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondError(w, http.StatusBadRequest, "invalid request body")
		return
	}

	domainID := uuid.New().String()
	query := `
		INSERT INTO domains (id, application_id, domain, is_primary, verified, ssl_status, created_at, updated_at)
		VALUES ($1, $2, $3, $4, false, 'pending', NOW(), NOW())
	`
	if _, err := h.db.Pool().Exec(r.Context(), query, domainID, id, req.Domain, req.IsPrimary); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to add domain")
		return
	}

	// Update Traefik config
	h.traefikGen.GenerateConfig(r.Context(), id)

	respondJSON(w, http.StatusCreated, map[string]string{
		"id":     domainID,
		"domain": req.Domain,
	})
}

// RemoveDomain removes a domain from an application
func (h *ApplicationHandler) RemoveDomain(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	domainID := chi.URLParam(r, "domainId")

	query := `DELETE FROM domains WHERE id = $1 AND application_id = $2`
	if _, err := h.db.Pool().Exec(r.Context(), query, domainID, id); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to remove domain")
		return
	}

	// Update Traefik config
	h.traefikGen.GenerateConfig(r.Context(), id)

	respondJSON(w, http.StatusOK, map[string]string{"status": "deleted"})
}

// ListDeployments returns deployments for an application
func (h *ApplicationHandler) ListDeployments(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")
	limitStr := r.URL.Query().Get("limit")
	limit := 20
	if limitStr != "" {
		if l, err := strconv.Atoi(limitStr); err == nil {
			limit = l
		}
	}

	deployments, err := h.repo.ListDeployments(r.Context(), id, limit)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to get deployments")
		return
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"deployments": deployments,
	})
}

// GetLatestDeployment returns the latest deployment for an application
func (h *ApplicationHandler) GetLatestDeployment(w http.ResponseWriter, r *http.Request) {
	id := chi.URLParam(r, "id")

	deployment, err := h.repo.GetLatestDeployment(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusNotFound, "no deployments found")
		return
	}

	respondJSON(w, http.StatusOK, deployment)
}

// Helper functions
func respondJSON(w http.ResponseWriter, status int, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

func respondError(w http.ResponseWriter, status int, message string) {
	respondJSON(w, status, map[string]string{"error": message})
}

func generateSlug(name string) string {
	// Simple slug generation - replace spaces with dashes, lowercase
	slug := ""
	for _, r := range name {
		if r >= 'a' && r <= 'z' {
			slug += string(r)
		} else if r >= 'A' && r <= 'Z' {
			slug += string(r + 32)
		} else if r >= '0' && r <= '9' {
			slug += string(r)
		} else if r == ' ' || r == '-' {
			slug += "-"
		}
	}
	return slug
}
