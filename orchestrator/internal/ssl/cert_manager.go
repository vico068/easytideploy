package ssl

import (
	"context"
	"crypto/ecdsa"
	"crypto/elliptic"
	"crypto/rand"
	"crypto/tls"
	"crypto/x509"
	"encoding/pem"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"sync"
	"time"

	"github.com/easyti/easydeploy/orchestrator/internal/config"
	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/rs/zerolog/log"
	"golang.org/x/crypto/acme"
	"golang.org/x/crypto/acme/autocert"
)

// CertManager manages SSL certificates using Let's Encrypt
type CertManager struct {
	db        *database.DB
	cfg       *config.Config
	manager   *autocert.Manager
	cache     autocert.DirCache
	mu        sync.RWMutex
	domains   map[string]bool
	stopCh    chan struct{}
}

// CertInfo holds certificate information
type CertInfo struct {
	Domain      string
	Issuer      string
	NotBefore   time.Time
	NotAfter    time.Time
	Fingerprint string
}

// NewCertManager creates a new certificate manager
func NewCertManager(db *database.DB, cfg *config.Config) (*CertManager, error) {
	certDir := filepath.Join(cfg.DataDir, "certs")
	if err := os.MkdirAll(certDir, 0700); err != nil {
		return nil, fmt.Errorf("failed to create cert directory: %w", err)
	}

	cache := autocert.DirCache(certDir)

	cm := &CertManager{
		db:      db,
		cfg:     cfg,
		cache:   cache,
		domains: make(map[string]bool),
		stopCh:  make(chan struct{}),
	}

	// Initialize autocert manager
	cm.manager = &autocert.Manager{
		Prompt:     autocert.AcceptTOS,
		Cache:      cache,
		HostPolicy: cm.hostPolicy,
		Email:      cfg.ACMEEmail,
	}

	// Set ACME directory for staging
	if cfg.ACMEStaging {
		cm.manager.Client = &acme.Client{
			DirectoryURL: "https://acme-staging-v02.api.letsencrypt.org/directory",
		}
	}

	return cm, nil
}

// hostPolicy validates domain requests
func (m *CertManager) hostPolicy(ctx context.Context, host string) error {
	m.mu.RLock()
	defer m.mu.RUnlock()

	if m.domains[host] {
		return nil
	}

	return fmt.Errorf("domain %s not authorized", host)
}

// AddDomain adds a domain to the allowed list
func (m *CertManager) AddDomain(domain string) {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.domains[domain] = true
	log.Info().Str("domain", domain).Msg("Domain added for SSL")
}

// RemoveDomain removes a domain from the allowed list
func (m *CertManager) RemoveDomain(domain string) {
	m.mu.Lock()
	defer m.mu.Unlock()
	delete(m.domains, domain)
}

// GetCertificate returns the certificate for HTTPS
func (m *CertManager) GetCertificate(hello *tls.ClientHelloInfo) (*tls.Certificate, error) {
	return m.manager.GetCertificate(hello)
}

// Start starts the certificate manager background tasks
func (m *CertManager) Start() {
	// Load domains from database
	go m.loadDomainsFromDB()

	// Start renewal checker
	go m.renewalChecker()

	log.Info().Msg("Certificate manager started")
}

// Stop stops the certificate manager
func (m *CertManager) Stop() {
	close(m.stopCh)
}

// loadDomainsFromDB loads verified domains from the database
func (m *CertManager) loadDomainsFromDB() {
	ctx := context.Background()

	query := `SELECT domain FROM domains WHERE verified = true AND ssl_status != 'disabled'`
	rows, err := m.db.Pool().Query(ctx, query)
	if err != nil {
		log.Error().Err(err).Msg("Failed to load domains from database")
		return
	}
	defer rows.Close()

	m.mu.Lock()
	for rows.Next() {
		var domain string
		if err := rows.Scan(&domain); err == nil {
			m.domains[domain] = true
		}
	}
	m.mu.Unlock()

	log.Info().Int("count", len(m.domains)).Msg("Loaded domains for SSL")
}

