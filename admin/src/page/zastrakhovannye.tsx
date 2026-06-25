import { Datatable } from '../module/datatable';
import { integ } from '@rocet/integration';
import '../css/page/zastrakhovannye.scss';

const page = document.querySelector('.page-zastrakhovannye') as HTMLElement | null;

function formatGender(value: unknown): string {
    const gender = String(value ?? '').trim().toUpperCase();
    if (gender === 'М' || gender === 'M') return 'Муж.';
    if (gender === 'Ж' || gender === 'F') return 'Жен.';
    return '';
}

if (page) {
    const table = Datatable.get('zastrakhovannye');
    if (table) {
        table.buildCells = (row: any, alias: string) => {
            if (alias === 'id') {
                return <td class="page-zastrakhovannye__id">{String(row.id)}</td>;
            }

            if (alias === 'operation_type_label') {
                const type = String(row.operation_type ?? '');
                if (!type) {
                    return <td class="page-zastrakhovannye__static">—</td>;
                }

                return (
                    <td class="page-zastrakhovannye__static">
                        <span class={`page-zastrakhovannye__operation page-zastrakhovannye__operation--${type}`}>
                            {String(row.operation_type_label ?? type)}
                        </span>
                    </td>
                );
            }

            if (alias === 'surname' || alias === 'name' || alias === 'patronymic') {
                return <td class="page-zastrakhovannye__static">{String(row[alias] ?? '')}</td>;
            }

            if (alias === 'birth_date') {
                return <td class="page-zastrakhovannye__static">{String(row.birth_date ?? '')}</td>;
            }

            if (alias === 'gender') {
                return <td class="page-zastrakhovannye__static">{formatGender(row.gender)}</td>;
            }

            if (alias === 'address') {
                const address = String(row.address ?? '');
                return (
                    <td class="page-zastrakhovannye__static page-zastrakhovannye__address" title={address}>
                        {address}
                    </td>
                );
            }

            if (alias === 'phone_home' || alias === 'phone_work' || alias === 'phone_mobile') {
                return <td class="page-zastrakhovannye__static">{String(row[alias] ?? '')}</td>;
            }

            if (alias === 'policy_number') {
                return <td class="page-zastrakhovannye__static">{String(row.policy_number ?? '')}</td>;
            }

            if (alias === 'service_start' || alias === 'service_end') {
                return <td class="page-zastrakhovannye__static">{String(row[alias] ?? '')}</td>;
            }

            if (alias === 'program' || alias === 'workplace' || alias === 'position') {
                const value = String(row[alias] ?? '');
                return (
                    <td class="page-zastrakhovannye__static page-zastrakhovannye__wrap" title={value}>
                        {value}
                    </td>
                );
            }

            if (alias === 'update' || alias === 'cdate') {
                return <td class="page-zastrakhovannye__static">{String(row[alias] ?? '')}</td>;
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
    }
}
