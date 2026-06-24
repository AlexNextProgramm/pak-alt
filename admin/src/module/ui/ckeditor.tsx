let ckeditorPromise: Promise<NonNullable<typeof window.CKEDITOR>> | null = null;
const editorsBySelector = new Map<string, { setData: (data: string, options?: { callback?: () => void }) => void }>();

function loadCkeditor(): Promise<NonNullable<typeof window.CKEDITOR>> {
    if (window.CKEDITOR) {
        return Promise.resolve(window.CKEDITOR);
    }

    if (!ckeditorPromise) {
        window.CKEDITOR_BASEPATH = "/view/assets/ckeditor/";

        ckeditorPromise = new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = "/view/assets/ckeditor/ckeditor.js";
            script.async = true;
            script.onload = () => {
                if (window.CKEDITOR) {
                    resolve(window.CKEDITOR);
                    return;
                }
                reject(new Error("CKEditor не загрузился"));
            };
            script.onerror = () => reject(new Error("Не удалось загрузить CKEditor"));
            document.head.appendChild(script);
        });
    }

    return ckeditorPromise;
}

export function syncBlogEditor(): void {
    if (!window.CKEDITOR) {
        return;
    }

    Object.values(window.CKEDITOR.instances).forEach((instance) => {
        instance.updateElement();
    });
}

function normalizeEditorContent(text: string): string {
    const trimmed = text.trim();
    const fenced = trimmed.match(/^```(?:html)?\s*([\s\S]*?)```$/i);

    return fenced ? fenced[1].trim() : trimmed;
}

function resolveEditorInstance(
    CKEDITOR: NonNullable<typeof window.CKEDITOR>,
    element: HTMLTextAreaElement,
    selector: string
) {
    const fromRegistry = editorsBySelector.get(selector);
    if (fromRegistry) {
        return fromRegistry;
    }

    const editorId = element.id;
    if (editorId && CKEDITOR.instances[editorId]) {
        return CKEDITOR.instances[editorId];
    }

    for (const instance of Object.values(CKEDITOR.instances)) {
        const editorElement = (instance as { element?: { $?: Element } }).element;
        if (editorElement?.$ === element) {
            return instance;
        }
    }

    return null;
}

/**
 * Вставляет HTML/текст в textarea или связанный CKEditor.
 */
export async function setEditorContent(selector: string, content: string): Promise<boolean> {
    const element = document.querySelector(selector);

    if (!element || !(element instanceof HTMLTextAreaElement)) {
        return false;
    }

    const normalized = normalizeEditorContent(content);

    try {
        const CKEDITOR = await loadCkeditor();

        for (let attempt = 0; attempt < 30; attempt++) {
            const instance = resolveEditorInstance(CKEDITOR, element, selector);

            if (instance) {
                await new Promise<void>((resolve) => {
                    instance.setData(normalized, {
                        callback: () => {
                            element.value = normalized;
                            resolve();
                        },
                    });
                });
                return true;
            }

            await new Promise((resolve) => setTimeout(resolve, 100));
        }
    } catch (error) {
        console.error(error);
    }

    element.value = normalized;
    return true;
}

export function initBlogEditor(selector: string): void {
    const element = document.querySelector(selector);

    if (!element || !(element instanceof HTMLTextAreaElement)) {
        return;
    }

    const editorId = element.id || "blog-content";

    loadCkeditor()
        .then((CKEDITOR) => {
            if (CKEDITOR.instances[editorId]) {
                editorsBySelector.set(selector, CKEDITOR.instances[editorId]);
                return;
            }

            CKEDITOR.config.versionCheck = false;

            CKEDITOR.replace(element, {
                language: "ru",
                height: 320,
                removePlugins: "elementspath",
                resize_enabled: true,
                versionCheck: false,
                on: {
                    instanceReady(event: { editor: { setData: (data: string, options?: { callback?: () => void }) => void } }) {
                        editorsBySelector.set(selector, event.editor);
                    },
                },
            });

            window.syncEditorsBeforeSubmit = syncBlogEditor;
        })
        .catch((error) => {
            console.error(error);
        });
}
