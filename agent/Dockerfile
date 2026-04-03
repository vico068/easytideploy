# Agent Dockerfile - Multi-stage build
FROM golang:1.22-alpine AS builder

RUN apk add --no-cache git ca-certificates

WORKDIR /app

COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-s -w" -o /agent ./cmd/agent

# Runtime
FROM alpine:3.20

RUN apk add --no-cache ca-certificates tzdata wget curl

WORKDIR /app

COPY --from=builder /agent .

EXPOSE 9090 9091

HEALTHCHECK --interval=15s --timeout=5s --retries=3 \
    CMD wget -q --spider http://localhost:9091/healthz || exit 1

ENTRYPOINT ["./agent"]
