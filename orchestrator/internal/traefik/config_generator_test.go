package traefik

import (
	"os"
	"path/filepath"
	"testing"
)

func TestRemoveAppConfigFiles(t *testing.T) {
	tmpDir := t.TempDir()
	appID := "app-test"
	yamlPath := filepath.Join(tmpDir, "app-"+appID+".yml")
	jsonPath := filepath.Join(tmpDir, "app-"+appID+".json")

	if err := os.WriteFile(yamlPath, []byte("http: {}\n"), 0o644); err != nil {
		t.Fatalf("failed to create YAML fixture: %v", err)
	}
	if err := os.WriteFile(jsonPath, []byte("{}\n"), 0o644); err != nil {
		t.Fatalf("failed to create JSON fixture: %v", err)
	}

	g := &ConfigGenerator{configDir: tmpDir}
	if err := g.removeAppConfigFiles(appID); err != nil {
		t.Fatalf("removeAppConfigFiles failed: %v", err)
	}

	if _, err := os.Stat(yamlPath); !os.IsNotExist(err) {
		t.Fatalf("expected YAML config to be removed, got err=%v", err)
	}
	if _, err := os.Stat(jsonPath); !os.IsNotExist(err) {
		t.Fatalf("expected JSON config to be removed, got err=%v", err)
	}

	// Idempotency: removing again should not fail.
	if err := g.removeAppConfigFiles(appID); err != nil {
		t.Fatalf("removeAppConfigFiles should be idempotent, got: %v", err)
	}
}
