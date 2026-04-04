package config

import (
	"os"
	"strconv"
)

type Config struct {
	// Server
	ListenAddr string
	APIKey     string

	// Database
	DatabaseURL string

	// Redis
	RedisURL string

	// Traefik
	TraefikAPIURL    string
	TraefikConfigDir string

	// Docker Registry
	DockerRegistry     string
	DockerRegistryUser string
	DockerRegistryPass string
	// AgentRegistry is the registry address as seen by agents (may differ from DockerRegistry
	// when the registry binds to localhost but agents are on separate hosts).
	AgentRegistry string

	// Build
	BuildTimeout int // seconds
	MaxRetries   int
	DataDir      string

	// Health checks
	HealthCheckInterval      int // seconds
	HealthCheckFailThreshold int // consecutive failures before marking unhealthy
	FailoverThreshold        int // consecutive failures before triggering failover

	// ACME/Let's Encrypt
	ACMEEnabled bool
	ACMEEmail   string
	ACMEStaging bool
}

func Load() (*Config, error) {
	cfg := &Config{
		ListenAddr:               getEnv("LISTEN_ADDR", ":8080"),
		APIKey:                   getEnv("API_KEY", ""),
		DatabaseURL:              getEnv("DATABASE_URL", "postgres://easydeploy:easydeploy_dev@localhost:5432/easydeploy"),
		RedisURL:                 getEnv("REDIS_URL", "redis://localhost:6379"),
		TraefikAPIURL:            getEnv("TRAEFIK_API_URL", "http://localhost:8081"),
		TraefikConfigDir:         getEnv("TRAEFIK_CONFIG_DIR", "/etc/traefik/dynamic"),
		DockerRegistry:           getEnv("DOCKER_REGISTRY", "registry.easyti.cloud"),
		DockerRegistryUser:       getEnv("DOCKER_REGISTRY_USER", ""),
		DockerRegistryPass:       getEnv("DOCKER_REGISTRY_PASS", ""),
		AgentRegistry:            getEnv("AGENT_REGISTRY", ""),
		BuildTimeout:             getEnvInt("BUILD_TIMEOUT", 600),
		MaxRetries:               getEnvInt("MAX_RETRIES", 3),
		DataDir:                  getEnv("DATA_DIR", "/var/lib/easydeploy"),
		HealthCheckInterval:      getEnvInt("HEALTH_CHECK_INTERVAL", 30),
		HealthCheckFailThreshold: getEnvInt("HEALTH_CHECK_FAIL_THRESHOLD", 3),
		FailoverThreshold:        getEnvInt("FAILOVER_THRESHOLD", 5),
		ACMEEnabled:              getEnvBool("ACME_ENABLED", true),
		ACMEEmail:                getEnv("ACME_EMAIL", "admin@easyti.cloud"),
		ACMEStaging:              getEnvBool("ACME_STAGING", false),
	}

	return cfg, nil
}

// AgentRegistryAddr returns the registry address that agents should use to pull images.
// Falls back to DockerRegistry if AgentRegistry is not configured.
func (c *Config) AgentRegistryAddr() string {
	if c.AgentRegistry != "" {
		return c.AgentRegistry
	}
	return c.DockerRegistry
}

func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}

func getEnvInt(key string, defaultValue int) int {
	if value := os.Getenv(key); value != "" {
		if intValue, err := strconv.Atoi(value); err == nil {
			return intValue
		}
	}
	return defaultValue
}

func getEnvBool(key string, defaultValue bool) bool {
	if value := os.Getenv(key); value != "" {
		if boolValue, err := strconv.ParseBool(value); err == nil {
			return boolValue
		}
	}
	return defaultValue
}
