<section class="page-mail__section">
    <div class="page-mail__section-head">
        <div>
            <h2 class="page-mail__subtitle">Непрочитанные письма</h2>
            <p class="page-mail__hint">Список непрочитанных писем из почтового ящика IMAP. Настройки подключения — в разделе «Переменные» (<code>imap.*</code>).</p>
        </div>
    </div>

    <table name="mail" limit="50" pagination="1" infoFooter="1" statusInfo="1">
        <thead>
            <tr name="column">
                <th alias="uid">UID</th>
                <th alias="from">Отправитель</th>
                <th alias="subject">Тема</th>
                <th alias="date_formatted">Дата</th>
                <th alias="size_formatted">Размер</th>
                <th alias="actions">Действие</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
