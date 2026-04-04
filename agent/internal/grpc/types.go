package grpc

import (
	"context"

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
	Healthy       bool    `json:"IsHealthy"` // marshaled as "IsHealthy" to match orchestrator's field name
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
	CreateContainer(ctx context.Context, req *CreateContainerRequest) (*CreateContainerResponse, error)
	StartContainer(ctx context.Context, req *ContainerRequest) (*ContainerResponse, error)
	StopContainer(ctx context.Context, req *ContainerRequest) (*ContainerResponse, error)
	RestartContainer(ctx context.Context, req *ContainerRequest) (*ContainerResponse, error)
	RemoveContainer(ctx context.Context, req *ContainerRequest) (*ContainerResponse, error)
	GetContainerLogs(ctx context.Context, req *GetLogsRequest) (*GetLogsResponse, error)
	StreamContainerLogs(req *GetLogsRequest, stream AgentService_StreamContainerLogsServer) error
	GetContainerStats(ctx context.Context, req *ContainerRequest) (*ContainerStats, error)
	HealthCheck(ctx context.Context, req *HealthCheckRequest) (*HealthCheckResponse, error)
	ListContainers(ctx context.Context, req *ListContainersRequest) (*ListContainersResponse, error)
	PullImage(ctx context.Context, req *PullImageRequest) (*PullImageResponse, error)
	ExecCommand(ctx context.Context, req *ExecRequest) (*ExecResponse, error)
	GetServerMetrics(ctx context.Context, req *Empty) (*ServerMetrics, error)
	Ping(ctx context.Context, req *Empty) (*PingResponse, error)
}

// AgentService_StreamContainerLogsServer is the server streaming interface for logs
type AgentService_StreamContainerLogsServer interface {
	Send(*LogLine) error
	grpc.ServerStream
}

// UnimplementedAgentServiceServer can be embedded for forward compatibility
type UnimplementedAgentServiceServer struct{}

func (UnimplementedAgentServiceServer) CreateContainer(context.Context, *CreateContainerRequest) (*CreateContainerResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) StartContainer(context.Context, *ContainerRequest) (*ContainerResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) StopContainer(context.Context, *ContainerRequest) (*ContainerResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) RestartContainer(context.Context, *ContainerRequest) (*ContainerResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) RemoveContainer(context.Context, *ContainerRequest) (*ContainerResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) GetContainerLogs(context.Context, *GetLogsRequest) (*GetLogsResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) StreamContainerLogs(*GetLogsRequest, AgentService_StreamContainerLogsServer) error {
	return nil
}
func (UnimplementedAgentServiceServer) GetContainerStats(context.Context, *ContainerRequest) (*ContainerStats, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) HealthCheck(context.Context, *HealthCheckRequest) (*HealthCheckResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) ListContainers(context.Context, *ListContainersRequest) (*ListContainersResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) PullImage(context.Context, *PullImageRequest) (*PullImageResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) ExecCommand(context.Context, *ExecRequest) (*ExecResponse, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) GetServerMetrics(context.Context, *Empty) (*ServerMetrics, error) {
	return nil, nil
}
func (UnimplementedAgentServiceServer) Ping(context.Context, *Empty) (*PingResponse, error) {
	return nil, nil
}

// RegisterAgentServiceServer registers the server with the gRPC server.
func RegisterAgentServiceServer(s *grpc.Server, srv AgentServiceServer) {
	s.RegisterService(&AgentService_ServiceDesc, srv)
}

// AgentService_ServiceDesc is the service descriptor.
// Method names match the orchestrator's proto client calls (/agent.AgentService/...).
var AgentService_ServiceDesc = grpc.ServiceDesc{
	ServiceName: "agent.AgentService",
	HandlerType: (*AgentServiceServer)(nil),
	Methods: []grpc.MethodDesc{
		{MethodName: "CreateContainer", Handler: _AgentService_CreateContainer_Handler},
		{MethodName: "RemoveContainer", Handler: _AgentService_RemoveContainer_Handler},
		{MethodName: "StartContainer", Handler: _AgentService_StartContainer_Handler},
		{MethodName: "StopContainer", Handler: _AgentService_StopContainer_Handler},
		{MethodName: "RestartContainer", Handler: _AgentService_RestartContainer_Handler},
		// Orchestrator calls "CheckHealth" but agent interface uses "HealthCheck"
		{MethodName: "CheckHealth", Handler: _AgentService_CheckHealth_Handler},
		// Orchestrator calls "GetServerStats" but agent interface uses "GetServerMetrics"
		{MethodName: "GetServerStats", Handler: _AgentService_GetServerStats_Handler},
		{MethodName: "PullImage", Handler: _AgentService_PullImage_Handler},
	},
	Streams: []grpc.StreamDesc{
		{
			StreamName:    "GetContainerLogs",
			Handler:       _AgentService_GetContainerLogs_Handler,
			ServerStreams: true,
		},
	},
	Metadata: "agent.proto",
}

func _AgentService_CreateContainer_Handler(srv interface{}, ctx context.Context, dec func(interface{}) error, interceptor grpc.UnaryServerInterceptor) (interface{}, error) {
	in := new(CreateContainerRequest)
	if err := dec(in); err != nil {
		return nil, err
	}
	if interceptor == nil {
		return srv.(AgentServiceServer).CreateContainer(ctx, in)
	}
	info := &grpc.UnaryServerInfo{Server: srv, FullMethod: "/agent.AgentService/CreateContainer"}
	handler := func(ctx context.Context, req interface{}) (interface{}, error) {
		return srv.(AgentServiceServer).CreateContainer(ctx, req.(*CreateContainerRequest))
	}
	return interceptor(ctx, in, info, handler)
}

