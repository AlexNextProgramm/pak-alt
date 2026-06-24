<?php

namespace App\Controller\Ajax\Variable;

use App\Controller\AjaxController;
use App\Form\Form;
use Pet\Request\Request;
use Pet\Router\Error as RE;
use Pet\Router\Response;
use Pet\Session\Session;

class Delete extends AjaxController
{
    public function helper(): array
    {
        $token = attr('csrf-token');
        unset(Request::$attribute['csrf-token']);

        if ($token != Session::get('csrf-token')) {
            RE::setHttp(RE::STATUS_HTTP::FORBIDDEN);
            Response::die('Не действительный токен csrf или проблема с сессиями на сервере');
        }

        return Form::errorInput('id', 'Удаление переменных запрещено. Список задаётся миграциями');
    }
}
