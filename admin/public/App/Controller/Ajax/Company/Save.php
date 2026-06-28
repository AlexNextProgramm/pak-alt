<?php

namespace App\Controller\Ajax\Company;

use App\Controller\AjaxController;
use App\Form\Form;
use App\Model\CompanyModel;

class Save extends AjaxController
{
    public function helper(): array
    {
        AjaxController::checkCsrf();

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