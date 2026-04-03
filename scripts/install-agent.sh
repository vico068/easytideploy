#!/usr/bin/env bash
# ============================================================================
# EasyDeploy PaaS - Agent Installer
# Installs the EasyDeploy agent on a remote worker server.
# The agent connects to the orchestrator via gRPC and manages Docker containers.
# Supported OS: Ubuntu 22.04+, Debian 12+, AlmaLinux/Rocky 9+, Amazon Linux 2023
# ============================================================================
set -euo pipefail

# =============================================================================
# Constants
# =============================================================================
readonly EASYDEPLOY_VERSION="1.0.0"
readonly AGENT_INSTALL_DIR="/opt/easydeploy-agent"
readonly LOG_FILE="/var/log/easydeploy-agent-install.log"
readonly REQUIRED_DOCKER_VERSION="24.0"
readonly REQUIRED_GO_VERSION="1.22"
readonly MIN_MEMORY_MB=512
readonly MIN_DISK_GB=5
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

generate_server_id() {
    local hostname
    hostname=$(hostname -s 2>/dev/null || hostname)
    echo "${hostname}-$(cat /proc/sys/kernel/random/uuid 2>/dev/null | cut -d'-' -f1 || openssl rand -hex 4)"
}

generate_api_key() {
    openssl rand -hex 32
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
        echo -en "${CYAN}${prompt}${NC} [${YELLOW}***${NC}]: "
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

get_public_ip() {
    curl -sf --max-time 5 https://ifconfig.me 2>/dev/null \
        || curl -sf --max-time 5 https://api.ipify.org 2>/dev/null \
        || curl -sf --max-time 5 https://icanhazip.com 2>/dev/null \
        || echo "0.0.0.0"
}

get_private_ip() {
    ip route get 1.1.1.1 2>/dev/null | awk '{print $7; exit}' \
        || hostname -I 2>/dev/null | awk '{print $1}' \
        || echo "127.0.0.1"
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
    local required_ports=(9090 9091)
    local ports_in_use=()
    for port in "${required_ports[@]}"; do
        if ss -tlnp | grep -q ":${port} "; then
            ports_in_use+=("$port")
        fi
    done
    if [ ${#ports_in_use[@]} -gt 0 ]; then
        log_warn "Portas em uso: ${ports_in_use[*]}"
        confirm_or_exit "Deseja continuar mesmo assim?"
    else
        log_ok "Portas necessarias estao livres (9090 gRPC, 9091 HTTP)"
    fi

    # Detect IP addresses
    PUBLIC_IP=$(get_public_ip)
    PRIVATE_IP=$(get_private_ip)
    log_ok "IP publico: ${PUBLIC_IP}"
    log_ok "IP privado: ${PRIVATE_IP}"

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
    echo -e "${BOLD}  EasyDeploy PaaS - Configuracao do Agent v${EASYDEPLOY_VERSION}${NC}"
    echo -e "${BOLD}==========================================================${NC}"
    echo ""
    echo "Responda as perguntas abaixo para configurar o agente."
    echo "Pressione ENTER para aceitar o valor padrao mostrado entre [ ]."
    echo ""

    # --- Server identification ---
    echo -e "${BOLD}--- Identificacao do Servidor ---${NC}"
    local default_server_id
    default_server_id=$(generate_server_id)
    local default_hostname
    default_hostname=$(hostname -f 2>/dev/null || hostname)
    prompt_with_default "ID unico do servidor" "$default_server_id" SERVER_ID
    prompt_with_default "Nome amigavel do servidor" "$default_hostname" SERVER_NAME
    echo ""

    # --- Orchestrator connection ---
    echo -e "${BOLD}--- Conexao com o Orchestrator ---${NC}"
    echo -e "  O agente precisa se conectar ao orchestrator do control plane."
    echo ""

    # Check if passed via environment var (for curl | bash installs)
    local default_orchestrator_url="${ORCHESTRATOR_URL:-}"
    local default_api_key="${API_KEY:-}"

    if [ -z "$default_orchestrator_url" ]; then
        default_orchestrator_url="https://api.easyti.cloud"
    fi

    prompt_with_default "URL do Orchestrator (ex: https://api.seu-dominio.com)" "$default_orchestrator_url" ORCHESTRATOR_URL

    if [ -z "$default_api_key" ]; then
        prompt_with_default "API Key do Orchestrator" "" ORCHESTRATOR_API_KEY "true"
    else
        ORCHESTRATOR_API_KEY="$default_api_key"
        log_info "API Key fornecida via variavel de ambiente."
    fi

    if [ -z "$ORCHESTRATOR_API_KEY" ]; then
        log_error "A API Key do orchestrator e obrigatoria."
        log_info "Voce pode encontra-la no arquivo .credentials do control plane."
        exit 1
    fi
    echo ""

    # --- Network ---
    echo -e "${BOLD}--- Rede ---${NC}"
    prompt_with_default "Porta do servidor gRPC" "9090" GRPC_PORT
    prompt_with_default "Porta do servidor HTTP (health/metrics)" "9091" HTTP_PORT
    prompt_with_default "IP que o orchestrator usara para conectar a este servidor" "$PUBLIC_IP" AGENT_ADVERTISE_IP
    echo ""

    # --- Docker ---
    echo -e "${BOLD}--- Docker ---${NC}"
    prompt_with_default "Socket do Docker" "unix:///var/run/docker.sock" DOCKER_HOST
    prompt_with_default "Maximo de containers neste servidor" "50" MAX_CONTAINERS
    echo ""

    # --- Heartbeat ---
    echo -e "${BOLD}--- Heartbeat ---${NC}"
    prompt_with_default "Intervalo de heartbeat (segundos)" "30" HEARTBEAT_INTERVAL
    prompt_with_default "Timeout do heartbeat (segundos)" "10" HEARTBEAT_TIMEOUT
    echo ""

    # --- TLS ---
    echo -e "${BOLD}--- Seguranca (TLS) ---${NC}"
    if prompt_yes_no "Habilitar TLS para gRPC?" "n"; then
        TLS_ENABLED="true"
        prompt_with_default "Caminho do certificado TLS" "/opt/easydeploy-agent/certs/agent.crt" TLS_CERT_FILE
        prompt_with_default "Caminho da chave TLS" "/opt/easydeploy-agent/certs/agent.key" TLS_KEY_FILE
    else
        TLS_ENABLED="false"
        TLS_CERT_FILE=""
        TLS_KEY_FILE=""
    fi
    echo ""

    # --- Metrics ---
    echo -e "${BOLD}--- Metricas ---${NC}"
    if prompt_yes_no "Habilitar metricas Prometheus?" "y"; then
        METRICS_ENABLED="true"
        prompt_with_default "Intervalo de coleta de metricas (segundos)" "60" METRICS_INTERVAL
    else
        METRICS_ENABLED="false"
        METRICS_INTERVAL="60"
    fi
    echo ""

    # --- Logging ---
    echo -e "${BOLD}--- Logging ---${NC}"
    prompt_with_default "Nivel de log (debug/info/warn/error)" "info" LOG_LEVEL
    if prompt_yes_no "Logs em formato JSON? (recomendado para producao)" "y"; then
        LOG_JSON="true"
    else
        LOG_JSON="false"
    fi
    echo ""

    # --- Install mode ---
    echo -e "${BOLD}--- Modo de Instalacao ---${NC}"
    echo -e "  1) ${BOLD}Docker${NC}  - Roda o agente como container Docker (recomendado)"
    echo -e "  2) ${BOLD}Binary${NC}  - Compila e instala o binario Go nativo"
    echo -e "  3) ${BOLD}Systemd${NC} - Compila e roda como servico systemd"
    echo ""
    prompt_with_default "Escolha o modo de instalacao (1/2/3)" "1" INSTALL_MODE
    case "$INSTALL_MODE" in
        1|docker|Docker) INSTALL_MODE="docker" ;;
        2|binary|Binary) INSTALL_MODE="binary" ;;
        3|systemd|Systemd) INSTALL_MODE="systemd" ;;
        *) INSTALL_MODE="docker" ;;
    esac
    echo ""

    # --- Firewall ---
    echo -e "${BOLD}--- Firewall ---${NC}"
    if prompt_yes_no "Configurar firewall automaticamente?" "y"; then
        SETUP_FIREWALL="true"
    else
        SETUP_FIREWALL="false"
    fi
    echo ""

    # --- Timezone ---
    echo -e "${BOLD}--- Fuso Horario ---${NC}"
    local current_tz
    current_tz=$(timedatectl show --property=Timezone --value 2>/dev/null || echo "America/Sao_Paulo")
    prompt_with_default "Fuso horario" "$current_tz" TIMEZONE
    echo ""

    # --- Confirmation ---
    show_configuration_summary
    confirm_or_exit "Confirma a instalacao com as configuracoes acima?"
}

