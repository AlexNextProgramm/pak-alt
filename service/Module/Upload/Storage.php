<?php

namespace Module\Upload;

use InvalidArgumentException;
use RuntimeException;

class Storage
{
    private string $root;
    private string $urlPrefix;

    public function __construct()
    {
        $projectRoot = dirname(ROOT);
        $this->root = $projectRoot . DS . 'var' . DS . 'uploads';
        $this->urlPrefix = defined('UPLOADS_URL') ? UPLOADS_URL : '/uploads';
        $this->ensureDir($this->root);
    }

    public function save(array $file, string $subdir = ''): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Ошибка загрузки файла');
        }

        $originalName = $file['name'] ?? 'file';
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = bin2hex(random_bytes(8)) . ($ext !== '' ? '.' . $ext : '');

        $relative = $this->joinRelative($subdir, $name);
        $dest = $this->resolve($relative);

        $this->ensureDir(dirname($dest));

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('Не удалось сохранить файл');
        }

        return $relative;
    }

    public function saveContent(string $content, string $relativePath): string
    {
        $relativePath = $this->normalizeRelative($relativePath);
        $dest = $this->resolve($relativePath);

        $this->ensureDir(dirname($dest));

        if (file_put_contents($dest, $content) === false) {
            throw new RuntimeException('Не удалось сохранить файл');
        }

        return $relativePath;
    }

    public function serve(string $relativePath): void
    {
        try {
            $fullPath = $this->resolve($relativePath);
        } catch (InvalidArgumentException) {
            http_response_code(404);
            return;
        }

        if (!is_file($fullPath)) {
            http_response_code(404);
            return;
        }

        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: public, max-age=31536000');

        readfile($fullPath);
        exit;
    }

    public function url(string $relativePath): string
    {
        $relativePath = ltrim($this->normalizeRelative($relativePath), '/');

        return rtrim($this->urlPrefix, '/') . '/' . $relativePath;
    }

    public function path(string $relativePath): string
    {
        return $this->resolve($relativePath);
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->resolve($relativePath));
    }

    public function delete(string $relativePath): bool
    {
        $fullPath = $this->resolve($relativePath);

        if (!is_file($fullPath)) {
            return false;
        }

        return unlink($fullPath);
    }

    public function __invoke($request): void
    {
        $this->serve(supple('*') ?? '');
    }

    private function resolve(string $relativePath): string
    {
        $relativePath = $this->normalizeRelative($relativePath);

        return $this->root . DS . str_replace('/', DS, $relativePath);
    }

    private function normalizeRelative(string $relativePath): string
    {
        $relativePath = str_replace(['\\', "\0"], '', $relativePath);
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '' || str_contains($relativePath, '..')) {
            throw new InvalidArgumentException('Некорректный путь к файлу');
        }

        return $relativePath;
    }

    private function joinRelative(string $subdir, string $name): string
    {
        $subdir = trim(str_replace('\\', '/', $subdir), '/');

        return $subdir !== '' ? $subdir . '/' . $name : $name;
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Не удалось создать каталог загрузок');
        }
    }
}
