<?php

declare(strict_types=1);

namespace App\DTO;

final class LogBatchRequestDTO
{
    /**
     * @param LogEntryDTO[] $logs
     */
    public function __construct(
        public readonly array $logs,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawLogs = isset($data['logs']) && is_array($data['logs']) ? $data['logs'] : [];

        $logs = array_map(
            static fn (mixed $log): LogEntryDTO => LogEntryDTO::fromArray(is_array($log) ? $log : []),
            $rawLogs,
        );

        return new self(logs: $logs);
    }
}