show_configuration_summary() {
    echo ""
    echo -e "${BOLD}==========================================================${NC}"
    echo -e "${BOLD}  Resumo da Configuracao do Agent${NC}"
    echo -e "${BOLD}==========================================================${NC}"
    echo ""
    echo -e "  ${BOLD}Server ID:${NC}            ${SERVER_ID}"
    echo -e "  ${BOLD}Server Name:${NC}          ${SERVER_NAME}"
    echo -e "  ${BOLD}Orchestrator URL:${NC}     ${ORCHESTRATOR_URL}"
    echo -e "  ${BOLD}Advertise IP:${NC}         ${AGENT_ADVERTISE_IP}"
    echo ""
    echo -e "  ${BOLD}gRPC Port:${NC}            ${GRPC_PORT}"
    echo -e "  ${BOLD}HTTP Port:${NC}            ${HTTP_PORT}"
    echo -e "  ${BOLD}Max Containers:${NC}       ${MAX_CONTAINERS}"
    echo -e "  ${BOLD}Heartbeat:${NC}            ${HEARTBEAT_INTERVAL}s (timeout: ${HEARTBEAT_TIMEOUT}s)"
    echo ""
    echo -e "  ${BOLD}TLS:${NC}                  ${TLS_ENABLED}"
    echo -e "  ${BOLD}Metrics:${NC}              ${METRICS_ENABLED}"
    echo -e "  ${BOLD}Log Level:${NC}            ${LOG_LEVEL}"
    echo -e "  ${BOLD}Log JSON:${NC}             ${LOG_JSON}"
    echo ""
    echo -e "  ${BOLD}Install Mode:${NC}         ${INSTALL_MODE}"
    echo -e "  ${BOLD}Firewall:${NC}             ${SETUP_FIREWALL}"
    echo -e "  ${BOLD}Timezone:${NC}             ${TIMEZONE}"
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

    log_ok "Docker pronto"
}

