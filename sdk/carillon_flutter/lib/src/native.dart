import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/services.dart';

import 'models.dart';
import 'platform.dart';

/// The native part of the plugin: APNs on iOS, FCM on Android (the
/// notifications are shown natively, with their image and buttons).
class NativePush extends PushPlatform {
  final _channel = const MethodChannel('carillon_flutter');
  final _tokens = StreamController<String>.broadcast();
  final _clicks = StreamController<CarillonClickEvent>.broadcast();
  final _displayed = StreamController<CarillonNotification>.broadcast();
  FutureOr<bool> Function(CarillonNotification)? _foreground;
  String? _token;

  @override
  String get type => Platform.isIOS ? 'ios' : 'android';

  @override
  Stream<String> get tokens => _tokens.stream;

  @override
  Stream<CarillonClickEvent> get clicks => _clicks.stream;

  @override
  Stream<CarillonNotification> get displayed => _displayed.stream;

  @override
  set foregroundHandler(
    FutureOr<bool> Function(CarillonNotification)? handler,
  ) => _foreground = handler;

  @override
  Future<void> start() async {
    _channel.setMethodCallHandler((call) async {
      final args = call.arguments is Map
          ? Map<String, dynamic>.from(call.arguments as Map)
          : <String, dynamic>{};
      switch (call.method) {
        case 'onToken':
          _token = args['token'] as String?;
          if (_token != null) _tokens.add(_token!);
        case 'onOpened':
          final event = _event(args);
          if (event != null) _clicks.add(event);
        case 'onForeground':
          final notification = CarillonNotification.fromPayload(_payload(args));
          if (notification == null) return true;
          // Received either way; the listener only decides the display.
          _displayed.add(notification);
          return await (_foreground?.call(notification) ?? true);
      }
      return null;
    });
    await _channel.invokeMethod('start');
  }

  @override
  Future<String?> environment() => _channel.invokeMethod<String>('environment');

  bool _firebaseReady = false;

  @override
  Future<bool> ensureFirebase(
    Future<Map<String, dynamic>?> Function(String package) options,
  ) async {
    if (!Platform.isAndroid || _firebaseReady) return true;
    // An app with google-services.json has its default Firebase app already.
    final package = await _channel.invokeMethod<String>('firebasePackage');
    if (package == null) return _firebaseReady = true;
    final firebase = await options(package);
    if (firebase == null) return false;
    await _channel.invokeMethod('configureFirebase', firebase);
    return _firebaseReady = true;
  }

  @override
  Future<String?> token() async =>
      _token ?? await _channel.invokeMethod<String>('token');

  @override
  Future<String> permission() async =>
      await _channel.invokeMethod<String>('permission') ?? 'not_determined';

  @override
  Future<bool> requestPermission() async =>
      await _channel.invokeMethod<bool>('requestPermission') ?? false;

  @override
  Future<CarillonClickEvent?> initialClick() async {
    final args = await _channel.invokeMethod<Map>('initialNotification');
    return args == null ? null : _event(Map<String, dynamic>.from(args));
  }

  @override
  Future<void> clearAll() => _channel.invokeMethod('clearAll');

  @override
  Future<void> share(Map<String, String?> values) =>
      _channel.invokeMethod('share', values);

  /// A map on iOS, a JSON string on Android.
  Map<String, dynamic>? _payload(Map<String, dynamic> args) {
    final payload = args['carillon'];
    if (payload is Map) return Map<String, dynamic>.from(payload);
    if (payload is String) {
      try {
        return Map<String, dynamic>.from(jsonDecode(payload) as Map);
      } catch (_) {}
    }
    return null;
  }

  CarillonClickEvent? _event(Map<String, dynamic> args) {
    final notification = CarillonNotification.fromPayload(_payload(args));
    return notification == null
        ? null
        : CarillonClickEvent(
            notification: notification,
            actionId: args['action'] as String?,
          );
  }
}
