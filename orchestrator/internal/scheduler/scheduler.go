package scheduler

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"time"

	"github.com/easyti/easydeploy/orchestrator/internal/config"
	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/easyti/easydeploy/orchestrator/internal/docker"
	"github.com/easyti/easydeploy/orchestrator/internal/git"
	"github.com/easyti/easydeploy/orchestrator/internal/metrics"
	"github.com/easyti/easydeploy/orchestrator/internal/queue"
	"github.com/easyti/easydeploy/orchestrator/internal/traefik"
	"github.com/easyti/easydeploy/orchestrator/pkg/buildpack"
	"github.com/google/uuid"
	"github.com/rs/zerolog"
	"github.com/rs/zerolog/log"
	"gopkg.in/yaml.v3"
)

// DeploymentStatus represents the status of a deployment
type DeploymentStatus string

const (
	StatusPending   DeploymentStatus = "pending"
	StatusBuilding  DeploymentStatus = "building"
	StatusDeploying DeploymentStatus = "deploying"
	StatusRunning   DeploymentStatus = "running"
	StatusFailed    DeploymentStatus = "failed"
	StatusCancelled DeploymentStatus = "cancelled"
)

// Scheduler manages build jobs and container health
type Scheduler struct {
	db             *database.DB
	queue          *queue.RedisQueue
	cfg            *config.Config
	gitCloner      *git.Cloner
	imageBuilder   *docker.ImageBuilder
	agentClients   map[string]*AgentClient
	traefikGen     *traefik.ConfigGenerator
	traefikScraper *metrics.TraefikScraper
	mu             sync.RWMutex
	healthTicker   *time.Ticker
	stopCh         chan struct{}
	wg             sync.WaitGroup
}

// SetTraefikGenerator sets the Traefik config generator (called after initialization)
func (s *Scheduler) SetTraefikGenerator(gen *traefik.ConfigGenerator) {
	s.traefikGen = gen
}

// New creates a new Scheduler instance
func New(db *database.DB, q *queue.RedisQueue, cfg *config.Config) (*Scheduler, error) {
	// Initialize git cloner
	gitCloner := git.NewCloner(filepath.Join(cfg.DataDir, "repos"))

	// Initialize image builder
	imageBuilder, err := docker.NewImageBuilder(filepath.Join(cfg.DataDir, "buildpacks"))
	if err != nil {
		return nil, fmt.Errorf("failed to create image builder: %w", err)
	}

	return &Scheduler{
		db:             db,
		queue:          q,
		cfg:            cfg,
		gitCloner:      gitCloner,
		imageBuilder:   imageBuilder,
		agentClients:   make(map[string]*AgentClient),
		traefikScraper: metrics.NewTraefikScraper(cfg.TraefikAPIURL),
		stopCh:         make(chan struct{}),
	}, nil
}

// Start begins processing jobs and health checks
func (s *Scheduler) Start() {
	// Process build jobs
	s.wg.Add(1)
	go s.processBuildJobs()

	// Health checks every 30 seconds
	s.healthTicker = time.NewTicker(30 * time.Second)
	s.wg.Add(1)
	go s.runHealthChecks()

	// Cleanup old repos every hour
	s.wg.Add(1)
	go s.cleanupOldRepos()

	// Metrics collection every 30 seconds
	s.wg.Add(1)
	go s.collectMetrics()

	log.Info().Msg("Scheduler started")
}

// Stop gracefully stops the scheduler
func (s *Scheduler) Stop() {
	close(s.stopCh)
	if s.healthTicker != nil {
		s.healthTicker.Stop()
	}

	// Close agent clients
	s.mu.Lock()
	for _, client := range s.agentClients {
		client.Close()
	}
	s.mu.Unlock()

	// Close image builder
	if s.imageBuilder != nil {
		s.imageBuilder.Close()
	}

	s.wg.Wait()
	log.Info().Msg("Scheduler stopped")
}

func (s *Scheduler) processBuildJobs() {
	defer s.wg.Done()

	for {
		select {
		case <-s.stopCh:
			return
		default:
			data, err := s.queue.Dequeue("builds", 5*time.Second)
			if err != nil {
				log.Error().Err(err).Msg("Failed to dequeue build job")
				continue
			}

			if data == nil {
				continue
			}

			s.handleBuildJob(data)
		}
	}
}

func (s *Scheduler) handleBuildJob(data []byte) {
	var job queue.BuildJob
	if err := json.Unmarshal(data, &job); err != nil {
		log.Error().Err(err).Msg("Failed to unmarshal build job")
		return
	}

	ctx := context.Background()
	logger := log.With().
		Str("deployment_id", job.DeploymentID).
		Str("application_id", job.ApplicationID).
		Logger()

	logger.Info().Msg("Starting build job")

	// Enrich job with data from panel if git_token is missing
	// The panel decrypts the token and returns it along with environment variables
	if job.GitToken == "" && job.GitRepository != "" {
		panelApp, err := s.fetchAppFromPanel(ctx, job.ApplicationID)
		if err != nil {
			logger.Warn().Err(err).Msg("Could not fetch app from panel, proceeding without token")
		} else {
			if panelApp.GitToken != "" {
				job.GitToken = panelApp.GitToken
			}
			if len(job.Environment) == 0 && len(panelApp.Environment) > 0 {
				job.Environment = panelApp.Environment
			}
			if job.CallbackURL == "" {
				job.CallbackURL = fmt.Sprintf("%s/api/internal/deployments/%s/status", s.cfg.PanelURL, job.DeploymentID)
			}
		}
	}

	// Update deployment status to building
	if err := s.updateDeploymentStatus(ctx, job.DeploymentID, StatusBuilding, ""); err != nil {
		logger.Error().Err(err).Msg("Failed to update deployment status")
	}
	go s.notifyPanel(job.CallbackURL, StatusBuilding, "", "", "", "")

	// Execute build pipeline
	result, err := s.executeBuildPipeline(ctx, &job, &logger)
	if err != nil {
		logger.Error().Err(err).Msg("Build pipeline failed")
		s.updateDeploymentStatus(ctx, job.DeploymentID, StatusFailed, err.Error())
		s.publishStatusEvent(job.DeploymentID, string(StatusFailed), err.Error())
		// Include partial build logs even on failure (result is non-nil with logs)
		buildLogs := ""
		if result != nil {
			buildLogs = result.BuildLogs
		}
		go s.notifyPanel(job.CallbackURL, StatusFailed, err.Error(), buildLogs, "", "")
		return
	}

	// Save commit info to deployment
	if result.CommitSHA != "" || result.CommitMsg != "" {
		if err := s.saveCommitInfo(ctx, job.DeploymentID, result.CommitSHA, result.CommitMsg); err != nil {
			logger.Error().Err(err).Msg("Failed to save commit info")
		}
	}

	// Deploy containers
	s.publishStage(job.DeploymentID, "deploy", "running")
	if err := s.deployContainers(ctx, &job, result, &logger); err != nil {
		logger.Error().Err(err).Msg("Deployment failed")
		s.publishStage(job.DeploymentID, "deploy", "failed")
		s.updateDeploymentStatus(ctx, job.DeploymentID, StatusFailed, err.Error())
		s.publishStatusEvent(job.DeploymentID, string(StatusFailed), err.Error())
		go s.notifyPanel(job.CallbackURL, StatusFailed, err.Error(), result.BuildLogs, result.CommitSHA, result.CommitMsg)
		return
	}

	s.publishStage(job.DeploymentID, "deploy", "success")
	logger.Info().Msg("Build job completed successfully")
	s.updateDeploymentStatus(ctx, job.DeploymentID, StatusRunning, "")
	s.publishStatusEvent(job.DeploymentID, string(StatusRunning), "")
	go s.notifyPanel(job.CallbackURL, StatusRunning, "", result.BuildLogs, result.CommitSHA, result.CommitMsg)
}