# =============================================================================
# Install Go (for binary/systemd modes)
# =============================================================================
install_go() {
    if [ "$INSTALL_MODE" = "docker" ]; then
        return
    fi

    log_step "Verificando/Instalando Go"

    if command_exists go; then
        local go_version
        go_version=$(go version | awk '{print $3}' | sed 's/go//')
        if version_gte "$go_version" "$REQUIRED_GO_VERSION"; then
            log_ok "Go ${go_version} ja instalado"
            return
        fi
    fi

    log_info "Instalando Go ${REQUIRED_GO_VERSION}..."

    local arch
    arch=$(uname -m)
    local go_arch="amd64"
    if [ "$arch" = "aarch64" ]; then
        go_arch="arm64"
    fi

    local go_tarball="go1.22.5.linux-${go_arch}.tar.gz"
    local go_url="https://go.dev/dl/${go_tarball}"

    cd /tmp
    curl -fsSLO "$go_url"
    rm -rf /usr/local/go
    tar -C /usr/local -xzf "$go_tarball"
    rm -f "$go_tarball"

    # Setup PATH
    if ! grep -q '/usr/local/go/bin' /etc/profile.d/go.sh 2>/dev/null; then
        cat > /etc/profile.d/go.sh <<'GOEOF'
export PATH=$PATH:/usr/local/go/bin
export GOPATH=/opt/go
export PATH=$PATH:$GOPATH/bin
GOEOF
    fi
    export PATH=$PATH:/usr/local/go/bin
    export GOPATH=/opt/go
    mkdir -p "$GOPATH"

    log_ok "Go $(go version | awk '{print $3}') instalado"
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
        ufw allow ${GRPC_PORT}/tcp comment "EasyDeploy Agent gRPC"
        ufw allow ${HTTP_PORT}/tcp comment "EasyDeploy Agent HTTP"
        # Allow HTTP/HTTPS for hosted applications
        ufw allow 80/tcp comment "HTTP (applications)"
        ufw allow 443/tcp comment "HTTPS (applications)"
        ufw --force enable
        log_ok "UFW configurado (SSH, gRPC:${GRPC_PORT}, HTTP:${HTTP_PORT}, 80, 443)"
    elif command_exists firewall-cmd; then
        firewall-cmd --permanent --add-service=ssh
        firewall-cmd --permanent --add-port=${GRPC_PORT}/tcp
        firewall-cmd --permanent --add-port=${HTTP_PORT}/tcp
        firewall-cmd --permanent --add-service=http
        firewall-cmd --permanent --add-service=https
        firewall-cmd --reload
        log_ok "Firewalld configurado"
    else
        case "$OS_ID" in
            ubuntu|debian)
                apt-get install -y ufw
                ufw default deny incoming
                ufw default allow outgoing
                ufw allow ssh
                ufw allow ${GRPC_PORT}/tcp
                ufw allow ${HTTP_PORT}/tcp
                ufw allow 80/tcp
                ufw allow 443/tcp
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

    mkdir -p "${AGENT_INSTALL_DIR}"/{bin,certs,config,logs,data}

    log_ok "Diretorios criados em ${AGENT_INSTALL_DIR}"
}

