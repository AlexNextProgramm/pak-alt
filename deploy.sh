#!/bin/bash

# ============================================================
# deploy.sh — автоматический деплой для проекта Gorning
# Запускается на кроне каждую минуту.
# Проверяет git-изменения, накатывает миграции и собирает
# frontend (admin + site) при необходимости.
# ============================================================

set -e

# ═══════════════════════════════════════════════════════════
# КОНФИГУРАЦИЯ
# ═══════════════════════════════════════════════════════════

# Ветка, с которой работает деплой
BRANCH="main"

# Путь определяется автоматически — откуда запущен скрипт
PROJECT_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
LOG_FILE="$PROJECT_DIR/log/deploy.log"
LOCK_FILE="/tmp/gorning-deploy.lock"

# Цвета для вывода
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Создаём директорию для логов, если её нет
mkdir -p "$(dirname "$LOG_FILE")"

log() {
    local message="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo -e "$message"
    echo "$message" >> "$LOG_FILE"
}

log_success() {
    log "${GREEN}$1${NC}"
}

log_warning() {
    log "${YELLOW}$1${NC}"
}

log_error() {
    log "${RED}$1${NC}"
}

# Проверка блокировки — чтобы не запустить два экземпляра одновременно
if [ -f "$LOCK_FILE" ]; then
    LOCK_PID=$(cat "$LOCK_FILE")
    if kill -0 "$LOCK_PID" 2>/dev/null; then
        log_warning "Предыдущий деплой ещё выполняется (PID: $LOCK_PID). Пропускаем."
        exit 0
    else
        log_warning "Найден stale lock-файл (PID: $LOCK_PID). Удаляем."
        rm -f "$LOCK_FILE"
    fi
fi

echo $$ > "$LOCK_FILE"

# Функция очистки lock-файла при выходе
cleanup() {
    rm -f "$LOCK_FILE"
}
trap cleanup EXIT

cd "$PROJECT_DIR"

# --- Шаг 0: проверка зависимостей (первичная установка) ---

install_dependencies() {
    local app_dir="$1"
    local app_name="$2"

    # Проверяем vendor (composer)
    if [ ! -d "$app_dir/vendor" ]; then
        log "vendor не найден в $app_name. Устанавливаем composer dependencies..."
        cd "$app_dir"
        if composer install --no-interaction 2>&1 >> "$LOG_FILE"; then
            log_success "composer install ($app_name): успешно"
        else
            log_error "composer install ($app_name): ошибка!"
        fi
        cd "$PROJECT_DIR"
    fi

    # Проверяем node_modules
    if [ ! -d "$app_dir/node_modules" ]; then
        log "node_modules не найден в $app_name. Устанавливаем npm dependencies..."
        cd "$app_dir"
        if npm install 2>&1 >> "$LOG_FILE"; then
            log_success "npm install ($app_name): успешно"
        else
            log_error "npm install ($app_name): ошибка!"
        fi
        cd "$PROJECT_DIR"
    fi
}

install_dependencies "$PROJECT_DIR/admin" "admin"
install_dependencies "$PROJECT_DIR/site" "site"

# --- Шаг 1: проверяем ветку и стягиваем изменения ---

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)

log "Текущая ветка: $CURRENT_BRANCH, целевая ветка: $BRANCH"

# Если текущая ветка не совпадает с целевой — переключаемся
if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
    log_warning "Переключаемся на ветку $BRANCH..."
    git checkout "$BRANCH" 2>&1 >> "$LOG_FILE"
fi

# Сохраняем текущий HEAD до pull
OLD_HEAD=$(git rev-parse HEAD)

# Стягиваем изменения (явно указываем ветку)
log "Стягиваем изменения из origin/$BRANCH..."
git pull origin "$BRANCH" 2>&1 >> "$LOG_FILE"

# Новый HEAD после pull
NEW_HEAD=$(git rev-parse HEAD)

# Если HEAD не изменился — коммитов не было, выходим
if [ "$OLD_HEAD" = "$NEW_HEAD" ]; then
    log_success "Изменений нет. Пропускаем."
    exit 0
fi

log_success "Обнаружены новые коммиты: $OLD_HEAD → $NEW_HEAD"

# Список изменённых файлов между старым и новым HEAD
CHANGED_FILES=$(git diff --name-only "$OLD_HEAD" "$NEW_HEAD")

# --- Шаг 2: проверяем composer.json (обновление PHP-зависимостей) ---

update_composer() {
    local app_dir="$1"
    local app_name="$2"
    local composer_path="${app_dir#${PROJECT_DIR}/}composer.json"

    if echo "$CHANGED_FILES" | grep -q "^${composer_path}"; then
        log "Обнаружены изменения в composer.json ($app_name). Обновляем composer dependencies..."
        cd "$app_dir"
        if composer update --no-interaction --with-dependencies 2>&1 >> "$LOG_FILE"; then
            log_success "composer update ($app_name): успешно"
        else
            log_error "composer update ($app_name): ошибка!"
        fi
        cd "$PROJECT_DIR"
    else
        log "Изменений в composer.json ($app_name) нет. Пропускаем."
    fi
}

update_composer "$PROJECT_DIR/admin" "admin"
update_composer "$PROJECT_DIR/site" "site"

# --- Шаг 3: проверяем миграции ---

if echo "$CHANGED_FILES" | grep -q "^migrations/"; then
    log "Обнаружены изменения в migrations/. Накатываем миграции..."

    # Admin
    log "Миграции admin..."
    cd "$PROJECT_DIR/admin"
    if php pet migrate 2>&1 >> "$LOG_FILE"; then
        log_success "Миграции admin: успешно"
    else
        log_error "Миграции admin: ошибка!"
    fi

    # Site
    log "Миграции site..."
    cd "$PROJECT_DIR/site"
    if php pet migrate 2>&1 >> "$LOG_FILE"; then
        log_success "Миграции site: успешно"
    else
        log_error "Миграции site: ошибка!"
    fi

    cd "$PROJECT_DIR"
else
    log "Изменений в migrations/ нет. Пропускаем миграции."
fi

# --- Шаг 4: проверяем frontend (admin/src) ---

if echo "$CHANGED_FILES" | grep -q "^admin/src/"; then
    log "Обнаружены изменения в admin/src/. Собираем build admin..."

    cd "$PROJECT_DIR/admin"
    if npm run build 2>&1 >> "$LOG_FILE"; then
        log_success "Build admin: успешно"
    else
        log_error "Build admin: ошибка!"
    fi

    cd "$PROJECT_DIR"
else
    log "Изменений в admin/src/ нет. Пропускаем build admin."
fi

# --- Шаг 5: проверяем frontend (site/src) ---

if echo "$CHANGED_FILES" | grep -q "^site/src/"; then
    log "Обнаружены изменения в site/src/. Собираем build site..."

    cd "$PROJECT_DIR/site"
    if npm run build 2>&1 >> "$LOG_FILE"; then
        log_success "Build site: успешно"
    else
        log_error "Build site: ошибка!"
    fi

    cd "$PROJECT_DIR"
else
    log "Изменений в site/src/ нет. Пропускаем build site."
fi

log_success "Деплой завершён."