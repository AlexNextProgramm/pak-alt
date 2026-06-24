<?php

namespace App\Table;

use App\Model\CronReportModel;

class CronReport extends CronReportModel implements Itable
{
    private array $allowedFilters = ['id', 'status', 'started_at'];

    public function renameFilter(string &$k, array|string &$v): bool
    {
        return in_array($k, $this->allowedFilters, true);
    }

    public function getDatatable(array $filters, string $where): void
    {
        if ($where !== '') {
            $this->whereAdd($where);
        }

        $this->orderBy('id', 'DESC');
    }

    public function behind(array &$items): void
    {
        foreach ($items as &$row) {
            // Форматируем статус для отображения
            if (isset($row['status'])) {
                $statusLabels = [
                    'running' => 'Выполняется',
                    'success' => 'Успешно',
                    'error'   => 'Ошибка',
                    'completed' => 'Завершено',
                ];
                $row['status_label'] = $statusLabels[$row['status']] ?? $row['status'];
            }

            // Обрезаем ошибки для таблицы
            if (!empty($row['errors'])) {
                $row['errors_short'] = mb_substr($row['errors'], 0, 100) . (mb_strlen($row['errors']) > 100 ? '…' : '');
            } else {
                $row['errors_short'] = '—';
            }
        }
        unset($row);
    }
}