<?php

use App\Form\Form;
use App\Module\Photo;

$entity = $entity ?? 'catalog';
$folder = $folder ?? $entity;
$entityId = !empty($entityId) ? (int)$entityId : null;
$photos = $photos ?? Photo::listForEntity($entity, $entityId);
$csrf = $csrf ?? Form::csrf(true);
?>

<div
    ui="photo-gallery"
    data-entity="<?= $entity ?>"
    data-folder="<?= $folder ?>"
    data-entity-id="<?= $entityId ?? '' ?>"
    data-csrf="<?= $csrf ?>"
    data-photos="<?= htmlspecialchars(json_encode($photos, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
></div>
