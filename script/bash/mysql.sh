#!/bin/bash

mysql_service_running() {
    systemctl is-active --quiet mysql 2>/dev/null
}

mysql_server_installed() {
    is_pkg_installed mysql-server-8.0 \
        || dpkg -l mysql-server-8.0 2>/dev/null | grep -qE '^(ii|iF|iU|iH)'
}

mysql_has_data_dir() {
    [ -f /var/lib/mysql/ibdata1 ]
}

install_mysql() {
    if mysql_service_running; then
        log "MySQL 8.0 уже запущен — установка СУБД пропущена."
        return 0
    fi

    if mysql_server_installed; then
        log "mysql-server-8.0 уже установлен — запуск сервиса..."
        ensure_service mysql
        return 0
    fi

    if [ -d /var/lib/mysql-8.0 ]; then
        log "Найден резерв данных в /var/lib/mysql-8.0."
        log "Установка MySQL пропущена, чтобы не затереть старые базы."
        log "Восстановите данные: sudo ./restore-mysql-8.0.sh"
        return 0
    fi

    if mysql_has_data_dir; then
        log "В /var/lib/mysql уже есть данные — установка MySQL пропущена."
        log "Запустите вручную: sudo systemctl start mysql"
        return 0
    fi

    log "Установка MySQL 8.0 (mysql-server-8.0)..."
    export DEBIAN_FRONTEND=noninteractive
    install_packages mysql-server-8.0
    ensure_service mysql
}
