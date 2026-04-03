package grpc

import (
	"context"
	"fmt"
	"io"
	"net"
	"time"

	"github.com/easyti/easydeploy/agent/internal/config"
	"github.com/easyti/easydeploy/agent/internal/docker"
	"github.com/rs/zerolog/log"
	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/reflection"
	"google.golang.org/grpc/status"
)

// Server wraps the gRPC server
type Server struct {
	docker     *docker.Client
	config     *config.Config
	grpcServer *grpc.Server
}

// NewServer creates a new gRPC server
func NewServer(d *docker.Client, cfg *config.Config) *Server {
	return &Server{
		docker: d,
		config: cfg,
	}
}

// Start starts the gRPC server
func (s *Server) Start() error {
	lis, err := net.Listen("tcp", s.config.GRPCAddress)
	if err != nil {
		return fmt.Errorf("failed to listen: %w", err)
	}

	// Create gRPC server with options
	opts := []grpc.ServerOption{
		grpc.MaxRecvMsgSize(100 * 1024 * 1024), // 100MB
		grpc.MaxSendMsgSize(100 * 1024 * 1024),
	}

	s.grpcServer = grpc.NewServer(opts...)

	// Register agent service
	RegisterAgentServiceServer(s.grpcServer, &agentService{
		docker: s.docker,
		config: s.config,
	})

	// Enable reflection for debugging
	reflection.Register(s.grpcServer)

	log.Info().Str("address", s.config.GRPCAddress).Msg("Starting gRPC server")

	return s.grpcServer.Serve(lis)
}

// Stop gracefully stops the gRPC server
func (s *Server) Stop() {
	if s.grpcServer != nil {
		s.grpcServer.GracefulStop()
	}
}

// agentService implements the AgentServiceServer interface
type agentService struct {
	UnimplementedAgentServiceServer
	docker *docker.Client
	config *config.Config
}

// CreateContainer creates a new container
func (s *agentService) CreateContainer(ctx context.Context, req *CreateContainerRequest) (*CreateContainerResponse, error) {
	log.Info().
		Str("image", req.ImageName).
		Str("name", req.Name).
		Msg("Creating container")

	cfg := &docker.ContainerConfig{
		Image:       req.ImageName,
		Name:        req.Name,
		Port:        int(req.Port),
		Environment: req.EnvVars,
		CPULimit:    req.CpuLimit,
		MemoryLimit: req.MemLimit,
		Labels:      req.Labels,
	}

	if req.HealthCheckPath != "" {
		cfg.HealthCheck = &docker.HealthCheck{
			Path:     req.HealthCheckPath,
			Interval: int(req.HealthCheckInterval),
			Timeout:  int(req.HealthCheckTimeout),
			Retries:  int(req.HealthCheckRetries),
		}
	}

	result, err := s.docker.Create(ctx, cfg)
	if err != nil {
		log.Error().Err(err).Msg("Failed to create container")
		return &CreateContainerResponse{
			Success: false,
			Error:   err.Error(),
		}, nil
	}

	log.Info().
		Str("container_id", result.ID).
		Str("ip", result.IPAddress).
		Int("host_port", result.HostPort).
		Msg("Container created successfully")

	return &CreateContainerResponse{
		Success:     true,
		ContainerId: result.ID,
		InternalIp:  result.IPAddress,
		HostPort:    int32(result.HostPort),
	}, nil
}

// StartContainer starts a container
func (s *agentService) StartContainer(ctx context.Context, req *ContainerRequest) (*ContainerResponse, error) {
	log.Info().Str("container_id", req.ContainerId).Msg("Starting container")

	if err := s.docker.Start(ctx, req.ContainerId); err != nil {
		log.Error().Err(err).Msg("Failed to start container")
		return &ContainerResponse{
			Success: false,
			Error:   err.Error(),
		}, nil
	}

	return &ContainerResponse{Success: true}, nil
}

