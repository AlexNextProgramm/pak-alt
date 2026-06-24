<?php

namespace App\Controller\Ajax\Mail;

use App\Controller\AjaxController;
use Module\Imap\Client;
use Pet\Request\Request;
use Pet\Router\Error as RE;
use Pet\Router\Response;
use Pet\Session\Session;

class View extends AjaxController
{
    public function helper(): array
    {
        $token = attr('csrf-token');
        unset(Request::$attribute['csrf-token']);

        if ($token != Session::get('csrf-token')) {
            RE::setHttp(RE::STATUS_HTTP::FORBIDDEN);
            Response::die('Не действительный токен csrf или проблема с сессиями на сервере');
        }

        $uid = (int)(attrs()['uid'] ?? 0);
        if ($uid <= 0) {
            return [
                'success' => false,
                'error' => 'Не указан UID письма',
            ];
        }

        $client = new Client();
        $result = $client->getMessage($uid);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Не удалось загрузить письмо',
            ];
        }

        $message = $result['message'];
        $attachments = [];

        foreach ($message['attachments'] ?? [] as $attachment) {
            $attachments[] = [
                'part' => (string)($attachment['part'] ?? ''),
                'filename' => (string)($attachment['filename'] ?? 'attachment'),
                'mime' => (string)($attachment['mime'] ?? 'application/octet-stream'),
                'size' => (int)($attachment['size'] ?? 0),
            ];
        }

        return [
            'success' => true,
            'message' => [
                'uid' => (int)($message['uid'] ?? $uid),
                'subject' => trim((string)($message['subject'] ?? '')) ?: '(без темы)',
                'from' => (string)($message['from'] ?? ''),
                'to' => (string)($message['to'] ?? ''),
                'date' => (string)($message['date'] ?? ''),
                'body_text' => (string)($message['body_text'] ?? ''),
                'body_html' => (string)($message['body_html'] ?? ''),
                'attachments' => $attachments,
            ],
        ];
    }
}
