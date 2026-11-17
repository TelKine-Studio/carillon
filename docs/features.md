# Features

A tour of the dashboard. Everything is also available through the API
([api.md](api.md)).

## Messages

- **Composer** with a live preview for iOS, Android and the web, an estimate
  of the audience, test sends to your own devices, drafts and duplication.
- **Channels**: push, email, SMS, WhatsApp, from the same screen.
- **Audience**: segments (include and exclude), external ids, device ids,
  numbers or addresses, platforms.
- **Delivery**: now, at a date, at the same local time for everyone, or at the
  **smart time** of each person (the hour they usually use the app, within 24
  hours).
- **A/B test**: two versions on a sample, then the best one to the rest.
- **Labels** to sort and filter the messages, **templates** to reuse contents.
- **Report** of each message: recipients, delivered, received or opened,
  clicked, converted; clicks per hour, errors, export per device.
- **Frequency cap**: a project can limit the notifications a device gets per
  day.

## Journeys

Automations that run on their own:

- **Triggers**: a new device, inactivity for N days, a tag that changes, an
  event of your app or server.
- **Steps**: wait, wait for an event (with a timeout), send a push, an email
  or an SMS, conditions on a tag, a click or an event, with a branch
  ("otherwise, go to step N").
- **Sending hours**: messages due at night wait for the morning, in the time
  zone of each person.
- Re-entry, segment filter, statistics per step.

## Audience

- **People and devices** like in OneSignal: tags, external id, devices per
  platform, events, messages received; test devices.
- **Segments** on tags, language, country, app version, sessions, last
  session, platform, **events** (how many times, over a period), **total
  value** of an event (e.g. money spent), **last click** and **location**
  (within a radius).
- **Location** of devices, read by the SDK only when the app already has the
  permission (never asked), rounded to about 1 km; on by default, can be
  turned off per project.
- **Import** from OneSignal (automatic nightly sync) or a CSV file.

## In-app messages

Banner, pop-up or full screen with an image and buttons; shown when the app
opens or after an event, after a delay, from the Nth session, once, every
session or every few days; per segment. Ready-made recipes (new feature,
offer, update, review) and an **A/B test** of the text.

## Insights

- **Statistics** over 7, 30 or 90 days: delivered, clicks, click rate, new and
  reachable devices, platforms, countries, languages, app versions.
- **From the notification to the conversion**: delivered, received, clicked,
  converted.
- **Retention by cohort**: people grouped by the week they arrived and the
  share of them back in the following weeks.
- **Conversions**: events of your app or server attributed to the messages
  (direct after a click, influenced after a reception), with their value.
- **Online now**: people who have the app open, refreshed every 10 seconds
  (Carillon SDK).
- **Weekly report** by email every Monday (can be switched off in the
  profile).

## Team and security

- Projects with members (owner, admin, editor, viewer) and API keys.
- Invitations only (no public sign-up), two-factor authentication, single
  sign-on (OpenID Connect), audit log.
- *Settings → System* shows the health of the instance.
