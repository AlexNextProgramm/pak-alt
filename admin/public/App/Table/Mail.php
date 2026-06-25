<?php

namespace App\Table;

use Module\Imap\Client;
use Pet\Model\Model;

class Mail extends Model implements Itable
{
    /** @var array<int, array<string, mixed>> */
    private array $messages = [];

    public $st = 0;

    /** @var string|null Ошибка IMAP, если есть */
    public ?string $imapError = null;

    public function renameFilter(string &$k, array|string &$v): bool
    {
        return false;
    }

    public function getDatatable(array $filters, string $where): void
    {
        $pages = (object)($filters['pages'] ?? []);
        $limit = (int)($pages->limit ?? 50);
        $page = max(1, (int)($pages->count ?? 1));
        $offset = ($page - 1) * $limit;

        $client = new Client();
        $result = $client->getMessagesPaginated($offset, $limit, 'UNSEEN');

        if (!$result['success']) {
            $this->imapError = $result['error'] ?? 'Ошибка загрузки почты';
            $this->st = 0;
            $this->messages = [];
            return;
        }

        $this->st = (int)($result['total'] ?? 0);
        $this->messages = $result['messages'] ?? [];
    }

    public function find(?array $fields = null, ?callable $callback = null): array
    {
        if ($callback) {
            $callback($this);
        }

        return $this->messages;
    }

    public function behind(array &$items): void
    {
        foreach ($items as &$row) {
            $row['date_formatted'] = $this->formatDate((string)($row['date'] ?? ''));
            $row['size_formatted'] = $this->formatSize((int)($row['size'] ?? 0));
            $row['subject'] = trim((string)($row['subject'] ?? '')) ?: '(без темы)';
        }
        unset($row);
    }

    private function formatDate(string $date): string
    {
        if ($date === '') {
            return '—';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        return date('d.m.Y H:i', $timestamp);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' Б';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' КБ';
        }

        return round($bytes / (1024 * 1024), 1) . ' МБ';
    }
}
