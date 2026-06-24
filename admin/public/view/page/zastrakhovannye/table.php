<section class="page-zastrakhovannye__section">
    <div class="page-zastrakhovannye__section-head">
        <div>
            <h2 class="page-zastrakhovannye__subtitle">Список застрахованных</h2>
            <p class="page-zastrakhovannye__hint">Управление застрахованными лицами.</p>
        </div>
    </div>

    <table name="zastrakhovannye" limit="500">
        <thead>
            <tr name="column">
                <th alias="id">ID</th>
                <th alias="surname">Фамилия</th>
                <th alias="name">Имя</th>
                <th alias="patronymic">Отчество</th>
                <th alias="birth_date">Дата рождения</th>
                <th alias="gender">Пол</th>
                <th alias="policy_number">№ полиса</th>
                <th alias="phone_mobile">Моб. телефон</th>
                <th alias="service_start">Начало</th>
                <th alias="service_end">Окончание</th>
                <th alias="update">Обновлено</th>
                <th alias="cdate">Создано</th>
            </tr>
            <tr name="filter">
                <th><input type="text" name="id" placeholder="ID"></th>
                <th><input type="text" name="surname" sign="LIKE" placeholder="Фамилия"></th>
                <th><input type="text" name="name" sign="LIKE" placeholder="Имя"></th>
                <th><input type="text" name="patronymic" sign="LIKE" placeholder="Отчество"></th>
                <th></th>
                <th></th>
                <th><input type="text" name="policy_number" sign="LIKE" placeholder="№ полиса"></th>
                <th><input type="text" name="phone_mobile" sign="LIKE" placeholder="Телефон"></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</section>