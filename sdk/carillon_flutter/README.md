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
  await Carillon.initialize(
    'YOUR-APP-ID',
    serverUrl: 'https://carillon.example.com',
    // Background failures (registration refused, server unreachable…):
    // report them to your own monitoring, release builds print nothing.
    onError: (error) => myMonitoring.report(error.operation, error.error),
  );
  runApp(const MyApp());
}

// Calls made before initialize() finishes wait for it (no error).
// The id of the device on the server, once registered:
final id = await Carillon.user.pushSubscription.ready(); // null after 30 s
Carillon.user.pushSubscription.idListenable; // ValueListenable<String?>
Carillon.lastError; // ValueNotifier<CarillonError?>, cleared once registered

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

## Location

When your app **already has** the location permission (it asked for it for its own needs), the SDK sends the last known position of the device: at most once a day, or after a move of more than 500 m. Carillon never asks for the permission, never turns the GPS on, and rounds the position to about 1 km. Target it in segments with the condition *Location* (within a radius), or in the API with `{"field": "location", "lat": 5.32, "long": -4.02, "radius": 5000}`.

```dart
// A person refused in your app: stop, and erase the position known by Carillon.
await Carillon.location.setShared(false);
```

The project can turn it off for every device in Carillon (Settings → General → Device location). Mention it in the privacy policy of your app; on the App Store it is an *approximate location*.

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

Add a **Notification Service Extension** target (*File → New → Target*) and
set its deployment target to the one of the app. Then:

1. *File → Add Package Dependencies…* →
   `https://github.com/TelKine-Studio/carillon`, **Dependency rule:
   Commit** (or branch/tag) = the same revision as `carillon_flutter` in
   `pubspec.yaml`; add the product **CarillonExtension** to the extension
   target only.
2. Replace its `NotificationService.swift` with:

```swift
import CarillonExtension

class NotificationService: CarillonNotificationService {}
```

The extension now follows the SDK: when you move `carillon_flutter` to a new
revision, move the package to the same one. To check it in CI, compare the
revisions, not the versions (a version stays the same across commits): the
`ref` of `carillon_flutter` in `pubspec.yaml` with the `revision` of
`carillon` in `ios/Runner.xcworkspace/xcshareddata/swiftpm/Package.resolved`
— two committed files, readable without network. Without Swift
packages, copy
[`ios/CarillonExtension/Sources/CarillonExtension/CarillonNotificationService.swift`](ios/CarillonExtension/Sources/CarillonExtension/CarillonNotificationService.swift)
instead and rename its class `NotificationService`.

To also count receptions on iOS, add the same **App Group** to the app and
to the extension, and its name under the `CarillonAppGroup` key in both
`Info.plist` files.

Debug builds use the APNs sandbox, TestFlight and App Store builds
production: the SDK detects it and tells the server.

### Live Activities

Activities started, updated and ended by your server through Carillon
(iOS 16.1+, started by push from iOS 17.2): give your `ActivityAttributes`
a `carillon: [String: String]` field (Carillon puts the activity_id in it),
make them `CarillonLiveActivityAttributes` in the app target
(`var carillonActivityId: String { carillon["activityId"] ?? "" }`), then at
launch:

```swift
import carillon_flutter

if #available(iOS 16.1, *) {
  CarillonLiveActivities.observe(DeliveryAttributes.self)
}
```

No widget to design: the Swift package product **CarillonLiveActivity**
has a standard activity (`CarillonActivityAttributes`) and its ready-made
widget (`CarillonLiveActivityWidget`), driven by the `live` object of the
API on iOS and Android.

The tokens reach Carillon by themselves. For activities managed by hand,
`Carillon.liveActivities` has the calls of OneSignal: `setPushToStartToken`,
`removePushToStartToken`, `enterLiveActivity`, `exitLiveActivity`.

On Android the same activities appear as Live Updates (ongoing notification
with progress, promoted on Android 16) without any code in the app. Full
guide: [docs/channels.md](../../docs/channels.md#live-activities-ios-and-android).

## Android setup

1. In Carillon, *Project → Settings → Platforms → Android (Firebase)*:
   upload the service account key of the Firebase project (*Project
   settings → Service accounts → Generate new private key*), and the
   `google-services.json` of the Android app.
2. That's all: the SDK gets the Firebase options of its package from
   Carillon and starts Firebase by itself, the app needs neither
   `google-services.json` nor the `com.google.gms.google-services` Gradle
   plugin. An app that already has them keeps working as before (its own
   Firebase app is used).
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

