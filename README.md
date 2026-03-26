# Spending Plan

Personal finance system based on Symfony + PostgreSQL, running in Docker.

## Stack

- PHP 8.4 (FPM)
- Symfony 8
- Nginx
- PostgreSQL 16

## Environment files

Use only these files:

- `.env.example` - committed skeleton with required variables
- `.env` - local real values (git ignored)

Setup:

```bash
cp .env.example .env
```

## Docker compose files

Use only these files:

- `docker-compose.yaml.example` - committed skeleton
- `docker-compose.yaml` - local real config (git ignored)

If missing:

```bash
cp docker-compose.yaml.example docker-compose.yaml
```

## Run (local)

```bash
docker compose -f docker-compose.yaml up -d --build
```

Install dependencies:

```bash
docker compose -f docker-compose.yaml run --rm php composer install
```

Run migrations:

```bash
docker compose -f docker-compose.yaml run --rm php php bin/console doctrine:migrations:migrate --no-interaction
```

Open:

- Web: http://localhost:8188/
- API health: http://localhost:8188/api/health
- Login: http://localhost:8188/login

Default users after migration:

- `admin` / `admin` (ROLE_ADMIN)
- `test` / `temp` (ROLE_USER)

## Telegram bot setup

1. Create bot in `@BotFather` and get token.
2. Set in `.env`:
   - `TELEGRAM_BOT_TOKEN`
   - `TELEGRAM_WEBHOOK_SECRET`
3. Expose local app to internet.
4. Register webhook:

```bash
source .env
curl -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/setWebhook" \
 -d "url=https://<PUBLIC_HOST>/api/telegram/webhook" \
  -d "secret_token=${TELEGRAM_WEBHOOK_SECRET}"
```

## Testing

Run PHPUnit:

```bash
make test
```

The test suite uses a dedicated PostgreSQL service (`postgres_test`) with fixture-driven data loading and transaction rollback per test.

Run mutation testing (Infection):

```bash
make mutation
```

## Xdebug + PhpStorm

Project is preconfigured for Xdebug in Docker. In `.env` set:

```bash
XDEBUG_MODE=debug,develop
XDEBUG_CLIENT_HOST=host.docker.internal
XDEBUG_CLIENT_PORT=9008
XDEBUG_IDEKEY=PHPSTORM
```

Apply config:

```bash
docker compose -f docker-compose.yaml up -d --build php
```

PhpStorm setup:

1. `Settings > PHP`:
   - Add CLI Interpreter: `From Docker Compose`
   - Service: `php`
   - Path mappings: project root -> `/var/www/app`
2. `Settings > PHP > Servers`:
   - Name: `spending-plan`
   - Host: `localhost`
   - Port: `8188`
   - Debugger: `Xdebug`
   - Use path mappings: project root -> `/var/www/app`
3. `Settings > PHP > Debug`:
   - Xdebug port: `9008`
   - Enable `Can accept external connections`

Web debugging:

1. Start `Start Listening for PHP Debug Connections` in PhpStorm.
2. Open app URL in browser and breakpoints will be hit.

Console debugging:

1. Run command from PhpStorm terminal/run config.
2. Keep `Start Listening for PHP Debug Connections` enabled.
