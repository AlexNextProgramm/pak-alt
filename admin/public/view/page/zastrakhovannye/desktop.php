<?php

use App\Form\Form;
?>

<div class="page-zastrakhovannye" data-csrf="<?= Form::csrf(true) ?>">
    <h1 class="block-header"><?= $header ?? 'Застрахованные' ?></h1>

    <? include __DIR__ . '/table.php' ?>
</div>