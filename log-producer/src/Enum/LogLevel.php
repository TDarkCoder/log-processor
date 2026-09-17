<?php

declare(strict_types=1);

namespace App\Enum;

enum LogLevel: string
{
    case Emergency = 'emergency';
    case Alert = 'alert';
    case Critical = 'critical';
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';
    case Info = 'info';
    case Debug = 'debug';

    public function priority(): int
    {
        return match ($this) {
            self::Emergency => 10,
            self::Alert => 9,
            self::Critical => 8,
            self::Error => 7,
            self::Warning => 5,
            self::Notice => 3,
            self::Info => 2,
            self::Debug => 1,
        };
    }

    public static function tryFromInsensitive(string $value): ?self
    {
        return self::tryFrom(strtolower($value));
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
