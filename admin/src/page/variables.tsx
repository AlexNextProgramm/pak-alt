import { Datatable } from '../module/datatable';
import { ajax } from '../module/ajax';
import { integ } from '@rocet/integration';
import '../css/page/variables.scss';

const DEBOUNCE_MS = 1300;

const page = document.querySelector('.page-variables') as HTMLElement | null;

if (page) {
    const csrf = page.dataset.csrf ?? '';
    const debounceTimers = new Map<string, ReturnType<typeof setTimeout>>();
    let rowCounter = 0;

    function lockTypeName(rowEl: HTMLTableRowElement, type: string, name: string) {
        const typeCell = rowEl.querySelector('[data-field="type"]')?.closest('td');
        const nameCell = rowEl.querySelector('[data-field="name"]')?.closest('td');

        if (typeCell) {
            typeCell.className = 'page-variables__static';
            typeCell.textContent = type;
        }

        if (nameCell) {
            nameCell.className = 'page-variables__static';
            nameCell.textContent = name;
        }
    }

    function saveRow(rowEl: HTMLTableRowElement) {
        const id = rowEl.dataset.id ?? '';
        const typeInput = rowEl.querySelector('[data-field="type"]') as HTMLInputElement | null;
        const nameInput = rowEl.querySelector('[data-field="name"]') as HTMLInputElement | null;
        const valueInput = rowEl.querySelector('[data-field="value"]') as HTMLInputElement | null;

        if (!valueInput) return;

        const type = typeInput?.value.trim() ?? '';
        const name = nameInput?.value.trim() ?? '';
        const value = valueInput.value;

        if (!id && type === '' && name === '' && value === '') {
            return;
        }

        ajax.send('variable_save', {
            id,
            type,
            name,
            value,
            'csrf-token': csrf,
        }).then((data: any) => {
            if (data?.type === 'error-input') {
                ajax.eventForm(data);
                return;
            }

            if (!data?.success) return;

            if (data.id) {
                rowEl.dataset.id = String(data.id);
                rowEl.classList.remove('page-variables__row--new');

                const idCell = rowEl.querySelector('.page-variables__id');
                if (idCell) {
                    idCell.textContent = String(data.id);
                }

                const rowKey = rowEl.dataset.rowKey;
                if (rowKey) {
                    debounceTimers.delete(rowKey);
                    rowEl.dataset.rowKey = String(data.id);
                }

                if (typeInput && nameInput) {
                    lockTypeName(rowEl, type, name);
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
        tr.className = 'page-variables__row--new';
        tr.dataset.rowKey = rowKey;

        tr.innerHTML = `
            <td class="page-variables__id">—</td>
            <td><input type="text" data-field="type" class="page-variables__input" placeholder="Тип"></td>
            <td><input type="text" data-field="name" class="page-variables__input" placeholder="Имя"></td>
            <td><input type="text" data-field="value" class="page-variables__input" placeholder="Значение"></td>
        `;

        bindRowInputs(tr);
        return tr;
    }

    function ensureEmptyRow() {
        const table = Datatable.get('variable');
        if (!table) return;

        const tbody = table.table.querySelector('tbody');
        if (!tbody) return;

        tbody.querySelector('.not-found')?.remove();

        if (!tbody.querySelector('.page-variables__row--new')) {
            tbody.appendChild(createEmptyRow());
        }
    }

    function appendEmptyRow() {
        const table = Datatable.get('variable');
        if (!table) return;

        const tbody = table.table.querySelector('tbody');
        if (!tbody) return;

        tbody.querySelector('.not-found')?.remove();
        tbody.appendChild(createEmptyRow());

        const input = tbody.querySelector('.page-variables__row--new:last-child [data-field="type"]') as HTMLInputElement | null;
        input?.focus();
    }

    const table = Datatable.get('variable');
    if (table) {
        table.buildCells = (row: any, alias: string) => {
            if (alias === 'id') {
                return <td class="page-variables__id">{String(row.id)}</td>;
            }

            if (alias === 'type' || alias === 'name') {
                return <td class="page-variables__static">{String(row[alias] ?? '')}</td>;
            }

            if (alias === 'value') {
                return (
                    <td>
                        <input
                            type="text"
                            data-field="value"
                            class="page-variables__input"
                            value={String(row.value ?? '')}
                        />
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
                bindRowInputs(row as HTMLTableRowElement);
            });

            ensureEmptyRow();
        };

        page.querySelector('[data-action="add-row"]')?.addEventListener('click', () => {
            appendEmptyRow();
        });
    }
}
