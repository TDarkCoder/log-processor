<?php

declare(strict_types=1);

namespace App\DTO;

final class LogEntryDTO implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $timestamp,
        public readonly string $level,
        public readonly string $service,
        public readonly string $message,
        public readonly array $context = [],
        public readonly ?string $traceId = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            timestamp: (string) ($data['timestamp'] ?? ''),
            level: (string) ($data['level'] ?? ''),
            service: (string) ($data['service'] ?? ''),
            message: (string) ($data['message'] ?? ''),
            context: isset($data['context']) && is_array($data['context']) ? $data['context'] : [],
            traceId: isset($data['trace_id']) ? (string) $data['trace_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'timestamp' => $this->timestamp,
            'level' => $this->level,
            'service' => $this->service,
            'message' => $this->message,
            'context' => $this->context,
        ];

        if ($this->traceId !== null) {
            $data['trace_id'] = $this->traceId;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
