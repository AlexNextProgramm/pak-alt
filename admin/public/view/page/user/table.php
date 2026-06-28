<?php

use App\Enum\UsersType;
?>

<section class="page-user__section">
    <div class="page-user__section-head">
        <div>
            <h2 class="page-user__subtitle">Список пользователей</h2>
            <p class="page-user__hint">Редактирование прямо в таблице. Нажмите на поле чтобы изменить.</p>
        </div>
        <button type="button" class="btn btn-submit-blue page-user__add" data-action="add-row" title="Добавить пользователя">+</button>
    </div>

    <table name="user" limit="500">
        <thead>
            <tr name="column">
                <th alias="id">ID</th>
                <th alias="fio">ФИО</th>
                <th alias="email">Email</th>
                <th alias="phone">Телефон</th>
                <th alias="type_text">Роль</th>
                <th alias="password">Пароль</th>
                <th alias="cdate_text">Создан</th>
                <th alias="actions"></th>
            </tr>
            <tr name="filter">
                <th><input type="text" name="id" placeholder="ID"></th>
                <th><input type="text" name="name" sign="LIKE" placeholder="ФИО"></th>
                <th><input type="text" name="email" sign="LIKE" placeholder="Email"></th>
                <th><input type="text" name="phone" sign="LIKE" placeholder="Телефон"></th>
                <th>
                    <select name="type">
                        <option value="">Все</option>
                        <? foreach (UsersType::data() as $typeId => $typeName): ?>
                            <option value="<?= $typeId ?>"><?= $typeName ?></option>
                        <? endforeach; ?>
                    </select>
                </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</section>