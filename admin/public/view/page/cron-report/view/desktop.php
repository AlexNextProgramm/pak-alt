<?php

/** @var array<string, mixed> $report */

$errors = trim((string)($report['errors'] ?? ''));
$errorLines = $errors !== '' ? preg_split('/\R/u', $errors) ?: [] : [];
?>

<div class="page-cron-report page-cron-report--view">
    <div class="page-cron-report__view-head">
        <a href="/cron-report" class="page-cron-report__back">← К списку запусков</a>
        <h1 class="block-header">Запуск #<?= (int)($report['id'] ?? 0) ?></h1>
    </div>

    <section class="page-cron-report__section page-cron-report__view-meta">
        <dl class="page-cron-report__meta">
            <div class="page-cron-report__meta-row">
                <dt>Время запуска</dt>
                <dd><?= htmlspecialchars((string)($report['started_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div class="page-cron-report__meta-row">
                <dt>Писем найдено</dt>
                <dd><?= (int)($report['emails_found'] ?? 0) ?></dd>
            </div>
            <div class="page-cron-report__meta-row">
                <dt>Статус</dt>
                <dd>
                    <span class="page-cron-report__status page-cron-report__status--<?= htmlspecialchars((string)($report['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars((string)($report['status_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </dd>
            </div>
        </dl>
    </section>

    <section class="page-cron-report__section">
        <h2 class="page-cron-report__subtitle">Ошибки</h2>
        <?php if ($errorLines === []): ?>
            <p class="page-cron-report__hint">Ошибок нет.</p>
        <?php else: ?>
            <ol class="page-cron-report__errors-list">
                <?php foreach ($errorLines as $line): ?>
                    <?php if (trim((string)$line) === '') {
                        continue;
                    } ?>
                    <li class="page-cron-report__errors-item"><?= htmlspecialchars((string)$line, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>
