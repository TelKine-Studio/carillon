## 0.3.0

- Live Activities (iOS): `CarillonLiveActivities.observe(YourAttributes.self)` sends Carillon the push-to-start token of the type (iOS 17.2+), the update token of each activity and its end; `Carillon.liveActivities` has the OneSignal calls for activities managed by hand. Calls made before the device is registered are sent once it is.
- Live Activity tokens are sent natively on iOS once the device is known (the app launched in the background by iOS to give a token runs a few seconds only), with a background task; an iPhone without the notification permission is registered without APNs token to keep its Live Activity tokens.
- A cold start counts its session even when the APNs token comes back after the first sync (the session, and the in-app messages "on opening", were lost); a known device keeps its stored token meanwhile.
- In-app messages are fetched at each launch of the app, not only on a new session: an event right after a relaunch finds its message.
- Standard Live Activity: Swift package product `CarillonLiveActivity` with `CarillonActivityAttributes` and a ready-made widget (`CarillonLiveActivityWidget`, lock screen and Dynamic Island), driven by the `live` object of the API, shown as a Live Update on Android too.
- An in-app message triggered (opening of the app or event) while another one is on screen is shown after it (it was dropped).
- In-app buttons with a link follow `openUrls` too: with `openUrls: false`, only the click listener gets the link (it opened the browser before).
- Live Activities on Android: shown as Live Updates (ongoing notification with title, text and a segmented progress bar, promoted on Android 16 with a status bar chip), updated in order and closed or turned into a final notification at the end. Tapping gives the activity data to the click listener.

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
