/// Carillon: self-hosted push notifications (an alternative to OneSignal).
///
/// ```dart
/// await Carillon.initialize('YOUR-APP-ID', serverUrl: 'https://push.example.com');
/// await Carillon.notifications.requestPermission();
/// await Carillon.login('user-42');
/// await Carillon.user.addTags({'plan': 'gold'});
/// Carillon.notifications.addClickListener((event) => print(event.notification.data));
/// ```
library;

export 'src/carillon.dart';
export 'src/models.dart';
export 'src/inbox_view.dart';
