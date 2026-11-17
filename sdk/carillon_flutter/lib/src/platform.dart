import 'dart:async';

import 'models.dart';

/// What each operating system provides to the SDK.
abstract class PushPlatform {
  /// "ios" or "android" (the device type known by the server).
  String get type;

  /// APNs environment of iOS builds ("sandbox" for development builds).
  Future<String?> environment() async => null;

  /// Push token, or null while it is not available.
  Future<String?> token();

  /// Android without google-services.json: Firebase is set up with the
  /// options served by Carillon. Returns whether Firebase is ready.
  Future<bool> ensureFirebase(
    Future<Map<String, dynamic>?> Function(String package) options,
  ) async => true;

  /// New tokens (refreshed by the system).
  Stream<String> get tokens;

  /// "granted", "denied" or "not_determined".
  Future<String> permission();

  Future<bool> requestPermission();

  /// Notifications tapped while the app runs.
  Stream<CarillonClickEvent> get clicks;

  /// The tap that launched the app, if any.
  Future<CarillonClickEvent?> initialClick();

  /// Called for a notification received while the app is open: returns
  /// whether it is shown.
  set foregroundHandler(
    FutureOr<bool> Function(CarillonNotification notification)? handler,
  );

  /// Notifications received while the app is open (reported as received).
  Stream<CarillonNotification> get displayed;

  /// Live Activity tokens and ends reported by the system (iOS).
  Stream<Map<String, dynamic>> get liveActivities => const Stream.empty();

  Future<void> start();

  Future<void> clearAll();

  /// Last known position {lat, long, accuracy, time}, only when the app
  /// already has the location permission (never asked by Carillon).
  Future<Map<String, dynamic>?> lastLocation() async => null;

  /// Makes the server, project and device known to the notification
  /// service extension (iOS), which reports receptions.
  Future<void> share(Map<String, String?> values) async {}
}
