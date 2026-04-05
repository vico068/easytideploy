package repository

import (
	"context"
	"time"

	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/google/uuid"
)

// Application represents an application record
type Application struct {
	ID                     string
	UserID                 string
	Name                   string
	Slug                   string
	GitRepository          string
	GitBranch              string
	GitToken               string
	RootDirectory          string
	Type                   string
	RuntimeVersion         string
	BuildCommand           string
	StartCommand           string
	Port                   int
	Replicas               int
	CPULimit               int
	MemoryLimit            int
	HealthCheckPath        string
	HealthCheckInterval    int
	AutoDeploy             bool
	SSLEnabled             bool
	Status                 string
	TraefikConfigUpdatedAt *time.Time
	CreatedAt              time.Time
	UpdatedAt              time.Time
}

// Deployment represents a deployment record
type Deployment struct {
	ID            string
	ApplicationID string
	Status        string
	CommitSha     string
	CommitMessage string
	ImageName     string
	ImageTag      string
	TriggeredBy   string
	ErrorMessage  string
	BuildLogs     string
	StartedAt     *time.Time
	CompletedAt   *time.Time
	CreatedAt     time.Time
}

// Container represents a container record
type Container struct {
	ID                string
	DeploymentID      string
	ApplicationID     string
	ServerID          string
	DockerContainerID string
	Name              string
	Status            string
	HealthStatus      string
	ReplicaIndex      int
	InternalPort      int
	HostPort          int
	HealthCheckedAt   *time.Time
	CreatedAt         time.Time
	UpdatedAt         time.Time
}

// Server represents a server record
type Server struct {
	ID              string
	Name            string
	IPAddress       string
	AgentAddress    string
	Status          string
	CPUCores        int
	MemoryTotal     int64
	DiskTotal       int64
	MaxContainers   int
	DockerVersion   string
	LastHeartbeat   *time.Time
	CreatedAt       time.Time
	UpdatedAt       time.Time
}

// Domain represents a domain record
type Domain struct {
	ID            string
	ApplicationID string
	Domain        string
	IsPrimary     bool
	Verified      bool
	SSLStatus     string
	SSLExpiresAt  *time.Time
	CreatedAt     time.Time
}

// EnvironmentVariable represents an environment variable record
type EnvironmentVariable struct {
	ID            string
	ApplicationID string
	Key           string
	Value         string
	IsSecret      bool
	CreatedAt     time.Time
}

// Repository provides database access methods
type Repository struct {
	db *database.DB
}

// NewRepository creates a new Repository instance
func NewRepository(db *database.DB) *Repository {
	return &Repository{db: db}
}

// Application methods

// GetApplication retrieves an application by ID
func (r *Repository) GetApplication(ctx context.Context, id string) (*Application, error) {
	query := `
		SELECT id, user_id, name, slug, COALESCE(git_repository, ''), COALESCE(git_branch, 'main'), COALESCE(git_token, ''), COALESCE(root_directory, '/'),
		       type, COALESCE(runtime_version, ''), COALESCE(build_command, ''), COALESCE(start_command, ''), port, replicas, cpu_limit, memory_limit,
		       COALESCE(health_check_path, '/health'), health_check_interval, auto_deploy, ssl_enabled, status,
		       traefik_config_updated_at, created_at, updated_at
		FROM applications
		WHERE id = $1
	`

	var app Application
	err := r.db.Pool().QueryRow(ctx, query, id).Scan(
		&app.ID, &app.UserID, &app.Name, &app.Slug, &app.GitRepository, &app.GitBranch,
		&app.GitToken, &app.RootDirectory, &app.Type, &app.RuntimeVersion, &app.BuildCommand, &app.StartCommand,
		&app.Port, &app.Replicas, &app.CPULimit, &app.MemoryLimit, &app.HealthCheckPath,
		&app.HealthCheckInterval, &app.AutoDeploy, &app.SSLEnabled, &app.Status,
		&app.TraefikConfigUpdatedAt, &app.CreatedAt, &app.UpdatedAt,
	)
	if err != nil {
		return nil, err
	}
	return &app, nil
}

