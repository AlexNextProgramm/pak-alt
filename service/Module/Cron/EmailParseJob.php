<?php

namespace Module\Cron;

use App\Model\CronReportModel;
use Module\Ai\InsuredParser;
use Module\Imap\Client;
use Module\Upload\Storage;
use RuntimeException;

class EmailParseJob
{
    private const EXCEL_EXTENSIONS = ['xlsx', 'xls', 'csv'];
    private const ZIP_EXTENSIONS = ['zip'];
    private const FOLDER_EMPTY = 'empty';
    private const FOLDER_ZIPPED = 'ziped';
    private const FOLDER_PARSED = 'parse';
    private const FOLDER_COMPLETED = 'completed';
    private const MAX_MESSAGES_PER_RUN = 100;

    private Client $imap;
    private Storage $storage;
    private InsuredParser $parser;
    private ZastrakhovannyeImporter $importer;
    private ParseLock $lock;

    private int $emailsFound = 0;

    private ?ReportErrorLogger $errorLogger = null;

    /** @var array<int, string> */
    private array $messageSubjects = [];

    /** @var list<string> */
    private array $savedParseFiles = [];

    private bool $debug = false;

    public function __construct(
        ?Client $imap = null,
        ?Storage $storage = null,
        ?InsuredParser $parser = null,
        ?ZastrakhovannyeImporter $importer = null,
        ?ParseLock $lock = null,
        bool $debug = false,
    ) {
        $this->imap = $imap ?? new Client();
        $this->storage = $storage ?? new Storage();
        $this->parser = $parser ?? new InsuredParser();
        $this->importer = $importer ?? new ZastrakhovannyeImporter();
        $this->lock = $lock ?? new ParseLock();
        $this->debug = $debug;
    }

    public function run(): int
    {
        $this->debug('start');

        if (!$this->lock->acquire()) {
            $this->debug('lock: not acquired, another process is running');

            return $this->finishSkipped('Парсинг уже выполняется другим процессом');
        }

        $this->debug('lock: acquired');
        $reportId = $this->startReport();
        $this->debug(sprintf('report: started #%d', $reportId));

        try {
            $this->processMailbox();
            $status = $this->errorLogger !== null && $this->errorLogger->hasErrors() ? 'completed' : 'success';
            $this->finishReport($reportId, $status);
            $this->debug(sprintf(
                'report: finished #%d status=%s emails_found=%d errors=%d',
                $reportId,
                $status,
                $this->emailsFound,
                $this->errorLogger?->count() ?? 0,
            ));
        } catch (\Throwable $error) {
            $this->addError(null, $error->getMessage());
            $this->finishReport($reportId, 'error');
            $this->debug(sprintf('report: finished #%d status=error: %s', $reportId, $error->getMessage()));
        } finally {
            $this->cleanupParseFiles();
            $this->lock->release();
            $this->debug('lock: released');
        }

        return $reportId;
    }

    private function finishSkipped(string $message): int
    {
        $reportId = $this->startReport();
        $this->errorLogger?->logGlobal($message);
        $this->finishReport($reportId, 'completed');

        return $reportId;
    }

    private function startReport(): int
    {
        $model = new CronReportModel();
        $id = $model->create([
            'started_at' => date('Y-m-d H:i:s'),
            'emails_found' => 0,
            'status' => 'running',
        ]);

        if (!$id) {
            throw new RuntimeException('Не удалось создать запись cron_report');
        }

        $reportId = (int)$id;
        $this->errorLogger = new ReportErrorLogger($reportId);

        return $reportId;
    }

    private function finishReport(int $reportId, string $status): void
    {
        $model = new CronReportModel(['id' => $reportId]);
        if (!$model->isInfo()) {
            return;
        }

        $model->set([
            'emails_found' => $this->emailsFound,
            'status' => $status,
        ]);
    }

    private function addError(?int $uid, string $message, ?string $filename = null): void
    {
        if ($this->errorLogger === null) {
            return;
        }

        $subject = $uid !== null ? ($this->messageSubjects[$uid] ?? null) : null;
        $this->errorLogger->log($uid, $subject, $filename, $this->shortError($message));
    }

