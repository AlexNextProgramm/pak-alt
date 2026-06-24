<?php

namespace App\Controller;

use APP\Module\Auth;
use Pet\Controller;
use Pet\Errors\AppException;
use Pet\Router\Response;

class AjaxController extends Controller
{
    const NAMESPASE = APP . "\\Controller\\Ajax\\";
    public function __construct()
    {
        Response::set(Response::TYPE_JSON);
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
