package scheduler

import (
	"context"
	"fmt"
	"time"

	"github.com/easyti/easydeploy/orchestrator/pkg/proto"
	"google.golang.org/grpc"
	"google.golang.org/grpc/credentials/insecure"
)

// DeployRequest represents a request to create a container via agent
type DeployRequest struct {
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

// CreateContainerResult holds the result of a container creation
type CreateContainerResult struct {
	ContainerID string
	HostPort    int32
}

// CreateContainer creates a new container on the agent
func (c *AgentClient) CreateContainer(ctx context.Context, req *DeployRequest) (*CreateContainerResult, error) {
	response, err := c.client.CreateContainer(ctx, &proto.CreateContainerRequest{
		Image:       req.ImageName,
		Name:        req.Name,
		Port:        int32(req.Port),
		Environment: req.EnvVars,
		CpuLimit:    req.CPULimit,
		MemoryLimit: req.MemLimit,
		Labels:      req.Labels,
	})
	if err != nil {
		return nil, fmt.Errorf("failed to create container: %w", err)
	}

	if !response.Success {
		return nil, fmt.Errorf("agent failed to create container: %s", response.Error)
	}

	return &CreateContainerResult{
		ContainerID: response.ContainerId,
		HostPort:    response.HostPort,
	}, nil
}

// StartContainer starts a container
func (c *AgentClient) StartContainer(ctx context.Context, containerID string) error {
	_, err := c.client.StartContainer(ctx, &proto.StartContainerRequest{
		ContainerId: containerID,
	})
	if err != nil {
		return fmt.Errorf("failed to start container: %w", err)
	}

	return nil
}

// StopContainer stops a container
func (c *AgentClient) StopContainer(ctx context.Context, containerID string) error {
	_, err := c.client.StopContainer(ctx, &proto.StopContainerRequest{
		ContainerId: containerID,
	})
	if err != nil {
		return fmt.Errorf("failed to stop container: %w", err)
	}

	return nil
}

// RestartContainer restarts a container
func (c *AgentClient) RestartContainer(ctx context.Context, containerID string) error {
	_, err := c.client.RestartContainer(ctx, &proto.RestartContainerRequest{
		ContainerId: containerID,
	})
	if err != nil {
		return fmt.Errorf("failed to restart container: %w", err)
	}

	return nil
}

// RemoveContainer removes a container
func (c *AgentClient) RemoveContainer(ctx context.Context, containerID string) error {
	_, err := c.client.RemoveContainer(ctx, &proto.RemoveContainerRequest{
		ContainerId: containerID,
		Force:       true,
	})
	if err != nil {
		return fmt.Errorf("failed to remove container: %w", err)
	}

	return nil
}

// GetLogs retrieves container logs via streaming
func (c *AgentClient) GetLogs(ctx context.Context, containerID string, lines int) (string, error) {
	stream, err := c.client.GetContainerLogs(ctx, &proto.GetLogsRequest{
		ContainerId: containerID,
		Lines:       int32(lines),
	})
	if err != nil {
		return "", fmt.Errorf("failed to get logs: %w", err)
	}

	var logs string
	for {
		line, err := stream.Recv()
		if err != nil {
			break
		}
		logs += line.Content + "\n"
	}

	return logs, nil
}

// HealthCheck checks if a container is healthy
func (c *AgentClient) HealthCheck(ctx context.Context, containerID string) (bool, error) {
	response, err := c.client.CheckHealth(ctx, &proto.HealthCheckRequest{
		ContainerId: containerID,
	})
	if err != nil {
		return false, fmt.Errorf("health check failed: %w", err)
	}

	return response.IsHealthy, nil
}

// PullImage pulls an image on the agent
func (c *AgentClient) PullImage(ctx context.Context, imageName string) error {
	response, err := c.client.PullImage(ctx, &proto.PullImageRequest{
		Image: imageName,
	})
	if err != nil {
		return fmt.Errorf("failed to pull image: %w", err)
	}

	if !response.Success {
		return fmt.Errorf("image pull failed")
	}

	return nil
}

// GetServerStats retrieves server-level stats from the agent
func (c *AgentClient) GetServerStats(ctx context.Context) (*proto.ServerStatsResponse, error) {
	response, err := c.client.GetServerStats(ctx, &proto.ServerStatsRequest{})
	if err != nil {
		return nil, fmt.Errorf("failed to get server stats: %w", err)
	}

	return response, nil
}

// GetContainerStats retrieves container statistics from the agent
func (c *AgentClient) GetContainerStats(ctx context.Context, containerID string) (*proto.ContainerStatsResponse, error) {
	ctx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()

	response, err := c.client.GetContainerStats(ctx, &proto.ContainerStatsRequest{
		ContainerId: containerID,
	})
	if err != nil {
		return nil, fmt.Errorf("failed to get container stats: %w", err)
	}

	return response, nil
}
