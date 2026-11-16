<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/assets/brand/carillon-logo-dark.svg">
    <img src="docs/assets/brand/carillon-logo-light.svg" alt="Carillon" height="48">
  </picture>
</p>

<p align="center"><strong>Self-hosted, open-source messaging for your apps — an alternative to OneSignal.</strong></p>

<p align="center">
  <a href="https://github.com/TelKine-Studio/carillon/actions/workflows/server.yml"><img src="https://github.com/TelKine-Studio/carillon/actions/workflows/server.yml/badge.svg" alt="Tests"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue" alt="MIT license"></a>
  <a href="docs/README.md"><img src="https://img.shields.io/badge/docs-read-orange" alt="Documentation"></a>
  <a href="https://telkine-studio.github.io/carillon/"><img src="https://img.shields.io/badge/website-visit-black" alt="Website"></a>
</p>

Push notifications are free at Apple and Google. Carillon sends them — and
emails, SMS and WhatsApp messages — to the users of all your apps from your
own server: no subscriber limit, no bill per message, your data stays with
you. It speaks the OneSignal API, so moving takes an afternoon.

## Install

On a server with Docker:

```bash
curl -fsSL https://raw.githubusercontent.com/TelKine-Studio/carillon/main/install.sh | sh
```

Open the address it prints: the setup creates the first administrator. Other
ways (plain Docker, Coolify, PostgreSQL): [docs/installation.md](docs/installation.md).

## What it does

**Every channel, one audience**

- Push to **iOS, Android, Huawei, Amazon Fire and the web**, with images,
  buttons, deep links, sounds, badges, grouping and your own icon.
- **Email** through SMTP, Resend, Amazon SES, Postmark, Mailgun or direct
  delivery (DKIM), with a visual editor, tracking and one-click unsubscribe.
- **SMS** with Twilio or the Orange SMS API, with the remaining balance in
  sight; **WhatsApp** with the Cloud API of Meta and approved templates.
- **In-app messages**: banners, pop-ups and full screens, with recipes and A/B
  tests.

**Sending well**

- A composer with live preview, audience estimate and test sends; drafts,
  templates, labels.
- Delivery now, at a date, at the same local time for everyone or at the
  **smart time** of each person.
- **A/B tests**, a daily **frequency cap**, segments on tags, sessions,
  events, money spent and clicks.
- **Journeys**: automations triggered by a new device, inactivity, a tag or an
  event, with waits, conditions, branches and sending hours.

**Knowing what works**

- Reports per message and per platform, from the delivery to the
  **conversion** (events of your app attributed to the messages).
- Statistics, **retention by cohort**, **who is online now**, a weekly email
  summary.

**Yours**

- One instance for all your apps, each project with its own keys, API keys
  and members; invitations only, 2FA, single sign-on, audit log.
- Configured from the browser, secrets encrypted; one Docker image, SQLite or
  PostgreSQL.
- **OneSignal-compatible REST API** and SDKs with the OneSignal API; the
  import and nightly sync of a OneSignal app keep the old app version
  reachable during the move.

A tour of every screen: [docs/features.md](docs/features.md).

## SDKs

| | |
|---|---|
| [Flutter](sdk/carillon_flutter) | iOS (APNs, no Firebase needed), Android (FCM), Huawei, Amazon; users, tags, events, in-app messages. |
| [Web](sdk/web) | Web push for Chrome, Edge, Firefox, Safari; in-app messages. Served by your instance. |
| [REST API](docs/api.md) | Any backend: notifications, users, templates, events, webhooks. |

## Documentation

[Installation](docs/installation.md) ·
[Channels](docs/channels.md) ·
[Features](docs/features.md) ·
[API](docs/api.md) ·
[Moving from OneSignal](docs/migrate-from-onesignal.md) ·
[Operations](docs/operations.md)

## Development

```bash
cd server
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
composer run dev
```

Tests: `php artisan test` (SQLite) — the CI also runs them on PostgreSQL.
Contributions are welcome: [CONTRIBUTING.md](CONTRIBUTING.md). Security
issues: [SECURITY.md](SECURITY.md).

## License

MIT — a project by [Telkine](https://github.com/TelKine-Studio).
