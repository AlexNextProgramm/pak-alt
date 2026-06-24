<?php

use App\Form\Form;
?>

<div class="page-mail" data-csrf="<?= Form::csrf(true) ?>">
    <h1 class="block-header">Почта</h1>

    <? include __DIR__ . '/table.php' ?>
</div>
