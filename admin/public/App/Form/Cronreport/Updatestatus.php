<?php

namespace App\Form\CronReport;

use App\Form\Form;
use App\Model\CronReportModel;
use App\Module\UI\Fire;
use Pet\Request\Request;

class Updatestatus extends Form
{
    public $auth = true;

    private const STATUSES = ['running', 'success', 'error', 'completed'];

    public function submit(Request $request)
    {
        $fields = Form::normalizerFields();
        $id = (int)($fields['id'] ?? 0);
        $status = (string)($fields['status'] ?? '');

        if ($id <= 0) {
            return new Fire('Не указан ID запуска', Fire::ERROR);
        }

        if (!in_array($status, self::STATUSES, true)) {
            return Form::errorInput('status', 'Некорректный статус');
        }

        $model = new CronReportModel(['id' => $id]);
        if (!$model->isInfo()) {
            return new Fire('Запуск не найден', Fire::ERROR);
        }

        $model->set(['status' => $status]);

        return [
            'type' => 'redirect',
            'href' => '/cron-report/view?id=' . $id,
        ];
    }
}
