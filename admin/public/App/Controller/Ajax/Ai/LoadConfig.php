<?php

namespace App\Controller\Ajax\Ai;

use App\Controller\AjaxController;
use App\Module\Ai\ParseConfig;
use App\Module\Auth;
use Pet\Router\HTTP;
use Pet\Router\Response;

class LoadConfig extends AjaxController
{
    public function helper(): array
    {
        Auth::init();
        if (!Auth::$isAuth) {
            Response::json(['error' => 'Нет авторизации'], HTTP::FORBIDDEN);
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
