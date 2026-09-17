<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\LogBatchRequestDTO;
use App\DTO\LogBatchResponseDTO;
use App\DTO\LogEntryDTO;
use App\Enum\LogLevel;
use App\Message\LogBatchMessage;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final readonly class LogIngestionService
{
    public function __construct(private MessageBusInterface $bus) {}

    /**
     * @throws ExceptionInterface|DateMalformedStringException
     */
    public function ingest(LogBatchRequestDTO $dto): LogBatchResponseDTO
    {
        $batchId = sprintf('batch_%s', str_replace('-', '', Uuid::v4()->toRfc4122()));
        $publishedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);

        $message = new LogBatchMessage(
            batchId: $batchId,
            logs: $dto->logs,
            publishedAt: $publishedAt,
        );

        $priority = $this->resolveBatchPriority($dto->logs);

        $this->bus->dispatch(new Envelope($message, $this->resolveStamps($priority)));

        return new LogBatchResponseDTO(
            status: 'accepted',
            batchId: $batchId,
            logsCount: count($dto->logs),
        );
    }

    /**
     * @return AmqpStamp[]
     */
    private function resolveStamps(int $priority): array
    {
        if (!class_exists(AmqpStamp::class)) {
            return [];
        }

        return [new AmqpStamp('logs.ingest', AMQP_NOPARAM, ['priority' => $priority])];
    }

    /**
     * @param LogEntryDTO[] $logs
     */
    private function resolveBatchPriority(array $logs): int
    {
        return array_reduce(
            $logs,
            static fn (int $max, LogEntryDTO $log): int => max(
                $max,
                LogLevel::tryFromInsensitive($log->level)?->priority() ?? 1,
            ),
            1,
        );
    }
}
