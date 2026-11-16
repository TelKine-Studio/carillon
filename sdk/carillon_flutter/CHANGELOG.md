## 0.2.0

- `initialize(onError:)` and `Carillon.lastError`: failures in the background are reported instead of being printed only in debug builds.
- `pushSubscription.ready()` and `pushSubscription.idListenable` to wait for the registration of the device.
- Calls made before `initialize()` finishes wait for it instead of throwing.
- Wider dependency ranges (package_info_plus 8–10, device_info_plus 9–13, flutter_timezone 3–5, http 1.x); Dart 3.8+.
- In-app messages (delay, from the Nth session, A/B test), custom events and outcomes, SMS and WhatsApp numbers, Huawei and Amazon tokens, large notification icon on Android, heartbeat for "online now".

## 0.1.0

- First version: APNs (iOS) and FCM (Android) registration, notification display with images and buttons, clicks, foreground handling, users (external id), tags, opt-out, sessions, reception and click reports, iOS notification service extension.
