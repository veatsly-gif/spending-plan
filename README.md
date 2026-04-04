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
cp docker-compose.yaml.example docker-compose.yaml
docker compose --profile test -f docker-compose.yaml up -d --build
docker compose -f docker-compose.yaml run --rm php composer install
docker compose -f docker-compose.yaml run --rm php php bin/console doctrine:migrations:migrate --no-interaction
```

The `test` profile starts `postgres_test` (needed for `make test`). Omit `--profile test` if you only need the main database.

## Production (Docker on a VPS)

1. **DNS**: Point your public hostname’s A/AAAA records at the server’s IP. Wait for DNS to propagate before expecting TLS to work.
2. **Tag deploy**: On the server, `git fetch` and `git checkout <tag>` (for example `v0.1.2`) so production matches a known revision.
3. **Config**: Copy `.env.example` to `.env` and set strong `APP_SECRET`, PostgreSQL credentials, `DATABASE_URL` (host name `postgres` inside Compose), `REDIS_DSN` (`redis://redis:6379`), `SYMFONY_TRUSTED_PROXIES` as in `.env.example`, **`PUBLIC_HOST`** and **`DEFAULT_URI`** for HTTPS (see step 4), and optional Telegram/DeepL keys. The production compose file mounts `./.env` into the PHP container so Symfony can read it at runtime.
4. **HTTPS (TLS)**: On a typical VPS (including providers like Aeza), TLS is not issued from a hosting panel for this Docker setup—you use a **free** public certificate. This project uses **Caddy** in `docker-compose.prod.yaml`, which obtains and renews certificates from **Let’s Encrypt** (ACME) automatically. Set **`PUBLIC_HOST`** in the server-only `.env` to your public hostname (no `https://`), and set **`DEFAULT_URI`** to the matching HTTPS base URL (`https://` plus the same host). Keep real hostnames out of git; they live only in `.env` on the machine. Open ports **80** and **443** on the host firewall so HTTP-01 validation can succeed.
5. **Run**:

```bash
docker compose -f docker-compose.prod.yaml --env-file .env up -d --build
docker compose -f docker-compose.prod.yaml exec -T php php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f docker-compose.prod.yaml logs --since=10m cron
```

6. **Firewall** (if using `ufw`): allow `22`, `80`, and `443`.
7. **Cron in production**: `docker-compose.prod.yaml` includes a dedicated `cron` service that runs Symfony commands from `docker/cron/root.crontab`, so no host-level crontab setup is required.

### Automatic deploy on new tag (GitHub Actions)

When you push a new tag like `v0.1.4`, GitHub Actions can deploy it to production automatically.

1. **Create workflow**: this repository includes `.github/workflows/deploy-prod-on-tag.yaml`.
2. **Configure GitHub secrets** (Repository -> Settings -> Secrets and variables -> Actions):
   - `PROD_HOST` (example: `89.169.32.85`)
   - `PROD_SSH_USER` (example: `root`)
   - `PROD_APP_DIR` (example: `/opt/spending-plan`)
   - `PROD_SSH_PRIVATE_KEY` (full private key content, including `-----BEGIN ...-----`)
3. **Prepare server SSH access**:
   - Create a deploy key pair on your machine (`ssh-keygen`).
   - Add the public key to server `~/.ssh/authorized_keys` for `PROD_SSH_USER`.
   - Put the private key into GitHub secret `PROD_SSH_PRIVATE_KEY`.
4. **Prepare server runtime**:
   - Repository already cloned at `PROD_APP_DIR` with working `origin`.
   - Docker + Docker Compose plugin installed.
   - Production `.env` exists in `PROD_APP_DIR` with real values.
5. **Deploy**:
   - Push a tag (`git tag -a v0.1.4 -m "Release v0.1.4" && git push origin v0.1.4`).
   - Workflow checks out that tag on the server and runs:
     - `docker compose -f docker-compose.prod.yaml --env-file .env up -d --build`
     - `doctrine:migrations:migrate --no-interaction`
     - cron/rates smoke checks (`crond` process, live-rate refresh command, Redis `income:rates:live` payload)

You can also run the workflow manually with `workflow_dispatch` and pass a tag name.

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
  - code: declaration_send_daily
    type: time_based
    date:
      day_of_month_lte: 10
    triggers:
      - declaration-send
    delivery_types:
      - pop-up
      - telegram
    template: declaration_send_tax_service
    frequency:
      mode: interval
      interval_seconds: 86400

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

1. Trigger entry points:
   - admin logs in;
   - authorized Telegram user linked to admin sends `/start`.
2. `NotificationTriggerRunner` loads YAML rules for current `APP_ENV`.
3. For each rule:
   - validates `type` and `date` gate;
   - checks `frequency` via Redis execution store;
   - runs all business triggers from `triggers` list.
4. If all conditions pass, runner emits `NotifyActionEvent` for each delivery type.
5. `NotificationService` forwards event data to `NotificationCenter`.
6. `NotificationCenter` handles all notification orchestration in one place:
   - resolves template renderer by `template` code,
   - resolves delivery handler by `delivery_types` entry,
   - dispatches rendered payload to the selected channel.
7. Current channels:
   - `pop-up` / `popup` / `banner`: stored in Redis queue and consumed once in Twig (`admin_popup()`).
   - `telegram`: sent to all authorized Telegram accounts linked to the admin.
8. After successful dispatch, execution store increments counter and updates last run timestamp.

Declaration reminder (`declaration_send_tax_service`) includes action buttons in popup and Telegram:
- `Already done`: disables the reminder for the current month.
- `Remind me later`: postpones reminder until the next day.

### Notification center extension points

Add a new template:

- implement `App\Service\Notification\NotificationTemplateRendererInterface`,
- return channel payloads from `render()` via `NotificationEnvelope`,
- register class under `src/Service/Notification/Template/`.

Add a new delivery channel:

- implement `App\Service\Notification\NotificationDeliveryHandlerInterface`,
- handle delivery in `deliver()`,
- register class under `src/Service/Notification/Delivery/`,
- reference new channel code in `delivery_types` inside YAML rule.

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

Production schedule (versioned, runs inside `cron` container):

```bash
*/30 * * * * app:income:rates:refresh-live
2,32 * * * * app:income:fill-live-gel
17 * * * * app:income:backfill-gel
```

Manual production verification:

```bash
docker compose -f docker-compose.prod.yaml exec -T cron sh -lc 'ps | grep -q "[c]rond" && echo "crond is running"'
docker compose -f docker-compose.prod.yaml exec -T php php bin/console app:income:rates:refresh-live --env=prod --no-debug
docker compose -f docker-compose.prod.yaml exec -T redis sh -lc 'redis-cli --raw GET income:rates:live'
docker compose -f docker-compose.prod.yaml logs --since=30m cron
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