// BuildResult contains the result of a build
type BuildResult struct {
	ImageName      string // registry-prefixed name used for push (DockerRegistry)
	AgentImageName string // registry-prefixed name for agents to pull (AgentRegistry)
	ImageTag       string
	CommitSHA      string
	CommitMsg      string
	BuildLogs      string
	AppType        string
	AppVersion     string
	Port           int
}

func (s *Scheduler) executeBuildPipeline(ctx context.Context, job *queue.BuildJob, logger *zerolog.Logger) (*BuildResult, error) {
	result := &BuildResult{}

	// Step 1: Clone repository
	logger.Info().Str("repo", job.GitRepository).Str("branch", job.GitBranch).Msg("Cloning repository")
	s.publishStage(job.DeploymentID, "clone", "running")

	cloneOpts := git.CloneOptions{
		URL:        job.GitRepository,
		Branch:     job.GitBranch,
		CommitHash: job.CommitSHA,
		Depth:      1,
	}
	if job.GitToken != "" {
		cloneOpts.Username = "oauth2"
		cloneOpts.Password = job.GitToken
	}

	repoPath, err := s.gitCloner.Clone(ctx, cloneOpts)
	if err != nil {
		s.publishStage(job.DeploymentID, "clone", "failed")
		return nil, fmt.Errorf("failed to clone repository: %w", err)
	}
	s.publishStage(job.DeploymentID, "clone", "success")
	defer s.gitCloner.Cleanup(repoPath)

	// Get commit info
	commitInfo, err := s.gitCloner.GetCommitInfo(ctx, repoPath)
	if err != nil {
		logger.Warn().Err(err).Msg("Failed to get commit info")
	} else {
		result.CommitSHA = commitInfo.Hash
		result.CommitMsg = commitInfo.Message
	}

	// Apply root directory if specified
	buildPath := repoPath
	if job.RootDirectory != "" && job.RootDirectory != "/" {
		subDir := strings.TrimPrefix(job.RootDirectory, "/")
		buildPath = filepath.Join(repoPath, subDir)
		if _, err := os.Stat(buildPath); os.IsNotExist(err) {
			return nil, fmt.Errorf("root_directory %q does not exist in repository", job.RootDirectory)
		}
		logger.Info().Str("root_directory", job.RootDirectory).Msg("Using subdirectory as build root")
	}

	// Step 2: Detect app type if not specified
	appType := job.Type
	appVersion := ""

	if appType == "" || appType == "auto" {
		logger.Info().Msg("Detecting application type")
		detection, err := buildpack.Detect(buildPath)
		if err != nil {
			return nil, fmt.Errorf("failed to detect app type: %w", err)
		}

		appType = string(detection.Type)
		appVersion = detection.Version
		result.Port = detection.Port

		// Use detected commands if not specified
		if job.BuildCommand == "" {
			job.BuildCommand = detection.BuildCommand
		}
		if job.StartCommand == "" {
			job.StartCommand = detection.StartCommand
		}
		if job.Port == 0 {
			job.Port = detection.Port
		}

		logger.Info().
			Str("type", appType).
			Str("version", appVersion).
			Msg("Detected application type")
	}

	result.AppType = appType
	result.AppVersion = appVersion

	// Step 3: Build Docker image
	imageName := fmt.Sprintf("easydeploy/%s", job.ApplicationID)
	imageTag := result.CommitSHA
	if imageTag == "" {
		imageTag = time.Now().Format("20060102150405")
	}
	// Use short SHA for tag
	if len(imageTag) > 12 {
		imageTag = imageTag[:12]
	}

	result.ImageName = imageName
	result.ImageTag = imageTag

	logger.Info().
		Str("image", fmt.Sprintf("%s:%s", imageName, imageTag)).
		Msg("Building Docker image")
	s.publishStage(job.DeploymentID, "build", "running")

	// Prepare build environment
	buildEnv := make(map[string]string)
	for k, v := range job.Environment {
		buildEnv[k] = v
	}
	if job.BuildCommand != "" {
		buildEnv["BUILD_COMMAND"] = job.BuildCommand
	}
	if job.StartCommand != "" {
		buildEnv["START_COMMAND"] = job.StartCommand
	}

	// Log callback
	var buildLogs string
	logCallback := func(line string) {
		buildLogs += line
		s.publishLogLine(job.DeploymentID, "build", line)
	}

	// Check if custom Dockerfile exists
	if appType == "docker" {
		// Use custom Dockerfile
		buildOpts := docker.BuildOptions{
			ContextPath: buildPath,
			Dockerfile:  "Dockerfile",
			ImageName:   imageName,
			ImageTag:    imageTag,
			BuildArgs:   buildEnv,
			Labels: map[string]string{
				"easydeploy.managed":       "true",
				"easydeploy.deployment.id": job.DeploymentID,
				"easydeploy.app.id":        job.ApplicationID,
			},
		}

		buildResult, err := s.imageBuilder.Build(ctx, buildOpts, logCallback)
		if err != nil {
			s.publishStage(job.DeploymentID, "build", "failed")
			result.BuildLogs = buildLogs // Capture partial logs on failure
			return result, fmt.Errorf("docker build failed: %w", err)
		}
		result.BuildLogs = buildResult.Logs
	} else {
		// Use buildpack
		buildResult, err := s.imageBuilder.BuildWithBuildpack(
			ctx,
			appType,
			appVersion,
			buildPath,
			imageName,
			imageTag,
			buildEnv,
			logCallback,
		)
		if err != nil {
			s.publishStage(job.DeploymentID, "build", "failed")
			result.BuildLogs = buildLogs // Capture partial logs on failure
			return result, fmt.Errorf("buildpack build failed: %w", err)
		}
		result.BuildLogs = buildResult.Logs
	}

	logger.Info().Msg("Docker image built successfully")
	s.publishStage(job.DeploymentID, "build", "success")

	// Step 4: Push to registry (if configured)
	if s.cfg.DockerRegistry != "" {
		logger.Info().Str("registry", s.cfg.DockerRegistry).Msg("Pushing image to registry")
		s.publishStage(job.DeploymentID, "push", "running")

		pushOpts := docker.PushOptions{
			ImageName: imageName,
			ImageTag:  imageTag,
			Registry:  s.cfg.DockerRegistry,
			Username:  s.cfg.DockerRegistryUser,
			Password:  s.cfg.DockerRegistryPass,
		}

		if err := s.imageBuilder.Push(ctx, pushOpts, logCallback); err != nil {
			s.publishStage(job.DeploymentID, "push", "failed")
			return nil, fmt.Errorf("failed to push image: %w", err)
		}

		result.ImageName = fmt.Sprintf("%s/%s", s.cfg.DockerRegistry, imageName)
		result.AgentImageName = fmt.Sprintf("%s/%s", s.cfg.AgentRegistryAddr(), imageName)
		s.publishStage(job.DeploymentID, "push", "success")
	}

	return result, nil
}

