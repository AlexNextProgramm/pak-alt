<?php

use Pet\Cookie\Cookie;
?>

<div class="<?= (Cookie::get('menu') == '1' ? "menu menu-hide" : "menu") ?>">
    <ul>
        <li style="padding-bottom: 5px; padding-top:5px;" evt="btn-roll-up-li" tabindex="-1">
            <button class="btn btn-roll-up <?= (Cookie::get('menu') == '1' ? "" : "btn-roll-up-close") ?>" tabindex="-1"></button>
        </li>
        <? foreach ($menu as $category): ?>

            <a class="link" href="<?= $category->url ?>" tabindex="-1">
                <li class="" tabindex="-1">
                    <div class="menu-icon">
                        <? svg($category->icon) ?>
                        <? if (!empty($category->badge)): ?>
                            <span class="menu-badge"><?= (int)$category->badge ?></span>
                        <? endif; ?>
                    </div>
                    <span><?= $category->name ?></span>
                </li>
            </a>
        <? endforeach; ?>
    </ul>

    <div class="block-button">
        <div class="flex-row">
        </div>
    </div>
</div>