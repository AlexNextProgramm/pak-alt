import { ajax } from '../module/ajax';
import { Fire } from '../module/ui/fire';
import { $, Rocet } from '@rocet/rocet';
import { div, span, table, tr, th, td, button } from '@rocet/RocetNodeElements';
import '../css/page/ai.scss';

const page = document.querySelector('.page-ai') as HTMLElement | null;

if (page) {
    const csrf = page.dataset.csrf ?? '';

    // ===== Табы =====
    const tabs = page.querySelectorAll('.page-ai__tab');
    const tabContents = page.querySelectorAll('.page-ai__tab-content');

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = (tab as HTMLElement).dataset.tab;

            tabs.forEach((t) => t.classList.remove('page-ai__tab--active'));
            tab.classList.add('page-ai__tab--active');

            tabContents.forEach((tc) => {
                tc.classList.toggle('page-ai__tab-content--active', (tc as HTMLElement).dataset.tabContent === target);
            });
        });
    });

    // ===== Форма загрузки файла =====
    const form = page.querySelector('.page-ai__form') as HTMLFormElement | null;
    const fileInput = page.querySelector('.page-ai__file-input') as HTMLInputElement | null;
    const fileLabel = page.querySelector('.page-ai__file-label') as HTMLElement | null;
    const fileText = page.querySelector('.page-ai__file-text') as HTMLElement | null;
    const fileName = page.querySelector('.page-ai__file-name') as HTMLElement | null;
    const resultBlock = page.querySelector('.page-ai__result') as HTMLElement | null;
    const resultCount = page.querySelector('.page-ai__result-count') as HTMLElement | null;
    const resultTableWrap = page.querySelector('.page-ai__result-table-wrap') as HTMLElement | null;
    const resultThead = page.querySelector('.page-ai__result-table thead') as HTMLElement | null;
    const resultTbody = page.querySelector('.page-ai__result-table tbody') as HTMLElement | null;
    const errorBlock = page.querySelector('.page-ai__error') as HTMLElement | null;
    const errorText = page.querySelector('.page-ai__error-text') as HTMLElement | null;
    const submitBtn = page.querySelector('.page-ai__submit') as HTMLButtonElement | null;

    // Drag & drop
    if (fileInput && fileLabel) {
        fileLabel.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileLabel.classList.add('page-ai__file-label--drag');
        });

        fileLabel.addEventListener('dragleave', () => {
            fileLabel.classList.remove('page-ai__file-label--drag');
        });

        fileLabel.addEventListener('drop', (e) => {
            e.preventDefault();
            fileLabel.classList.remove('page-ai__file-label--drag');
            if (e.dataTransfer?.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateFileName();
            }
        });

        fileInput.addEventListener('change', () => {
            updateFileName();
        });

        function updateFileName() {
            if (fileInput?.files?.length) {
                const name = fileInput.files[0].name;
                if (fileText) fileText.style.display = 'none';
                if (fileName) {
                    fileName.textContent = name;
                    fileName.style.display = 'inline';
                }
            } else {
                if (fileText) fileText.style.display = '';
                if (fileName) {
                    fileName.textContent = '';
                    fileName.style.display = 'none';
                }
            }
        }
    }

    // Submit формы — передаём саму форму в ajax.post (он сам создаст FormData с файлами)
    const parseBtn = page.querySelector('[data-action="parse-file"]') as HTMLButtonElement | null;

    if (form && parseBtn) {
        parseBtn.addEventListener('click', () => {
            if (!fileInput?.files?.length) {
                Fire.show({ message: 'Выберите файл' });
                return;
            }

            parseBtn.disabled = true;
            parseBtn.textContent = 'Обработка...';

            // Прячем старые результаты
            if (resultBlock) resultBlock.style.display = 'none';
            if (errorBlock) errorBlock.style.display = 'none';

            // Передаём форму целиком — ajax.post сам прочитает form-data, файлы, form-name и csrf-token
            ajax.post(form, {}, '/ajax/ai_parse')
                .then((raw: any) => {
                    const data = typeof raw === 'string'
                        ? (() => { try { return JSON.parse(raw); } catch { return null; } })()
                        : raw;

                    if (!data) {
                        showError('Некорректный ответ сервера');
                        return;
                    }

                    if (data?.type === 'error') {
                        Fire.show({ message: data.message || 'Ошибка обработки' });
                        showError(data.message);
                        return;
                    }

                    if (data?.type === 'fire') {
                        Fire.show(data);
                        return;
                    }

                    if (data?.type === 'error-input') {
                        ajax.eventForm(data);
                        return;
                    }

                    if (data?.type === 'success' && data?.data) {
                        showResult(data.data, data.count ?? data.data.length);
                        return;
                    }

                    if (Array.isArray(data?.data) && data.data.length) {
                        showResult(data.data, data.count ?? data.data.length);
                    }
                })
                .catch((err: any) => {
                    const msg = 'Ошибка запроса: ' + (err?.message || 'неизвестная ошибка');
                    Fire.show({ message: msg });
                    showError(msg);
                })
                .finally(() => {
                    parseBtn.disabled = false;
                    parseBtn.textContent = 'Обработать';
                });
        });
    }

    function showError(message: string) {
        if (errorBlock && errorText) {
            errorText.textContent = message;
            errorBlock.style.display = 'flex';
        }
        if (resultBlock) resultBlock.style.display = 'none';
    }

    function showResult(data: any[], count: number) {
        if (!resultBlock || !resultThead || !resultTbody || !resultCount) return;

        if (!data.length) {
            showError('Нет данных для отображения');
            return;
        }

        const columnLabels: Record<string, string> = {
            operation_type: 'Тип операции',
            surname: 'Фамилия',
            name: 'Имя',
            patronymic: 'Отчество',
            birth_date: 'Дата рождения',
            gender: 'Пол',
            address: 'Адрес',
            phone_home: 'Тел. домашний',
            phone_work: 'Тел. служебный',
            phone_mobile: 'Тел. мобильный',
            policy_number: '№ полиса',
            service_start: 'Начало обслуж.',
            service_end: 'Окончание обслуж.',
            program: 'Программа помощи',
            workplace: 'Место работы',
        };

        const columnOrder = [
            'operation_type',
            'surname',
            'name',
            'patronymic',
            'birth_date',
            'gender',
            'policy_number',
            'service_start',
            'service_end',
            'program',
            'workplace',
            'address',
            'phone_home',
            'phone_work',
            'phone_mobile',
        ];

        const stickyColumns = ['surname', 'name', 'patronymic'];

        const hasCellValue = (value: unknown) => {
            if (value === null || value === undefined) return false;
            return String(value).trim() !== '';
        };

        const availableColumns = columnOrder.filter((col) => col in data[0]);
        const columns = availableColumns.filter((col) =>
            data.some((row) => hasCellValue(row[col])),
        );

        const formatCell = (value: unknown) => {
            if (value === null || value === undefined) return '';
            if (typeof value === 'object') return JSON.stringify(value);
            return String(value);
        };

        resultThead.innerHTML = '';
        const headerRow = document.createElement('tr');
        const stickyOffsets: Record<string, number> = {};
        let stickyLeft = 0;

        columns.forEach((col) => {
            const th = document.createElement('th');
            th.textContent = columnLabels[col] || col;
            th.dataset.col = col;

            if (stickyColumns.includes(col)) {
                th.classList.add('page-ai__result-sticky');
                stickyOffsets[col] = stickyLeft;
                th.style.left = `${stickyLeft}px`;
                stickyLeft += col === 'surname' ? 120 : col === 'name' ? 100 : 130;
            }

            if (col === 'address') {
                th.classList.add('page-ai__result-col--wide');
            }

            headerRow.appendChild(th);
        });
        resultThead.appendChild(headerRow);

        resultTbody.innerHTML = '';
        data.forEach((row) => {
            const tr = document.createElement('tr');
            columns.forEach((col) => {
                const td = document.createElement('td');
                td.textContent = formatCell(row[col]);
                td.dataset.col = col;

                if (stickyColumns.includes(col)) {
                    td.classList.add('page-ai__result-sticky');
                    td.style.left = `${stickyOffsets[col]}px`;
                }

                if (col === 'address') {
                    td.classList.add('page-ai__result-col--wide');
                }

                tr.appendChild(td);
            });
            resultTbody.appendChild(tr);
        });

        resultCount.textContent = `Найдено записей: ${count}`;
        resultBlock.style.display = 'block';
        if (errorBlock) errorBlock.style.display = 'none';

        if (resultTableWrap) {
            resultTableWrap.scrollLeft = 0;
        }
    }

    // ===== Редактор конфига =====
    const configSaveBtn = page.querySelector('[data-action="save-config"]') as HTMLButtonElement | null;
    const configResetBtn = page.querySelector('[data-action="reset-config"]') as HTMLButtonElement | null;
    const configEditor = page.querySelector('.page-ai__config-editor') as HTMLElement | null;
    const configTextarea = page.querySelector('.page-ai__config-textarea') as HTMLTextAreaElement | null;
    const configStatus = page.querySelector('.page-ai__config-status') as HTMLElement | null;
    const configSource = page.querySelector('.page-ai__config-source') as HTMLElement | null;
    const configSourceLabel = page.querySelector('.page-ai__config-source-label') as HTMLElement | null;

    function updateConfigSource(isCustom: boolean, path?: string) {
        if (!configSource || !configSourceLabel) return;

        configSource.style.display = 'block';
        configSourceLabel.className = 'page-ai__config-source-label';

        if (isCustom) {
            configSourceLabel.classList.add('page-ai__config-source-label--custom');
            configSourceLabel.textContent = path
                ? `Используется кастомный конфиг: ${path}`
                : 'Используется кастомный конфиг';
            if (configResetBtn) configResetBtn.style.display = 'inline-flex';
        } else {
            configSourceLabel.classList.add('page-ai__config-source-label--default');
            configSourceLabel.textContent = 'Используется конфиг по умолчанию: ai/mamp/parse-insured.json';
            if (configResetBtn) configResetBtn.style.display = 'none';
        }
    }

    function applyConfigData(data: { content?: string; isCustom?: boolean; path?: string }) {
        if (data.content && configTextarea) {
            configTextarea.value = data.content;
        }
        if (configEditor) {
            configEditor.style.display = 'block';
        }
        if (typeof data.isCustom === 'boolean') {
            updateConfigSource(data.isCustom, data.path);
        }
        if (configStatus) {
            configStatus.style.display = 'none';
        }
    }

    function loadConfig() {
        if (configTextarea) {
            configTextarea.placeholder = 'Загрузка конфигурации...';
            configTextarea.disabled = true;
        }

        return ajax.send('ai_loadConfig', { 'csrf-token': csrf })
            .then((data: any) => {
                if (data?.type === 'success' && data?.content) {
                    applyConfigData(data);
                    return;
                }

                if (data?.type === 'fire') {
                    Fire.show(data);
                }
            })
            .catch(() => {
                if (configStatus) {
                    configStatus.textContent = 'Ошибка загрузки конфига';
                    configStatus.className = 'page-ai__config-status page-ai__config-status--error';
                    configStatus.style.display = 'block';
                }
            })
            .finally(() => {
                if (configTextarea) {
                    configTextarea.placeholder = '';
                    configTextarea.disabled = false;
                }
            });
    }

    loadConfig();

    if (configSaveBtn && configTextarea) {
        configSaveBtn.addEventListener('click', () => {
            const content = configTextarea.value;

            try {
                JSON.parse(content);
            } catch (e: any) {
                if (configStatus) {
                    configStatus.textContent = 'Некорректный JSON: ' + (e.message || '');
                    configStatus.className = 'page-ai__config-status page-ai__config-status--error';
                    configStatus.style.display = 'block';
                }
                return;
            }

            configSaveBtn.disabled = true;
            configSaveBtn.textContent = 'Сохранение...';

            ajax.send('ai_saveConfig', {
                content,
                'csrf-token': csrf,
            })
                .then((data: any) => {
                    if (data?.type === 'fire') {
                        Fire.show(data);
                        applyConfigData({
                            content,
                            isCustom: data.isCustom ?? true,
                            path: data.path,
                        });
                    } else if (data?.type === 'error-input') {
                        ajax.eventForm(data);
                    }
                })
                .catch(() => {
                    if (configStatus) {
                        configStatus.textContent = 'Ошибка сохранения конфига';
                        configStatus.className = 'page-ai__config-status page-ai__config-status--error';
                        configStatus.style.display = 'block';
                    }
                })
                .finally(() => {
                    configSaveBtn.disabled = false;
                    configSaveBtn.textContent = 'Сохранить';
                });
        });
    }

    if (configResetBtn) {
        configResetBtn.addEventListener('click', () => {
            if (!confirm('Удалить кастомный конфиг и вернуть ai/mamp/parse-insured.json?')) {
                return;
            }

            configResetBtn.disabled = true;
            configResetBtn.textContent = 'Сброс...';

            ajax.send('ai_resetConfig', { 'csrf-token': csrf })
                .then((data: any) => {
                    if (data?.type === 'fire') {
                        Fire.show(data);
                        applyConfigData({
                            content: data.content,
                            isCustom: false,
                            path: data.path,
                        });
                    }
                })
                .catch(() => {
                    if (configStatus) {
                        configStatus.textContent = 'Ошибка сброса конфига';
                        configStatus.className = 'page-ai__config-status page-ai__config-status--error';
                        configStatus.style.display = 'block';
                    }
                })
                .finally(() => {
                    configResetBtn.disabled = false;
                    configResetBtn.textContent = 'Вернуть по умолчанию';
                });
        });
    }
}