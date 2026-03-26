# Spending Plan

Personal finance system based on Symfony + PostgreSQL, running in Docker.

## Stack

- PHP 8.4 (FPM)
- Symfony 8
- Nginx
- PostgreSQL 16

## Run (local)

```bash
docker compose up -d --build
```

Install dependencies (first run only):

```bash
docker compose run --rm php composer install
```

Run migrations:

```bash
docker compose run --rm php php bin/console doctrine:migrations:migrate --no-interaction
```

Open:

- Web: http://localhost:8188/
- API health: http://localhost:8188/api/health

## Production-oriented compose

Use `docker-compose.prod.yml` for deployment-like setup:

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Set strong values for `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`, `APP_SECRET` before production deployment.
