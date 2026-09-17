<?php

declare(strict_types=1);

namespace App\Message;

use App\DTO\LogEntryDTO;

final class LogBatchMessage
{
    /**
     * @param LogEntryDTO[] $logs
     */
    public function __construct(
        public readonly string $batchId,
        public readonly array $logs,
        public readonly string $publishedAt,
        public readonly int $retryCount = 0,
    ) {}
}
