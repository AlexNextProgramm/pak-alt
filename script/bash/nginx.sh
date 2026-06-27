#!/bin/bash

MODULE_TITLE="Nginx (пакет)"
MODULE_FUNC="install_nginx"

install_nginx() {
    log "Проверка Nginx..."
    install_packages nginx
    ensure_service nginx
}