// StopContainer stops a container
func (s *agentService) StopContainer(ctx context.Context, req *ContainerRequest) (*ContainerResponse, error) {
	log.Info().Str("container_id", req.ContainerId).Msg("Stopping container")

	timeout := 30
	if req.Timeout > 0 {
		timeout = int(req.Timeout)
	}

	if err := s.docker.Stop(ctx, req.ContainerId, timeout); err != nil {
		log.Error().Err(err).Msg("Failed to stop container")
		return &ContainerResponse{
			Success: false,
			Error:   err.Error(),
		}, nil
	}

	return &ContainerResponse{Success: true}, nil
}

// RestartContainer restarts a container
func (s *agentService) RestartContainer(ctx context.Context, req *ContainerRequest) (*ContainerResponse, error) {
	log.Info().Str("container_id", req.ContainerId).Msg("Restarting container")

	timeout := 30
	if req.Timeout > 0 {
		timeout = int(req.Timeout)
	}

	if err := s.docker.Restart(ctx, req.ContainerId, timeout); err != nil {
		log.Error().Err(err).Msg("Failed to restart container")
		return &ContainerResponse{
			Success: false,
			Error:   err.Error(),
		}, nil
	}

	return &ContainerResponse{Success: true}, nil
}

// RemoveContainer removes a container
func (s *agentService) RemoveContainer(ctx context.Context, req *ContainerRequest) (*ContainerResponse, error) {
	log.Info().Str("container_id", req.ContainerId).Msg("Removing container")

	force := true
	if err := s.docker.Remove(ctx, req.ContainerId, force); err != nil {
		log.Error().Err(err).Msg("Failed to remove container")
		return &ContainerResponse{
			Success: false,
			Error:   err.Error(),
		}, nil
	}

	return &ContainerResponse{Success: true}, nil
}

// GetContainerLogs returns container logs
func (s *agentService) GetContainerLogs(ctx context.Context, req *GetLogsRequest) (*GetLogsResponse, error) {
	log.Debug().Str("container_id", req.ContainerId).Msg("Getting container logs")

	logs, err := s.docker.LogsString(ctx, req.ContainerId, int(req.Tail))
	if err != nil {
		return nil, status.Errorf(codes.Internal, "failed to get logs: %v", err)
	}

	return &GetLogsResponse{Logs: logs}, nil
}

// StreamContainerLogs streams container logs in real-time
func (s *agentService) StreamContainerLogs(req *GetLogsRequest, stream AgentService_StreamContainerLogsServer) error {
	log.Debug().Str("container_id", req.ContainerId).Msg("Streaming container logs")

	logsReader, err := s.docker.Logs(stream.Context(), req.ContainerId, int(req.Tail), true)
	if err != nil {
		return status.Errorf(codes.Internal, "failed to get logs: %v", err)
	}
	defer logsReader.Close()

	buf := make([]byte, 4096)
	for {
		select {
		case <-stream.Context().Done():
			return nil
		default:
			n, err := logsReader.Read(buf)
			if n > 0 {
				// Skip Docker log header (8 bytes)
				content := buf[:n]
				if len(content) > 8 {
					content = content[8:]
				}

				if err := stream.Send(&LogLine{
					Content:   string(content),
					Timestamp: time.Now().Format(time.RFC3339),
				}); err != nil {
					return err
				}
			}
			if err == io.EOF {
				return nil
			}
			if err != nil {
				return status.Errorf(codes.Internal, "log read error: %v", err)
			}
		}
	}
}

// GetContainerStats returns container statistics
func (s *agentService) GetContainerStats(ctx context.Context, req *ContainerRequest) (*ContainerStats, error) {
	stats, err := s.docker.Stats(ctx, req.ContainerId)
	if err != nil {
		return nil, status.Errorf(codes.Internal, "failed to get stats: %v", err)
	}

	return &ContainerStats{
		ContainerId:    req.ContainerId,
		CpuPercent:     stats.CPUPercent,
		MemoryUsage:    stats.MemoryUsage,
		MemoryLimit:    stats.MemoryLimit,
		MemoryPercent:  stats.MemoryPercent,
		NetworkRxBytes: stats.NetworkRxBytes,
		NetworkTxBytes: stats.NetworkTxBytes,
		BlockRead:      stats.BlockRead,
		BlockWrite:     stats.BlockWrite,
		Pids:           stats.PIDs,
		Status:         stats.Status,
		Health:         stats.Health,
	}, nil
}

