import { ajax } from '../ajax';
import { Fire } from '../ui/fire';
import { setEditorContent } from './ckeditor';

interface YandexAIOptions {
    /** CSS-селектор текстового поля, куда вставлять результат */
    targetSelector: string;
    /** Текст-подсказка для поля ввода (placeholder) */
    placeholder?: string;
}

/** Иконка «искры» для AI — stroke, как в меню админки */
function sparkleIcon(size: number): string {
    return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M12 3L13.4 9.6L20 11L13.4 12.4L12 19L10.6 12.4L4 11L10.6 9.6L12 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
        <path d="M19 3.5V6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        <path d="M20.5 5H17.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    </svg>`;
}

/**
 * Модуль кнопки AI-агента (Yandex AI).
 * Добавляет кнопку AI-агента над текстовым полем.
 * При нажатии открывает выпадающую форму с текстовым полем для запроса.
 * После получения ответа вставляет текст в целевое поле.
 */
export class YandexAI {
    private readonly targetSelector: string;
    private readonly placeholder: string;
    private container: HTMLElement | null = null;
    private dropdown: HTMLElement | null = null;
    private isOpen = false;

    constructor(options: YandexAIOptions) {
        this.targetSelector = options.targetSelector;
        this.placeholder = options.placeholder ?? 'Опишите, что нужно сгенерировать...';
    }

    /**
     * Инициализирует кнопку AI-агента для указанного текстового поля.
     */
    init(): void {
        const target = document.querySelector<HTMLTextAreaElement | HTMLInputElement>(this.targetSelector);
        if (!target) return;

        // Создаём контейнер-обёртку
        this.container = document.createElement('div');
        this.container.className = 'yandex-ai-wrapper';

        // Вставляем обёртку перед target
        target.parentNode?.insertBefore(this.container, target);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'yandex-ai-btn';
        button.title = 'AI-агент — сгенерировать текст';
        button.setAttribute('aria-label', 'AI-агент — сгенерировать текст');
        button.innerHTML = `
            <span class="yandex-ai-btn__icon" aria-hidden="true">${sparkleIcon(16)}</span>
            <span class="yandex-ai-btn__label">AI</span>
        `;
        button.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleDropdown(button);
        });

        this.container.appendChild(button);

        // Создаём выпадающую форму
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'yandex-ai-dropdown';
        this.dropdown.style.display = 'none';
        this.dropdown.innerHTML = `
            <div class="yandex-ai-dropdown__header">
                <span class="yandex-ai-dropdown__header-icon" aria-hidden="true">${sparkleIcon(20)}</span>
                <div class="yandex-ai-dropdown__header-text">
                    <span class="yandex-ai-dropdown__title">AI-агент</span>
                    <span class="yandex-ai-dropdown__subtitle">Опишите задачу — текст появится в поле ниже</span>
                </div>
            </div>
            <div class="yandex-ai-dropdown__body">
                <textarea class="yandex-ai-dropdown__input" placeholder="${this.placeholder}" rows="4"></textarea>
                <div class="yandex-ai-dropdown__actions">
                    <button type="button" class="yandex-ai-dropdown__submit">${sparkleIcon(14)} Сгенерировать</button>
                    <button type="button" class="yandex-ai-dropdown__cancel">Отмена</button>
                </div>
                <div class="yandex-ai-dropdown__loader" style="display:none;">
                    <span class="yandex-ai-dropdown__loader-spinner" aria-hidden="true"></span>
                    Генерация текста…
                </div>
            </div>
        `;

        this.container.appendChild(this.dropdown);

        // Обработчики
        const submitBtn = this.dropdown.querySelector<HTMLButtonElement>('.yandex-ai-dropdown__submit');
        const cancelBtn = this.dropdown.querySelector<HTMLButtonElement>('.yandex-ai-dropdown__cancel');
        const textarea = this.dropdown.querySelector<HTMLTextAreaElement>('.yandex-ai-dropdown__input');

        submitBtn?.addEventListener('click', () => {
            this.generate(textarea, target);
        });

        cancelBtn?.addEventListener('click', () => {
            this.close();
        });

        // Закрытие по Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });

        // Закрытие по клику вне дропдауна
        document.addEventListener('click', (e) => {
            if (this.isOpen && this.container && !this.container.contains(e.target as Node)) {
                this.close();
            }
        });
    }

    private toggleDropdown(button: HTMLElement): void {
        if (this.isOpen) {
            this.close();
        } else {
            this.open(button);
        }
    }

    private open(button: HTMLElement): void {
        if (!this.dropdown) return;
        this.isOpen = true;
        this.container?.classList.add('yandex-ai-wrapper--open');
        this.dropdown.style.display = 'block';

        // Фокус на текстовое поле
        const textarea = this.dropdown.querySelector<HTMLTextAreaElement>('.yandex-ai-dropdown__input');
        setTimeout(() => textarea?.focus(), 100);
    }

    private close(): void {
        if (!this.dropdown) return;
        this.isOpen = false;
        this.container?.classList.remove('yandex-ai-wrapper--open');
        this.dropdown.style.display = 'none';

        // Очищаем поле ввода
        const textarea = this.dropdown.querySelector<HTMLTextAreaElement>('.yandex-ai-dropdown__input');
        if (textarea) {
            textarea.value = '';
        }

        // Скрываем loader
        const loader = this.dropdown.querySelector<HTMLElement>('.yandex-ai-dropdown__loader');
        if (loader) {
            loader.style.display = 'none';
        }

        // Показываем кнопки
        const actions = this.dropdown.querySelector<HTMLElement>('.yandex-ai-dropdown__actions');
        if (actions) {
            actions.style.display = '';
        }
    }

    private async generate(
        textarea: HTMLTextAreaElement | null,
        target: HTMLTextAreaElement | HTMLInputElement
    ): Promise<void> {
        if (!textarea || !this.dropdown) return;

        const input = textarea.value.trim();
        if (!input) {
            Fire.show({ message: 'Введите текст для генерации', status: 'error' });
            return;
        }

        // Показываем loader
        const loader = this.dropdown.querySelector<HTMLElement>('.yandex-ai-dropdown__loader');
        const actions = this.dropdown.querySelector<HTMLElement>('.yandex-ai-dropdown__actions');

        if (loader) loader.style.display = 'block';
        if (actions) actions.style.display = 'none';

        try {
            const csrf = document.querySelector<HTMLElement>('[data-csrf]')?.dataset.csrf
                ?? document.querySelector('form')?.getAttribute('csrf-token')
                ?? '';

            const rawResult = await ajax.post(
                { input, 'csrf-token': csrf },
                { 'form-name': 'YandexAI/Generate' },
                '/ajax/yandexAi_generate'
            );

            let result: Record<string, unknown> = {};
            if (typeof rawResult === 'string') {
                try {
                    result = JSON.parse(rawResult);
                } catch {
                    result = {};
                }
            } else if (rawResult && typeof rawResult === 'object') {
                result = rawResult;
            }

            if (result?.type === 'modal') {
                // Показываем модалку с сообщением о недостающих настройках
                ajax.eventForm(result);
                this.close();
                return;
            }

            if (result?.type === 'fire') {
                Fire.show(result);
                this.close();
                return;
            }

            if (result?.success && result?.text) {
                if (target instanceof HTMLTextAreaElement) {
                    await setEditorContent(this.targetSelector, String(result.text));
                } else if (target instanceof HTMLInputElement) {
                    target.value = String(result.text).trim();
                }

                Fire.show({ message: 'Текст сгенерирован', status: 'success' });
                this.close();
            } else {
                Fire.show({ message: result?.error ?? 'Ошибка генерации', status: 'error' });
                this.close();
            }
        } catch (error) {
            console.error('Yandex AI error:', error);
            Fire.show({ message: 'Ошибка сети при генерации текста', status: 'error' });
            this.close();
        }
    }
}

/**
 * Инициализирует AI-агента для указанного текстового поля.
 */
export function initYandexAI(targetSelector: string, placeholder?: string): void {
    const agent = new YandexAI({ targetSelector, placeholder });
    agent.init();
}