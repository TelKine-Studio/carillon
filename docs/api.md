# Carillon REST API

The API follows the OneSignal format: to move a server from OneSignal,
replace `https://onesignal.com/api/v1` (or `https://api.onesignal.com`) with
`https://<your-carillon>/api/v1` and use an API key of the project
(*Project → Settings → API keys*).

```
Authorization: Key crl_…        (Basic and Bearer are accepted too)
Content-Type: application/json
```

Errors use the OneSignal shape: `{"errors": ["…"]}` with status 400 (invalid
request), 403 (missing or wrong key) or 404.

## Send a notification

`POST /api/v1/notifications`

```json
{
  "app_id": "4eb01a29-c164-4643-b1dd-4bde1c98173a",
  "included_segments": ["All"],
  "headings": {"en": "Sale", "fr": "Soldes"},
  "contents": {"en": "-20% today", "fr": "-20 % aujourd’hui"},
  "data": {"order": 42},
  "url": "https://shop.example.com/sale"
}
```

Response: `{"id": "<message id>", "external_id": null, "recipients": 1250}`.
If nobody matches: 400 `All included players are not subscribed`.

### Audience (one of)

| Field | Meaning |
| --- | --- |
| `included_segments` / `excluded_segments` | `All`, `Subscribed Users`, `Active Users` (7 days), `Engaged Users`, `Inactive Users`, or a segment of the project |
| `filters` | `[{"field": "tag", "key": "plan", "relation": "=", "value": "gold"}, {"operator": "OR"}, …]` |
| `include_aliases` | `{"external_id": ["user-42", …]}` (or legacy `include_external_user_ids`) — up to 20,000 |
| `include_subscription_ids` | device ids (or legacy `include_player_ids`) — up to 20,000 |
| `isIos`, `isAndroid`, `isAnyWeb` | restrict to these platforms |

Filter fields: `tag` (relations `=`, `!=`, `>`, `<`, `exists`, `not_exists`,
`time_elapsed_gt`, `time_elapsed_lt`), `language`, `country`, `app_version`,
`session_count`, `external_id`, `device_type`, `last_session` and
`first_session` (`hours_ago`).

### Content and options

`headings`, `subtitle`, `contents` (per language, the device language is used,
English otherwise), `data`, `url`, `app_url`, `web_url`, `big_picture` /
`ios_attachments`, `chrome_web_image`, `chrome_web_icon`, `buttons`
(`[{"id": "buy", "text": "Buy"}]`), `web_buttons`, `ios_sound`,
`android_sound`, `ios_badgeType` (`SetTo`) and `ios_badgeCount`, `thread_id`,
`ios_category`, `ios_interruption_level`, `android_channel_id`,
`android_accent_color`, `small_icon`, `large_icon`, `android_group`,
`priority` (10 high, 5 normal), `ttl` (seconds), `collapse_id`,
`content_available` (background notification), `send_after` (ISO date: the
message is scheduled), `idempotency_key` (a second call with the same key
returns the first message), `name`.

## Follow a notification

- `GET /api/v1/notifications/{id}` — status, `successful`, `failed`
  (refused, the device is disabled), `errored`, `received`, `converted`
  (clicks), `remaining`, `platform_delivery_stats` per platform.
- `GET /api/v1/notifications?limit=50&offset=0` — latest notifications.
- `DELETE /api/v1/notifications/{id}` — cancels a scheduled notification.

## Users (people and their devices)

- `POST /api/v1/apps/{app_id}/users` — creates or updates a person with
  devices: used to import an audience.

  ```json
  {
    "identity": {"external_id": "user-42"},
    "properties": {"tags": {"plan": "gold"}, "language": "fr", "timezone_id": "Africa/Abidjan"},
    "subscriptions": [{"type": "iOSPush", "token": "<APNs token>", "test_type": null}, {"type": "AndroidPush", "token": "<FCM token>"}]
  }
  ```

- `GET /api/v1/apps/{app_id}/users/by/external_id/{external_id}`
- `PATCH /api/v1/apps/{app_id}/users/by/external_id/{external_id}` —
  `{"properties": {"tags": {"plan": "silver", "old": null}}}` (`null` removes a tag)
- `DELETE /api/v1/apps/{app_id}/users/by/external_id/{external_id}`

## SDK endpoints

Called by the Carillon SDKs from the apps, with the App ID only (it is
public). The device id returned at registration identifies the device
afterwards.

- `POST /api/v1/sdk/apps/{app_id}/subscriptions` — `{"type": "ios|android|web",
  "token": "…", "environment": "sandbox|production", "language", "timezone",
  "country", "app_version", "device_model", "device_os", "sdk",
  "external_id", "tags"}` → `{"id", "subscriber_id", "external_id", "enabled", "tags"}`
- `PATCH …/subscriptions/{id}` — new token, `enabled`, `session: true`…
- `POST …/subscriptions/{id}/login` — `{"external_id": "user-42"}`
- `POST …/subscriptions/{id}/logout`
- `PUT …/subscriptions/{id}/tags` — `{"tags": {"plan": "gold", "old": null}}`
- `POST …/subscriptions/{id}/events` — `{"message_id": "…", "event": "received|clicked"}`

## What the devices receive

- **iOS**: `aps` (alert, sound, badge, thread-id, `mutable-content: 1`) and a
  `carillon` dictionary: `i` (message id), `title`, `subtitle`, `body`, `d`
  (data), `u` (url), `img` (image), `b` (buttons).
- **Android**: a data message `{"carillon": "<JSON>"}` with the same keys plus
  `ch` (channel), `color`, `si`/`li` (icons), `g` (group): the SDK displays
  the notification, its image and buttons, and reports clicks.
- **Web**: `{"id", "title", "body", "icon", "image", "url", "data", "actions", "tag"}`
  for the service worker.

## Migrating from OneSignal

APNs tokens belong to your app and FCM tokens to your Firebase project: they
keep working with Carillon. Export your subscribers from OneSignal, then
import them with `POST /apps/{app_id}/users` (tokens, external ids, tags):
your users keep receiving notifications without doing anything. Web push
subscriptions are tied to OneSignal's VAPID keys, which cannot be exported:
browsers subscribe again with the Carillon web SDK.