// GetApplicationBySlug retrieves an application by slug
func (r *Repository) GetApplicationBySlug(ctx context.Context, slug string) (*Application, error) {
	query := `
		SELECT id, user_id, name, slug, COALESCE(git_repository, ''), COALESCE(git_branch, 'main'), COALESCE(git_token, ''), COALESCE(root_directory, '/'),
		       type, COALESCE(runtime_version, ''), COALESCE(build_command, ''), COALESCE(start_command, ''), port, replicas, cpu_limit, memory_limit,
		       COALESCE(health_check_path, '/health'), health_check_interval, auto_deploy, ssl_enabled, status,
		       traefik_config_updated_at, created_at, updated_at
		FROM applications
		WHERE slug = $1
	`

	var app Application
	err := r.db.Pool().QueryRow(ctx, query, slug).Scan(
		&app.ID, &app.UserID, &app.Name, &app.Slug, &app.GitRepository, &app.GitBranch,
		&app.GitToken, &app.RootDirectory, &app.Type, &app.RuntimeVersion, &app.BuildCommand, &app.StartCommand,
		&app.Port, &app.Replicas, &app.CPULimit, &app.MemoryLimit, &app.HealthCheckPath,
		&app.HealthCheckInterval, &app.AutoDeploy, &app.SSLEnabled, &app.Status,
		&app.TraefikConfigUpdatedAt, &app.CreatedAt, &app.UpdatedAt,
	)
	if err != nil {
		return nil, err
	}
	return &app, nil
}

