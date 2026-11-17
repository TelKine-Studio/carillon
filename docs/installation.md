# Installation

Carillon is one Docker image (nginx, PHP-FPM, the sending queue and the
scheduler) with SQLite or PostgreSQL. Everything else is configured from the
browser: no configuration file to edit.

## One command

On a server with Docker:

```bash
curl -fsSL https://raw.githubusercontent.com/TelKine-Studio/carillon/main/install.sh | sh
```

The script writes `./carillon/docker-compose.yml` (Carillon + PostgreSQL with
a random password kept in `./carillon/.env`), starts everything and prints the
address to open. Options: `CARILLON_DIR=/opt/carillon CARILLON_PORT=9000`.
Run it again to update.

Open the address: the setup page creates the first administrator.

## Docker, by hand

SQLite, for a small audience or a test:

```bash
docker run -d --name carillon -p 8080:80 -v carillon-data:/data ghcr.io/telkine-studio/carillon:latest
```

PostgreSQL, recommended in production:

```bash
git clone https://github.com/TelKine-Studio/carillon.git && cd carillon
POSTGRES_PASSWORD=change-me docker compose -f docker-compose.postgres.yml up -d
```

## Coolify

Create a *Docker Compose* resource from this repository with
`docker-compose.postgres.yml`, set `POSTGRES_PASSWORD` in the environment
variables and give a domain to the `carillon` service (port 80). Coolify
handles HTTPS.

Keep *Advanced → Predefined network* on **Isolated network only** (the
default). On the shared network of Coolify, the name `postgres` may also be
another app's database: Carillon then reaches it from time to time and
answers with errors 500 ("password authentication failed for user
carillon" in the logs).

## HTTPS

Put Carillon behind HTTPS before going live: browsers only allow web push on
secure sites, and the SDKs refuse plain HTTP in production. Coolify, Caddy or
Traefik get the certificate for you. Carillon trusts the `X-Forwarded-*`
headers of the proxy and the real address of visitors behind Cloudflare.

## Environment variables

None is required. The useful ones:

| Variable | Default | Use |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | `pgsql` for PostgreSQL (then `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). |
| `APP_URL` | detected | Public address, if the proxy hides it. Also settable in *Settings → General*. |
| `APP_KEY` | generated in `/data` | Key that encrypts the secrets (push keys, passwords). Keep `/data` safe. |
| `QUEUE_CONNECTION` | `database` | `redis` (with `REDIS_HOST`) for very large audiences. |

## Updating

```bash
docker compose pull && docker compose up -d
```

Migrations run when the container starts. Read the release notes before a
major version.

## Next

- Add the keys of your apps: [channels.md](channels.md)
- Send from your server: [api.md](api.md)
- Coming from OneSignal: [migrate-from-onesignal.md](migrate-from-onesignal.md)
- Keep it healthy: [operations.md](operations.md)
