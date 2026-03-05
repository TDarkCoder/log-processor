<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\LogBatchRequestDTO;
use App\DTO\LogBatchResponseDTO;
use App\DTO\LogEntryDTO;
use App\Enum\LogLevel;
use App\Message\LogBatchMessage;
use App\Service\LogValidatorService;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsController]
final readonly class LogIngestionController
{
    public function __construct(
        private MessageBusInterface $bus,
        private LogValidatorService $validator,
    ) {}

    /**
     * @throws ExceptionInterface|DateMalformedStringException
     */
    public function ingest(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), associative: true);

        if (!is_array($body) || !isset($body['logs']) || !is_array($body['logs'])) {
            return new JsonResponse(
                ['status' => 'error', 'message' => 'Request body must be a JSON object with a "logs" array.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $dto = LogBatchRequestDTO::fromArray($body);
        $errors = $this->validator->validate($dto);

        if ($errors !== []) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $batchId = sprintf('batch_%s', str_replace('-', '', Uuid::v4()->toRfc4122()));
        $publishedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);

        $message = new LogBatchMessage(
            batchId: $batchId,
            logs: $dto->logs,
            publishedAt: $publishedAt,
        );

        $priority = $this->resolveBatchPriority($dto->logs);

        $this->bus->dispatch(new Envelope($message, $this->resolveStamps($priority)));

        $response = new LogBatchResponseDTO(
            status: 'accepted',
            batchId: $batchId,
            logsCount: count($dto->logs),
        );

        return new JsonResponse($response, Response::HTTP_ACCEPTED);
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
