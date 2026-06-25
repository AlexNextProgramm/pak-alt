<?php

namespace Module\Imap;

use Model\VariableModel;
use RuntimeException;

class Client
{
    private readonly string $host;
    private readonly int $port;
    private readonly string $username;
    private readonly string $password;
    private readonly string $encryption;
    private readonly bool $verifySsl;
    private readonly string $folder;

    /** @var resource|\IMAP\Connection|null */
    private $connection = null;

    private ?string $currentMailbox = null;

    public function __construct(
        ?string $host = null,
        ?int $port = null,
        ?string $username = null,
        ?string $password = null,
        ?string $encryption = null,
        ?bool $verifySsl = null,
        ?string $folder = null,
    ) {
        if ($host !== null) {
            $this->host = $host;
            $this->port = $port ?? 993;
            $this->username = $username ?? '';
            $this->password = $password ?? '';
            $this->encryption = $encryption ?? 'ssl';
            $this->verifySsl = $verifySsl ?? true;
            $this->folder = $folder ?? 'INBOX';
            return;
        }

        $this->host = $this->loadVariable('host');
        $this->port = (int)($this->loadVariable('port') ?: '993');
        $this->username = $this->loadVariable('username');
        $this->password = $this->loadVariable('password');
        $this->encryption = $this->loadVariable('encryption') ?: 'ssl';
        $this->verifySsl = $this->loadVariable('verify_ssl') !== '0';
        $this->folder = $this->loadVariable('folder');
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function isConfigured(): bool
    {
        return $this->host !== ''
            && $this->username !== ''
            && $this->password !== ''
            && $this->port > 0;
    }

    /**
     * @return string[]
     */
    public function getMissingSettings(): array
    {
        $missing = [];

        if ($this->host === '') {
            $missing[] = 'Хост (imap.host)';
        }
        if ($this->port <= 0) {
            $missing[] = 'Порт (imap.port)';
        }
        if ($this->username === '') {
            $missing[] = 'Логин (imap.username)';
        }
        if ($this->password === '') {
            $missing[] = 'Пароль (imap.password)';
        }

        return $missing;
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function testConnection(): array
    {
        $connectResult = $this->connect();

        if (!$connectResult['success']) {
            return $connectResult;
        }

        $this->disconnect();

        return ['success' => true];
    }

    /**
     * @return array{success: bool, folders?: string[], error?: string}
     */
    public function getFolders(): array
    {
        $connectResult = $this->connect();
        if (!$connectResult['success']) {
            return $connectResult;
        }

        try {
            $mailboxList = imap_list($this->connection, $this->buildMailboxRoot(), '*');
            if ($mailboxList === false) {
                return [
                    'success' => false,
                    'error' => $this->lastImapError('Не удалось получить список папок'),
                ];
            }

            $folders = [];
            foreach ($mailboxList as $mailboxPath) {
                $folders[] = $this->extractFolderName($mailboxPath);
            }

            sort($folders);

            return [
                'success' => true,
                'folders' => $folders,
            ];
        } catch (\Throwable $error) {
            error_log('IMAP getFolders: ' . $error->getMessage());

            return [
                'success' => false,
                'error' => $error->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function ensureFolder(string $folder): array
    {
        $folder = trim($folder);
        if ($folder === '') {
            return [
                'success' => false,
                'error' => 'Не указано имя папки',
            ];
        }

        $connectResult = $this->connect();
        if (!$connectResult['success']) {
            return $connectResult;
        }

        try {
            $foldersResult = $this->getFolders();
            if (!$foldersResult['success']) {
                return [
                    'success' => false,
                    'error' => (string)($foldersResult['error'] ?? 'Не удалось получить список папок'),
                ];
            }

            $existing = $foldersResult['folders'] ?? [];
            if (in_array($folder, $existing, true)) {
                return ['success' => true];
            }

            $mailbox = $this->buildMailboxString($folder);
            if (!imap_createmailbox($this->connection, $mailbox)) {
                return [
                    'success' => false,
                    'error' => $this->lastImapError('Не удалось создать папку ' . $folder),
                ];
            }

            return ['success' => true];
        } catch (\Throwable $error) {
            error_log('IMAP ensureFolder: ' . $error->getMessage());

            return [
                'success' => false,
                'error' => $error->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, messages?: array<int, array<string, mixed>>, total?: int, error?: string}
     */
    public function getMessagesPaginated(
        int $offset = 0,
        int $limit = 50,
        string $criteria = 'UNSEEN',
        ?string $folder = null,
    ): array {
        $connectResult = $this->connect($folder);
        if (!$connectResult['success']) {
            return $connectResult;
        }

        try {
            $uidList = imap_search($this->connection, $criteria, SE_UID);
            if ($uidList === false) {
                return [
                    'success' => true,
                    'messages' => [],
                    'total' => 0,
                ];
            }

            rsort($uidList, SORT_NUMERIC);
            $total = count($uidList);

            if ($limit > 0) {
                $uidList = array_slice($uidList, $offset, $limit);
            } elseif ($offset > 0) {
                $uidList = array_slice($uidList, $offset);
            }

            if ($uidList === []) {
                return [
                    'success' => true,
                    'messages' => [],
                    'total' => $total,
                ];
            }

            $overviewList = imap_fetch_overview($this->connection, implode(',', $uidList), FT_UID);
            if ($overviewList === false) {
                return [
                    'success' => false,
                    'error' => $this->lastImapError('Не удалось получить заголовки писем'),
                ];
            }

            $messages = [];
            foreach ($overviewList as $overviewItem) {
                $messages[] = $this->mapOverview($overviewItem);
            }

            return [
                'success' => true,
                'messages' => $messages,
                'total' => $total,
            ];
        } catch (\Throwable $error) {
            error_log('IMAP getMessagesPaginated: ' . $error->getMessage());

            return [
                'success' => false,
                'error' => $error->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, messages?: array<int, array<string, mixed>>, error?: string}
     */
    public function getMessages(int $limit = 50, string $criteria = 'ALL', ?string $folder = null): array
    {
        $connectResult = $this->connect($folder);
        if (!$connectResult['success']) {
            return $connectResult;
        }

        try {
            $uidList = imap_search($this->connection, $criteria, SE_UID);
            if ($uidList === false) {
                return [
                    'success' => true,
                    'messages' => [],
                ];
            }

            rsort($uidList, SORT_NUMERIC);
            if ($limit > 0) {
                $uidList = array_slice($uidList, 0, $limit);
            }

            if ($uidList === []) {
                return [
                    'success' => true,
                    'messages' => [],
                ];
            }

            $overviewList = imap_fetch_overview($this->connection, implode(',', $uidList), FT_UID);
            if ($overviewList === false) {
                return [
                    'success' => false,
                    'error' => $this->lastImapError('Не удалось получить заголовки писем'),
                ];
            }

            $messages = [];
            foreach ($overviewList as $overviewItem) {
                $messages[] = $this->mapOverview($overviewItem);
            }

            return [
                'success' => true,
                'messages' => $messages,
            ];
        } catch (\Throwable $error) {
            error_log('IMAP getMessages: ' . $error->getMessage());

            return [
                'success' => false,
                'error' => $error->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, message?: array<string, mixed>, error?: string}
     */
    public function getMessage(int $uid, ?string $folder = null, bool $keepUnread = false): array
    {
        $connectResult = $this->connect($folder);
        if (!$connectResult['success']) {
            return $connectResult;
        }

        try {
            $overviewList = imap_fetch_overview($this->connection, (string)$uid, FT_UID);
            if ($overviewList === false || $overviewList === []) {
                return [
                    'success' => false,
                    'error' => 'Письмо не найдено',
                ];
            }

            $message = $this->mapOverview($overviewList[0]);
            $structure = imap_fetchstructure($this->connection, $uid, FT_UID);
            if ($structure === false) {
                return [
                    'success' => false,
                    'error' => $this->lastImapError('Не удалось получить структуру письма'),
                ];
            }

            $bodyData = $this->extractBody($uid, $structure);
            $message['body_text'] = $bodyData['body_text'];
            $message['body_html'] = $bodyData['body_html'];
            $message['attachments'] = $bodyData['attachments'];

            if ($keepUnread) {
                $this->markAsUnread($uid, $folder);
            }

            return [
                'success' => true,
                'message' => $message,
            ];
        } catch (\Throwable $error) {
            error_log('IMAP getMessage: ' . $error->getMessage());

            return [
                'success' => false,
                'error' => $error->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function markAsRead(int $uid, ?string $folder = null): array
    {
        return $this->setFlag($uid, '\\Seen', $folder);
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function markAsUnread(int $uid, ?string $folder = null): array
    {
        return $this->clearFlag($uid, '\\Seen', $folder);
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function deleteMessage(int $uid, ?string $folder = null): array
    {
        $connectResult = $this->connect($folder);
        if (!$connectResult['success']) {
            return $connectResult;
        }

        try {
            if (!imap_delete($this->connection, (string)$uid, FT_UID)) {
                return [
                    'success' => false,
                    'error' => $this->lastImapError('Не удалось удалить письмо'),
                ];
            }

            imap_expunge($this->connection);

            return ['success' => true];
        } catch (\Throwable $error) {
            error_log('IMAP deleteMessage: ' . $error->getMessage());

            return [
                'success' => false,
                'error' => $error->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function moveMessage(int $uid, string $targetFolder, ?string $folder = null): array
    {
        $connectResult = $this->connect($folder);
        if (!$connectResult['success']) {
            return $connectResult;
        }

        try {
            if (!imap_mail_move($this->connection, (string)$uid, $targetFolder, CP_UID)) {
                return [
                    'success' => false,
                    'error' => $this->lastImapError('Не удалось переместить письмо'),
                ];
            }

            imap_expunge($this->connection);

            return ['success' => true];
        } catch (\Throwable $error) {
            error_log('IMAP moveMessage: ' . $error->getMessage());

            return [
                'success' => false,
                'error' => $error->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, content?: string, error?: string}
     */
    public function getAttachment(int $uid, string $partNumber, ?string $folder = null, bool $keepUnread = false): array
    {
        $connectResult = $this->connect($folder);
        if (!$connectResult['success']) {
            return $connectResult;
        }

        try {
            $body = imap_fetchbody($this->connection, $uid, $partNumber, FT_UID | FT_PEEK);
            if ($body === false) {
                return [
                    'success' => false,
                    'error' => $this->lastImapError('Не удалось получить вложение'),
                ];
            }

            $structure = imap_fetchstructure($this->connection, $uid, FT_UID);
            if ($structure === false) {
                return [
                    'success' => false,
                    'error' => $this->lastImapError('Не удалось получить структуру письма'),
                ];
            }

            $partStructure = $this->findPartStructure($structure, $partNumber);
            if ($partStructure === null) {
                return [
                    'success' => false,
                    'error' => 'Часть письма не найдена',
                ];
            }

            if ($keepUnread) {
                $this->markAsUnread($uid, $folder);
            }

            return [
                'success' => true,
                'content' => $this->decodeBody($body, (int)($partStructure->encoding ?? 0)),
            ];
        } catch (\Throwable $error) {
            error_log('IMAP getAttachment: ' . $error->getMessage());

            return [
                'success' => false,
                'error' => $error->getMessage(),
            ];
        }
    }

    public function disconnect(): void
    {
        if ($this->connection === null) {
            return;
        }

        imap_close($this->connection);
        $this->connection = null;
        $this->currentMailbox = null;
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private function connect(?string $folder = null): array
    {
        if (!function_exists('imap_open')) {
            return [
                'success' => false,
                'error' => 'PHP-расширение imap не установлено',
            ];
        }

        if (!$this->isConfigured()) {
            $missing = $this->getMissingSettings();

            return [
                'success' => false,
                'error' => 'IMAP не настроен. Заполните в разделе «Переменные»: ' . implode(', ', $missing),
            ];
        }

        $mailbox = $this->buildMailboxString($folder ?? $this->folder);

        if ($this->connection !== null && $this->currentMailbox === $mailbox) {
            return ['success' => true];
        }

        if ($this->connection !== null) {
            $reopenResult = @imap_reopen($this->connection, $mailbox);
            if ($reopenResult) {
                $this->currentMailbox = $mailbox;
                return ['success' => true];
            }

            $this->disconnect();
        }

        $connection = @imap_open(
            $mailbox,
            $this->username,
            $this->password,
            0,
            1,
            [
                'DISABLE_AUTHENTICATOR' => 'GSSAPI',
            ],
        );

        if ($connection === false) {
            return [
                'success' => false,
                'error' => $this->lastImapError('Не удалось подключиться к почтовому серверу'),
            ];
        }

        $this->connection = $connection;
        $this->currentMailbox = $mailbox;

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private function setFlag(int $uid, string $flag, ?string $folder): array
    {
        $connectResult = $this->connect($folder);
        if (!$connectResult['success']) {
            return $connectResult;
        }

        if (!imap_setflag_full($this->connection, (string)$uid, $flag, ST_UID)) {
            return [
                'success' => false,
                'error' => $this->lastImapError('Не удалось установить флаг письма'),
            ];
        }

        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private function clearFlag(int $uid, string $flag, ?string $folder): array
    {
        $connectResult = $this->connect($folder);
        if (!$connectResult['success']) {
            return $connectResult;
        }

        if (!imap_clearflag_full($this->connection, (string)$uid, $flag, ST_UID)) {
            return [
                'success' => false,
                'error' => $this->lastImapError('Не удалось снять флаг письма'),
            ];
        }

        return ['success' => true];
    }

    private function loadVariable(string $name): string
    {
        $variableModel = new VariableModel(['type' => 'imap', 'name' => $name]);

        return $variableModel->isInfo() ? trim((string)$variableModel->get('value')) : '';
    }

    private function buildMailboxRoot(): string
    {
        return $this->buildMailboxString('');
    }

    private function buildMailboxString(string $folder): string
    {
        $flags = match ($this->encryption) {
            'tls' => '/imap/tls',
            'none' => '/imap',
            default => '/imap/ssl',
        };

        if (!$this->verifySsl) {
            $flags .= '/novalidate-cert';
        }

        $mailbox = sprintf('{%s:%d%s}', $this->host, $this->port, $flags);

        if ($folder !== '') {
            $mailbox .= $folder;
        }

        return $mailbox;
    }

    private function extractFolderName(string $mailboxPath): string
    {
        $bracePos = strpos($mailboxPath, '}');
        if ($bracePos === false) {
            return $mailboxPath;
        }

        return substr($mailboxPath, $bracePos + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOverview(object $overviewItem): array
    {
        return [
            'uid' => (int)($overviewItem->uid ?? 0),
            'msgno' => (int)($overviewItem->msgno ?? 0),
            'subject' => $this->decodeHeader((string)($overviewItem->subject ?? '')),
            'from' => $this->decodeHeader((string)($overviewItem->from ?? '')),
            'to' => $this->decodeHeader((string)($overviewItem->to ?? '')),
            'date' => (string)($overviewItem->date ?? ''),
            'seen' => !empty($overviewItem->seen),
            'answered' => !empty($overviewItem->answered),
            'flagged' => !empty($overviewItem->flagged),
            'deleted' => !empty($overviewItem->deleted),
            'size' => (int)($overviewItem->size ?? 0),
        ];
    }

    /**
     * @return array{body_text: ?string, body_html: ?string, attachments: array<int, array<string, mixed>>}
     */
    private function extractBody(int $uid, object $structure, string $partNumber = ''): array
    {
        $bodyText = null;
        $bodyHtml = null;
        $attachments = [];

        if (!isset($structure->parts) || !is_array($structure->parts)) {
            $content = $this->fetchPartBody($uid, $partNumber === '' ? '1' : $partNumber);
            $decodedContent = $this->decodeBody($content, (int)($structure->encoding ?? 0));
            $mimeType = $this->partMimeType($structure);

            if ($mimeType === 'text/html') {
                $bodyHtml = $decodedContent;
            } else {
                $bodyText = $decodedContent;
            }

            $attachmentMeta = $this->extractAttachmentMeta($structure, $partNumber === '' ? '1' : $partNumber);
            if ($attachmentMeta !== null) {
                $attachments[] = $attachmentMeta;
            }

            return [
                'body_text' => $bodyText,
                'body_html' => $bodyHtml,
                'attachments' => $attachments,
            ];
        }

        foreach ($structure->parts as $index => $partStructure) {
            $currentPartNumber = $partNumber === ''
                ? (string)($index + 1)
                : $partNumber . '.' . ($index + 1);

            $effectiveStructure = $partStructure;
            if ($this->isEmbeddedMessagePart($partStructure) && !$this->hasSubParts($partStructure)) {
                $embeddedStructure = $this->fetchPartStructureByUid($uid, $currentPartNumber);
                if ($embeddedStructure !== null) {
                    $effectiveStructure = $embeddedStructure;
                }
            }

            $partData = $this->extractBody($uid, $effectiveStructure, $currentPartNumber);
            $bodyText = $bodyText ?? $partData['body_text'];
            $bodyHtml = $bodyHtml ?? $partData['body_html'];
            $attachments = [...$attachments, ...$partData['attachments']];
        }

        return [
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'attachments' => $attachments,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractAttachmentMeta(object $structure, string $partNumber): ?array
    {
        $filename = $this->partFilename($structure);
        if ($filename === null) {
            return null;
        }

        return [
            'part' => $partNumber,
            'filename' => $filename,
            'mime' => $this->partMimeType($structure),
            'size' => (int)($structure->bytes ?? 0),
        ];
    }

    private function partMimeType(object $structure): string
    {
        $type = $this->mimeTypeName((int)($structure->type ?? 0));
        $subtype = strtolower((string)($structure->subtype ?? 'plain'));

        return $type . '/' . $subtype;
    }

    private function mimeTypeName(int $type): string
    {
        return match ($type) {
            TYPETEXT => 'text',
            TYPEMULTIPART => 'multipart',
            TYPEMESSAGE => 'message',
            TYPEAPPLICATION => 'application',
            TYPEAUDIO => 'audio',
            TYPEIMAGE => 'image',
            TYPEVIDEO => 'video',
            TYPEMODEL => 'model',
            default => 'application',
        };
    }

    private function hasSubParts(object $structure): bool
    {
        return isset($structure->parts) && is_array($structure->parts) && $structure->parts !== [];
    }

    private function isEmbeddedMessagePart(object $structure): bool
    {
        return (int)($structure->type ?? 0) === TYPEMESSAGE
            && strtolower((string)($structure->subtype ?? '')) === 'rfc822';
    }

    private function fetchPartStructureByUid(int $uid, string $partNumber): ?object
    {
        if ($this->connection === null) {
            return null;
        }

        $structure = @imap_bodystruct($this->connection, $uid, $partNumber, FT_UID);

        return $structure !== false ? $structure : null;
    }

    private function partFilename(object $structure): ?string
    {
        foreach (['dparameters', 'parameters'] as $parameterKey) {
            if (!isset($structure->$parameterKey) || !is_array($structure->$parameterKey)) {
                continue;
            }

            foreach ($structure->$parameterKey as $parameter) {
                $attribute = strtolower((string)($parameter->attribute ?? ''));
                if (in_array($attribute, ['filename', 'name'], true)) {
                    return $this->decodeHeader((string)($parameter->value ?? ''));
                }
            }
        }

        return null;
    }

    private function fetchPartBody(int $uid, string $partNumber): string
    {
        $body = imap_fetchbody($this->connection, $uid, $partNumber, FT_UID | FT_PEEK);
        if ($body === false) {
            throw new RuntimeException($this->lastImapError('Не удалось получить тело письма'));
        }

        return $body;
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            ENCBASE64 => base64_decode($body, true) ?: '',
            ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
            ENC8BIT, ENC7BIT => $body,
            default => $body,
        };
    }

    private function decodeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $decoded = imap_utf8($value);

        return $decoded !== false ? $decoded : $value;
    }

    private function findPartStructure(object $structure, string $partNumber): ?object
    {
        $partSegments = explode('.', $partNumber);
        $currentStructure = $structure;

        foreach ($partSegments as $segment) {
            $partIndex = (int)$segment - 1;
            if (!isset($currentStructure->parts[$partIndex])) {
                return null;
            }

            $currentStructure = $currentStructure->parts[$partIndex];
        }

        return $currentStructure;
    }

    private function lastImapError(string $fallback): string
    {
        $errors = imap_errors();
        if (is_array($errors) && $errors !== []) {
            return 'IMAP: ' . end($errors);
        }

        $alerts = imap_alerts();
        if (is_array($alerts) && $alerts !== []) {
            return 'IMAP: ' . end($alerts);
        }

        return $fallback;
    }
}