func _AgentService_RemoveContainer_Handler(srv interface{}, ctx context.Context, dec func(interface{}) error, interceptor grpc.UnaryServerInterceptor) (interface{}, error) {
	in := new(ContainerRequest)
	if err := dec(in); err != nil {
		return nil, err
	}
	if interceptor == nil {
		return srv.(AgentServiceServer).RemoveContainer(ctx, in)
	}
	info := &grpc.UnaryServerInfo{Server: srv, FullMethod: "/agent.AgentService/RemoveContainer"}
	handler := func(ctx context.Context, req interface{}) (interface{}, error) {
		return srv.(AgentServiceServer).RemoveContainer(ctx, req.(*ContainerRequest))
	}
	return interceptor(ctx, in, info, handler)
}

func _AgentService_StartContainer_Handler(srv interface{}, ctx context.Context, dec func(interface{}) error, interceptor grpc.UnaryServerInterceptor) (interface{}, error) {
	in := new(ContainerRequest)
	if err := dec(in); err != nil {
		return nil, err
	}
	if interceptor == nil {
		return srv.(AgentServiceServer).StartContainer(ctx, in)
	}
	info := &grpc.UnaryServerInfo{Server: srv, FullMethod: "/agent.AgentService/StartContainer"}
	handler := func(ctx context.Context, req interface{}) (interface{}, error) {
		return srv.(AgentServiceServer).StartContainer(ctx, req.(*ContainerRequest))
	}
	return interceptor(ctx, in, info, handler)
}

func _AgentService_StopContainer_Handler(srv interface{}, ctx context.Context, dec func(interface{}) error, interceptor grpc.UnaryServerInterceptor) (interface{}, error) {
	in := new(ContainerRequest)
	if err := dec(in); err != nil {
		return nil, err
	}
	if interceptor == nil {
		return srv.(AgentServiceServer).StopContainer(ctx, in)
	}
	info := &grpc.UnaryServerInfo{Server: srv, FullMethod: "/agent.AgentService/StopContainer"}
	handler := func(ctx context.Context, req interface{}) (interface{}, error) {
		return srv.(AgentServiceServer).StopContainer(ctx, req.(*ContainerRequest))
	}
	return interceptor(ctx, in, info, handler)
}

func _AgentService_RestartContainer_Handler(srv interface{}, ctx context.Context, dec func(interface{}) error, interceptor grpc.UnaryServerInterceptor) (interface{}, error) {
	in := new(ContainerRequest)
	if err := dec(in); err != nil {
		return nil, err
	}
	if interceptor == nil {
		return srv.(AgentServiceServer).RestartContainer(ctx, in)
	}
	info := &grpc.UnaryServerInfo{Server: srv, FullMethod: "/agent.AgentService/RestartContainer"}
	handler := func(ctx context.Context, req interface{}) (interface{}, error) {
		return srv.(AgentServiceServer).RestartContainer(ctx, req.(*ContainerRequest))
	}
	return interceptor(ctx, in, info, handler)
}

func _AgentService_CheckHealth_Handler(srv interface{}, ctx context.Context, dec func(interface{}) error, interceptor grpc.UnaryServerInterceptor) (interface{}, error) {
	in := new(HealthCheckRequest)
	if err := dec(in); err != nil {
		return nil, err
	}
	if interceptor == nil {
		return srv.(AgentServiceServer).HealthCheck(ctx, in)
	}
	info := &grpc.UnaryServerInfo{Server: srv, FullMethod: "/agent.AgentService/CheckHealth"}
	handler := func(ctx context.Context, req interface{}) (interface{}, error) {
		return srv.(AgentServiceServer).HealthCheck(ctx, req.(*HealthCheckRequest))
	}
	return interceptor(ctx, in, info, handler)
}

func _AgentService_GetServerStats_Handler(srv interface{}, ctx context.Context, dec func(interface{}) error, interceptor grpc.UnaryServerInterceptor) (interface{}, error) {
	in := new(Empty)
	if err := dec(in); err != nil {
		return nil, err
	}
	if interceptor == nil {
		return srv.(AgentServiceServer).GetServerMetrics(ctx, in)
	}
	info := &grpc.UnaryServerInfo{Server: srv, FullMethod: "/agent.AgentService/GetServerStats"}
	handler := func(ctx context.Context, req interface{}) (interface{}, error) {
		return srv.(AgentServiceServer).GetServerMetrics(ctx, req.(*Empty))
	}
	return interceptor(ctx, in, info, handler)
}

func _AgentService_PullImage_Handler(srv interface{}, ctx context.Context, dec func(interface{}) error, interceptor grpc.UnaryServerInterceptor) (interface{}, error) {
	in := new(PullImageRequest)
	if err := dec(in); err != nil {
		return nil, err
	}
	if interceptor == nil {
		return srv.(AgentServiceServer).PullImage(ctx, in)
	}
	info := &grpc.UnaryServerInfo{Server: srv, FullMethod: "/agent.AgentService/PullImage"}
	handler := func(ctx context.Context, req interface{}) (interface{}, error) {
		return srv.(AgentServiceServer).PullImage(ctx, req.(*PullImageRequest))
	}
	return interceptor(ctx, in, info, handler)
}

func _AgentService_GetContainerLogs_Handler(srv interface{}, stream grpc.ServerStream) error {
	m := new(GetLogsRequest)
	if err := stream.RecvMsg(m); err != nil {
		return err
	}
	return srv.(AgentServiceServer).StreamContainerLogs(m, &agentServiceStreamLogsServer{stream})
}

type agentServiceStreamLogsServer struct {
	grpc.ServerStream
}

func (x *agentServiceStreamLogsServer) Send(m *LogLine) error {
	return x.ServerStream.SendMsg(m)
}
