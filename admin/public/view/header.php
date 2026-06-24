<?
use App\Module\Auth;
use App\Module\UI\UI;
?>
<header>
    <div class="wrapper admin-header">
        <div class="admin-header__brand">
            <a class="admin-header__logo" href="/">
                <img src="/<?= IMG ?>/pak-alt.png" alt="PAK-Альтамед" width="36" height="36">
                <span class="admin-header__title">PAK-Альтамед</span>
            </a>
        </div>
        <div class="admin-header__actions">
            <? if (!empty($buttonsHeader)): ?>
                <? foreach ($buttonsHeader as $but) : ?>
                    <? UI::show($but); ?>
                <? endforeach; ?>
            <? endif; ?>
            <? if (Auth::$isAuth): ?>
                <button class="btn-round btn-content-log-in" evt="exit" data="log-in" title="Выйти"></button>
            <? endif; ?>
        </div>
    </div>
</header>
