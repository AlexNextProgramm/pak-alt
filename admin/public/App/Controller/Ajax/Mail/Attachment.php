<?php

namespace App\Controller\Ajax\Mail;

use App\Controller\AjaxController;
use Module\Imap\Client;
use Pet\Router\HTTP;
use Pet\Router\Response;

class Attachment extends AjaxController
{
    public function __construct()
    {
    }

    public function helper(): void
    {
        AjaxController::checkCsrf();

        $uid = (int)(attrs()['uid'] ?? 0);
        $part = (string)(attrs()['part'] ?? '');
        $filename = (string)(attrs()['filename'] ?? 'attachment');
        $mime = (string)(attrs()['mime'] ?? 'application/octet-stream');

        if ($uid <= 0 || $part === '' || !preg_match('/^[0-9]+(?:\.[0-9]+)*$/', $part)) {
            Response::json(['error' => 'Некорректные параметры'], HTTP::BAD_REQUEST);
        }

        $filename = basename(str_replace(["\0", '/', '\\'], '', $filename));
        if ($filename === '') {
            $filename = 'attachment';
        }

        $client = new Client();
        $result = $client->getAttachment($uid, $part, keepUnread: true);

        if (!$result['success']) {
            Response::json(['error' => $result['error'] ?? 'Не удалось получить вложение'], HTTP::NOT_FOUND);
        }

        $content = $result['content'];
        $safeMime = preg_match('/^[\w.+-]+\/[\w.+-]+$/', $mime) ? $mime : 'application/octet-stream';

        header('Content-Type: ' . $safeMime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-store');

        echo $content;
        exit;
    }
}
