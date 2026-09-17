<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Message\LogBatchMessage;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class LogIngestionControllerTest extends WebTestCase
{
    private const string ENDPOINT = '/api/logs/ingest';

    private const array VALID_LOG = [
        'timestamp' => '2026-02-26T10:30:45Z',
        'level' => 'error',
        'service' => 'auth-service',
        'message' => 'User authentication failed',
        'context' => ['user_id' => 123, 'ip' => '192.168.1.1'],
        'trace_id' => 'abc123def456',
    ];

    public function testIngestValidBatchReturns202(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: self::ENDPOINT,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['logs' => [self::VALID_LOG]]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $body = json_decode($client->getResponse()->getContent(), associative: true);
        $this->assertSame('accepted', $body['status']);
        $this->assertSame(1, $body['logs_count']);
        $this->assertStringStartsWith('batch_', $body['batch_id']);
        $this->assertMatchesRegularExpression('/^batch_[0-9a-f]{32}$/', $body['batch_id']);
    }

    public function testIngestDispatchesMessageToTransport(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: self::ENDPOINT,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['logs' => [self::VALID_LOG, self::VALID_LOG]]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $envelopes = $transport->getSent();

        $this->assertCount(1, $envelopes);

        /** @var LogBatchMessage $message */
        $message = $envelopes[0]->getMessage();
        $this->assertInstanceOf(LogBatchMessage::class, $message);
        $this->assertCount(2, $message->logs);
        $this->assertStringStartsWith('batch_', $message->batchId);
        $this->assertSame(0, $message->retryCount);
        $this->assertNotEmpty($message->publishedAt);
    }

    public function testIngestMultipleLogLevelsDispatchesSingleMessage(): void
    {
        $client = static::createClient();

        $logs = [
            array_merge(self::VALID_LOG, ['level' => 'debug']),
            array_merge(self::VALID_LOG, ['level' => 'error']),
            array_merge(self::VALID_LOG, ['level' => 'info']),
        ];

        $client->request(
            method: 'POST',
            uri: self::ENDPOINT,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['logs' => $logs]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $body = json_decode($client->getResponse()->getContent(), associative: true);
        $this->assertSame(3, $body['logs_count']);
    }

    public function testIngestWithoutLogsKeyReturns400(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: self::ENDPOINT,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['data' => []]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $body = json_decode($client->getResponse()->getContent(), associative: true);
        $this->assertSame('error', $body['status']);
    }

    public function testIngestWithMalformedJsonReturns400(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: self::ENDPOINT,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{invalid json',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testIngestWithMissingRequiredFieldsReturns400(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: self::ENDPOINT,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'logs' => [
                    ['timestamp' => '2026-02-26T10:30:45Z', 'level' => 'info'],
                ],
            ]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $body = json_decode($client->getResponse()->getContent(), associative: true);
        $this->assertSame('error', $body['status']);
        $this->assertSame('Validation failed.', $body['message']);
        $this->assertIsArray($body['errors']);
        $this->assertNotEmpty($body['errors']);
    }

    public function testIngestWithInvalidTimestampReturns400(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: self::ENDPOINT,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'logs' => [
                    array_merge(self::VALID_LOG, ['timestamp' => 'not-a-timestamp']),
                ],
            ]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $body = json_decode($client->getResponse()->getContent(), associative: true);
        $this->assertSame('error', $body['status']);

        $fields = array_column($body['errors'], 'field');
        $this->assertContains('timestamp', $fields);
    }

    public function testIngestWithInvalidLevelReturns400(): void
    {
        $client = static::createClient();

        $client->request(
            method: 'POST',
            uri: self::ENDPOINT,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'logs' => [
                    array_merge(self::VALID_LOG, ['level' => 'verbose']),
                ],
            ]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testBatchIdIsUniquePerRequest(): void
    {
        $client = static::createClient();

        $payload = json_encode(['logs' => [self::VALID_LOG]]);

        $client->request('POST', self::ENDPOINT, server: ['CONTENT_TYPE' => 'application/json'], content: $payload);
        $body1 = json_decode($client->getResponse()->getContent(), associative: true);

        $client->request('POST', self::ENDPOINT, server: ['CONTENT_TYPE' => 'application/json'], content: $payload);
        $body2 = json_decode($client->getResponse()->getContent(), associative: true);

        $this->assertNotSame($body1['batch_id'], $body2['batch_id']);
    }
}
