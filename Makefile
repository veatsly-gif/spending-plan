up:
	docker compose -f docker-compose.yaml up -d --build

down:
	docker compose -f docker-compose.yaml down

install:
	docker compose -f docker-compose.yaml run --rm php composer install

migrate:
	docker compose -f docker-compose.yaml run --rm php php bin/console doctrine:migrations:migrate --no-interaction

bash:
	docker compose -f docker-compose.yaml exec php sh