# =============================================================================
# Generate agent environment file
# =============================================================================
generate_env_file() {
    log_step "Gerando arquivo de configuracao"

    cat > "${AGENT_INSTALL_DIR}/config/.env" <<ENVEOF
# ============================================================================
# EasyDeploy Agent - Configuracao
# Gerado em: $(date '+%Y-%m-%d %H:%M:%S')
# ============================================================================

# --- Identificacao do Servidor ---
SERVER_ID=${SERVER_ID}
SERVER_NAME=${SERVER_NAME}
VERSION=${EASYDEPLOY_VERSION}

# --- Rede ---
GRPC_ADDRESS=:${GRPC_PORT}
HTTP_ADDRESS=:${HTTP_PORT}

# --- Orchestrator ---
ORCHESTRATOR_URL=${ORCHESTRATOR_URL}
ORCHESTRATOR_API_KEY=${ORCHESTRATOR_API_KEY}

# --- Docker ---
DOCKER_HOST=${DOCKER_HOST}

# --- Heartbeat ---
HEARTBEAT_INTERVAL=${HEARTBEAT_INTERVAL}
HEARTBEAT_TIMEOUT=${HEARTBEAT_TIMEOUT}

# --- Limites ---
MAX_CONTAINERS=${MAX_CONTAINERS}

# --- Logging ---
LOG_LEVEL=${LOG_LEVEL}
LOG_JSON=${LOG_JSON}

# --- Metricas ---
METRICS_ENABLED=${METRICS_ENABLED}
METRICS_INTERVAL=${METRICS_INTERVAL}

# --- TLS ---
TLS_ENABLED=${TLS_ENABLED}
TLS_CERT_FILE=${TLS_CERT_FILE}
TLS_KEY_FILE=${TLS_KEY_FILE}

# --- Timezone ---
TZ=${TIMEZONE}
ENVEOF

    chmod 600 "${AGENT_INSTALL_DIR}/config/.env"
    log_ok "Arquivo .env gerado em ${AGENT_INSTALL_DIR}/config/.env"
}

# =============================================================================
# Install via Docker mode
# =============================================================================
install_docker_mode() {
    log_step "Instalando agente via Docker"

    # Create docker-compose for the agent
    cat > "${AGENT_INSTALL_DIR}/docker-compose.yml" <<COMPOSEEOF
version: "3.8"

services:
  agent:
    image: easydeploy/agent:${EASYDEPLOY_VERSION}
    container_name: easydeploy-agent
    restart: always
    env_file:
      - ./config/.env
    ports:
      - "${GRPC_PORT}:9090"
      - "${HTTP_PORT}:9091"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - agent-data:/app/data
      - ./logs:/app/logs
$(if [ "$TLS_ENABLED" = "true" ]; then echo "      - ./certs:/app/certs:ro"; fi)
    environment:
      - TZ=${TIMEZONE}
    networks:
      - easydeploy
    healthcheck:
      test: ["CMD", "wget", "-q", "--spider", "http://localhost:9091/healthz"]
      interval: 15s
      timeout: 5s
      retries: 3
      start_period: 10s
    deploy:
      resources:
        limits:
          memory: 256M
          cpus: "0.5"
    security_opt:
      - no-new-privileges:true
    logging:
      driver: json-file
      options:
        max-size: "50m"
        max-file: "3"

networks:
  easydeploy:
    driver: bridge

volumes:
  agent-data:
COMPOSEEOF

    # Check if Dockerfile exists locally (for building from source)
    local script_dir
    script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && cd .. && pwd)"

    if [ -f "${script_dir}/agent/Dockerfile" ]; then
        log_info "Construindo imagem do agent a partir do codigo fonte..."
        docker build -t easydeploy/agent:${EASYDEPLOY_VERSION} "${script_dir}/agent"
        log_ok "Imagem construida localmente"
    else
        log_info "Tentando baixar imagem pre-construida..."
        if ! docker pull easydeploy/agent:${EASYDEPLOY_VERSION} 2>/dev/null; then
            log_warn "Imagem pre-construida nao encontrada."
            log_info "Clonando repositorio para construir a imagem..."

            local tmp_dir
            tmp_dir=$(mktemp -d)
            git clone https://github.com/vico068/easytideploy.git "${tmp_dir}" 2>/dev/null || {
                log_error "Nao foi possivel clonar o repositorio."
                log_error "Verifique se o repositorio esta acessivel ou coloque o Dockerfile em ${AGENT_INSTALL_DIR}/agent/"
                exit 1
            }
            docker build -t easydeploy/agent:${EASYDEPLOY_VERSION} "${tmp_dir}/agent"
            rm -rf "${tmp_dir}"
            log_ok "Imagem construida a partir do repositorio"
        else
            log_ok "Imagem baixada com sucesso"
        fi
    fi

    # Start the agent
    log_info "Iniciando agente..."
    cd "${AGENT_INSTALL_DIR}"
    docker compose up -d

    log_ok "Agente Docker iniciado"
}

