#!/bin/bash

apt_update() {
    log "Обновление списка пакетов..."
    sudo apt update -y
}
