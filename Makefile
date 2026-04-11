up:
	docker compose --profile test -f docker-compose.yaml up -d --build

up-frontend:
	docker compose --profile test --profile frontend -f docker-compose.yaml up -d --build

down:
	docker compose -f docker-compose.yaml down

install:
	docker compose -f docker-compose.yaml run --rm php composer install

migrate:
	docker compose -f docker-compose.yaml run --rm php php bin/console doctrine:migrations:migrate --no-interaction

bash:
	docker compose -f docker-compose.yaml exec php sh

test:
	docker compose --profile test -f docker-compose.yaml up -d postgres_test
	docker compose -f docker-compose.yaml exec -T php php vendor/bin/phpunit -c phpunit.xml.dist

mutation:
	docker compose -f docker-compose.yaml exec -T php sh -lc 'XDEBUG_MODE=coverage php vendor/bin/infection --configuration=infection.json.dist --threads=max'