    private function processMailbox(): void
    {
        if (!$this->imap->isConfigured()) {
            $missing = $this->imap->getMissingSettings();
            $this->debug('imap: not configured, missing: ' . implode(', ', $missing));
            throw new RuntimeException('IMAP не настроен: ' . implode(', ', $missing));
        }

        $this->debug('imap: configured');
        $this->ensureTargetFolders();

        $messagesResult = $this->imap->getMessages(self::MAX_MESSAGES_PER_RUN, 'ALL');
        if (!$messagesResult['success']) {
            $error = (string)($messagesResult['error'] ?? 'Не удалось получить письма');
            $this->debug('imap: getMessages failed: ' . $error);
            throw new RuntimeException($error);
        }

        $uids = [];
        foreach ($messagesResult['messages'] ?? [] as $message) {
            $uid = (int)($message['uid'] ?? 0);
            if ($uid > 0) {
                $uids[] = $uid;
            }
        }

        $this->debug(sprintf(
            'imap: getMessages ok, criteria=ALL, limit=%d, uids=%d',
            self::MAX_MESSAGES_PER_RUN,
            count($uids),
        ));

        if ($uids === []) {
            $this->debug('mailbox: no messages to process, exiting');
        }

        foreach ($uids as $uid) {
            try {
                $this->processMessage($uid);
            } catch (\Throwable $error) {
                $this->addError($uid, $error->getMessage());
                $this->debug(sprintf('message UID %d: unhandled error, moving to completed', $uid));
                $this->moveMessageSafe($uid, self::FOLDER_COMPLETED);
            }
        }
    }

    private function ensureTargetFolders(): void
    {
        foreach ([
            self::FOLDER_EMPTY,
            self::FOLDER_ZIPPED,
            self::FOLDER_PARSED,
            self::FOLDER_COMPLETED,
        ] as $folder) {
            $result = $this->imap->ensureFolder($folder);
            if (!$result['success']) {
                $error = (string)($result['error'] ?? 'Не удалось подготовить папку ' . $folder);
                $this->debug('imap: ensureFolder ' . $folder . ' failed: ' . $error);
                throw new RuntimeException($error);
            }

            $this->debug('imap: folder ' . $folder . ' ready');
        }
    }

    private function processMessage(int $uid): void
    {
        $messageResult = $this->imap->getMessage($uid, null, true);
        if (!$messageResult['success']) {
            $this->addError($uid, (string)($messageResult['error'] ?? 'не удалось прочитать письмо'));
            $this->moveMessageSafe($uid, self::FOLDER_COMPLETED);

            return;
        }

        $message = $messageResult['message'];
        $this->messageSubjects[$uid] = (string)($message['subject'] ?? '');
        $attachments = $message['attachments'] ?? [];
        $this->emailsFound++;

        $this->debug(sprintf(
            'message UID %d: subject=%s, attachments=%d, zip=%s',
            $uid,
            $this->debugSubject((string)($message['subject'] ?? '')),
            count($attachments),
            $this->hasZipAttachment($attachments) ? 'yes' : 'no',
        ));

        if ($attachments === []) {
            $this->debug(sprintf('message UID %d: move to %s (no attachments)', $uid, self::FOLDER_EMPTY));
            $this->moveMessageSafe($uid, self::FOLDER_EMPTY);

            return;
        }

        if ($this->hasZipAttachment($attachments)) {
            $this->debug(sprintf('message UID %d: move to %s (has zip attachment)', $uid, self::FOLDER_ZIPPED));
            $this->moveMessageSafe($uid, self::FOLDER_ZIPPED);

            return;
        }

        $excelAttachments = $this->filterExcelAttachments($attachments, $message);

        $this->debug(sprintf(
            'message UID %d: excel=%d, alpha=%s',
            $uid,
            count($excelAttachments),
            $this->isAlphaInsuranceMessage($message) ? 'yes' : 'no',
        ));

        if ($excelAttachments === []) {
            $this->addError($uid, 'нет подходящей таблицы для импорта');
            $this->debug(sprintf('message UID %d: move to %s (no matching excel)', $uid, self::FOLDER_COMPLETED));
            $this->moveMessageSafe($uid, self::FOLDER_COMPLETED);

            return;
        }

        $hasError = false;
        $processedAny = false;
        $seenFilenames = [];

        foreach ($excelAttachments as $attachment) {
            $filenameKey = mb_strtolower(trim((string)($attachment['filename'] ?? '')));
            if ($filenameKey !== '' && isset($seenFilenames[$filenameKey])) {
                continue;
            }
            if ($filenameKey !== '') {
                $seenFilenames[$filenameKey] = true;
            }

            try {
                $this->processAttachment($uid, $attachment);
                $processedAny = true;
            } catch (\Throwable $error) {
                $hasError = true;
                $filename = (string)($attachment['filename'] ?? 'file');
                $this->addError($uid, $error->getMessage(), $filename);
            }
        }

        if ($hasError || !$processedAny) {
            $this->debug(sprintf('message UID %d: move to %s (processing failed)', $uid, self::FOLDER_COMPLETED));
            $this->moveMessageSafe($uid, self::FOLDER_COMPLETED);

            return;
        }

        $this->debug(sprintf('message UID %d: move to %s (processed successfully)', $uid, self::FOLDER_PARSED));
        $this->moveMessageSafe($uid, self::FOLDER_PARSED);
    }