# =============================================================================
# Install via Binary/Systemd mode
# =============================================================================
install_binary_mode() {
    log_step "Compilando agente a partir do codigo fonte"

    local src_dir="${AGENT_INSTALL_DIR}/src"

    # Try to use local source
    local script_dir
    script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && cd .. && pwd)"

    if [ -f "${script_dir}/agent/go.mod" ]; then
        log_info "Usando codigo fonte local..."
        src_dir="${script_dir}/agent"
    else
        log_info "Clonando repositorio..."
        local tmp_dir
        tmp_dir=$(mktemp -d)
        git clone https://github.com/vico068/easytideploy.git "${tmp_dir}" 2>/dev/null || {
            log_error "Nao foi possivel clonar o repositorio."
            exit 1
        }
        src_dir="${tmp_dir}/agent"
    fi

    cd "${src_dir}"

    log_info "Baixando dependencias Go..."
    go mod download

    log_info "Compilando binario..."
    CGO_ENABLED=0 GOOS=linux go build \
        -ldflags="-s -w -X main.version=${EASYDEPLOY_VERSION}" \
        -o "${AGENT_INSTALL_DIR}/bin/easydeploy-agent" \
        ./cmd/agent

    chmod +x "${AGENT_INSTALL_DIR}/bin/easydeploy-agent"

    log_ok "Binario compilado: ${AGENT_INSTALL_DIR}/bin/easydeploy-agent"
}

# =============================================================================
# Setup systemd service
# =============================================================================
setup_systemd_service() {
    if [ "$INSTALL_MODE" = "docker" ]; then
        log_step "Configurando servico systemd (Docker)"

        cat > /etc/systemd/system/easydeploy-agent.service <<SERVICEEOF
[Unit]
Description=EasyDeploy PaaS Agent (Docker)
Documentation=https://docs.easyti.cloud
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=${AGENT_INSTALL_DIR}
ExecStart=/usr/bin/docker compose up -d
ExecStop=/usr/bin/docker compose down
ExecReload=/usr/bin/docker compose restart
TimeoutStartSec=120
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
SERVICEEOF

    else
        log_step "Configurando servico systemd (Binary)"

        # Create system user for the agent
        if ! id easydeploy &>/dev/null; then
            useradd --system --shell /usr/sbin/nologin --home-dir "${AGENT_INSTALL_DIR}" easydeploy
            # Add to docker group for Docker socket access
            usermod -aG docker easydeploy
        fi

        cat > /etc/systemd/system/easydeploy-agent.service <<SERVICEEOF
[Unit]
Description=EasyDeploy PaaS Agent
Documentation=https://docs.easyti.cloud
After=network-online.target docker.service
Wants=network-online.target
Requires=docker.service

[Service]
Type=simple
User=easydeploy
Group=easydeploy
EnvironmentFile=${AGENT_INSTALL_DIR}/config/.env
ExecStart=${AGENT_INSTALL_DIR}/bin/easydeploy-agent
ExecReload=/bin/kill -HUP \$MAINPID
Restart=always
RestartSec=5
StartLimitInterval=60
StartLimitBurst=3

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=easydeploy-agent

# Security hardening
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=${AGENT_INSTALL_DIR}/data ${AGENT_INSTALL_DIR}/logs
PrivateTmp=true
ProtectKernelTunables=true
ProtectControlGroups=true
RestrictSUIDSGID=true

# Resource limits
LimitNOFILE=65535
LimitNPROC=4096
MemoryMax=512M
CPUQuota=100%

[Install]
WantedBy=multi-user.target
SERVICEEOF

        # Fix permissions
        chown -R easydeploy:easydeploy "${AGENT_INSTALL_DIR}"
    fi

    systemctl daemon-reload
    systemctl enable easydeploy-agent.service

    if [ "$INSTALL_MODE" != "docker" ]; then
        log_info "Iniciando agente..."
        systemctl start easydeploy-agent.service
    fi

    log_ok "Servico systemd 'easydeploy-agent' configurado e habilitado"
}

