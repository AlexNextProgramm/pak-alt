<?php

namespace Module\Cron;

use App\Model\CronReportErrorModel;

class ReportErrorLogger
{
    private int $reportId;
    private int $count = 0;

    public function __construct(int $reportId)
    {
        $this->reportId = $reportId;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function hasErrors(): bool
    {
        return $this->count > 0;
    }

    public function log(
        ?int $uid,
        ?string $subject,
        ?string $filename,
        string $message,
        ?string $senderEmail = null,
    ): void {
        $message = trim($message);
        if ($message === '') {
            return;
        }

        (new CronReportErrorModel())->create([
            'report_id' => $this->reportId,
            'message_uid' => $uid,
            'subject' => $this->normalizeSubject($subject),
            'sender_email' => $this->normalizeEmail($senderEmail),
            'filename' => $this->normalizeFilename($filename),
            'message' => $message,
        ]);

        $this->count++;
    }

    public function logGlobal(string $message): void
    {
        $this->log(null, null, null, $message);
    }

    private function normalizeSubject(?string $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        $subject = trim(preg_replace('/\s+/u', ' ', $subject) ?? $subject);
        if ($subject === '') {
            return '(без темы)';
        }

        if (mb_strlen($subject) > 500) {
            return mb_substr($subject, 0, 497) . '...';
        }

        return $subject;
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = strtolower(trim($email));

        return $email !== '' ? mb_substr($email, 0, 255) : null;
    }

    private function normalizeFilename(?string $filename): ?string
    {
        if ($filename === null) {
            return null;
        }

        $filename = trim($filename);

        return $filename !== '' ? mb_substr($filename, 0, 255) : null;
    }
}
