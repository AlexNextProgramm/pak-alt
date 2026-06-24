<?php

#Константы Папок
define('ROOT', __DIR__);
define('DS', DIRECTORY_SEPARATOR);
define('ENV', '.env');
setConstantEnv(ROOT);

# Внешний env
setConstantEnv(ROOT . "/..");