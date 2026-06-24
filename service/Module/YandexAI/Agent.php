<?php

namespace Module\YandexAI;

use Model\VariableModel;

class Agent
{
    private const API_URL = 'https://ai.api.cloud.yandex.net/v1/responses';

    private readonly string $apiKey;
    private readonly string $projectId;
    private readonly string $promptId;

    public function __construct()
    {
        $apiKeyModel = new VariableModel(['type' => 'yandex_ai', 'name' => 'api_key']);
        $projectModel = new VariableModel(['type' => 'yandex_ai', 'name' => 'project_id']);
        $promptModel = new VariableModel(['type' => 'yandex_ai', 'name' => 'prompt_id']);

        $this->apiKey = $apiKeyModel->isInfo() ? (string)$apiKeyModel->get('value') : '';
        $this->projectId = $projectModel->isInfo() ? (string)$projectModel->get('value') : '';
        $this->promptId = $promptModel->isInfo() ? (string)$promptModel->get('value') : '';
    }

    /**
     * Проверяет, настроен ли AI-агент (есть ли ключ и параметры).
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->projectId !== '' && $this->promptId !== '';
    }

    /**
     * Возвращает список отсутствующих настроек.
     *
     * @return string[]
     */
    public function getMissingSettings(): array
    {
        $missing = [];
        if ($this->apiKey === '') {
            $missing[] = 'API-ключ (yandex_ai.api_key)';
        }
        if ($this->projectId === '') {
            $missing[] = 'Project ID (yandex_ai.project_id)';
        }
        if ($this->promptId === '') {
            $missing[] = 'Prompt ID (yandex_ai.prompt_id)';
        }
        return $missing;
    }

    /**
     * Отправляет запрос к Yandex AI Agent и возвращает сгенерированный текст.
     *
     * @param string $input Входной текст для агента
     * @return array{success: bool, text?: string, error?: string}
     */
    public function generate(string $input): array
    {
        if (!$this->isConfigured()) {
            $missing = $this->getMissingSettings();
            return [
                'success' => false,
                'error' => 'AI-агент не настроен. Заполните в разделе "Переменные": ' . implode(', ', $missing),
            ];
        }

        try {
            $requestBody = [
                'prompt' => [
                    'id' => $this->promptId,
                ],
                'input' => $input,
            ];

            [$status, $bodyText] = $this->post($requestBody);

            if ($status < 200 || $status >= 300) {
                $bodyJson = $this->tryJson($bodyText);
                $message = (is_array($bodyJson) && isset($bodyJson['error']['message']) && is_string($bodyJson['error']['message']))
                    ? $bodyJson['error']['message']
                    : "Yandex AI HTTP {$status}";

                return [
                    'success' => false,
                    'error' => $message,
                    'details' => $bodyJson ?? $bodyText,
                ];
            }

            $data = $this->tryJson($bodyText);

            if (!is_array($data)) {
                return [
                    'success' => false,
                    'error' => 'Yandex AI: не удалось разобрать ответ',
                    'details' => $bodyText,
                ];
            }

            if (($data['status'] ?? '') === 'failed') {
                $message = is_string($data['error']['message'] ?? null)
                    ? $data['error']['message']
                    : 'Yandex AI: генерация завершилась с ошибкой';

                return [
                    'success' => false,
                    'error' => $message,
                    'details' => $data,
                ];
            }

            $text = $this->extractText($data);

            if ($text !== null && $text !== '') {
                return [
                    'success' => true,
                    'text' => $text,
                ];
            }

            return [
                'success' => false,
                'error' => 'Yandex AI: неожиданный формат ответа',
                'details' => $data,
            ];
        } catch (\Throwable $error) {
            error_log('Ошибка Yandex AI: ' . $error->getMessage());

            return [
                'success' => false,
                'error' => $error->getMessage(),
            ];
        }
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function post(array $body): array
    {
        $ch = curl_init(self::API_URL);

        if ($ch === false) {
            throw new \RuntimeException('Yandex AI: не удалось инициализировать HTTP-клиент');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Api-Key ' . $this->apiKey,
                'OpenAI-Project: ' . $this->projectId,
                'x-folder-id: ' . $this->projectId,
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
            CURLOPT_TIMEOUT => 60,
        ]);

        $bodyText = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($bodyText === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($error !== '' ? $error : 'Yandex AI: ошибка HTTP-запроса');
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

    /**
     * Извлекает текст из ответа Responses API и совместимых форматов.
     */
    private function extractText(array $data): ?string
    {
        if (isset($data['output_text']) && is_string($data['output_text']) && $data['output_text'] !== '') {
            return $data['output_text'];
        }

        if (isset($data['text']) && is_string($data['text']) && $data['text'] !== '') {
            return $data['text'];
        }

        $text = $this->extractTextFromOutput($data['output'] ?? null);
        if ($text !== null) {
            return $text;
        }

        $alternative = $data['result']['alternatives'][0] ?? null;
        if (is_array($alternative)) {
            if (isset($alternative['message']['text']) && is_string($alternative['message']['text'])) {
                return $alternative['message']['text'];
            }

            if (isset($alternative['text']) && is_string($alternative['text'])) {
                return $alternative['text'];
            }
        }

        return null;
    }

    private function extractTextFromOutput(mixed $output): ?string
    {
        if (!is_array($output)) {
            return null;
        }

        $parts = [];

        foreach ($output as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (isset($item['text']) && is_string($item['text']) && $item['text'] !== '') {
                $parts[] = $item['text'];
                continue;
            }

            if (!isset($item['content']) || !is_array($item['content'])) {
                continue;
            }

            foreach ($item['content'] as $content) {
                if (!is_array($content)) {
                    continue;
                }

                $type = $content['type'] ?? null;
                if ($type !== null && !in_array($type, ['output_text', 'text'], true)) {
                    continue;
                }

                if (isset($content['text']) && is_string($content['text']) && $content['text'] !== '') {
                    $parts[] = $content['text'];
                }
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode("\n", $parts);
    }
}