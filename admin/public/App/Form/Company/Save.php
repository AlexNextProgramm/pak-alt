<?php

namespace App\Form\Company;

use App\Form\Form;
use App\Model\CompanyModel;
use Pet\Request\Request;

class Save extends Form
{
    public $auth = true;

    public function submit(Request $request)
    {
        $fields = Form::normalizerFields();

        if (empty($fields['name'])) {
            return Form::errorInput('name', 'Укажите название компании');
        }

        $id = !empty($fields['id']) ? (int)$fields['id'] : null;

        $data = [
            'name' => trim($fields['name']),
            'email' => trim($fields['email'] ?? ''),
        ];

        $model = new CompanyModel();
        $model->ifExistSetOrCreate($data, $id);

        return ['type' => 'reload'];
    }
}