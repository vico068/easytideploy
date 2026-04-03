package docker

import (
	"bufio"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"strconv"
	"strings"
	"syscall"
	"time"

	"github.com/docker/docker/api/types"
	"github.com/docker/docker/api/types/container"
	"github.com/docker/docker/api/types/filters"
	"github.com/docker/docker/api/types/image"
	"github.com/docker/docker/api/types/network"
	"github.com/docker/docker/client"
	"github.com/docker/go-connections/nat"
)

// Client wraps the Docker client
type Client struct {
	docker *client.Client
}

// ContainerConfig holds container creation configuration
type ContainerConfig struct {
	Image        string
	Name         string
	Port         int
	HostPort     int
	Environment  map[string]string
	CPULimit     int64 // millicores (500 = 0.5 CPU)
	MemoryLimit  int64 // bytes
	Labels       map[string]string
	HealthCheck  *HealthCheck
	NetworkMode  string
	RestartPolicy string
	Volumes      map[string]string
	Command      []string
}

// HealthCheck holds health check configuration
type HealthCheck struct {
	Path     string
	Interval int // seconds
	Timeout  int // seconds
	Retries  int
}

// ContainerResult holds container creation result
type ContainerResult struct {
	ID        string
	Name      string
	IPAddress string
	HostPort  int
}

// ContainerStats holds container statistics
type ContainerStats struct {
	ContainerID   string
	Status        string
	Health        string
	CPUPercent    float64
	MemoryUsage   int64
	MemoryLimit   int64
	MemoryPercent float64
	NetworkRxBytes int64
	NetworkTxBytes int64
	BlockRead     int64
	BlockWrite    int64
	PIDs          int64
	RestartCount  int
	StartedAt     time.Time
}

// ServerStats holds server-level statistics
type ServerStats struct {
	CPUCores       int
	CPUUsage       float64
	MemoryUsage    int64
	MemoryTotal    int64
	MemoryPercent  float64
	DiskUsage      int64
	DiskTotal      int64
	DiskPercent    float64
	ContainerCount int
	RunningCount   int
	NetworkRxBytes int64
	NetworkTxBytes int64
}

// ExecResult holds command execution result
type ExecResult struct {
	ExitCode int
	Output   string
	Stderr   string
}

// NewClient creates a new Docker client
func NewClient() (*Client, error) {
	cli, err := client.NewClientWithOpts(client.FromEnv, client.WithAPIVersionNegotiation())
	if err != nil {
		return nil, fmt.Errorf("failed to create Docker client: %w", err)
	}

	// Test connection
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if _, err := cli.Ping(ctx); err != nil {
		return nil, fmt.Errorf("failed to connect to Docker daemon: %w", err)
	}

	return &Client{docker: cli}, nil
}

// Close closes the Docker client connection
func (c *Client) Close() error {
	if c.docker != nil {
		return c.docker.Close()
	}
	return nil
}

