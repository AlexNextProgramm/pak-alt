<?php

namespace App\Controller;

use Module\Gaitcha\GaitchaEndpoint;
use Pet\Request\Request;

/**
 * Контроллер для эндпоинта инициализации Gaitcha.
 * Маршрут: POST /captcha/init
 */
class GaitchaController
{
    public function init(Request $request): void
    {
        $requestBody = json_decode((string) file_get_contents('php://input'), true);
        $endpoint = new GaitchaEndpoint();
        $endpoint->handle(is_array($requestBody) ? $requestBody : []);
    }
}