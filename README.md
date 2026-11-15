# Carillon

**Self-hosted, open-source push notifications — an alternative to OneSignal.**

Push notifications are free at Apple (APNs) and Google (Firebase Cloud Messaging).
Carillon lets you send them to all the apps of your team from your own server:
no subscriber limits, no per-message bill, your data stays with you.

> 🚧 Work in progress. Available: projects and members, push keys per
> project, the OneSignal-compatible REST API, the sending engine, the
> dashboard, the Flutter SDK, delivery at local time, templates and
> webhooks, the web SDK. Coming next: the OneSignal import.

## Features

- **Projects**: one instance hosts all your apps, each with its own Apple,
  Firebase and web push keys, API keys and members (owner, admin, editor,
  viewer).
- **Private by design**: no public sign-up, the administrator invites the
  team; two-factor authentication, single sign-on (OpenID Connect) and an
  audit log.
- **Everything from the browser**: push keys, mail server and security are
  configured in the interface, secrets are encrypted.
- **One container**: a single Docker image (nginx, PHP-FPM, queue and
  scheduler), SQLite or PostgreSQL, ready for Coolify.

- **OneSignal-compatible API**: `POST /api/v1/notifications` with segments,
  filters, external ids and device ids, localized contents, data, images,
  buttons, scheduling, idempotency; users API to import your audience. See
  [docs/api.md](docs/api.md).
- **Sending engine**: Apple (HTTP/2, .p8 key), Firebase (HTTP v1) and web
  push, in parallel batches of 500 devices, dead tokens disabled
  automatically, statistics per platform (sent, failed, received, clicked).

- **Dashboard**: message composer with live preview and audience estimate,
  audience (people and devices, tags, test sends), segments with a filter
  builder, statistics per message and per platform.
- **Flutter SDK**: APNs on iOS (no Firebase there), FCM on Android, images,
  buttons, clicks, users and tags, with the API of the OneSignal SDK. See
  [sdk/carillon_flutter](sdk/carillon_flutter).

- **Delivery at local time**: the same hour for everyone, in their time zone.
- **Templates**: reusable contents, from the composer or with `template_id`.
- **Webhooks**: signed events (message sent, received, clicked, new or
  unsubscribed device) sent to your servers, with retries.

- **Web SDK**: push notifications for websites, served by your instance.
  See [sdk/web](sdk/web).

Planned: OneSignal import.

## Install

```bash
docker run -d -p 8080:80 -v carillon-data:/data ghcr.io/telkine-studio/carillon:latest
```

Open the address in your browser: the setup creates the first administrator.

With PostgreSQL (recommended for large audiences), use
[docker-compose.postgres.yml](docker-compose.postgres.yml):

```bash
POSTGRES_PASSWORD=change-me docker compose -f docker-compose.postgres.yml up -d
```

On Coolify, create a *Docker Compose* resource from this repository with
`docker-compose.postgres.yml`, set `POSTGRES_PASSWORD`, and assign a domain
to the `carillon` service (port 80).

## Development

```bash
cd server
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
composer run dev
```

## License

MIT — a project by [Telkine](https://github.com/TelKine-Studio).
