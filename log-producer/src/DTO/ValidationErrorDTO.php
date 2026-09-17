<?php

declare(strict_types=1);

namespace App\DTO;

final class ValidationErrorDTO implements \JsonSerializable
{
    public function __construct(
        public readonly int $index,
        public readonly string $field,
        public readonly string $message,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'field' => $this->field,
            'message' => $this->message,
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
