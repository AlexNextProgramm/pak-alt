<?php

namespace App\Controller\Ajax\Company;

use App\Controller\AjaxController;
use App\Form\Form;
use App\Model\CompanyModel;
use Pet\Request\Request;
use Pet\Router\Error as RE;
use Pet\Router\Response;
use Pet\Session\Session;

class Save extends AjaxController
{
    public function helper(): array
    {
        $token = attr('csrf-token');
        unset(Request::$attribute['csrf-token']);

        if ($token != Session::get('csrf-token')) {
            RE::setHttp(RE::STATUS_HTTP::FORBIDDEN);
            Response::die('Не действительный токен csrf или проблема с сессиями на сервере');
        }

        $fields = Form::normalizerFields();
        $name = trim($fields['name'] ?? '');
        $email = trim($fields['email'] ?? '');
        $id = !empty($fields['id']) ? (int)$fields['id'] : null;

        if ($id) {
            $model = new CompanyModel(['id' => $id]);
            if (!$model->isInfo()) {
                return Form::errorInput('id', 'Компания не найдена');
            }

            $model->set([
                'name' => $name,
                'email' => $email ?: null,
            ]);

            return [
                'success' => true,
                'id' => (int)$model->get('id'),
            ];
        }

        if ($name === '') {
            return Form::errorInput('name', 'Укажите название компании');
        }

        $model = (new CompanyModel())->ifExistSetOrCreate([
            'name' => $name,
            'email' => $email ?: null,
        ]);

        return [
            'success' => true,
            'id' => (int)$model->get('id'),
        ];
    }
}