package alerting

import (
	"context"
	"fmt"
	"time"

	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/google/uuid"
)

// DefaultRules returns the default alert rules
func DefaultRules() []*AlertRule {
	return []*AlertRule{
		ContainerDownRule(),
		ContainerHighCPURule(80),
		ContainerHighMemoryRule(85),
		ServerDownRule(),
		ServerHighCPURule(90),
		ServerHighMemoryRule(90),
		ServerHighDiskRule(85),
		DeploymentFailedRule(),
		SSLExpiringRule(14),
	}
}

// ContainerDownRule creates a rule for down containers
func ContainerDownRule() *AlertRule {
	return &AlertRule{
		Name:     "ContainerDown",
		Type:     AlertContainerDown,
		Severity: SeverityCritical,
		Cooldown: 5 * time.Minute,
		Condition: func(ctx context.Context, db *database.DB) ([]*Alert, error) {
			query := `
				SELECT c.id, c.name, a.name as app_name, s.name as server_name
				FROM containers c
				JOIN applications a ON c.application_id = a.id
				JOIN servers s ON c.server_id = s.id
				WHERE c.status = 'failed'
				AND c.updated_at > NOW() - INTERVAL '5 minutes'
			`

			rows, err := db.Pool().Query(ctx, query)
			if err != nil {
				return nil, err
			}
			defer rows.Close()

			var alerts []*Alert
			for rows.Next() {
				var containerID, containerName, appName, serverName string
				if err := rows.Scan(&containerID, &containerName, &appName, &serverName); err != nil {
					continue
				}

				alerts = append(alerts, &Alert{
					ID:       fmt.Sprintf("container-down-%s", containerID[:8]),
					Type:     AlertContainerDown,
					Severity: SeverityCritical,
					Title:    "Container Down",
					Message:  fmt.Sprintf("Container %s is down", containerName),
					Labels: map[string]string{
						"container_id":   containerID,
						"container_name": containerName,
						"application":    appName,
						"server":         serverName,
					},
					StartsAt: time.Now(),
					Status:   "firing",
				})
			}

			return alerts, nil
		},
	}
}

// ContainerHighCPURule creates a rule for high CPU usage
func ContainerHighCPURule(threshold float64) *AlertRule {
	return &AlertRule{
		Name:     "ContainerHighCPU",
		Type:     AlertContainerHighCPU,
		Severity: SeverityWarning,
		Cooldown: 10 * time.Minute,
		Condition: func(ctx context.Context, db *database.DB) ([]*Alert, error) {
			query := `
				SELECT c.id, c.name, a.name as app_name, AVG(ru.cpu_percent) as avg_cpu
				FROM containers c
				JOIN applications a ON c.application_id = a.id
				JOIN resource_usages ru ON c.id = ru.container_id
				WHERE ru.created_at > NOW() - INTERVAL '5 minutes'
				AND c.status = 'running'
				GROUP BY c.id, c.name, a.name
				HAVING AVG(ru.cpu_percent) > $1
			`

			rows, err := db.Pool().Query(ctx, query, threshold)
			if err != nil {
				return nil, err
			}
			defer rows.Close()

			var alerts []*Alert
			for rows.Next() {
				var containerID, containerName, appName string
				var avgCPU float64
				if err := rows.Scan(&containerID, &containerName, &appName, &avgCPU); err != nil {
					continue
				}

				alerts = append(alerts, &Alert{
					ID:       fmt.Sprintf("container-cpu-%s", containerID[:8]),
					Type:     AlertContainerHighCPU,
					Severity: SeverityWarning,
					Title:    "High CPU Usage",
					Message:  fmt.Sprintf("Container %s has %.1f%% CPU usage", containerName, avgCPU),
					Labels: map[string]string{
						"container_id":   containerID,
						"container_name": containerName,
						"application":    appName,
						"cpu_percent":    fmt.Sprintf("%.1f", avgCPU),
					},
					StartsAt: time.Now(),
					Status:   "firing",
				})
			}

			return alerts, nil
		},
	}
}

