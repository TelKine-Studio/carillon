# Carillon REST API

The API follows the OneSignal format: to move a server from OneSignal,
replace `https://onesignal.com/api/v1` (or `https://api.onesignal.com`) with
`https://<your-carillon>/api/v1` and use an API key of the project
(*Project → Settings → API keys*).

```
Authorization: Key crl_…        (Basic and Bearer are accepted too)
Content-Type: application/json
```

Keys have an access level, chosen when they are created: **sending only**
(notifications, reading templates, custom events and stats: the right one for
an app backend) or **full access** (also the users API and template changes).
A sending key used elsewhere gets a `403`.

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

**Delivery at local time**: `"delayed_option": "timezone"` with
`"delivery_time_of_day": "9:00AM"` (or `"21:30"`) delivers the message at
that time in the time zone of each person (sent by the SDK), within the next
24 hours: today where the time has not passed yet, tomorrow elsewhere.
Devices without a known time zone use UTC. Combined with `send_after`, the
24 hours start at that date. The notification stays cancelable until the
last time zone is reached. `"delayed_option": "last-active"` is accepted
and sent right away.

**Templates**: `"template_id": "…"` sends the content of a template
(texts, link, image, buttons, options); the fields of the request override
it. The message keeps the template for its statistics.

## Smart send time

`"delayed_option": "last-active"` (as in OneSignal) delivers the message to
each person within 24 hours, at the hour they usually use the app: learned
from their sessions, clicks, events and, for devices still on the OneSignal
SDK, the activity of the nightly sync. People without history get the
busiest hour of the project. `"delayed_option": "timezone"` with
`"delivery_time_of_day": "9:00AM"` sends at the same local time for everyone.

## Send an email

The same endpoint sends emails (OneSignal format), with the email settings
of the project (*Settings → Platforms → Email*):

```json
{
  "app_id": "…",
  "target_channel": "email",
  "included_segments": ["Subscribed Users"],
  "email_subject": "Hello {{ tags.first_name | default: \"there\" }}",
  "email_preheader": "Our new collection is here",
  "email_body": "<html><body><p>…</p></body></html>"
}
```

- Audience: the same segments, filters and `include_aliases.external_id`
  (their email addresses), or `include_email_tokens` (addresses).
- `email_subject`, `email_body` and `email_preheader` accept a text or a
  `{"en": …, "fr": …}` map (the language of each person).
- Personalization: `{{ email }}`, `{{ external_id }}`, `{{ tags.key }}`, with
  a fallback: `{{ tags.key | default: "…" }}`.
- `email_from_name`, `email_from_address`, `email_reply_to_address`,
  `disable_email_click_tracking`.
- An unsubscribe link is added at the bottom, unless the body contains
  `{{ unsubscribe_url }}`; the one-click unsubscribe headers required by
  Gmail and Yahoo are always set.
- Statistics: `received` counts opens, `converted` clicks.

Addresses are added with the SDKs (`addEmail`), the users API
(`{"type": "Email", "token": "ada@example.com"}`) or the OneSignal import.

## Send on WhatsApp

WhatsApp messages go through the WhatsApp Cloud API of Meta (*Settings →
Platforms → WhatsApp*: phone number id, business account id, token of a
system user). Outside the 24-hour window after a reply of the person, only
templates approved by Meta are delivered:

```json
{
  "app_id": "…",
  "target_channel": "whatsapp",
  "included_segments": ["Subscribed Users"],
  "whatsapp_template": "promo",
  "whatsapp_language": "fr",
  "whatsapp_variables": ["{{ tags.first_name | default: \"toi\" }}", "20"]
}
```

- Named template variables: `"whatsapp_variables": {"first_name": "…"}`.
- Within the 24-hour window, a text: `"contents": {"en": "…"}` without
  `whatsapp_template`.
- Audience: segments, filters, `include_aliases.external_id` or
  `include_phone_numbers`. A number may be both an SMS and a WhatsApp
  subscription; numbers WhatsApp refuses (not on WhatsApp, blocked, opted
  out of marketing) are no longer targeted.
- Numbers are added with the SDKs (`addWhatsApp`, once the person agreed), the
  users API (subscription type `WhatsApp`) or an import.

## Huawei and Amazon devices

Huawei phones without Google services and Amazon Fire devices receive the
same notifications, through Huawei Push Kit and Amazon Device Messaging
(*Settings → Platforms*). The Flutter SDK registers them with
`Carillon.notifications.setPushToken(token, service: 'huawei' | 'amazon')`
(token of the `huawei_push` plugin or ADM registration id); the OneSignal
import keeps them too. OneSignal flags `isHuawei` and `isAdm` select them.

## Send an SMS

SMS go through Twilio or the Orange SMS API (*Settings → Platforms → SMS*),
same endpoint:

```json
{
  "app_id": "…",
  "target_channel": "sms",
  "included_segments": ["Subscribed Users"],
  "contents": {"en": "Hi {{ tags.first_name | default: \"there\" }}, -20% today", "fr": "…"}
}
```

