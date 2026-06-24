<section class="page-variables__section">
    <div class="page-variables__section-head">
        <div>
            <h2 class="page-variables__subtitle">Список переменных</h2>
            <p class="page-variables__hint">Новые переменные можно добавить здесь или через миграцию. Удаление запрещено.</p>
        </div>
        <button type="button" class="btn btn-submit-blue page-variables__add" data-action="add-row" title="Добавить строку">+</button>
    </div>

    <table name="variable" limit="500">
        <thead>
            <tr name="column">
                <th alias="id">ID</th>
                <th alias="type">Тип</th>
                <th alias="name">Имя</th>
                <th alias="value">Значение</th>
            </tr>
            <tr name="filter">
                <th><input type="text" name="id" placeholder="ID"></th>
                <th><input type="text" name="type" sign="LIKE" placeholder="Тип"></th>
                <th><input type="text" name="name" sign="LIKE" placeholder="Имя"></th>
                <th></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