    /**
     * @param array<string, mixed> $attachment
     */
    private function processAttachment(int $uid, array $attachment): void
    {
        $partNumber = (string)($attachment['part'] ?? '');
        $filename = trim((string)($attachment['filename'] ?? ''));
        if ($partNumber === '' || $filename === '') {
            throw new RuntimeException('Некорректные метаданные вложения');
        }

        $attachmentResult = $this->imap->getAttachment($uid, $partNumber, null, true);
        if (!$attachmentResult['success']) {
            throw new RuntimeException((string)($attachmentResult['error'] ?? 'не удалось скачать вложение'));
        }

        $safeFilename = $this->safeFilename($filename);
        $relativePath = sprintf(
            'parse/%s/%d_%s',
            date('Y-m-d'),
            $uid,
            bin2hex(random_bytes(4)) . '_' . $safeFilename,
        );

        $savedRelativePath = $this->storage->saveContent((string)$attachmentResult['content'], $relativePath);
        $this->savedParseFiles[] = $savedRelativePath;
        $absolutePath = $this->storage->path($savedRelativePath);
        $this->debug(sprintf('attachment UID %d: saved %s', $uid, $savedRelativePath));

        $rows = $this->parser->parseFile($absolutePath, $filename);
        $this->debug(sprintf('attachment UID %d file %s: parsed %d rows', $uid, $filename, count($rows)));
        $importResult = $this->importer->import($rows);
        $this->debug(sprintf(
            'attachment UID %d file %s: imported %d, import_errors=%d',
            $uid,
            $filename,
            $importResult['imported'],
            count($importResult['errors']),
        ));

        foreach ($importResult['errors'] as $rowError) {
            $this->addError($uid, $rowError, $filename);
        }

        if ($importResult['imported'] === 0) {
            throw new RuntimeException(
                $importResult['errors'][0] ?? 'Не удалось импортировать ни одной записи из файла',
            );
        }

        if ($importResult['errors'] !== []) {
            throw new RuntimeException('Часть записей не импортирована');
        }
    }

