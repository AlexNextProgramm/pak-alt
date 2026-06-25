<?php

namespace App\Controller\Ajax\Ai;

use App\Controller\AjaxController;
use App\Form\Form;
use App\Module\Ai\ParseConfig;
use App\Module\Auth;
use Pet\Router\Error as RE;
use Pet\Router\Response;

class SaveConfig extends AjaxController
{
    public function helper(): array
    {
        Auth::init();
        if (!Auth::$isAuth) {
            RE::setHttp(RE::STATUS_HTTP::FORBIDDEN);
            Response::die('Нет авторизации');
        }

        $fields = Form::normalizerFields();
        $content = $fields['content'] ?? '';

        if (empty(trim($content))) {
            return Form::errorInput('content', 'Конфиг не может быть пустым');
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return Form::errorInput('content', 'Некорректный JSON: ' . json_last_error_msg());
        }

        $configPath = ParseConfig::uploadPath();
        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $written = file_put_contents($configPath, $pretty, LOCK_EX);

        if ($written === false) {
            return ['type' => 'fire', 'message' => 'Ошибка записи файла конфига'];
        }

        ParseConfig::setVariablePath($configPath);

        return [
            'type' => 'fire',
            'message' => 'Конфиг сохранён. Путь добавлен в переменную ai.parse_config',
            'isCustom' => true,
            'path' => $configPath,
        ];
    }
}
