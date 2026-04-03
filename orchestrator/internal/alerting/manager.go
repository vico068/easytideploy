package alerting

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"sync"
	"time"

	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/rs/zerolog/log"
)

// AlertSeverity represents the severity of an alert
type AlertSeverity string

const (
	SeverityInfo     AlertSeverity = "info"
	SeverityWarning  AlertSeverity = "warning"
	SeverityCritical AlertSeverity = "critical"
)

// AlertType represents the type of alert
type AlertType string

const (
	AlertContainerDown       AlertType = "container_down"
	AlertContainerHighCPU    AlertType = "container_high_cpu"
	AlertContainerHighMemory AlertType = "container_high_memory"
	AlertServerDown          AlertType = "server_down"
	AlertServerHighCPU       AlertType = "server_high_cpu"
	AlertServerHighMemory    AlertType = "server_high_memory"
	AlertServerHighDisk      AlertType = "server_high_disk"
	AlertDeploymentFailed    AlertType = "deployment_failed"
	AlertBuildFailed         AlertType = "build_failed"
	AlertSSLExpiring         AlertType = "ssl_expiring"
	AlertHealthCheckFailed   AlertType = "health_check_failed"
	AlertFailover            AlertType = "failover"
)

// Alert represents an alert
type Alert struct {
	ID          string            `json:"id"`
	Type        AlertType         `json:"type"`
	Severity    AlertSeverity     `json:"severity"`
	Title       string            `json:"title"`
	Message     string            `json:"message"`
	Labels      map[string]string `json:"labels"`
	Annotations map[string]string `json:"annotations"`
	StartsAt    time.Time         `json:"starts_at"`
	EndsAt      *time.Time        `json:"ends_at,omitempty"`
	Status      string            `json:"status"` // firing, resolved
}

// AlertChannel is the interface for alert destinations
type AlertChannel interface {
	Send(ctx context.Context, alert *Alert) error
	Name() string
}

// WebhookChannel sends alerts to a webhook
type WebhookChannel struct {
	name    string
	url     string
	headers map[string]string
	client  *http.Client
}

// NewWebhookChannel creates a new webhook channel
func NewWebhookChannel(name, url string, headers map[string]string) *WebhookChannel {
	return &WebhookChannel{
		name:    name,
		url:     url,
		headers: headers,
		client: &http.Client{
			Timeout: 10 * time.Second,
		},
	}
}

// Name returns the channel name
func (w *WebhookChannel) Name() string {
	return w.name
}

