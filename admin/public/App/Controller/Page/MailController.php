<?php

namespace App\Controller\Page;

use App\Controller\PageController;
use Module\Imap\Client;
use Pet\Request\Request;
use Pet\Router\Error as RE;

class MailController extends PageController
{
    public function index(Request $request)
    {
        view('page.mail.init', []);
    }

    public function attachment(): void
    {
        $uid = (int)(attrs()['uid'] ?? 0);
        $part = (string)(attrs()['part'] ?? '');
        $filename = (string)(attrs()['filename'] ?? 'attachment');
        $mime = (string)(attrs()['mime'] ?? 'application/octet-stream');

        if ($uid <= 0 || $part === '' || !preg_match('/^[0-9]+(?:\.[0-9]+)*$/', $part)) {
            RE::setHttp(RE::STATUS_HTTP::BAD_REQUEST);
            echo 'Некорректные параметры';
            return;
        }

        $filename = basename(str_replace(["\0", '/', '\\'], '', $filename));
        if ($filename === '') {
            $filename = 'attachment';
        }

        $client = new Client();
        $result = $client->getAttachment($uid, $part, keepUnread: true);

        if (!$result['success']) {
            RE::setHttp(RE::STATUS_HTTP::NOT_FOUND);
            echo $result['error'] ?? 'Не удалось получить вложение';
            return;
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
