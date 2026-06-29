<?php

namespace App\Form\Photo;

use App\Form\Form;
use App\Model\UploadPhotoModel;
use App\Module\Photo;
use App\Module\UI\Fire;
use Pet\Request\Request;
use Module\Upload\Storage;

class Upload extends Form
{
    public $auth = true;

    public function submit(Request $request)
    {
        $fields = Form::normalizerFields();
        $entity = trim((string)($fields['entity'] ?? ''));
        $folder = trim((string)($fields['folder'] ?? $entity));

        if ($entity === '' || $folder === '') {
            return new Fire('Не указана сущность', Fire::ERROR);
        }

        if (str_contains($entity, '/') || str_contains($folder, '/') || str_contains($folder, '..')) {
            return new Fire('Некорректные параметры', Fire::ERROR);
        }

        $file = request()->allFiles()['file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return new Fire('Файл не получен', Fire::ERROR);
        }

        if (!Photo::isAllowedImage($file)) {
            return new Fire('Допустимы только изображения (jpg, png, gif, webp)', Fire::ERROR);
        }

        $entityId = !empty($fields['entity_id']) ? (int)$fields['entity_id'] : null;
        $storage = new Storage();

        try {
            $path = $storage->save($file, $folder);
        } catch (\Throwable $e) {
            return new Fire('Не удалось загрузить файл ', Fire::ERROR);
        }

        $model = new UploadPhotoModel();
        $existing = $model->find(['entity' => $entity], function ($m) use ($entityId) {
            if ($entityId) {
                $m->whereAdd("entity_id = '$entityId'");
            } else {
                $m->whereAdd('entity_id IS NULL');
            }

            return $m;
        });
        $position = count($existing);

        $data = [
            'entity' => $entity,
            'path' => $path,
            'position' => $position,
        ];

        if ($entityId) {
            $data['entity_id'] = $entityId;
        }

        $id = $model->create($data);

        return [
            'type' => 'photo-uploaded',
            'photo' => [
                'id' => (int)$id,
                'path' => $path,
                'url' => $storage->url($path),
                'position' => $data['position'],
            ],
        ];
    }
}
