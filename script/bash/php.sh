#!/bin/bash

install_php() {
    log "Проверка PHP-FPM и расширений..."
    install_packages \
        php-fpm php-cli php-mysql php-mbstring php-xml php-curl \
        php-zip php-gd php-bcmath php-json php-tokenizer php-imap

    local ini
    local php_fpm_ini php_cli_ini

    php_fpm_ini="$(find /etc/php -name "php.ini" -path "*/fpm/*" 2>/dev/null | head -1)"
    php_cli_ini="$(find /etc/php -name "php.ini" -path "*/cli/*" 2>/dev/null | head -1)"

    for ini in "$php_fpm_ini" "$php_cli_ini"; do
        if [ -n "$ini" ] && grep -q '^short_open_tag = On' "$ini" 2>/dev/null; then
            log "short_open_tag уже включён в $ini."
        elif [ -n "$ini" ]; then
            log "Включение short_open_tag в $ini..."
            sudo sed -i 's/^short_open_tag = Off/short_open_tag = On/' "$ini" \
                || echo "short_open_tag = On" | sudo tee -a "$ini" >/dev/null
        fi
    done

    PHP_FPM_SERVICE="$(systemctl list-units --type=service --all 2>/dev/null | grep -oE 'php[0-9.]*-fpm\.service' | head -1)"
    if [ -n "$PHP_FPM_SERVICE" ]; then
        ensure_service "$PHP_FPM_SERVICE"
    fi

    PHP_FPM_SOCK="$(find /var/run/php -name 'php*-fpm.sock' 2>/dev/null | head -1)"
    if [ -z "$PHP_FPM_SOCK" ]; then
        PHP_FPM_SOCK="/var/run/php/php-fpm.sock"
    fi

    export PHP_FPM_SOCK
}