// Create creates and starts a new container
func (c *Client) Create(ctx context.Context, cfg *ContainerConfig) (*ContainerResult, error) {
	// Convert environment map to slice
	env := make([]string, 0, len(cfg.Environment))
	for k, v := range cfg.Environment {
		env = append(env, k+"="+v)
	}

	// Set up port bindings
	exposedPorts := nat.PortSet{}
	portBindings := nat.PortMap{}

	if cfg.Port > 0 {
		portStr := fmt.Sprintf("%d/tcp", cfg.Port)
		exposedPorts[nat.Port(portStr)] = struct{}{}

		hostPort := ""
		if cfg.HostPort > 0 {
			hostPort = strconv.Itoa(cfg.HostPort)
		}
		portBindings[nat.Port(portStr)] = []nat.PortBinding{
			{HostIP: "0.0.0.0", HostPort: hostPort},
		}
	}

	// Set up labels
	labels := map[string]string{
		"easydeploy.managed": "true",
		"easydeploy.created": time.Now().Format(time.RFC3339),
	}
	for k, v := range cfg.Labels {
		labels[k] = v
	}

	// Set up health check
	var healthCheck *container.HealthConfig
	if cfg.HealthCheck != nil && cfg.HealthCheck.Path != "" {
		interval := time.Duration(cfg.HealthCheck.Interval) * time.Second
		if interval == 0 {
			interval = 30 * time.Second
		}
		timeout := time.Duration(cfg.HealthCheck.Timeout) * time.Second
		if timeout == 0 {
			timeout = 10 * time.Second
		}
		retries := cfg.HealthCheck.Retries
		if retries == 0 {
			retries = 3
		}

		healthCheck = &container.HealthConfig{
			Test:     []string{"CMD-SHELL", fmt.Sprintf("curl -f http://localhost:%d%s || exit 1", cfg.Port, cfg.HealthCheck.Path)},
			Interval: interval,
			Timeout:  timeout,
			Retries:  retries,
		}
	}

	// Create container config
	containerCfg := &container.Config{
		Image:        cfg.Image,
		Env:          env,
		ExposedPorts: exposedPorts,
		Labels:       labels,
		Healthcheck:  healthCheck,
	}

	if len(cfg.Command) > 0 {
		containerCfg.Cmd = cfg.Command
	}

	// Create host config with resource limits
	restartPolicy := cfg.RestartPolicy
	if restartPolicy == "" {
		restartPolicy = "unless-stopped"
	}

	hostCfg := &container.HostConfig{
		PortBindings: portBindings,
		Resources: container.Resources{
			NanoCPUs:  cfg.CPULimit * 1000000, // millicores to nanocores
			Memory:    cfg.MemoryLimit,
			PidsLimit: ptrInt64(512),          // Limit number of PIDs
		},
		RestartPolicy: container.RestartPolicy{
			Name: container.RestartPolicyMode(restartPolicy),
		},
		SecurityOpt: []string{
			"no-new-privileges:true",
		},
		ReadonlyRootfs: false, // Apps need to write to /tmp, etc.
		Tmpfs: map[string]string{
			"/tmp": "rw,noexec,nosuid,size=256m",
		},
		LogConfig: container.LogConfig{
			Type: "json-file",
			Config: map[string]string{
				"max-size": "50m",
				"max-file": "3",
			},
		},
	}

	// Set up volumes
	if len(cfg.Volumes) > 0 {
		binds := make([]string, 0, len(cfg.Volumes))
		for host, container := range cfg.Volumes {
			binds = append(binds, fmt.Sprintf("%s:%s", host, container))
		}
		hostCfg.Binds = binds
	}

	if cfg.NetworkMode != "" {
		hostCfg.NetworkMode = container.NetworkMode(cfg.NetworkMode)
	}

	// Network config
	networkCfg := &network.NetworkingConfig{}

	// Create container
	resp, err := c.docker.ContainerCreate(ctx, containerCfg, hostCfg, networkCfg, nil, cfg.Name)
	if err != nil {
		return nil, fmt.Errorf("failed to create container: %w", err)
	}

	// Start container
	if err := c.docker.ContainerStart(ctx, resp.ID, container.StartOptions{}); err != nil {
		// Cleanup on failure
		c.docker.ContainerRemove(ctx, resp.ID, container.RemoveOptions{Force: true})
		return nil, fmt.Errorf("failed to start container: %w", err)
	}

	// Get container info
	inspect, err := c.docker.ContainerInspect(ctx, resp.ID)
	if err != nil {
		return nil, fmt.Errorf("failed to inspect container: %w", err)
	}

	// Get container IP
	var ip string
	for _, network := range inspect.NetworkSettings.Networks {
		ip = network.IPAddress
		break
	}

	// Get assigned host port
	hostPort := 0
	if cfg.Port > 0 {
		portStr := fmt.Sprintf("%d/tcp", cfg.Port)
		if bindings, ok := inspect.NetworkSettings.Ports[nat.Port(portStr)]; ok && len(bindings) > 0 {
			hostPort, _ = strconv.Atoi(bindings[0].HostPort)
		}
	}

	return &ContainerResult{
		ID:        resp.ID,
		Name:      inspect.Name,
		IPAddress: ip,
		HostPort:  hostPort,
	}, nil
}

