package traefik

import (
	"context"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"github.com/easyti/easydeploy/orchestrator/internal/config"
	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/rs/zerolog/log"
	"gopkg.in/yaml.v3"
)

// Router represents a Traefik HTTP router
type Router struct {
	Rule        string   `yaml:"rule"`
	Service     string   `yaml:"service"`
	EntryPoints []string `yaml:"entryPoints"`
	TLS         *TLS     `yaml:"tls,omitempty"`
	Middlewares []string `yaml:"middlewares,omitempty"`
	Priority    int      `yaml:"priority,omitempty"`
}

// TLS represents TLS configuration
type TLS struct {
	CertResolver string `yaml:"certResolver,omitempty"`
}

// Service represents a Traefik service
type Service struct {
	LoadBalancer *LoadBalancer `yaml:"loadBalancer"`
}

// LoadBalancer represents a load balancer configuration
type LoadBalancer struct {
	Servers        []Server     `yaml:"servers"`
	HealthCheck    *HealthCheck `yaml:"healthCheck,omitempty"`
	PassHostHeader bool         `yaml:"passHostHeader"`
	Sticky         *Sticky      `yaml:"sticky,omitempty"`
}

// Server represents a backend server
type Server struct {
	URL string `yaml:"url"`
}

// HealthCheck represents a health check configuration
type HealthCheck struct {
	Path     string `yaml:"path"`
	Interval string `yaml:"interval"`
	Timeout  string `yaml:"timeout"`
}

// Sticky represents sticky session configuration
type Sticky struct {
	Cookie *Cookie `yaml:"cookie,omitempty"`
}

// Cookie represents sticky cookie configuration
type Cookie struct {
	Name     string `yaml:"name"`
	Secure   bool   `yaml:"secure"`
	HTTPOnly bool   `yaml:"httpOnly"`
}

// Middleware represents a Traefik middleware
type Middleware struct {
	StripPrefix    *StripPrefix    `yaml:"stripPrefix,omitempty"`
	Headers        *Headers        `yaml:"headers,omitempty"`
	RateLimit      *RateLimit      `yaml:"rateLimit,omitempty"`
	Retry          *Retry          `yaml:"retry,omitempty"`
	Compress       *Compress       `yaml:"compress,omitempty"`
	RedirectScheme *RedirectScheme `yaml:"redirectScheme,omitempty"`
}

// StripPrefix represents strip prefix middleware
type StripPrefix struct {
	Prefixes []string `yaml:"prefixes"`
}

// Headers represents headers middleware
type Headers struct {
	CustomRequestHeaders         map[string]string `yaml:"customRequestHeaders,omitempty"`
	CustomResponseHeaders        map[string]string `yaml:"customResponseHeaders,omitempty"`
	AccessControlAllowOriginList []string          `yaml:"accessControlAllowOriginList,omitempty"`
	AccessControlAllowMethods    []string          `yaml:"accessControlAllowMethods,omitempty"`
	AccessControlAllowHeaders    []string          `yaml:"accessControlAllowHeaders,omitempty"`
}

// RateLimit represents rate limiting middleware
type RateLimit struct {
	Average int `yaml:"average"`
	Burst   int `yaml:"burst"`
}

// Retry represents retry middleware
type Retry struct {
	Attempts int `yaml:"attempts"`
}

// Compress represents compression middleware
type Compress struct{}

// RedirectScheme represents redirect scheme middleware
type RedirectScheme struct {
	Scheme    string `yaml:"scheme"`
	Permanent bool   `yaml:"permanent"`
}

// DynamicConfig represents the complete Traefik dynamic configuration
type DynamicConfig struct {
	HTTP *HTTPConfig `yaml:"http"`
}

// HTTPConfig represents HTTP configuration
type HTTPConfig struct {
	Routers     map[string]*Router     `yaml:"routers,omitempty"`
	Services    map[string]*Service    `yaml:"services,omitempty"`
	Middlewares map[string]*Middleware `yaml:"middlewares,omitempty"`
}

// ConfigGenerator generates Traefik dynamic configurations
type ConfigGenerator struct {
	db        *database.DB
	cfg       *config.Config
	configDir string
	mu        sync.Mutex
}

// NewConfigGenerator creates a new ConfigGenerator
func NewConfigGenerator(db *database.DB, cfg *config.Config) *ConfigGenerator {
	return &ConfigGenerator{
		db:        db,
		cfg:       cfg,
		configDir: cfg.TraefikConfigDir,
	}
}

