<?php

namespace App\Controller\Ajax\User;

use App\Controller\AjaxController;
use App\Enum\UsersType;
use App\Form\Form;
use App\Module\UI\Fire;
use Module\Model\UserModel;


class Save extends AjaxController
{
    public function helper(): array
    {
        AjaxController::checkCsrf();

        $fields = Form::normalizerFields();
        $id = (int)($fields['id'] ?? 0);
        $name = trim((string)($fields['name'] ?? ''));
        $surname = trim((string)($fields['surname'] ?? ''));
        $email = trim((string)($fields['email'] ?? ''));
        $phone = Form::sanitazePhone((string)($fields['phone'] ?? ''));
        $type = (int)($fields['type'] ?? 0);
        $password = (string)($fields['password'] ?? '');

        if (empty($phone)) {
            Fire::response('Заполните телефон', Fire::ERROR);
        }
        if ($name === '' && $surname === '') {
            Fire::response('Заполните имя или фамилию', Fire::ERROR);
        }

        if ($type === 0 || !array_key_exists($type, UsersType::data())) {
            Fire::response('Выберите роль', Fire::ERROR);
        }

        // Если пароль передан — валидируем
        if ($password !== '') {
            $error = Form::validatePassword($password, ['len' => 4, 'isInt' => false, 'UpperCase' => false, 'LoverCase' => false, 'isSimbol' => false]);
            if ($error !== false) {
                Fire::response($error, Fire::ERROR);
            }
        }

        // Поиск существующего пользователя: сначала по id, потом по телефону
        $model = null;
        if ($id > 0) {
            $model = new UserModel(['id' => $id]);
        }
        if (!$model || !$model->isInfo()) {
            $model = new UserModel(['phone' => $phone]);
        }
        if (!$model->isInfo()) {
            $model = new UserModel(['phone' => $phone, 'type' => $type ?? 1, 'name' => $name], isNotExistCreate: true);
        }

        $updateData = [
            'name' => $name,
            'surname' => $surname,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'type' => $type,
        ];

        // Обновляем пароль только если передан новый
        if ($password !== '') {
            $updateData['password'] = password_hash(SALT . $password, PASSWORD_DEFAULT);
            $updateData['password_length'] = strlen($password);
        }

        $model->set($updateData);
        return [
            'success' => true,
            'id' => (int)$model->get('id'),
        ];
    }
}