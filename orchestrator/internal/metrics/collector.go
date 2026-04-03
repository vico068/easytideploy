package metrics

import (
	"context"
	"sync"
	"time"

	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/easyti/easydeploy/orchestrator/internal/queue"
	"github.com/rs/zerolog/log"
)

// Collector collects metrics from the database and queue
type Collector struct {
	db      *database.DB
	queue   *queue.RedisQueue
	metrics *Metrics
	ticker  *time.Ticker
	stopCh  chan struct{}
	wg      sync.WaitGroup
}

// NewCollector creates a new metrics collector
func NewCollector(db *database.DB, q *queue.RedisQueue, m *Metrics) *Collector {
	return &Collector{
		db:      db,
		queue:   q,
		metrics: m,
		stopCh:  make(chan struct{}),
	}
}

// Start starts collecting metrics
func (c *Collector) Start(interval time.Duration) {
	c.ticker = time.NewTicker(interval)

	c.wg.Add(1)
	go func() {
		defer c.wg.Done()

		// Collect immediately on start
		c.collect()

		for {
			select {
			case <-c.ticker.C:
				c.collect()
			case <-c.stopCh:
				return
			}
		}
	}()

	log.Info().Dur("interval", interval).Msg("Metrics collector started")
}

// Stop stops the collector
func (c *Collector) Stop() {
	if c.ticker != nil {
		c.ticker.Stop()
	}
	close(c.stopCh)
	c.wg.Wait()
	log.Info().Msg("Metrics collector stopped")
}

// collect gathers all metrics
func (c *Collector) collect() {
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	// Collect in parallel
	var wg sync.WaitGroup

	wg.Add(1)
	go func() {
		defer wg.Done()
		c.collectApplicationMetrics(ctx)
	}()

	wg.Add(1)
	go func() {
		defer wg.Done()
		c.collectServerMetrics(ctx)
	}()

	wg.Add(1)
	go func() {
		defer wg.Done()
		c.collectContainerMetrics(ctx)
	}()

	wg.Add(1)
	go func() {
		defer wg.Done()
		c.collectDeploymentMetrics(ctx)
	}()

	wg.Add(1)
	go func() {
		defer wg.Done()
		c.collectQueueMetrics(ctx)
	}()

	wg.Add(1)
	go func() {
		defer wg.Done()
		c.collectSSLMetrics(ctx)
	}()

	wg.Wait()
}

// collectApplicationMetrics collects application and domain metrics
func (c *Collector) collectApplicationMetrics(ctx context.Context) {
	// Count applications
	var appCount int
	query := `SELECT COUNT(*) FROM applications WHERE status = 'active'`
	if err := c.db.Pool().QueryRow(ctx, query).Scan(&appCount); err == nil {
		c.metrics.SetApplicationCount(appCount)
	}

	// Count domains
	var domainCount int
	query = `SELECT COUNT(*) FROM domains WHERE verified = true`
	if err := c.db.Pool().QueryRow(ctx, query).Scan(&domainCount); err == nil {
		c.metrics.SetDomainCount(domainCount)
	}
}

// collectServerMetrics collects server metrics
func (c *Collector) collectServerMetrics(ctx context.Context) {
	// Count servers
	var total, online int
	query := `SELECT COUNT(*), COUNT(*) FILTER (WHERE status = 'online') FROM servers`
	if err := c.db.Pool().QueryRow(ctx, query).Scan(&total, &online); err == nil {
		c.metrics.SetServerCounts(total, online)
	}

	// Get per-server metrics
	query = `
		SELECT s.id, s.name,
			COALESCE(AVG(ru.cpu_percent), 0) as cpu,
			COALESCE(AVG(ru.memory_percent), 0) as memory,
			COALESCE(AVG(ru.disk_percent), 0) as disk,
			COUNT(c.id) as containers
		FROM servers s
		LEFT JOIN resource_usages ru ON s.id = ru.server_id AND ru.created_at > NOW() - INTERVAL '5 minutes'
		LEFT JOIN containers c ON s.id = c.server_id AND c.status = 'running'
		WHERE s.status = 'online'
		GROUP BY s.id, s.name
	`

	rows, err := c.db.Pool().Query(ctx, query)
	if err != nil {
		log.Warn().Err(err).Msg("Failed to collect server metrics")
		return
	}
	defer rows.Close()

	for rows.Next() {
		var serverID, serverName string
		var cpu, memory, disk float64
		var containers int

		if err := rows.Scan(&serverID, &serverName, &cpu, &memory, &disk, &containers); err != nil {
			continue
		}

		c.metrics.RecordServerStats(serverID, serverName, cpu, memory, disk, containers)
	}
}