// HealthCheck checks if a container is healthy
func (s *agentService) HealthCheck(ctx context.Context, req *HealthCheckRequest) (*HealthCheckResponse, error) {
	stats, err := s.docker.Stats(ctx, req.ContainerId)
	if err != nil {
		return &HealthCheckResponse{
			Healthy: false,
			Status:  "error",
			Message: err.Error(),
		}, nil
	}

	healthy := stats.Status == "running" && (stats.Health == "healthy" || stats.Health == "unknown")

	return &HealthCheckResponse{
		Healthy:       healthy,
		Status:        stats.Status,
		HealthStatus:  stats.Health,
		CpuPercent:    stats.CPUPercent,
		MemoryPercent: stats.MemoryPercent,
		RestartCount:  int32(stats.RestartCount),
	}, nil
}

// ListContainers lists all containers
func (s *agentService) ListContainers(ctx context.Context, req *ListContainersRequest) (*ListContainersResponse, error) {
	var containers []types.Container
	var err error

	if req.ManagedOnly {
		containers, err = s.docker.ListManagedContainers(ctx)
	} else {
		containers, err = s.docker.ListContainers(ctx, req.All, nil)
	}

	if err != nil {
		return nil, status.Errorf(codes.Internal, "failed to list containers: %v", err)
	}

	result := &ListContainersResponse{
		Containers: make([]*ContainerInfo, 0, len(containers)),
	}

	for _, c := range containers {
		info := &ContainerInfo{
			Id:      c.ID,
			Name:    "",
			Image:   c.Image,
			Status:  c.Status,
			State:   c.State,
			Created: c.Created,
			Labels:  c.Labels,
		}

		if len(c.Names) > 0 {
			info.Name = c.Names[0]
		}

		result.Containers = append(result.Containers, info)
	}

	return result, nil
}

// PullImage pulls an image from registry
func (s *agentService) PullImage(ctx context.Context, req *PullImageRequest) (*PullImageResponse, error) {
	log.Info().Str("image", req.ImageName).Msg("Pulling image")

	if err := s.docker.Pull(ctx, req.ImageName, req.RegistryAuth); err != nil {
		log.Error().Err(err).Msg("Failed to pull image")
		return &PullImageResponse{
			Success: false,
			Error:   err.Error(),
		}, nil
	}

	return &PullImageResponse{Success: true}, nil
}

// ExecCommand executes a command in a container
func (s *agentService) ExecCommand(ctx context.Context, req *ExecRequest) (*ExecResponse, error) {
	log.Debug().
		Str("container_id", req.ContainerId).
		Strs("command", req.Command).
		Msg("Executing command")

	result, err := s.docker.Exec(ctx, req.ContainerId, req.Command)
	if err != nil {
		return nil, status.Errorf(codes.Internal, "failed to execute command: %v", err)
	}

	return &ExecResponse{
		Output:   result.Output,
		ExitCode: int32(result.ExitCode),
	}, nil
}

// GetServerMetrics returns server-level metrics
func (s *agentService) GetServerMetrics(ctx context.Context, req *Empty) (*ServerMetrics, error) {
	stats := s.docker.GetServerStats(ctx)

	return &ServerMetrics{
		CpuCores:       int32(stats.CPUCores),
		CpuPercent:     stats.CPUUsage,
		MemoryTotal:    stats.MemoryTotal,
		MemoryUsed:     stats.MemoryUsage,
		MemoryPercent:  stats.MemoryPercent,
		DiskTotal:      stats.DiskTotal,
		DiskUsed:       stats.DiskUsage,
		DiskPercent:    stats.DiskPercent,
		ContainerCount: int32(stats.ContainerCount),
		RunningCount:   int32(stats.RunningCount),
	}, nil
}

// Ping checks if the agent is alive
func (s *agentService) Ping(ctx context.Context, req *Empty) (*PingResponse, error) {
	return &PingResponse{
		Alive:     true,
		Timestamp: time.Now().Unix(),
		Version:   s.config.Version,
		ServerId:  s.config.ServerID,
	}, nil
}

// Import types from docker package
type types = struct{}

func init() {
	// Register types - this is a placeholder
	// In production, actual protobuf types would be used
}
