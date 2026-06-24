<section class="page-company__section">
    <div class="page-company__section-head">
        <div>
            <h2 class="page-company__subtitle">Список компаний</h2>
            <p class="page-company__hint">Управление страховыми компаниями-партнёрами.</p>
        </div>
        <button type="button" class="btn btn-submit-blue page-company__add" data-action="add-row" title="Добавить компанию">+</button>
    </div>

    <table name="company" limit="500">
        <thead>
            <tr name="column">
                <th alias="id">ID</th>
                <th alias="name">Название</th>
                <th alias="email">Email</th>
                <th alias="update">Обновлено</th>
                <th alias="cdate">Создано</th>
            </tr>
            <tr name="filter">
                <th><input type="text" name="id" placeholder="ID"></th>
                <th><input type="text" name="name" sign="LIKE" placeholder="Название"></th>
                <th><input type="text" name="email" sign="LIKE" placeholder="Email"></th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</section>