// GenerateConfig generates Traefik configuration for an application
func (g *ConfigGenerator) GenerateConfig(ctx context.Context, applicationID string) error {
	g.mu.Lock()
	defer g.mu.Unlock()

	// Get application details
	app, err := g.getApplication(ctx, applicationID)
	if err != nil {
		return fmt.Errorf("failed to get application: %w", err)
	}

	// Get domains for application
	domains, err := g.getDomains(ctx, applicationID)
	if err != nil {
		return fmt.Errorf("failed to get domains: %w", err)
	}

	// Get running containers
	containers, err := g.getRunningContainers(ctx, applicationID)
	if err != nil {
		return fmt.Errorf("failed to get containers: %w", err)
	}

	if len(containers) == 0 {
		log.Warn().Str("app", applicationID).Msg("No running containers found")
		return nil
	}

	// Generate configuration
	config := g.buildConfig(app, domains, containers)

	// Write configuration file
	if err := g.writeConfig(applicationID, config); err != nil {
		return err
	}

	// Update SSL status for domains configured with TLS
	if app.SSLEnabled {
		g.updateDomainSSLStatus(ctx, applicationID)
	}

	return nil
}

// Application represents an application from the database
type Application struct {
	ID              string
	Name            string
	Slug            string
	Port            int
	HealthCheckPath string
	SSLEnabled      bool
}

// Domain represents a domain from the database
type Domain struct {
	Domain    string
	IsPrimary bool
	SSLStatus string
}

// Container represents a container from the database
type Container struct {
	ID           string
	DockerID     string
	ServerID     string
	ServerIP     string
	HostPort     int
	InternalPort int
}

func (g *ConfigGenerator) getApplication(ctx context.Context, applicationID string) (*Application, error) {
	query := `
		SELECT id, name, slug, port, health_check_path, ssl_enabled
		FROM applications
		WHERE id = $1
	`

	var app Application
	err := g.db.Pool().QueryRow(ctx, query, applicationID).Scan(
		&app.ID, &app.Name, &app.Slug, &app.Port, &app.HealthCheckPath, &app.SSLEnabled,
	)
	return &app, err
}

