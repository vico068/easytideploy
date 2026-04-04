#!/usr/bin/env bash
# ============================================================================
# EasyDeploy PaaS - Control Plane Installer
# Installs: Panel (Laravel/Filament) + Orchestrator (Go) + PostgreSQL + Redis + Traefik
# Supported OS: Ubuntu 22.04+, Debian 12+, AlmaLinux/Rocky 9+, Amazon Linux 2023
# ============================================================================
set -euo pipefail

# =============================================================================
# Constants
# =============================================================================
readonly EASYDEPLOY_VERSION="1.0.0"
readonly INSTALL_DIR="/opt/easydeploy"
readonly LOG_FILE="/var/log/easydeploy-install.log"
readonly REQUIRED_DOCKER_VERSION="24.0"
readonly REQUIRED_DOCKER_COMPOSE_VERSION="2.20"
readonly MIN_MEMORY_MB=2048
readonly MIN_DISK_GB=10
readonly SUPPORTED_OS="ubuntu debian almalinux rocky amzn"

# Cores
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly CYAN='\033[0;36m'
readonly BOLD='\033[1m'
readonly NC='\033[0m'

# =============================================================================
# Logging
# =============================================================================
exec > >(tee -a "$LOG_FILE") 2>&1

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_step() { echo -e "\n${BLUE}${BOLD}>>> $1${NC}"; }
log_ok() { echo -e "${GREEN}  [OK]${NC} $1"; }

# =============================================================================
# Helper functions
# =============================================================================
command_exists() { command -v "$1" &>/dev/null; }

generate_password() {
    openssl rand -base64 32 | tr -d '/+=' | head -c 32
}

generate_api_key() {
    openssl rand -hex 32
}

generate_app_key() {
    # Laravel-compatible base64 key
    echo "base64:$(openssl rand -base64 32)"
}

version_gte() {
    printf '%s\n%s\n' "$2" "$1" | sort -V | head -n 1 | grep -q "^$2$"
}

prompt_with_default() {
    local prompt="$1"
    local default="$2"
    local var_name="$3"
    local is_secret="${4:-false}"

    if [ "$is_secret" = "true" ]; then
        echo -en "${CYAN}${prompt}${NC} [${YELLOW}auto-generated${NC}]: "
        read -rs value
        echo ""
    else
        echo -en "${CYAN}${prompt}${NC} [${YELLOW}${default}${NC}]: "
        read -r value
    fi
    value="${value:-$default}"
    eval "$var_name='$value'"
}

prompt_yes_no() {
    local prompt="$1"
    local default="${2:-y}"
    local result

    if [ "$default" = "y" ]; then
        echo -en "${CYAN}${prompt}${NC} [${YELLOW}Y/n${NC}]: "
    else
        echo -en "${CYAN}${prompt}${NC} [${YELLOW}y/N${NC}]: "
    fi
    read -r result
    result="${result:-$default}"
    [[ "$result" =~ ^[Yy]$ ]]
}

confirm_or_exit() {
    if ! prompt_yes_no "$1" "y"; then
        log_warn "Instalacao cancelada pelo usuario."
        exit 0
    fi
}

