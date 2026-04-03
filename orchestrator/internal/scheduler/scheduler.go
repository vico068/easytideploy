package scheduler

import (
	"context"
	"encoding/json"
	"fmt"
	"path/filepath"
	"sync"
	"time"

	"github.com/easyti/easydeploy/orchestrator/internal/config"
	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/easyti/easydeploy/orchestrator/internal/docker"
	"github.com/easyti/easydeploy/orchestrator/internal/git"
	"github.com/easyti/easydeploy/orchestrator/internal/queue"
	"github.com/easyti/easydeploy/orchestrator/pkg/buildpack"
	"github.com/rs/zerolog/log"
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
	db           *database.DB
	queue        *queue.RedisQueue
	cfg          *config.Config
	gitCloner    *git.Cloner
	imageBuilder *docker.ImageBuilder
	agentClients map[string]*AgentClient
	mu           sync.RWMutex
	healthTicker *time.Ticker
	stopCh       chan struct{}
	wg           sync.WaitGroup
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
		db:           db,
		queue:        q,
		cfg:          cfg,
		gitCloner:    gitCloner,
		imageBuilder: imageBuilder,
		agentClients: make(map[string]*AgentClient),
		stopCh:       make(chan struct{}),
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

	// Update deployment status to building
	if err := s.updateDeploymentStatus(ctx, job.DeploymentID, StatusBuilding, ""); err != nil {
		logger.Error().Err(err).Msg("Failed to update deployment status")
	}

	// Execute build pipeline
	result, err := s.executeBuildPipeline(ctx, &job, &logger)
	if err != nil {
		logger.Error().Err(err).Msg("Build pipeline failed")
		s.updateDeploymentStatus(ctx, job.DeploymentID, StatusFailed, err.Error())
		return
	}

	// Deploy containers
	if err := s.deployContainers(ctx, &job, result, &logger); err != nil {
		logger.Error().Err(err).Msg("Deployment failed")
		s.updateDeploymentStatus(ctx, job.DeploymentID, StatusFailed, err.Error())
		return
	}

	logger.Info().Msg("Build job completed successfully")
	s.updateDeploymentStatus(ctx, job.DeploymentID, StatusRunning, "")
}

// BuildResult contains the result of a build
type BuildResult struct {
	ImageName  string
	ImageTag   string
	CommitSHA  string
	CommitMsg  string
	BuildLogs  string
	AppType    string
	AppVersion string
	Port       int
}

func (s *Scheduler) executeBuildPipeline(ctx context.Context, job *queue.BuildJob, logger *log.Logger) (*BuildResult, error) {
	result := &BuildResult{}

	// Step 1: Clone repository
	logger.Info().Str("repo", job.GitRepository).Str("branch", job.GitBranch).Msg("Cloning repository")

	cloneOpts := git.CloneOptions{
		URL:        job.GitRepository,
		Branch:     job.GitBranch,
		CommitHash: job.CommitSHA,
		Depth:      1,
	}

	repoPath, err := s.gitCloner.Clone(ctx, cloneOpts)
	if err != nil {
		return nil, fmt.Errorf("failed to clone repository: %w", err)
	}
	defer s.gitCloner.Cleanup(repoPath)

	// Get commit info
	commitInfo, err := s.gitCloner.GetCommitInfo(ctx, repoPath)
	if err != nil {
		logger.Warn().Err(err).Msg("Failed to get commit info")
	} else {
		result.CommitSHA = commitInfo.Hash
		result.CommitMsg = commitInfo.Message
	}

	// Step 2: Detect app type if not specified
	appType := job.Type
	appVersion := ""

	if appType == "" || appType == "auto" {
		logger.Info().Msg("Detecting application type")
		detection, err := buildpack.Detect(repoPath)
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
		// Could also stream logs to the database or websocket
	}

	// Check if custom Dockerfile exists
	if appType == "docker" {
		// Use custom Dockerfile
		buildOpts := docker.BuildOptions{
			ContextPath: repoPath,
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
			return nil, fmt.Errorf("docker build failed: %w", err)
		}
		result.BuildLogs = buildResult.Logs
	} else {
		// Use buildpack
		buildResult, err := s.imageBuilder.BuildWithBuildpack(
			ctx,
			appType,
			appVersion,
			repoPath,
			imageName,
			imageTag,
			buildEnv,
			logCallback,
		)
		if err != nil {
			return nil, fmt.Errorf("buildpack build failed: %w", err)
		}
		result.BuildLogs = buildResult.Logs
	}

	logger.Info().Msg("Docker image built successfully")

	// Step 4: Push to registry (if configured)
	if s.cfg.DockerRegistry != "" {
		logger.Info().Str("registry", s.cfg.DockerRegistry).Msg("Pushing image to registry")

		pushOpts := docker.PushOptions{
			ImageName: imageName,
			ImageTag:  imageTag,
			Registry:  s.cfg.DockerRegistry,
			Username:  s.cfg.DockerRegistryUser,
			Password:  s.cfg.DockerRegistryPass,
		}

		if err := s.imageBuilder.Push(ctx, pushOpts, logCallback); err != nil {
			return nil, fmt.Errorf("failed to push image: %w", err)
		}

		result.ImageName = fmt.Sprintf("%s/%s", s.cfg.DockerRegistry, imageName)
	}

	return result, nil
}

