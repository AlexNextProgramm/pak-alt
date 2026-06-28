import { Datatable } from '../module/datatable';
import { ajax } from '../module/ajax';
import { integ } from '@rocet/integration';
import { applyPhoneMask } from '../module/phone-mask';
import '../css/page/user.scss';

const page = document.querySelector('.page-user') as HTMLElement | null;

if (page) {
    const csrf = page.dataset.csrf ?? '';
    let rowCounter = 0;

    function saveRow(rowEl: HTMLTableRowElement) {
        const id = rowEl.dataset.id ?? '';
        const nameInput = rowEl.querySelector('[data-field="name"]') as HTMLInputElement | null;
        const surnameInput = rowEl.querySelector('[data-field="surname"]') as HTMLInputElement | null;
        const emailInput = rowEl.querySelector('[data-field="email"]') as HTMLInputElement | null;
        const phoneInput = rowEl.querySelector('[data-field="phone"]') as HTMLInputElement | null;
        const typeSelect = rowEl.querySelector('[data-field="type"]') as HTMLSelectElement | null;
        const passwordInput = rowEl.querySelector('[data-field="password"]') as HTMLInputElement | null;

        const name = nameInput?.value.trim() ?? '';
        const surname = surnameInput?.value.trim() ?? '';
        const email = emailInput?.value.trim() ?? '';
        const phone = phoneInput?.value.trim() ?? '';
        const type = typeSelect?.value ?? '';
        const password = passwordInput?.value ?? '';

        if (!id && name === '' && surname === '' && email === '' && phone === '' && password === '') {
            return;
        }

        // Блокируем кнопку сохранения
        const saveBtn = rowEl.querySelector('[data-action="save-row"]') as HTMLButtonElement | null;
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Сохранение...';
        }

        ajax.send('user_save', {
            id,
            name,
            surname,
            email,
            phone,
            type,
            password,
            'csrf-token': csrf,
        }).then((data: any) => {
            if (ajax.eventForm(data)) {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Сохранить';
                }
                return;
            }

            if (!data?.success) {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Сохранить';
                }
                return;
            }

            // Перезагружаем таблицу после успешного сохранения
            const table = Datatable.get('user');
            if (table) {
                table.reload();
            }
        });
    }

    function bindRowButtons(rowEl: HTMLTableRowElement) {
        const saveBtn = rowEl.querySelector('[data-action="save-row"]') as HTMLButtonElement | null;
        if (saveBtn) {
            saveBtn.addEventListener('click', () => saveRow(rowEl));
        }

        // Маска телефона
        const phoneInput = rowEl.querySelector('[data-field="phone"]') as HTMLInputElement | null;
        if (phoneInput) {
            applyPhoneMask(phoneInput);
        }
    }

    function createEmptyRow(): HTMLTableRowElement {
        const rowKey = `new-${++rowCounter}`;
        const tr = document.createElement('tr');
        tr.className = 'page-user__row--new';
        tr.dataset.rowKey = rowKey;

        tr.innerHTML = `
            <td class="page-user__id">—</td>
            <td>
                <input type="text" data-field="name" class="page-user__input page-user__input--name" placeholder="Имя">
                <input type="text" data-field="surname" class="page-user__input page-user__input--surname" placeholder="Фамилия">
            </td>
            <td><input type="text" data-field="email" class="page-user__input" placeholder="Email"></td>
            <td><input type="text" data-field="phone" class="page-user__input" placeholder="Телефон"></td>
            <td>
                <select data-field="type" class="page-user__select">
                    <option value="">Выберите роль</option>
                    <option value="1">Системный Администратор</option>
                    <option value="3">Администратор</option>
                </select>
            </td>
            <td>
                <input type="text" data-field="password" class="page-user__input page-user__input--password" placeholder="Пароль">
            </td>
            <td>
                <button type="button" data-action="save-row" class="btn btn-submit-blue page-user__save-btn">Сохранить</button>
            </td>
        `;

        bindRowButtons(tr);
        return tr;
    }

    function ensureEmptyRow() {
        const table = Datatable.get('user');
        if (!table) return;

        const tbody = table.table.querySelector('tbody');
        if (!tbody) return;

        tbody.querySelector('.not-found')?.remove();

        if (!tbody.querySelector('.page-user__row--new')) {
            tbody.appendChild(createEmptyRow());
        }
    }

    function appendEmptyRow() {
        const table = Datatable.get('user');
        if (!table) return;

        const tbody = table.table.querySelector('tbody');
        if (!tbody) return;

        tbody.querySelector('.not-found')?.remove();
        tbody.appendChild(createEmptyRow());

        const input = tbody.querySelector('.page-user__row--new:last-child [data-field="name"]') as HTMLInputElement | null;
        input?.focus();
    }

    function selectOption(value: string, selected: string): boolean {
        return value === selected;
    }

    const table = Datatable.get('user');
    if (table) {
        table.buildCells = (row: any, alias: string) => {
            if (alias === 'id') {
                return <td class="page-user__id">{String(row.id)}</td>;
            }

            if (alias === 'fio') {
                return (
                    <td>
                        <input
                            type="text"
                            data-field="name"
                            class="page-user__input page-user__input--name"
                            value={String(row.name ?? '')}
                            placeholder="Имя"
                        />
                        <input
                            type="text"
                            data-field="surname"
                            class="page-user__input page-user__input--surname"
                            value={String(row.surname ?? '')}
                            placeholder="Фамилия"
                        />
                    </td>
                );
            }

            if (alias === 'email') {
                return (
                    <td>
                        <input
                            type="text"
                            data-field="email"
                            class="page-user__input"
                            value={String(row.email ?? '')}
                        />
                    </td>
                );
            }

            if (alias === 'phone') {
                return (
                    <td>
                        <input
                            type="text"
                            data-field="phone"
                            class="page-user__input"
                            value={String(row.phone ?? '')}
                        />
                    </td>
                );
            }

            if (alias === 'type_text') {
                const typeVal = String(row.type ?? '');
                return (
                    <td>
                        <select data-field="type" class="page-user__select">
                            <option value="1" selected={selectOption('1', typeVal)}>Системный Администратор</option>
                            <option value="3" selected={selectOption('3', typeVal)}>Администратор</option>
                        </select>
                    </td>
                );
            }

            if (alias === 'password') {
                return (
                    <td>
                        <input
                            type="text"
                            data-field="password"
                            class="page-user__input page-user__input--password"
                            value=""
                            placeholder="Новый пароль"
                        />
                    </td>
                );
            }

            if (alias === 'cdate_text') {
                return <td class="page-user__cdate">{String(row.cdate_text ?? '')}</td>;
            }

            if (alias === 'actions') {
                return (
                    <td>
                        <button type="button" data-action="save-row" class="btn btn-submit-blue page-user__save-btn">Сохранить</button>
                    </td>
                );
            }

            return null;
        };

        table.buildRows = (row: any, cells: any[]) => {
            return (
                <tr data-id={String(row.id)} data-row-key={String(row.id)}>
                    {...cells}
                </tr>
            );
        };

        table.initCallback = () => {
            table.table.querySelectorAll('tbody tr[data-id]').forEach((row) => {
                bindRowButtons(row as HTMLTableRowElement);
            });

            ensureEmptyRow();
        };

        page.querySelector('[data-action="add-row"]')?.addEventListener('click', () => {
            appendEmptyRow();
        });
    }
}