spinner() {
    local pid=$1
    local delay=0.1
    local spinstr='|/-\'
    while kill -0 "$pid" 2>/dev/null; do
        local temp=${spinstr#?}
        printf " [%c]  " "$spinstr"
        spinstr=$temp${spinstr%"$temp"}
        sleep $delay
        printf "\b\b\b\b\b\b"
    done
    printf "      \b\b\b\b\b\b"
}

# =============================================================================
# Preflight checks
# =============================================================================
preflight_checks() {
    log_step "Verificacoes pre-instalacao"

    # Root check
    if [ "$EUID" -ne 0 ]; then
        log_error "Este script deve ser executado como root (sudo)."
        exit 1
    fi
    log_ok "Executando como root"

    # OS detection
    if [ -f /etc/os-release ]; then
        source /etc/os-release
        OS_ID="$ID"
        OS_VERSION="$VERSION_ID"
        OS_NAME="$PRETTY_NAME"
    else
        log_error "Nao foi possivel detectar o sistema operacional."
        exit 1
    fi
    log_ok "Sistema operacional: $OS_NAME"

    # Check supported OS
    local os_supported=false
    for os in $SUPPORTED_OS; do
        if [ "$OS_ID" = "$os" ]; then
            os_supported=true
            break
        fi
    done
    if [ "$os_supported" = false ]; then
        log_warn "Sistema operacional '$OS_ID' nao e oficialmente suportado."
        confirm_or_exit "Deseja continuar mesmo assim?"
    fi

    # Architecture check
    local arch
    arch=$(uname -m)
    if [[ "$arch" != "x86_64" && "$arch" != "aarch64" ]]; then
        log_error "Arquitetura '$arch' nao suportada. Requer x86_64 ou aarch64."
        exit 1
    fi
    log_ok "Arquitetura: $arch"

    # Memory check
    local total_mem_mb
    total_mem_mb=$(awk '/MemTotal/ {printf "%.0f", $2/1024}' /proc/meminfo)
    if [ "$total_mem_mb" -lt "$MIN_MEMORY_MB" ]; then
        log_warn "Memoria insuficiente: ${total_mem_mb}MB (minimo recomendado: ${MIN_MEMORY_MB}MB)"
        confirm_or_exit "Deseja continuar mesmo assim?"
    else
        log_ok "Memoria RAM: ${total_mem_mb}MB"
    fi

    # Disk check
    local free_disk_gb
    free_disk_gb=$(df -BG / | awk 'NR==2 {gsub("G",""); print $4}')
    if [ "$free_disk_gb" -lt "$MIN_DISK_GB" ]; then
        log_warn "Disco insuficiente: ${free_disk_gb}GB livre (minimo recomendado: ${MIN_DISK_GB}GB)"
        confirm_or_exit "Deseja continuar mesmo assim?"
    else
        log_ok "Espaco em disco: ${free_disk_gb}GB livre"
    fi

    # Port availability
    local required_ports=(80 443 5432 6379 8080 8000)
    local ports_in_use=()
    for port in "${required_ports[@]}"; do
        if ss -tlnp | grep -q ":${port} "; then
            ports_in_use+=("$port")
        fi
    done
    if [ ${#ports_in_use[@]} -gt 0 ]; then
        log_warn "Portas em uso: ${ports_in_use[*]}"
        log_warn "Servicos nessas portas podem conflitar com o EasyDeploy."
        confirm_or_exit "Deseja continuar mesmo assim?"
    else
        log_ok "Portas necessarias estao livres (80, 443, 5432, 6379, 8080, 8000)"
    fi

    # Internet connectivity
    if ! curl -s --max-time 5 https://get.docker.com >/dev/null 2>&1; then
        log_error "Sem conectividade com a internet. Verifique sua conexao."
        exit 1
    fi
    log_ok "Conectividade com a internet"
}

# =============================================================================
# Interactive configuration
# =============================================================================
collect_configuration() {
    log_step "Configuracao interativa"
    echo ""
    echo -e "${BOLD}==========================================================${NC}"
    echo -e "${BOLD}  EasyDeploy PaaS - Configuracao do Control Plane v${EASYDEPLOY_VERSION}${NC}"
    echo -e "${BOLD}==========================================================${NC}"
    echo ""
    echo "Responda as perguntas abaixo para configurar o sistema."
    echo "Pressione ENTER para aceitar o valor padrao mostrado entre [ ]."
    echo ""

    # --- Dominio e URLs ---
    echo -e "${BOLD}--- Dominio e URLs ---${NC}"
    prompt_with_default "Dominio principal (ex: easyti.cloud)" "easyti.cloud" DOMAIN
    prompt_with_default "Subdominio do painel" "deploy.${DOMAIN}" PANEL_DOMAIN
    prompt_with_default "Subdominio da API (orchestrator)" "api.${DOMAIN}" API_DOMAIN
    prompt_with_default "Subdominio do Traefik dashboard" "traefik.${DOMAIN}" TRAEFIK_DOMAIN
    echo ""

    # --- SSL / Let's Encrypt ---
    echo -e "${BOLD}--- SSL / Let's Encrypt ---${NC}"
    if prompt_yes_no "Habilitar SSL automatico com Let's Encrypt?" "y"; then
        ACME_ENABLED="true"
        prompt_with_default "Email para certificados SSL" "admin@${DOMAIN}" ACME_EMAIL
        if prompt_yes_no "Usar servidor staging do Let's Encrypt? (para testes)" "n"; then
            ACME_STAGING="true"
        else
            ACME_STAGING="false"
        fi
    else
        ACME_ENABLED="false"
        ACME_EMAIL="admin@${DOMAIN}"
        ACME_STAGING="true"
    fi
    echo ""

    # --- Banco de dados ---
    echo -e "${BOLD}--- Banco de dados PostgreSQL ---${NC}"
    prompt_with_default "Nome do banco de dados" "easydeploy" DB_DATABASE
    prompt_with_default "Usuario do banco de dados" "easydeploy" DB_USERNAME
    local default_db_pass
    default_db_pass=$(generate_password)
    prompt_with_default "Senha do banco de dados" "$default_db_pass" DB_PASSWORD "true"
    prompt_with_default "Porta do PostgreSQL" "5432" DB_PORT
    echo ""

    # --- Redis ---
    echo -e "${BOLD}--- Redis ---${NC}"
    local default_redis_pass
    default_redis_pass=$(generate_password)
    prompt_with_default "Senha do Redis" "$default_redis_pass" REDIS_PASSWORD "true"
    prompt_with_default "Porta do Redis" "6379" REDIS_PORT
    echo ""

    # --- Orchestrator ---
    echo -e "${BOLD}--- Orchestrator ---${NC}"
    local default_api_key
    default_api_key=$(generate_api_key)
    prompt_with_default "API Key do Orchestrator" "$default_api_key" ORCHESTRATOR_API_KEY "true"
    prompt_with_default "Porta do Orchestrator" "8080" ORCHESTRATOR_PORT
    echo ""

    # --- Panel ---
    echo -e "${BOLD}--- Painel Web (Laravel/Filament) ---${NC}"
    APP_KEY=$(generate_app_key)
    prompt_with_default "Porta do Painel" "8000" PANEL_PORT
    echo ""

    # --- Admin user ---
    echo -e "${BOLD}--- Usuario Administrador ---${NC}"
    prompt_with_default "Nome do admin" "Admin EasyDeploy" ADMIN_NAME
    prompt_with_default "Email do admin" "admin@${DOMAIN}" ADMIN_EMAIL
    local default_admin_pass
    default_admin_pass=$(generate_password | head -c 16)
    prompt_with_default "Senha do admin (min 8 caracteres)" "$default_admin_pass" ADMIN_PASSWORD "true"
    if [ ${#ADMIN_PASSWORD} -lt 8 ]; then
        log_warn "Senha muito curta. Usando senha gerada automaticamente."
        ADMIN_PASSWORD="$default_admin_pass"
    fi
    echo ""

    # --- Traefik ---
    echo -e "${BOLD}--- Traefik (Reverse Proxy) ---${NC}"
    prompt_with_default "Porta do Dashboard Traefik" "8081" TRAEFIK_DASHBOARD_PORT
    prompt_with_default "Nivel de log do Traefik (DEBUG/INFO/WARN/ERROR)" "INFO" TRAEFIK_LOG_LEVEL
    echo ""

    # --- Docker Registry ---
    echo -e "${BOLD}--- Docker Registry ---${NC}"
    if prompt_yes_no "Instalar Docker Registry local?" "y"; then
        INSTALL_REGISTRY="true"
        prompt_with_default "Porta do Registry" "5000" REGISTRY_PORT
        DOCKER_REGISTRY="localhost:${REGISTRY_PORT}"
    else
        INSTALL_REGISTRY="false"
        prompt_with_default "URL do Docker Registry externo" "registry.easyti.cloud" DOCKER_REGISTRY
        REGISTRY_PORT="5000"
    fi
    echo ""

    # --- Timezone ---
    echo -e "${BOLD}--- Fuso Horario ---${NC}"
    local current_tz
    current_tz=$(timedatectl show --property=Timezone --value 2>/dev/null || echo "America/Sao_Paulo")
    prompt_with_default "Fuso horario" "$current_tz" TIMEZONE
    echo ""

    # --- Versao ---
    echo -e "${BOLD}--- Versao ---${NC}"
    prompt_with_default "Versao a instalar (latest, tag especifica)" "latest" VERSION
    echo ""

    # --- Backup ---
    echo -e "${BOLD}--- Backup automatico ---${NC}"
    if prompt_yes_no "Configurar backup automatico diario?" "y"; then
        SETUP_BACKUP="true"
        prompt_with_default "Diretorio de backup" "/opt/easydeploy/backups" BACKUP_DIR
        prompt_with_default "Reter backups por quantos dias?" "7" BACKUP_RETENTION_DAYS
        prompt_with_default "Horario do backup diario (cron, formato HH:MM)" "02:00" BACKUP_TIME
    else
        SETUP_BACKUP="false"
        BACKUP_DIR="/opt/easydeploy/backups"
        BACKUP_RETENTION_DAYS="7"
        BACKUP_TIME="02:00"
    fi
    echo ""

    # --- Firewall ---
    echo -e "${BOLD}--- Firewall ---${NC}"
    if prompt_yes_no "Configurar firewall (ufw) automaticamente?" "y"; then
        SETUP_FIREWALL="true"
    else
        SETUP_FIREWALL="false"
    fi
    echo ""

    # --- Demo data ---
    echo -e "${BOLD}--- Dados de demonstracao ---${NC}"
    if prompt_yes_no "Carregar dados de demonstracao?" "n"; then
        LOAD_DEMO_DATA="true"
    else
        LOAD_DEMO_DATA="false"
    fi
    echo ""

    # --- Confirmation ---
    show_configuration_summary
    confirm_or_exit "Confirma a instalacao com as configuracoes acima?"
}

show_configuration_summary() {
    echo ""
    echo -e "${BOLD}==========================================================${NC}"
    echo -e "${BOLD}  Resumo da Configuracao${NC}"
    echo -e "${BOLD}==========================================================${NC}"
    echo ""
    echo -e "  ${BOLD}Dominio:${NC}              ${DOMAIN}"
    echo -e "  ${BOLD}Painel URL:${NC}           https://${PANEL_DOMAIN}"
    echo -e "  ${BOLD}API URL:${NC}              https://${API_DOMAIN}"
    echo -e "  ${BOLD}Traefik Dashboard:${NC}    https://${TRAEFIK_DOMAIN}"
    echo -e "  ${BOLD}SSL (Let's Encrypt):${NC}  ${ACME_ENABLED}"
    echo -e "  ${BOLD}ACME Email:${NC}           ${ACME_EMAIL}"
    echo ""
    echo -e "  ${BOLD}Database:${NC}             ${DB_DATABASE}@postgres:${DB_PORT}"
    echo -e "  ${BOLD}Redis:${NC}                porta ${REDIS_PORT}"
    echo -e "  ${BOLD}Orchestrator:${NC}         porta ${ORCHESTRATOR_PORT}"
    echo -e "  ${BOLD}Panel:${NC}                porta ${PANEL_PORT}"
    echo -e "  ${BOLD}Registry:${NC}             ${DOCKER_REGISTRY}"
    echo ""
    echo -e "  ${BOLD}Admin:${NC}                ${ADMIN_EMAIL}"
    echo -e "  ${BOLD}Timezone:${NC}             ${TIMEZONE}"
    echo -e "  ${BOLD}Versao:${NC}               ${VERSION}"
    echo -e "  ${BOLD}Backup auto:${NC}          ${SETUP_BACKUP}"
    echo -e "  ${BOLD}Firewall:${NC}             ${SETUP_FIREWALL}"
    echo -e "  ${BOLD}Dados demo:${NC}           ${LOAD_DEMO_DATA}"
    echo ""
}

# =============================================================================
# Install system dependencies
# =============================================================================
install_system_dependencies() {
    log_step "Instalando dependencias do sistema"

    case "$OS_ID" in
        ubuntu|debian)
            export DEBIAN_FRONTEND=noninteractive
            apt-get update -y
            apt-get install -y \
                curl wget git openssl ca-certificates \
                gnupg lsb-release software-properties-common \
                apt-transport-https jq htop unzip \
                logrotate cron
            ;;
        almalinux|rocky)
            dnf install -y epel-release
            dnf install -y \
                curl wget git openssl ca-certificates \
                jq htop unzip logrotate cronie
            ;;
        amzn)
            yum install -y \
                curl wget git openssl ca-certificates \
                jq htop unzip logrotate cronie
            ;;
    esac

    log_ok "Dependencias do sistema instaladas"
}

# =============================================================================
# Install Docker
# =============================================================================
install_docker() {
    log_step "Verificando/Instalando Docker"

    if command_exists docker; then
        local docker_version
        docker_version=$(docker version --format '{{.Server.Version}}' 2>/dev/null || echo "0.0")
        if version_gte "$docker_version" "$REQUIRED_DOCKER_VERSION"; then
            log_ok "Docker ${docker_version} ja instalado"
        else
            log_warn "Docker ${docker_version} encontrado, mas versao >= ${REQUIRED_DOCKER_VERSION} e recomendada."
            if prompt_yes_no "Atualizar Docker?"; then
                curl -fsSL https://get.docker.com | sh
            fi
        fi
    else
        log_info "Instalando Docker..."
        curl -fsSL https://get.docker.com | sh
        log_ok "Docker instalado com sucesso"
    fi

    # Enable and start Docker
    systemctl enable docker
    systemctl start docker

    # Verify Docker Compose plugin
    if docker compose version &>/dev/null; then
        local compose_version
        compose_version=$(docker compose version --short 2>/dev/null || echo "0.0")
        log_ok "Docker Compose ${compose_version} disponivel"
    else
        log_info "Instalando Docker Compose plugin..."
        case "$OS_ID" in
            ubuntu|debian)
                apt-get install -y docker-compose-plugin
                ;;
            almalinux|rocky)
                dnf install -y docker-compose-plugin
                ;;
            *)
                mkdir -p /usr/local/lib/docker/cli-plugins
                curl -SL "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" \
                    -o /usr/local/lib/docker/cli-plugins/docker-compose
                chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
                ;;
        esac
        log_ok "Docker Compose instalado"
    fi

    # Configure Docker log rotation
    mkdir -p /etc/docker
    if [ ! -f /etc/docker/daemon.json ]; then
        cat > /etc/docker/daemon.json <<'DOCKEREOF'
{
    "log-driver": "json-file",
    "log-opts": {
        "max-size": "50m",
        "max-file": "3"
    },
    "storage-driver": "overlay2",
    "live-restore": true
}
DOCKEREOF
        systemctl restart docker
        log_ok "Docker log rotation configurado"
    fi
}

