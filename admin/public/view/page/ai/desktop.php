<?php

use App\Form\Form;
?>

<div class="page-ai" data-csrf="<?= Form::csrf(true) ?>">
    <h1 class="block-header"><?= $header ?? 'AI — Обработка данных' ?></h1>

    <!-- Табы -->
    <div class="page-ai__tabs">
        <button type="button" class="page-ai__tab page-ai__tab--active" data-tab="parse">Парсинг</button>
        <button type="button" class="page-ai__tab" data-tab="config">Конфигурация парсера</button>
    </div>

    <!-- Вкладка: Парсинг -->
    <div class="page-ai__tab-content page-ai__tab-content--active" data-tab-content="parse">
        <section class="page-ai__section">
            <div class="page-ai__section-head">
                <div>
                    <h2 class="page-ai__subtitle">Парсинг списка застрахованных</h2>
                    <p class="page-ai__hint">Загрузите Excel-файл (.xlsx, .xls, .csv) со списком застрахованных для парсинга через AI.</p>
                </div>
            </div>

            <form csrf-token="<?= Form::csrf(true) ?>" class="page-ai__form" enctype="multipart/form-data">
                <div class="page-ai__form-row">
                    <div ui="file-upload" class="page-ai__file-wrap">
                        <input type="file" name="file" id="ai-file-input" accept=".xlsx,.xls,.csv" class="page-ai__file-input" required>
                        <label for="ai-file-input" class="page-ai__file-label">
                            <span class="page-ai__file-icon">📄</span>
                            <span class="page-ai__file-text">Выберите файл или перетащите его сюда</span>
                            <span class="page-ai__file-name"></span>
                        </label>
                    </div>
                    <button type="button" class="btn btn-submit-blue page-ai__submit" data-action="parse-file">Обработать</button>
                </div>
            </form>

            <div class="page-ai__result" style="display:none">
                <div class="page-ai__result-head">
                    <h3 class="page-ai__result-title">Результат обработки</h3>
                    <span class="page-ai__result-count"></span>
                </div>
                <div class="page-ai__result-table-wrap">
                    <table class="page-ai__result-table">
                        <thead></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="page-ai__error" style="display:none">
                <div class="page-ai__error-icon">⚠️</div>
                <div class="page-ai__error-text"></div>
            </div>
        </section>
    </div>

    <!-- Вкладка: Конфигурация парсера -->
    <div class="page-ai__tab-content" data-tab-content="config">
        <section class="page-ai__section">
            <div class="page-ai__section-head">
                <div>
                    <h2 class="page-ai__subtitle">Конфигурация парсера</h2>
                    <p class="page-ai__hint">Редактирование JSON-конфига парсинга застрахованных. При сохранении файл записывается в <code>var/uploads/config/ai/parse.json</code>, путь добавляется в переменную <code>ai.parse_config</code>. Файл <code>ai/mamp/parse-insured.json</code> не изменяется.</p>
                </div>
            </div>

            <div class="page-ai__config-source" style="display:none">
                <span class="page-ai__config-source-label"></span>
            </div>

            <div class="page-ai__config-toolbar">
                <button type="button" class="btn btn-submit page-ai__config-save" data-action="save-config">Сохранить</button>
                <button type="button" class="btn btn-submit-blue page-ai__config-reset" data-action="reset-config" style="display:none">Вернуть по умолчанию</button>
            </div>

            <div class="page-ai__config-editor">
                <textarea class="page-ai__config-textarea" spellcheck="false" placeholder="Загрузка конфигурации..."></textarea>
            </div>

            <div class="page-ai__config-status" style="display:none"></div>
        </section>
    </div>
</div>