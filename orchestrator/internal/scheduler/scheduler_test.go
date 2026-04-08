package scheduler

import (
	"testing"

	"github.com/easyti/easydeploy/orchestrator/internal/config"
)

func TestDeploymentContainerName(t *testing.T) {
	s := &Scheduler{}

	tests := []struct {
		name         string
		appID        string
		deploymentID string
		replica      int
		rolling      bool
		expected     string
	}{
		{
			name:         "first deploy uses final replica name",
			appID:        "app-123",
			deploymentID: "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee",
			replica:      1,
			rolling:      false,
			expected:     "app-123-replica-1",
		},
		{
			name:         "rolling deploy uses short staged suffix",
			appID:        "app-123",
			deploymentID: "a2748f46-cdd0-4de8-87bc-5a5e0c6d2333",
			replica:      2,
			rolling:      true,
			expected:     "app-123-replica-2_deploy_a2748f46",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			actual := s.deploymentContainerName(tt.appID, tt.deploymentID, tt.replica, tt.rolling)
			if actual != tt.expected {
				t.Fatalf("unexpected name: got %q want %q", actual, tt.expected)
			}
		})
	}
}

func TestResolveCallbackURL(t *testing.T) {
	s := &Scheduler{
		cfg: &config.Config{PanelURL: "http://panel:8000"},
	}

	tests := []struct {
		name     string
		input    string
		expected string
	}{
		{
			name:     "keeps non-local callback URL",
			input:    "https://deploy.easyti.cloud/api/internal/deployments/123/status",
			expected: "https://deploy.easyti.cloud/api/internal/deployments/123/status",
		},
		{
			name:     "rewrites localhost callback URL using panel host",
			input:    "http://localhost/api/internal/deployments/123/status",
			expected: "http://panel:8000/api/internal/deployments/123/status",
		},
		{
			name:     "empty callback URL stays empty",
			input:    "",
			expected: "",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			actual := s.resolveCallbackURL(tt.input)
			if actual != tt.expected {
				t.Fatalf("unexpected callback URL: got %q want %q", actual, tt.expected)
			}
		})
	}
}
