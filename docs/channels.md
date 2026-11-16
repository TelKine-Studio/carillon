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
