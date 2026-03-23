# Docker PHP Once — Infrastructure & Deployment

Reference skill for the Docker, FrankenPHP, Mercure, and Once deployment stack.

## Architecture

- **Runtime**: FrankenPHP (Caddy + PHP in one binary) on Alpine
- **Database**: SQLite via Doctrine ORM, persisted on `/storage` volume
- **Real-time**: Mercure built into FrankenPHP (production), separate `dunglas/mercure` container (dev)
- **Frontend**: Symfony AssetMapper (importmap, no Webpack)
- **Hosting**: Once (Basecamp) with Kamal proxy
- **CI/CD**: GitHub Actions → GHCR → Once pulls image
- **Registry**: `ghcr.io/florentdestremau/ag-voter-sf`

## Key Files

| File | Role |
|---|---|
| `Dockerfile` | Multi-stage build: composer → builder → final (non-root user 1000) |
| `frankenphp/Caddyfile` | Caddy config with Mercure, PHP routing, file_server |
| `frankenphp/docker-entrypoint.sh` | Runs migrations then exec CMD |
| `frankenphp/conf.d/app.ini` | PHP ini overrides |
| `compose.yaml` | Dev Mercure container (`dunglas/mercure`) |
| `compose.override.yaml` | Dev port mappings (Mercure → 3001) |
| `.github/workflows/docker.yml` | Build & push to GHCR on push/PR to master |
| `.github/workflows/ci.yml` | CS Fixer + PHPUnit |

## Mercure Configuration

### Production (FrankenPHP built-in)
Mercure is compiled into FrankenPHP — no separate container needed.

**Caddyfile** (`frankenphp/Caddyfile`):
```caddyfile
mercure {
    publisher_jwt {env.MERCURE_JWT_SECRET} HS256
    subscriber_jwt {env.MERCURE_JWT_SECRET} HS256
    anonymous
    subscriptions
}
```

**Routing** — Mercure handles `/.well-known/mercure*`, everything else goes to PHP:
```caddyfile
@phpRoute {
    not path /.well-known/mercure*
    not file {path}
}
```

**Required env vars in production**:
- `MERCURE_JWT_SECRET` — minimum 256 bits (32+ characters), shared between Caddy and Symfony
- `MERCURE_URL=http://localhost/.well-known/mercure` — internal publish URL (same process)
- `MERCURE_PUBLIC_URL=https://<domain>/.well-known/mercure` — **must be absolute URL** (Stimulus `new URL()` fails on relative)

### Dev (separate container)
- `compose.yaml`: `dunglas/mercure` image on port 3001
- CORS origins: `http://127.0.0.1:8000 https://127.0.0.1:8001 http://localhost:8000 https://localhost:8001`
- JWT keys must match `MERCURE_JWT_SECRET` in `.env` (default: `!ChangeThisMercureHubJWTSecretKey!`)
- `.env.local` overrides: `MERCURE_URL=http://localhost:3001/.well-known/mercure`

### Common Pitfalls
- **JWT key too short**: HS256 requires ≥256 bits (32 chars). Error: "Key provided is shorter than 256 bits"
- **MERCURE_PUBLIC_URL relative**: The `mercure-turbo-stream` Stimulus controller does `new URL(hubValue)` — relative paths fail. Always use absolute URLs.
- **CORS in dev**: `symfony serve` uses HTTPS on 8001, Mercure is HTTP on 3001 → cross-origin. Add all dev origins to `MERCURE_EXTRA_DIRECTIVES`.
- **Publish failures in tests**: No Mercure hub in CI. `MercureDoctrineListener` has try/catch to handle this gracefully.

## Docker Workflow

- Triggers on push to `master`, tags `v*`, and PRs to `master`
- Always logs in to GHCR (needed for cache even on PRs)
- Always pushes (`push: true`) — PRs get `pr-N` tag, master gets `master` tag
- Build cache stored at `ghcr.io/.../ag-voter-sf:buildcache`
- `APP_VERSION` build arg = short SHA, shown in footer

## Deployment (Once)

Once pulls the Docker image from GHCR. To deploy:
1. Push to master (or merge PR)
2. CI builds and pushes image with `master` tag
3. Redeploy on Once (pulls latest `master` image)

For PR testing: image is tagged `pr-<number>`, deploy manually on Once with that tag.

## Build & Test Locally

```bash
# Build
docker build -t ag-voter-sf:test .

# Run
docker run --rm -p 8080:80 \
  -e MERCURE_JWT_SECRET='a-secret-that-is-at-least-32-characters-long!' \
  -e MERCURE_PUBLIC_URL='http://localhost:8080/.well-known/mercure' \
  -e APP_SECRET='devsecret' \
  -e ADMIN_PASSWORD_HASH='$2y$13$...' \
  ag-voter-sf:test

# Dev with symfony serve
docker compose up -d          # starts Mercure on port 3001
symfony serve                 # HTTPS on port 8001
```