# =============================================================================
# Setup log rotation
# =============================================================================
setup_logrotate() {
    log_step "Configurando rotacao de logs"

    cat > /etc/logrotate.d/easydeploy-agent <<LOGROTEOF
${AGENT_INSTALL_DIR}/logs/*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 0640 root root
}

/var/log/easydeploy-agent*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 root root
}
LOGROTEOF

    log_ok "Rotacao de logs configurada"
}

# =============================================================================
# Create management scripts
# =============================================================================
create_management_scripts() {
    log_step "Criando scripts de gerenciamento"

    # Wrapper script
    cat > /usr/local/bin/easydeploy-agent <<'WRAPPEREOF'
#!/usr/bin/env bash
set -euo pipefail

AGENT_DIR="/opt/easydeploy-agent"
ACTION="${1:-status}"

case "$ACTION" in
    start)
        echo "Iniciando EasyDeploy Agent..."
        systemctl start easydeploy-agent
        echo "Agente iniciado."
        ;;
    stop)
        echo "Parando EasyDeploy Agent..."
        systemctl stop easydeploy-agent
        echo "Agente parado."
        ;;
    restart)
        echo "Reiniciando EasyDeploy Agent..."
        systemctl restart easydeploy-agent
        echo "Agente reiniciado."
        ;;
    status)
        systemctl status easydeploy-agent --no-pager
        echo ""
        echo "--- Health Check ---"
        curl -sf http://localhost:9091/health 2>/dev/null | jq . || echo "Agente nao esta respondendo."
        ;;
    logs)
        shift
        if [ -f "${AGENT_DIR}/docker-compose.yml" ]; then
            cd "${AGENT_DIR}" && docker compose logs "${@:---tail 100 -f}"
        else
            journalctl -u easydeploy-agent "${@:---tail 100 -f}"
        fi
        ;;
    health)
        curl -sf http://localhost:9091/health 2>/dev/null | jq . || echo "Agente nao esta respondendo."
        ;;
    metrics)
        curl -sf http://localhost:9091/metrics/json 2>/dev/null | jq . || echo "Metricas nao disponiveis."
        ;;
    containers)
        curl -sf http://localhost:9091/containers 2>/dev/null | jq . || echo "Nao foi possivel listar containers."
        ;;
    info)
        curl -sf http://localhost:9091/info 2>/dev/null | jq . || echo "Info nao disponivel."
        ;;
    config)
        cat "${AGENT_DIR}/config/.env"
        ;;
    update)
        echo "Atualizando EasyDeploy Agent..."
        if [ -f "${AGENT_DIR}/docker-compose.yml" ]; then
            cd "${AGENT_DIR}"
            docker compose pull
            docker compose up -d
        else
            echo "Atualizacao manual necessaria para instalacao binaria."
            echo "Baixe o novo binario e reinicie o servico."
        fi
        ;;
    uninstall)
        echo "Desinstalando EasyDeploy Agent..."
        read -p "Tem certeza? (y/N): " confirm
        if [[ "$confirm" =~ ^[Yy]$ ]]; then
            systemctl stop easydeploy-agent 2>/dev/null || true
            systemctl disable easydeploy-agent 2>/dev/null || true
            rm -f /etc/systemd/system/easydeploy-agent.service
            systemctl daemon-reload
            if [ -f "${AGENT_DIR}/docker-compose.yml" ]; then
                cd "${AGENT_DIR}" && docker compose down -v 2>/dev/null || true
                docker rmi easydeploy/agent 2>/dev/null || true
            fi
            rm -rf "${AGENT_DIR}"
            rm -f /usr/local/bin/easydeploy-agent
            rm -f /etc/logrotate.d/easydeploy-agent
            echo "Agente desinstalado com sucesso."
        else
            echo "Cancelado."
        fi
        ;;
    *)
        echo "EasyDeploy Agent - Comandos:"
        echo ""
        echo "  easydeploy-agent start       Inicia o agente"
        echo "  easydeploy-agent stop        Para o agente"
        echo "  easydeploy-agent restart     Reinicia o agente"
        echo "  easydeploy-agent status      Status do agente"
        echo "  easydeploy-agent logs        Ver logs (use -f para follow)"
        echo "  easydeploy-agent health      Verificar saude"
        echo "  easydeploy-agent metrics     Ver metricas do servidor"
        echo "  easydeploy-agent containers  Listar containers gerenciados"
        echo "  easydeploy-agent info        Informacoes do agente"
        echo "  easydeploy-agent config      Ver configuracao"
        echo "  easydeploy-agent update      Atualizar agente"
        echo "  easydeploy-agent uninstall   Desinstalar agente"
        echo ""
        ;;
esac
WRAPPEREOF

    chmod +x /usr/local/bin/easydeploy-agent

    log_ok "Comando 'easydeploy-agent' disponivel globalmente"
}

# =============================================================================
# Register with orchestrator
# =============================================================================
register_with_orchestrator() {
    log_step "Registrando agente no orchestrator"

    local register_payload
    register_payload=$(cat <<JSONEOF
{
    "server_id": "${SERVER_ID}",
    "name": "${SERVER_NAME}",
    "ip_address": "${AGENT_ADVERTISE_IP}",
    "grpc_port": ${GRPC_PORT},
    "http_port": ${HTTP_PORT},
    "max_containers": ${MAX_CONTAINERS},
    "tls_enabled": ${TLS_ENABLED},
    "version": "${EASYDEPLOY_VERSION}"
}
JSONEOF
    )

    local response
    local http_code
    http_code=$(curl -sf -o /tmp/easydeploy-register-response.json -w "%{http_code}" \
        --max-time 15 \
        -X POST \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer ${ORCHESTRATOR_API_KEY}" \
        -d "$register_payload" \
        "${ORCHESTRATOR_URL}/api/v1/servers/register" 2>/dev/null) || true

    if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
        log_ok "Agente registrado com sucesso no orchestrator"
        if [ -f /tmp/easydeploy-register-response.json ]; then
            cat /tmp/easydeploy-register-response.json | jq . 2>/dev/null || true
            rm -f /tmp/easydeploy-register-response.json
        fi
    else
        log_warn "Nao foi possivel registrar automaticamente (HTTP ${http_code:-timeout})."
        log_warn "Isso e normal se o orchestrator ainda nao esta acessivel."
        log_info "O agente tentara registrar via heartbeat quando o orchestrator estiver disponivel."
        log_info "Ou registre manualmente pelo painel admin."
    fi
}

# =============================================================================
# Health check
# =============================================================================
final_health_check() {
    log_step "Verificacao final de saude"

    sleep 5

    # Check if service is running
    if systemctl is-active easydeploy-agent &>/dev/null; then
        log_ok "Servico easydeploy-agent: ativo"
    else
        log_warn "Servico easydeploy-agent: inativo"
        log_info "Verificando logs..."
        if [ "$INSTALL_MODE" = "docker" ]; then
            cd "${AGENT_INSTALL_DIR}" && docker compose logs --tail 20 2>/dev/null || true
        else
            journalctl -u easydeploy-agent --tail 20 --no-pager 2>/dev/null || true
        fi
    fi

    # Check HTTP health endpoint
    local retries=0
    local max_retries=15
    while [ "$retries" -lt "$max_retries" ]; do
        if curl -sf --max-time 3 "http://localhost:${HTTP_PORT}/healthz" &>/dev/null; then
            log_ok "Health check HTTP: respondendo"
            break
        fi
        retries=$((retries + 1))
        sleep 2
    done

    if [ "$retries" -ge "$max_retries" ]; then
        log_warn "Health check HTTP: nao respondeu (pode estar inicializando)"
    fi

    # Check Docker connectivity
    if docker info &>/dev/null; then
        log_ok "Docker: acessivel"
        local containers
        containers=$(docker ps --format '{{.Names}}' | wc -l)
        log_ok "Containers rodando: ${containers}"
    else
        log_warn "Docker: nao acessivel"
    fi
}

# =============================================================================
# Save agent info
# =============================================================================
save_agent_info() {
    cat > "${AGENT_INSTALL_DIR}/.agent-info" <<INFOEOF
# ============================================================================
# EasyDeploy Agent - Informacoes
# Instalado em: $(date '+%Y-%m-%d %H:%M:%S')
# ============================================================================
SERVER_ID=${SERVER_ID}
SERVER_NAME=${SERVER_NAME}
INSTALL_MODE=${INSTALL_MODE}
ORCHESTRATOR_URL=${ORCHESTRATOR_URL}
AGENT_ADVERTISE_IP=${AGENT_ADVERTISE_IP}
PUBLIC_IP=${PUBLIC_IP}
PRIVATE_IP=${PRIVATE_IP}
GRPC_PORT=${GRPC_PORT}
HTTP_PORT=${HTTP_PORT}
MAX_CONTAINERS=${MAX_CONTAINERS}
INFOEOF
    chmod 600 "${AGENT_INSTALL_DIR}/.agent-info"
}

# =============================================================================
# Print final instructions
# =============================================================================
print_final_instructions() {
    echo ""
    echo -e "${BOLD}========================================================================${NC}"
    echo -e "${GREEN}${BOLD}  EasyDeploy PaaS Agent - Instalacao Completa!${NC}"
    echo -e "${BOLD}========================================================================${NC}"
    echo ""
    echo -e "  ${BOLD}Server ID:${NC}         ${CYAN}${SERVER_ID}${NC}"
    echo -e "  ${BOLD}Server Name:${NC}       ${CYAN}${SERVER_NAME}${NC}"
    echo -e "  ${BOLD}Install Mode:${NC}      ${CYAN}${INSTALL_MODE}${NC}"
    echo ""
    echo -e "  ${BOLD}Orchestrator:${NC}      ${CYAN}${ORCHESTRATOR_URL}${NC}"
    echo -e "  ${BOLD}IP Publico:${NC}        ${CYAN}${PUBLIC_IP}${NC}"
    echo -e "  ${BOLD}IP Privado:${NC}        ${CYAN}${PRIVATE_IP}${NC}"
    echo -e "  ${BOLD}gRPC:${NC}              ${CYAN}${AGENT_ADVERTISE_IP}:${GRPC_PORT}${NC}"
    echo -e "  ${BOLD}HTTP:${NC}              ${CYAN}http://${AGENT_ADVERTISE_IP}:${HTTP_PORT}${NC}"
    echo ""
    echo -e "  ${BOLD}Comandos uteis:${NC}"
    echo -e "    Status:       ${CYAN}easydeploy-agent status${NC}"
    echo -e "    Logs:         ${CYAN}easydeploy-agent logs -f${NC}"
    echo -e "    Health:       ${CYAN}easydeploy-agent health${NC}"
    echo -e "    Metricas:     ${CYAN}easydeploy-agent metrics${NC}"
    echo -e "    Containers:   ${CYAN}easydeploy-agent containers${NC}"
    echo -e "    Restart:      ${CYAN}easydeploy-agent restart${NC}"
    echo -e "    Desinstalar:  ${CYAN}easydeploy-agent uninstall${NC}"
    echo ""
    echo -e "  ${BOLD}Arquivos:${NC}"
    echo -e "    Config:       ${CYAN}${AGENT_INSTALL_DIR}/config/.env${NC}"
    echo -e "    Logs:         ${CYAN}${AGENT_INSTALL_DIR}/logs/${NC}"
    echo -e "    Info:         ${CYAN}${AGENT_INSTALL_DIR}/.agent-info${NC}"
    echo ""
    echo -e "${YELLOW}${BOLD}  PROXIMO PASSO:${NC}"
    echo -e "    No painel admin do EasyDeploy, va em Servidores e verifique se"
    echo -e "    este servidor aparece como conectado."
    echo ""
    echo -e "    Se configurou DNS, aponte *.apps.seu-dominio para: ${CYAN}${PUBLIC_IP}${NC}"
    echo ""
    echo -e "    Log da instalacao: ${LOG_FILE}"
    echo ""
    echo -e "${BOLD}========================================================================${NC}"
    echo ""
}

# =============================================================================
# Non-interactive mode (for curl | bash installs)
# =============================================================================
run_noninteractive() {
    # Validate required env vars
    if [ -z "${ORCHESTRATOR_URL:-}" ]; then
        log_error "ORCHESTRATOR_URL e obrigatoria no modo nao-interativo."
        exit 1
    fi
    if [ -z "${API_KEY:-}" ]; then
        log_error "API_KEY e obrigatoria no modo nao-interativo."
        exit 1
    fi

    SERVER_ID="${SERVER_ID:-$(generate_server_id)}"
    SERVER_NAME="${SERVER_NAME:-$(hostname -f 2>/dev/null || hostname)}"
    ORCHESTRATOR_API_KEY="${API_KEY}"
    GRPC_PORT="${GRPC_PORT:-9090}"
    HTTP_PORT="${HTTP_PORT:-9091}"
    AGENT_ADVERTISE_IP="${AGENT_IP:-$(get_public_ip)}"
    DOCKER_HOST="${DOCKER_HOST:-unix:///var/run/docker.sock}"
    MAX_CONTAINERS="${MAX_CONTAINERS:-50}"
    HEARTBEAT_INTERVAL="${HEARTBEAT_INTERVAL:-30}"
    HEARTBEAT_TIMEOUT="${HEARTBEAT_TIMEOUT:-10}"
    TLS_ENABLED="${TLS_ENABLED:-false}"
    TLS_CERT_FILE="${TLS_CERT_FILE:-}"
    TLS_KEY_FILE="${TLS_KEY_FILE:-}"
    METRICS_ENABLED="${METRICS_ENABLED:-true}"
    METRICS_INTERVAL="${METRICS_INTERVAL:-60}"
    LOG_LEVEL="${LOG_LEVEL:-info}"
    LOG_JSON="${LOG_JSON:-true}"
    INSTALL_MODE="${INSTALL_MODE:-docker}"
    SETUP_FIREWALL="${SETUP_FIREWALL:-true}"
    TIMEZONE="${TIMEZONE:-$(timedatectl show --property=Timezone --value 2>/dev/null || echo 'America/Sao_Paulo')}"
}

# =============================================================================
# Main
# =============================================================================
main() {
    echo ""
    echo -e "${BOLD}========================================================================${NC}"
    echo -e "${BOLD}        EasyDeploy PaaS - Instalador do Agent v${EASYDEPLOY_VERSION}${NC}"
    echo -e "${BOLD}========================================================================${NC}"
    echo ""

    preflight_checks

    # Check if running in non-interactive mode (piped or env vars set)
    if [ -n "${ORCHESTRATOR_URL:-}" ] && [ -n "${API_KEY:-}" ] && [ ! -t 0 ]; then
        log_info "Modo nao-interativo detectado."
        run_noninteractive
    else
        collect_configuration
    fi

    install_system_dependencies
    install_docker
    install_go
    setup_firewall
    create_directories
    generate_env_file

    case "$INSTALL_MODE" in
        docker)
            install_docker_mode
            ;;
        binary|systemd)
            install_binary_mode
            ;;
    esac

    setup_systemd_service
    setup_logrotate
    create_management_scripts
    save_agent_info
    register_with_orchestrator
    final_health_check
    print_final_instructions
}

# Run
main "$@"
