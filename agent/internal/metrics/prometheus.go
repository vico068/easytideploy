package metrics

import (
	"net/http"

	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promauto"
	"github.com/prometheus/client_golang/prometheus/promhttp"
)

// Metrics holds all Prometheus metrics for the agent
type Metrics struct {
	// System metrics
	CPUUsagePercent    prometheus.Gauge
	MemoryUsageBytes   prometheus.Gauge
	MemoryUsagePercent prometheus.Gauge
	DiskUsageBytes     prometheus.Gauge
	DiskUsagePercent   prometheus.Gauge
	LoadAverage1       prometheus.Gauge
	LoadAverage5       prometheus.Gauge
	LoadAverage15      prometheus.Gauge

	// Container metrics
	ContainersTotal     *prometheus.GaugeVec
	ContainerCPUUsage   *prometheus.GaugeVec
	ContainerMemUsage   *prometheus.GaugeVec
	ContainerNetRx      *prometheus.CounterVec
	ContainerNetTx      *prometheus.CounterVec
	ContainerBlockRead  *prometheus.CounterVec
	ContainerBlockWrite *prometheus.CounterVec
	ContainerRestarts   *prometheus.CounterVec

	// Docker operations
	DockerPullTotal     *prometheus.CounterVec
	DockerPullDuration  *prometheus.HistogramVec
	DockerCreateTotal   *prometheus.CounterVec
	DockerStartTotal    *prometheus.CounterVec
	DockerStopTotal     *prometheus.CounterVec
	DockerRemoveTotal   *prometheus.CounterVec
	DockerOperationErrs *prometheus.CounterVec

	// Health monitoring
	HeartbeatTotal       prometheus.Counter
	HeartbeatFailures    prometheus.Counter
	LastHeartbeatSuccess prometheus.Gauge
	HealthCheckLatency   *prometheus.HistogramVec

	// gRPC metrics
	GRPCRequestsTotal   *prometheus.CounterVec
	GRPCRequestDuration *prometheus.HistogramVec
	GRPCActiveStreams   prometheus.Gauge

	// Agent info
	AgentInfo *prometheus.GaugeVec
}

// New creates a new Metrics instance with all metrics registered
func New(namespace string, serverID string) *Metrics {
	m := &Metrics{
		// System metrics
		CPUUsagePercent: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "cpu_usage_percent",
				Help:      "Current CPU usage percentage",
			},
		),
		MemoryUsageBytes: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "memory_usage_bytes",
				Help:      "Current memory usage in bytes",
			},
		),
		MemoryUsagePercent: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "memory_usage_percent",
				Help:      "Current memory usage percentage",
			},
		),
		DiskUsageBytes: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "disk_usage_bytes",
				Help:      "Current disk usage in bytes",
			},
		),
		DiskUsagePercent: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "disk_usage_percent",
				Help:      "Current disk usage percentage",
			},
		),
		LoadAverage1: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "load_average_1m",
				Help:      "1-minute load average",
			},
		),
		LoadAverage5: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "load_average_5m",
				Help:      "5-minute load average",
			},
		),
		LoadAverage15: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "load_average_15m",
				Help:      "15-minute load average",
			},
		),

		// Container metrics
		ContainersTotal: promauto.NewGaugeVec(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "containers_total",
				Help:      "Total number of containers by state",
			},
			[]string{"state"},
		),
		ContainerCPUUsage: promauto.NewGaugeVec(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "container_cpu_usage_percent",
				Help:      "Container CPU usage percentage",
			},
			[]string{"container_id", "name"},
		),
		ContainerMemUsage: promauto.NewGaugeVec(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "container_memory_usage_bytes",
				Help:      "Container memory usage in bytes",
			},
			[]string{"container_id", "name"},
		),
		ContainerNetRx: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "container_network_rx_bytes_total",
				Help:      "Container network received bytes total",
			},
			[]string{"container_id", "name"},
		),
		ContainerNetTx: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "container_network_tx_bytes_total",
				Help:      "Container network transmitted bytes total",
			},
			[]string{"container_id", "name"},
		),
		ContainerBlockRead: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "container_block_read_bytes_total",
				Help:      "Container block read bytes total",
			},
			[]string{"container_id", "name"},
		),
		ContainerBlockWrite: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "container_block_write_bytes_total",
				Help:      "Container block write bytes total",
			},
			[]string{"container_id", "name"},
		),
		ContainerRestarts: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "container_restarts_total",
				Help:      "Container restart count",
			},
			[]string{"container_id", "name"},
		),

		// Docker operations
		DockerPullTotal: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "docker_pull_total",
				Help:      "Total number of docker image pulls",
			},
			[]string{"image", "status"},
		),
		DockerPullDuration: promauto.NewHistogramVec(
			prometheus.HistogramOpts{
				Namespace: namespace,
				Name:      "docker_pull_duration_seconds",
				Help:      "Docker image pull duration in seconds",
				Buckets:   []float64{1, 5, 10, 30, 60, 120, 300},
			},
			[]string{"image"},
		),
		DockerCreateTotal: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "docker_create_total",
				Help:      "Total number of container creates",
			},
			[]string{"status"},
		),
		DockerStartTotal: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "docker_start_total",
				Help:      "Total number of container starts",
			},
			[]string{"status"},
		),
		DockerStopTotal: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "docker_stop_total",
				Help:      "Total number of container stops",
			},
			[]string{"status"},
		),
		DockerRemoveTotal: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "docker_remove_total",
				Help:      "Total number of container removes",
			},
			[]string{"status"},
		),
		DockerOperationErrs: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "docker_operation_errors_total",
				Help:      "Total number of docker operation errors",
			},
			[]string{"operation"},
		),

		// Health monitoring
		HeartbeatTotal: promauto.NewCounter(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "heartbeat_total",
				Help:      "Total number of heartbeats sent",
			},
		),
		HeartbeatFailures: promauto.NewCounter(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "heartbeat_failures_total",
				Help:      "Total number of heartbeat failures",
			},
		),
		LastHeartbeatSuccess: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "last_heartbeat_success_timestamp",
				Help:      "Unix timestamp of last successful heartbeat",
			},
		),
		HealthCheckLatency: promauto.NewHistogramVec(
			prometheus.HistogramOpts{
				Namespace: namespace,
				Name:      "health_check_latency_seconds",
				Help:      "Health check latency in seconds",
				Buckets:   []float64{.001, .005, .01, .025, .05, .1, .25, .5, 1},
			},
			[]string{"container_id"},
		),

		// gRPC metrics
		GRPCRequestsTotal: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "grpc_requests_total",
				Help:      "Total number of gRPC requests",
			},
			[]string{"method", "status"},
		),
		GRPCRequestDuration: promauto.NewHistogramVec(
			prometheus.HistogramOpts{
				Namespace: namespace,
				Name:      "grpc_request_duration_seconds",
				Help:      "gRPC request duration in seconds",
				Buckets:   []float64{.005, .01, .025, .05, .1, .25, .5, 1, 2.5, 5, 10},
			},
			[]string{"method"},
		),
		GRPCActiveStreams: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "grpc_active_streams",
				Help:      "Number of active gRPC streams",
			},
		),

		// Agent info
		AgentInfo: promauto.NewGaugeVec(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "agent_info",
				Help:      "Agent information (always 1)",
			},
			[]string{"server_id", "version"},
		),
	}

	return m
}