func (g *ConfigGenerator) getDomains(ctx context.Context, applicationID string) ([]*Domain, error) {
	query := `
		SELECT domain, is_primary, ssl_status
		FROM domains
		WHERE application_id = $1 AND verified = true
		ORDER BY is_primary DESC
	`

	rows, err := g.db.Pool().Query(ctx, query, applicationID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var domains []*Domain
	for rows.Next() {
		var d Domain
		if err := rows.Scan(&d.Domain, &d.IsPrimary, &d.SSLStatus); err != nil {
			continue
		}
		domains = append(domains, &d)
	}

	return domains, nil
}

func (g *ConfigGenerator) getRunningContainers(ctx context.Context, applicationID string) ([]*Container, error) {
	query := `
		SELECT c.id, c.docker_container_id, c.server_id, host(s.ip_address), COALESCE(c.host_port, 0), COALESCE(c.internal_port, 0)
		FROM containers c
		JOIN servers s ON c.server_id = s.id
		WHERE c.application_id = $1 AND c.status = 'running' AND c.health_status = 'healthy'
	`

	rows, err := g.db.Pool().Query(ctx, query, applicationID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var containers []*Container
	for rows.Next() {
		var c Container
		if err := rows.Scan(&c.ID, &c.DockerID, &c.ServerID, &c.ServerIP, &c.HostPort, &c.InternalPort); err != nil {
			log.Error().Err(err).Msg("Failed to scan container row")
			continue
		}
		containers = append(containers, &c)
	}

	return containers, nil
}

func (g *ConfigGenerator) buildConfig(app *Application, domains []*Domain, containers []*Container) *DynamicConfig {
	config := &DynamicConfig{
		HTTP: &HTTPConfig{
			Routers:     make(map[string]*Router),
			Services:    make(map[string]*Service),
			Middlewares: make(map[string]*Middleware),
		},
	}

	serviceName := fmt.Sprintf("svc-%s", app.Slug)

	// Build servers list from containers
	servers := make([]Server, 0, len(containers))
	for _, c := range containers {
		port := c.HostPort
		if port == 0 {
			port = c.InternalPort
		}
		servers = append(servers, Server{
			URL: fmt.Sprintf("http://%s:%d", c.ServerIP, port),
		})
	}

	// Create service with load balancer
	config.HTTP.Services[serviceName] = &Service{
		LoadBalancer: &LoadBalancer{
			Servers:        servers,
			PassHostHeader: true,
		},
	}

	// Add common middlewares
	compressMiddleware := fmt.Sprintf("%s-compress", app.Slug)
	config.HTTP.Middlewares[compressMiddleware] = &Middleware{
		Compress: &Compress{},
	}

	headersMiddleware := fmt.Sprintf("%s-headers", app.Slug)
	config.HTTP.Middlewares[headersMiddleware] = &Middleware{
		Headers: &Headers{
			CustomResponseHeaders: map[string]string{
				"X-Frame-Options":        "SAMEORIGIN",
				"X-Content-Type-Options": "nosniff",
				"X-XSS-Protection":       "1; mode=block",
			},
		},
	}

	middlewares := []string{compressMiddleware, headersMiddleware}

	// Create routers for each domain
	for i, domain := range domains {
		routerName := fmt.Sprintf("rt-%s-%d", app.Slug, i)
		rule := fmt.Sprintf("Host(`%s`)", domain.Domain)

		var tls *TLS
		if app.SSLEnabled || domain.SSLStatus == "issued" {
			tls = &TLS{
				CertResolver: "letsencrypt",
			}

			// Add HTTPS redirect for HTTP router
			httpRouterName := fmt.Sprintf("rt-%s-%d-http", app.Slug, i)
			redirectMiddleware := fmt.Sprintf("%s-redirect", app.Slug)

			config.HTTP.Middlewares[redirectMiddleware] = &Middleware{
				RedirectScheme: &RedirectScheme{
					Scheme:    "https",
					Permanent: true,
				},
			}

			config.HTTP.Routers[httpRouterName] = &Router{
				Rule:        rule,
				Service:     serviceName,
				EntryPoints: []string{"web"},
				Middlewares: []string{redirectMiddleware},
				Priority:    1,
			}
		}

		config.HTTP.Routers[routerName] = &Router{
			Rule:        rule,
			Service:     serviceName,
			EntryPoints: []string{"websecure"},
			TLS:         tls,
			Middlewares: middlewares,
			Priority:    10,
		}
	}

	// If no domains, create router with slug-based subdomain
	if len(domains) == 0 {
		routerName := fmt.Sprintf("rt-%s-default", app.Slug)
		rule := fmt.Sprintf("Host(`%s.apps.easyti.cloud`)", app.Slug)

		config.HTTP.Routers[routerName] = &Router{
			Rule:        rule,
			Service:     serviceName,
			EntryPoints: []string{"websecure"},
			TLS: &TLS{
				CertResolver: "letsencrypt",
			},
			Middlewares: middlewares,
		}
	}

	return config
}

func (g *ConfigGenerator) writeConfig(applicationID string, config *DynamicConfig) error {
	// Ensure config directory exists
	if err := os.MkdirAll(g.configDir, 0755); err != nil {
		return fmt.Errorf("failed to create config directory: %w", err)
	}

	// Write configuration file in YAML (Traefik file provider doesn't support JSON in directory mode)
	filename := filepath.Join(g.configDir, fmt.Sprintf("app-%s.yml", applicationID))

	data, err := yaml.Marshal(config)
	if err != nil {
		return fmt.Errorf("failed to marshal config: %w", err)
	}

	if err := os.WriteFile(filename, data, 0644); err != nil {
		return fmt.Errorf("failed to write config file: %w", err)
	}

	// Remove old JSON config if it exists
	oldJSON := filepath.Join(g.configDir, fmt.Sprintf("app-%s.json", applicationID))
	os.Remove(oldJSON)

	log.Info().Str("file", filename).Msg("Traefik configuration updated")
	return nil
}

// RemoveConfig removes Traefik configuration for an application
func (g *ConfigGenerator) RemoveConfig(applicationID string) error {
	g.mu.Lock()
	defer g.mu.Unlock()

	filename := filepath.Join(g.configDir, fmt.Sprintf("app-%s.yml", applicationID))

	if err := os.Remove(filename); err != nil && !os.IsNotExist(err) {
		return fmt.Errorf("failed to remove config file: %w", err)
	}

	// Also remove old JSON config if it exists
	oldJSON := filepath.Join(g.configDir, fmt.Sprintf("app-%s.json", applicationID))
	os.Remove(oldJSON)

	log.Info().Str("file", filename).Msg("Traefik configuration removed")
	return nil
}

// updateDomainSSLStatus marks domains as active when Traefik config with TLS is generated
func (g *ConfigGenerator) updateDomainSSLStatus(ctx context.Context, applicationID string) {
	query := `
		UPDATE domains
		SET ssl_status = 'active', ssl_enabled = true
		WHERE application_id = $1 AND verified = true AND ssl_status != 'active'
	`
	if _, err := g.db.Pool().Exec(ctx, query, applicationID); err != nil {
		log.Error().Err(err).Str("app", applicationID).Msg("Failed to update domain SSL status")
	}
}

// RefreshAllConfigs regenerates configurations for all active applications
func (g *ConfigGenerator) RefreshAllConfigs(ctx context.Context) error {
	query := `SELECT id FROM applications WHERE status = 'active'`

	rows, err := g.db.Pool().Query(ctx, query)
	if err != nil {
		return err
	}
	defer rows.Close()

	var applicationIDs []string
	for rows.Next() {
		var id string
		if err := rows.Scan(&id); err != nil {
			continue
		}
		applicationIDs = append(applicationIDs, id)
	}

	for _, appID := range applicationIDs {
		if err := g.GenerateConfig(ctx, appID); err != nil {
			log.Error().Err(err).Str("app", appID).Msg("Failed to generate config")
		}
	}

	return nil
}

// WatchChanges starts watching for configuration changes
func (g *ConfigGenerator) WatchChanges(ctx context.Context, interval time.Duration) {
	ticker := time.NewTicker(interval)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			// Get applications with pending config updates
			query := `
				SELECT DISTINCT a.id
				FROM applications a
				JOIN containers c ON a.id = c.application_id
				WHERE c.updated_at > a.traefik_config_updated_at
				   OR a.traefik_config_updated_at IS NULL
			`

			rows, err := g.db.Pool().Query(ctx, query)
			if err != nil {
				log.Error().Err(err).Msg("Failed to query pending config updates")
				continue
			}

			var pendingApps []string
			for rows.Next() {
				var id string
				if err := rows.Scan(&id); err == nil {
					pendingApps = append(pendingApps, id)
				}
			}
			rows.Close()

			for _, appID := range pendingApps {
				if err := g.GenerateConfig(ctx, appID); err != nil {
					log.Error().Err(err).Str("app", appID).Msg("Failed to update config")
					continue
				}

				// Update timestamp
				updateQuery := `UPDATE applications SET traefik_config_updated_at = NOW() WHERE id = $1`
				g.db.Pool().Exec(ctx, updateQuery, appID)
			}
		}
	}
}