// ContainerHighMemoryRule creates a rule for high memory usage
func ContainerHighMemoryRule(threshold float64) *AlertRule {
	return &AlertRule{
		Name:     "ContainerHighMemory",
		Type:     AlertContainerHighMemory,
		Severity: SeverityWarning,
		Cooldown: 10 * time.Minute,
		Condition: func(ctx context.Context, db *database.DB) ([]*Alert, error) {
			query := `
				SELECT c.id, c.name, a.name as app_name, AVG(ru.memory_percent) as avg_mem
				FROM containers c
				JOIN applications a ON c.application_id = a.id
				JOIN resource_usages ru ON c.id = ru.container_id
				WHERE ru.created_at > NOW() - INTERVAL '5 minutes'
				AND c.status = 'running'
				GROUP BY c.id, c.name, a.name
				HAVING AVG(ru.memory_percent) > $1
			`

			rows, err := db.Pool().Query(ctx, query, threshold)
			if err != nil {
				return nil, err
			}
			defer rows.Close()

			var alerts []*Alert
			for rows.Next() {
				var containerID, containerName, appName string
				var avgMem float64
				if err := rows.Scan(&containerID, &containerName, &appName, &avgMem); err != nil {
					continue
				}

				alerts = append(alerts, &Alert{
					ID:       fmt.Sprintf("container-mem-%s", containerID[:8]),
					Type:     AlertContainerHighMemory,
					Severity: SeverityWarning,
					Title:    "High Memory Usage",
					Message:  fmt.Sprintf("Container %s has %.1f%% memory usage", containerName, avgMem),
					Labels: map[string]string{
						"container_id":   containerID,
						"container_name": containerName,
						"application":    appName,
						"memory_percent": fmt.Sprintf("%.1f", avgMem),
					},
					StartsAt: time.Now(),
					Status:   "firing",
				})
			}

			return alerts, nil
		},
	}
}

// ServerDownRule creates a rule for down servers
func ServerDownRule() *AlertRule {
	return &AlertRule{
		Name:     "ServerDown",
		Type:     AlertServerDown,
		Severity: SeverityCritical,
		Cooldown: 2 * time.Minute,
		Condition: func(ctx context.Context, db *database.DB) ([]*Alert, error) {
			query := `
				SELECT id, name, ip_address
				FROM servers
				WHERE status = 'offline'
				AND last_heartbeat < NOW() - INTERVAL '5 minutes'
			`

			rows, err := db.Pool().Query(ctx, query)
			if err != nil {
				return nil, err
			}
			defer rows.Close()

			var alerts []*Alert
			for rows.Next() {
				var serverID, serverName, ipAddress string
				if err := rows.Scan(&serverID, &serverName, &ipAddress); err != nil {
					continue
				}

				alerts = append(alerts, &Alert{
					ID:       fmt.Sprintf("server-down-%s", serverID[:8]),
					Type:     AlertServerDown,
					Severity: SeverityCritical,
					Title:    "Server Down",
					Message:  fmt.Sprintf("Server %s (%s) is offline", serverName, ipAddress),
					Labels: map[string]string{
						"server_id":   serverID,
						"server_name": serverName,
						"ip_address":  ipAddress,
					},
					StartsAt: time.Now(),
					Status:   "firing",
				})
			}

			return alerts, nil
		},
	}
}