func (s *Scheduler) deployContainers(ctx context.Context, job *queue.BuildJob, result *BuildResult, logger *zerolog.Logger) error {
	// Update deployment status
	if err := s.updateDeploymentStatus(ctx, job.DeploymentID, StatusDeploying, ""); err != nil {
		return err
	}

	replicas := job.Replicas
	if replicas <= 0 {
		replicas = 1
	}

	logger.Info().Int("replicas", replicas).Msg("Deploying containers with zero-downtime strategy")

	hasExistingReplicas, err := s.hasExistingRunningReplicas(ctx, job.ApplicationID, job.DeploymentID)
	if err != nil {
		return fmt.Errorf("failed to check existing replicas: %w", err)
	}

	// Track new containers for health checks
	var newContainers []containerInfo

	// Select servers and create containers
	for i := 0; i < replicas; i++ {
		// Select server using scheduling strategy
		server, err := s.SelectServer(job.CPULimit, job.MemoryLimit)
		if err != nil {
			return fmt.Errorf("failed to select server: %w", err)
		}

		// Get agent client for server
		client, err := s.getAgentClient(server.ID, server.AgentAddress)
		if err != nil {
			return fmt.Errorf("failed to connect to agent: %w", err)
		}

		// Pull image on agent before creating container
		pullImageName := fmt.Sprintf("%s:%s", result.AgentImageName, result.ImageTag)
		logger.Info().Str("image", pullImageName).Str("server", server.ID).Msg("Pulling image on agent")
		if err := client.PullImage(ctx, pullImageName); err != nil {
			return fmt.Errorf("failed to pull image on agent: %w", err)
		}

		containerName := s.deploymentContainerName(job.ApplicationID, job.DeploymentID, i, hasExistingReplicas)

		// Create container using stage suffix only for rolling deployments.
		containerResult, err := client.CreateContainer(ctx, &DeployRequest{
			ImageName: fmt.Sprintf("%s:%s", result.AgentImageName, result.ImageTag),
			Name:      containerName,
			EnvVars:   job.Environment,
			Port:      job.Port,
			CPULimit:  int64(job.CPULimit),
			MemLimit:  int64(job.MemoryLimit) * 1024 * 1024, // Convert MB to bytes
			Labels: map[string]string{
				"easydeploy.managed":       "true",
				"easydeploy.deployment.id": job.DeploymentID,
				"easydeploy.app.id":        job.ApplicationID,
				"traefik.enable":           "true",
			},
		})
		if err != nil {
			return fmt.Errorf("failed to create container: %w", err)
		}

		// Save container to database
		if err := s.saveContainer(ctx, job.DeploymentID, job.ApplicationID, server.ID, containerResult.ContainerID, containerName, int(containerResult.HostPort), job.Port, i); err != nil {
			logger.Error().Err(err).Msg("Failed to save container to database")
		}

		logger.Info().
			Str("container_id", containerResult.ContainerID).
			Str("server", server.ID).
			Int("host_port", int(containerResult.HostPort)).
			Int("replica", i).
			Msg("Container created")

		// Track new container for health checks
		newContainers = append(newContainers, containerInfo{
			dockerID:     containerResult.ContainerID,
			serverID:     server.ID,
			agentAddress: server.AgentAddress,
		})
	}

	// Wait for new containers to become healthy before proceeding
	logger.Info().Msg("Waiting for new containers to become healthy")
	if err := s.waitForContainersHealthy(ctx, newContainers, logger); err != nil {
		logger.Error().Err(err).Msg("New containers failed health checks")
		return fmt.Errorf("new containers failed to become healthy: %w", err)
	}

	logger.Info().Msg("All new containers are healthy")

	// Cleanup old replicas from previous deployment.
	s.cleanupOldContainers(ctx, job.ApplicationID, job.DeploymentID, logger)

	// Promote staged _deploy names to final names after old replicas are gone.
	if err := s.promoteDeploymentContainerNames(ctx, job.ApplicationID, job.DeploymentID); err != nil {
		return fmt.Errorf("failed to promote deployment container names: %w", err)
	}

	// Refresh Traefik after promotion/cleanup so load-balancer points to current replicas only.
	if err := s.updateTraefikConfig(ctx, job.ApplicationID); err != nil {
		return fmt.Errorf("failed to update traefik config after cleanup: %w", err)
	}

	// Regenerate and verify routes after cleanup to ensure only current deployment backends remain.
	if err := s.ensureTraefikRoutesForDeployment(ctx, job.ApplicationID, job.DeploymentID); err != nil {
		return fmt.Errorf("failed to ensure traefik routes for deployment: %w", err)
	}

	logger.Info().Msg("Zero-downtime deployment completed successfully")

	return nil
}

// waitForContainersHealthy waits for all new containers to pass health checks
// before proceeding with the deployment. This ensures zero-downtime deployments.
// containerInfo represents a container for health checking
type containerInfo struct {
	dockerID     string
	serverID     string
	agentAddress string
}

func (s *Scheduler) waitForContainersHealthy(ctx context.Context, containers []containerInfo, logger *zerolog.Logger) error {
	const (
		maxRetries      = 30 // Maximum number of health check attempts
		retryInterval   = 2  // Seconds between retries
		startupGraceSec = 5  // Initial grace period before first check
	)

	// Give containers a few seconds to start up
	logger.Info().Int("grace_period_sec", startupGraceSec).Msg("Waiting for containers to start")
	time.Sleep(time.Duration(startupGraceSec) * time.Second)

	// Track health status for each container
	healthyCount := 0
	attempts := 0

	for attempts < maxRetries {
		healthyCount = 0
		allHealthy := true

		for _, container := range containers {
			client, err := s.getAgentClient(container.serverID, container.agentAddress)
			if err != nil {
				logger.Warn().
					Err(err).
					Str("container", container.dockerID).
					Msg("Failed to get agent client for health check")
				allHealthy = false
				continue
			}

			healthy, err := client.HealthCheck(ctx, container.dockerID)
			if err != nil {
				logger.Debug().
					Err(err).
					Str("container", container.dockerID).
					Int("attempt", attempts+1).
					Msg("Health check error")
				allHealthy = false
				continue
			}

			if healthy {
				healthyCount++
			} else {
				allHealthy = false
				logger.Debug().
					Str("container", container.dockerID).
					Int("attempt", attempts+1).
					Msg("Container not yet healthy")
			}
		}

		if allHealthy {
			logger.Info().
				Int("containers", healthyCount).
				Int("attempts", attempts+1).
				Msg("All containers are healthy")
			return nil
		}

		attempts++
		if attempts < maxRetries {
			logger.Debug().
				Int("healthy", healthyCount).
				Int("total", len(containers)).
				Int("attempt", attempts).
				Int("max_attempts", maxRetries).
				Msg("Waiting for containers to become healthy")
			time.Sleep(time.Duration(retryInterval) * time.Second)
		}
	}

	return fmt.Errorf("containers failed to become healthy after %d attempts (%d/%d healthy)",
		maxRetries, healthyCount, len(containers))
}

func (s *Scheduler) updateDeploymentStatus(ctx context.Context, deploymentID string, status DeploymentStatus, errorMsg string) error {
	query := `
		UPDATE deployments
		SET status = $1, error_message = $2, updated_at = NOW()
		WHERE id = $3
	`
	_, err := s.db.Pool().Exec(ctx, query, status, errorMsg, deploymentID)
	return err
}

func (s *Scheduler) saveCommitInfo(ctx context.Context, deploymentID string, commitSHA string, commitMessage string) error {
	query := `
		UPDATE deployments
		SET commit_sha = $1, commit_message = $2, updated_at = NOW()
		WHERE id = $3
	`
	_, err := s.db.Pool().Exec(ctx, query, commitSHA, commitMessage, deploymentID)
	return err
}

