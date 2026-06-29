<section class="page-cron-report__section">
    <div class="page-cron-report__section-head">
        <div>
            <h2 class="page-cron-report__subtitle">История запусков крона</h2>
            <p class="page-cron-report__hint">Журнал выполнения фоновых задач: время запуска, количество найденных писем, ошибки, статус.</p>
        </div>
    </div>

    <table name="cron_report" limit="10" pagination="1" infoFooter="1" statusInfo="1" limitInfo="1" scrolling="1">
        <thead>
            <tr name="column">
                <th alias="id">ID</th>
                <th alias="started_at">Время запуска</th>
                <th alias="emails_found">Писем найдено</th>
                <th alias="errors_short">Ошибки</th>
                <th alias="status_label">Статус</th>
            </tr>
            <tr name="filter">
                <th><input type="text" name="id" placeholder="ID"></th>
                <th></th>
                <th></th>
                <th></th>
                <th>
                    <select name="status">
                        <option value="">Все</option>
                        <option value="running">Выполняется</option>
                        <option value="success">Успешно</option>
                        <option value="error">Ошибка</option>
                        <option value="completed">Завершено</option>
                    </select>
                </th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</section>