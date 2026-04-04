# Orchestrator Dockerfile - Multi-stage build
FROM golang:1.24-alpine AS builder

RUN apk add --no-cache git ca-certificates

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-s -w" -o /orchestrator ./cmd/orchestrator

# Runtime
FROM alpine:3.20

RUN apk add --no-cache ca-certificates tzdata wget git

RUN addgroup -g 1000 -S easydeploy && \
    adduser -u 1000 -S easydeploy -G easydeploy

WORKDIR /app

COPY --from=builder /orchestrator .

RUN mkdir -p /var/lib/easydeploy /etc/traefik/dynamic && \
    chown -R easydeploy:easydeploy /var/lib/easydeploy /etc/traefik/dynamic

USER easydeploy

EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=5s --retries=3 \
    CMD wget -q -O /dev/null http://localhost:8080/health || exit 1

ENTRYPOINT ["./orchestrator"]
