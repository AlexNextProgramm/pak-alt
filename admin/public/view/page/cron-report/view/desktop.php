<?php

use App\Form\Form;

/** @var array<string, mixed> $report */
/** @var array<string, string> $statusLabels */
/** @var list<array{message_uid: int|null, subject: string|null, sender_email: string|null, items: list<array{filename: string|null, message: string}>}> $errorGroups */

$errorGroups = $errorGroups ?? [];
$statusLabels = $statusLabels ?? [];
$isRunning = ($report['status'] ?? '') === 'running';
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
                <dt>Ошибок</dt>
                <dd><?= (int)($report['errors_count'] ?? 0) ?></dd>
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

    <?php if ($isRunning): ?>
        <div class="page-cron-report__alert">
            Запуск завис в статусе «Выполняется» — вероятно, крон завершился аварийно. Измените статус вручную, чтобы закрыть запись.
        </div>
    <?php endif; ?>

    <section class="page-cron-report__section page-cron-report__status-form">
        <h2 class="page-cron-report__subtitle">Изменить статус</h2>
        <p class="page-cron-report__hint">Используйте, если запуск остался в «Выполняется» после сбоя или нужно исправить статус вручную.</p>
        <form name="CronReport/UpdateStatus" csrf-token="<?= Form::csrf(true) ?>" class="page-cron-report__form">
            <input type="hidden" name="id" value="<?= (int)($report['id'] ?? 0) ?>">
            <div class="page-cron-report__form-row">
                <select name="status" class="page-cron-report__status-select">
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= ($report['status'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-submit-blue">Сохранить статус</button>
            </div>
        </form>
    </section>

    <section class="page-cron-report__section">
        <h2 class="page-cron-report__subtitle">Ошибки по письмам</h2>
        <?php if ($errorGroups === []): ?>
            <p class="page-cron-report__hint">Ошибок нет.</p>
        <?php else: ?>
            <div class="page-cron-report__mail-groups">
                <?php foreach ($errorGroups as $group): ?>
                    <article class="page-cron-report__mail-group">
                        <header class="page-cron-report__mail-head">
                            <?php if (!empty($group['message_uid'])): ?>
                                <span class="page-cron-report__mail-uid">UID <?= (int)$group['message_uid'] ?></span>
                            <?php else: ?>
                                <span class="page-cron-report__mail-uid">Общие ошибки</span>
                            <?php endif; ?>
                            <?php if (!empty($group['subject'])): ?>
                                <h3 class="page-cron-report__mail-subject"><?= htmlspecialchars((string)$group['subject'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <?php endif; ?>
                            <?php if (!empty($group['sender_email'])): ?>
                                <p class="page-cron-report__mail-from"><?= htmlspecialchars((string)$group['sender_email'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </header>
                        <ol class="page-cron-report__errors-list">
                            <?php foreach ($group['items'] as $item): ?>
                                <li class="page-cron-report__errors-item">
                                    <?php if (!empty($item['filename'])): ?>
                                        <span class="page-cron-report__error-file"><?= htmlspecialchars((string)$item['filename'], ENT_QUOTES, 'UTF-8') ?>:</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars((string)($item['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