// ServerHighCPURule creates a rule for high server CPU
func ServerHighCPURule(threshold float64) *AlertRule {
	return &AlertRule{
		Name:     "ServerHighCPU",
		Type:     AlertServerHighCPU,
		Severity: SeverityWarning,
		Cooldown: 10 * time.Minute,
		Condition: func(ctx context.Context, db *database.DB) ([]*Alert, error) {
			query := `
				SELECT s.id, s.name, AVG(ru.cpu_percent) as avg_cpu
				FROM servers s
				JOIN resource_usages ru ON s.id = ru.server_id
				WHERE ru.created_at > NOW() - INTERVAL '5 minutes'
				AND s.status = 'online'
				GROUP BY s.id, s.name
				HAVING AVG(ru.cpu_percent) > $1
			`

			rows, err := db.Pool().Query(ctx, query, threshold)
			if err != nil {
				return nil, err
			}
			defer rows.Close()

			var alerts []*Alert
			for rows.Next() {
				var serverID, serverName string
				var avgCPU float64
				if err := rows.Scan(&serverID, &serverName, &avgCPU); err != nil {
					continue
				}

				alerts = append(alerts, &Alert{
					ID:       fmt.Sprintf("server-cpu-%s", serverID[:8]),
					Type:     AlertServerHighCPU,
					Severity: SeverityWarning,
					Title:    "High Server CPU",
					Message:  fmt.Sprintf("Server %s has %.1f%% CPU usage", serverName, avgCPU),
					Labels: map[string]string{
						"server_id":   serverID,
						"server_name": serverName,
						"cpu_percent": fmt.Sprintf("%.1f", avgCPU),
					},
					StartsAt: time.Now(),
					Status:   "firing",
				})
			}

			return alerts, nil
		},
	}
}

// ServerHighMemoryRule creates a rule for high server memory
func ServerHighMemoryRule(threshold float64) *AlertRule {
	return &AlertRule{
		Name:     "ServerHighMemory",
		Type:     AlertServerHighMemory,
		Severity: SeverityWarning,
		Cooldown: 10 * time.Minute,
		Condition: func(ctx context.Context, db *database.DB) ([]*Alert, error) {
			query := `
				SELECT s.id, s.name, AVG(ru.memory_percent) as avg_mem
				FROM servers s
				JOIN resource_usages ru ON s.id = ru.server_id
				WHERE ru.created_at > NOW() - INTERVAL '5 minutes'
				AND s.status = 'online'
				GROUP BY s.id, s.name
				HAVING AVG(ru.memory_percent) > $1
			`

			rows, err := db.Pool().Query(ctx, query, threshold)
			if err != nil {
				return nil, err
			}
			defer rows.Close()

			var alerts []*Alert
			for rows.Next() {
				var serverID, serverName string
				var avgMem float64
				if err := rows.Scan(&serverID, &serverName, &avgMem); err != nil {
					continue
				}

				alerts = append(alerts, &Alert{
					ID:       fmt.Sprintf("server-mem-%s", serverID[:8]),
					Type:     AlertServerHighMemory,
					Severity: SeverityWarning,
					Title:    "High Server Memory",
					Message:  fmt.Sprintf("Server %s has %.1f%% memory usage", serverName, avgMem),
					Labels: map[string]string{
						"server_id":      serverID,
						"server_name":    serverName,
						"memory_percent": fmt.Sprintf("%.1f", avgMem),
					},
					StartsAt: time.Now(),
					Status:   "firing",
				})
			}

			return alerts, nil
		},
	}
}

// ServerHighDiskRule creates a rule for high server disk usage
func ServerHighDiskRule(threshold float64) *AlertRule {
	return &AlertRule{
		Name:     "ServerHighDisk",
		Type:     AlertServerHighDisk,
		Severity: SeverityWarning,
		Cooldown: 30 * time.Minute,
		Condition: func(ctx context.Context, db *database.DB) ([]*Alert, error) {
			query := `
				SELECT s.id, s.name, AVG(ru.disk_percent) as avg_disk
				FROM servers s
				JOIN resource_usages ru ON s.id = ru.server_id
				WHERE ru.created_at > NOW() - INTERVAL '10 minutes'
				AND s.status = 'online'
				GROUP BY s.id, s.name
				HAVING AVG(ru.disk_percent) > $1
			`

			rows, err := db.Pool().Query(ctx, query, threshold)
			if err != nil {
				return nil, err
			}
			defer rows.Close()

			var alerts []*Alert
			for rows.Next() {
				var serverID, serverName string
				var avgDisk float64
				if err := rows.Scan(&serverID, &serverName, &avgDisk); err != nil {
					continue
				}

				alerts = append(alerts, &Alert{
					ID:       fmt.Sprintf("server-disk-%s", serverID[:8]),
					Type:     AlertServerHighDisk,
					Severity: SeverityWarning,
					Title:    "High Server Disk Usage",
					Message:  fmt.Sprintf("Server %s has %.1f%% disk usage", serverName, avgDisk),
					Labels: map[string]string{
						"server_id":    serverID,
						"server_name":  serverName,
						"disk_percent": fmt.Sprintf("%.1f", avgDisk),
					},
					StartsAt: time.Now(),
					Status:   "firing",
				})
			}

			return alerts, nil
		},
	}
}