func (s *Scheduler) notifyPanel(callbackURL string, status DeploymentStatus, errorMsg string, buildLogs string, commitSHA string, commitMsg string) {
	callbackURL = s.resolveCallbackURL(callbackURL)
	if callbackURL == "" {
		return
	}

	payload := map[string]string{
		"status": string(status),
	}
	if errorMsg != "" {
		payload["error_message"] = errorMsg
	}
	if buildLogs != "" {
		payload["build_logs"] = buildLogs
	}
	if commitSHA != "" {
		payload["commit_sha"] = commitSHA
	}
	if commitMsg != "" {
		payload["commit_message"] = commitMsg
	}

	data, err := json.Marshal(payload)
	if err != nil {
		log.Error().Err(err).Msg("Failed to marshal panel callback payload")
		return
	}

	req, err := http.NewRequestWithContext(context.Background(), http.MethodPost, callbackURL, bytes.NewReader(data))
	if err != nil {
		log.Error().Err(err).Msg("Failed to create panel callback request")
		return
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+s.cfg.APIKey)

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		log.Error().Err(err).Str("url", callbackURL).Msg("Failed to notify panel of deployment status")
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode >= 400 {
		log.Error().Int("status", resp.StatusCode).Str("url", callbackURL).Msg("Panel callback returned error")
	} else {
		log.Info().Str("url", callbackURL).Str("deployment_status", string(status)).Msg("Panel notified of deployment status")
	}
}

func (s *Scheduler) resolveCallbackURL(callbackURL string) string {
	callbackURL = strings.TrimSpace(callbackURL)
	if callbackURL == "" {
		return ""
	}

	u, err := url.Parse(callbackURL)
	if err != nil || u.Host == "" {
		return callbackURL
	}

	host := strings.Trim(strings.Split(u.Host, ":")[0], "[]")
	if host != "localhost" && host != "127.0.0.1" && host != "::1" {
		return callbackURL
	}

	panelURL := strings.TrimSpace(s.cfg.PanelURL)
	if panelURL == "" {
		return callbackURL
	}

	panelParsed, err := url.Parse(panelURL)
	if err != nil || panelParsed.Host == "" {
		return callbackURL
	}

	u.Scheme = panelParsed.Scheme
	u.Host = panelParsed.Host

	return u.String()
}

func (s *Scheduler) saveContainer(ctx context.Context, deploymentID, appID, serverID, containerID, name string, hostPort, internalPort, replica int) error {
	query := `
		INSERT INTO containers (id, deployment_id, application_id, server_id, docker_container_id, name, host_port, internal_port, status, health_status, replica_index, created_at, updated_at)
		VALUES (gen_random_uuid(), $1, $2, $3, $4, $5, $6, $7, 'running', 'healthy', $8, NOW(), NOW())
		RETURNING id
	`
	if strings.TrimSpace(name) == "" {
		name = fmt.Sprintf("%s-replica-%d", appID, replica)
	}
	var dbContainerID string
	if err := s.db.Pool().QueryRow(ctx, query, deploymentID, appID, serverID, containerID, name, hostPort, internalPort, replica).Scan(&dbContainerID); err != nil {
		return err
	}

	go s.notifyPanelContainerStatus(dbContainerID, "running", "healthy", nil, nil)

	return nil
}

func (s *Scheduler) promoteDeploymentContainerNames(ctx context.Context, applicationID, deploymentID string) error {
	query := `
		UPDATE containers
		SET name = application_id || '-replica-' || replica_index,
		    updated_at = NOW()
		WHERE application_id = $1
		  AND deployment_id = $2
		  AND status = 'running'
		  AND name LIKE '%_deploy%'
	`

	if _, err := s.db.Pool().Exec(ctx, query, applicationID, deploymentID); err != nil {
		return err
	}

	return nil
}

func (s *Scheduler) hasExistingRunningReplicas(ctx context.Context, applicationID, currentDeploymentID string) (bool, error) {
	query := `
		SELECT COUNT(*)
		FROM containers
		WHERE application_id = $1
		  AND status = 'running'
		  AND deployment_id != $2
	`

	var count int
	if err := s.db.Pool().QueryRow(ctx, query, applicationID, currentDeploymentID).Scan(&count); err != nil {
		return false, err
	}

	return count > 0, nil
}

func (s *Scheduler) deploymentContainerName(applicationID, deploymentID string, replica int, rolling bool) string {
	if !rolling {
		return fmt.Sprintf("%s-replica-%d", applicationID, replica)
	}

	deploySuffix := deploymentID
	if len(deploySuffix) > 8 {
		deploySuffix = deploySuffix[:8]
	}

	return fmt.Sprintf("%s-replica-%d_deploy_%s", applicationID, replica, deploySuffix)
}

// cleanupOldContainers stops and removes containers from previous deployments of the same application
func (s *Scheduler) cleanupOldContainers(ctx context.Context, applicationID, currentDeploymentID string, logger *zerolog.Logger) {
	// Find old containers from previous deployments
	query := `
		SELECT c.id, c.docker_container_id, c.server_id, s.agent_address
		FROM containers c
		JOIN servers s ON c.server_id = s.id
		WHERE c.application_id = $1
		  AND c.deployment_id != $2
		  AND c.status = 'running'
	`

	rows, err := s.db.Pool().Query(ctx, query, applicationID, currentDeploymentID)
	if err != nil {
		logger.Error().Err(err).Msg("Failed to query old containers for cleanup")
		return
	}
	defer rows.Close()

	type oldContainer struct {
		ID           string
		DockerID     string
		ServerID     string
		AgentAddress string
	}

	var oldContainers []oldContainer
	for rows.Next() {
		var c oldContainer
		if err := rows.Scan(&c.ID, &c.DockerID, &c.ServerID, &c.AgentAddress); err != nil {
			logger.Error().Err(err).Msg("Failed to scan old container row")
			continue
		}
		oldContainers = append(oldContainers, c)
	}

	if len(oldContainers) == 0 {
		return
	}

	logger.Info().Int("count", len(oldContainers)).Msg("Cleaning up old containers from previous deployments")

	for _, c := range oldContainers {
		// Stop and remove via agent
		client, err := s.getAgentClient(c.ServerID, c.AgentAddress)
		if err != nil {
			logger.Warn().Err(err).Str("container", c.ID).Msg("Failed to get agent client for cleanup")
			continue
		}

		if err := client.StopContainer(ctx, c.DockerID); err != nil {
			logger.Warn().Err(err).Str("container", c.DockerID).Msg("Failed to stop old container")
		}

		if err := client.RemoveContainer(ctx, c.DockerID); err != nil {
			logger.Warn().Err(err).Str("container", c.DockerID).Msg("Failed to remove old container")
		}

		// Update DB status
		updateQuery := `UPDATE containers SET status = 'stopped', updated_at = NOW() WHERE id = $1`
		if _, err := s.db.Pool().Exec(ctx, updateQuery, c.ID); err != nil {
			logger.Warn().Err(err).Str("container", c.ID).Msg("Failed to update old container status")
		}

		go s.notifyPanelContainerStatus(c.ID, "stopped", "unknown", nil, nil)

		logger.Info().Str("container_id", c.DockerID).Str("db_id", c.ID).Msg("Old container cleaned up")
	}
}

func (s *Scheduler) updateTraefikConfig(ctx context.Context, applicationID string) error {
	if s.traefikGen == nil {
		return fmt.Errorf("traefik config generator not set")
	}
	return s.traefikGen.GenerateConfig(ctx, applicationID)
}

