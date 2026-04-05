package metrics

import (
	"context"
	"fmt"
	"io"
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"sync"

	"github.com/rs/zerolog/log"
)

// TraefikMetrics contains parsed metrics from Traefik
type TraefikMetrics struct {
	Applications map[string]*ApplicationHTTPMetrics
}

// ApplicationHTTPMetrics contains HTTP metrics for a single application
type ApplicationHTTPMetrics struct {
	Requests2xx   int64
	Requests3xx   int64
	Requests4xx   int64
	Requests5xx   int64
	TotalRequests int64
}

// TraefikScraper scrapes metrics from Traefik's Prometheus endpoint
type TraefikScraper struct {
	traefikURL  string
	lastValues  map[string]int64 // key: "slug:code" -> last counter value
	mu          sync.Mutex
}

// NewTraefikScraper creates a new TraefikScraper
func NewTraefikScraper(traefikURL string) *TraefikScraper {
	return &TraefikScraper{
		traefikURL: traefikURL,
		lastValues: make(map[string]int64),
	}
}

// Scrape fetches and parses Traefik metrics, returning deltas since last scrape
func (t *TraefikScraper) Scrape(ctx context.Context) (*TraefikMetrics, error) {
	req, err := http.NewRequestWithContext(ctx, "GET", t.traefikURL+"/metrics", nil)
	if err != nil {
		return nil, fmt.Errorf("create request: %w", err)
	}

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("fetch metrics: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("unexpected status: %d", resp.StatusCode)
	}

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("read body: %w", err)
	}

	return t.parseAndDelta(string(body))
}

// parseAndDelta parses Prometheus metrics and calculates deltas
func (t *TraefikScraper) parseAndDelta(data string) (*TraefikMetrics, error) {
	t.mu.Lock()
	defer t.mu.Unlock()

	result := &TraefikMetrics{
		Applications: make(map[string]*ApplicationHTTPMetrics),
	}

	// Match: traefik_service_requests_total{code="200",method="GET",protocol="http",service="svc-myapp@file"} 91
	re := regexp.MustCompile(`traefik_service_requests_total\{code="(\d+)",method="[^"]+",protocol="[^"]+",service="svc-([^@"]+)@[^"]*"\}\s+(\d+)`)

	newValues := make(map[string]int64)

	lines := strings.Split(data, "\n")
	for _, line := range lines {
		if strings.HasPrefix(line, "#") || line == "" {
			continue
		}

		matches := re.FindStringSubmatch(line)
		if len(matches) != 4 {
			continue
		}

		statusCode := matches[1]
		appSlug := matches[2]
		currentValue, _ := strconv.ParseInt(matches[3], 10, 64)

		key := appSlug + ":" + statusCode
		newValues[key] = currentValue

		// Calculate delta
		lastValue, exists := t.lastValues[key]
		var delta int64
		if exists {
			delta = currentValue - lastValue
			if delta < 0 {
				delta = currentValue // Counter reset
			}
		} else {
			// First scrape: use 0 delta (we need a baseline)
			delta = 0
		}

		if delta == 0 {
			continue
		}

		if _, ok := result.Applications[appSlug]; !ok {
			result.Applications[appSlug] = &ApplicationHTTPMetrics{}
		}

		metric := result.Applications[appSlug]
		codeInt, _ := strconv.Atoi(statusCode)

		switch {
		case codeInt >= 200 && codeInt < 300:
			metric.Requests2xx += delta
		case codeInt >= 300 && codeInt < 400:
			metric.Requests3xx += delta
		case codeInt >= 400 && codeInt < 500:
			metric.Requests4xx += delta
		case codeInt >= 500 && codeInt < 600:
			metric.Requests5xx += delta
		}
		metric.TotalRequests += delta
	}

	// Update last values
	for k, v := range newValues {
		t.lastValues[k] = v
	}

	log.Debug().
		Int("applications", len(result.Applications)).
		Msg("scraped traefik metrics")

	return result, nil
}