# =============================================================================
# Setup firewall
# =============================================================================
setup_firewall() {
    if [ "$SETUP_FIREWALL" != "true" ]; then
        return
    fi

    log_step "Configurando firewall"

    if command_exists ufw; then
        ufw --force reset
        ufw default deny incoming
        ufw default allow outgoing
        ufw allow ssh
        ufw allow 80/tcp    # HTTP
        ufw allow 443/tcp   # HTTPS
        ufw allow 9090/tcp  # gRPC agent (se necessario)
        ufw --force enable
        log_ok "UFW configurado (SSH, HTTP, HTTPS, gRPC)"
    elif command_exists firewall-cmd; then
        firewall-cmd --permanent --add-service=ssh
        firewall-cmd --permanent --add-service=http
        firewall-cmd --permanent --add-service=https
        firewall-cmd --permanent --add-port=9090/tcp
        firewall-cmd --reload
        log_ok "Firewalld configurado (SSH, HTTP, HTTPS, gRPC)"
    else
        case "$OS_ID" in
            ubuntu|debian)
                apt-get install -y ufw
                ufw default deny incoming
                ufw default allow outgoing
                ufw allow ssh
                ufw allow 80/tcp
                ufw allow 443/tcp
                ufw allow 9090/tcp
                ufw --force enable
                log_ok "UFW instalado e configurado"
                ;;
            *)
                log_warn "Nenhum firewall suportado encontrado. Configure manualmente."
                ;;
        esac
    fi
}

