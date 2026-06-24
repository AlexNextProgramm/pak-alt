<?php

namespace Module\PlusOfon;

class PlusOfon
{
    private const CLIENT_ID = '10553';
    private const URL = 'https://restapi.plusofon.ru/api/v1';
    private const NUMBER_ID = 106561;

    public function __construct(
        private readonly string $token,
    ) {
    }

    /**
     * Отправка SMS сообщения.
     *
     * @return array{success: bool, id?: string, error?: string, status?: int, details?: mixed}
     */
    public function sms(string|int $phone, string $text): array
    {
        try {
            $to = $this->normPhone($phone);
            $toNum = filter_var($to, FILTER_VALIDATE_INT);

            if ($to === '' || $toNum === false) {
                return ['success' => false, 'error' => 'PlusOfon: неверный номер получателя'];
            }

            $requestBody = [
                'text' => $text,
                'number_id' => self::NUMBER_ID,
                'to' => $toNum,
                'reject_long' => true,
                'count_pdu' => true,
            ];

            [$status, $bodyText] = $this->post('/sms', $requestBody);

            if ($status < 200 || $status >= 300) {
                $bodyJson = $this->tryJson($bodyText);
                $message = (is_array($bodyJson) && isset($bodyJson['message']) && is_string($bodyJson['message']))
                    ? $bodyJson['message']
                    : "PlusOfon HTTP {$status}";

                return [
                    'success' => false,
                    'status' => $status,
                    'error' => $message,
                    'details' => $bodyJson ?? $bodyText,
                ];
            }

            $data = $this->tryJson($bodyText);

            if (is_array($data) && !empty($data['success'])) {
                return [
                    'success' => true,
                    'id' => (string) ($data['data']['id'] ?? ''),
                ];
            }

            return [
                'success' => false,
                'status' => $status,
                'error' => 'PlusOfon: отправка неуспешна',
                'details' => $data ?? $bodyText,
            ];
        } catch (\Throwable $error) {
            error_log('Ошибка отправки SMS: ' . $error->getMessage());

            return [
                'success' => false,
                'error' => $error->getMessage(),
            ];
        }
    }

    private function normPhone(string|int $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            return '7' . substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '7' . $digits;
        }

        return $digits;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function post(string $path, array $body): array
    {
        $ch = curl_init(self::URL . $path);

        if ($ch === false) {
            throw new \RuntimeException('PlusOfon: не удалось инициализировать HTTP-клиент');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Client: ' . self::CLIENT_ID,
                'Authorization: Bearer ' . $this->token,
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
        ]);

        $bodyText = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($bodyText === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($error !== '' ? $error : 'PlusOfon: ошибка HTTP-запроса');
        }

        curl_close($ch);

        return [$status, $bodyText];
    }

    private function tryJson(string $value): mixed
    {
        if ($value === '') {
            return null;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
