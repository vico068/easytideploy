package handlers

import (
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"io"
	"net/http"
	"strings"

	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/easyti/easydeploy/orchestrator/internal/queue"
	"github.com/easyti/easydeploy/orchestrator/internal/repository"
	"github.com/rs/zerolog/log"
)

// WebhookHandler handles git webhooks
type WebhookHandler struct {
	db    *database.DB
	queue *queue.RedisQueue
	repo  *repository.Repository
}

// NewWebhookHandler creates a new WebhookHandler
func NewWebhookHandler(db *database.DB, q *queue.RedisQueue, repo *repository.Repository) *WebhookHandler {
	return &WebhookHandler{
		db:    db,
		queue: q,
		repo:  repo,
	}
}

// GitHubPushPayload represents a GitHub push event
type GitHubPushPayload struct {
	Ref        string `json:"ref"`
	Before     string `json:"before"`
	After      string `json:"after"`
	Repository struct {
		ID       int64  `json:"id"`
		Name     string `json:"name"`
		FullName string `json:"full_name"`
		CloneURL string `json:"clone_url"`
		SSHURL   string `json:"ssh_url"`
	} `json:"repository"`
	Pusher struct {
		Name  string `json:"name"`
		Email string `json:"email"`
	} `json:"pusher"`
	HeadCommit struct {
		ID      string `json:"id"`
		Message string `json:"message"`
		Author  struct {
			Name  string `json:"name"`
			Email string `json:"email"`
		} `json:"author"`
	} `json:"head_commit"`
}

// HandleGitHub handles GitHub webhooks
func (h *WebhookHandler) HandleGitHub(w http.ResponseWriter, r *http.Request) {
	event := r.Header.Get("X-GitHub-Event")
	if event != "push" && event != "ping" {
		respondJSON(w, http.StatusOK, map[string]string{"message": "event ignored"})
		return
	}

	if event == "ping" {
		respondJSON(w, http.StatusOK, map[string]string{"message": "pong"})
		return
	}

	// Read body
	body, err := io.ReadAll(r.Body)
	if err != nil {
		respondError(w, http.StatusBadRequest, "failed to read request body")
		return
	}

	// Parse payload
	var payload GitHubPushPayload
	if err := json.Unmarshal(body, &payload); err != nil {
		respondError(w, http.StatusBadRequest, "invalid payload")
		return
	}

	// Extract branch from ref
	branch := strings.TrimPrefix(payload.Ref, "refs/heads/")

	// Find application by repository URL
	app, err := h.findApplicationByRepo(r.Context(), payload.Repository.CloneURL, branch)
	if err != nil {
		log.Debug().Str("repo", payload.Repository.CloneURL).Msg("No application found for repository")
		respondJSON(w, http.StatusOK, map[string]string{"message": "no matching application"})
		return
	}

	// Verify webhook signature if secret is configured
	signature := r.Header.Get("X-Hub-Signature-256")
	if app.WebhookSecret != "" && signature != "" {
		if !h.verifyGitHubSignature(body, signature, app.WebhookSecret) {
			respondError(w, http.StatusUnauthorized, "invalid signature")
			return
		}
	}

	// Check if auto-deploy is enabled
	if !app.AutoDeploy {
		respondJSON(w, http.StatusOK, map[string]string{"message": "auto-deploy disabled"})
		return
	}

	// Create deployment
	deploymentID, err := h.createDeployment(r.Context(), app.ID, payload.After, payload.HeadCommit.Message, "webhook:github")
	if err != nil {
		log.Error().Err(err).Msg("Failed to create deployment")
		respondError(w, http.StatusInternalServerError, "failed to create deployment")
		return
	}

	// Queue build job
	job := queue.BuildJob{
		DeploymentID:  deploymentID,
		ApplicationID: app.ID,
		GitRepository: payload.Repository.CloneURL,
		GitBranch:     branch,
		CommitSHA:     payload.After,
		Type:          app.Type,
		BuildCommand:  app.BuildCommand,
		StartCommand:  app.StartCommand,
		Port:          app.Port,
		Replicas:      app.Replicas,
		CPULimit:      app.CPULimit,
		MemoryLimit:   app.MemoryLimit,
	}

	if err := h.queue.Enqueue("builds", job); err != nil {
		log.Error().Err(err).Msg("Failed to enqueue build job")
		respondError(w, http.StatusInternalServerError, "failed to queue deployment")
		return
	}

	log.Info().
		Str("app", app.ID).
		Str("deployment", deploymentID).
		Str("commit", payload.After[:8]).
		Msg("Deployment triggered by GitHub webhook")

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"message":       "deployment queued",
		"deployment_id": deploymentID,
	})
}

