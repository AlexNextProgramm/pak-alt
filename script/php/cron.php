#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Cron: парсинг Excel-вложений из почты и запись застрахованных в БД.
 *
 * Требования:
 * - миграции imap, zastrakhovannye, cron_report
 * - PHP-расширение imap
 * - Node.js и пакет ai/
 * - заполненные переменные imap в админке
 *
 * Запуск:
 *   php script/php/cron.php
 *   php script/php/cron.php --debug
 *   CRON_DEBUG=1 php script/php/cron.php
 */

require __DIR__ . '/bootstrap.php';

use Module\Cron\EmailParseJob;

$debug = in_array('--debug', $argv ?? [], true) || getenv('CRON_DEBUG') === '1';
$reportId = (new EmailParseJob(debug: $debug))->run();

fwrite(STDOUT, sprintf("[%s] cron finished, report #%d\n", date('c'), $reportId));
