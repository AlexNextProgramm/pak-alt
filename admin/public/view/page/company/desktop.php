<?php

use App\Form\Form;
?>

<div class="page-company" data-csrf="<?= Form::csrf(true) ?>">
    <h1 class="block-header"><?= $header ?? 'Страховые компании' ?></h1>

    <? include __DIR__ . '/table.php' ?>
</div>