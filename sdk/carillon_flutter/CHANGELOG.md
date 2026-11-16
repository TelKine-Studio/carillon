## 0.2.0

- iOS notification service extension as a Swift package (`CarillonExtension`, at the root of the repository): subclass `CarillonNotificationService` instead of copying a file.
- Android without `google-services.json` nor the Google services Gradle plugin: the Firebase options of the package are served by Carillon (upload the file in the Android settings of the project), kept on the device and applied at every start of the process.
- `initialize(onError:)` and `Carillon.lastError`: failures in the background are reported instead of being printed only in debug builds.
- `pushSubscription.ready()` and `pushSubscription.idListenable` to wait for the registration of the device.
- Calls made before `initialize()` finishes wait for it instead of throwing.
- Wider dependency ranges (package_info_plus 8–10, device_info_plus 9–13, flutter_timezone 3–5, http 1.x); Dart 3.8+.
- In-app messages (delay, from the Nth session, A/B test), custom events and outcomes, SMS and WhatsApp numbers, Huawei and Amazon tokens, large notification icon on Android, heartbeat for "online now".

## 0.1.0

- First version: APNs (iOS) and FCM (Android) registration, notification display with images and buttons, clicks, foreground handling, users (external id), tags, opt-out, sessions, reception and click reports, iOS notification service extension.
