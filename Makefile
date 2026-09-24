.PHONY: up down sh

up:
	docker compose -f log-consumer/docker-compose.yml up -d
	docker compose -f log-producer/docker-compose.yml up -d

down:
	docker compose -f log-consumer/docker-compose.yml down
	docker compose -f log-producer/docker-compose.yml down