// collectContainerMetrics collects container metrics
func (c *Collector) collectContainerMetrics(ctx context.Context) {
	// Count containers by status
	query := `
		SELECT
			COUNT(*) FILTER (WHERE status = 'running') as running,
			COUNT(*) FILTER (WHERE status = 'stopped') as stopped,
			COUNT(*) FILTER (WHERE status = 'failed') as failed
		FROM containers
	`

	var running, stopped, failed int
	if err := c.db.Pool().QueryRow(ctx, query).Scan(&running, &stopped, &failed); err == nil {
		c.metrics.SetContainerCounts(running, stopped, failed)
	}

	// Get per-container metrics from recent resource usage
	query = `
		SELECT c.id, c.application_id,
			COALESCE(AVG(ru.cpu_percent), 0) as cpu,
			COALESCE(AVG(ru.memory_usage), 0) as memory,
			COALESCE(SUM(ru.network_in), 0) as net_rx,
			COALESCE(SUM(ru.network_out), 0) as net_tx
		FROM containers c
		LEFT JOIN resource_usages ru ON c.id = ru.container_id AND ru.created_at > NOW() - INTERVAL '5 minutes'
		WHERE c.status = 'running'
		GROUP BY c.id, c.application_id
	`

	rows, err := c.db.Pool().Query(ctx, query)
	if err != nil {
		log.Warn().Err(err).Msg("Failed to collect container metrics")
		return
	}
	defer rows.Close()

	for rows.Next() {
		var containerID, appID string
		var cpu, memory float64
		var netRx, netTx int64

		if err := rows.Scan(&containerID, &appID, &cpu, &memory, &netRx, &netTx); err != nil {
			continue
		}

		c.metrics.RecordContainerStats(containerID, appID, cpu, memory, netRx, netTx)
	}
}

// collectDeploymentMetrics collects deployment metrics
func (c *Collector) collectDeploymentMetrics(ctx context.Context) {
	// Count active deployments
	var activeCount int
	query := `SELECT COUNT(*) FROM deployments WHERE status IN ('pending', 'building', 'deploying')`
	if err := c.db.Pool().QueryRow(ctx, query).Scan(&activeCount); err == nil {
		c.metrics.SetActiveDeployments(activeCount)
	}
}

// collectQueueMetrics collects queue metrics
func (c *Collector) collectQueueMetrics(ctx context.Context) {
	if c.queue == nil {
		return
	}

	length, err := c.queue.Len("builds")
	if err == nil {
		c.metrics.SetBuildQueueLength(int(length))
	}
}

// collectSSLMetrics collects SSL certificate metrics
func (c *Collector) collectSSLMetrics(ctx context.Context) {
	query := `
		SELECT
			COUNT(*) FILTER (WHERE ssl_status = 'issued') as issued,
			COUNT(*) FILTER (WHERE ssl_status = 'pending') as pending,
			COUNT(*) FILTER (WHERE ssl_status = 'failed') as failed
		FROM domains
		WHERE verified = true
	`

	var issued, pending, failed int
	if err := c.db.Pool().QueryRow(ctx, query).Scan(&issued, &pending, &failed); err == nil {
		c.metrics.SetSSLCertificateCounts(issued, pending, failed)
	}
}
