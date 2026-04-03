package metrics

import (
	"net/http"
	"strconv"
	"time"

	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promauto"
	"github.com/prometheus/client_golang/prometheus/promhttp"
)

// Metrics holds all Prometheus metrics
type Metrics struct {
	// HTTP metrics
	HTTPRequestsTotal    *prometheus.CounterVec
	HTTPRequestDuration  *prometheus.HistogramVec
	HTTPRequestsInFlight prometheus.Gauge

	// Deployment metrics
	DeploymentsTotal   *prometheus.CounterVec
	DeploymentDuration *prometheus.HistogramVec
	DeploymentsActive  prometheus.Gauge

	// Container metrics
	ContainersTotal     *prometheus.GaugeVec
	ContainerCPUUsage   *prometheus.GaugeVec
	ContainerMemUsage   *prometheus.GaugeVec
	ContainerNetworkRx  *prometheus.CounterVec
	ContainerNetworkTx  *prometheus.CounterVec
	ContainerRestarts   *prometheus.CounterVec

	// Server metrics
	ServersTotal      prometheus.Gauge
	ServersOnline     prometheus.Gauge
	ServerCPUUsage    *prometheus.GaugeVec
	ServerMemUsage    *prometheus.GaugeVec
	ServerDiskUsage   *prometheus.GaugeVec
	ServerContainers  *prometheus.GaugeVec

	// Build metrics
	BuildsTotal      *prometheus.CounterVec
	BuildDuration    *prometheus.HistogramVec
	BuildQueueLength prometheus.Gauge

	// Health check metrics
	HealthChecksTotal  *prometheus.CounterVec
	HealthCheckLatency *prometheus.HistogramVec
	FailoversTotal     prometheus.Counter

	// Application metrics
	ApplicationsTotal   prometheus.Gauge
	DomainsTotal        prometheus.Gauge
	SSLCertificatesTotal *prometheus.GaugeVec
}

// New creates a new Metrics instance with all metrics registered
func New(namespace string) *Metrics {
	m := &Metrics{
		// HTTP metrics
		HTTPRequestsTotal: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "http_requests_total",
				Help:      "Total number of HTTP requests",
			},
			[]string{"method", "path", "status"},
		),
		HTTPRequestDuration: promauto.NewHistogramVec(
			prometheus.HistogramOpts{
				Namespace: namespace,
				Name:      "http_request_duration_seconds",
				Help:      "HTTP request latency in seconds",
				Buckets:   []float64{.005, .01, .025, .05, .1, .25, .5, 1, 2.5, 5, 10},
			},
			[]string{"method", "path"},
		),
		HTTPRequestsInFlight: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "http_requests_in_flight",
				Help:      "Current number of HTTP requests being processed",
			},
		),

		// Deployment metrics
		DeploymentsTotal: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "deployments_total",
				Help:      "Total number of deployments",
			},
			[]string{"application", "status"},
		),
		DeploymentDuration: promauto.NewHistogramVec(
			prometheus.HistogramOpts{
				Namespace: namespace,
				Name:      "deployment_duration_seconds",
				Help:      "Deployment duration in seconds",
				Buckets:   []float64{10, 30, 60, 120, 300, 600, 1200},
			},
			[]string{"application", "status"},
		),
		DeploymentsActive: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "deployments_active",
				Help:      "Number of active deployments",
			},
		),

		// Container metrics
		ContainersTotal: promauto.NewGaugeVec(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "containers_total",
				Help:      "Total number of containers by status",
			},
			[]string{"status"},
		),
		ContainerCPUUsage: promauto.NewGaugeVec(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "container_cpu_usage_percent",
				Help:      "Container CPU usage percentage",
			},
			[]string{"container_id", "application"},
		),
		ContainerMemUsage: promauto.NewGaugeVec(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "container_memory_usage_bytes",
				Help:      "Container memory usage in bytes",
			},
			[]string{"container_id", "application"},
		),
		ContainerNetworkRx: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "container_network_rx_bytes_total",
				Help:      "Container network received bytes",
			},
			[]string{"container_id", "application"},
		),
		ContainerNetworkTx: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "container_network_tx_bytes_total",
				Help:      "Container network transmitted bytes",
			},
			[]string{"container_id", "application"},
		),
		ContainerRestarts: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "container_restarts_total",
				Help:      "Container restart count",
			},
			[]string{"container_id", "application"},
		),

		// Server metrics
		ServersTotal: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "servers_total",
				Help:      "Total number of servers",
			},
		),
		ServersOnline: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "servers_online",
				Help:      "Number of online servers",
			},
		),
		ServerCPUUsage: promauto.NewGaugeVec(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "server_cpu_usage_percent",
				Help:      "Server CPU usage percentage",
			},
			[]string{"server_id", "server_name"},
		),
		ServerMemUsage: promauto.NewGaugeVec(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "server_memory_usage_percent",
				Help:      "Server memory usage percentage",
			},
			[]string{"server_id", "server_name"},
		),
		ServerDiskUsage: promauto.NewGaugeVec(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "server_disk_usage_percent",
				Help:      "Server disk usage percentage",
			},
			[]string{"server_id", "server_name"},
		),
		ServerContainers: promauto.NewGaugeVec(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "server_containers",
				Help:      "Number of containers per server",
			},
			[]string{"server_id", "server_name"},
		),

		// Build metrics
		BuildsTotal: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "builds_total",
				Help:      "Total number of builds",
			},
			[]string{"application", "status"},
		),
		BuildDuration: promauto.NewHistogramVec(
			prometheus.HistogramOpts{
				Namespace: namespace,
				Name:      "build_duration_seconds",
				Help:      "Build duration in seconds",
				Buckets:   []float64{10, 30, 60, 120, 300, 600, 1200, 1800},
			},
			[]string{"application", "type"},
		),
		BuildQueueLength: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "build_queue_length",
				Help:      "Number of builds in queue",
			},
		),

		// Health check metrics
		HealthChecksTotal: promauto.NewCounterVec(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "health_checks_total",
				Help:      "Total number of health checks",
			},
			[]string{"result"},
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
		FailoversTotal: promauto.NewCounter(
			prometheus.CounterOpts{
				Namespace: namespace,
				Name:      "failovers_total",
				Help:      "Total number of container failovers",
			},
		),

		// Application metrics
		ApplicationsTotal: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "applications_total",
				Help:      "Total number of applications",
			},
		),
		DomainsTotal: promauto.NewGauge(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "domains_total",
				Help:      "Total number of custom domains",
			},
		),
		SSLCertificatesTotal: promauto.NewGaugeVec(
			prometheus.GaugeOpts{
				Namespace: namespace,
				Name:      "ssl_certificates_total",
				Help:      "Number of SSL certificates by status",
			},
			[]string{"status"},
		),
	}

	return m
}