// ListApplications retrieves all applications for a user
func (r *Repository) ListApplications(ctx context.Context, userID string) ([]*Application, error) {
	query := `
		SELECT id, user_id, name, slug, COALESCE(git_repository, ''), COALESCE(git_branch, 'main'), COALESCE(git_token, ''), COALESCE(root_directory, '/'),
		       type, COALESCE(runtime_version, ''), COALESCE(build_command, ''), COALESCE(start_command, ''), port, replicas, cpu_limit, memory_limit,
		       COALESCE(health_check_path, '/health'), health_check_interval, auto_deploy, ssl_enabled, status,
		       traefik_config_updated_at, created_at, updated_at
		FROM applications
		WHERE user_id = $1
		ORDER BY created_at DESC
	`

	rows, err := r.db.Pool().Query(ctx, query, userID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var apps []*Application
	for rows.Next() {
		var app Application
		if err := rows.Scan(
			&app.ID, &app.UserID, &app.Name, &app.Slug, &app.GitRepository, &app.GitBranch,
			&app.GitToken, &app.RootDirectory, &app.Type, &app.RuntimeVersion, &app.BuildCommand, &app.StartCommand,
			&app.Port, &app.Replicas, &app.CPULimit, &app.MemoryLimit, &app.HealthCheckPath,
			&app.HealthCheckInterval, &app.AutoDeploy, &app.SSLEnabled, &app.Status,
			&app.TraefikConfigUpdatedAt, &app.CreatedAt, &app.UpdatedAt,
		); err != nil {
			continue
		}
		apps = append(apps, &app)
	}
	return apps, nil
}

// UpdateApplicationStatus updates the status of an application
func (r *Repository) UpdateApplicationStatus(ctx context.Context, id, status string) error {
	query := `UPDATE applications SET status = $1, updated_at = NOW() WHERE id = $2`
	_, err := r.db.Pool().Exec(ctx, query, status, id)
	return err
}

// Deployment methods

// CreateDeployment creates a new deployment
func (r *Repository) CreateDeployment(ctx context.Context, d *Deployment) error {
	if d.ID == "" {
		d.ID = uuid.New().String()
	}

	query := `
		INSERT INTO deployments (id, application_id, status, commit_sha, commit_message, triggered_by, created_at)
		VALUES ($1, $2, $3, $4, $5, $6, NOW())
	`

	_, err := r.db.Pool().Exec(ctx, query, d.ID, d.ApplicationID, d.Status, d.CommitSha, d.CommitMessage, d.TriggeredBy)
	return err
}

// GetDeployment retrieves a deployment by ID
func (r *Repository) GetDeployment(ctx context.Context, id string) (*Deployment, error) {
	query := `
		SELECT id, application_id, status, commit_sha, commit_message, image_name, image_tag,
		       triggered_by, error_message, build_logs, started_at, completed_at, created_at
		FROM deployments
		WHERE id = $1
	`

	var d Deployment
	err := r.db.Pool().QueryRow(ctx, query, id).Scan(
		&d.ID, &d.ApplicationID, &d.Status, &d.CommitSha, &d.CommitMessage, &d.ImageName,
		&d.ImageTag, &d.TriggeredBy, &d.ErrorMessage, &d.BuildLogs, &d.StartedAt,
		&d.CompletedAt, &d.CreatedAt,
	)
	if err != nil {
		return nil, err
	}
	return &d, nil
}

// GetLatestDeployment retrieves the latest deployment for an application
func (r *Repository) GetLatestDeployment(ctx context.Context, applicationID string) (*Deployment, error) {
	query := `
		SELECT id, application_id, status, commit_sha, commit_message, image_name, image_tag,
		       triggered_by, error_message, build_logs, started_at, completed_at, created_at
		FROM deployments
		WHERE application_id = $1
		ORDER BY created_at DESC
		LIMIT 1
	`

	var d Deployment
	err := r.db.Pool().QueryRow(ctx, query, applicationID).Scan(
		&d.ID, &d.ApplicationID, &d.Status, &d.CommitSha, &d.CommitMessage, &d.ImageName,
		&d.ImageTag, &d.TriggeredBy, &d.ErrorMessage, &d.BuildLogs, &d.StartedAt,
		&d.CompletedAt, &d.CreatedAt,
	)
	if err != nil {
		return nil, err
	}
	return &d, nil
}

// ListDeployments retrieves deployments for an application
func (r *Repository) ListDeployments(ctx context.Context, applicationID string, limit int) ([]*Deployment, error) {
	query := `
		SELECT id, application_id, status, commit_sha, commit_message, image_name, image_tag,
		       triggered_by, error_message, build_logs, started_at, completed_at, created_at
		FROM deployments
		WHERE application_id = $1
		ORDER BY created_at DESC
		LIMIT $2
	`

	rows, err := r.db.Pool().Query(ctx, query, applicationID, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var deployments []*Deployment
	for rows.Next() {
		var d Deployment
		if err := rows.Scan(
			&d.ID, &d.ApplicationID, &d.Status, &d.CommitSha, &d.CommitMessage, &d.ImageName,
			&d.ImageTag, &d.TriggeredBy, &d.ErrorMessage, &d.BuildLogs, &d.StartedAt,
			&d.CompletedAt, &d.CreatedAt,
		); err != nil {
			continue
		}
		deployments = append(deployments, &d)
	}
	return deployments, nil
}

// UpdateDeploymentStatus updates the status of a deployment
func (r *Repository) UpdateDeploymentStatus(ctx context.Context, id, status, errorMsg string) error {
	var query string
	if status == "running" {
		query = `UPDATE deployments SET status = $1, completed_at = NOW() WHERE id = $2`
		_, err := r.db.Pool().Exec(ctx, query, status, id)
		return err
	} else if status == "failed" {
		query = `UPDATE deployments SET status = $1, error_message = $2, completed_at = NOW() WHERE id = $3`
		_, err := r.db.Pool().Exec(ctx, query, status, errorMsg, id)
		return err
	} else if status == "building" || status == "deploying" {
		query = `UPDATE deployments SET status = $1, started_at = COALESCE(started_at, NOW()) WHERE id = $2`
		_, err := r.db.Pool().Exec(ctx, query, status, id)
		return err
	}

	query = `UPDATE deployments SET status = $1 WHERE id = $2`
	_, err := r.db.Pool().Exec(ctx, query, status, id)
	return err
}

// UpdateDeploymentImage updates the image information for a deployment
func (r *Repository) UpdateDeploymentImage(ctx context.Context, id, imageName, imageTag string) error {
	query := `UPDATE deployments SET image_name = $1, image_tag = $2 WHERE id = $3`
	_, err := r.db.Pool().Exec(ctx, query, imageName, imageTag, id)
	return err
}

// AppendDeploymentLogs appends logs to a deployment
func (r *Repository) AppendDeploymentLogs(ctx context.Context, id, logs string) error {
	query := `UPDATE deployments SET build_logs = COALESCE(build_logs, '') || $1 WHERE id = $2`
	_, err := r.db.Pool().Exec(ctx, query, logs, id)
	return err
}

// Container methods

// CreateContainer creates a new container
func (r *Repository) CreateContainer(ctx context.Context, c *Container) error {
	if c.ID == "" {
		c.ID = uuid.New().String()
	}

	query := `
		INSERT INTO containers (id, deployment_id, application_id, server_id, docker_container_id,
		                        name, status, health_status, replica_index, internal_port, host_port, created_at)
		VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, NOW())
	`

	_, err := r.db.Pool().Exec(ctx, query,
		c.ID, c.DeploymentID, c.ApplicationID, c.ServerID, c.DockerContainerID,
		c.Name, c.Status, c.HealthStatus, c.ReplicaIndex, c.InternalPort, c.HostPort,
	)
	return err
}

// GetContainer retrieves a container by ID
func (r *Repository) GetContainer(ctx context.Context, id string) (*Container, error) {
	query := `
		SELECT id, deployment_id, application_id, server_id, docker_container_id, name,
		       status, health_status, replica_index, internal_port, host_port,
		       health_checked_at, created_at, updated_at
		FROM containers
		WHERE id = $1
	`

	var c Container
	err := r.db.Pool().QueryRow(ctx, query, id).Scan(
		&c.ID, &c.DeploymentID, &c.ApplicationID, &c.ServerID, &c.DockerContainerID,
		&c.Name, &c.Status, &c.HealthStatus, &c.ReplicaIndex, &c.InternalPort, &c.HostPort,
		&c.HealthCheckedAt, &c.CreatedAt, &c.UpdatedAt,
	)
	if err != nil {
		return nil, err
	}
	return &c, nil
}

// ListContainersByApplication retrieves containers for an application
func (r *Repository) ListContainersByApplication(ctx context.Context, applicationID string) ([]*Container, error) {
	query := `
		SELECT id, deployment_id, application_id, server_id, docker_container_id, name,
		       status, health_status, replica_index, internal_port, host_port,
		       health_checked_at, created_at, updated_at
		FROM containers
		WHERE application_id = $1
		ORDER BY replica_index
	`

	rows, err := r.db.Pool().Query(ctx, query, applicationID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var containers []*Container
	for rows.Next() {
		var c Container
		if err := rows.Scan(
			&c.ID, &c.DeploymentID, &c.ApplicationID, &c.ServerID, &c.DockerContainerID,
			&c.Name, &c.Status, &c.HealthStatus, &c.ReplicaIndex, &c.InternalPort, &c.HostPort,
			&c.HealthCheckedAt, &c.CreatedAt, &c.UpdatedAt,
		); err != nil {
			continue
		}
		containers = append(containers, &c)
	}
	return containers, nil
}

// ListRunningContainers retrieves all running containers
func (r *Repository) ListRunningContainers(ctx context.Context) ([]*Container, error) {
	query := `
		SELECT id, deployment_id, application_id, server_id, docker_container_id, name,
		       status, health_status, replica_index, internal_port, host_port,
		       health_checked_at, created_at, updated_at
		FROM containers
		WHERE status = 'running'
	`

	rows, err := r.db.Pool().Query(ctx, query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var containers []*Container
	for rows.Next() {
		var c Container
		if err := rows.Scan(
			&c.ID, &c.DeploymentID, &c.ApplicationID, &c.ServerID, &c.DockerContainerID,
			&c.Name, &c.Status, &c.HealthStatus, &c.ReplicaIndex, &c.InternalPort, &c.HostPort,
			&c.HealthCheckedAt, &c.CreatedAt, &c.UpdatedAt,
		); err != nil {
			continue
		}
		containers = append(containers, &c)
	}
	return containers, nil
}

// UpdateContainerStatus updates the status of a container
func (r *Repository) UpdateContainerStatus(ctx context.Context, id, status string) error {
	query := `UPDATE containers SET status = $1, updated_at = NOW() WHERE id = $2`
	_, err := r.db.Pool().Exec(ctx, query, status, id)
	return err
}

// UpdateContainerHealth updates the health status of a container
func (r *Repository) UpdateContainerHealth(ctx context.Context, id, healthStatus string) error {
	query := `UPDATE containers SET health_status = $1, health_checked_at = NOW(), updated_at = NOW() WHERE id = $2`
	_, err := r.db.Pool().Exec(ctx, query, healthStatus, id)
	return err
}

// DeleteContainer deletes a container
func (r *Repository) DeleteContainer(ctx context.Context, id string) error {
	query := `DELETE FROM containers WHERE id = $1`
	_, err := r.db.Pool().Exec(ctx, query, id)
	return err
}

// Server methods

// GetServer retrieves a server by ID
func (r *Repository) GetServer(ctx context.Context, id string) (*Server, error) {
	query := `
		SELECT id, name, ip_address, agent_address, status, cpu_cores, memory_total,
		       disk_total, max_containers, docker_version, last_heartbeat, created_at, updated_at
		FROM servers
		WHERE id = $1
	`

	var s Server
	err := r.db.Pool().QueryRow(ctx, query, id).Scan(
		&s.ID, &s.Name, &s.IPAddress, &s.AgentAddress, &s.Status, &s.CPUCores,
		&s.MemoryTotal, &s.DiskTotal, &s.MaxContainers, &s.DockerVersion,
		&s.LastHeartbeat, &s.CreatedAt, &s.UpdatedAt,
	)
	if err != nil {
		return nil, err
	}
	return &s, nil
}

// ListServers retrieves all servers
func (r *Repository) ListServers(ctx context.Context) ([]*Server, error) {
	query := `
		SELECT id, name, ip_address, agent_address, status, cpu_cores, memory_total,
		       disk_total, max_containers, docker_version, last_heartbeat, created_at, updated_at
		FROM servers
		ORDER BY name
	`

	rows, err := r.db.Pool().Query(ctx, query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var servers []*Server
	for rows.Next() {
		var s Server
		if err := rows.Scan(
			&s.ID, &s.Name, &s.IPAddress, &s.AgentAddress, &s.Status, &s.CPUCores,
			&s.MemoryTotal, &s.DiskTotal, &s.MaxContainers, &s.DockerVersion,
			&s.LastHeartbeat, &s.CreatedAt, &s.UpdatedAt,
		); err != nil {
			continue
		}
		servers = append(servers, &s)
	}
	return servers, nil
}

// ListOnlineServers retrieves all online servers
func (r *Repository) ListOnlineServers(ctx context.Context) ([]*Server, error) {
	query := `
		SELECT id, name, ip_address, agent_address, status, cpu_cores, memory_total,
		       disk_total, max_containers, docker_version, last_heartbeat, created_at, updated_at
		FROM servers
		WHERE status = 'online'
		ORDER BY name
	`

	rows, err := r.db.Pool().Query(ctx, query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var servers []*Server
	for rows.Next() {
		var s Server
		if err := rows.Scan(
			&s.ID, &s.Name, &s.IPAddress, &s.AgentAddress, &s.Status, &s.CPUCores,
			&s.MemoryTotal, &s.DiskTotal, &s.MaxContainers, &s.DockerVersion,
			&s.LastHeartbeat, &s.CreatedAt, &s.UpdatedAt,
		); err != nil {
			continue
		}
		servers = append(servers, &s)
	}
	return servers, nil
}

// UpdateServerStatus updates the status of a server
func (r *Repository) UpdateServerStatus(ctx context.Context, id, status string) error {
	query := `UPDATE servers SET status = $1, updated_at = NOW() WHERE id = $2`
	_, err := r.db.Pool().Exec(ctx, query, status, id)
	return err
}

// UpdateServerHeartbeat updates the heartbeat of a server
func (r *Repository) UpdateServerHeartbeat(ctx context.Context, id string, cpuCores int, memTotal, diskTotal int64) error {
	query := `
		UPDATE servers
		SET last_heartbeat = NOW(), status = 'online', cpu_cores = $2, memory_total = $3, disk_total = $4, updated_at = NOW()
		WHERE id = $1
	`
	_, err := r.db.Pool().Exec(ctx, query, id, cpuCores, memTotal, diskTotal)
	return err
}

// Domain methods

// GetDomainsByApplication retrieves domains for an application
func (r *Repository) GetDomainsByApplication(ctx context.Context, applicationID string) ([]*Domain, error) {
	query := `
		SELECT id, application_id, domain, is_primary, verified, ssl_status, ssl_expires_at, created_at
		FROM domains
		WHERE application_id = $1
		ORDER BY is_primary DESC, domain
	`

	rows, err := r.db.Pool().Query(ctx, query, applicationID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var domains []*Domain
	for rows.Next() {
		var d Domain
		if err := rows.Scan(&d.ID, &d.ApplicationID, &d.Domain, &d.IsPrimary, &d.Verified, &d.SSLStatus, &d.SSLExpiresAt, &d.CreatedAt); err != nil {
			continue
		}
		domains = append(domains, &d)
	}
	return domains, nil
}

// Environment variable methods

// GetEnvironmentVariables retrieves environment variables for an application
func (r *Repository) GetEnvironmentVariables(ctx context.Context, applicationID string) ([]*EnvironmentVariable, error) {
	query := `
		SELECT id, application_id, key, value, is_secret, created_at
		FROM environment_variables
		WHERE application_id = $1
		ORDER BY key
	`

	rows, err := r.db.Pool().Query(ctx, query, applicationID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var vars []*EnvironmentVariable
	for rows.Next() {
		var v EnvironmentVariable
		if err := rows.Scan(&v.ID, &v.ApplicationID, &v.Key, &v.Value, &v.IsSecret, &v.CreatedAt); err != nil {
			continue
		}
		vars = append(vars, &v)
	}
	return vars, nil
}

// GetEnvironmentVariablesAsMap retrieves environment variables as a map
func (r *Repository) GetEnvironmentVariablesAsMap(ctx context.Context, applicationID string) (map[string]string, error) {
	vars, err := r.GetEnvironmentVariables(ctx, applicationID)
	if err != nil {
		return nil, err
	}

	result := make(map[string]string)
	for _, v := range vars {
		result[v.Key] = v.Value
	}
	return result, nil
}

// Statistics methods

// GetApplicationStats retrieves statistics for an application
type ApplicationStats struct {
	TotalDeployments       int
	SuccessfulDeployments  int
	FailedDeployments      int
	RunningContainers      int
	AverageBuildTime       float64
	LastDeploymentAt       *time.Time
}

func (r *Repository) GetApplicationStats(ctx context.Context, applicationID string) (*ApplicationStats, error) {
	query := `
		SELECT
			(SELECT COUNT(*) FROM deployments WHERE application_id = $1) as total_deployments,
			(SELECT COUNT(*) FROM deployments WHERE application_id = $1 AND status = 'running') as successful,
			(SELECT COUNT(*) FROM deployments WHERE application_id = $1 AND status = 'failed') as failed,
			(SELECT COUNT(*) FROM containers WHERE application_id = $1 AND status = 'running') as running_containers,
			(SELECT AVG(EXTRACT(EPOCH FROM (completed_at - started_at))) FROM deployments WHERE application_id = $1 AND completed_at IS NOT NULL) as avg_build_time,
			(SELECT MAX(created_at) FROM deployments WHERE application_id = $1) as last_deployment
	`

	var stats ApplicationStats
	err := r.db.Pool().QueryRow(ctx, query, applicationID).Scan(
		&stats.TotalDeployments,
		&stats.SuccessfulDeployments,
		&stats.FailedDeployments,
		&stats.RunningContainers,
		&stats.AverageBuildTime,
		&stats.LastDeploymentAt,
	)
	if err != nil {
		return nil, err
	}
	return &stats, nil
}

// GetGlobalStats retrieves global platform statistics
type GlobalStats struct {
	TotalApplications     int
	TotalDeployments      int
	TotalContainers       int
	OnlineServers         int
	TotalServers          int
	DeploymentsToday      int
	SuccessfulDeployments int
	FailedDeployments     int
}

func (r *Repository) GetGlobalStats(ctx context.Context) (*GlobalStats, error) {
	query := `
		SELECT
			(SELECT COUNT(*) FROM applications) as total_apps,
			(SELECT COUNT(*) FROM deployments) as total_deployments,
			(SELECT COUNT(*) FROM containers WHERE status = 'running') as total_containers,
			(SELECT COUNT(*) FROM servers WHERE status = 'online') as online_servers,
			(SELECT COUNT(*) FROM servers) as total_servers,
			(SELECT COUNT(*) FROM deployments WHERE created_at >= CURRENT_DATE) as deployments_today,
			(SELECT COUNT(*) FROM deployments WHERE status = 'running') as successful,
			(SELECT COUNT(*) FROM deployments WHERE status = 'failed') as failed
	`

	var stats GlobalStats
	err := r.db.Pool().QueryRow(ctx, query).Scan(
		&stats.TotalApplications,
		&stats.TotalDeployments,
		&stats.TotalContainers,
		&stats.OnlineServers,
		&stats.TotalServers,
		&stats.DeploymentsToday,
		&stats.SuccessfulDeployments,
		&stats.FailedDeployments,
	)
	if err != nil {
		return nil, err
	}
	return &stats, nil
}
