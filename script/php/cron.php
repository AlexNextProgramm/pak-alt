#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Cron: загрузка вложений из почты компаний.
 *
 * Требования:
 * - миграции 6_imap.sql (переменные imap.*) и 27_upload_file.sql
 * - PHP-расширение imap
 * - заполненные переменные imap в админке
 *
 * Запуск:
 *   php script/php/cron
 */

require __DIR__ . '/bootstrap.php';

use Module\Cron\EmailAttachmentJob;

$reportId = (new EmailAttachmentJob())->run();

fwrite(STDOUT, sprintf("[%s] cron finished, report #%d\n", date('c'), $reportId));
