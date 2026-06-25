<?php

namespace App\Controller\Ajax\Ai;

use App\Controller\AjaxController;
use App\Module\Ai\ParseConfig;
use App\Module\Auth;
use Pet\Router\Error as RE;
use Pet\Router\Response;

class ResetConfig extends AjaxController
{
    public function helper(): array
    {
        Auth::init();
        if (!Auth::$isAuth) {
            RE::setHttp(RE::STATUS_HTTP::FORBIDDEN);
            Response::die('Нет авторизации');
        }

        ParseConfig::deleteUploadFile();
        ParseConfig::clearVariable();

        $defaultPath = ParseConfig::defaultPath();
        if (!$defaultPath) {
            return ['type' => 'fire', 'message' => 'Файл конфига по умолчанию не найден'];
        }

        $content = file_get_contents($defaultPath);
        if ($content === false) {
            return ['type' => 'fire', 'message' => 'Ошибка чтения конфига по умолчанию'];
        }

        return [
            'type' => 'fire',
            'message' => 'Восстановлен конфиг по умолчанию',
            'content' => $content,
            'isCustom' => false,
            'path' => $defaultPath,
        ];
    }
}
