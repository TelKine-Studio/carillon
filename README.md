# Carillon

**Self-hosted, open-source push notifications — an alternative to OneSignal.**

Push notifications are free at Apple (APNs) and Google (Firebase Cloud Messaging).
Carillon lets you send them to all the apps of your team from your own server:
no subscriber limits, no per-message bill, your data stays with you.

> 🚧 Work in progress. This first version provides the foundation: projects,
> members and roles, API keys and push keys per project. Sending, the
> audience, segments, statistics and the SDKs come next.

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

Planned: OneSignal-compatible REST API (`/api/v1/notifications`), subscribers
with external ids and tags, segments, scheduling and per-timezone delivery,
statistics, templates, webhooks, import from OneSignal, Flutter and web SDKs.

## Install

```bash
docker run -d -p 8080:80 -v carillon-data:/data ghcr.io/telkine-studio/carillon:latest
```

Open the address in your browser: the setup creates the first administrator.

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
