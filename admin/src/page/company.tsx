import { Datatable } from '../module/datatable';
import { ajax } from '../module/ajax';
import { integ } from '@rocet/integration';
import '../css/page/company.scss';

const DEBOUNCE_MS = 1300;

const page = document.querySelector('.page-company') as HTMLElement | null;

if (page) {
    const csrf = page.dataset.csrf ?? '';
    const debounceTimers = new Map<string, ReturnType<typeof setTimeout>>();
    let rowCounter = 0;

    function saveRow(rowEl: HTMLTableRowElement) {
        const id = rowEl.dataset.id ?? '';
        const nameInput = rowEl.querySelector('[data-field="name"]') as HTMLInputElement | null;
        const emailInput = rowEl.querySelector('[data-field="email"]') as HTMLInputElement | null;

        if (!nameInput) return;

        const name = nameInput.value.trim();
        const email = emailInput?.value.trim() ?? '';

        if (!id && name === '' && email === '') {
            return;
        }

        ajax.send('company_save', {
            id,
            name,
            email,
            'csrf-token': csrf,
        }).then((data: any) => {
            if (data?.type === 'error-input') {
                ajax.eventForm(data);
                return;
            }

            if (!data?.success) return;

            if (data.id) {
                rowEl.dataset.id = String(data.id);
                rowEl.classList.remove('page-company__row--new');

                const idCell = rowEl.querySelector('.page-company__id');
                if (idCell) {
                    idCell.textContent = String(data.id);
                }

                const rowKey = rowEl.dataset.rowKey;
                if (rowKey) {
                    debounceTimers.delete(rowKey);
                    rowEl.dataset.rowKey = String(data.id);
                }

                if (nameInput) {
                    const nameCell = nameInput.closest('td');
                    if (nameCell) {
                        nameCell.className = 'page-company__static';
                        nameCell.textContent = name;
                    }
                }

                if (emailInput) {
                    const emailCell = emailInput.closest('td');
                    if (emailCell) {
                        emailCell.className = 'page-company__static';
                        emailCell.textContent = email;
                    }
                }
            }

            ensureEmptyRow();
        });
    }

    function scheduleSave(rowEl: HTMLTableRowElement) {
        const rowKey = rowEl.dataset.rowKey ?? rowEl.dataset.id ?? 'new';
        const existing = debounceTimers.get(rowKey);
        if (existing) {
            clearTimeout(existing);
        }

        debounceTimers.set(
            rowKey,
            setTimeout(() => saveRow(rowEl), DEBOUNCE_MS),
        );
    }

    function bindRowInputs(rowEl: HTMLTableRowElement) {
        rowEl.querySelectorAll('[data-field]').forEach((input) => {
            input.addEventListener('input', () => scheduleSave(rowEl));
        });
    }

    function createEmptyRow(): HTMLTableRowElement {
        const rowKey = `new-${++rowCounter}`;
        const tr = document.createElement('tr');
        tr.className = 'page-company__row--new';
        tr.dataset.rowKey = rowKey;

        tr.innerHTML = `
            <td class="page-company__id">—</td>
            <td><input type="text" data-field="name" class="page-company__input" placeholder="Название компании"></td>
            <td><input type="text" data-field="email" class="page-company__input" placeholder="Email"></td>
            <td class="page-company__static">—</td>
            <td class="page-company__static">—</td>
        `;

        bindRowInputs(tr);
        return tr;
    }

    function ensureEmptyRow() {
        const table = Datatable.get('company');
        if (!table) return;

        const tbody = table.table.querySelector('tbody');
        if (!tbody) return;

        tbody.querySelector('.not-found')?.remove();

        if (!tbody.querySelector('.page-company__row--new')) {
            tbody.appendChild(createEmptyRow());
        }
    }

    function appendEmptyRow() {
        const table = Datatable.get('company');
        if (!table) return;

        const tbody = table.table.querySelector('tbody');
        if (!tbody) return;

        tbody.querySelector('.not-found')?.remove();
        tbody.appendChild(createEmptyRow());

        const input = tbody.querySelector('.page-company__row--new:last-child [data-field="name"]') as HTMLInputElement | null;
        input?.focus();
    }

    const table = Datatable.get('company');
    if (table) {
        table.buildCells = (row: any, alias: string) => {
            if (alias === 'id') {
                return <td class="page-company__id">{String(row.id)}</td>;
            }

            if (alias === 'name') {
                return (
                    <td class="page-company__static">{String(row.name ?? '')}</td>
                );
            }

            if (alias === 'email') {
                return (
                    <td class="page-company__static">{String(row.email ?? '')}</td>
                );
            }

            if (alias === 'update' || alias === 'cdate') {
                return <td class="page-company__static">{String(row[alias] ?? '')}</td>;
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
                bindRowInputs(row as HTMLTableRowElement);
            });

            ensureEmptyRow();
        };

        page.querySelector('[data-action="add-row"]')?.addEventListener('click', () => {
            appendEmptyRow();
        });
    }
}