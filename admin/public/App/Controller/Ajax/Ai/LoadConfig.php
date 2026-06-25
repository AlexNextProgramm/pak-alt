<?php

namespace App\Controller\Ajax\Ai;

use App\Controller\AjaxController;
use App\Module\Ai\ParseConfig;
use App\Module\Auth;
use Pet\Router\Error as RE;
use Pet\Router\Response;

class LoadConfig extends AjaxController
{
    public function helper(): array
    {
        Auth::init();
        if (!Auth::$isAuth) {
            RE::setHttp(RE::STATUS_HTTP::FORBIDDEN);
            Response::die('Нет авторизации');
        }

        $loaded = ParseConfig::load();
        if (isset($loaded['error'])) {
            return ['type' => 'fire', 'message' => $loaded['error']];
        }

        return [
            'type' => 'success',
            'content' => $loaded['content'],
            'isCustom' => $loaded['isCustom'],
            'path' => $loaded['path'],
        ];
    }
}
