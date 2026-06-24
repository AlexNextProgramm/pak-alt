<?

include __DIR__ . '/header.php' ?>
<div class="content">
    <div class="wrapper">
        <? include __DIR__ . "/menu.php" ?>
        <div class="desktop-wrapper">
            <? include __DIR__ . '/page' . ($desktop ?? 'desktop.php')  ?>
        </div>
    </div>
</div>
<? //include __DIR__ . '/footer.php' ?>