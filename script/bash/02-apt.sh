#!/bin/bash

MODULE_TITLE="Обновление apt"
MODULE_FUNC="apt_update"

apt_update() {
    log "Обновление списка пакетов..."
    sudo apt update -y
}
