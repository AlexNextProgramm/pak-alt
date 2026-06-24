<?php

namespace App\Controller\Ajax\Mail;

use App\Controller\AjaxController;
use Module\Imap\Client;
use Pet\Request\Request;
use Pet\Router\Error as RE;
use Pet\Router\Response;
use Pet\Session\Session;

class Attachment extends AjaxController
{
    public function __construct()
    {
    }

    public function helper(): void
    {
        $token = attr('csrf-token');
        unset(Request::$attribute['csrf-token']);

        if ($token != Session::get('csrf-token')) {
            RE::setHttp(RE::STATUS_HTTP::FORBIDDEN);
            Response::die('Не действительный токен csrf или проблема с сессиями на сервере');
        }

        $uid = (int)(attrs()['uid'] ?? 0);
        $part = (string)(attrs()['part'] ?? '');
        $filename = (string)(attrs()['filename'] ?? 'attachment');
        $mime = (string)(attrs()['mime'] ?? 'application/octet-stream');

        if ($uid <= 0 || $part === '' || !preg_match('/^[0-9]+(?:\.[0-9]+)*$/', $part)) {
            RE::setHttp(RE::STATUS_HTTP::BAD_REQUEST);
            Response::die('Некорректные параметры');
        }

        $filename = basename(str_replace(["\0", '/', '\\'], '', $filename));
        if ($filename === '') {
            $filename = 'attachment';
        }

        $client = new Client();
        $result = $client->getAttachment($uid, $part, keepUnread: true);

        if (!$result['success']) {
            RE::setHttp(RE::STATUS_HTTP::NOT_FOUND);
            Response::die($result['error'] ?? 'Не удалось получить вложение');
        }

        $content = $result['content'];
        $safeMime = preg_match('/^[\w.+-]+\/[\w.+-]+$/', $mime) ? $mime : 'application/octet-stream';

        header('Content-Type: ' . $safeMime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-store');

        Response::die($content);
    }
}