func (s *Scheduler) ensureTraefikRoutesForDeployment(ctx context.Context, applicationID, deploymentID string) error {
	if err := s.updateTraefikConfig(ctx, applicationID); err != nil {
		return err
	}

	expectedURLs, err := s.getDeploymentBackendURLs(ctx, applicationID, deploymentID)
	if err != nil {
		return fmt.Errorf("failed to list deployment backends: %w", err)
	}

	if len(expectedURLs) == 0 {
		return fmt.Errorf("no healthy running containers found for deployment %s", deploymentID)
	}

	actualURLs, err := s.readTraefikBackendURLs(applicationID)
	if err != nil {
		return fmt.Errorf("failed to read traefik backend urls: %w", err)
	}

	if !sameStringSet(expectedURLs, actualURLs) {
		return fmt.Errorf("traefik backends mismatch: expected=%v actual=%v", expectedURLs, actualURLs)
	}

	log.Info().
		Str("application_id", applicationID).
		Str("deployment_id", deploymentID).
		Int("backends", len(actualURLs)).
		Msg("Traefik route validated for deployment")

	return nil
}

func (s *Scheduler) getDeploymentBackendURLs(ctx context.Context, applicationID, deploymentID string) ([]string, error) {
	query := `
		SELECT host(s.ip_address), COALESCE(c.host_port, 0), COALESCE(c.internal_port, 0)
		FROM containers c
		JOIN servers s ON c.server_id = s.id
		WHERE c.application_id = $1
		  AND c.deployment_id = $2
		  AND c.status = 'running'
		  AND c.health_status = 'healthy'
	`

	rows, err := s.db.Pool().Query(ctx, query, applicationID, deploymentID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	urls := make([]string, 0)
	for rows.Next() {
		var serverIP string
		var hostPort int
		var internalPort int
		if err := rows.Scan(&serverIP, &hostPort, &internalPort); err != nil {
			return nil, err
		}

		port := hostPort
		if port == 0 {
			port = internalPort
		}
		urls = append(urls, fmt.Sprintf("http://%s:%d", serverIP, port))
	}

	if err := rows.Err(); err != nil {
		return nil, err
	}

	sort.Strings(urls)
	return dedupeStrings(urls), nil
}

func (s *Scheduler) readTraefikBackendURLs(applicationID string) ([]string, error) {
	configPath := filepath.Join(s.cfg.TraefikConfigDir, fmt.Sprintf("app-%s.yml", applicationID))

	data, err := os.ReadFile(configPath)
	if err != nil {
		return nil, err
	}

	var cfg traefik.DynamicConfig
	if err := yaml.Unmarshal(data, &cfg); err != nil {
		return nil, err
	}

	if cfg.HTTP == nil || len(cfg.HTTP.Services) == 0 {
		return nil, fmt.Errorf("no services found in traefik config")
	}

	urls := make([]string, 0)
	for _, svc := range cfg.HTTP.Services {
		if svc == nil || svc.LoadBalancer == nil {
			continue
		}
		for _, server := range svc.LoadBalancer.Servers {
			if server.URL != "" {
				urls = append(urls, server.URL)
			}
		}
	}

	sort.Strings(urls)
	return dedupeStrings(urls), nil
}

func sameStringSet(a, b []string) bool {
	if len(a) != len(b) {
		return false
	}
	for i := range a {
		if a[i] != b[i] {
			return false
		}
	}
	return true
}

func dedupeStrings(values []string) []string {
	if len(values) == 0 {
		return values
	}

	result := make([]string, 0, len(values))
	last := ""
	for i, v := range values {
		if i == 0 || v != last {
			result = append(result, v)
			last = v
		}
	}

	return result
}

func (s *Scheduler) runHealthChecks() {
	defer s.wg.Done()

	for {
		select {
		case <-s.healthTicker.C:
			s.checkAllContainers()
			s.reconcileAllActiveApplications()
		case <-s.stopCh:
			return
		}
	}
}

func (s *Scheduler) reconcileAllActiveApplications() {
	ctx, cancel := context.WithTimeout(context.Background(), 45*time.Second)
	defer cancel()

	query := `SELECT id FROM applications WHERE status = 'active'`
	rows, err := s.db.Pool().Query(ctx, query)
	if err != nil {
		log.Error().Err(err).Msg("Failed to query active applications for replica reconciliation")
		return
	}
	defer rows.Close()

	for rows.Next() {
		var appID string
		if err := rows.Scan(&appID); err != nil {
			continue
		}

		if err := s.reconcileApplicationReplicas(ctx, appID); err != nil {
			log.Warn().Err(err).Str("application_id", appID).Msg("Replica reconciliation failed")
		}
	}
}

func (s *Scheduler) checkAllContainers() {
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	// Get all running containers from database
	query := `
		SELECT c.id, c.docker_container_id, c.server_id, s.agent_address
		FROM containers c
		JOIN servers s ON c.server_id = s.id
		WHERE c.status = 'running'
	`

	rows, err := s.db.Pool().Query(ctx, query)
	if err != nil {
		log.Error().Err(err).Msg("Failed to query containers for health check")
		return
	}
	defer rows.Close()

	for rows.Next() {
		var containerID, dockerContainerID, serverID, agentAddress string
		if err := rows.Scan(&containerID, &dockerContainerID, &serverID, &agentAddress); err != nil {
			continue
		}

		// Check container health via agent
		go s.checkContainerHealth(containerID, dockerContainerID, serverID, agentAddress)
	}

	log.Debug().Msg("Health checks completed")
}

func (s *Scheduler) checkContainerHealth(containerID, dockerContainerID, serverID, agentAddress string) {
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	client, err := s.getAgentClient(serverID, agentAddress)
	if err != nil {
		log.Warn().Err(err).Str("server", serverID).Msg("Failed to get agent client for health check")
		return
	}

	healthy, err := client.HealthCheck(ctx, dockerContainerID)
	if err != nil {
		log.Warn().Err(err).Str("container", containerID).Msg("Health check failed")
		s.markContainerUnhealthy(containerID)
		return
	}

	if !healthy {
		log.Warn().Str("container", containerID).Msg("Container unhealthy")
		s.markContainerUnhealthy(containerID)
	} else {
		s.markContainerHealthy(containerID)
	}
}

func (s *Scheduler) markContainerUnhealthy(containerID string) {
	ctx := context.Background()
	query := `UPDATE containers SET health_status = 'unhealthy', updated_at = NOW() WHERE id = $1`
	cmd, err := s.db.Pool().Exec(ctx, query, containerID)
	if err != nil {
		log.Debug().Err(err).Str("container_id", containerID).Msg("Failed to mark container unhealthy")
		return
	}

	if cmd.RowsAffected() > 0 {
		go s.notifyPanelContainerStatus(containerID, "running", "unhealthy", nil, nil)
	}
}

func (s *Scheduler) markContainerHealthy(containerID string) {
	ctx := context.Background()
	query := `UPDATE containers SET health_status = 'healthy', health_checked_at = NOW(), updated_at = NOW() WHERE id = $1 AND health_status != 'healthy'`
	cmd, err := s.db.Pool().Exec(ctx, query, containerID)
	if err != nil {
		log.Debug().Err(err).Str("container_id", containerID).Msg("Failed to mark container healthy")
		return
	}

	if cmd.RowsAffected() > 0 {
		go s.notifyPanelContainerStatus(containerID, "running", "healthy", nil, nil)
	}
}

func (s *Scheduler) notifyPanelContainerStatus(containerID, status, healthStatus string, cpuUsage *float64, memoryUsage *float64) {
	if s.cfg.PanelURL == "" {
		return
	}

	url := fmt.Sprintf("%s/api/internal/containers/%s/status", s.cfg.PanelURL, containerID)

	payload := map[string]interface{}{
		"status":        status,
		"health_status": healthStatus,
	}
	if cpuUsage != nil {
		payload["cpu_usage"] = *cpuUsage
	}
	if memoryUsage != nil {
		payload["memory_usage"] = *memoryUsage
	}

	data, err := json.Marshal(payload)
	if err != nil {
		log.Debug().Err(err).Str("container_id", containerID).Msg("Failed to marshal container callback payload")
		return
	}

	req, err := http.NewRequestWithContext(context.Background(), http.MethodPost, url, bytes.NewReader(data))
	if err != nil {
		log.Debug().Err(err).Str("container_id", containerID).Msg("Failed to create container callback request")
		return
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+s.cfg.APIKey)

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		log.Debug().Err(err).Str("container_id", containerID).Msg("Failed to notify panel of container status")
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode >= 400 {
		log.Debug().
			Int("status_code", resp.StatusCode).
			Str("container_id", containerID).
			Str("container_status", status).
			Str("container_health_status", healthStatus).
			Msg("Panel container callback returned error")
	}
}

func (s *Scheduler) cleanupOldRepos() {
	defer s.wg.Done()

	ticker := time.NewTicker(1 * time.Hour)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			// Cleanup repos older than 1 hour
			repoDir := filepath.Join(s.cfg.DataDir, "repos")
			log.Debug().Str("dir", repoDir).Msg("Cleaning up old repositories")
			// Implementation would delete old repo-* directories
		case <-s.stopCh:
			return
		}
	}
}