- Audience: the same segments, filters and `include_aliases.external_id`
  (their phone numbers), or `include_phone_numbers` (E.164: `+2250700000000`).
- `sms_from` overrides the sender, `sms_media_urls` (https) sends an MMS where
  the carrier supports it (US, Canada).
- Personalization: `{{ phone_number }}`, `{{ external_id }}`, `{{ tags.key }}`.
- 160 characters per part (70 with characters outside the GSM alphabet, such
  as emoji); the provider bills each part. A number reported as invalid
  (Twilio: invalid or unsubscribed with STOP; Orange: SVC0004) is no longer
  targeted.
- Orange: the token (`/oauth/v3/token`, client credentials) is renewed
  automatically; the sender is the number of the contract (`+2250000`), with
  the sender name Orange validated, if any.

Numbers are added with the SDKs (`addSms`), the users API (subscription type
`SMS`) or the OneSignal import.

**Delivery**: each instance chooses how its emails leave, per project:
SMTP (any server), Resend, Amazon SES, Postmark, Mailgun, or direct delivery
from the server itself (MX lookup, DKIM signature, opportunistic TLS; needs
outgoing port 25 and a good IP reputation). Bounces and complaints reported
by the services disable the addresses (webhook address shown in the
settings).

## Templates

- `POST /api/v1/templates` — `name`, `headings`, `subtitle`, `contents`
  and any content option of a notification (`url`, `big_picture`,
  `buttons`, `data`, `ttl`…). The audience and the delivery are chosen when
  sending.
- `GET /api/v1/templates?limit=50&offset=0` — templates of the project.
- `GET /api/v1/templates/{id}`
- `PATCH /api/v1/templates/{id}` — only the fields given change.
- `DELETE /api/v1/templates/{id}`

## Follow a notification

- `GET /api/v1/notifications/{id}` — status, `successful`, `failed`
  (refused, the device is disabled), `errored`, `received`, `converted`
  (clicks), `remaining`, `platform_delivery_stats` per platform.
- `legacy`: devices of this message still on the OneSignal SDK (their
  receptions and clicks are not reported).
- `GET /api/v1/notifications?limit=50&offset=0` — latest notifications.
- `GET /api/v1/apps/{app_id}/stats` — reachable devices (`devices`,
  `platforms`), how many still run the OneSignal SDK (`legacy`,
  `legacy_share` in %, `carillon_sdk`), email, SMS and WhatsApp
  subscriptions, people online now, `duplicates` (people reachable on several
  devices of one platform: a rising figure means old installs pile up), state
  of the OneSignal sync. The figure to
  watch before switching OneSignal off.
- `DELETE /api/v1/notifications/{id}` — cancels a scheduled notification
  (or the time zones not reached yet of a local time delivery).

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

## Custom events and conversions

What people do after a message (purchase, top-up…), sent by your server in
the format of OneSignal:

```
POST /api/v1/apps/{app_id}/custom_events
{"events": [{"name": "recharge", "external_id": "user-42", "properties": {"amount": 1000}, "timestamp": "2026-11-16T12:00:00Z"}]}
```

- The person is found by `external_id`, or by `onesignal_id` / `subscription_id`
  (the id of a device at OneSignal or in Carillon).
- `value`, or a property `value`, `revenue`, `amount`, `price` or `total`,
  is the value of the event (shown as revenue).
- An event within 24 hours of a notification is attributed to it: **direct**
  if the person clicked it, **influenced** if they received it. Message reports
  and the statistics show these conversions.
- Events also start the journeys triggered by them.

From the apps: `Carillon.user.trackEvent(name, properties)` and
`Carillon.session.addOutcome(name)` / `addOutcomeWithValue(name, value)`.

## Live Activities (iOS and Android)

