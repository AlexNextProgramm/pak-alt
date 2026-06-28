<?php

use App\Enum\UsersType;
use App\Form\Form;
?>

<div class="page-user" data-csrf="<?= Form::csrf(true) ?>">
    <h1 class="block-header"><?= $header ?? 'Пользователи' ?></h1>

    <? include __DIR__ . '/table.php' ?>
</div>