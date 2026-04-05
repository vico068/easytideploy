package scheduler

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"time"
)

// PanelApplication represents an application fetched from the panel API
// with decrypted secrets (git_token) and environment variables.
type PanelApplication struct {
	ID             string            `json:"id"`
	Name           string            `json:"name"`
	Slug           string            `json:"slug"`
	GitRepository  string            `json:"git_repository"`
	GitBranch      string            `json:"git_branch"`
	GitToken       string            `json:"git_token"`
	RootDirectory  string            `json:"root_directory"`
	Type           string            `json:"type"`
	RuntimeVersion string            `json:"runtime_version"`
	BuildCommand   string            `json:"build_command"`
	StartCommand   string            `json:"start_command"`
	Port           int               `json:"port"`
	Replicas       int               `json:"replicas"`
	CPULimit       int               `json:"cpu_limit"`
	MemoryLimit    int               `json:"memory_limit"`
	HealthCheck    map[string]any    `json:"health_check"`
	AutoDeploy     bool              `json:"auto_deploy"`
	SSLEnabled     bool              `json:"ssl_enabled"`
	Environment    map[string]string `json:"environment"`
}

// fetchAppFromPanel calls the panel internal API to get application details
// with decrypted git_token and resolved environment variables.
func (s *Scheduler) fetchAppFromPanel(ctx context.Context, applicationID string) (*PanelApplication, error) {
	url := fmt.Sprintf("%s/api/internal/applications/%s", s.cfg.PanelURL, applicationID)

	req, err := http.NewRequestWithContext(ctx, http.MethodGet, url, nil)
	if err != nil {
		return nil, fmt.Errorf("failed to create request: %w", err)
	}
	req.Header.Set("Authorization", "Bearer "+s.cfg.APIKey)
	req.Header.Set("Accept", "application/json")

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return nil, fmt.Errorf("failed to fetch app from panel: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("panel returned status %d for app %s", resp.StatusCode, applicationID)
	}

	var app PanelApplication
	if err := json.NewDecoder(resp.Body).Decode(&app); err != nil {
		return nil, fmt.Errorf("failed to decode panel response: %w", err)
	}

	return &app, nil
}