// Server represents a worker server
type Server struct {
	ID           string
	AgentAddress string
	CPUUsage     float64
	MemoryUsage  float64
	Containers   int
}

// SelectServer chooses the best server for a new container
func (s *Scheduler) SelectServer(cpuNeeded, memoryNeeded int) (*Server, error) {
	s.mu.RLock()
	defer s.mu.RUnlock()

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	// Query online servers with capacity
	query := `
		SELECT s.id, s.agent_address,
			COALESCE(AVG(ru.cpu_percent), 0) as cpu_usage,
			COALESCE(AVG(ru.memory_percent), 0) as memory_usage,
			COUNT(c.id) as container_count
		FROM servers s
		LEFT JOIN resource_usages ru ON s.id = ru.server_id AND ru.created_at > NOW() - INTERVAL '5 minutes'
		LEFT JOIN containers c ON s.id = c.server_id AND c.status = 'running'
		WHERE s.status = 'online'
		GROUP BY s.id, s.agent_address
		HAVING COALESCE(AVG(ru.cpu_percent), 0) < 80
		   AND COALESCE(AVG(ru.memory_percent), 0) < 80
		ORDER BY COUNT(c.id) ASC, COALESCE(AVG(ru.cpu_percent), 0) + COALESCE(AVG(ru.memory_percent), 0) ASC
		LIMIT 1
	`

	var server Server
	err := s.db.Pool().QueryRow(ctx, query).Scan(
		&server.ID, &server.AgentAddress, &server.CPUUsage, &server.MemoryUsage, &server.Containers,
	)
	if err != nil {
		return nil, fmt.Errorf("no available servers: %w", err)
	}

	return &server, nil
}

// getAgentClient gets or creates an agent client for a server
func (s *Scheduler) getAgentClient(serverID, address string) (*AgentClient, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	if client, ok := s.agentClients[serverID]; ok {
		return client, nil
	}

	client, err := NewAgentClient(address)
	if err != nil {
		return nil, err
	}

	s.agentClients[serverID] = client
	return client, nil
}

// GetContainerLogs retrieves logs from a container via agent
func (s *Scheduler) GetContainerLogs(ctx context.Context, containerID string, lines int) (string, error) {
	// Get container info from database
	query := `
		SELECT c.docker_container_id, s.id, s.agent_address
		FROM containers c
		JOIN servers s ON c.server_id = s.id
		WHERE c.id = $1
	`

	var dockerID, serverID, agentAddress string
	err := s.db.Pool().QueryRow(ctx, query, containerID).Scan(&dockerID, &serverID, &agentAddress)
	if err != nil {
		return "", fmt.Errorf("container not found: %w", err)
	}

	client, err := s.getAgentClient(serverID, agentAddress)
	if err != nil {
		return "", err
	}

	return client.GetLogs(ctx, dockerID, lines)
}

// RestartContainer restarts a container via agent
func (s *Scheduler) RestartContainer(ctx context.Context, containerID string) error {
	// Get container info from database
	query := `
		SELECT c.docker_container_id, s.id, s.agent_address
		FROM containers c
		JOIN servers s ON c.server_id = s.id
		WHERE c.id = $1
	`

	var dockerID, serverID, agentAddress string
	err := s.db.Pool().QueryRow(ctx, query, containerID).Scan(&dockerID, &serverID, &agentAddress)
	if err != nil {
		return fmt.Errorf("container not found: %w", err)
	}

	client, err := s.getAgentClient(serverID, agentAddress)
	if err != nil {
		return err
	}

	return client.RestartContainer(ctx, dockerID)
}

// StopContainer stops a container via agent
func (s *Scheduler) StopContainer(ctx context.Context, containerID string) error {
	// Get container info from database
	query := `
		SELECT c.docker_container_id, s.id, s.agent_address, c.application_id
		FROM containers c
		JOIN servers s ON c.server_id = s.id
		WHERE c.id = $1
	`

	var dockerID, serverID, agentAddress, applicationID string
	err := s.db.Pool().QueryRow(ctx, query, containerID).Scan(&dockerID, &serverID, &agentAddress, &applicationID)
	if err != nil {
		return fmt.Errorf("container not found: %w", err)
	}

	client, err := s.getAgentClient(serverID, agentAddress)
	if err != nil {
		return err
	}

	if err := client.StopContainer(ctx, dockerID); err != nil {
		return err
	}

	// Update database
	updateQuery := `UPDATE containers SET status = 'stopped', updated_at = NOW() WHERE id = $1`
	if _, err = s.db.Pool().Exec(ctx, updateQuery, containerID); err != nil {
		return err
	}

	go s.notifyPanelContainerStatus(containerID, "stopped", "unknown", nil, nil)

	if err := s.reconcileApplicationReplicas(ctx, applicationID); err != nil {
		return fmt.Errorf("container stopped but failed to reconcile replicas: %w", err)
	}

	return nil
}

// ReconcileApplicationReplicas ensures runtime replicas match desired application replicas.
func (s *Scheduler) ReconcileApplicationReplicas(ctx context.Context, applicationID string) error {
	return s.reconcileApplicationReplicas(ctx, applicationID)
}

