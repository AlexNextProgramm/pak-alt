<?php

namespace App\Model;

use Pet\Model\Model;

class CronReportErrorModel extends Model
{
    protected string $table = 'cron_report_error';

    /**
     * @param int[] $reportIds
     * @return array<int, int> report_id => count
     */
    public static function countByReportIds(array $reportIds): array
    {
        $reportIds = array_values(array_unique(array_filter(array_map('intval', $reportIds))));
        if ($reportIds === []) {
            return [];
        }

        $idsList = implode(',', $reportIds);
        $rows = (new self())->find(null, static function (self $query) use ($idsList): self {
            $query->whereAdd("report_id IN ($idsList)");

            return $query;
        });

        $counts = array_fill_keys($reportIds, 0);
        foreach ($rows as $row) {
            $reportId = (int)($row['report_id'] ?? 0);
            if ($reportId > 0) {
                $counts[$reportId] = ($counts[$reportId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @return list<array{message_uid: int|null, subject: string|null, sender_email: string|null, items: list<array{filename: string|null, message: string}>}>
     */
    public static function groupedByReport(int $reportId): array
    {
        $rows = (new self())->find(['report_id' => $reportId], static function (self $query): self {
            return $query->orderBy('id', 'ASC');
        });

        /** @var array<string, array{message_uid: int|null, subject: string|null, sender_email: string|null, items: list<array{filename: string|null, message: string}>}> $groups */
        $groups = [];

        foreach ($rows as $row) {
            $uid = isset($row['message_uid']) ? (int)$row['message_uid'] : null;
            $key = $uid !== null ? 'uid:' . $uid : '_global';

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'message_uid' => $uid,
                    'subject' => isset($row['subject']) ? (string)$row['subject'] : null,
                    'sender_email' => isset($row['sender_email']) ? (string)$row['sender_email'] : null,
                    'items' => [],
                ];
            }

            $groups[$key]['items'][] = [
                'filename' => isset($row['filename']) ? (string)$row['filename'] : null,
                'message' => (string)($row['message'] ?? ''),
            ];
        }

        return array_values($groups);
    }
}
