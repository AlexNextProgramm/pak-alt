<?php

namespace App\Controller\Ajax\Ai;

use App\Controller\AjaxController;
use App\Form\Form;
use App\Module\Auth;
use Module\Ai\InsuredParser;
use Pet\Router\HTTP;
use Pet\Router\Response;
use RuntimeException;

class Parse extends AjaxController
{
    public function helper(): array
    {
        Auth::init();
        if (!Auth::$isAuth) {
            Response::json(['error' => 'Нет авторизации'], HTTP::FORBIDDEN);
        }

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return Form::errorInput('file', 'Ошибка загрузки файла');
        }

        $tmpPath = $file['tmp_name'];
        $origName = $file['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            return Form::errorInput('file', 'Допустимы только файлы .xlsx, .xls, .csv');
        }

        $tmpDir = sys_get_temp_dir();
        $tmpFile = $tmpDir . '/pak-alt-ai-' . uniqid() . '.' . $ext;
        copy($tmpPath, $tmpFile);

        try {
            $result = (new InsuredParser())->parseFile($tmpFile, $origName);
        } catch (RuntimeException $error) {
            return [
                'type' => 'error',
                'message' => $error->getMessage(),
            ];
        } finally {
            @unlink($tmpFile);
        }

        return [
            'type' => 'success',
            'data' => $result,
            'count' => count($result),
        ];
    }
}
