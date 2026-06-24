import { Datatable } from '../module/datatable';
import { ajax } from '../module/ajax';
import { integ } from '@rocet/integration';
import { Fire } from '../module/ui/fire';
import '../css/page/mail.scss';

type MailAttachment = {
    part: string;
    filename: string;
    mime: string;
    size: number;
};

type MailMessage = {
    uid: number;
    subject: string;
    from: string;
    to: string;
    date: string;
    body_text: string;
    body_html: string;
    attachments: MailAttachment[];
};

const page = document.querySelector('.page-mail') as HTMLElement | null;

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} Б`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round((bytes / 1024) * 10) / 10} КБ`;
    }

    return `${Math.round((bytes / (1024 * 1024)) * 10) / 10} МБ`;
}

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatDate(date: string): string {
    if (!date) {
        return '—';
    }

    const timestamp = Date.parse(date);
    if (Number.isNaN(timestamp)) {
        return date;
    }

    return new Date(timestamp).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function buildAttachmentDownload(
    message: MailMessage,
    attachment: MailAttachment,
    csrf: string,
): () => Promise<void> {
    return async () => {
        const body = new FormData();
        body.append('uid', String(message.uid));
        body.append('part', attachment.part);
        body.append('filename', attachment.filename);
        body.append('mime', attachment.mime);
        body.append('csrf-token', csrf);

        try {
            const response = await fetch('/ajax/mail_attachment', {
                method: 'POST',
                body,
            });

            if (!response.ok) {
                const errorText = await response.text();
                Fire.show({
                    status: 'error',
                    text: errorText || 'Не удалось скачать вложение',
                });
                return;
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = attachment.filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        } catch {
            Fire.show({
                status: 'error',
                text: 'Ошибка при скачивании вложения',
            });
        }
    };
}

function closeMailModal(): void {
    document.querySelector('.page-mail__modal-overlay')?.remove();
}

function openMailModal(message: MailMessage, csrf: string): void {
    closeMailModal();

    const overlay = document.createElement('div');
    overlay.className = 'modal-carset page-mail__modal-overlay';

    const modal = document.createElement('div');
    modal.className = 'modal-fones page-mail__modal';

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'modal-close';
    closeButton.setAttribute('aria-label', 'Закрыть');
    closeButton.textContent = '×';
    closeButton.addEventListener('click', closeMailModal);

    const header = document.createElement('div');
    header.className = 'page-mail__modal-header';
    header.innerHTML = `
        <h3 class="page-mail__modal-title">${escapeHtml(message.subject)}</h3>
        <p class="page-mail__modal-meta"><strong>От:</strong> ${escapeHtml(message.from)}</p>
        <p class="page-mail__modal-meta"><strong>Кому:</strong> ${escapeHtml(message.to || '—')}</p>
        <p class="page-mail__modal-meta"><strong>Дата:</strong> ${escapeHtml(formatDate(message.date))}</p>
    `;

    const attachmentsBlock = document.createElement('div');
    attachmentsBlock.className = 'page-mail__modal-attachments';

    const attachmentsTitle = document.createElement('h4');
    attachmentsTitle.textContent = 'Вложения';
    attachmentsBlock.appendChild(attachmentsTitle);

    const attachmentsList = document.createElement('ul');
    attachmentsList.className = 'page-mail__attachments-list';

    if (!message.attachments.length) {
        const emptyItem = document.createElement('li');
        emptyItem.className = 'page-mail__no-attachments';
        emptyItem.textContent = 'Вложений нет';
        attachmentsList.appendChild(emptyItem);
    } else {
        message.attachments.forEach((attachment) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'page-mail__attachment-link';
            button.textContent = `${attachment.filename} (${formatSize(attachment.size)})`;
            button.addEventListener('click', buildAttachmentDownload(message, attachment, csrf));
            item.appendChild(button);
            attachmentsList.appendChild(item);
        });
    }

    attachmentsBlock.appendChild(attachmentsList);

    const bodyBlock = document.createElement('div');
    bodyBlock.className = 'page-mail__modal-body block_body';

    if (message.body_html) {
        const frame = document.createElement('iframe');
        frame.className = 'page-mail__body-frame';
        frame.setAttribute('sandbox', '');
        frame.srcdoc = message.body_html;
        bodyBlock.appendChild(frame);
    } else {
        const pre = document.createElement('pre');
        pre.className = 'page-mail__body-text';
        pre.textContent = message.body_text || 'Текст письма отсутствует';
        bodyBlock.appendChild(pre);
    }

    modal.append(closeButton, header, attachmentsBlock, bodyBlock);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            closeMailModal();
        }
    });
}

async function openMail(uid: number): Promise<void> {
    const csrf = page?.dataset.csrf ?? '';

    try {
        const response = await ajax.send('mail_view', {
            uid: String(uid),
            'csrf-token': csrf,
        });

        if (!response?.success) {
            Fire.show({
                status: 'error',
                text: response?.error ?? 'Не удалось загрузить письмо',
            });
            return;
        }

        openMailModal(response.message as MailMessage, csrf);
    } catch {
        Fire.show({
            status: 'error',
            text: 'Ошибка при загрузке письма',
        });
    }
}

if (page) {
    page.addEventListener('click', (event) => {
        const target = event.target as HTMLElement | null;
        const button = target?.closest('[data-action="open-mail"]') as HTMLButtonElement | null;

        if (!button) {
            return;
        }

        event.preventDefault();
        const uid = Number(button.dataset.uid ?? 0);
        if (uid > 0) {
            void openMail(uid);
        }
    });

    const table = Datatable.get('mail');

    if (table) {
        table.buildCells = (row: any, alias: string) => {
            if (alias === 'uid') {
                return <td class="page-mail__uid">{String(row.uid ?? '')}</td>;
            }

            if (alias === 'from') {
                return <td class="page-mail__cell page-mail__from">{String(row.from ?? '')}</td>;
            }

            if (alias === 'subject') {
                return <td class="page-mail__cell page-mail__subject">{String(row.subject ?? '')}</td>;
            }

            if (alias === 'date_formatted') {
                return <td class="page-mail__cell page-mail__date">{String(row.date_formatted ?? '')}</td>;
            }

            if (alias === 'size_formatted') {
                return <td class="page-mail__cell page-mail__size">{String(row.size_formatted ?? '')}</td>;
            }

            if (alias === 'actions') {
                return (
                    <td class="page-mail__actions">
                        <button
                            type="button"
                            class="btn btn-submit-blue page-mail__open"
                            data-action="open-mail"
                            data-uid={String(row.uid ?? '')}
                        >
                            Открыть
                        </button>
                    </td>
                );
            }

            return null;
        };

        table.buildRows = (row: any, cells: any[]) => {
            return (
                <tr data-uid={String(row.uid ?? '')}>
                    {...cells}
                </tr>
            );
        };
    }
}
