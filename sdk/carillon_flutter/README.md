# carillon_flutter

Push notifications for Flutter apps with [Carillon](https://github.com/TelKine-Studio/carillon),
the self-hosted alternative to OneSignal.

- **iOS**: APNs directly (no Firebase on iOS), images and buttons through a
  notification service extension.
- **Android**: Firebase Cloud Messaging data messages, shown by the SDK with
  their image, buttons, color and channel.
- Clicks (with the button pressed), foreground display control, users
  (external id), tags, opt-out, sessions, reception and click statistics.

The API mirrors the OneSignal Flutter SDK, so moving an app is mostly a
matter of renaming `OneSignal` to `Carillon`.

## Install

```yaml
dependencies:
  carillon_flutter:
    git:
      url: https://github.com/TelKine-Studio/carillon
      path: sdk/carillon_flutter
```

## Use

```dart
import 'package:carillon_flutter/carillon_flutter.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Carillon.initialize('YOUR-APP-ID', serverUrl: 'https://carillon.example.com');
  runApp(const MyApp());
}

// When it makes sense in your app (the system asks only once).
await Carillon.notifications.requestPermission();

// Link the device to the person signed in (their id in your system).
await Carillon.login(user.id);
await Carillon.logout();

await Carillon.user.addTags({'plan': 'pro', 'level': 3});
await Carillon.user.addEmail('ada@example.com'); // email channel
await Carillon.user.addSms('+2250700000000'); // SMS channel (international format)
await Carillon.user.addWhatsApp('+2250700000000'); // WhatsApp, after the person agreed
// Huawei without Google services / Amazon Fire: token of huawei_push or ADM
await Carillon.notifications.setPushToken(hmsToken, service: 'huawei');
await Carillon.user.removeTag('level');

Carillon.notifications.addClickListener((event) {
  // event.notification.data, .url, .title…; event.actionId for a button.
});

Carillon.notifications.addForegroundWillDisplayListener((event) {
  // event.preventDefault() hides it while the app is open.
});

await Carillon.user.pushSubscription.optOut(); // and optIn()
Carillon.user.pushSubscription.id; // device id, to send a test from the dashboard

// Events: start journeys and are counted as conversions of the last notification.
await Carillon.user.trackEvent('purchase', {'value': 2500, 'product': 'forfait'});
await Carillon.session.addOutcomeWithValue('recharge', 1000);
```

The App ID is shown in *Project → Settings → General* of your Carillon
instance. Tapping a notification with a launch URL opens it, unless you pass
`openUrls: false` to `initialize`.

## In-app messages

Banners and pop-ups designed in Carillon (*In-app*) are shown inside the app
when it opens, or after an event reported with `trackEvent`. Give the SDK the
navigator of your app:

```dart
MaterialApp(
  navigatorKey: Carillon.navigatorKey,
  // …
);

// Optional: pause them (e.g. during a payment), or react to a button.
Carillon.inAppMessages.paused = true;
Carillon.inAppMessages.addClickListener((messageId, button) {
  debugPrint('In-app ${button['id']} pressed');
});
```

## iOS setup

1. In Xcode, *Runner → Signing & Capabilities*: add **Push Notifications**.
2. In `AppDelegate.swift`, set the notification center delegate before the
   end of the launch (FlutterAppDelegate forwards the callbacks to the
   plugins):

   ```swift
   import UserNotifications

   override func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?) -> Bool {
     UNUserNotificationCenter.current().delegate = self
     return super.application(application, didFinishLaunchingWithOptions: launchOptions)
   }
   ```

3. Upload your APNs key (`.p8`, *Certificates, Identifiers & Profiles → Keys*)
   in *Project → Settings → Platforms → Apple Push (APNs)* with its Key ID, your Team ID and the
   bundle id of the app.

### Images and buttons (recommended)

Add a **Notification Service Extension** target (*File → New → Target*),
and replace its `NotificationService.swift` with
[`ios/extension/NotificationService.swift`](ios/extension/NotificationService.swift).
Set its deployment target to the one of the app.

To also count receptions on iOS, add the same **App Group** to the app and
to the extension, and its name under the `CarillonAppGroup` key in both
`Info.plist` files.

Debug builds use the APNs sandbox, TestFlight and App Store builds
production: the SDK detects it and tells the server.

## Android setup

1. Add Firebase to the app (`google-services.json` in `android/app` and the
   `com.google.gms.google-services` Gradle plugin), as for any FCM app.
2. Upload the service account key of the Firebase project (*Project
   settings → Service accounts → Generate new private key*) in *Project →
   Settings → Platforms → Android (Firebase)*.
3. Optional: a monochrome status bar icon named `ic_stat_carillon` in
   `android/app/src/main/res/drawable*` (otherwise the app icon is used).

The SDK declares its own `FirebaseMessagingService`. If the app also uses
`firebase_messaging` to receive other messages, only one service receives
them: keep Carillon for your notifications.

## Example

[`example`](example) shows every feature:

```bash
cd example
flutter run --dart-define=CARILLON_APP_ID=… --dart-define=CARILLON_SERVER=https://carillon.example.com
```

## Notification icon (Android)

- Small icon (status bar): add a white, transparent drawable named
  `ic_stat_carillon` to the app (`android/app/src/main/res/drawable*`), or
  name another one per message with `small_icon`; otherwise the app icon.
- Large icon (logo next to the text): the "Icon" of the message, else the
  icon of the project in Carillon; downloaded and shown round.
- iOS always shows the icon of the app (Apple's rule).

