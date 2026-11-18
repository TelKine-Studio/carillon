# Moving from OneSignal

Carillon speaks the OneSignal formats, so the move is mostly a matter of
changing addresses. Your subscribers keep receiving notifications during the
whole move.

## 1. Create the project

In Carillon, create a project per OneSignal app and add **the same keys** as
in OneSignal (*Settings → Platforms*):

- **Apple**: a `.p8` key of the same Apple team, and the same bundle id.
- **Android**: the service account key of the same Firebase project.
- **Web**: generate new VAPID keys (OneSignal does not give its own).
- **Email** (if you use it): your SMTP server or service (Resend, SES,
  Postmark, Mailgun), or direct delivery.

## 2. Import your audience

In Carillon, *Audience → Import from OneSignal → Automatic sync*: paste the
App ID and the REST API key of your OneSignal app (*Settings → Keys & IDs*).
Carillon asks OneSignal for an export through its API, imports it, then does
it again at the frequency you choose (every 1, 3, 6 or 12 hours, or once a day
at a set hour, UTC): new installs of the old app version keep arriving.

The export of the OneSignal dashboard (*Users & subscriptions → Export*) does
not contain the push tokens and cannot be used. A file exported with the API
(`POST /players/csv_export`) can also be sent by hand on the same page.

- iOS and Android devices work right away: their tokens are used with your
  own keys. People keep their external id and tags.
- Until a device runs the new version of your app, Carillon sends it the
  OneSignal format: the OneSignal SDK still in the app displays the
  notification (image and buttons included) and gives its data and URL to
  your click handler. As soon as the updated app registers with the Carillon
  SDK, the device switches to the Carillon format by itself. You can
  therefore send from Carillon to everybody right after the import, without
  waiting for updates. Opens are not counted for these devices.
- Email addresses are imported too, with their state.
- Web subscriptions are tied to the keys of OneSignal and cannot be moved:
  the web SDK subscribes your visitors again on their next visit.
- Importing again updates the devices (run it again just before switching
  your server, to get the latest devices).

## 3. Update your apps

### Flutter

Replace `onesignal_flutter` with [`carillon_flutter`](../sdk/carillon_flutter):

| OneSignal | Carillon |
| --- | --- |
| `OneSignal.initialize(appId)` | `await Carillon.initialize(appId, serverUrl: 'https://carillon.example.com')` |
| `OneSignal.Notifications.requestPermission(true)` | `Carillon.notifications.requestPermission()` |
| `OneSignal.login(id)` / `OneSignal.logout()` | `Carillon.login(id)` / `Carillon.logout()` |
| `OneSignal.User.addTags({...})` | `Carillon.user.addTags({...})` |
| `OneSignal.User.removeTag(key)` | `Carillon.user.removeTag(key)` |
| `OneSignal.Notifications.addClickListener(...)` | `Carillon.notifications.addClickListener(...)` |
| `OneSignal.Notifications.addForegroundWillDisplayListener(...)` | `Carillon.notifications.addForegroundWillDisplayListener(...)` |
| `OneSignal.User.pushSubscription.optOut()` | `Carillon.user.pushSubscription.optOut()` |
| `event.notification.additionalData` | `event.notification.data` |
| `event.result.actionId` | `event.actionId` |

On iOS, replace the OneSignal notification service extension with the one
of Carillon (images and buttons). When the new version starts, each device
finds its imported profile: nothing is duplicated.

### Websites

Replace the OneSignal script and service worker with those of Carillon
([web SDK](../sdk/web)). The API is the same shape (`Carillon.init`,
`Carillon.login`, `Carillon.user.addTags`…).

## 4. Switch your server

Thanks to the OneSignal format kept for old app versions, you can switch
right after the import:

- replace `https://onesignal.com/api/v1` (or `https://api.onesignal.com`) with
  `https://carillon.example.com/api/v1`;
- replace the OneSignal REST API key with an API key of the project
  (*Settings → API keys*); the `Authorization: Key …` (or `Basic …`) header
  stays the same;
- keep `app_id`: use the App ID of the Carillon project.

`POST /notifications` accepts the same fields: `included_segments`,
`excluded_segments`, `filters`, `include_aliases.external_id`,
`include_subscription_ids`, `headings`, `contents`, `data`, `url`,
`big_picture`, `buttons`, `send_after`, `delayed_option`, `template_id`…
See [api.md](api.md). Segments with the same names as in OneSignal can be
recreated in *Segments*.

Run the import one last time, then stop OneSignal.