# =============================================================================
# Create directory structure
# =============================================================================
create_directories() {
    log_step "Criando estrutura de diretorios"

    mkdir -p "${INSTALL_DIR}"/{proxy/{dynamic,acme},data,backups,logs}
    chmod 600 "${INSTALL_DIR}/proxy/acme" 2>/dev/null || true
    touch "${INSTALL_DIR}/proxy/acme/acme.json"
    chmod 600 "${INSTALL_DIR}/proxy/acme/acme.json"

    log_ok "Diretorios criados em ${INSTALL_DIR}"
}

# =============================================================================
# Clone/Update repository
# =============================================================================
setup_repository() {
    log_step "Configurando repositorio"

    if [ -d "${INSTALL_DIR}/.git" ]; then
        log_info "Repositorio existente encontrado. Atualizando..."
        cd "${INSTALL_DIR}"
        git fetch --all
        git checkout "${VERSION}" 2>/dev/null || git pull origin main
    else
        log_info "Clonando repositorio..."
        git clone https://github.com/vico068/easytideploy.git "${INSTALL_DIR}/repo" 2>/dev/null || {
            log_warn "Nao foi possivel clonar o repositorio remoto."
            log_info "Usando arquivos locais se disponiveis..."

            # Se executado localmente do projeto, copia os arquivos
            local script_dir
            script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && cd .. && pwd)"
            if [ -f "${script_dir}/docker-compose.yml" ]; then
                log_info "Copiando arquivos do projeto local: ${script_dir}"
                cp -r "${script_dir}"/* "${INSTALL_DIR}/" 2>/dev/null || true
                cp -r "${script_dir}"/.* "${INSTALL_DIR}/" 2>/dev/null || true
            fi
        }
    fi

    cd "${INSTALL_DIR}"
    log_ok "Repositorio configurado"
}

# =============================================================================
# Generate environment file
# =============================================================================
generate_env_file() {
    log_step "Gerando arquivo de configuracao (.env)"

    cat > "${INSTALL_DIR}/.env" <<ENVEOF
# ============================================================================
# EasyDeploy PaaS - Configuracao de Producao
# Gerado em: $(date '+%Y-%m-%d %H:%M:%S')
# ============================================================================

# --- Aplicacao ---
APP_ENV=production
APP_DEBUG=false
APP_KEY=${APP_KEY}
APP_URL=https://${PANEL_DOMAIN}
VERSION=${VERSION}

# --- Dominio ---
DOMAIN=${DOMAIN}
PANEL_DOMAIN=${PANEL_DOMAIN}
API_DOMAIN=${API_DOMAIN}

# --- Banco de dados (PostgreSQL) ---
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

# --- Redis ---
REDIS_HOST=redis
REDIS_PORT=${REDIS_PORT}
REDIS_PASSWORD=${REDIS_PASSWORD}

# --- Orchestrator ---
ORCHESTRATOR_PORT=${ORCHESTRATOR_PORT}
ORCHESTRATOR_API_KEY=${ORCHESTRATOR_API_KEY}

# --- Panel ---
PANEL_PORT=${PANEL_PORT}
EASYDEPLOY_ORCHESTRATOR_URL=http://orchestrator:8080
EASYDEPLOY_API_KEY=${ORCHESTRATOR_API_KEY}
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# --- Docker Registry ---
DOCKER_REGISTRY=${DOCKER_REGISTRY}
REGISTRY_PORT=${REGISTRY_PORT}

# --- Traefik ---
TRAEFIK_DASHBOARD_PORT=${TRAEFIK_DASHBOARD_PORT}
TRAEFIK_LOG_LEVEL=${TRAEFIK_LOG_LEVEL}

# --- SSL / ACME ---
ACME_ENABLED=${ACME_ENABLED}
ACME_EMAIL=${ACME_EMAIL}
ACME_STAGING=${ACME_STAGING}

# --- Logging ---
LOG_LEVEL=info

# --- Timezone ---
TZ=${TIMEZONE}
ENVEOF

    chmod 600 "${INSTALL_DIR}/.env"
    log_ok "Arquivo .env gerado em ${INSTALL_DIR}/.env"
}

# =============================================================================
# Generate Traefik configuration
# =============================================================================
generate_traefik_config() {
    log_step "Gerando configuracao do Traefik"

    cat > "${INSTALL_DIR}/proxy/traefik.yml" <<TRAEFIKEOF
api:
  dashboard: true
  insecure: false

entryPoints:
  web:
    address: ":80"
    http:
      redirections:
        entryPoint:
          to: websecure
          scheme: https
  websecure:
    address: ":443"

providers:
  file:
    directory: /etc/traefik/dynamic
    watch: true
  docker:
    endpoint: "unix:///var/run/docker.sock"
    exposedByDefault: false
    network: easydeploy

certificatesResolvers:
  letsencrypt:
    acme:
      email: ${ACME_EMAIL}
      storage: /etc/traefik/acme/acme.json
      httpChallenge:
        entryPoint: web
$(if [ "$ACME_STAGING" = "true" ]; then echo "      caServer: https://acme-staging-v02.api.letsencrypt.org/directory"; fi)

log:
  level: ${TRAEFIK_LOG_LEVEL}
  format: json

accessLog:
  format: json
  filePath: /var/log/traefik/access.log
  bufferingSize: 100

metrics:
  prometheus:
    addEntryPointsLabels: true
    addServicesLabels: true

ping:
  entryPoint: web
TRAEFIKEOF

    # Generate dynamic config for panel and orchestrator routes
    cat > "${INSTALL_DIR}/proxy/dynamic/easydeploy.yml" <<DYNEOF
http:
  routers:
    panel:
      rule: "Host(\`${PANEL_DOMAIN}\`)"
      service: panel
      entryPoints:
        - websecure
      tls:
        certResolver: letsencrypt

    orchestrator-api:
      rule: "Host(\`${API_DOMAIN}\`)"
      service: orchestrator
      entryPoints:
        - websecure
      tls:
        certResolver: letsencrypt

    traefik-dashboard:
      rule: "Host(\`${TRAEFIK_DOMAIN}\`)"
      service: api@internal
      entryPoints:
        - websecure
      tls:
        certResolver: letsencrypt
      middlewares:
        - rate-limit

  services:
    panel:
      loadBalancer:
        servers:
          - url: "http://panel:8000"

    orchestrator:
      loadBalancer:
        servers:
          - url: "http://orchestrator:8080"

  middlewares:
    rate-limit:
      rateLimit:
        average: 100
        burst: 50
    security-headers:
      headers:
        stsSeconds: 31536000
        stsIncludeSubdomains: true
        stsPreload: true
        forceSTSHeader: true
        contentTypeNosniff: true
        frameDeny: true
        browserXssFilter: true
        referrerPolicy: "strict-origin-when-cross-origin"
        customResponseHeaders:
          X-Powered-By: ""
          Server: ""
DYNEOF

    log_ok "Configuracao do Traefik gerada"
}

# =============================================================================
# Generate docker-compose production override
# =============================================================================
generate_docker_compose_override() {
    log_step "Gerando docker-compose de producao"

    # If docker-compose.yml doesn't exist at INSTALL_DIR, create a minimal one
    if [ ! -f "${INSTALL_DIR}/docker-compose.yml" ]; then
        log_info "Gerando docker-compose.yml principal..."
        # The main docker-compose.yml should have been copied from the repo
        # If not, we need to create the production override assuming repo compose exists
    fi

    cat > "${INSTALL_DIR}/docker-compose.override.yml" <<COMPOSEEOF
# EasyDeploy Production Override
# Auto-generated by installer on $(date '+%Y-%m-%d %H:%M:%S')
services:
  postgres:
    restart: always
    environment:
      POSTGRES_DB: \${DB_DATABASE:-easydeploy}
      POSTGRES_USER: \${DB_USERNAME:-easydeploy}
      POSTGRES_PASSWORD: \${DB_PASSWORD}
      TZ: ${TIMEZONE}
    deploy:
      resources:
        limits:
          memory: 1G
          cpus: "1.0"

  redis:
    restart: always
    command: >
      redis-server
      --appendonly yes
      --maxmemory 512mb
      --maxmemory-policy allkeys-lru
      --requirepass \${REDIS_PASSWORD}
    environment:
      TZ: ${TIMEZONE}
    deploy:
      resources:
        limits:
          memory: 512M
          cpus: "0.5"

  traefik:
    restart: always
    command:
      - "--api.dashboard=true"
      - "--api.insecure=false"
      - "--providers.docker=true"
      - "--providers.docker.exposedbydefault=false"
      - "--providers.docker.network=easydeploy_easydeploy"
      - "--providers.file.directory=/etc/traefik/dynamic"
      - "--providers.file.watch=true"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.web.http.redirections.entrypoint.to=websecure"
      - "--entrypoints.web.http.redirections.entrypoint.scheme=https"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.letsencrypt.acme.email=\${ACME_EMAIL}"
      - "--certificatesresolvers.letsencrypt.acme.storage=/etc/traefik/acme/acme.json"
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge.entrypoint=web"
      - "--log.level=\${TRAEFIK_LOG_LEVEL:-INFO}"
      - "--accesslog=true"
      - "--ping=true"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./proxy/traefik.yml:/etc/traefik/traefik.yml:ro
      - ./proxy/dynamic:/etc/traefik/dynamic
      - ./proxy/acme:/etc/traefik/acme
    environment:
      TZ: ${TIMEZONE}
    deploy:
      resources:
        limits:
          memory: 256M
          cpus: "0.5"

  orchestrator:
    restart: always
    environment:
      APP_ENV: production
      ACME_ENABLED: \${ACME_ENABLED:-true}
      ACME_EMAIL: \${ACME_EMAIL}
      ACME_STAGING: \${ACME_STAGING:-false}
      TZ: ${TIMEZONE}
    deploy:
      resources:
        limits:
          memory: 512M
          cpus: "1.0"

  panel:
    restart: always
    environment:
      APP_NAME: EasyDeploy
      APP_ENV: production
      APP_DEBUG: "false"
      APP_URL: https://${PANEL_DOMAIN}
      REDIS_PASSWORD: \${REDIS_PASSWORD}
      TZ: ${TIMEZONE}
    deploy:
      resources:
        limits:
          memory: 512M
          cpus: "1.0"

  queue-worker:
    restart: always
    environment:
      APP_ENV: production
      REDIS_PASSWORD: \${REDIS_PASSWORD}
      TZ: ${TIMEZONE}
    deploy:
      resources:
        limits:
          memory: 256M
          cpus: "0.5"
      replicas: 2

  scheduler:
    restart: always
    environment:
      APP_ENV: production
      REDIS_PASSWORD: \${REDIS_PASSWORD}
      TZ: ${TIMEZONE}
    deploy:
      resources:
        limits:
          memory: 128M
          cpus: "0.25"
COMPOSEEOF

    log_ok "Docker compose de producao gerado"
}

# =============================================================================
# Build images
# =============================================================================
build_images() {
    log_step "Construindo imagens Docker"

    cd "${INSTALL_DIR}"

    if [ -f "${INSTALL_DIR}/orchestrator/Dockerfile" ]; then
        log_info "Construindo imagem do Orchestrator..."
        docker build -t easydeploy/orchestrator:${VERSION} ./orchestrator
        log_ok "Imagem do Orchestrator construida"
    fi

    if [ -f "${INSTALL_DIR}/agent/Dockerfile" ]; then
        log_info "Construindo imagem do Agent..."
        docker build -t easydeploy/agent:${VERSION} ./agent
        log_ok "Imagem do Agent construida"
    fi

    if [ -f "${INSTALL_DIR}/panel/Dockerfile" ]; then
        log_info "Construindo imagem do Panel..."
        docker build -t easydeploy/panel:${VERSION} ./panel
        log_ok "Imagem do Panel construida"
    fi
}

# =============================================================================
# Start services
# =============================================================================
start_services() {
    log_step "Iniciando servicos"

    cd "${INSTALL_DIR}"

    # Start infrastructure first
    log_info "Iniciando PostgreSQL e Redis..."
    docker compose up -d postgres redis
    log_info "Aguardando banco de dados ficar pronto..."

    local retries=0
    local max_retries=30
    while ! docker compose exec -T postgres pg_isready -U "${DB_USERNAME}" &>/dev/null; do
        retries=$((retries + 1))
        if [ "$retries" -ge "$max_retries" ]; then
            log_error "PostgreSQL nao ficou pronto apos ${max_retries} tentativas."
            exit 1
        fi
        sleep 2
    done
    log_ok "PostgreSQL pronto"

    retries=0
    while ! docker compose exec -T redis redis-cli -a "${REDIS_PASSWORD}" ping 2>/dev/null | grep -q PONG; do
        retries=$((retries + 1))
        if [ "$retries" -ge "$max_retries" ]; then
            log_error "Redis nao ficou pronto apos ${max_retries} tentativas."
            exit 1
        fi
        sleep 2
    done
    log_ok "Redis pronto"

    # Start Traefik
    log_info "Iniciando Traefik..."
    docker compose up -d traefik
    sleep 5
    log_ok "Traefik iniciado"

    # Start Docker Registry
    if [ "$INSTALL_REGISTRY" = "true" ]; then
        log_info "Iniciando Docker Registry..."
        docker compose up -d registry
        log_ok "Docker Registry iniciado"
    fi

    # Start Orchestrator
    log_info "Iniciando Orchestrator..."
    docker compose up -d orchestrator
    sleep 5
    log_ok "Orchestrator iniciado"

    # Start Panel
    log_info "Iniciando Panel..."
    docker compose up -d panel
    sleep 10
    log_ok "Panel iniciado"

    # Start Queue Worker and Scheduler
    log_info "Iniciando Queue Worker e Scheduler..."
    docker compose up -d queue-worker scheduler
    log_ok "Queue Worker e Scheduler iniciados"
}

# =============================================================================
# Run migrations and seed
# =============================================================================
run_migrations() {
    log_step "Executando migracoes do banco de dados"

    cd "${INSTALL_DIR}"

    # Wait for panel to be ready
    local retries=0
    local max_retries=30
    while ! docker compose exec -T panel php artisan --version &>/dev/null; do
        retries=$((retries + 1))
        if [ "$retries" -ge "$max_retries" ]; then
            log_error "Panel nao ficou pronto para migracoes."
            exit 1
        fi
        sleep 3
    done

    log_info "Executando migracoes..."
    docker compose exec -T panel php artisan migrate --force
    log_ok "Migracoes executadas"

    log_info "Executando seeds iniciais..."
    docker compose exec -T panel php artisan db:seed --force
    log_ok "Seeds executados"

    if [ "$LOAD_DEMO_DATA" = "true" ]; then
        log_info "Carregando dados de demonstracao..."
        docker compose exec -T panel php artisan db:seed --class=DemoSeeder --force
        log_ok "Dados de demonstracao carregados"
    fi
}

# =============================================================================
# Create admin user
# =============================================================================
create_admin_user() {
    log_step "Criando usuario administrador"

    cd "${INSTALL_DIR}"

    local escaped_password
    escaped_password=$(echo "$ADMIN_PASSWORD" | sed "s/'/\\\\'/g")
    local escaped_name
    escaped_name=$(echo "$ADMIN_NAME" | sed "s/'/\\\\'/g")

    docker compose exec -T panel php artisan tinker --execute="
        \$user = \\App\\Models\\User::where('email', '${ADMIN_EMAIL}')->first();
        if (\$user) {
            echo 'Usuario admin ja existe. Atualizando senha...';
            \$user->update(['password' => bcrypt('${escaped_password}')]);
        } else {
            \\App\\Models\\User::create([
                'name' => '${escaped_name}',
                'email' => '${ADMIN_EMAIL}',
                'password' => bcrypt('${escaped_password}'),
                'email_verified_at' => now(),
            ]);
            echo 'Usuario admin criado com sucesso.';
        }
    "

    log_ok "Usuario administrador configurado: ${ADMIN_EMAIL}"
}

# =============================================================================
# Setup systemd service
# =============================================================================
setup_systemd_service() {
    log_step "Configurando servico systemd"

    cat > /etc/systemd/system/easydeploy.service <<SERVICEEOF
[Unit]
Description=EasyDeploy PaaS Control Plane
Documentation=https://docs.easyti.cloud
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=${INSTALL_DIR}
ExecStartPre=/usr/bin/docker compose pull --quiet
ExecStart=/usr/bin/docker compose up -d
ExecStop=/usr/bin/docker compose down
ExecReload=/usr/bin/docker compose restart
TimeoutStartSec=300
TimeoutStopSec=120

[Install]
WantedBy=multi-user.target
SERVICEEOF

    systemctl daemon-reload
    systemctl enable easydeploy.service

    log_ok "Servico systemd 'easydeploy' habilitado"
}

# =============================================================================
# Setup automatic backups
# =============================================================================
setup_automatic_backups() {
    if [ "$SETUP_BACKUP" != "true" ]; then
        return
    fi

    log_step "Configurando backups automaticos"

    mkdir -p "${BACKUP_DIR}"

    # Create backup script
    cat > "${INSTALL_DIR}/scripts/backup.sh" <<'BACKUPEOF'
#!/usr/bin/env bash
set -euo pipefail

INSTALL_DIR="${INSTALL_DIR:-/opt/easydeploy}"
BACKUP_DIR="${BACKUP_DIR:-/opt/easydeploy/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_NAME="easydeploy-${TIMESTAMP}"

cd "${INSTALL_DIR}"

echo "[$(date)] Iniciando backup ${BACKUP_NAME}..."

# Database backup
docker compose exec -T postgres pg_dump \
    -U "${DB_USERNAME:-easydeploy}" \
    -Fc \
    "${DB_DATABASE:-easydeploy}" > "${BACKUP_DIR}/${BACKUP_NAME}.dump"

# Compress
gzip "${BACKUP_DIR}/${BACKUP_NAME}.dump"

# Backup .env
cp "${INSTALL_DIR}/.env" "${BACKUP_DIR}/${BACKUP_NAME}.env"

# Backup Traefik ACME
if [ -f "${INSTALL_DIR}/proxy/acme/acme.json" ]; then
    cp "${INSTALL_DIR}/proxy/acme/acme.json" "${BACKUP_DIR}/${BACKUP_NAME}-acme.json"
fi

# Cleanup old backups
find "${BACKUP_DIR}" -name "easydeploy-*.dump.gz" -mtime +${RETENTION_DAYS} -delete
find "${BACKUP_DIR}" -name "easydeploy-*.env" -mtime +${RETENTION_DAYS} -delete
find "${BACKUP_DIR}" -name "easydeploy-*-acme.json" -mtime +${RETENTION_DAYS} -delete

echo "[$(date)] Backup completo: ${BACKUP_DIR}/${BACKUP_NAME}.dump.gz"
BACKUPEOF

    chmod +x "${INSTALL_DIR}/scripts/backup.sh"

    # Setup cron
    local backup_hour backup_minute
    backup_hour=$(echo "$BACKUP_TIME" | cut -d: -f1)
    backup_minute=$(echo "$BACKUP_TIME" | cut -d: -f2)

    local cron_line="${backup_minute} ${backup_hour} * * * INSTALL_DIR=${INSTALL_DIR} BACKUP_DIR=${BACKUP_DIR} RETENTION_DAYS=${BACKUP_RETENTION_DAYS} ${INSTALL_DIR}/scripts/backup.sh >> /var/log/easydeploy-backup.log 2>&1"

    (crontab -l 2>/dev/null | grep -v "easydeploy.*backup" ; echo "$cron_line") | crontab -

    log_ok "Backup automatico configurado: diario as ${BACKUP_TIME}"
    log_ok "Retencao: ${BACKUP_RETENTION_DAYS} dias"
    log_ok "Diretorio: ${BACKUP_DIR}"
}

# =============================================================================
# Setup log rotation
# =============================================================================
setup_logrotate() {
    log_step "Configurando rotacao de logs"

    cat > /etc/logrotate.d/easydeploy <<LOGROTEOF
/var/log/easydeploy*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 root root
}

${INSTALL_DIR}/logs/*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 0640 root root
}
LOGROTEOF

    log_ok "Rotacao de logs configurada"
}

# =============================================================================
# Save credentials
# =============================================================================
save_credentials() {
    log_step "Salvando credenciais"

    cat > "${INSTALL_DIR}/.credentials" <<CREDEOF
# ============================================================================
# EasyDeploy PaaS - Credenciais
# Gerado em: $(date '+%Y-%m-%d %H:%M:%S')
# ATENCAO: Mantenha este arquivo seguro e faca backup!
# ============================================================================

# Acesso ao painel
PANEL_URL=https://${PANEL_DOMAIN}
ADMIN_EMAIL=${ADMIN_EMAIL}
ADMIN_PASSWORD=${ADMIN_PASSWORD}

# API
API_URL=https://${API_DOMAIN}
ORCHESTRATOR_API_KEY=${ORCHESTRATOR_API_KEY}

# Banco de dados
DB_HOST=localhost
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

# Redis
REDIS_HOST=localhost
REDIS_PORT=${REDIS_PORT}
REDIS_PASSWORD=${REDIS_PASSWORD}

# Traefik Dashboard
TRAEFIK_URL=https://${TRAEFIK_DOMAIN}

# Comando para adicionar agentes em servidores remotos:
# curl -fsSL https://raw.githubusercontent.com/easytisolutions/easydeploy/main/scripts/install-agent.sh | \\
#   sudo ORCHESTRATOR_URL=https://${API_DOMAIN} \\
#   API_KEY=${ORCHESTRATOR_API_KEY} \\
#   bash
CREDEOF

    chmod 600 "${INSTALL_DIR}/.credentials"
    log_ok "Credenciais salvas em ${INSTALL_DIR}/.credentials"
}

# =============================================================================
# Health check
# =============================================================================
final_health_check() {
    log_step "Verificacao final de saude"

    cd "${INSTALL_DIR}"

    local all_healthy=true

    # Check containers
    local services=("postgres" "redis" "traefik" "orchestrator" "panel" "queue-worker" "scheduler")
    for svc in "${services[@]}"; do
        local status
        status=$(docker compose ps --format json "$svc" 2>/dev/null | jq -r '.State' 2>/dev/null || echo "unknown")
        if [ "$status" = "running" ]; then
            log_ok "${svc}: rodando"
        else
            log_warn "${svc}: ${status}"
            all_healthy=false
        fi
    done

    # Test PostgreSQL connection
    if docker compose exec -T postgres pg_isready -U "${DB_USERNAME}" &>/dev/null; then
        log_ok "PostgreSQL: aceitando conexoes"
    else
        log_warn "PostgreSQL: nao esta aceitando conexoes"
        all_healthy=false
    fi

    # Test Redis connection
    if docker compose exec -T redis redis-cli -a "${REDIS_PASSWORD}" ping 2>/dev/null | grep -q PONG; then
        log_ok "Redis: respondendo ao ping"
    else
        log_warn "Redis: nao esta respondendo"
        all_healthy=false
    fi

    # Test Orchestrator health
    if curl -sf --max-time 5 http://localhost:${ORCHESTRATOR_PORT}/health &>/dev/null; then
        log_ok "Orchestrator API: saudavel"
    else
        log_warn "Orchestrator API: nao acessivel (pode estar inicializando)"
    fi

    # Test Panel
    if curl -sf --max-time 10 http://localhost:${PANEL_PORT} &>/dev/null; then
        log_ok "Panel: acessivel"
    else
        log_warn "Panel: nao acessivel (pode estar inicializando)"
    fi

    echo ""
    if [ "$all_healthy" = true ]; then
        log_ok "Todos os servicos estao saudaveis!"
    else
        log_warn "Alguns servicos ainda estao inicializando. Verifique com: cd ${INSTALL_DIR} && docker compose ps"
    fi
}

# =============================================================================
# Print final instructions
# =============================================================================
print_final_instructions() {
    echo ""
    echo -e "${BOLD}========================================================================${NC}"
    echo -e "${GREEN}${BOLD}  EasyDeploy PaaS - Instalacao Completa!${NC}"
    echo -e "${BOLD}========================================================================${NC}"
    echo ""
    echo -e "${BOLD}  Acesso ao Painel:${NC}"
    echo -e "    URL:    ${CYAN}https://${PANEL_DOMAIN}${NC}"
    echo -e "    Email:  ${CYAN}${ADMIN_EMAIL}${NC}"
    echo -e "    Senha:  ${CYAN}${ADMIN_PASSWORD}${NC}"
    echo ""
    echo -e "${BOLD}  API do Orchestrator:${NC}"
    echo -e "    URL:    ${CYAN}https://${API_DOMAIN}${NC}"
    echo -e "    Key:    ${CYAN}${ORCHESTRATOR_API_KEY:0:8}...${NC}"
    echo ""
    echo -e "${BOLD}  Dashboard Traefik:${NC}"
    echo -e "    URL:    ${CYAN}https://${TRAEFIK_DOMAIN}${NC}"
    echo ""
    echo -e "${BOLD}  Credenciais:${NC}"
    echo -e "    Arquivo: ${CYAN}${INSTALL_DIR}/.credentials${NC}"
    echo -e "    Env:     ${CYAN}${INSTALL_DIR}/.env${NC}"
    echo ""
    echo -e "${BOLD}  Comandos uteis:${NC}"
    echo -e "    Ver status:     ${CYAN}cd ${INSTALL_DIR} && docker compose ps${NC}"
    echo -e "    Ver logs:       ${CYAN}cd ${INSTALL_DIR} && docker compose logs -f${NC}"
    echo -e "    Restart:        ${CYAN}systemctl restart easydeploy${NC}"
    echo -e "    Backup manual:  ${CYAN}${INSTALL_DIR}/scripts/backup.sh${NC}"
    echo ""
    echo -e "${BOLD}  Adicionar servidor (agente):${NC}"
    echo -e "    No servidor remoto execute:"
    echo -e "    ${CYAN}curl -fsSL https://raw.githubusercontent.com/easytisolutions/easydeploy/main/scripts/install-agent.sh | \\\\${NC}"
    echo -e "    ${CYAN}  sudo ORCHESTRATOR_URL=https://${API_DOMAIN} \\\\${NC}"
    echo -e "    ${CYAN}  API_KEY=${ORCHESTRATOR_API_KEY:0:8}... \\\\${NC}"
    echo -e "    ${CYAN}  bash${NC}"
    echo ""
    echo -e "${YELLOW}${BOLD}  IMPORTANTE:${NC}"
    echo -e "    1. Altere a senha do admin no primeiro acesso"
    echo -e "    2. Configure o DNS para apontar *.${DOMAIN} para o IP deste servidor"
    echo -e "    3. Faca backup do arquivo ${INSTALL_DIR}/.credentials"
    echo -e "    4. Log da instalacao: ${LOG_FILE}"
    echo ""
    echo -e "${BOLD}========================================================================${NC}"
    echo ""
}

# =============================================================================
# Main
# =============================================================================
main() {
    echo ""
    echo -e "${BOLD}========================================================================${NC}"
    echo -e "${BOLD}        EasyDeploy PaaS - Instalador do Control Plane v${EASYDEPLOY_VERSION}${NC}"
    echo -e "${BOLD}========================================================================${NC}"
    echo ""

    preflight_checks
    collect_configuration
    install_system_dependencies
    install_docker
    setup_firewall
    create_directories
    setup_repository
    generate_env_file
    generate_traefik_config
    generate_docker_compose_override
    build_images
    start_services
    run_migrations
    create_admin_user
    setup_systemd_service
    setup_automatic_backups
    setup_logrotate
    save_credentials
    final_health_check
    print_final_instructions
}

# Run
main "$@"
