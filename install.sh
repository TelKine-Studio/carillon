#!/bin/sh
# Carillon — install on a server with Docker, in one command:
#
#   curl -fsSL https://raw.githubusercontent.com/TelKine-Studio/carillon/main/install.sh | sh
#
# Options (environment variables):
#   CARILLON_DIR   where the files go (default: ./carillon)
#   CARILLON_PORT  port of the dashboard (default: 8080)
#
# It writes a docker-compose.yml with PostgreSQL and a random password,
# starts everything, then prints the address to open. Run it again to update.

set -eu

DIR="${CARILLON_DIR:-./carillon}"
PORT="${CARILLON_PORT:-8080}"
IMAGE="ghcr.io/telkine-studio/carillon:latest"

say() { printf '\033[1;33m▸\033[0m %s\n' "$1"; }
fail() { printf '\033[1;31m✗\033[0m %s\n' "$1" >&2; exit 1; }

command -v docker >/dev/null 2>&1 || fail "Docker is required: https://docs.docker.com/engine/install/"
docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 is required (docker compose …)."

mkdir -p "$DIR"
cd "$DIR"

# The password is generated once and kept in .env.
if [ ! -f .env ]; then
  if command -v openssl >/dev/null 2>&1; then
    PASSWORD="$(openssl rand -hex 24)"
  else
    PASSWORD="$(LC_ALL=C tr -dc 'a-zA-Z0-9' </dev/urandom | head -c 48)"
  fi
  umask 077
  printf 'POSTGRES_PASSWORD=%s\nCARILLON_PORT=%s\n' "$PASSWORD" "$PORT" > .env
  say "Database password generated in $DIR/.env"
fi

cat > docker-compose.yml <<'YAML'
# Carillon with PostgreSQL, written by install.sh (run it again to update).
services:
  carillon:
    image: ghcr.io/telkine-studio/carillon:latest
    restart: unless-stopped
    depends_on:
      postgres:
        condition: service_healthy
    environment:
      DB_CONNECTION: pgsql
      DB_HOST: postgres
      DB_PORT: 5432
      DB_DATABASE: carillon
      DB_USERNAME: carillon
      DB_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - carillon-data:/data
    ports:
      - "${CARILLON_PORT:-8080}:80"
    healthcheck:
      test: ["CMD", "curl", "-fsS", "http://127.0.0.1/up"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 30s

  postgres:
    image: postgres:17-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: carillon
      POSTGRES_USER: carillon
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - postgres-data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U carillon -d carillon"]
      interval: 5s
      timeout: 5s
      retries: 10

volumes:
  carillon-data:
  postgres-data:
YAML

say "Downloading $IMAGE"
if docker compose pull --quiet 2>/dev/null; then
  say "Starting Carillon"
  docker compose up -d
else
  # Image not reachable (private package, no network to ghcr.io…): build it
  # from the public repository instead (a few minutes).
  say "Image not available: building it from the repository (a few minutes)…"
  cat > docker-compose.build.yml <<'YAML'
services:
  carillon:
    image: carillon:local
    build:
      context: https://github.com/TelKine-Studio/carillon.git#main:server
YAML
  # Every later "docker compose" command of this folder uses both files.
  grep -q '^COMPOSE_FILE=' .env || printf 'COMPOSE_FILE=docker-compose.yml:docker-compose.build.yml\n' >> .env
  COMPOSE_FILE=docker-compose.yml:docker-compose.build.yml docker compose up -d --build
fi

say "Waiting for the dashboard…"
i=0
until curl -fsS "http://127.0.0.1:$PORT/up" >/dev/null 2>&1; do
  i=$((i + 1))
  [ "$i" -gt 60 ] && fail "Carillon did not answer: see 'docker compose logs carillon' in $DIR."
  sleep 2
done

printf '\n\033[1;32m✓ Carillon is running.\033[0m\n'
printf '  Open http://<this-server>:%s to create the first administrator.\n' "$PORT"
printf '  Put it behind HTTPS (Caddy, Traefik, Coolify…) before going live: web push needs it.\n'
printf '  Files: %s · update: run this script again · logs: docker compose logs -f carillon\n\n' "$(pwd)"