    /**
     * @param list<array<string, mixed>> $attachments
     * @param array<string, mixed> $message
     * @return list<array<string, mixed>>
     */
    private function filterExcelAttachments(array $attachments, array $message): array
    {
        $excelAttachments = array_values(array_filter(
            $attachments,
            fn(array $attachment): bool => $this->isExcelAttachment($attachment),
        ));

        if ($excelAttachments === [] || !$this->isAlphaInsuranceMessage($message)) {
            return $excelAttachments;
        }

        $alphaAllAttachments = array_values(array_filter(
            $excelAttachments,
            fn(array $attachment): bool => $this->isAlphaAllAttachment((string)($attachment['filename'] ?? '')),
        ));

        if ($alphaAllAttachments === []) {
            $filenames = array_map(
                fn(array $attachment): string => (string)($attachment['filename'] ?? ''),
                $excelAttachments,
            );
            $this->debug(sprintf(
                'alpha filter: no *_all files among excel attachments: %s',
                implode(', ', $filenames),
            ));
        }

        return $alphaAllAttachments;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function isAlphaInsuranceMessage(array $message): bool
    {
        $parts = [
            (string)($message['subject'] ?? ''),
            (string)($message['body_text'] ?? ''),
            strip_tags((string)($message['body_html'] ?? '')),
        ];

        $text = mb_strtolower(implode(' ', $parts));
        $text = str_replace('ё', 'е', $text);

        return str_contains($text, 'альфастрахование')
            || str_contains($text, 'альфа страхование')
            || str_contains($text, 'ао "альфастрахование"');
    }

    private function isAlphaAllAttachment(string $filename): bool
    {
        $name = mb_strtolower(trim($filename));
        if ($name === '') {
            return false;
        }

        return str_contains($name, '_all.')
            || preg_match('/_all\.xlsx?$/iu', $name) === 1;
    }

    /**
     * @param list<array<string, mixed>> $attachments
     */
    private function hasZipAttachment(array $attachments): bool
    {
        foreach ($attachments as $attachment) {
            if ($this->isZipAttachment($attachment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $attachment
     */
    private function isZipAttachment(array $attachment): bool
    {
        $filename = trim((string)($attachment['filename'] ?? ''));
        if ($filename === '') {
            return false;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($ext, self::ZIP_EXTENSIONS, true);
    }

    /**
     * @param array<string, mixed> $attachment
     */
    private function isExcelAttachment(array $attachment): bool
    {
        $filename = trim((string)($attachment['filename'] ?? ''));
        if ($filename === '') {
            return false;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($ext, self::EXCEL_EXTENSIONS, true);
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^\p{L}\p{N}._-]/u', '_', $filename) ?? 'file';

        return $filename !== '' ? $filename : 'file';
    }

    private function cleanupParseFiles(): void
    {
        if ($this->savedParseFiles === []) {
            return;
        }

        $dirs = [];

        foreach ($this->savedParseFiles as $relativePath) {
            $this->storage->delete($relativePath);
            $dirs[dirname($relativePath)] = true;
        }

        foreach (array_keys($dirs) as $dir) {
            $fullDir = $this->storage->path($dir);
            if (!is_dir($fullDir)) {
                continue;
            }

            $entries = scandir($fullDir);
            if ($entries !== false && array_diff($entries, ['.', '..']) === []) {
                @rmdir($fullDir);
            }
        }

        $parseRoot = $this->storage->path('parse');
        if (is_dir($parseRoot)) {
            $entries = scandir($parseRoot);
            if ($entries !== false && array_diff($entries, ['.', '..']) === []) {
                @rmdir($parseRoot);
            }
        }

        $this->savedParseFiles = [];
    }

    private function moveMessageSafe(int $uid, string $targetFolder): void
    {
        $moveResult = $this->imap->moveMessage($uid, $targetFolder);
        if ($moveResult['success']) {
            return;
        }

        $this->addError($uid, sprintf(
            'не удалось переместить письмо в %s (%s)',
            $targetFolder,
            (string)($moveResult['error'] ?? 'неизвестная ошибка'),
        ));
    }

    private function debug(string $message): void
    {
        if (!$this->debug) {
            return;
        }

        fwrite(STDERR, sprintf("[%s] [cron-debug] %s\n", date('c'), $message));
    }

    private function debugSubject(string $subject): string
    {
        $subject = trim(preg_replace('/\s+/u', ' ', $subject) ?? $subject);
        if (mb_strlen($subject) > 80) {
            return mb_substr($subject, 0, 77) . '...';
        }

        return $subject !== '' ? $subject : '(empty)';
    }

    private function shortError(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return 'неизвестная ошибка';
        }

        $line = strtok($message, "\n");
        if ($line === false) {
            return $message;
        }

        $line = trim($line);
        if (str_starts_with($line, '❌ Ошибка:')) {
            $line = trim(substr($line, strlen('❌ Ошибка:')));
        }

        return $line !== '' ? $line : $message;
    }
}