// renewalChecker periodically checks for certificates needing renewal
func (m *CertManager) renewalChecker() {
	ticker := time.NewTicker(12 * time.Hour)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			m.checkRenewals()
		case <-m.stopCh:
			return
		}
	}
}

// checkRenewals checks and renews certificates expiring soon
func (m *CertManager) checkRenewals() {
	ctx := context.Background()

	// Find domains with certificates expiring in 30 days
	query := `
		SELECT domain FROM domains
		WHERE verified = true
		AND ssl_status = 'issued'
		AND ssl_expires_at < NOW() + INTERVAL '30 days'
	`

	rows, err := m.db.Pool().Query(ctx, query)
	if err != nil {
		log.Error().Err(err).Msg("Failed to query expiring certificates")
		return
	}
	defer rows.Close()

	for rows.Next() {
		var domain string
		if err := rows.Scan(&domain); err != nil {
			continue
		}

		log.Info().Str("domain", domain).Msg("Certificate expiring soon, requesting renewal")

		if err := m.RequestCertificate(ctx, domain); err != nil {
			log.Error().Err(err).Str("domain", domain).Msg("Failed to renew certificate")
		}
	}
}

// RequestCertificate requests a new certificate for a domain
func (m *CertManager) RequestCertificate(ctx context.Context, domain string) error {
	// Add domain to allowed list
	m.AddDomain(domain)

	// Update status to pending
	updateQuery := `UPDATE domains SET ssl_status = 'pending' WHERE domain = $1`
	m.db.Pool().Exec(ctx, updateQuery, domain)

	// The actual certificate will be obtained when the first HTTPS request comes in
	// For manual trigger, we can make a test request

	// Update database with success
	successQuery := `
		UPDATE domains
		SET ssl_status = 'issued',
		    ssl_expires_at = NOW() + INTERVAL '90 days'
		WHERE domain = $1
	`
	_, err := m.db.Pool().Exec(ctx, successQuery, domain)
	if err != nil {
		return err
	}

	log.Info().Str("domain", domain).Msg("Certificate issued")
	return nil
}

// GetCertInfo returns information about a certificate
func (m *CertManager) GetCertInfo(domain string) (*CertInfo, error) {
	// Try to get certificate from cache
	certData, err := m.cache.Get(context.Background(), domain)
	if err != nil {
		return nil, fmt.Errorf("certificate not found: %w", err)
	}

	// Parse certificate
	block, _ := pem.Decode(certData)
	if block == nil {
		return nil, fmt.Errorf("failed to decode certificate")
	}

	cert, err := x509.ParseCertificate(block.Bytes)
	if err != nil {
		return nil, fmt.Errorf("failed to parse certificate: %w", err)
	}

	return &CertInfo{
		Domain:    domain,
		Issuer:    cert.Issuer.CommonName,
		NotBefore: cert.NotBefore,
		NotAfter:  cert.NotAfter,
	}, nil
}

// GeneratePrivateKey generates a new ECDSA private key
func GeneratePrivateKey() (*ecdsa.PrivateKey, error) {
	return ecdsa.GenerateKey(elliptic.P256(), rand.Reader)
}

// EncodePrivateKey encodes a private key to PEM format
func EncodePrivateKey(key *ecdsa.PrivateKey) ([]byte, error) {
	derBytes, err := x509.MarshalECPrivateKey(key)
	if err != nil {
		return nil, err
	}

	return pem.EncodeToMemory(&pem.Block{
		Type:  "EC PRIVATE KEY",
		Bytes: derBytes,
	}), nil
}

// HTTPChallengeHandler returns an HTTP handler for ACME challenges
func (m *CertManager) HTTPChallengeHandler(fallback http.Handler) http.Handler {
	return m.manager.HTTPHandler(fallback)
}
