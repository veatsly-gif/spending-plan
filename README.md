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

Create incomer users from admin panel and assign `ROLE_INCOMER`.

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

## Trigger-based notifications

Notification rules are configured from YAML files by environment:

- `triggers/dev/*.yaml`
- `triggers/test/*.yaml`
- `triggers/prod/*.yaml`

Current rule file example:

```yaml
notifications:
  - code: missing_next_month_spending_plans_daily
    type: time_based
    date:
      day_of_month_gt: 25
    triggers:
      - missing_next_month_spending_plans
    delivery_types:
      - pop-up
      - telegram
    template: spending_plan_missing_next_month
    frequency:
      mode: interval
      interval_seconds: 86400
```

### Fields

- `code`: stable trigger rule id. Used in Redis keys for frequency/counters.
- `type`: trigger family. Currently supported: `time_based`.
- `date`: time condition for `time_based` rules.
- `triggers`: list of business trigger codes (AND logic).
- `delivery_types`: where to deliver (`pop-up`, `telegram`).
- `template`: notification template id.
- `frequency`: execution mode.

### Frequency modes

- `mode: once`
  - fire only one time for the same admin and trigger `code`.
- `mode: interval`
  - fire once per interval in seconds (`interval_seconds` required).
  - example: `86400` means max once per day.
- `mode: every_time`
  - no throttling, fires every time conditions match.

### How it works end-to-end

1. Admin logs in.
2. `NotificationTriggerRunner` loads YAML rules for current `APP_ENV`.
3. For each rule:
   - validates `type` and `date` gate;
   - checks `frequency` via Redis execution store;
   - runs all business triggers from `triggers` list.
4. If all conditions pass, runner emits `NotifyActionEvent` for each delivery type.
5. `NotificationService` handles delivery:
   - `pop-up`: stored in Redis queue and consumed once in Twig (`admin_popup()`).
   - `telegram`: sent to all authorized Telegram accounts linked to the admin.
6. After successful dispatch, execution store increments counter and updates last run timestamp.

### Redis keys used by trigger execution store

- `sp:trigger:count:{adminId}:{code}`: total number of successful runs for this admin/rule.
- `sp:trigger:last:{adminId}:{code}`: unix timestamp of the last successful run.

### Recreate missing-plan trigger scenario

Command clears plans for a month and removes cached suggestions:

```bash
docker compose -f docker-compose.yaml exec -T php php bin/console app:notifications:recreate-spending-plan-trigger 2026-04
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

## Income rates cron commands

Refresh live rates (`EUR->GEL`, `USDT->GEL`) into Redis:

```bash
docker compose -f docker-compose.yaml exec -T php php bin/console app:income:rates:refresh-live
```

Backfill `income.official_rated_amount_in_gel` for records older than one day:

```bash
docker compose -f docker-compose.yaml exec -T php php bin/console app:income:backfill-gel
```

Fill missing `income.amount_in_gel` from current live rates:

```bash
docker compose -f docker-compose.yaml exec -T php php bin/console app:income:fill-live-gel
```

Example crontab on host:

```bash
0 */3 * * * cd /Users/sergeysheps/Projects/spending-plan && docker compose -f docker-compose.yaml exec -T php php bin/console app:income:rates:refresh-live
30 2 * * * cd /Users/sergeysheps/Projects/spending-plan && docker compose -f docker-compose.yaml exec -T php php bin/console app:income:backfill-gel
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
