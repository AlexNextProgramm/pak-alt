<?php

namespace Module\Gaitcha;

use Gaitcha\AbstractEndpoint;
use Pet\Router\Response;

/**
 * Эндпоинт для инициализации Gaitcha.
 *
 * Выдаёт токен + field_name для клиентской части.
 * Маршрут: POST /captcha/init
 */
class GaitchaEndpoint extends AbstractEndpoint
{
    public function __construct()
    {
        parent::__construct(GaitchaConfig::get());
    }

    /**
     * Обрабатывает запрос на инициализацию.
     *
     * @param array $requestBody Тело запроса (JSON).
     * @return void
     */
    public function handle(array $requestBody = []): void
    {
        $data = $this->handleInit($requestBody);
        $this->sendJsonResponse($data);
    }

    public function sendJsonResponse(array $data): void
    {
        Response::json($data);
    }
}