// GitLabPushPayload represents a GitLab push event
type GitLabPushPayload struct {
	ObjectKind string `json:"object_kind"`
	Ref        string `json:"ref"`
	Before     string `json:"before"`
	After      string `json:"after"`
	Project    struct {
		ID        int64  `json:"id"`
		Name      string `json:"name"`
		Namespace string `json:"namespace"`
		GitHTTPURL string `json:"git_http_url"`
		GitSSHURL  string `json:"git_ssh_url"`
	} `json:"project"`
	Commits []struct {
		ID      string `json:"id"`
		Message string `json:"message"`
		Author  struct {
			Name  string `json:"name"`
			Email string `json:"email"`
		} `json:"author"`
	} `json:"commits"`
}

// HandleGitLab handles GitLab webhooks
func (h *WebhookHandler) HandleGitLab(w http.ResponseWriter, r *http.Request) {
	event := r.Header.Get("X-Gitlab-Event")
	if event != "Push Hook" {
		respondJSON(w, http.StatusOK, map[string]string{"message": "event ignored"})
		return
	}

	body, err := io.ReadAll(r.Body)
	if err != nil {
		respondError(w, http.StatusBadRequest, "failed to read request body")
		return
	}

	var payload GitLabPushPayload
	if err := json.Unmarshal(body, &payload); err != nil {
		respondError(w, http.StatusBadRequest, "invalid payload")
		return
	}

	branch := strings.TrimPrefix(payload.Ref, "refs/heads/")

	app, err := h.findApplicationByRepo(r.Context(), payload.Project.GitHTTPURL, branch)
	if err != nil {
		respondJSON(w, http.StatusOK, map[string]string{"message": "no matching application"})
		return
	}

	// Verify token
	token := r.Header.Get("X-Gitlab-Token")
	if app.WebhookSecret != "" && token != app.WebhookSecret {
		respondError(w, http.StatusUnauthorized, "invalid token")
		return
	}

	if !app.AutoDeploy {
		respondJSON(w, http.StatusOK, map[string]string{"message": "auto-deploy disabled"})
		return
	}

	commitMessage := ""
	if len(payload.Commits) > 0 {
		commitMessage = payload.Commits[0].Message
	}

	deploymentID, err := h.createDeployment(r.Context(), app.ID, payload.After, commitMessage, "webhook:gitlab")
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to create deployment")
		return
	}

	job := queue.BuildJob{
		DeploymentID:  deploymentID,
		ApplicationID: app.ID,
		GitRepository: payload.Project.GitHTTPURL,
		GitBranch:     branch,
		CommitSHA:     payload.After,
		Type:          app.Type,
		BuildCommand:  app.BuildCommand,
		StartCommand:  app.StartCommand,
		Port:          app.Port,
		Replicas:      app.Replicas,
		CPULimit:      app.CPULimit,
		MemoryLimit:   app.MemoryLimit,
	}

	if err := h.queue.Enqueue("builds", job); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to queue deployment")
		return
	}

	log.Info().Str("app", app.ID).Str("deployment", deploymentID).Msg("Deployment triggered by GitLab webhook")

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"message":       "deployment queued",
		"deployment_id": deploymentID,
	})
}

// BitbucketPushPayload represents a Bitbucket push event
type BitbucketPushPayload struct {
	Push struct {
		Changes []struct {
			New struct {
				Type   string `json:"type"`
				Name   string `json:"name"`
				Target struct {
					Hash    string `json:"hash"`
					Message string `json:"message"`
				} `json:"target"`
			} `json:"new"`
		} `json:"changes"`
	} `json:"push"`
	Repository struct {
		FullName string `json:"full_name"`
		Links    struct {
			HTML struct {
				Href string `json:"href"`
			} `json:"html"`
		} `json:"links"`
	} `json:"repository"`
}