func (s *Scheduler) deployContainers(ctx context.Context, job *queue.BuildJob, result *BuildResult, logger *log.Logger) error {
	// Update deployment status
	if err := s.updateDeploymentStatus(ctx, job.DeploymentID, StatusDeploying, ""); err != nil {
		return err
	}

	replicas := job.Replicas
	if replicas <= 0 {
		replicas = 1
	}

	logger.Info().Int("replicas", replicas).Msg("Deploying containers")

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

		// Create container
		containerID, err := client.CreateContainer(ctx, &CreateContainerRequest{
			ImageName: fmt.Sprintf("%s:%s", result.ImageName, result.ImageTag),
			Name:      fmt.Sprintf("%s-%s-%d", job.ApplicationID, result.ImageTag[:8], i),
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
		if err := s.saveContainer(ctx, job.DeploymentID, job.ApplicationID, server.ID, containerID, i); err != nil {
			logger.Error().Err(err).Msg("Failed to save container to database")
		}

		logger.Info().
			Str("container_id", containerID).
			Str("server", server.ID).
			Int("replica", i).
			Msg("Container created")
	}

	// Update Traefik configuration
	if err := s.updateTraefikConfig(ctx, job.ApplicationID); err != nil {
		logger.Warn().Err(err).Msg("Failed to update Traefik config")
	}

	return nil
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

func (s *Scheduler) saveContainer(ctx context.Context, deploymentID, appID, serverID, containerID string, replica int) error {
	query := `
		INSERT INTO containers (id, deployment_id, application_id, server_id, docker_container_id, name, status, replica_index, created_at)
		VALUES (gen_random_uuid(), $1, $2, $3, $4, $5, 'running', $6, NOW())
	`
	name := fmt.Sprintf("%s-replica-%d", appID, replica)
	_, err := s.db.Pool().Exec(ctx, query, deploymentID, appID, serverID, containerID, name, replica)
	return err
}

func (s *Scheduler) updateTraefikConfig(ctx context.Context, applicationID string) error {
	// This will be implemented by the Traefik config generator
	// For now, just log it
	log.Info().Str("app_id", applicationID).Msg("Traefik config update requested")
	return nil
}

func (s *Scheduler) runHealthChecks() {
	defer s.wg.Done()

	for {
		select {
		case <-s.healthTicker.C:
			s.checkAllContainers()
		case <-s.stopCh:
			return
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
	}
}

func (s *Scheduler) markContainerUnhealthy(containerID string) {
	ctx := context.Background()
	query := `UPDATE containers SET health_status = 'unhealthy', updated_at = NOW() WHERE id = $1`
	s.db.Pool().Exec(ctx, query, containerID)
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

	if err := client.StopContainer(ctx, dockerID); err != nil {
		return err
	}

	// Update database
	updateQuery := `UPDATE containers SET status = 'stopped', updated_at = NOW() WHERE id = $1`
	_, err = s.db.Pool().Exec(ctx, updateQuery, containerID)
	return err
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
