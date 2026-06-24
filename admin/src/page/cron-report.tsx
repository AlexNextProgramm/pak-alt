import { Datatable } from '../module/datatable';
import { integ } from '@rocet/integration';
import '../css/page/cron-report.scss';

const page = document.querySelector('.page-cron-report') as HTMLElement | null;

if (page) {
    const table = Datatable.get('cron_report');

    if (table) {
        table.buildCells = (row: any, alias: string) => {
            if (alias === 'id') {
                return <td class="page-cron-report__id">{String(row.id)}</td>;
            }

            if (alias === 'started_at') {
                return <td class="page-cron-report__cell">{String(row.started_at ?? '')}</td>;
            }

            if (alias === 'emails_found') {
                return <td class="page-cron-report__cell">{String(row.emails_found ?? '0')}</td>;
            }

            if (alias === 'errors_short') {
                const errors = row.errors ?? '';
                const short = row.errors_short ?? '—';
                if (errors) {
                    return (
                        <td class="page-cron-report__cell page-cron-report__errors" title={String(errors)}>
                            {String(short)}
                        </td>
                    );
                }
                return <td class="page-cron-report__cell">—</td>;
            }

            if (alias === 'status_label') {
                const status = row.status ?? '';
                return (
                    <td class="page-cron-report__cell">
                        <span class={`page-cron-report__status page-cron-report__status--${status}`}>
                            {String(row.status_label ?? status)}
                        </span>
                    </td>
                );
            }

            return null;
        };

        table.buildRows = (row: any, cells: any[]) => {
            return (
                <tr data-id={String(row.id)}>
                    {...cells}
                </tr>
            );
        };
    }
}