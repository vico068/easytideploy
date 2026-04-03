package logging

import (
	"context"
	"net/http"
	"time"

	"github.com/go-chi/chi/v5/middleware"
	"github.com/google/uuid"
	"github.com/rs/zerolog"
	"github.com/rs/zerolog/log"
)

type contextKey string

const (
	// RequestIDKey is the context key for request ID
	RequestIDKey contextKey = "request_id"
	// CorrelationIDKey is the context key for correlation ID
	CorrelationIDKey contextKey = "correlation_id"
	// LoggerKey is the context key for the logger
	LoggerKey contextKey = "logger"
)

// CorrelationIDHeader is the HTTP header for correlation ID
const CorrelationIDHeader = "X-Correlation-ID"

// FromContext returns the logger from context
func FromContext(ctx context.Context) zerolog.Logger {
	if logger, ok := ctx.Value(LoggerKey).(zerolog.Logger); ok {
		return logger
	}
	return log.Logger
}

// WithLogger adds a logger to context
func WithLogger(ctx context.Context, logger zerolog.Logger) context.Context {
	return context.WithValue(ctx, LoggerKey, logger)
}

// GetCorrelationID returns the correlation ID from context
func GetCorrelationID(ctx context.Context) string {
	if id, ok := ctx.Value(CorrelationIDKey).(string); ok {
		return id
	}
	return ""
}

// WithCorrelationID adds a correlation ID to context
func WithCorrelationID(ctx context.Context, id string) context.Context {
	return context.WithValue(ctx, CorrelationIDKey, id)
}

// GetRequestID returns the request ID from context
func GetRequestID(ctx context.Context) string {
	if id := middleware.GetReqID(ctx); id != "" {
		return id
	}
	if id, ok := ctx.Value(RequestIDKey).(string); ok {
		return id
	}
	return ""
}

// NewCorrelationID generates a new correlation ID
func NewCorrelationID() string {
	return uuid.New().String()[:8]
}

// CorrelationMiddleware adds correlation ID to requests
func CorrelationMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		correlationID := r.Header.Get(CorrelationIDHeader)
		if correlationID == "" {
			correlationID = NewCorrelationID()
		}

		requestID := middleware.GetReqID(r.Context())
		if requestID == "" {
			requestID = NewCorrelationID()
		}

		// Create context with IDs
		ctx := WithCorrelationID(r.Context(), correlationID)
		ctx = context.WithValue(ctx, RequestIDKey, requestID)

		// Create logger with context fields
		logger := log.With().
			Str("correlation_id", correlationID).
			Str("request_id", requestID).
			Logger()

		ctx = WithLogger(ctx, logger)

		// Set response header
		w.Header().Set(CorrelationIDHeader, correlationID)

		next.ServeHTTP(w, r.WithContext(ctx))
	})
}

// StructuredLogger returns a middleware for structured request logging
func StructuredLogger(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		ww := middleware.NewWrapResponseWriter(w, r.ProtoMajor)

		defer func() {
			logger := FromContext(r.Context())

			logger.Info().
				Str("method", r.Method).
				Str("path", r.URL.Path).
				Str("query", r.URL.RawQuery).
				Int("status", ww.Status()).
				Int("bytes", ww.BytesWritten()).
				Dur("duration", time.Since(start)).
				Str("remote_addr", r.RemoteAddr).
				Str("user_agent", r.UserAgent()).
				Msg("HTTP request")
		}()

		next.ServeHTTP(ww, r)
	})
}

// Event creates a new log event with context fields
type Event struct {
	ctx    context.Context
	fields map[string]interface{}
}

// NewEvent creates a new log event
func NewEvent(ctx context.Context) *Event {
	return &Event{
		ctx:    ctx,
		fields: make(map[string]interface{}),
	}
}

// Str adds a string field
func (e *Event) Str(key, value string) *Event {
	e.fields[key] = value
	return e
}

// Int adds an int field
func (e *Event) Int(key string, value int) *Event {
	e.fields[key] = value
	return e
}

// Int64 adds an int64 field
func (e *Event) Int64(key string, value int64) *Event {
	e.fields[key] = value
	return e
}

// Float64 adds a float64 field
func (e *Event) Float64(key string, value float64) *Event {
	e.fields[key] = value
	return e
}

// Bool adds a bool field
func (e *Event) Bool(key string, value bool) *Event {
	e.fields[key] = value
	return e
}

// Err adds an error field
func (e *Event) Err(err error) *Event {
	if err != nil {
		e.fields["error"] = err.Error()
	}
	return e
}

// Dur adds a duration field
func (e *Event) Dur(key string, d time.Duration) *Event {
	e.fields[key] = d.String()
	return e
}

// Info logs an info message
func (e *Event) Info(msg string) {
	logger := FromContext(e.ctx)
	event := logger.Info()
	for k, v := range e.fields {
		event = event.Interface(k, v)
	}
	event.Msg(msg)
}

// Warn logs a warning message
func (e *Event) Warn(msg string) {
	logger := FromContext(e.ctx)
	event := logger.Warn()
	for k, v := range e.fields {
		event = event.Interface(k, v)
	}
	event.Msg(msg)
}

// Error logs an error message
func (e *Event) Error(msg string) {
	logger := FromContext(e.ctx)
	event := logger.Error()
	for k, v := range e.fields {
		event = event.Interface(k, v)
	}
	event.Msg(msg)
}

// Debug logs a debug message
func (e *Event) Debug(msg string) {
	logger := FromContext(e.ctx)
	event := logger.Debug()
	for k, v := range e.fields {
		event = event.Interface(k, v)
	}
	event.Msg(msg)
}

// L is a shorthand for creating a new event from context
func L(ctx context.Context) *Event {
	return NewEvent(ctx)
}

// Info logs an info message with context
func Info(ctx context.Context, msg string) {
	FromContext(ctx).Info().Msg(msg)
}

// Warn logs a warning message with context
func Warn(ctx context.Context, msg string) {
	FromContext(ctx).Warn().Msg(msg)
}

// Error logs an error message with context
func Error(ctx context.Context, err error, msg string) {
	FromContext(ctx).Error().Err(err).Msg(msg)
}

// Debug logs a debug message with context
func Debug(ctx context.Context, msg string) {
	FromContext(ctx).Debug().Msg(msg)
}
