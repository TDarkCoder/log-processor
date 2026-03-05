<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\LogBatchRequestDTO;
use App\DTO\LogEntryDTO;
use App\DTO\ValidationErrorDTO;
use App\Service\LogValidatorService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LogValidatorTest extends TestCase
{
    private LogValidatorService $validator;

    protected function setUp(): void
    {
        $this->validator = new LogValidatorService();
    }

    public function testValidBatchReturnsNoErrors(): void
    {
        $dto = new LogBatchRequestDTO([
            new LogEntryDTO(
                timestamp: '2026-02-26T10:30:45Z',
                level: 'error',
                service: 'auth-service',
                message: 'User authentication failed',
                context: ['user_id' => 123],
                traceId: 'abc123',
            ),
            new LogEntryDTO(
                timestamp: '2026-02-26T10:30:46Z',
                level: 'info',
                service: 'api-gateway',
                message: 'Request processed',
            ),
        ]);

        $errors = $this->validator->validate($dto);

        $this->assertSame([], $errors);
    }

    public function testEmptyBatchReturnsNoErrors(): void
    {
        $dto = new LogBatchRequestDTO([]);
        $this->assertSame([], $this->validator->validate($dto));
    }

    public function testMissingTimestampReturnsError(): void
    {
        $dto = new LogBatchRequestDTO([
            new LogEntryDTO(timestamp: '', level: 'info', service: 'svc', message: 'msg'),
        ]);

        $errors = $this->validator->validate($dto);

        $this->assertContainsField($errors, 0, 'timestamp');
    }

    public function testMissingLevelReturnsError(): void
    {
        $dto = new LogBatchRequestDTO([
            new LogEntryDTO(timestamp: '2026-02-26T10:30:45Z', level: '', service: 'svc', message: 'msg'),
        ]);

        $errors = $this->validator->validate($dto);

        $this->assertContainsField($errors, 0, 'level');
    }

    public function testMissingServiceReturnsError(): void
    {
        $dto = new LogBatchRequestDTO([
            new LogEntryDTO(timestamp: '2026-02-26T10:30:45Z', level: 'info', service: '', message: 'msg'),
        ]);

        $errors = $this->validator->validate($dto);

        $this->assertContainsField($errors, 0, 'service');
    }

    public function testMissingMessageReturnsError(): void
    {
        $dto = new LogBatchRequestDTO([
            new LogEntryDTO(timestamp: '2026-02-26T10:30:45Z', level: 'info', service: 'svc', message: ''),
        ]);

        $errors = $this->validator->validate($dto);

        $this->assertContainsField($errors, 0, 'message');
    }

    public function testAllRequiredFieldsMissingReturnsFourErrors(): void
    {
        $dto = new LogBatchRequestDTO([
            new LogEntryDTO(timestamp: '', level: '', service: '', message: ''),
        ]);

        $errors = $this->validator->validate($dto);

        $this->assertCount(4, $errors);
    }

    public function testInvalidTimestampFormatReturnsError(): void
    {
        $dto = new LogBatchRequestDTO([
            new LogEntryDTO(timestamp: 'not-a-date', level: 'info', service: 'svc', message: 'msg'),
        ]);

        $errors = $this->validator->validate($dto);

        $this->assertContainsField($errors, 0, 'timestamp');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validTimestampProvider(): array
    {
        return [
            'UTC Z suffix' => ['2026-02-26T10:30:45Z'],
            'with offset' => ['2026-02-26T10:30:45+00:00'],
            'with positive offset' => ['2026-02-26T15:30:45+05:00'],
        ];
    }

    #[DataProvider('validTimestampProvider')]
    public function testValidTimestampFormatsAreAccepted(string $timestamp): void
    {
        $dto = new LogBatchRequestDTO([
            new LogEntryDTO(timestamp: $timestamp, level: 'info', service: 'svc', message: 'msg'),
        ]);

        $errors = $this->validator->validate($dto);

        $this->assertNoFieldError($errors, 'timestamp');
    }

    public function testInvalidLogLevelReturnsError(): void
    {
        $dto = new LogBatchRequestDTO([
            new LogEntryDTO(timestamp: '2026-02-26T10:30:45Z', level: 'verbose', service: 'svc', message: 'msg'),
        ]);

        $errors = $this->validator->validate($dto);

        $this->assertContainsField($errors, 0, 'level');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validLevelProvider(): array
    {
        return [
            'emergency' => ['emergency'],
            'alert' => ['alert'],
            'critical' => ['critical'],
            'error' => ['error'],
            'warning' => ['warning'],
            'notice' => ['notice'],
            'info' => ['info'],
            'debug' => ['debug'],
            'uppercase INFO' => ['INFO'],
        ];
    }

    #[DataProvider('validLevelProvider')]
    public function testValidLogLevelsAreAccepted(string $level): void
    {
        $dto = new LogBatchRequestDTO([
            new LogEntryDTO(timestamp: '2026-02-26T10:30:45Z', level: $level, service: 'svc', message: 'msg'),
        ]);

        $errors = $this->validator->validate($dto);

        $this->assertNoFieldError($errors, 'level');
    }

    public function testBatchExceedingMaxSizeReturnsError(): void
    {
        $logs = array_fill(0, 1001, new LogEntryDTO(
            timestamp: '2026-02-26T10:30:45Z',
            level: 'info',
            service: 'svc',
            message: 'msg',
        ));

        $dto = new LogBatchRequestDTO($logs);
        $errors = $this->validator->validate($dto);

        $this->assertCount(1, $errors);
        $this->assertSame('logs', $errors[0]->field);
        $this->assertSame(-1, $errors[0]->index);
    }

    public function testBatchAtMaxSizeIsAccepted(): void
    {
        $logs = array_fill(0, 1000, new LogEntryDTO(
            timestamp: '2026-02-26T10:30:45Z',
            level: 'info',
            service: 'svc',
            message: 'msg',
        ));

        $dto = new LogBatchRequestDTO($logs);
        $this->assertSame([], $this->validator->validate($dto));
    }

    public function testErrorIndexMatchesLogPosition(): void
    {
        $dto = new LogBatchRequestDTO([
            new LogEntryDTO(timestamp: '2026-02-26T10:30:45Z', level: 'info', service: 'svc', message: 'valid'),
            new LogEntryDTO(timestamp: '', level: 'info', service: 'svc', message: 'invalid'),
        ]);

        $errors = $this->validator->validate($dto);

        $this->assertCount(1, $errors);
        $this->assertSame(1, $errors[0]->index);
    }

    // --- helpers ---

    /**
     * @param ValidationErrorDTO[] $errors
     */
    private function assertContainsField(array $errors, int $index, string $field): void
    {
        foreach ($errors as $error) {
            if ($error->index === $index && $error->field === $field) {
                $this->addToAssertionCount(1);
                return;
            }
        }

        $this->fail(sprintf('Expected a validation error at index %d for field "%s".', $index, $field));
    }

    /**
     * @param ValidationErrorDTO[] $errors
     */
    private function assertNoFieldError(array $errors, string $field): void
    {
        foreach ($errors as $error) {
            if ($error->field === $field) {
                $this->fail(sprintf('Unexpected validation error for field "%s": %s', $field, $error->message));
            }
        }

        $this->addToAssertionCount(1);
    }
}
