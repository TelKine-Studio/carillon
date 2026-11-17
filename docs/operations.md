# Operations

## Health

*Settings → System* (administrators) shows the database, the sending queue
(waiting and failed jobs), the scheduler (last run), the free disk space and
the outgoing mail. For an external monitoring tool, `GET /up` answers `200`
when the application runs.

The image runs, under supervisord: nginx, PHP-FPM, a queue worker and the
scheduler (`schedule:work`). The scheduler sends scheduled messages, moves
people through journeys, syncs OneSignal at night, cleans old delivery
details and sends the weekly reports.

## Backups

What to keep:

- **The `/data` volume**: the `APP_KEY` that encrypts the secrets, the SQLite
  database (if used) and the uploaded files. Without the key, push keys and
  passwords stored in the database cannot be read.
- **The PostgreSQL database**, if used:

```bash
docker compose exec -T postgres pg_dump -U carillon carillon | gzip > carillon-$(date +%F).sql.gz
```

Restore:

```bash
gunzip -c carillon-2026-11-22.sql.gz | docker compose exec -T postgres psql -U carillon carillon
```

With SQLite, copy `/data/database.sqlite` while the container is stopped, or
use `sqlite3 /data/database.sqlite ".backup /data/backup.sqlite"`.

## Updates

```bash
docker compose pull && docker compose up -d
```

Migrations run at start. Pin a version (`ghcr.io/telkine-studio/carillon:v1.2.0`)
to update on your own schedule.

## Retention

Per-device delivery details are kept 90 days by default (*Settings → General
→ History*); the totals of the messages are kept forever.

## Large audiences

- Use PostgreSQL.
- Messages are sent in parallel batches of 500 devices; add queue workers
  (`php artisan queue:work`) in another container with the same image and
  environment to send faster.
- `QUEUE_CONNECTION=redis` with a Redis server lightens the database.
- Email and SMS providers have their own rate limits: Carillon follows the
  rate set in the email settings, Twilio and Orange queue messages on their
  side.

## Troubleshooting

- **Nothing is sent**: *Settings → System* tells whether the queue worker and
  the scheduler run; the report of the message lists the errors given by
  Apple, Google, the email or SMS provider.
- **iOS devices do not receive**: the bundle id must match the app, and
  development builds need the sandbox environment (the SDK reports it).
- **Web push asks nothing**: the site must be served over HTTPS, and the
  permission must be asked after a click of the visitor.
- **Logs**: `docker compose logs -f carillon`.

### Errors 500 from time to time, "password authentication failed"

Carillon sometimes reaches another PostgreSQL than its own: on Coolify,
switch *Advanced → Predefined network* to **Isolated network only** and
redeploy (see [Installation](installation.md#coolify)).
