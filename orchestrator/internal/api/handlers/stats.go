package handlers

import (
	"net/http"

	"github.com/easyti/easydeploy/orchestrator/internal/repository"
	"github.com/go-chi/chi/v5"
)

// StatsHandler handles statistics endpoints
type StatsHandler struct {
	repo *repository.Repository
}

// NewStatsHandler creates a new StatsHandler
func NewStatsHandler(repo *repository.Repository) *StatsHandler {
	return &StatsHandler{repo: repo}
}

// GetGlobal returns global platform statistics
func (h *StatsHandler) GetGlobal(w http.ResponseWriter, r *http.Request) {
	stats, err := h.repo.GetGlobalStats(r.Context())
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to get stats")
		return
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"total_applications":   stats.TotalApplications,
		"total_deployments":    stats.TotalDeployments,
		"total_containers":     stats.TotalContainers,
		"online_servers":       stats.OnlineServers,
		"total_servers":        stats.TotalServers,
		"deployments_today":    stats.DeploymentsToday,
		"successful_deployments": stats.SuccessfulDeployments,
		"failed_deployments":   stats.FailedDeployments,
	})
}

// GetApplication returns statistics for a specific application
func (h *StatsHandler) GetApplication(w http.ResponseWriter, r *http.Request) {
	applicationID := chi.URLParam(r, "id")
	if applicationID == "" {
		respondError(w, http.StatusBadRequest, "application id is required")
		return
	}

	stats, err := h.repo.GetApplicationStats(r.Context(), applicationID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "failed to get stats")
		return
	}

	response := map[string]interface{}{
		"total_deployments":      stats.TotalDeployments,
		"successful_deployments": stats.SuccessfulDeployments,
		"failed_deployments":     stats.FailedDeployments,
		"running_containers":     stats.RunningContainers,
		"average_build_time":     stats.AverageBuildTime,
	}

	if stats.LastDeploymentAt != nil {
		response["last_deployment_at"] = stats.LastDeploymentAt.Format("2006-01-02T15:04:05Z")
	}

	respondJSON(w, http.StatusOK, response)
}
