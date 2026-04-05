package handlers

import (
	"net/http"

	"github.com/easyti/easydeploy/orchestrator/internal/config"
	"github.com/easyti/easydeploy/orchestrator/internal/database"
	"github.com/easyti/easydeploy/orchestrator/internal/traefik"
	"github.com/go-chi/chi/v5"
)

type ProxyHandler struct {
	cfg        *config.Config
	db         *database.DB
	traefikGen *traefik.ConfigGenerator
}

func NewProxyHandler(cfg *config.Config, db *database.DB, traefikGen *traefik.ConfigGenerator) *ProxyHandler {
	return &ProxyHandler{
		cfg:        cfg,
		db:         db,
		traefikGen: traefikGen,
	}
}

// Sync regenerates all Traefik configurations
func (h *ProxyHandler) Sync(w http.ResponseWriter, r *http.Request) {
	if err := h.traefikGen.RefreshAllConfigs(r.Context()); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to sync configurations")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{
		"status": "synced",
	})
}

// SyncApplication regenerates Traefik configuration for a specific application
func (h *ProxyHandler) SyncApplication(w http.ResponseWriter, r *http.Request) {
	appID := chi.URLParam(r, "appId")

	if err := h.traefikGen.GenerateConfig(r.Context(), appID); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to sync configuration")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{
		"status":         "synced",
		"application_id": appID,
	})
}

// GetConfig returns the current Traefik configuration for an application
func (h *ProxyHandler) GetConfig(w http.ResponseWriter, r *http.Request) {
	appID := chi.URLParam(r, "appId")

	configYAML, err := h.traefikGen.GenerateYAMLConfig(r.Context(), appID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to generate configuration")
		return
	}

	w.Header().Set("Content-Type", "text/yaml")
	w.WriteHeader(http.StatusOK)
	w.Write([]byte(configYAML))
}

// DeleteConfig removes the Traefik configuration for an application
func (h *ProxyHandler) DeleteConfig(w http.ResponseWriter, r *http.Request) {
	appID := chi.URLParam(r, "appId")

	if err := h.traefikGen.DeleteConfig(r.Context(), appID); err != nil {
		respondError(w, http.StatusInternalServerError, "failed to delete configuration")
		return
	}

	respondJSON(w, http.StatusOK, map[string]string{
		"status":         "deleted",
		"application_id": appID,
	})
}
