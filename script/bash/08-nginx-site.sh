#!/bin/bash

MODULE_TITLE="Nginx (конфиг проекта)"
MODULE_FUNC="configure_nginx_site"

prompt_nginx_server_name() {
    if [ -n "${NGINX_SERVER_NAME:-}" ]; then
        log "server_name: $NGINX_SERVER_NAME (из NGINX_SERVER_NAME)"
        return 0
    fi

    echo ""
    read -r -p "Напишите server_name для nginx: " NGINX_SERVER_NAME

    if [ -z "$NGINX_SERVER_NAME" ]; then
        NGINX_SERVER_NAME="_"
        log "server_name не указан — используется: _"
    else
        log "server_name: $NGINX_SERVER_NAME"
    fi
}

configure_nginx_site() {
    detect_apps

    if ! $HAS_ADMIN && ! $HAS_SITE; then
        log "Ошибка: не найдены каталоги admin/public или site/public в $PROJECT_DIR"
        return 1
    fi

    prompt_nginx_server_name

    if $HAS_SITE; then
        NGINX_ROOT="${WWW_LINK}/site/public"
    else
        NGINX_ROOT="${WWW_LINK}/admin/public"
    fi

    local admin_public="${PROJECT_DIR}/admin/public"
    local nginx_admin_block=""

    if $HAS_ADMIN && $HAS_SITE; then
        nginx_admin_block=$(cat <<NGINX_ADMIN

    location ^~ /admin/ {
        alias ${admin_public}/;
        index index.php;
        try_files \$uri \$uri/ /admin/index.php?\$query_string;

        location ~ \.php\$ {
            include fastcgi_params;
            fastcgi_pass unix:${PHP_FPM_SOCK};
            fastcgi_param SCRIPT_FILENAME \$request_filename;
        }
    }
NGINX_ADMIN
)
    fi

    # PHP-FPM сокет по умолчанию, если не задан
    local fpm_sock="${PHP_FPM_SOCK:-/var/run/php/php-fpm.sock}"

    log "Настройка Nginx ($NGINX_CONF)..."

    sudo tee "$NGINX_CONF" > /dev/null <<NGINX_EOF
server {
    listen 80;
    server_name ${NGINX_SERVER_NAME};
    root ${NGINX_ROOT};
    index index.php index.html;
${nginx_admin_block}

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:${fpm_sock};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)\$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
NGINX_EOF

    if [ -f "/etc/nginx/sites-enabled/default" ]; then
        sudo rm /etc/nginx/sites-enabled/default
    fi

    if [ ! -L "/etc/nginx/sites-enabled/${PROJECT_NAME}.conf" ]; then
        sudo ln -sf "$NGINX_CONF" "/etc/nginx/sites-enabled/${PROJECT_NAME}.conf"
    fi

    if sudo nginx -t; then
        sudo systemctl reload nginx
        log "Nginx настроен и перезагружен."
    else
        log "Ошибка конфигурации Nginx — проверьте $NGINX_CONF вручную."
    fi
}