// GenerateYAMLConfig generates Traefik configuration in YAML format
func (g *ConfigGenerator) GenerateYAMLConfig(ctx context.Context, applicationID string) (string, error) {
	app, err := g.getApplication(ctx, applicationID)
	if err != nil {
		return "", err
	}

	domains, err := g.getDomains(ctx, applicationID)
	if err != nil {
		return "", err
	}

	containers, err := g.getRunningContainers(ctx, applicationID)
	if err != nil {
		return "", err
	}

	var yamlBuilder strings.Builder
	yamlBuilder.WriteString("http:\n")
	yamlBuilder.WriteString("  routers:\n")

	serviceName := fmt.Sprintf("svc-%s", app.Slug)

	// Generate routers
	for i, domain := range domains {
		routerName := fmt.Sprintf("rt-%s-%d", app.Slug, i)
		yamlBuilder.WriteString(fmt.Sprintf("    %s:\n", routerName))
		yamlBuilder.WriteString(fmt.Sprintf("      rule: \"Host(`%s`)\"\n", domain.Domain))
		yamlBuilder.WriteString(fmt.Sprintf("      service: %s\n", serviceName))
		yamlBuilder.WriteString("      entryPoints:\n")
		yamlBuilder.WriteString("        - websecure\n")
		if app.SSLEnabled {
			yamlBuilder.WriteString("      tls:\n")
			yamlBuilder.WriteString("        certResolver: letsencrypt\n")
		}
	}

	// Generate services
	yamlBuilder.WriteString("  services:\n")
	yamlBuilder.WriteString(fmt.Sprintf("    %s:\n", serviceName))
	yamlBuilder.WriteString("      loadBalancer:\n")
	yamlBuilder.WriteString("        servers:\n")
	for _, c := range containers {
		port := c.HostPort
		if port == 0 {
			port = c.InternalPort
		}
		yamlBuilder.WriteString(fmt.Sprintf("          - url: \"http://%s:%d\"\n", c.ServerIP, port))
	}

	return yamlBuilder.String(), nil
}

// DeleteConfig removes the Traefik configuration file for an application
func (g *ConfigGenerator) DeleteConfig(ctx context.Context, applicationID string) error {
	g.mu.Lock()
	defer g.mu.Unlock()

	// Get application to determine slug
	app, err := g.getApplication(ctx, applicationID)
	if err != nil {
		// If application not found, config may already be deleted
		log.Warn().Str("app_id", applicationID).Msg("Application not found, config may already be deleted")
		return nil
	}

	// Delete configuration file
	filename := fmt.Sprintf("app-%s.yml", applicationID)
	configPath := filepath.Join(g.cfg.TraefikConfigDir, filename)

	if err := os.Remove(configPath); err != nil {
		if os.IsNotExist(err) {
			// Already deleted, not an error
			log.Info().Str("file", filename).Msg("Config file already deleted")
			return nil
		}
		return fmt.Errorf("failed to delete config file: %w", err)
	}

	log.Info().
		Str("app_id", applicationID).
		Str("app_name", app.Name).
		Str("file", filename).
		Msg("Traefik configuration deleted")

	return nil
}
