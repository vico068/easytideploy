package grpc

import (
	"crypto/tls"
	"crypto/x509"
	"fmt"
	"os"

	"google.golang.org/grpc/credentials"
)

// TLSConfig holds TLS configuration
type TLSConfig struct {
	CertFile   string
	KeyFile    string
	CAFile     string
	ServerName string
	Insecure   bool
}

// LoadServerTLS loads TLS configuration for a gRPC server
func LoadServerTLS(cfg *TLSConfig) (credentials.TransportCredentials, error) {
	if cfg.Insecure || (cfg.CertFile == "" && cfg.KeyFile == "") {
		return nil, nil
	}

	cert, err := tls.LoadX509KeyPair(cfg.CertFile, cfg.KeyFile)
	if err != nil {
		return nil, fmt.Errorf("failed to load server certificate: %w", err)
	}

	tlsConfig := &tls.Config{
		Certificates: []tls.Certificate{cert},
		MinVersion:   tls.VersionTLS12,
	}

	// Load CA certificate for client verification (mTLS)
	if cfg.CAFile != "" {
		caCert, err := os.ReadFile(cfg.CAFile)
		if err != nil {
			return nil, fmt.Errorf("failed to load CA certificate: %w", err)
		}

		caCertPool := x509.NewCertPool()
		if !caCertPool.AppendCertsFromPEM(caCert) {
			return nil, fmt.Errorf("failed to parse CA certificate")
		}

		tlsConfig.ClientCAs = caCertPool
		tlsConfig.ClientAuth = tls.RequireAndVerifyClientCert
	}

	return credentials.NewTLS(tlsConfig), nil
}

// LoadClientTLS loads TLS configuration for a gRPC client
func LoadClientTLS(cfg *TLSConfig) (credentials.TransportCredentials, error) {
	if cfg.Insecure {
		return nil, nil
	}

	tlsConfig := &tls.Config{
		MinVersion: tls.VersionTLS12,
	}

	// Load client certificate
	if cfg.CertFile != "" && cfg.KeyFile != "" {
		cert, err := tls.LoadX509KeyPair(cfg.CertFile, cfg.KeyFile)
		if err != nil {
			return nil, fmt.Errorf("failed to load client certificate: %w", err)
		}
		tlsConfig.Certificates = []tls.Certificate{cert}
	}

	// Load CA certificate
	if cfg.CAFile != "" {
		caCert, err := os.ReadFile(cfg.CAFile)
		if err != nil {
			return nil, fmt.Errorf("failed to load CA certificate: %w", err)
		}

		caCertPool := x509.NewCertPool()
		if !caCertPool.AppendCertsFromPEM(caCert) {
			return nil, fmt.Errorf("failed to parse CA certificate")
		}

		tlsConfig.RootCAs = caCertPool
	}

	if cfg.ServerName != "" {
		tlsConfig.ServerName = cfg.ServerName
	}

	return credentials.NewTLS(tlsConfig), nil
}

// GenerateSelfSignedCert generates a self-signed certificate for testing
// In production, use Let's Encrypt or a proper CA
func GenerateSelfSignedCert(certFile, keyFile, hostname string) error {
	// This would use crypto/x509 to generate a self-signed cert
	// For now, this is a placeholder - in production use tools like:
	// - openssl
	// - cfssl
	// - Let's Encrypt/ACME
	return nil
}
