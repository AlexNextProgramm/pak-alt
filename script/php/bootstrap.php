<?php

declare(strict_types=1);

$adminRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'admin';

require_once $adminRoot . '/vendor/autoload.php';
require_once $adminRoot . '/vendor/pet/framework/function.php';
require_once $adminRoot . '/config.constant.php';

spl_autoload_register(static function (string $class): bool {
    $file = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    $path = PUBLIC_DIR . DIRECTORY_SEPARATOR . $file;

    if (file_exists($path)) {
        require_once $path;

        return true;
    }

    return false;
});

if (defined('EXTERNAL_MODULE')) {
    spl_autoload_register(static function (string $class): bool {
        foreach (explode('||', EXTERNAL_MODULE) as $module) {
            $path = ROOT . DIRECTORY_SEPARATOR . trim($module) . DIRECTORY_SEPARATOR;
            $file = $path . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

            if (file_exists($file)) {
                require_once $file;

                return true;
            }
        }

        return false;
    });
}