// HandleBitbucket handles Bitbucket webhooks
func (h *WebhookHandler) HandleBitbucket(w http.ResponseWriter, r *http.Request) {
	event := r.Header.Get("X-Event-Key")
	if event != "repo:push" {
		respondJSON(w, http.StatusOK, map[string]string{"message": "event ignored"})
		return
	}

	body, err := io.ReadAll(r.Body)
	if err != nil {
		respondError(w, http.StatusBadRequest, "failed to read request body")
		return
	}

	var payload BitbucketPushPayload
	if err := json.Unmarshal(body, &payload); err != nil {
		respondError(w, http.StatusBadRequest, "invalid payload")
		return
	}

	if len(payload.Push.Changes) == 0 {
		respondJSON(w, http.StatusOK, map[string]string{"message": "no changes"})
		return
	}

	change := payload.Push.Changes[0]
	branch := change.New.Name
	commitSHA := change.New.Target.Hash
	commitMessage := change.New.Target.Message

	repoURL := "https://bitbucket.org/" + payload.Repository.FullName + ".git"

	app, err := h.findApplicationByRepo(r.Context(), repoURL, branch)
	if err != nil {
		respondJSON(w, http.StatusOK, map[string]string{"message": "no matching application"})
		return
	}

	if !app.AutoDeploy {
		respondJSON(w, http.StatusOK, map[string]string{"message": "auto-deploy disabled"})
		return
	}

	deploymentID, err := h.createDeployment(r.Context(), app.ID, commitSHA, commitMessage, "webhook:bitbucket")
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to create deployment")
		return
	}

	job := queue.BuildJob{
		DeploymentID:  deploymentID,
		ApplicationID: app.ID,
		GitRepository: repoURL,
		GitBranch:     branch,
		CommitSHA:     commitSHA,
		Type:          app.Type,
		BuildCommand:  app.BuildCommand,
		StartCommand:  app.StartCommand,
		Port:          app.Port,
		Replicas:      app.Replicas,
		CPULimit:      app.CPULimit,
		MemoryLimit:   app.MemoryLimit,
	}

	if err := h.queue.Enqueue("builds", job); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to queue deployment")
		return
	}

	log.Info().Str("app", app.ID).Str("deployment", deploymentID).Msg("Deployment triggered by Bitbucket webhook")

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"message":       "deployment queued",
		"deployment_id": deploymentID,
	})
}

// Application with webhook info
type webhookApp struct {
	ID            string
	Type          string
	BuildCommand  string
	StartCommand  string
	Port          int
	Replicas      int
	CPULimit      int
	MemoryLimit   int
	AutoDeploy    bool
	WebhookSecret string
}

func (h *WebhookHandler) findApplicationByRepo(ctx context.Context, repoURL, branch string) (*webhookApp, error) {
	query := `
		SELECT id, type, build_command, start_command, port, replicas, cpu_limit, memory_limit, auto_deploy, COALESCE(webhook_secret, '')
		FROM applications
		WHERE git_repository = $1 AND git_branch = $2 AND status = 'active'
		LIMIT 1
	`

	var app webhookApp
	err := h.db.Pool().QueryRow(ctx, query, repoURL, branch).Scan(
		&app.ID, &app.Type, &app.BuildCommand, &app.StartCommand, &app.Port,
		&app.Replicas, &app.CPULimit, &app.MemoryLimit, &app.AutoDeploy, &app.WebhookSecret,
	)
	if err != nil {
		return nil, err
	}
	return &app, nil
}

func (h *WebhookHandler) verifyGitHubSignature(payload []byte, signature, secret string) bool {
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write(payload)
	expected := "sha256=" + hex.EncodeToString(mac.Sum(nil))
	return hmac.Equal([]byte(expected), []byte(signature))
}

func (h *WebhookHandler) createDeployment(ctx context.Context, appID, commitSHA, commitMessage, triggeredBy string) (string, error) {
	var deploymentID string
	query := `
		INSERT INTO deployments (id, application_id, status, commit_sha, commit_message, triggered_by, created_at)
		VALUES (gen_random_uuid(), $1, 'pending', $2, $3, $4, NOW())
		RETURNING id
	`
	err := h.db.Pool().QueryRow(ctx, query, appID, commitSHA, commitMessage, triggeredBy).Scan(&deploymentID)
	return deploymentID, err
}
