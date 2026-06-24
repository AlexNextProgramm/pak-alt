#!/bin/bash

# Общие функции установщика (подключается из install.sh)

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

ask_yes_no() {
    local prompt="$1"
    local answer

    read -r -p "$prompt [y/N]: " answer
    case "${answer,,}" in
        y|yes|д|да) return 0 ;;
        *) return 1 ;;
    esac
}

run_module() {
    local title="$1"
    shift

    echo ""
    if ask_yes_no "Запустить модуль «${title}»?"; then
        log ">>> Модуль: ${title}"
        "$@"
    else
        log ">>> Пропуск модуля: ${title}"
    fi
}

command_exists() {
    command -v "$1" &>/dev/null
}

is_pkg_installed() {
    dpkg -s "$1" &>/dev/null
}

install_packages() {
    local missing=()

    for pkg in "$@"; do
        if is_pkg_installed "$pkg"; then
            log "$pkg уже установлен — пропуск."
        else
            missing+=("$pkg")
        fi
    done

    if [ ${#missing[@]} -eq 0 ]; then
        return 0
    fi

    log "Установка пакетов: ${missing[*]}..."
    sudo apt install -y "${missing[@]}"
}

ensure_service() {
    local service="$1"

    if ! systemctl list-unit-files "$service" &>/dev/null; then
        log "Сервис $service не найден."
        return 1
    fi

    sudo systemctl enable "$service" 2>/dev/null || true

    if systemctl is-active --quiet "$service" 2>/dev/null; then
        log "$service уже запущен."
    else
        sudo systemctl start "$service"
        log "$service запущен."
    fi
}

normalize_db_name() {
    local name="$1"
    name="$(echo "$name" | tr '[:upper:]' '[:lower:]')"
    name="$(echo "$name" | tr -- '-. ' '_')"
    name="$(echo "$name" | sed 's/[^a-z0-9_]/_/g')"
    name="$(echo "$name" | sed 's/__*/_/g; s/^_//; s/_$//')"
    echo "$name"
}

detect_apps() {
    HAS_ADMIN=false
    HAS_SITE=false
    [ -d "$PROJECT_DIR/admin/public" ] && HAS_ADMIN=true
    [ -d "$PROJECT_DIR/site/public" ] && HAS_SITE=true
}