// Remove removes a container
func (c *Client) Remove(ctx context.Context, containerID string, force bool) error {
	return c.docker.ContainerRemove(ctx, containerID, container.RemoveOptions{
		Force:         force,
		RemoveVolumes: true,
	})
}

// Stop stops a container
func (c *Client) Stop(ctx context.Context, containerID string, timeout int) error {
	if timeout <= 0 {
		timeout = 30
	}
	return c.docker.ContainerStop(ctx, containerID, container.StopOptions{
		Timeout: &timeout,
	})
}

// Start starts a container
func (c *Client) Start(ctx context.Context, containerID string) error {
	return c.docker.ContainerStart(ctx, containerID, container.StartOptions{})
}

// Restart restarts a container
func (c *Client) Restart(ctx context.Context, containerID string, timeout int) error {
	if timeout <= 0 {
		timeout = 30
	}
	return c.docker.ContainerRestart(ctx, containerID, container.StopOptions{
		Timeout: &timeout,
	})
}

// Logs returns container logs
func (c *Client) Logs(ctx context.Context, containerID string, lines int, follow bool) (io.ReadCloser, error) {
	tailLines := "100"
	if lines > 0 {
		tailLines = strconv.Itoa(lines)
	}

	options := container.LogsOptions{
		ShowStdout: true,
		ShowStderr: true,
		Tail:       tailLines,
		Follow:     follow,
		Timestamps: true,
	}

	return c.docker.ContainerLogs(ctx, containerID, options)
}

// LogsString returns container logs as a string
func (c *Client) LogsString(ctx context.Context, containerID string, lines int) (string, error) {
	reader, err := c.Logs(ctx, containerID, lines, false)
	if err != nil {
		return "", err
	}
	defer reader.Close()

	var output strings.Builder
	scanner := bufio.NewScanner(reader)
	for scanner.Scan() {
		line := scanner.Bytes()
		// Docker logs have 8-byte header, skip it for plain output
		if len(line) > 8 {
			output.Write(line[8:])
		} else {
			output.Write(line)
		}
		output.WriteString("\n")
	}

	return output.String(), scanner.Err()
}

// Stats returns container statistics
func (c *Client) Stats(ctx context.Context, containerID string) (*ContainerStats, error) {
	// Get container inspect for basic info
	inspect, err := c.docker.ContainerInspect(ctx, containerID)
	if err != nil {
		return nil, fmt.Errorf("failed to inspect container: %w", err)
	}

	health := "unknown"
	if inspect.State.Health != nil {
		health = inspect.State.Health.Status
	}

	startedAt, _ := time.Parse(time.RFC3339, inspect.State.StartedAt)

	stats := &ContainerStats{
		ContainerID:  containerID,
		Status:       inspect.State.Status,
		Health:       health,
		RestartCount: inspect.RestartCount,
		StartedAt:    startedAt,
	}

	// Get live stats
	statsResp, err := c.docker.ContainerStats(ctx, containerID, false)
	if err != nil {
		return stats, nil // Return partial stats on error
	}
	defer statsResp.Body.Close()

	var statsJSON types.StatsJSON
	if err := json.NewDecoder(statsResp.Body).Decode(&statsJSON); err != nil {
		return stats, nil
	}

	// Calculate CPU percentage
	cpuDelta := float64(statsJSON.CPUStats.CPUUsage.TotalUsage - statsJSON.PreCPUStats.CPUUsage.TotalUsage)
	systemDelta := float64(statsJSON.CPUStats.SystemUsage - statsJSON.PreCPUStats.SystemUsage)
	cpuCount := float64(statsJSON.CPUStats.OnlineCPUs)
	if cpuCount == 0 {
		cpuCount = float64(len(statsJSON.CPUStats.CPUUsage.PercpuUsage))
	}
	if systemDelta > 0 && cpuDelta > 0 {
		stats.CPUPercent = (cpuDelta / systemDelta) * cpuCount * 100.0
	}

	// Memory stats
	stats.MemoryUsage = int64(statsJSON.MemoryStats.Usage)
	stats.MemoryLimit = int64(statsJSON.MemoryStats.Limit)
	if stats.MemoryLimit > 0 {
		stats.MemoryPercent = float64(stats.MemoryUsage) / float64(stats.MemoryLimit) * 100.0
	}

	// Network stats
	for _, netStats := range statsJSON.Networks {
		stats.NetworkRxBytes += int64(netStats.RxBytes)
		stats.NetworkTxBytes += int64(netStats.TxBytes)
	}

	// Block I/O
	for _, blk := range statsJSON.BlkioStats.IoServiceBytesRecursive {
		switch blk.Op {
		case "read", "Read":
			stats.BlockRead += int64(blk.Value)
		case "write", "Write":
			stats.BlockWrite += int64(blk.Value)
		}
	}

	// PIDs
	stats.PIDs = int64(statsJSON.PidsStats.Current)

	return stats, nil
}

