<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\LogBatchRequestDTO;
use App\DTO\LogEntryDTO;
use App\DTO\ValidationErrorDTO;

final class LogValidatorService
{
    private const int MAX_BATCH_SIZE = 1000;

    private const array VALID_LEVELS = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
    ];

    /**
     * @return ValidationErrorDTO[]
     */
    public function validate(LogBatchRequestDTO $dto): array
    {
        if (count($dto->logs) > self::MAX_BATCH_SIZE) {
            return [new ValidationErrorDTO(
                index: -1,
                field: 'logs',
                message: sprintf('Batch exceeds maximum allowed size of %d logs.', self::MAX_BATCH_SIZE),
            )];
        }

        $errors = [];

        foreach ($dto->logs as $index => $log) {
            array_push($errors, ...$this->validateEntry($log, $index));
        }

        return $errors;
    }

    /**
     * @return ValidationErrorDTO[]
     */
    private function validateEntry(LogEntryDTO $log, int $index): array
    {
        $errors = [];

        if ($log->timestamp === '') {
            $errors[] = new ValidationErrorDTO($index, 'timestamp', 'This field is required.');
        } elseif (!$this->isValidTimestamp($log->timestamp)) {
            $errors[] = new ValidationErrorDTO($index, 'timestamp', 'Must be a valid ISO 8601 date-time (e.g. 2026-02-26T10:30:45Z).');
        }

        if ($log->level === '') {
            $errors[] = new ValidationErrorDTO($index, 'level', 'This field is required.');
        } elseif (!in_array(strtolower($log->level), self::VALID_LEVELS, strict: true)) {
            $errors[] = new ValidationErrorDTO(
                $index,
                'level',
                sprintf('Invalid level. Allowed values: %s.', implode(', ', self::VALID_LEVELS)),
            );
        }

        if ($log->service === '') {
            $errors[] = new ValidationErrorDTO($index, 'service', 'This field is required.');
        }

        if ($log->message === '') {
            $errors[] = new ValidationErrorDTO($index, 'message', 'This field is required.');
        }

        return $errors;
    }

    private function isValidTimestamp(string $timestamp): bool
    {
        $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $timestamp)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $timestamp);

        return $dt !== false;
    }
}
