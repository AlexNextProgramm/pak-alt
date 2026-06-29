<?php

namespace App\Controller\Page;

use App\Controller\PageController;
use App\Model\CronReportModel;
use Pet\Request\Request;
use Pet\Router\Error as RE;
use Pet\View\View;

class CronReportController extends PageController
{
    public function index(Request $request)
    {
        view('page.cron-report.init', []);
    }

    public function view(Request $request): void
    {
        $id = (int)(attrs()['id'] ?? 0);
        if ($id <= 0) {
            RE::setHttp(RE::STATUS_HTTP::NOT_FOUND);
            echo 'Отчёт не найден';

            return;
        }

        $model = new CronReportModel(['id' => $id]);
        if (!$model->isInfo()) {
            RE::setHttp(RE::STATUS_HTTP::NOT_FOUND);
            echo 'Отчёт не найден';

            return;
        }

        $status = (string)$model->get('status');
        $statusLabels = [
            'running' => 'Выполняется',
            'success' => 'Успешно',
            'error' => 'Ошибка',
            'completed' => 'Завершено',
        ];

        View::append([
            'desktop' => '/cron-report/view/desktop.php',
        ]);

        view('page.cron-report.init', [
            'report' => [
                'id' => $id,
                'started_at' => (string)$model->get('started_at'),
                'emails_found' => (int)$model->get('emails_found'),
                'errors' => $model->get('errors'),
                'status' => $status,
                'status_label' => $statusLabels[$status] ?? $status,
            ],
        ]);
    }
}