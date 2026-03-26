up:
	docker compose up -d --build

down:
	docker compose down

install:
	docker compose run --rm php composer install

migrate:
	docker compose run --rm php php bin/console doctrine:migrations:migrate --no-interaction

bash:
	docker compose exec php sh
