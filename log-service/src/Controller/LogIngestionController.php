<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\LogBatchRequestDTO;
use App\DTO\LogBatchResponseDTO;
use App\Message\LogBatchMessage;
use App\Service\LogValidatorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class LogIngestionController
{
    private const array LEVEL_PRIORITIES = [
        'emergency' => 10,
        'alert' => 9,
        'critical' => 8,
        'error' => 7,
        'warning' => 5,
        'notice' => 3,
        'info' => 2,
        'debug' => 1,
    ];

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LogValidatorService $validator,
    ) {}

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
                'errors' => array_map(static fn ($e) => $e->toArray(), $errors),
            ], Response::HTTP_BAD_REQUEST);
        }

        $batchId = sprintf('batch_%s', str_replace('-', '', Uuid::v4()->toRfc4122()));
        $publishedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);

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

        return new JsonResponse($response->toArray(), Response::HTTP_ACCEPTED);
    }

    /**
     * @param \App\DTO\LogEntryDTO[] $logs
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
     * @param \App\DTO\LogEntryDTO[] $logs
     */
    private function resolveBatchPriority(array $logs): int
    {
        $maxPriority = 1;

        foreach ($logs as $log) {
            $priority = self::LEVEL_PRIORITIES[strtolower($log->level)] ?? 1;
            if ($priority > $maxPriority) {
                $maxPriority = $priority;
            }
        }

        return $maxPriority;
    }
}
