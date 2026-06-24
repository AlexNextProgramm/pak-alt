<?php

namespace App\Form\Photo;

use App\Form\Form;
use App\Module\Photo;
use App\Module\UI\Fire;
use Pet\Request\Request;

class Delete extends Form
{
    public $auth = true;

    public function submit(Request $request)
    {
        $fields = Form::normalizerFields();
        $id = (int)($fields['id'] ?? 0);

        if ($id <= 0) {
            return new Fire('Не указан ID фото', Fire::ERROR);
        }

        if (!Photo::deleteById($id)) {
            return new Fire('Фото не найдено', Fire::ERROR);
        }

        return ['type' => 'photo-deleted', 'id' => $id];
    }
}
