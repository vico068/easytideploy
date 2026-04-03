package scheduler

import (
	"context"
	"fmt"
	"time"

	"github.com/easyti/easydeploy/orchestrator/pkg/proto"
	"google.golang.org/grpc"
	"google.golang.org/grpc/credentials/insecure"
)

// CreateContainerRequest represents a request to create a container
type CreateContainerRequest struct {
	ImageName string
	Name      string
	EnvVars   map[string]string
	Port      int
	CPULimit  int64
	MemLimit  int64
	Labels    map[string]string
}

// AgentClient wraps the gRPC client for agent communication
type AgentClient struct {
	conn   *grpc.ClientConn
	client proto.AgentServiceClient
}

// NewAgentClient creates a new agent client
func NewAgentClient(address string) (*AgentClient, error) {
	// Create gRPC connection with timeout
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	conn, err := grpc.DialContext(ctx, address,
		grpc.WithTransportCredentials(insecure.NewCredentials()),
		grpc.WithBlock(),
	)
	if err != nil {
		return nil, fmt.Errorf("failed to connect to agent: %w", err)
	}

	return &AgentClient{
		conn:   conn,
		client: proto.NewAgentServiceClient(conn),
	}, nil
}

// Close closes the gRPC connection
func (c *AgentClient) Close() error {
	if c.conn != nil {
		return c.conn.Close()
	}
	return nil
}

// CreateContainer creates a new container on the agent
func (c *AgentClient) CreateContainer(ctx context.Context, req *CreateContainerRequest) (string, error) {
	// Convert port to port mapping
	portMappings := make(map[string]int32)
	if req.Port > 0 {
		portMappings[fmt.Sprintf("%d/tcp", req.Port)] = 0 // 0 = auto-assign host port
	}

	response, err := c.client.CreateContainer(ctx, &proto.CreateContainerRequest{
		ImageName:    req.ImageName,
		Name:         req.Name,
		EnvVars:      req.EnvVars,
		PortMappings: portMappings,
		CpuLimit:     req.CPULimit,
		MemLimit:     req.MemLimit,
		Labels:       req.Labels,
	})
	if err != nil {
		return "", fmt.Errorf("failed to create container: %w", err)
	}

	if !response.Success {
		return "", fmt.Errorf("container creation failed: %s", response.Error)
	}

	return response.ContainerId, nil
}

// StartContainer starts a container
func (c *AgentClient) StartContainer(ctx context.Context, containerID string) error {
	response, err := c.client.StartContainer(ctx, &proto.ContainerRequest{
		ContainerId: containerID,
	})
	if err != nil {
		return fmt.Errorf("failed to start container: %w", err)
	}

	if !response.Success {
		return fmt.Errorf("container start failed: %s", response.Error)
	}

	return nil
}

// StopContainer stops a container
func (c *AgentClient) StopContainer(ctx context.Context, containerID string) error {
	response, err := c.client.StopContainer(ctx, &proto.ContainerRequest{
		ContainerId: containerID,
	})
	if err != nil {
		return fmt.Errorf("failed to stop container: %w", err)
	}

	if !response.Success {
		return fmt.Errorf("container stop failed: %s", response.Error)
	}

	return nil
}

// RestartContainer restarts a container
func (c *AgentClient) RestartContainer(ctx context.Context, containerID string) error {
	response, err := c.client.RestartContainer(ctx, &proto.ContainerRequest{
		ContainerId: containerID,
	})
	if err != nil {
		return fmt.Errorf("failed to restart container: %w", err)
	}

	if !response.Success {
		return fmt.Errorf("container restart failed: %s", response.Error)
	}

	return nil
}

// RemoveContainer removes a container
func (c *AgentClient) RemoveContainer(ctx context.Context, containerID string) error {
	response, err := c.client.RemoveContainer(ctx, &proto.ContainerRequest{
		ContainerId: containerID,
	})
	if err != nil {
		return fmt.Errorf("failed to remove container: %w", err)
	}

	if !response.Success {
		return fmt.Errorf("container removal failed: %s", response.Error)
	}

	return nil
}

// GetLogs retrieves container logs
func (c *AgentClient) GetLogs(ctx context.Context, containerID string, lines int) (string, error) {
	response, err := c.client.GetContainerLogs(ctx, &proto.GetLogsRequest{
		ContainerId: containerID,
		Tail:        int32(lines),
		Timestamps:  true,
	})
	if err != nil {
		return "", fmt.Errorf("failed to get logs: %w", err)
	}

	return response.Logs, nil
}

// GetContainerStats retrieves container statistics
func (c *AgentClient) GetContainerStats(ctx context.Context, containerID string) (*proto.ContainerStats, error) {
	response, err := c.client.GetContainerStats(ctx, &proto.ContainerRequest{
		ContainerId: containerID,
	})
	if err != nil {
		return nil, fmt.Errorf("failed to get stats: %w", err)
	}

	return response, nil
}

// HealthCheck checks if a container is healthy
func (c *AgentClient) HealthCheck(ctx context.Context, containerID string) (bool, error) {
	response, err := c.client.HealthCheck(ctx, &proto.HealthCheckRequest{
		ContainerId: containerID,
	})
	if err != nil {
		return false, fmt.Errorf("health check failed: %w", err)
	}

	return response.Healthy, nil
}

// ListContainers lists all containers on the agent
func (c *AgentClient) ListContainers(ctx context.Context, onlyRunning bool) ([]*proto.ContainerInfo, error) {
	response, err := c.client.ListContainers(ctx, &proto.ListContainersRequest{
		All: !onlyRunning,
	})
	if err != nil {
		return nil, fmt.Errorf("failed to list containers: %w", err)
	}

	return response.Containers, nil
}

// PullImage pulls an image on the agent
func (c *AgentClient) PullImage(ctx context.Context, imageName string) error {
	response, err := c.client.PullImage(ctx, &proto.PullImageRequest{
		ImageName: imageName,
	})
	if err != nil {
		return fmt.Errorf("failed to pull image: %w", err)
	}

	if !response.Success {
		return fmt.Errorf("image pull failed: %s", response.Error)
	}

	return nil
}

// ExecCommand executes a command in a container
func (c *AgentClient) ExecCommand(ctx context.Context, containerID string, command []string) (string, int32, error) {
	response, err := c.client.ExecCommand(ctx, &proto.ExecRequest{
		ContainerId: containerID,
		Command:     command,
	})
	if err != nil {
		return "", -1, fmt.Errorf("failed to exec command: %w", err)
	}

	return response.Output, response.ExitCode, nil
}

// GetServerMetrics retrieves server-level metrics from the agent
func (c *AgentClient) GetServerMetrics(ctx context.Context) (*proto.ServerMetrics, error) {
	response, err := c.client.GetServerMetrics(ctx, &proto.Empty{})
	if err != nil {
		return nil, fmt.Errorf("failed to get server metrics: %w", err)
	}

	return response, nil
}