// Pull pulls an image from registry
func (c *Client) Pull(ctx context.Context, imageName string, registryAuth string) error {
	options := image.PullOptions{}
	if registryAuth != "" {
		options.RegistryAuth = registryAuth
	}

	reader, err := c.docker.ImagePull(ctx, imageName, options)
	if err != nil {
		return fmt.Errorf("failed to pull image: %w", err)
	}
	defer reader.Close()

	// Consume the output to wait for completion
	io.Copy(io.Discard, reader)

	return nil
}

// Exec executes a command inside a container
func (c *Client) Exec(ctx context.Context, containerID string, command []string) (*ExecResult, error) {
	execConfig := container.ExecOptions{
		Cmd:          command,
		AttachStdout: true,
		AttachStderr: true,
	}

	execID, err := c.docker.ContainerExecCreate(ctx, containerID, execConfig)
	if err != nil {
		return nil, fmt.Errorf("failed to create exec: %w", err)
	}

	resp, err := c.docker.ContainerExecAttach(ctx, execID.ID, container.ExecAttachOptions{})
	if err != nil {
		return nil, fmt.Errorf("failed to attach exec: %w", err)
	}
	defer resp.Close()

	// Read output
	output, _ := io.ReadAll(resp.Reader)

	// Get exit code
	inspectResp, err := c.docker.ContainerExecInspect(ctx, execID.ID)
	if err != nil {
		return nil, fmt.Errorf("failed to inspect exec: %w", err)
	}

	return &ExecResult{
		ExitCode: inspectResp.ExitCode,
		Output:   string(output),
	}, nil
}

// ListContainers lists containers with optional filters
func (c *Client) ListContainers(ctx context.Context, all bool, labelFilter map[string]string) ([]types.Container, error) {
	filterArgs := filters.NewArgs()

	for k, v := range labelFilter {
		filterArgs.Add("label", fmt.Sprintf("%s=%s", k, v))
	}

	return c.docker.ContainerList(ctx, container.ListOptions{
		All:     all,
		Filters: filterArgs,
	})
}

// ListManagedContainers lists containers managed by EasyDeploy
func (c *Client) ListManagedContainers(ctx context.Context) ([]types.Container, error) {
	return c.ListContainers(ctx, true, map[string]string{
		"easydeploy.managed": "true",
	})
}

// GetServerStats returns server-level statistics
func (c *Client) GetServerStats(ctx context.Context) *ServerStats {
	stats := &ServerStats{}

	// Get container counts
	containers, _ := c.docker.ContainerList(ctx, container.ListOptions{All: true})
	stats.ContainerCount = len(containers)

	runningContainers, _ := c.docker.ContainerList(ctx, container.ListOptions{})
	stats.RunningCount = len(runningContainers)

	// Get system info
	info, err := c.docker.Info(ctx)
	if err == nil {
		stats.CPUCores = info.NCPU
		stats.MemoryTotal = info.MemTotal
	}

	// Get disk usage
	diskUsage, err := c.docker.DiskUsage(ctx, types.DiskUsageOptions{})
	if err == nil {
		for _, img := range diskUsage.Images {
			stats.DiskUsage += img.Size
		}
		for _, cont := range diskUsage.Containers {
			stats.DiskUsage += cont.SizeRw
		}
	}

	// Get host memory and CPU from /proc
	stats.MemoryUsage, stats.MemoryPercent = getHostMemoryUsage()
	stats.CPUUsage = getHostCPUUsage()

	// Get disk stats from filesystem
	stats.DiskTotal, stats.DiskUsage, stats.DiskPercent = getHostDiskUsage("/")

	return stats
}