// Send sends an alert to the webhook
func (w *WebhookChannel) Send(ctx context.Context, alert *Alert) error {
	payload, err := json.Marshal(alert)
	if err != nil {
		return fmt.Errorf("failed to marshal alert: %w", err)
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, w.url, bytes.NewReader(payload))
	if err != nil {
		return fmt.Errorf("failed to create request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")
	for k, v := range w.headers {
		req.Header.Set(k, v)
	}

	resp, err := w.client.Do(req)
	if err != nil {
		return fmt.Errorf("failed to send alert: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode >= 400 {
		return fmt.Errorf("webhook returned status %d", resp.StatusCode)
	}

	return nil
}

// SlackChannel sends alerts to Slack
type SlackChannel struct {
	name       string
	webhookURL string
	channel    string
	client     *http.Client
}

// NewSlackChannel creates a new Slack channel
func NewSlackChannel(name, webhookURL, channel string) *SlackChannel {
	return &SlackChannel{
		name:       name,
		webhookURL: webhookURL,
		channel:    channel,
		client: &http.Client{
			Timeout: 10 * time.Second,
		},
	}
}

// Name returns the channel name
func (s *SlackChannel) Name() string {
	return s.name
}

// Send sends an alert to Slack
func (s *SlackChannel) Send(ctx context.Context, alert *Alert) error {
	color := "good"
	switch alert.Severity {
	case SeverityWarning:
		color = "warning"
	case SeverityCritical:
		color = "danger"
	}

	emoji := ":information_source:"
	switch alert.Severity {
	case SeverityWarning:
		emoji = ":warning:"
	case SeverityCritical:
		emoji = ":rotating_light:"
	}

	// Build fields from labels
	fields := make([]map[string]interface{}, 0)
	for k, v := range alert.Labels {
		fields = append(fields, map[string]interface{}{
			"title": k,
			"value": v,
			"short": true,
		})
	}

	payload := map[string]interface{}{
		"channel": s.channel,
		"attachments": []map[string]interface{}{
			{
				"color":   color,
				"pretext": fmt.Sprintf("%s *%s*", emoji, alert.Title),
				"text":    alert.Message,
				"fields":  fields,
				"footer":  "EasyDeploy Alerts",
				"ts":      alert.StartsAt.Unix(),
			},
		},
	}

	data, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("failed to marshal payload: %w", err)
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, s.webhookURL, bytes.NewReader(data))
	if err != nil {
		return fmt.Errorf("failed to create request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")

	resp, err := s.client.Do(req)
	if err != nil {
		return fmt.Errorf("failed to send alert: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode >= 400 {
		return fmt.Errorf("slack returned status %d", resp.StatusCode)
	}

	return nil
}

// AlertRule defines when to trigger an alert
type AlertRule struct {
	Name        string
	Type        AlertType
	Severity    AlertSeverity
	Condition   func(ctx context.Context, db *database.DB) ([]*Alert, error)
	Cooldown    time.Duration
	LastFired   map[string]time.Time
	mu          sync.Mutex
}

// ShouldFire checks if the rule should fire for a given key
func (r *AlertRule) ShouldFire(key string) bool {
	r.mu.Lock()
	defer r.mu.Unlock()

	if r.LastFired == nil {
		r.LastFired = make(map[string]time.Time)
	}

	lastFired, ok := r.LastFired[key]
	if !ok || time.Since(lastFired) >= r.Cooldown {
		r.LastFired[key] = time.Now()
		return true
	}
	return false
}

// AlertManager manages alerts and channels
type AlertManager struct {
	db       *database.DB
	channels []AlertChannel
	rules    []*AlertRule
	stopCh   chan struct{}
	wg       sync.WaitGroup
	mu       sync.RWMutex
}

// NewAlertManager creates a new alert manager
func NewAlertManager(db *database.DB) *AlertManager {
	return &AlertManager{
		db:       db,
		channels: make([]AlertChannel, 0),
		rules:    make([]*AlertRule, 0),
		stopCh:   make(chan struct{}),
	}
}

// AddChannel adds an alert channel
func (m *AlertManager) AddChannel(ch AlertChannel) {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.channels = append(m.channels, ch)
	log.Info().Str("channel", ch.Name()).Msg("Alert channel added")
}

// AddRule adds an alert rule
func (m *AlertManager) AddRule(rule *AlertRule) {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.rules = append(m.rules, rule)
	log.Info().Str("rule", rule.Name).Msg("Alert rule added")
}

// Start starts the alert manager
func (m *AlertManager) Start(checkInterval time.Duration) {
	m.wg.Add(1)
	go func() {
		defer m.wg.Done()
		ticker := time.NewTicker(checkInterval)
		defer ticker.Stop()

		for {
			select {
			case <-ticker.C:
				m.checkRules()
			case <-m.stopCh:
				return
			}
		}
	}()

	log.Info().Dur("interval", checkInterval).Msg("Alert manager started")
}

// Stop stops the alert manager
func (m *AlertManager) Stop() {
	close(m.stopCh)
	m.wg.Wait()
	log.Info().Msg("Alert manager stopped")
}

// checkRules evaluates all rules
func (m *AlertManager) checkRules() {
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	m.mu.RLock()
	rules := make([]*AlertRule, len(m.rules))
	copy(rules, m.rules)
	m.mu.RUnlock()

	for _, rule := range rules {
		alerts, err := rule.Condition(ctx, m.db)
		if err != nil {
			log.Error().Err(err).Str("rule", rule.Name).Msg("Failed to evaluate rule")
			continue
		}

		for _, alert := range alerts {
			key := fmt.Sprintf("%s:%s", alert.Type, alert.ID)
			if rule.ShouldFire(key) {
				m.sendAlert(ctx, alert)
			}
		}
	}
}

// sendAlert sends an alert to all channels
func (m *AlertManager) sendAlert(ctx context.Context, alert *Alert) {
	m.mu.RLock()
	channels := make([]AlertChannel, len(m.channels))
	copy(channels, m.channels)
	m.mu.RUnlock()

	for _, ch := range channels {
		if err := ch.Send(ctx, alert); err != nil {
			log.Error().Err(err).
				Str("channel", ch.Name()).
				Str("alert", alert.ID).
				Msg("Failed to send alert")
		} else {
			log.Info().
				Str("channel", ch.Name()).
				Str("alert", alert.ID).
				Str("type", string(alert.Type)).
				Msg("Alert sent")
		}
	}

	// Store alert in database
	m.storeAlert(ctx, alert)
}

// storeAlert stores an alert in the database
func (m *AlertManager) storeAlert(ctx context.Context, alert *Alert) {
	query := `
		INSERT INTO alerts (id, type, severity, title, message, labels, starts_at, status)
		VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
		ON CONFLICT (id) DO UPDATE SET
			status = EXCLUDED.status,
			ends_at = EXCLUDED.ends_at
	`

	labels, _ := json.Marshal(alert.Labels)

	_, err := m.db.Pool().Exec(ctx, query,
		alert.ID,
		alert.Type,
		alert.Severity,
		alert.Title,
		alert.Message,
		labels,
		alert.StartsAt,
		alert.Status,
	)
	if err != nil {
		log.Error().Err(err).Str("alert", alert.ID).Msg("Failed to store alert")
	}
}

// FireAlert manually fires an alert
func (m *AlertManager) FireAlert(ctx context.Context, alert *Alert) {
	alert.Status = "firing"
	if alert.StartsAt.IsZero() {
		alert.StartsAt = time.Now()
	}
	m.sendAlert(ctx, alert)
}

// ResolveAlert resolves an alert
func (m *AlertManager) ResolveAlert(ctx context.Context, alertID string) {
	now := time.Now()
	alert := &Alert{
		ID:     alertID,
		Status: "resolved",
		EndsAt: &now,
	}

	query := `UPDATE alerts SET status = 'resolved', ends_at = $1 WHERE id = $2`
	_, err := m.db.Pool().Exec(ctx, query, now, alertID)
	if err != nil {
		log.Error().Err(err).Str("alert", alertID).Msg("Failed to resolve alert")
		return
	}

	// Notify channels about resolution
	m.mu.RLock()
	channels := make([]AlertChannel, len(m.channels))
	copy(channels, m.channels)
	m.mu.RUnlock()

	for _, ch := range channels {
		ch.Send(ctx, alert)
	}
}
