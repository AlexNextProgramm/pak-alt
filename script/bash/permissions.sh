#!/bin/bash

MODULE_TITLE="Права доступа"
MODULE_FUNC="setup_permissions"

setup_permissions() {
    log "Настройка прав доступа..."

    for app_dir in "$PROJECT_DIR"/*/; do
        [ -d "$app_dir" ] || continue
        local assets_dir="${app_dir}public/view/assets"
        if [ -d "$assets_dir" ]; then
            sudo chown -R www-data:www-data "$assets_dir" 2>/dev/null || true
        fi
    done

    sudo chmod -R 755 "$PROJECT_DIR" 2>/dev/null || true
}
