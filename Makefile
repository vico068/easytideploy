.PHONY: dev build test deploy clean proto install-panel install-orchestrator install-agent backup restore setup setup-agent uninstall

# =====================
# Development
# =====================

dev: dev-infra
	@echo "Starting development environment..."
	cd panel && php artisan serve --port=8000 &
	cd orchestrator && go run cmd/orchestrator/main.go &
	cd agent && go run cmd/agent/main.go &
	@echo "Panel: http://localhost:8000"
	@echo "Orchestrator: http://localhost:8080"
	@echo "Agent: localhost:9090 (gRPC)"

dev-infra:
	docker compose up -d postgres redis traefik

dev-all:
	docker compose up -d

dev-down:
	docker compose down
	-pkill -f "php artisan serve"
	-pkill -f "go run cmd/orchestrator"
	-pkill -f "go run cmd/agent"

# =====================
# Build
# =====================

build: build-panel build-orchestrator build-agent

build-panel:
	docker build -t easydeploy/panel:latest ./panel

build-orchestrator:
	docker build -t easydeploy/orchestrator:latest ./orchestrator

build-agent:
	docker build -t easydeploy/agent:latest ./agent

# =====================
# Tests
# =====================

test: test-panel test-orchestrator test-agent

test-panel:
	cd panel && php artisan test

test-orchestrator:
	cd orchestrator && go test ./...

test-agent:
	cd agent && go test ./...

# =====================
# Installation
# =====================

install-panel:
	cd panel && composer install
	cd panel && cp .env.example .env
	cd panel && php artisan key:generate
	cd panel && php artisan migrate --seed
	cd panel && npm install && npm run build

install-orchestrator:
	cd orchestrator && go mod download

install-agent:
	cd agent && go mod download

install: install-panel install-orchestrator install-agent

# =====================
# Protobuf
# =====================

proto:
	protoc --go_out=. --go-grpc_out=. orchestrator/pkg/proto/agent.proto
	cp orchestrator/pkg/proto/*.pb.go agent/pkg/proto/

# =====================
# Deploy
# =====================

deploy-agent:
	@if [ -z "$(SERVER)" ]; then echo "Usage: make deploy-agent SERVER=hostname"; exit 1; fi
	ssh root@$(SERVER) "docker pull easydeploy/agent:latest && docker compose -f /opt/easydeploy/docker-compose.agent.yml up -d"

deploy-control-plane:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# =====================
# Maintenance
# =====================

clean:
	cd panel && php artisan cache:clear
	cd panel && php artisan config:clear
	cd panel && php artisan route:clear
	cd panel && php artisan view:clear

logs:
	docker compose logs -f

status:
	docker compose ps

# =====================
# Database
# =====================

migrate:
	cd panel && php artisan migrate

migrate-fresh:
	cd panel && php artisan migrate:fresh --seed

seed:
	cd panel && php artisan db:seed

seed-demo:
	cd panel && php artisan db:seed --class=DemoSeeder

# =====================
# Backup / Restore
# =====================

BACKUP_DIR ?= ./backups
BACKUP_NAME ?= easydeploy-$(shell date +%Y%m%d-%H%M%S)

backup:
	@mkdir -p $(BACKUP_DIR)
	@echo "Backing up database..."
	docker exec easydeploy-postgres pg_dump -U easydeploy -Fc easydeploy > $(BACKUP_DIR)/$(BACKUP_NAME).dump
	@echo "Backup created: $(BACKUP_DIR)/$(BACKUP_NAME).dump"

restore:
	@if [ -z "$(FILE)" ]; then echo "Usage: make restore FILE=./backups/file.dump"; exit 1; fi
	@echo "Restoring database from $(FILE)..."
	docker exec -i easydeploy-postgres pg_restore -U easydeploy -d easydeploy --clean --if-exists < $(FILE)
	@echo "Restore complete."

backup-list:
	@ls -lh $(BACKUP_DIR)/*.dump 2>/dev/null || echo "No backups found in $(BACKUP_DIR)/"

# =====================
# Production Setup
# =====================

setup:
	@echo "Running control plane installer..."
	sudo bash scripts/install-control-plane.sh

setup-agent:
	@echo "Running agent installer..."
	sudo bash scripts/install-agent.sh

uninstall:
	@echo "Running control plane uninstaller..."
	sudo bash scripts/uninstall-control-plane.sh
