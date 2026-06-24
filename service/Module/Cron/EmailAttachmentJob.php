<?php

namespace Module\Cron;

use App\Model\CompanyModel;
use App\Model\CronReportModel;
use App\Model\UploadFileModel;
use Module\Imap\Client;
use Module\Upload\Storage;
use RuntimeException;

class EmailAttachmentJob
{
    private Client $imap;
    private Storage $storage;

    /** @var array<string, int> */
    private array $companyByEmail = [];

    private int $emailsFound = 0;

    /** @var list<string> */
    private array $errors = [];

    public function __construct(?Client $imap = null, ?Storage $storage = null)
    {
        $this->imap = $imap ?? new Client();
        $this->storage = $storage ?? new Storage();
    }

    public function run(): int
    {
        $reportId = $this->startReport();

        try {
            $this->loadCompanies();
            $this->processMailbox();
            $this->finishReport($reportId, $this->errors === [] ? 'success' : 'completed');
        } catch (\Throwable $error) {
            $this->errors[] = $error->getMessage();
            $this->finishReport($reportId, 'error');
        }

        return $reportId;
    }

    private function startReport(): int
    {
        $model = new CronReportModel();

        $id = $model->create([
            'started_at' => date('Y-m-d H:i:s'),
            'emails_found' => 0,
            'errors' => null,
            'status' => 'running',
        ]);

        if (!$id) {
            throw new RuntimeException('Не удалось создать запись cron_report');
        }

        return (int)$id;
    }

    private function finishReport(int $reportId, string $status): void
    {
        $model = new CronReportModel(['id' => $reportId]);

        if (!$model->isInfo()) {
            return;
        }

        $model->set([
            'emails_found' => $this->emailsFound,
            'errors' => $this->errors === [] ? null : implode("\n", $this->errors),
            'status' => $status,
        ]);
    }

    private function loadCompanies(): void
    {
        $companies = (new CompanyModel())->find(null, function (CompanyModel $query): void {
            $query->whereAdd('email IS NOT NULL');
            $query->whereAdd("email <> ''");
        });

        foreach ($companies as $company) {
            $email = strtolower(trim((string)($company['email'] ?? '')));

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $this->companyByEmail[$email] = (int)$company['id'];
        }

        if ($this->companyByEmail === []) {
            throw new RuntimeException('В таблице company нет компаний с email');
        }
    }

    private function processMailbox(): void
    {
        if (!$this->imap->isConfigured()) {
            $missing = implode(', ', $this->imap->getMissingSettings());

            throw new RuntimeException('IMAP не настроен: ' . $missing);
        }

        $messagesResult = $this->imap->getMessages(0, 'UNSEEN');

        if (!$messagesResult['success']) {
            throw new RuntimeException((string)($messagesResult['error'] ?? 'Не удалось получить письма'));
        }

        foreach ($messagesResult['messages'] ?? [] as $message) {
            $senderEmail = $this->extractEmail((string)($message['from'] ?? ''));

            if ($senderEmail === null || !isset($this->companyByEmail[$senderEmail])) {
                continue;
            }

            $this->emailsFound++;
            $this->processMessage(
                (int)$message['uid'],
                $this->companyByEmail[$senderEmail],
                $senderEmail,
            );
        }
    }

    private function processMessage(int $uid, int $companyId, string $senderEmail): void
    {
        $messageResult = $this->imap->getMessage($uid);

        if (!$messageResult['success']) {
            $this->errors[] = sprintf(
                'UID %d (%s): %s',
                $uid,
                $senderEmail,
                (string)($messageResult['error'] ?? 'не удалось прочитать письмо'),
            );

            return;
        }

        $attachments = $messageResult['message']['attachments'] ?? [];

        if ($attachments === []) {
            return;
        }

        $downloadDir = 'download/' . $this->emailDirName($senderEmail);

        foreach ($attachments as $attachment) {
            $this->saveAttachment($uid, $companyId, $senderEmail, $downloadDir, $attachment);
        }

        $this->imap->markAsRead($uid);
    }

    /**
     * @param array<string, mixed> $attachment
     */
    private function saveAttachment(
        int $uid,
        int $companyId,
        string $senderEmail,
        string $downloadDir,
        array $attachment,
    ): void {
        $partNumber = (string)($attachment['part'] ?? '');
        $filename = trim((string)($attachment['filename'] ?? ''));

        if ($partNumber === '' || $filename === '') {
            $this->createUploadRecord(
                $companyId,
                null,
                sprintf('UID %d (%s): некорректные метаданные вложения', $uid, $senderEmail),
            );

            return;
        }

        $attachmentResult = $this->imap->getAttachment($uid, $partNumber);

        if (!$attachmentResult['success']) {
            $this->createUploadRecord(
                $companyId,
                null,
                sprintf(
                    'UID %d (%s), файл %s: %s',
                    $uid,
                    $senderEmail,
                    $filename,
                    (string)($attachmentResult['error'] ?? 'не удалось скачать вложение'),
                ),
            );

            return;
        }

        $safeFilename = $this->safeFilename($filename);
        $relativePath = $downloadDir . '/' . bin2hex(random_bytes(8)) . '_' . $safeFilename;

        try {
            $savedPath = $this->storage->saveContent((string)$attachmentResult['content'], $relativePath);
            $this->createUploadRecord($companyId, $savedPath, null);
        } catch (\Throwable $error) {
            $this->createUploadRecord(
                $companyId,
                null,
                sprintf(
                    'UID %d (%s), файл %s: %s',
                    $uid,
                    $senderEmail,
                    $filename,
                    $error->getMessage(),
                ),
            );
        }
    }

    private function createUploadRecord(int $companyId, ?string $path, ?string $exception): void
    {
        (new UploadFileModel())->create([
            'company_id' => $companyId,
            'path' => $path,
            'exception' => $exception,
        ]);
    }

    private function extractEmail(string $from): ?string
    {
        if (preg_match('/<([^>]+)>/', $from, $matches) === 1) {
            $email = strtolower(trim($matches[1]));

            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }

        $from = strtolower(trim($from));

        return filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : null;
    }

    private function emailDirName(string $email): string
    {
        return preg_replace('/[^a-zA-Z0-9@._-]/', '_', $email) ?: 'unknown';
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^\p{L}\p{N}._-]/u', '_', $filename) ?? 'file';

        return $filename !== '' ? $filename : 'file';
    }
}
