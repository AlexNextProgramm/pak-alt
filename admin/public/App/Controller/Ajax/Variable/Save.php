<?php

namespace App\Controller\Ajax\Variable;

use App\Controller\AjaxController;
use App\Form\Form;
use Model\VariableModel;
use Pet\Errors\AppException;

class Save extends AjaxController
{
    public function helper(): array
    {
        AjaxController::checkCsrf();

        $fields = Form::normalizerFields();
        $type = trim($fields['type'] ?? '');
        $name = trim($fields['name'] ?? '');
        $value = (string)($fields['value'] ?? '');
        $id = !empty($fields['id']) ? (int)$fields['id'] : null;

        if ($id) {
            $model = new VariableModel(['id' => $id]);
            if (!$model->isInfo()) {
                return Form::errorInput('id', 'Переменная не найдена');
            }

            $model->set(['value' => $value]);

            return [
                'success' => true,
                'id' => (int)$model->get('id'),
            ];
        }

        if ($type === '') {
            return Form::errorInput('type', 'Укажите тип');
        }

        if ($name === '') {
            return Form::errorInput('name', 'Укажите имя');
        }

        try {
            $model = (new VariableModel())->ifExistSetOrCreate([
                'type' => $type,
                'name' => $name,
                'value' => $value,
            ]);
        } catch (AppException) {
            return Form::errorInput('name', 'Переменная с таким типом и именем уже существует');
        }

        return [
            'success' => true,
            'id' => (int)$model->get('id'),
        ];
    }
}
