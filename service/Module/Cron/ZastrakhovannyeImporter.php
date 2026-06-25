<?php

namespace Module\Cron;

use App\Model\ZastrakhovannyeModel;
use Throwable;

class ZastrakhovannyeImporter
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return array{imported: int, errors: list<string>}
     */
    public function import(array $rows): array
    {
        $imported = 0;
        $errors = [];

        foreach ($rows as $row) {
            $data = $this->mapRow($row);
            if ($data === null) {
                continue;
            }

            try {
                $model = new ZastrakhovannyeModel();
                $id = $model->create($this->filterNullFields($data));
                if (!$id) {
                    $errors[] = 'Не удалось сохранить запись застрахованного';
                    continue;
                }

                $imported++;
            } catch (Throwable $error) {
                $errors[] = $error->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function mapRow(array $row): ?array
    {
        $surname = trim((string)($row['surname'] ?? ''));
        $name = trim((string)($row['name'] ?? ''));
        $birthDate = $this->normalizeDate($row['birth_date'] ?? null);

        if ($surname === '' || $name === '' || $birthDate === null) {
            return null;
        }

        return [
            'operation_type' => $this->normalizeOperationType($row['operation_type'] ?? null),
            'surname' => $surname,
            'name' => $name,
            'patronymic' => trim((string)($row['patronymic'] ?? '')) ?: null,
            'birth_date' => $birthDate,
            'gender' => $this->normalizeGender($row['gender'] ?? null),
            'address' => trim((string)($row['address'] ?? '')) ?: null,
            'phone_home' => trim((string)($row['phone_home'] ?? '')) ?: null,
            'phone_work' => trim((string)($row['phone_work'] ?? '')) ?: null,
            'phone_mobile' => trim((string)($row['phone_mobile'] ?? '')) ?: null,
            'policy_number' => trim((string)($row['policy_number'] ?? '')) ?: null,
            'service_start' => $this->normalizeDate($row['service_start'] ?? null),
            'service_end' => $this->normalizeDate($row['service_end'] ?? null),
            'program' => trim((string)($row['program'] ?? '')) ?: null,
            'workplace' => trim((string)($row['workplace'] ?? '')) ?: null,
            'position' => trim((string)($row['position'] ?? '')) ?: null,
        ];
    }

    /**
     * Pet Model подставляет NULL как '' — для DATE-колонок это ломает INSERT.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterNullFields(array $data): array
    {
        return array_filter($data, static fn(mixed $value): bool => $value !== null);
    }

    private function normalizeOperationType(mixed $value): ?string
    {
        $type = trim((string)$value);
        if ($type === 'прикрепление' || $type === 'открепление') {
            return $type;
        }

        return null;
    }

    private function normalizeGender(mixed $value): string
    {
        $gender = mb_strtoupper(trim((string)$value));
        if (in_array($gender, ['М', 'M'], true)) {
            return 'М';
        }
        if (in_array($gender, ['Ж', 'F'], true)) {
            return 'Ж';
        }

        return 'М';
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (preg_match('#^(\d{2})[./](\d{2})[./](\d{4})$#', $value, $matches) === 1) {
            return sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        return null;
    }
}