func (s *Scheduler) reconcileApplicationReplicas(ctx context.Context, applicationID string) error {
	var appStatus string
	var desiredReplicas, appPort, cpuLimit, memoryLimit int

	appQuery := `
		SELECT status, replicas, port, cpu_limit, memory_limit
		FROM applications
		WHERE id = $1
	`
	if err := s.db.Pool().QueryRow(ctx, appQuery, applicationID).Scan(&appStatus, &desiredReplicas, &appPort, &cpuLimit, &memoryLimit); err != nil {
		return fmt.Errorf("failed to load application state: %w", err)
	}

	if appStatus != "active" {
		return nil
	}

	if desiredReplicas <= 0 {
		if err := s.scaleApplicationToZero(ctx, applicationID); err != nil {
			return fmt.Errorf("failed to scale application to zero: %w", err)
		}
		return nil
	}

	if err := s.cleanupUnhealthyReplicas(ctx, applicationID); err != nil {
		return fmt.Errorf("failed to cleanup unhealthy replicas: %w", err)
	}

	var runningReplicas int
	countQuery := `SELECT COUNT(*) FROM containers WHERE application_id = $1 AND status = 'running' AND health_status = 'healthy'`
	if err := s.db.Pool().QueryRow(ctx, countQuery, applicationID).Scan(&runningReplicas); err != nil {
		return fmt.Errorf("failed to count running replicas: %w", err)
	}

	if runningReplicas > desiredReplicas {
		if err := s.scaleApplicationDown(ctx, applicationID, runningReplicas-desiredReplicas); err != nil {
			return fmt.Errorf("failed to scale down replicas: %w", err)
		}

		if err := s.updateTraefikConfig(ctx, applicationID); err != nil {
			return fmt.Errorf("failed to update traefik after scale down: %w", err)
		}

		return nil
	}

	if runningReplicas >= desiredReplicas {
		return nil
	}

	missingReplicas := desiredReplicas - runningReplicas

	var templateDeploymentID, imageName, imageTag string
	templateQuery := `
		SELECT c.deployment_id, d.image_name, d.image_tag
		FROM containers c
		JOIN deployments d ON d.id = c.deployment_id
		WHERE c.application_id = $1
		  AND c.status = 'running'
		  AND COALESCE(d.image_name, '') <> ''
		  AND COALESCE(d.image_tag, '') <> ''
		ORDER BY c.created_at DESC
		LIMIT 1
	`
	templateErr := s.db.Pool().QueryRow(ctx, templateQuery, applicationID).Scan(&templateDeploymentID, &imageName, &imageTag)
	if templateErr != nil {
		log.Warn().
			Str("application_id", applicationID).
			Int("missing", missingReplicas).
			Err(templateErr).
			Msg("Missing container image metadata for direct self-heal; enqueuing self-heal deployment")
		return s.enqueueSelfHealDeployment(ctx, applicationID)
	}

	envVars, err := s.getApplicationEnvironment(ctx, applicationID)
	if err != nil {
		return fmt.Errorf("failed to load application environment: %w", err)
	}

	imageRef := fmt.Sprintf("%s:%s", imageName, imageTag)
	created := 0

	for i := 0; i < missingReplicas; i++ {
		replicaIndex, err := s.nextReplicaIndex(ctx, applicationID)
		if err != nil {
			return fmt.Errorf("failed to allocate replica index: %w", err)
		}

		server, err := s.SelectServer(cpuLimit, memoryLimit)
		if err != nil {
			return fmt.Errorf("failed to select server for self-heal: %w", err)
		}

		client, err := s.getAgentClient(server.ID, server.AgentAddress)
		if err != nil {
			return fmt.Errorf("failed to connect to agent for self-heal: %w", err)
		}

		if err := client.PullImage(ctx, imageRef); err != nil {
			return fmt.Errorf("failed to pull image for self-heal: %w", err)
		}

		containerResult, err := client.CreateContainer(ctx, &DeployRequest{
			ImageName: imageRef,
			Name:      fmt.Sprintf("%s-selfheal-%d", applicationID, time.Now().UnixNano()),
			EnvVars:   envVars,
			Port:      appPort,
			CPULimit:  int64(cpuLimit),
			MemLimit:  int64(memoryLimit) * 1024 * 1024,
			Labels: map[string]string{
				"easydeploy.managed":       "true",
				"easydeploy.deployment.id": templateDeploymentID,
				"easydeploy.app.id":        applicationID,
				"traefik.enable":           "true",
			},
		})
		if err != nil {
			return fmt.Errorf("failed to create self-heal container: %w", err)
		}

		if err := s.saveContainer(ctx, templateDeploymentID, applicationID, server.ID, containerResult.ContainerID, fmt.Sprintf("%s-replica-%d", applicationID, replicaIndex), int(containerResult.HostPort), appPort, replicaIndex); err != nil {
			return fmt.Errorf("failed to persist self-heal container: %w", err)
		}

		created++
	}

	if created == 0 {
		return fmt.Errorf("self-heal did not create any replacement containers")
	}

	if err := s.updateTraefikConfig(ctx, applicationID); err != nil {
		return fmt.Errorf("failed to update traefik after self-heal: %w", err)
	}

	log.Info().
		Str("application_id", applicationID).
		Int("created", created).
		Int("desired_replicas", desiredReplicas).
		Int("running_replicas", runningReplicas+created).
		Msg("Application replicas reconciled after container stop")

	return nil
}

func (s *Scheduler) scaleApplicationToZero(ctx context.Context, applicationID string) error {
	query := `
		SELECT c.id, c.docker_container_id, c.server_id, s.agent_address
		FROM containers c
		JOIN servers s ON s.id = c.server_id
		WHERE c.application_id = $1
		  AND c.status = 'running'
	`

	rows, err := s.db.Pool().Query(ctx, query, applicationID)
	if err != nil {
		return err
	}
	defer rows.Close()

	type activeContainer struct {
		ID           string
		DockerID     string
		ServerID     string
		AgentAddress string
	}

	containers := make([]activeContainer, 0)
	for rows.Next() {
		var c activeContainer
		if err := rows.Scan(&c.ID, &c.DockerID, &c.ServerID, &c.AgentAddress); err != nil {
			continue
		}
		containers = append(containers, c)
	}

	for _, c := range containers {
		if err := s.stopAndMarkContainer(ctx, c.ID, c.DockerID, c.ServerID, c.AgentAddress, "unknown"); err != nil {
			return err
		}
	}

	if s.traefikGen != nil {
		if err := s.traefikGen.RemoveConfig(applicationID); err != nil {
			return err
		}
	}

	return nil
}

func (s *Scheduler) scaleApplicationDown(ctx context.Context, applicationID string, toRemove int) error {
	if toRemove <= 0 {
		return nil
	}

	query := `
		SELECT c.id, c.docker_container_id, c.server_id, s.agent_address
		FROM containers c
		JOIN servers s ON s.id = c.server_id
		WHERE c.application_id = $1
		  AND c.status = 'running'
		  AND c.health_status = 'healthy'
		ORDER BY c.replica_index DESC, c.created_at DESC
	`

	rows, err := s.db.Pool().Query(ctx, query, applicationID)
	if err != nil {
		return err
	}
	defer rows.Close()

	type runningContainer struct {
		ID           string
		DockerID     string
		ServerID     string
		AgentAddress string
	}

	candidates := make([]runningContainer, 0)
	for rows.Next() {
		var c runningContainer
		if err := rows.Scan(&c.ID, &c.DockerID, &c.ServerID, &c.AgentAddress); err != nil {
			continue
		}
		candidates = append(candidates, c)
	}

	if len(candidates) < toRemove {
		toRemove = len(candidates)
	}

	for i := 0; i < toRemove; i++ {
		c := candidates[i]
		if err := s.stopAndMarkContainer(ctx, c.ID, c.DockerID, c.ServerID, c.AgentAddress, "healthy"); err != nil {
			return err
		}
	}

	return nil
}

func (s *Scheduler) stopAndMarkContainer(ctx context.Context, containerID, dockerID, serverID, agentAddress, healthStatus string) error {
	client, err := s.getAgentClient(serverID, agentAddress)
	if err != nil {
		return err
	}

	if err := client.StopContainer(ctx, dockerID); err != nil {
		return err
	}

	if err := client.RemoveContainer(ctx, dockerID); err != nil {
		log.Warn().Err(err).Str("container_id", containerID).Msg("Failed to remove container after stop")
	}

	updateQuery := `UPDATE containers SET status = 'stopped', updated_at = NOW() WHERE id = $1`
	if _, err := s.db.Pool().Exec(ctx, updateQuery, containerID); err != nil {
		return err
	}

	go s.notifyPanelContainerStatus(containerID, "stopped", healthStatus, nil, nil)

	return nil
}