Activities shown on the lock screen and in the Dynamic Island, in the format
of OneSignal, and the same calls show them on Android as Live Updates. The
app declares them on iOS (SwiftUI widget extension) and the SDK sends their
tokens: see [Channels → Live Activities](channels.md#live-activities-ios-and-android).

**Start** by push, on the targeted devices able to show it: iPhones with a
push-to-start token for this type (iOS 17.2+), Android apps on the Carillon
Flutter SDK 0.3+:

```
POST /api/v1/apps/{app_id}/activities/activity/{activity_type}
{
  "event": "start",
  "activity_id": "MRC-20261116-0428",
  "event_attributes": {"reference": "MRC-20261116-0428", "amount": 1000},
  "event_updates": {"step": "waiting"},
  "headings": {"fr": "Recharge en cours"},
  "contents": {"fr": "On s'en occupe."},
  "include_aliases": {"external_id": ["user-42"]},
  "stale_date": 1763310000,
  "end_after": 1800,
  "android": {"progress": 1, "progress_max": 3, "segments": 3, "color": "#0F766E"},
  "data": {"screen": "purchases", "reference": "MRC-20261116-0428"}
}
→ {"id": "MRC-20261116-0428", "recipients": 1, "already": 0, "errors": []}
```

- `activity_type` is the name of the `ActivityAttributes` struct of the app;
  `event_attributes` its fields, `event_updates` its `ContentState`.
- Audience: `include_aliases.external_id`, `include_subscription_ids`,
  `included_segments` / `excluded_segments`, `filters`. Devices that cannot
  show it (old iOS, no start token yet, old SDK) are not counted: the call
  succeeds with `recipients: 0`.
- `android`: what Android shows, since it has no widget. `title`, `body`
  (text or `{"fr": …}`; `headings` / `contents` by default), `short_text`
  (status bar chip), `progress`, `progress_max`, `segments` (steps of the bar),
  `indeterminate`, `chronometer` (Unix seconds to count from or to),
  `color`, `small_icon`. Updates only send what changes. `data` is given to
  the click listener of the app when the notification is tapped.
- `headings` / `contents` are the alert iOS shows at the start (required by
  Apple; the `name` is used without them).
- An `activity_id` starts once per device: calling start again (a retry of
  your backend) does nothing (`already`).
- `end_after` (seconds, default 8 hours, Apple's limit): Carillon ends the
  activity by then if nobody did, and it is stale from then on unless a
  `stale_date` is given (until the person allows Live Activities, iOS gives
  no update token and only the dates of the start act on the screen). `stale_date` / `dismissal_date` are Unix
  timestamps in seconds.

**Update** or **end**, on every device showing the activity:

```
POST /api/v1/apps/{app_id}/live_activities/{activity_id}/notifications
{"event": "update", "event_updates": {"step": "sending"}, "android": {"progress": 2}, "priority": 5}
{"event": "end", "event_updates": {"step": "delivered"}, "android": {"title": "Delivered", "progress": 3}, "dismissal_date": 1763309000}
→ {"id": "…", "recipients": 1, "pending": 0, "errors": []}
```

- `priority` 10 (default) is delivered at once and counts against the budget
  Apple gives each device; 5 is delivered when convenient, without limit.
  The end is always sent with 10. On Android, 5 is a normal FCM priority.
- At the end, Android keeps a regular notification with the `android` title
  and text until `dismissal_date` (or removes it without a title).
- `pending`: devices whose app has not sent the update token yet (just
  started by push). The update, or the end, is kept and sent as soon as the
  token arrives: an activity ended before is closed on the device then.
- 404 when no device shows the activity (never started, or ended).

The dashboard lists the activities (*Messages → Live Activities*). An API key
limited to sending can start, update and end activities.

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
- `PUT …/subscriptions/{id}/live-activities/start-token` — `{"activity_type": "DeliveryAttributes", "token": "…"}` (`token: null` removes it)
- `POST …/subscriptions/{id}/live-activities/enter` — `{"activity_id", "activity_type", "token"}`: the update token of an activity on screen
- `POST …/subscriptions/{id}/live-activities/exit` — `{"activity_id"}`: the activity left the screen

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

## Webhooks

*Project → Settings → Webhooks*: Carillon sends a `POST` request with a JSON
body to your URL for the events you choose.

| Event | When | `data` |
| --- | --- | --- |
| `message.sent` | A message has been sent to all its recipients | `message_id`, `name`, `template_id`, `recipients`, `successful`, `failed`, `errored`, `completed_at` |
| `notification.received` | Shown on a device (reported by the SDK) | `message_id`, `subscription_id`, `external_id`, `platform` |
| `notification.clicked` | Opened, once per device | the same, and `action_id` for a button |
| `subscription.created` | A new device registered | `subscription_id`, `subscriber_id`, `external_id`, `platform`, `language`, `device_model`, `app_version` |
| `subscription.unsubscribed` | Notifications turned off (`reason: opted_out`), token refused by Apple/Google (`token_invalid`), email unsubscribed, bounced or complained (`unsubscribed`, `bounced`, `complained`) | `subscription_id`, `subscriber_id`, `external_id`, `platform`, `reason` |
| `email.opened` / `email.clicked` | An email opened, or a link clicked, once per address | `message_id`, `subscription_id`, `external_id` |
| `onesignal_sync.failed` | The nightly import of the OneSignal app failed (sent once per new error) | `onesignal_app_id`, `error`, `last_synced_at` |

```json
{
  "id": "0193…",
  "type": "notification.clicked",
  "created_at": "2026-11-17T09:00:00+00:00",
  "data": {"message_id": "…", "subscription_id": "…", "external_id": "user-42", "platform": "ios", "action_id": "track"},
  "project": {"app_id": "…", "name": "Shop"}
}
```

Headers: `Carillon-Event`, `Carillon-Delivery` (the event `id`, to ignore
duplicates) and `Carillon-Signature: t=<timestamp>,v1=<signature>`, where the
signature is the HMAC-SHA256 of `<timestamp>.<raw body>` with the secret of
the webhook. Answer with a 2xx status within 10 seconds: failed deliveries
are retried 4 times (10 s, 1 min, 5 min, 30 min), and a webhook is disabled
after 20 events in a row could not be delivered.