// Handler returns the Prometheus HTTP handler
func Handler() http.Handler {
	return promhttp.Handler()
}

// SetSystemMetrics updates system metrics
func (m *Metrics) SetSystemMetrics(cpu, memBytes, memPercent, diskBytes, diskPercent float64) {
	m.CPUUsagePercent.Set(cpu)
	m.MemoryUsageBytes.Set(memBytes)
	m.MemoryUsagePercent.Set(memPercent)
	m.DiskUsageBytes.Set(diskBytes)
	m.DiskUsagePercent.Set(diskPercent)
}

// SetLoadAverage updates load average metrics
func (m *Metrics) SetLoadAverage(load1, load5, load15 float64) {
	m.LoadAverage1.Set(load1)
	m.LoadAverage5.Set(load5)
	m.LoadAverage15.Set(load15)
}

// SetContainerCounts updates container counts by state
func (m *Metrics) SetContainerCounts(running, paused, stopped int) {
	m.ContainersTotal.WithLabelValues("running").Set(float64(running))
	m.ContainersTotal.WithLabelValues("paused").Set(float64(paused))
	m.ContainersTotal.WithLabelValues("stopped").Set(float64(stopped))
}

// RecordContainerStats records container statistics
func (m *Metrics) RecordContainerStats(containerID, name string, cpu, memBytes float64) {
	m.ContainerCPUUsage.WithLabelValues(containerID, name).Set(cpu)
	m.ContainerMemUsage.WithLabelValues(containerID, name).Set(memBytes)
}

// RecordContainerNetwork records container network statistics
func (m *Metrics) RecordContainerNetwork(containerID, name string, rx, tx int64) {
	m.ContainerNetRx.WithLabelValues(containerID, name).Add(float64(rx))
	m.ContainerNetTx.WithLabelValues(containerID, name).Add(float64(tx))
}

// RecordDockerPull records a docker pull operation
func (m *Metrics) RecordDockerPull(image, status string, duration float64) {
	m.DockerPullTotal.WithLabelValues(image, status).Inc()
	if status == "success" {
		m.DockerPullDuration.WithLabelValues(image).Observe(duration)
	}
}

// RecordDockerOperation records a docker operation
func (m *Metrics) RecordDockerOperation(operation, status string) {
	switch operation {
	case "create":
		m.DockerCreateTotal.WithLabelValues(status).Inc()
	case "start":
		m.DockerStartTotal.WithLabelValues(status).Inc()
	case "stop":
		m.DockerStopTotal.WithLabelValues(status).Inc()
	case "remove":
		m.DockerRemoveTotal.WithLabelValues(status).Inc()
	}
	if status == "error" {
		m.DockerOperationErrs.WithLabelValues(operation).Inc()
	}
}

// RecordHeartbeat records a heartbeat
func (m *Metrics) RecordHeartbeat(success bool) {
	m.HeartbeatTotal.Inc()
	if success {
		m.LastHeartbeatSuccess.SetToCurrentTime()
	} else {
		m.HeartbeatFailures.Inc()
	}
}

// RecordGRPCRequest records a gRPC request
func (m *Metrics) RecordGRPCRequest(method, status string, duration float64) {
	m.GRPCRequestsTotal.WithLabelValues(method, status).Inc()
	m.GRPCRequestDuration.WithLabelValues(method).Observe(duration)
}

// SetAgentInfo sets agent information metric
func (m *Metrics) SetAgentInfo(serverID, version string) {
	m.AgentInfo.WithLabelValues(serverID, version).Set(1)
}
