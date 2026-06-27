#!/bin/bash

# ============================================================
# deploy.sh — автоматический деплой для проекта
# Запускается на кроне каждую минуту.
# Проверяет git-изменения, накатывает миграции и собирает
# frontend при необходимости.
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
LOCK_FILE="/tmp/$(basename "$PROJECT_DIR")-deploy.lock"

# Создаём директорию для логов, если её нет
mkdir -p "$(dirname "$LOG_FILE")"

# Подключаем общие функции (log, log_success, log_warning, log_error,
# detect_app_dirs, has_lock_file, install_dependencies, update_composer, build_frontend)
# shellcheck source=script/bash/lib.sh
source "$PROJECT_DIR/script/bash/lib.sh"

# ═══════════════════════════════════════════════════════════
# БЛОКИРОВКА
# ═══════════════════════════════════════════════════════════

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

cleanup() {
    rm -f "$LOCK_FILE"
}
trap cleanup EXIT

cd "$PROJECT_DIR"

# ═══════════════════════════════════════════════════════════
# ШАГ 0: сканируем папки с composer.json / package.json
# ═══════════════════════════════════════════════════════════

log "Сканирование директорий с composer.json / package.json..."
APP_LIST=($(detect_app_dirs))

if [ ${#APP_LIST[@]} -eq 0 ]; then
    log_warning "Не найдено ни одного приложения с composer.json или package.json."
fi

# Устанавливаем зависимости, если нет lock-файлов
for entry in "${APP_LIST[@]}"; do
    app_dir="${entry%%:*}"
    app_name="${entry##*:}"
    install_dependencies "$app_dir" "$app_name"
done

# ═══════════════════════════════════════════════════════════
# ШАГ 1: проверяем ветку и стягиваем изменения
# ═══════════════════════════════════════════════════════════

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

# ═══════════════════════════════════════════════════════════
# ШАГ 2: обновление composer при изменении composer.json
# ═══════════════════════════════════════════════════════════

for entry in "${APP_LIST[@]}"; do
    app_dir="${entry%%:*}"
    app_name="${entry##*:}"

    if [ -f "$app_dir/composer.json" ]; then
        update_composer "$app_dir" "$app_name"
    fi
done

# ═══════════════════════════════════════════════════════════
# ШАГ 3: проверяем миграции
# ═══════════════════════════════════════════════════════════

if echo "$CHANGED_FILES" | grep -q "^migrations/"; then
    log "Обнаружены изменения в migrations/. Накатываем миграции..."

    for entry in "${APP_LIST[@]}"; do
        app_dir="${entry%%:*}"
        app_name="${entry##*:}"

        if [ -f "$app_dir/pet" ]; then
            log "Миграции $app_name..."
            (cd "$app_dir" && php pet migrate) 2>&1 | tee -a "$LOG_FILE"
            log_success "Миграции $app_name: успешно"
        fi
    done
else
    log "Изменений в migrations/ нет. Пропускаем миграции."
fi

# ═══════════════════════════════════════════════════════════
# ШАГ 4: сборка frontend при изменении src/ или package.json
# ═══════════════════════════════════════════════════════════

for entry in "${APP_LIST[@]}"; do
    app_dir="${entry%%:*}"
    app_name="${entry##*:}"

    if [ -f "$app_dir/package.json" ]; then
        build_frontend "$app_dir" "$app_name"
    fi
done

log_success "Деплой завершён."