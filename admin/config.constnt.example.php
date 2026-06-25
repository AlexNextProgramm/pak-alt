<?php

#Константы Папок
define('ROOT', __DIR__);
define('DS', DIRECTORY_SEPARATOR);
define('ENV', '.env');

# NODE_BIN — путь к node для парсера Excel (/ai, cron). Пример в .env.dev:
# NODE_BIN = /usr/bin/node

setConstantEnv(ROOT);

# Внешний env
setConstantEnv(ROOT . "/..");