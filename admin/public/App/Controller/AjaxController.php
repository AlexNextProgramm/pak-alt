<?php

namespace App\Controller;

use APP\Module\Auth;
use Pet\Controller;
use Pet\Errors\AppException;
use Pet\Request\Request;
use Pet\Router\Header;
use Pet\Router\HTTP;
use Pet\Router\Response;
use Pet\Session\Session;

class AjaxController extends Controller
{
    const NAMESPASE = APP . "\\Controller\\Ajax\\";

    public function __construct()
    {
        Header::json();
    }

    /**
     * Проверяет CSRF-токен из запроса.
     * Если токен недействителен — завершает выполнение через Response::json.
     */
    final public static function checkCsrf(): void
    {
        $token = attr('csrf-token');
        unset(Request::$attribute['csrf-token']);

        if ($token !== Session::get('csrf-token')) {
            Response::json(
                ['error' => 'Не действительный токен csrf или проблема с сессиями на сервере'],
                HTTP::FORBIDDEN
            );
        }
    }

    public function index()
    {
        $data = explode('_', supple('name'));
        foreach ($data as &$name) {
            $name = ucfirst($name);
        }

        $class = AjaxController::NAMESPASE . implode('\\', $data);
        if (!class_exists($class)) {
            throw new AppException("Нет такого класса ajax $class", E_ERROR);
        }
        return (new $class())->helper();
    }
}
