<?php

namespace App\Table;

use App\Model\CronReportErrorModel;
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
        $ids = array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $items);
        $errorCounts = CronReportErrorModel::countByReportIds($ids);

        foreach ($items as &$row) {
            if (isset($row['status'])) {
                $statusLabels = [
                    'running' => 'Выполняется',
                    'success' => 'Успешно',
                    'error'   => 'Ошибка',
                    'completed' => 'Завершено',
                ];
                $row['status_label'] = $statusLabels[$row['status']] ?? $row['status'];
            }

            $count = $errorCounts[(int)($row['id'] ?? 0)] ?? 0;
            $row['errors_count'] = $count;
            $row['errors_short'] = $count > 0 ? self::formatErrorCount($count) : '—';
        }
        unset($row);
    }

    private static function formatErrorCount(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $count . ' ошибка';
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return $count . ' ошибки';
        }

        return $count . ' ошибок';
    }
}
