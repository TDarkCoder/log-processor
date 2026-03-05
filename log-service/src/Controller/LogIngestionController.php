<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\LogBatchRequestDTO;
use App\Service\LogIngestionService;
use App\Service\LogValidatorService;
use DateMalformedStringException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsController]
final readonly class LogIngestionController
{
    public function __construct(
        private LogIngestionService $logIngestionService,
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

        $response = $this->logIngestionService->ingest($dto);

        return new JsonResponse($response, Response::HTTP_ACCEPTED);
    }
}