func (s *Scheduler) cleanupUnhealthyReplicas(ctx context.Context, applicationID string) error {
	query := `
		SELECT c.id, c.docker_container_id, c.server_id, s.agent_address
		FROM containers c
		JOIN servers s ON s.id = c.server_id
		WHERE c.application_id = $1
		  AND c.status = 'running'
		  AND c.health_status = 'unhealthy'
	`

	rows, err := s.db.Pool().Query(ctx, query, applicationID)
	if err != nil {
		return err
	}
	defer rows.Close()

	type unhealthyContainer struct {
		ID           string
		DockerID     string
		ServerID     string
		AgentAddress string
	}

	var unhealthy []unhealthyContainer
	for rows.Next() {
		var c unhealthyContainer
		if err := rows.Scan(&c.ID, &c.DockerID, &c.ServerID, &c.AgentAddress); err != nil {
			continue
		}
		unhealthy = append(unhealthy, c)
	}

	for _, c := range unhealthy {
		client, err := s.getAgentClient(c.ServerID, c.AgentAddress)
		if err != nil {
			log.Warn().Err(err).Str("container_id", c.ID).Msg("Failed to get agent client for unhealthy replica cleanup")
			continue
		}

		if err := client.StopContainer(ctx, c.DockerID); err != nil {
			log.Warn().Err(err).Str("container_id", c.ID).Msg("Failed to stop unhealthy container")
		}

		if err := client.RemoveContainer(ctx, c.DockerID); err != nil {
			log.Warn().Err(err).Str("container_id", c.ID).Msg("Failed to remove unhealthy container")
		}

		updateQuery := `UPDATE containers SET status = 'stopped', updated_at = NOW() WHERE id = $1`
		if _, err := s.db.Pool().Exec(ctx, updateQuery, c.ID); err != nil {
			log.Warn().Err(err).Str("container_id", c.ID).Msg("Failed to mark unhealthy container as stopped")
			continue
		}

		go s.notifyPanelContainerStatus(c.ID, "stopped", "unhealthy", nil, nil)
	}

	return nil
}

func (s *Scheduler) nextReplicaIndex(ctx context.Context, applicationID string) (int, error) {
	query := `
		SELECT COALESCE(MAX(replica_index), -1) + 1
		FROM containers
		WHERE application_id = $1
	`

	var nextIndex int
	if err := s.db.Pool().QueryRow(ctx, query, applicationID).Scan(&nextIndex); err != nil {
		return 0, err
	}

	return nextIndex, nil
}

func (s *Scheduler) getApplicationEnvironment(ctx context.Context, applicationID string) (map[string]string, error) {
	query := `SELECT key, value FROM environment_variables WHERE application_id = $1`
	rows, err := s.db.Pool().Query(ctx, query, applicationID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	env := make(map[string]string)
	for rows.Next() {
		var key, value string
		if err := rows.Scan(&key, &value); err != nil {
			return nil, err
		}
		env[key] = value
	}

	if err := rows.Err(); err != nil {
		return nil, err
	}

	return env, nil
}

func (s *Scheduler) enqueueSelfHealDeployment(ctx context.Context, applicationID string) error {
	panelApp, err := s.fetchAppFromPanel(ctx, applicationID)
	if err != nil {
		return fmt.Errorf("failed to fetch app details for self-heal deployment: %w", err)
	}

	deploymentID := uuid.NewString()
	insertQuery := `
		INSERT INTO deployments (id, application_id, status, triggered_by, created_at)
		VALUES ($1, $2, 'pending', 'self-heal', NOW())
	`
	if _, err := s.db.Pool().Exec(ctx, insertQuery, deploymentID, applicationID); err != nil {
		return fmt.Errorf("failed to create self-heal deployment record: %w", err)
	}

	job := queue.BuildJob{
		DeploymentID:  deploymentID,
		ApplicationID: panelApp.ID,
		GitRepository: panelApp.GitRepository,
		GitBranch:     panelApp.GitBranch,
		GitToken:      panelApp.GitToken,
		Type:          panelApp.Type,
		BuildCommand:  panelApp.BuildCommand,
		StartCommand:  panelApp.StartCommand,
		RootDirectory: panelApp.RootDirectory,
		Port:          panelApp.Port,
		Replicas:      panelApp.Replicas,
		CPULimit:      panelApp.CPULimit,
		MemoryLimit:   panelApp.MemoryLimit,
		Environment:   panelApp.Environment,
		CallbackURL:   fmt.Sprintf("%s/api/internal/deployments/%s/status", s.cfg.PanelURL, deploymentID),
	}

	if err := s.queue.Enqueue("builds", job); err != nil {
		return fmt.Errorf("failed to enqueue self-heal deployment: %w", err)
	}

	log.Info().
		Str("application_id", applicationID).
		Str("deployment_id", deploymentID).
		Msg("Enqueued self-heal deployment to restore desired replicas")

	return nil
}

// publishLogLine publishes a build log line to Redis Pub/Sub
func (s *Scheduler) publishLogLine(deploymentID, stage, line string) {
	payload, err := json.Marshal(map[string]string{
		"type":  "log",
		"stage": stage,
		"line":  line,
		"ts":    time.Now().Format(time.RFC3339),
	})
	if err != nil {
		return
	}
	if err := s.queue.Publish("deploy-logs:"+deploymentID, string(payload)); err != nil {
		log.Debug().Err(err).Str("deployment_id", deploymentID).Msg("Failed to publish log line")
	}
}

// publishStage publishes a stage transition event to Redis Pub/Sub
func (s *Scheduler) publishStage(deploymentID, stage, status string) {
	payload, err := json.Marshal(map[string]string{
		"type":   "stage",
		"stage":  stage,
		"status": status,
		"ts":     time.Now().Format(time.RFC3339),
	})
	if err != nil {
		return
	}
	if err := s.queue.Publish("deploy-logs:"+deploymentID, string(payload)); err != nil {
		log.Debug().Err(err).Str("deployment_id", deploymentID).Msg("Failed to publish stage event")
	}
}

// publishStatusEvent publishes a final deployment status event to Redis Pub/Sub
func (s *Scheduler) publishStatusEvent(deploymentID, status, errorMsg string) {
	payload, err := json.Marshal(map[string]string{
		"type":   "status",
		"status": status,
		"error":  errorMsg,
		"ts":     time.Now().Format(time.RFC3339),
	})
	if err != nil {
		return
	}
	if err := s.queue.Publish("deploy-logs:"+deploymentID, string(payload)); err != nil {
		log.Debug().Err(err).Str("deployment_id", deploymentID).Msg("Failed to publish status event")
	}
}

// RemoveContainer removes a container via agent
func (s *Scheduler) RemoveContainer(ctx context.Context, containerID string) error {
	// Get container info from database
	query := `
		SELECT c.docker_container_id, s.id, s.agent_address
		FROM containers c
		JOIN servers s ON c.server_id = s.id
		WHERE c.id = $1
	`

	var dockerID, serverID, agentAddress string
	err := s.db.Pool().QueryRow(ctx, query, containerID).Scan(&dockerID, &serverID, &agentAddress)
	if err != nil {
		return fmt.Errorf("container not found: %w", err)
	}

	client, err := s.getAgentClient(serverID, agentAddress)
	if err != nil {
		return err
	}

	if err := client.RemoveContainer(ctx, dockerID); err != nil {
		return err
	}

	// Delete from database
	deleteQuery := `DELETE FROM containers WHERE id = $1`
	_, err = s.db.Pool().Exec(ctx, deleteQuery, containerID)
	return err
}
