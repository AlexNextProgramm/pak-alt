<?php

namespace App\Controller\Ajax\Variable;

use App\Controller\AjaxController;
use App\Form\Form;

class Delete extends AjaxController
{
    public function helper(): array
    {
        AjaxController::checkCsrf();

        return Form::errorInput('id', 'Удаление переменных запрещено. Список задаётся миграциями');
    }
}
