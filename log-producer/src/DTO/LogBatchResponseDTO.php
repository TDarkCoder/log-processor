<?php

declare(strict_types=1);

namespace App\DTO;

final class LogBatchResponseDTO implements \JsonSerializable
{
    public function __construct(
        public readonly string $status,
        public readonly string $batchId,
        public readonly int $logsCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'batch_id' => $this->batchId,
            'logs_count' => $this->logsCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
