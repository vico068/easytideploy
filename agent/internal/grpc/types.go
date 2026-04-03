package grpc

import (
	"google.golang.org/grpc"
)

// Proto message types - these mirror the proto definitions
// In production, these would be generated from agent.proto

// Empty is an empty message
type Empty struct{}

// CreateContainerRequest contains container creation parameters
type CreateContainerRequest struct {
	ImageName           string
	Name                string
	EnvVars             map[string]string
	PortMappings        map[string]int32
	Port                int32
	CpuLimit            int64 // millicores
	MemLimit            int64 // bytes
	Labels              map[string]string
	HealthCheckPath     string
	HealthCheckInterval int32
	HealthCheckTimeout  int32
	HealthCheckRetries  int32
	Command             []string
	Volumes             map[string]string
	NetworkMode         string
}

// CreateContainerResponse contains the result of container creation
type CreateContainerResponse struct {
	Success     bool
	ContainerId string
	InternalIp  string
	HostPort    int32
	Error       string
}

// ContainerRequest is a generic container request
type ContainerRequest struct {
	ContainerId string
	Timeout     int32
}

// ContainerResponse is a generic container response
type ContainerResponse struct {
	Success bool
	Error   string
}

// GetLogsRequest contains log retrieval parameters
type GetLogsRequest struct {
	ContainerId string
	Tail        int32
	Follow      bool
	Timestamps  bool
	Since       string
	Until       string
}

// GetLogsResponse contains log output
type GetLogsResponse struct {
	Logs string
}

// LogLine represents a single log line
type LogLine struct {
	Content   string
	Timestamp string
	Stream    string // stdout or stderr
}

// ContainerStats contains container statistics
type ContainerStats struct {
	ContainerId    string
	CpuPercent     float64
	MemoryUsage    int64
	MemoryLimit    int64
	MemoryPercent  float64
	NetworkRxBytes int64
	NetworkTxBytes int64
	BlockRead      int64
	BlockWrite     int64
	Pids           int64
	Status         string
	Health         string
}

// HealthCheckRequest contains health check parameters
type HealthCheckRequest struct {
	ContainerId string
	HttpPath    string
	HttpPort    int32
}

// HealthCheckResponse contains health check result
type HealthCheckResponse struct {
	Healthy       bool
	Status        string
	HealthStatus  string
	Message       string
	CpuPercent    float64
	MemoryPercent float64
	RestartCount  int32
}

// ListContainersRequest contains list parameters
type ListContainersRequest struct {
	All         bool
	ManagedOnly bool
	Labels      map[string]string
}

// ListContainersResponse contains container list
type ListContainersResponse struct {
	Containers []*ContainerInfo
}

// ContainerInfo contains container information
type ContainerInfo struct {
	Id      string
	Name    string
	Image   string
	Status  string
	State   string
	Created int64
	Labels  map[string]string
	Ports   []*PortMapping
}

// PortMapping represents a port mapping
type PortMapping struct {
	ContainerPort int32
	HostPort      int32
	Protocol      string
}

// PullImageRequest contains image pull parameters
type PullImageRequest struct {
	ImageName    string
	RegistryAuth string
}

// PullImageResponse contains image pull result
type PullImageResponse struct {
	Success bool
	Digest  string
	Error   string
}

// ExecRequest contains command execution parameters
type ExecRequest struct {
	ContainerId string
	Command     []string
	Env         []string
	WorkDir     string
	User        string
	Tty         bool
}

// ExecResponse contains command execution result
type ExecResponse struct {
	Output   string
	Stderr   string
	ExitCode int32
}

// ServerMetrics contains server-level metrics
type ServerMetrics struct {
	CpuCores       int32
	CpuPercent     float64
	MemoryTotal    int64
	MemoryUsed     int64
	MemoryPercent  float64
	DiskTotal      int64
	DiskUsed       int64
	DiskPercent    float64
	NetworkIn      int64
	NetworkOut     int64
	ContainerCount int32
	RunningCount   int32
}

// PingResponse contains ping result
type PingResponse struct {
	Alive     bool
	Timestamp int64
	Version   string
	ServerId  string
}

// AgentServiceServer is the server API for AgentService
type AgentServiceServer interface {
	CreateContainer(ctx interface{}, req *CreateContainerRequest) (*CreateContainerResponse, error)
	StartContainer(ctx interface{}, req *ContainerRequest) (*ContainerResponse, error)
	StopContainer(ctx interface{}, req *ContainerRequest) (*ContainerResponse, error)
	RestartContainer(ctx interface{}, req *ContainerRequest) (*ContainerResponse, error)
	RemoveContainer(ctx interface{}, req *ContainerRequest) (*ContainerResponse, error)
	GetContainerLogs(ctx interface{}, req *GetLogsRequest) (*GetLogsResponse, error)
	StreamContainerLogs(req *GetLogsRequest, stream AgentService_StreamContainerLogsServer) error
	GetContainerStats(ctx interface{}, req *ContainerRequest) (*ContainerStats, error)
	HealthCheck(ctx interface{}, req *HealthCheckRequest) (*HealthCheckResponse, error)
	ListContainers(ctx interface{}, req *ListContainersRequest) (*ListContainersResponse, error)
	PullImage(ctx interface{}, req *PullImageRequest) (*PullImageResponse, error)
	ExecCommand(ctx interface{}, req *ExecRequest) (*ExecResponse, error)
	GetServerMetrics(ctx interface{}, req *Empty) (*ServerMetrics, error)
	Ping(ctx interface{}, req *Empty) (*PingResponse, error)
}

// AgentService_StreamContainerLogsServer is the server streaming interface for logs
type AgentService_StreamContainerLogsServer interface {
	Send(*LogLine) error
	grpc.ServerStream
}

// UnimplementedAgentServiceServer can be embedded for forward compatibility
type UnimplementedAgentServiceServer struct{}

func (UnimplementedAgentServiceServer) CreateContainer(interface{}, *CreateContainerRequest) (*CreateContainerResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) StartContainer(interface{}, *ContainerRequest) (*ContainerResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) StopContainer(interface{}, *ContainerRequest) (*ContainerResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) RestartContainer(interface{}, *ContainerRequest) (*ContainerResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) RemoveContainer(interface{}, *ContainerRequest) (*ContainerResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) GetContainerLogs(interface{}, *GetLogsRequest) (*GetLogsResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) StreamContainerLogs(*GetLogsRequest, AgentService_StreamContainerLogsServer) error {
	return nil
}
func (UnimplementedAgentServiceServer) GetContainerStats(interface{}, *ContainerRequest) (*ContainerStats, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) HealthCheck(interface{}, *HealthCheckRequest) (*HealthCheckResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) ListContainers(interface{}, *ListContainersRequest) (*ListContainersResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) PullImage(interface{}, *PullImageRequest) (*PullImageResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) ExecCommand(interface{}, *ExecRequest) (*ExecResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) GetServerMetrics(interface{}, *Empty) (*ServerMetrics, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) Ping(interface{}, *Empty) (*PingResponse, error) {
	return nil, nil
}

// RegisterAgentServiceServer registers the server
func RegisterAgentServiceServer(s *grpc.Server, srv AgentServiceServer) {
	// In production, this would use generated registration code
	// For now, we use reflection-based registration
}
