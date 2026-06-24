<?php

use App\Form\Form;
?>

<div class="page-variables" data-csrf="<?= Form::csrf(true) ?>">
    <h1 class="block-header"><?= $header ?? 'Переменные' ?></h1>

    <? include __DIR__ . '/table.php' ?>
</div>
