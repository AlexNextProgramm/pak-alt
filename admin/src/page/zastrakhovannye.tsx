import { Datatable } from '../module/datatable';
import { integ } from '@rocet/integration';
import '../css/page/zastrakhovannye.scss';

const page = document.querySelector('.page-zastrakhovannye') as HTMLElement | null;

if (page) {
    const table = Datatable.get('zastrakhovannye');
    if (table) {
        table.buildCells = (row: any, alias: string) => {
            if (alias === 'id') {
                return <td class="page-zastrakhovannye__id">{String(row.id)}</td>;
            }

            if (alias === 'surname' || alias === 'name' || alias === 'patronymic') {
                return <td class="page-zastrakhovannye__static">{String(row[alias] ?? '')}</td>;
            }

            if (alias === 'birth_date') {
                return <td class="page-zastrakhovannye__static">{String(row.birth_date ?? '')}</td>;
            }

            if (alias === 'gender') {
                const gender = row.gender === 'М' ? 'Муж.' : (row.gender === 'Ж' ? 'Жен.' : '');
                return <td class="page-zastrakhovannye__static">{gender}</td>;
            }

            if (alias === 'policy_number') {
                return <td class="page-zastrakhovannye__static">{String(row.policy_number ?? '')}</td>;
            }

            if (alias === 'phone_mobile') {
                return <td class="page-zastrakhovannye__static">{String(row.phone_mobile ?? '')}</td>;
            }

            if (alias === 'service_start' || alias === 'service_end') {
                return <td class="page-zastrakhovannye__static">{String(row[alias] ?? '')}</td>;
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