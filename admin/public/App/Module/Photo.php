<?php

namespace App\Module;

use App\Model\UploadPhotoModel;
use Module\Upload\Storage;

class Photo
{
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public static function isAllowedImage(array $file): bool
    {
        $name = $file['name'] ?? '';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return in_array($ext, self::ALLOWED_EXT, true);
    }

    public static function url(string $path): string
    {
        return (new Storage())->url($path);
    }

    /**
     * @return array<int, array{id: int, path: string, url: string, position: int}>
     */
    public static function listForEntity(string $entity, ?int $entityId): array
    {
        $model = new UploadPhotoModel();

        if ($entityId) {
            $rows = $model->find(
                ['entity' => $entity, 'entity_id' => $entityId],
                fn($m) => $m->orderBy('position', 'ASC')->orderBy('id', 'ASC')
            );
        } else {
            $rows = [];
        }

        return self::mapRows($rows);
    }

    /**
     * @param int[] $photoIds
     */
    public static function syncEntity(string $entity, int $entityId, array $photoIds, string $folder): void
    {
        $photoIds = self::normalizeIds($photoIds);
        $model = new UploadPhotoModel();
        $storage = new Storage();

        $linked = $model->find(
            ['entity' => $entity, 'entity_id' => $entityId],
            fn($m) => $m->orderBy('id', 'ASC')
        );

        foreach ($linked as $row) {
            if (!in_array((int)$row['id'], $photoIds, true)) {
                $storage->delete($row['path']);
                $model->delete(['id' => (int)$row['id']]);
            }
        }

        $position = 0;
        foreach ($photoIds as $photoId) {
            $photo = new UploadPhotoModel(['id' => $photoId]);
            if (!$photo->isInfo()) {
                continue;
            }

            $data = $photo->data();
            if ($data['entity'] !== $entity) {
                continue;
            }

            if (!empty($data['entity_id']) && (int)$data['entity_id'] !== $entityId) {
                continue;
            }

            if (!str_starts_with($data['path'], $folder . '/')) {
                continue;
            }

            $photo->set([
                'entity_id' => $entityId,
                'position' => $position++,
            ]);
        }
    }

    /**
     * Удаляет фото без привязки к сущности (кроме переданных id).
     *
     * @param int[] $keepIds
     */
    public static function cleanupOrphans(string $entity, array $keepIds = []): void
    {
        $keepIds = self::normalizeIds($keepIds);
        $model = new UploadPhotoModel();
        $storage = new Storage();

        $orphans = $model->find(
            ['entity' => $entity],
            function ($m) use ($keepIds) {
                $m->whereAdd('entity_id IS NULL');
                if ($keepIds !== []) {
                    $ids = implode(',', $keepIds);
                    $m->whereAdd("id NOT IN ($ids)");
                }

                return $m;
            }
        );

        foreach ($orphans as $row) {
            $storage->delete($row['path']);
            $model->delete(['id' => (int)$row['id']]);
        }
    }

    public static function deleteEntityPhotos(string $entity, int $entityId): void
    {
        $model = new UploadPhotoModel();
        $storage = new Storage();

        $rows = $model->find(['entity' => $entity, 'entity_id' => $entityId]);

        foreach ($rows as $row) {
            $storage->delete($row['path']);
            $model->delete(['id' => (int)$row['id']]);
        }
    }

    public static function deleteById(int $id): bool
    {
        $photo = new UploadPhotoModel(['id' => $id]);
        if (!$photo->isInfo()) {
            return false;
        }

        $path = $photo->get('path');
        (new Storage())->delete($path);
        $photo->delete();

        return true;
    }

    /**
     * @param mixed $raw
     * @return int[]
     */
    public static function normalizeIds(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }

        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $ids = [];
        foreach ($raw as $value) {
            $id = (int)$value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{id: int, path: string, url: string, position: int}>
     */
    private static function mapRows(array $rows): array
    {
        $storage = new Storage();
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'id' => (int)$row['id'],
                'path' => $row['path'],
                'url' => $storage->url($row['path']),
                'position' => (int)($row['position'] ?? 0),
            ];
        }

        return $result;
    }
}
