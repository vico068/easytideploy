# Orchestrator Dockerfile - Multi-stage build
FROM golang:1.24-alpine AS builder

RUN apk add --no-cache git ca-certificates

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN CGO_ENABLED=0 GOOS=linux GOMAXPROCS=1 GOMEMLIMIT=1GiB go build -p=1 -ldflags="-s -w" -o /orchestrator ./cmd/orchestrator

# Runtime
FROM alpine:3.20

RUN apk add --no-cache ca-certificates tzdata wget git

RUN addgroup -g 1000 -S easydeploy && \
    adduser -u 1000 -S easydeploy -G easydeploy

WORKDIR /app

COPY --from=builder /orchestrator .
COPY buildpacks buildpacks

RUN mkdir -p /var/lib/easydeploy/repos /etc/traefik/dynamic && \
    chown -R easydeploy:easydeploy /var/lib/easydeploy /etc/traefik/dynamic /app

USER easydeploy

EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=5s --retries=3 \
    CMD wget -q -O /dev/null http://localhost:8080/health || exit 1

ENTRYPOINT ["./orchestrator"]