// getHostMemoryUsage reads memory usage from /proc/meminfo
func getHostMemoryUsage() (int64, float64) {
	data, err := os.ReadFile("/proc/meminfo")
	if err != nil {
		return 0, 0
	}

	var total, available int64
	lines := strings.Split(string(data), "\n")
	for _, line := range lines {
		fields := strings.Fields(line)
		if len(fields) < 2 {
			continue
		}
		value, _ := strconv.ParseInt(fields[1], 10, 64)
		value *= 1024 // Convert KB to bytes

		switch fields[0] {
		case "MemTotal:":
			total = value
		case "MemAvailable:":
			available = value
		}
	}

	used := total - available
	percent := 0.0
	if total > 0 {
		percent = float64(used) / float64(total) * 100.0
	}

	return used, percent
}

// getHostCPUUsage reads CPU usage from /proc/stat
func getHostCPUUsage() float64 {
	data, err := os.ReadFile("/proc/stat")
	if err != nil {
		return 0
	}

	lines := strings.Split(string(data), "\n")
	for _, line := range lines {
		if strings.HasPrefix(line, "cpu ") {
			fields := strings.Fields(line)
			if len(fields) < 5 {
				return 0
			}

			user, _ := strconv.ParseFloat(fields[1], 64)
			nice, _ := strconv.ParseFloat(fields[2], 64)
			system, _ := strconv.ParseFloat(fields[3], 64)
			idle, _ := strconv.ParseFloat(fields[4], 64)

			total := user + nice + system + idle
			if total > 0 {
				return (total - idle) / total * 100.0
			}
		}
	}

	return 0
}

// getHostDiskUsage gets disk usage for a path
func getHostDiskUsage(path string) (total, used int64, percent float64) {
	var stat syscall.Statfs_t
	if err := syscall.Statfs(path, &stat); err != nil {
		return 0, 0, 0
	}

	total = int64(stat.Blocks) * int64(stat.Bsize)
	free := int64(stat.Bfree) * int64(stat.Bsize)
	used = total - free

	if total > 0 {
		percent = float64(used) / float64(total) * 100.0
	}

	return total, used, percent
}

// InspectContainer returns detailed container information
func (c *Client) InspectContainer(ctx context.Context, containerID string) (*types.ContainerJSON, error) {
	inspect, err := c.docker.ContainerInspect(ctx, containerID)
	if err != nil {
		return nil, err
	}
	return &inspect, nil
}

// WaitContainer waits for a container to stop
func (c *Client) WaitContainer(ctx context.Context, containerID string) (<-chan container.WaitResponse, <-chan error) {
	return c.docker.ContainerWait(ctx, containerID, container.WaitConditionNotRunning)
}

// KillContainer sends a signal to a container
func (c *Client) KillContainer(ctx context.Context, containerID, signal string) error {
	return c.docker.ContainerKill(ctx, containerID, signal)
}

// PruneContainers removes stopped containers
func (c *Client) PruneContainers(ctx context.Context) (uint64, error) {
	report, err := c.docker.ContainersPrune(ctx, filters.Args{})
	if err != nil {
		return 0, err
	}
	return report.SpaceReclaimed, nil
}

// PruneImages removes unused images
func (c *Client) PruneImages(ctx context.Context, dangling bool) (uint64, error) {
	filterArgs := filters.NewArgs()
	if dangling {
		filterArgs.Add("dangling", "true")
	}

	report, err := c.docker.ImagesPrune(ctx, filterArgs)
	if err != nil {
		return 0, err
	}
	return report.SpaceReclaimed, nil
}

func ptrInt64(v int64) *int64 {
	return &v
}
