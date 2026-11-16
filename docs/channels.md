# Channels

Each project has its own keys, set in *Settings → Platforms*. Secrets are
encrypted in the database and never shown again.

## Push notifications

| Platform | What Carillon needs | Where to find it |
|---|---|---|
| **iOS** (APNs) | `.p8` key, Key ID, Team ID, bundle id | developer.apple.com → Certificates, IDs & Profiles → Keys. One key works for every app of the team. Choose the environment **Sandbox & Production**: a Production-only key makes Apple refuse debug builds and simulators (`BadEnvironmentKeyInToken`). |
| **Android** (FCM) | Service account JSON of the Firebase project, and the `google-services.json` of the app (optional) | Firebase console → Project settings → Service accounts → Generate new private key. With `google-services.json` uploaded, the apps need neither the file nor the Gradle plugin: the SDK gets the options from Carillon. |
| **Huawei** (Push Kit) | App ID and App secret | AppGallery Connect → Project settings → General information. For Huawei phones without Google services. |
| **Amazon** (ADM) | Client ID and client secret | Amazon developer console → your app → Device Messaging → security profile. For Fire tablets and Fire TV. |
| **Web** (VAPID) | Nothing: Carillon generates the keys | Then follow the snippet shown on the page (service worker + script). |

The Flutter SDK registers iOS and Android devices by itself. Huawei and Amazon
devices are registered by the app with
`Carillon.notifications.setPushToken(token, service: 'huawei' | 'amazon')`.

Counting devices: on iOS a device exists in Carillon only once the person
allowed notifications (Apple gives no token before). On Android the FCM token
exists from the first launch, so a device can be subscribed while
notifications are turned off on the phone; it shows as *unsubscribed* once
the SDK sees the permission is refused. Compare iOS and Android audiences with
this in mind.

Notification icon: the small icon of Android is a drawable of the app
(`ic_stat_carillon`); the large one comes from the message, else the icon of
the project. iOS always shows the icon of the app.

## Email

Choose how emails leave, per project: an SMTP server (yours, Gmail, Brevo,
OVH…), Resend, Amazon SES, Postmark, Mailgun, or **direct delivery** from the
server (Carillon signs with DKIM and shows the DNS records to add). Opens,
clicks, one-click unsubscribe, bounces and complaints are handled.

The composer has a visual editor (blocks: title, text, image, button,
divider, space) and an HTML mode. Templates can hold emails too.

## SMS

| Provider | Settings |
|---|---|
| **Twilio** | Account SID, auth token, sender (number, Messaging Service SID `MG…` or sender name). |
| **Orange** (SMS API, developer.orange.com) | Client ID, client secret, number of the contract (`+2250000`), sender name if Orange validated one. |

The remaining balance (Orange units per country with the end of the contract,
Twilio credit) is shown in the settings and in the composer, which warns when
an audience needs more SMS than what is left.

Numbers are added with `Carillon.user.addSms('+2250700000000')`, the users API
(subscription type `SMS`) or the OneSignal import.

## WhatsApp

Through the WhatsApp Cloud API of Meta:

1. In the Meta developer console, add WhatsApp to an app and register your
   business number.
2. Create a system user with the `whatsapp_business_messaging` permission and
   a permanent token.
3. In Carillon: *Phone number ID*, *Business account ID* (to list your
   templates) and the token.

Outside the 24-hour window after a reply of the person, WhatsApp only
delivers templates approved by Meta: the composer lists them, with their
variables (`{{ tags.first_name }}` works in each). Within the window, free
text is allowed.

Numbers are added with `Carillon.user.addWhatsApp(…)` once the person agreed,
or the users API (subscription type `WhatsApp`). A number can be both an SMS
and a WhatsApp subscription.

## In-app messages

Banners, pop-ups and full screens shown inside the open app by the Carillon
SDKs (Flutter, web): nothing to set up besides `navigatorKey:
Carillon.navigatorKey` in the `MaterialApp` of a Flutter app.

## Live Activities (iOS and Android)

A Live Activity shows something in progress on the lock screen and in the
Dynamic Island (a delivery, a match, a ride), updated by your server without
opening the app. Carillon starts them by push (iOS 17.2+), updates and ends
them through the [API](api.md#live-activities-ios-and-android), with the same APNs key.

In the app (the look of the activity belongs to it):

1. Add a **Widget Extension** with a Live Activity, and
   `NSSupportsLiveActivities = YES` in the `Info.plist` of the app.
2. Keep the activity_id that Carillon puts under `carillon` in the
   attributes, in a file shared by the app and the widget:

   ```swift
   import ActivityKit

   struct DeliveryAttributes: ActivityAttributes {
     struct ContentState: Codable, Hashable { var step: String }
     var reference: String
     var carillon: [String: String]   // {"activityId": …}, filled by Carillon
   }
   ```

   and, in the app target only (the widget does not link the plugin):

   ```swift
   import carillon_flutter

   extension DeliveryAttributes: CarillonLiveActivityAttributes {
     var carillonActivityId: String { carillon["activityId"] ?? "" }
   }
   ```

   An activity started by the app itself gives
   `carillon: ["activityId": "…"]` with an id your server knows.
3. At launch, in `AppDelegate`, after `GeneratedPluginRegistrant.register`:

   ```swift
   if #available(iOS 16.1, *) {
     CarillonLiveActivities.observe(DeliveryAttributes.self)
   }
   ```

   The SDK then sends Carillon the push-to-start token of the type, the
   update token of each activity on screen and its end. A device must be
   registered (notifications allowed) for its tokens to be kept.

Good to know, measured on iOS 27:

- The first activity started by push shows **"Allow Live Activities from
  <app>?"** on the lock screen. Until the person taps *Allow*, iOS gives no
  update token: the activity shows, but Carillon cannot update or end it
  (the updates wait, `pending`). Once allowed, iOS hands the token to the
  app in the background and the SDK sends it at once; later activities get
  their token right away.
- Live Activities need no notification permission: an iPhone that refused
  notifications is registered anyway (without APNs token) to keep its
  Live Activity tokens, and stays out of the audience of notifications.
- Reinstalling the app ends its activities.

From your server: start when it is worth it (for instance only when an
order takes longer than usual), update rarely, end at the outcome. Carillon
ends by itself an activity left open past `end_after`, and the end sent
before the app gave its token is delivered when it does.

On Android, the same calls show a **Live Update**: an ongoing notification
with the title, the text and a progress bar of the `android` object of the
requests, promoted on Android 16 (chip in the status bar, top of the
notification shade and of the lock screen), a regular ongoing notification
before. Nothing to write in the app: the Carillon Flutter SDK 0.3+ shows it,
and tapping it gives `data` to the click listener. Older SDKs are left out.

