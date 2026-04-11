# Frontend Migration Notes

## Current Direction

- Twig is deprecated for login/dashboard flows.
- React SPA entry points:
  - `/app/login`
  - `/app/dashboard`
- Symfony still serves backend/domain logic and exposes API.
- Frontend/backend communication for migrated flows is API + Bearer token.

## Auth Contract (Token Principle)

- `POST /api/login`
  - input: `{ username, password }`
  - output: `{ success, tokenType, token, expiresAt, user }`
- `GET /api/login/stub`
  - requires `Authorization: Bearer <token>`
  - used to validate current token
- `GET /api/dashboard`
  - requires token
- `POST /api/dashboard/spends`
  - requires token
- `POST /api/dashboard/incomes`
  - requires token

## Backward Compatibility

- `APP_FRONTEND_MODE=twig` keeps legacy Twig pages.
- `APP_FRONTEND_MODE=react` routes `/login` and `/dashboard` to SPA entry points.
- Legacy Twig endpoints are still present for non-migrated modules.

## Build/Run

1. Build frontend bundle:

```bash
docker compose -f docker-compose.yaml run --rm --no-deps node sh -lc "npm install && npm run build"
```

2. Open:
- `/app/login` (React)
- `/app/dashboard` (React)

3. Optional dev server:

```bash
docker compose --profile frontend -f docker-compose.yaml up -d node
```

## Next Steps

1. Move `/dashboard/spends` and `/dashboard/incomes` list/edit/delete flows to API + SPA routes.
2. Add refresh token / revoke strategy for long-lived sessions.
3. Introduce shadcn/ui components and replace temporary custom form components.
4. Add E2E coverage for token auth and SPA route guards.
