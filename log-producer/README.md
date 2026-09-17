# Log Service

A Symfony 7 microservice that ingests batch log entries via an HTTP API and publishes them asynchronously to RabbitMQ using Symfony Messenger.

## Architecture

```
POST /api/logs/ingest
        │
        ▼
LogIngestionController
        │  validates via LogValidatorService
        │  generates batch_id (UUIDv4)
        │
        ▼
Symfony Messenger Bus
        │  dispatches LogBatchMessage
        │  attaches priority stamp (based on highest log level)
        ▼
RabbitMQ — exchange: logs (direct)
        │
        └─► queue: logs.ingest (durable, max-priority: 10)
```

## Requirements

- PHP 8.3+
- ext-amqp
- Composer 2
- Docker & Docker Compose (for local development)

## Local Development

### 1. Clone and configure environment

```bash
cp .env.example .env
# Edit .env if needed (defaults work with docker-compose)
```

### 2. Start services

```bash
docker compose up -d
```

This starts:
| Service   | URL / Port                        |
|-----------|-----------------------------------|
| Nginx     | http://localhost:8080             |
| PHP-FPM   | Internal (port 9000)              |
| RabbitMQ  | amqp://localhost:5672             |
| RabbitMQ UI | http://localhost:15672 (guest/guest) |

### 3. Install dependencies (first run)

```bash
docker compose exec php composer install
```

### 4. Consume messages

```bash
docker compose exec php php bin/console messenger:consume async -vv
```

## API

### POST /api/logs/ingest

Accepts a batch of log entries and publishes them to RabbitMQ.

**Request**

```
POST /api/logs/ingest
Content-Type: application/json
```

```json
{
  "logs": [
    {
      "timestamp": "2026-02-26T10:30:45Z",
      "level": "error",
      "service": "auth-service",
      "message": "User authentication failed",
      "context": {
        "user_id": 123,
        "ip": "192.168.1.1",
        "error_code": "INVALID_TOKEN"
      },
      "trace_id": "abc123def456"
    },
    {
      "timestamp": "2026-02-26T10:30:46Z",
      "level": "info",
      "service": "api-gateway",
      "message": "Request processed",
      "context": {
        "endpoint": "/api/users",
        "method": "GET",
        "response_time_ms": 145
      },
      "trace_id": "abc123def456"
    }
  ]
}
```

**Response 202 Accepted**

```json
{
  "status": "accepted",
  "batch_id": "batch_550e8400e29b41d4a716446655440000",
  "logs_count": 2
}
```

**Response 400 Bad Request**

```json
{
  "status": "error",
  "message": "Validation failed.",
  "errors": [
    {
      "index": 0,
      "field": "timestamp",
      "message": "This field is required."
    }
  ]
}
```

**Validation rules**

| Field       | Required | Notes                                          |
|-------------|----------|------------------------------------------------|
| `timestamp` | Yes      | ISO 8601 format (e.g. `2026-02-26T10:30:45Z`) |
| `level`     | Yes      | One of: `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug` |
| `service`   | Yes      | Non-empty string                               |
| `message`   | Yes      | Non-empty string                               |
| `context`   | No       | Arbitrary JSON object                          |
| `trace_id`  | No       | String                                         |

Maximum **1000 logs** per request.

## curl Examples

**Minimal valid request**

```bash
curl -s -X POST http://localhost:8080/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{
    "logs": [
      {
        "timestamp": "2026-02-26T10:30:45Z",
        "level": "error",
        "service": "auth-service",
        "message": "User authentication failed"
      }
    ]
  }' | jq
```

**Full request with context and trace_id**

```bash
curl -s -X POST http://localhost:8080/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{
    "logs": [
      {
        "timestamp": "2026-02-26T10:30:45Z",
        "level": "error",
        "service": "auth-service",
        "message": "User authentication failed",
        "context": {"user_id": 123, "ip": "192.168.1.1", "error_code": "INVALID_TOKEN"},
        "trace_id": "abc123def456"
      },
      {
        "timestamp": "2026-02-26T10:30:46Z",
        "level": "info",
        "service": "api-gateway",
        "message": "Request processed",
        "context": {"endpoint": "/api/users", "method": "GET", "response_time_ms": 145},
        "trace_id": "abc123def456"
      }
    ]
  }' | jq
```

**Trigger a validation error**

```bash
curl -s -X POST http://localhost:8080/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{
    "logs": [
      {
        "timestamp": "not-a-date",
        "level": "unknown-level",
        "service": "",
        "message": ""
      }
    ]
  }' | jq
```

## Running Tests

```bash
# All tests
docker compose exec php php vendor/bin/phpunit

# Unit tests only
docker compose exec php php vendor/bin/phpunit --testsuite Unit

# Integration tests only
docker compose exec php php vendor/bin/phpunit --testsuite Integration
```

## RabbitMQ Configuration

| Setting          | Value                  |
|------------------|------------------------|
| Exchange name    | `logs`                 |
| Exchange type    | `direct`               |
| Routing key      | `logs.ingest`          |
| Queue name       | `logs.ingest`          |
| Queue durability | durable                |
| Max priority     | 10                     |

**Priority mapping** (highest wins per batch):

| Level       | Priority |
|-------------|----------|
| emergency   | 10       |
| alert       | 9        |
| critical    | 8        |
| error       | 7        |
| warning     | 5        |
| notice      | 3        |
| info        | 2        |
| debug       | 1        |

## Environment Variables

| Variable                    | Default                        | Description                          |
|-----------------------------|--------------------------------|--------------------------------------|
| `APP_ENV`                   | `dev`                          | Application environment              |
| `APP_SECRET`                | —                              | Symfony secret (32+ chars)           |
| `APP_DEBUG`                 | `true`                         | Enable debug mode                    |
| `RABBITMQ_USER`             | `guest`                        | RabbitMQ username                    |
| `RABBITMQ_PASSWORD`         | `guest`                        | RabbitMQ password                    |
| `RABBITMQ_HOST`             | `rabbitmq`                     | RabbitMQ hostname                    |
| `RABBITMQ_PORT`             | `5672`                         | RabbitMQ port                        |
| `RABBITMQ_VHOST`            | `%2f`                          | RabbitMQ virtual host (URL-encoded)  |
| `MESSENGER_TRANSPORT_DSN`   | Assembled from above           | Full AMQP DSN for Symfony Messenger  |
