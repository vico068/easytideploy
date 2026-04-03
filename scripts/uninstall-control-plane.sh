#!/usr/bin/env bash
# ============================================================================
# EasyDeploy PaaS - Uninstaller (Control Plane)
# Removes: Panel, Orchestrator, PostgreSQL, Redis, Traefik and all data.
# ============================================================================
set -euo pipefail

readonly INSTALL_DIR="/opt/easydeploy"

# Cores
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly BOLD='\033[1m'
readonly NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_step() { echo -e "\n${BLUE}${BOLD}>>> $1${NC}"; }
log_ok() { echo -e "${GREEN}  [OK]${NC} $1"; }

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}[ERROR]${NC} Este script deve ser executado como root (sudo)."
    exit 1
fi

echo ""
echo -e "${BOLD}========================================================================${NC}"
echo -e "${RED}${BOLD}  EasyDeploy PaaS - Desinstalador do Control Plane${NC}"
echo -e "${BOLD}========================================================================${NC}"
echo ""
echo -e "${YELLOW}ATENCAO: Esta acao ira remover TODOS os componentes do EasyDeploy.${NC}"
echo ""

# Options
REMOVE_DATA=false
REMOVE_DOCKER=false
REMOVE_BACKUPS=false

echo -e "${BOLD}O que deseja remover?${NC}"
echo ""

echo -en "  Remover volumes Docker (banco de dados, Redis)? [y/N]: "
read -r answer
[[ "$answer" =~ ^[Yy]$ ]] && REMOVE_DATA=true

echo -en "  Remover backups? [y/N]: "
read -r answer
[[ "$answer" =~ ^[Yy]$ ]] && REMOVE_BACKUPS=true

echo -en "  Remover Docker Engine do sistema? [y/N]: "
read -r answer
[[ "$answer" =~ ^[Yy]$ ]] && REMOVE_DOCKER=true

echo ""
echo -e "${RED}${BOLD}  Confirma a desinstalacao completa? (y/N): ${NC}"
read -r confirm
if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    log_warn "Desinstalacao cancelada."
    exit 0
fi

echo ""

# Stop and disable systemd service
log_step "Parando servicos"
systemctl stop easydeploy.service 2>/dev/null || true
systemctl disable easydeploy.service 2>/dev/null || true
rm -f /etc/systemd/system/easydeploy.service
systemctl daemon-reload
log_ok "Servico systemd removido"

# Stop containers
if [ -f "${INSTALL_DIR}/docker-compose.yml" ]; then
    log_step "Parando containers"
    cd "${INSTALL_DIR}"
    if [ "$REMOVE_DATA" = true ]; then
        docker compose down -v --remove-orphans 2>/dev/null || true
        log_ok "Containers e volumes removidos"
    else
        docker compose down --remove-orphans 2>/dev/null || true
        log_ok "Containers removidos (volumes preservados)"
    fi
fi

# Remove Docker images
log_step "Removendo imagens Docker"
docker rmi easydeploy/orchestrator 2>/dev/null || true
docker rmi easydeploy/agent 2>/dev/null || true
docker rmi easydeploy/panel 2>/dev/null || true
docker network rm easydeploy_easydeploy 2>/dev/null || true
log_ok "Imagens removidas"

# Remove install directory
log_step "Removendo arquivos"
if [ "$REMOVE_BACKUPS" = true ]; then
    rm -rf "${INSTALL_DIR}"
    log_ok "Diretorio ${INSTALL_DIR} removido completamente"
else
    # Keep backups
    if [ -d "${INSTALL_DIR}/backups" ]; then
        local backup_tmp="/tmp/easydeploy-backups-$(date +%s)"
        mv "${INSTALL_DIR}/backups" "$backup_tmp"
        rm -rf "${INSTALL_DIR}"
        mkdir -p "${INSTALL_DIR}"
        mv "$backup_tmp" "${INSTALL_DIR}/backups"
        log_ok "Diretorio removido (backups preservados em ${INSTALL_DIR}/backups)"
    else
        rm -rf "${INSTALL_DIR}"
        log_ok "Diretorio ${INSTALL_DIR} removido"
    fi
fi

# Remove cron jobs
log_step "Removendo tarefas agendadas"
(crontab -l 2>/dev/null | grep -v "easydeploy" | crontab -) 2>/dev/null || true
log_ok "Cron jobs removidos"

# Remove logrotate
rm -f /etc/logrotate.d/easydeploy
log_ok "Logrotate removido"

# Remove log files
rm -f /var/log/easydeploy-install.log
rm -f /var/log/easydeploy-backup.log
log_ok "Logs removidos"

# Remove Docker
if [ "$REMOVE_DOCKER" = true ]; then
    log_step "Removendo Docker"
    if [ -f /etc/os-release ]; then
        source /etc/os-release
        case "$ID" in
            ubuntu|debian)
                apt-get purge -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin 2>/dev/null || true
                apt-get autoremove -y
                ;;
            almalinux|rocky)
                dnf remove -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin 2>/dev/null || true
                ;;
        esac
    fi
    rm -rf /var/lib/docker /var/lib/containerd
    rm -f /etc/docker/daemon.json
    log_ok "Docker removido"
fi

echo ""
echo -e "${GREEN}${BOLD}========================================================================${NC}"
echo -e "${GREEN}${BOLD}  EasyDeploy Control Plane desinstalado com sucesso.${NC}"
echo -e "${GREEN}${BOLD}========================================================================${NC}"
echo ""
