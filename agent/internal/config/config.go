package config

import (
	"os"
	"strconv"
)

// Config holds the agent configuration
type Config struct {
	// Server identification
	ServerID   string
	ServerName string
	Version    string

	// Network
	GRPCAddress  string
	HTTPAddress  string

	// Orchestrator connection
	OrchestratorURL    string
	OrchestratorAPIKey string

	// Docker
	DockerHost string

	// Heartbeat
	HeartbeatInterval int // seconds
	HeartbeatTimeout  int // seconds

	// Resource limits
	MaxContainers int

	// Logging
	LogLevel string
	LogJSON  bool

	// Metrics
	MetricsEnabled  bool
	MetricsInterval int // seconds

	// Security
	TLSEnabled  bool
	TLSCertFile string
	TLSKeyFile  string
}

// Load loads configuration from environment variables
func Load() (*Config, error) {
	hostname, _ := os.Hostname()

	cfg := &Config{
		// Server identification
		ServerID:   getEnv("SERVER_ID", hostname),
		ServerName: getEnv("SERVER_NAME", hostname),
		Version:    getEnv("VERSION", "1.0.0"),

		// Network
		GRPCAddress: getEnv("GRPC_ADDRESS", ":9090"),
		HTTPAddress: getEnv("HTTP_ADDRESS", ":9091"),

		// Orchestrator
		OrchestratorURL:    getEnv("ORCHESTRATOR_URL", "http://localhost:8080"),
		OrchestratorAPIKey: getEnv("ORCHESTRATOR_API_KEY", ""),

		// Docker
		DockerHost: getEnv("DOCKER_HOST", "unix:///var/run/docker.sock"),

		// Heartbeat
		HeartbeatInterval: getEnvInt("HEARTBEAT_INTERVAL", 30),
		HeartbeatTimeout:  getEnvInt("HEARTBEAT_TIMEOUT", 10),

		// Resource limits
		MaxContainers: getEnvInt("MAX_CONTAINERS", 50),

		// Logging
		LogLevel: getEnv("LOG_LEVEL", "info"),
		LogJSON:  getEnvBool("LOG_JSON", false),

		// Metrics
		MetricsEnabled:  getEnvBool("METRICS_ENABLED", true),
		MetricsInterval: getEnvInt("METRICS_INTERVAL", 60),

		// Security
		TLSEnabled:  getEnvBool("TLS_ENABLED", false),
		TLSCertFile: getEnv("TLS_CERT_FILE", ""),
		TLSKeyFile:  getEnv("TLS_KEY_FILE", ""),
	}

	return cfg, nil
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