// DeploymentFailedRule creates a rule for failed deployments
func DeploymentFailedRule() *AlertRule {
	return &AlertRule{
		Name:     "DeploymentFailed",
		Type:     AlertDeploymentFailed,
		Severity: SeverityCritical,
		Cooldown: 1 * time.Minute,
		Condition: func(ctx context.Context, db *database.DB) ([]*Alert, error) {
			query := `
				SELECT d.id, d.commit_sha, a.name as app_name
				FROM deployments d
				JOIN applications a ON d.application_id = a.id
				WHERE d.status = 'failed'
				AND d.updated_at > NOW() - INTERVAL '5 minutes'
			`

			rows, err := db.Pool().Query(ctx, query)
			if err != nil {
				return nil, err
			}
			defer rows.Close()

			var alerts []*Alert
			for rows.Next() {
				var deployID, commitSha, appName string
				if err := rows.Scan(&deployID, &commitSha, &appName); err != nil {
					continue
				}

				alerts = append(alerts, &Alert{
					ID:       fmt.Sprintf("deploy-failed-%s", deployID[:8]),
					Type:     AlertDeploymentFailed,
					Severity: SeverityCritical,
					Title:    "Deployment Failed",
					Message:  fmt.Sprintf("Deployment for %s failed (commit: %s)", appName, commitSha[:7]),
					Labels: map[string]string{
						"deployment_id": deployID,
						"application":   appName,
						"commit":        commitSha,
					},
					StartsAt: time.Now(),
					Status:   "firing",
				})
			}

			return alerts, nil
		},
	}
}

// SSLExpiringRule creates a rule for expiring SSL certificates
func SSLExpiringRule(daysBeforeExpiry int) *AlertRule {
	return &AlertRule{
		Name:     "SSLExpiring",
		Type:     AlertSSLExpiring,
		Severity: SeverityWarning,
		Cooldown: 24 * time.Hour,
		Condition: func(ctx context.Context, db *database.DB) ([]*Alert, error) {
			query := `
				SELECT domain, ssl_expires_at
				FROM domains
				WHERE ssl_status = 'issued'
				AND ssl_expires_at < NOW() + INTERVAL '1 day' * $1
				AND ssl_expires_at > NOW()
			`

			rows, err := db.Pool().Query(ctx, query, daysBeforeExpiry)
			if err != nil {
				return nil, err
			}
			defer rows.Close()

			var alerts []*Alert
			for rows.Next() {
				var domain string
				var expiresAt time.Time
				if err := rows.Scan(&domain, &expiresAt); err != nil {
					continue
				}

				daysLeft := int(time.Until(expiresAt).Hours() / 24)

				alerts = append(alerts, &Alert{
					ID:       fmt.Sprintf("ssl-expiring-%s", uuid.New().String()[:8]),
					Type:     AlertSSLExpiring,
					Severity: SeverityWarning,
					Title:    "SSL Certificate Expiring",
					Message:  fmt.Sprintf("SSL certificate for %s expires in %d days", domain, daysLeft),
					Labels: map[string]string{
						"domain":     domain,
						"expires_at": expiresAt.Format(time.RFC3339),
						"days_left":  fmt.Sprintf("%d", daysLeft),
					},
					StartsAt: time.Now(),
					Status:   "firing",
				})
			}

			return alerts, nil
		},
	}
}