// Handler returns the Prometheus HTTP handler
func Handler() http.Handler {
	return promhttp.Handler()
}

// RecordHTTPRequest records an HTTP request
func (m *Metrics) RecordHTTPRequest(method, path string, status int, duration time.Duration) {
	m.HTTPRequestsTotal.WithLabelValues(method, path, strconv.Itoa(status)).Inc()
	m.HTTPRequestDuration.WithLabelValues(method, path).Observe(duration.Seconds())
}

// RecordDeployment records a deployment
func (m *Metrics) RecordDeployment(application, status string, duration time.Duration) {
	m.DeploymentsTotal.WithLabelValues(application, status).Inc()
	m.DeploymentDuration.WithLabelValues(application, status).Observe(duration.Seconds())
}

// RecordBuild records a build
func (m *Metrics) RecordBuild(application, buildType, status string, duration time.Duration) {
	m.BuildsTotal.WithLabelValues(application, status).Inc()
	m.BuildDuration.WithLabelValues(application, buildType).Observe(duration.Seconds())
}

// RecordContainerStats records container statistics
func (m *Metrics) RecordContainerStats(containerID, application string, cpuPercent, memBytes float64, netRx, netTx int64) {
	m.ContainerCPUUsage.WithLabelValues(containerID, application).Set(cpuPercent)
	m.ContainerMemUsage.WithLabelValues(containerID, application).Set(memBytes)
	m.ContainerNetworkRx.WithLabelValues(containerID, application).Add(float64(netRx))
	m.ContainerNetworkTx.WithLabelValues(containerID, application).Add(float64(netTx))
}

// RecordServerStats records server statistics
func (m *Metrics) RecordServerStats(serverID, serverName string, cpuPercent, memPercent, diskPercent float64, containers int) {
	m.ServerCPUUsage.WithLabelValues(serverID, serverName).Set(cpuPercent)
	m.ServerMemUsage.WithLabelValues(serverID, serverName).Set(memPercent)
	m.ServerDiskUsage.WithLabelValues(serverID, serverName).Set(diskPercent)
	m.ServerContainers.WithLabelValues(serverID, serverName).Set(float64(containers))
}

// RecordHealthCheck records a health check result
func (m *Metrics) RecordHealthCheck(containerID string, healthy bool, latency time.Duration) {
	result := "healthy"
	if !healthy {
		result = "unhealthy"
	}
	m.HealthChecksTotal.WithLabelValues(result).Inc()
	m.HealthCheckLatency.WithLabelValues(containerID).Observe(latency.Seconds())
}

// RecordFailover records a failover event
func (m *Metrics) RecordFailover() {
	m.FailoversTotal.Inc()
}

// SetContainerCounts sets container counts by status
func (m *Metrics) SetContainerCounts(running, stopped, failed int) {
	m.ContainersTotal.WithLabelValues("running").Set(float64(running))
	m.ContainersTotal.WithLabelValues("stopped").Set(float64(stopped))
	m.ContainersTotal.WithLabelValues("failed").Set(float64(failed))
}

// SetServerCounts sets server counts
func (m *Metrics) SetServerCounts(total, online int) {
	m.ServersTotal.Set(float64(total))
	m.ServersOnline.Set(float64(online))
}

// SetApplicationCount sets application count
func (m *Metrics) SetApplicationCount(count int) {
	m.ApplicationsTotal.Set(float64(count))
}

// SetDomainCount sets domain count
func (m *Metrics) SetDomainCount(count int) {
	m.DomainsTotal.Set(float64(count))
}

// SetSSLCertificateCounts sets SSL certificate counts by status
func (m *Metrics) SetSSLCertificateCounts(issued, pending, failed int) {
	m.SSLCertificatesTotal.WithLabelValues("issued").Set(float64(issued))
	m.SSLCertificatesTotal.WithLabelValues("pending").Set(float64(pending))
	m.SSLCertificatesTotal.WithLabelValues("failed").Set(float64(failed))
}

// SetBuildQueueLength sets the build queue length
func (m *Metrics) SetBuildQueueLength(length int) {
	m.BuildQueueLength.Set(float64(length))
}

// SetActiveDeployments sets the number of active deployments
func (m *Metrics) SetActiveDeployments(count int) {
	m.DeploymentsActive.Set(float64(count))
}
