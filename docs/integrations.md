# Integrations

**Data → Integrations** connects a project to other tools. The *Connections*
tab lists what is connected (integrations and webhooks) with its status; the
*Catalog* tab lists everything that can be connected, inbound or outbound.

Secrets (keys, passwords, webhook URLs) are stored encrypted. An integration
that fails 20 times in a row is paused; **Resume** turns it back on.

## Analytics: PostHog, Mixpanel, Amplitude (outbound)

Each event you pick (notification received or clicked, email opened or
clicked, new device, unsubscribe, message sent) is sent to the tool, retried
for about 40 minutes if it is down.

| Tool | Key | Endpoint |
|---|---|---|
| PostHog | project API key (`phc_…`) | `/i/v0/e/` of `us.i.posthog.com`, `eu.i.posthog.com` or your own instance |
| Mixpanel | project token | `/track` of `api.mixpanel.com`, `api-eu.…` or `api-in.…` |
| Amplitude | API key | `/2/httpapi` of `api2.amplitude.com` or `api.eu.amplitude.com` |

- **User**: the external id of your app (`external_id`), else the Carillon id
  of the user, so events join the ones your app already sends.
- **Event name**: `Carillon Notification clicked`; the prefix can be changed
  or removed.
- **Properties**: the data of the [webhook](api.md#webhooks) of the event
  (`message_id`, `platform`, `action_id`…), plus `carillon_event_id`, used to
  deduplicate retries (`$insert_id`, `insert_id`).

## Slack and Discord (outbound)

A message in a channel for the events you pick. By default: a message sent
(with its totals), a failed OneSignal sync, a failed integration (SQL sync or
export). Paste the URL of an *incoming webhook* (Slack) or of a channel
webhook (Discord). For n8n, Make or Zapier, use a [webhook](api.md#webhooks).

## Segment and RudderStack (inbound)

Carillon is a source with the format of Segment. After connecting it, the page
shows the endpoint and the write key:

```
POST https://<your-server>/api/v1/sources/segment/<app_id>
POST https://<your-server>/api/v1/sources/segment/<app_id>/v1/batch   (HTTP API of the Segment libraries)
```

Authentication: HTTP Basic with the write key as user name and an empty
password (like Segment), or `Authorization: Bearer <write key>`.

| Call | Effect in Carillon |
|---|---|
| `identify` | the user `userId` is created (option) or updated: scalar `traits` become tags (`address.city`…), `context.locale` the language, `context.timezone` the time zone |
| `track` | custom event `event` of the user, with its `properties` and `timestamp` (conversions, segments, journeys) |
| `page`, `screen` | custom event `page: <name>` / `screen: <name>` |
| `batch` | up to 500 calls |

Calls without `userId` (anonymous visitors) and events of unknown users are
ignored, and counted as such in the answer. Traits listed in *Traits not
copied as tags* (by default `email, phone, name, firstName, lastName`) are
not stored.

- **Segment**: destination *Webhooks (Actions)*, method POST, the URL above,
  header `Authorization: Bearer <write key>`.
- **RudderStack**: destination *Webhook*, same URL and header.

## PostgreSQL and MySQL / MariaDB (inbound)

A `SELECT` on the database of your app, every hour or every night at 03:00,
copies the properties of your users to Carillon, without code in the app:

```sql
SELECT id AS external_id, plan, city, language
FROM users
WHERE updated_at > now() - interval '2 days'
```

- The column `external_id` (its name can be changed) finds the user.
- `language`, `timezone` and `country` set those properties; every other
  column becomes a tag, and an empty value removes the tag.
- Only the users already known to Carillon are updated, unless *Create
  missing users* is on.
- The query runs in a read-only transaction, stopped after 2 minutes, and
  reads at most 200 000 rows. Use a user with read rights only all the same.
- **Test** connects and shows the columns of the first rows; **Sync now** runs
  it in the queue.

The database must be reachable from the Carillon server (same network, VPN or
an IP allow-list).

## Amazon S3 and compatible storages (outbound)

Every night at 04:15, the send history of the day before is written as
`<folder>/carillon/<app_id>/deliveries/YYYY/MM/DD.csv.gz` (gzipped CSV):

```
message_id,message_name,channel,subscription_id,external_id,platform,status,error,sent_at,received_at,clicked_at
```

Works with Amazon S3 (leave *Endpoint* empty) and S3-compatible storages:
Cloudflare R2 (`https://<account>.r2.cloudflarestorage.com`, region `auto`),
MinIO, SeaweedFS, Scaleway, OVH… (path-style URLs on). The access key only
needs `PutObject` on the bucket. **Test** writes a small `.carillon-test`
file; **Export yesterday** exports the day before at once.
