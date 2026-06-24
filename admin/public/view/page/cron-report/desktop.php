<?php

use App\Form\Form;
?>

<div class="page-cron-report" data-csrf="<?= Form::csrf(true) ?>">
    <h1 class="block-header">Отчёт крона</h1>

    <? include __DIR__ . '/table.php' ?>
</div>