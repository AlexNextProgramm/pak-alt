#!/bin/bash

install_nginx() {
    log "Проверка Nginx..."
    install_packages nginx
    ensure_service nginx
}
