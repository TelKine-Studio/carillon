import 'dart:async';
import 'dart:io';
import 'dart:ui';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_timezone/flutter_timezone.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api.dart';
import 'models.dart';
import 'native.dart';
import 'platform.dart';
import 'in_app_view.dart';
import 'store.dart';

/// Entry point of the SDK (same shape as OneSignal's).
class Carillon {
  Carillon._();

  static const sdkVersion = 'carillon-flutter/0.1.0';

  static _Engine? _engine;

  static _Engine get _ready {
    final engine = _engine;
    if (engine == null) {
      throw StateError('Call Carillon.initialize() first.');
    }
    return engine;
  }

  /// Starts the SDK: registers this device with the project of [appId]
  /// on the Carillon server at [serverUrl].
  ///
  /// [openUrls]: tapping a notification with a link opens it.
  static Future<void> initialize(
    String appId, {
    required String serverUrl,
    bool openUrls = true,
  }) async {
    if (_engine != null) return;
    WidgetsFlutterBinding.ensureInitialized();

    final engine = _Engine(
      appId,
      serverUrl,
      await CarillonStore.open(),
      openUrls: openUrls,
    );
    _engine = engine;
    await engine.start();
  }

  /// Links this device to a person of your app (their id in your system).
  static Future<void> login(String externalId) => _ready.login(externalId);

  /// Detaches this device from the person (e.g. on sign out).
  static Future<void> logout() => _ready.logout();

  static final notifications = CarillonNotifications._();
  static final user = CarillonUser._();
  static final inAppMessages = CarillonInAppMessages._();

  /// The notifications this person received, for a notification center.
  static final inbox = CarillonInbox._();

  /// Give it to MaterialApp(navigatorKey: Carillon.navigatorKey): in-app
  /// messages are shown above the current page.
  static final navigatorKey = GlobalKey<NavigatorState>();
  static final session = CarillonSession._();
}

class CarillonNotifications {
  CarillonNotifications._();

  /// Whether the user allows notifications.
  Future<bool> get permission async =>
      await Carillon._ready.platform.permission() == 'granted';

  /// Asks the user (once: the system remembers the answer).
  Future<bool> requestPermission() => Carillon._ready.requestPermission();

  void addClickListener(void Function(CarillonClickEvent event) listener) =>
      Carillon._ready.addClickListener(listener);

  void removeClickListener(void Function(CarillonClickEvent event) listener) =>
      Carillon._ready.clickListeners.remove(listener);

  /// Called before a notification is shown while the app is open; call
  /// [CarillonForegroundEvent.preventDefault] to hide it.
  void addForegroundWillDisplayListener(
    void Function(CarillonForegroundEvent event) listener,
  ) => Carillon._ready.foregroundListeners.add(listener);

  void removeForegroundWillDisplayListener(
    void Function(CarillonForegroundEvent event) listener,
  ) => Carillon._ready.foregroundListeners.remove(listener);

  /// Removes the notifications of this app from the notification center.
  Future<void> clearAll() => Carillon._ready.platform.clearAll();

  /// For Huawei phones without Google services (`service: 'huawei'`, token
  /// of the huawei_push plugin) or Amazon Fire devices (`service: 'amazon'`,
  /// ADM registration id): Carillon sends through Push Kit or ADM instead
  /// of Firebase. Call it again when the token changes.
  Future<void> setPushToken(String token, {required String service}) {
    assert(
      service == 'huawei' || service == 'amazon',
      'service is huawei or amazon',
    );
    final engine = Carillon._ready;
    engine.externalToken = token.trim();
    engine.externalType = service;
    return engine.sync();
  }
}

class CarillonUser {
  CarillonUser._();

  String? get externalId => Carillon._ready.store.externalId;

  Map<String, String> get tags => Carillon._ready.store.tags;

  Future<void> addTag(String key, Object value) => addTags({key: value});

  /// Adds an email address to this person, for the email channel.
  Future<void> addEmail(String email) =>
      Carillon._ready.email(email, add: true);

  Future<void> removeEmail(String email) =>
      Carillon._ready.email(email, add: false);

  /// Adds a phone number (international format, +2250700000000), for SMS.
  Future<void> addSms(String phoneNumber) =>
      Carillon._ready.sms(phoneNumber, add: true);

  Future<void> removeSms(String phoneNumber) =>
      Carillon._ready.sms(phoneNumber, add: false);

  Future<void> addTags(Map<String, Object> tags) => Carillon._ready.setTags(
    tags.map((key, value) => MapEntry(key, '$value')),
  );

  Future<void> removeTag(String key) => removeTags([key]);

  Future<void> removeTags(List<String> keys) =>
      Carillon._ready.setTags({for (final key in keys) key: null});

  /// Reports something the person did (purchase, top-up…): it can start a
  /// journey and is attributed to the last notification (conversions).
  Future<void> trackEvent(String name, [Map<String, Object?>? properties]) =>
      Carillon._ready.customEvent(name, properties: properties);

  final pushSubscription = CarillonPushSubscription._();
}

/// Banners and pop-ups designed in Carillon, shown inside the app.
class CarillonInAppMessages {
  CarillonInAppMessages._();

  /// While paused, no in-app message is shown (e.g. during a checkout).
  bool paused = false;

  final _clickListeners =
      <void Function(String messageId, Map<String, dynamic> button)>[];

  /// Called when a button of an in-app message is pressed.
  void addClickListener(
    void Function(String messageId, Map<String, dynamic> button) listener,
  ) => _clickListeners.add(listener);

  void removeClickListener(
    void Function(String messageId, Map<String, dynamic> button) listener,
  ) => _clickListeners.remove(listener);
}

/// Notification center of the app: the push notifications received by this
/// person (all their devices), newest first. [CarillonInboxView] shows it.
class CarillonInbox {
  CarillonInbox._();

  /// Unread messages, for a badge (updated by [refresh] and the actions).
  final unreadCount = ValueNotifier<int>(0);

  /// The last list fetched.
  final messages = ValueNotifier<List<CarillonInboxMessage>>(const []);

  Future<List<CarillonInboxMessage>> refresh() async {
    final engine = Carillon._ready;
    await (engine._syncing ?? Future.value());
    final id = engine.store.subscriptionId;
    if (id == null) return messages.value;
    final result = await engine.api.inbox(id);
    final list = [
      for (final item in (result['messages'] as List?) ?? const [])
        CarillonInboxMessage.fromJson(Map<String, dynamic>.from(item as Map)),
    ];
    messages.value = list;
    unreadCount.value =
        (result['unread'] as num?)?.toInt() ??
        list.where((m) => !m.read).length;
    return list;
  }

  Future<void> markRead(List<String> messageIds) => _update({
    'message_ids': messageIds,
  }, (m) => messageIds.contains(m.id) ? m.copyWith(read: true) : m);

  Future<void> markAllRead() =>
      _update({'all': true}, (m) => m.copyWith(read: true));

  /// Removes messages from the inbox of this person.
  Future<void> remove(List<String> messageIds) => _update({
    'message_ids': messageIds,
    'action': 'remove',
  }, (m) => messageIds.contains(m.id) ? null : m);

  Future<void> _update(
    Map<String, dynamic> body,
    CarillonInboxMessage? Function(CarillonInboxMessage) change,
  ) async {
    final engine = Carillon._ready;
    final id = engine.store.subscriptionId;
    if (id == null) return;
    // Updated right away, sent in the background.
    messages.value = [for (final m in messages.value) ?change(m)];
    unreadCount.value = messages.value.where((m) => !m.read).length;
    await engine.api.inboxUpdate(id, body);
  }
}

/// Outcomes, as OneSignal.Session: conversions attributed to notifications.
class CarillonSession {
  CarillonSession._();

  Future<void> addOutcome(String name) => Carillon._ready.customEvent(name);

  Future<void> addOutcomeWithValue(String name, num value) =>
      Carillon._ready.customEvent(name, value: value);
}

class CarillonPushSubscription {
  CarillonPushSubscription._();

  /// Id of this device on the server (to target it, e.g. for tests).
  String? get id => Carillon._ready.store.subscriptionId;

  String? get token => Carillon._ready.store.token;

  /// Receives notifications: allowed by the user and not opted out.
  bool get optedIn => Carillon._ready.enabled;

  /// Stops notifications for this device without removing the permission.
  Future<void> optOut() => Carillon._ready.setOptedOut(true);

  Future<void> optIn() => Carillon._ready.setOptedOut(false);
}

class _Engine with WidgetsBindingObserver {
  _Engine(this.appId, String serverUrl, this.store, {required this.openUrls})
    : api = CarillonApi(serverUrl, appId),
      platform = NativePush() {
    store
      ..appId = appId
      ..serverUrl = serverUrl;
  }

  final String appId;
  final CarillonApi api;
  final CarillonStore store;
  final PushPlatform platform;
  final bool openUrls;

  List<InAppMessage> _inApp = const [];
  bool _showingInApp = false;

  final clickListeners = <void Function(CarillonClickEvent)>[];
  final foregroundListeners = <void Function(CarillonForegroundEvent)>[];
  final _unhandledClicks = <CarillonClickEvent>[];
  bool enabled = false;
  Future<void>? _syncing;

  /// A new session after 30 minutes in the background (as OneSignal).
  static const _sessionGap = Duration(minutes: 30);

  Future<void> start() async {
    platform.foregroundHandler = (notification) {
      final event = CarillonForegroundEvent(notification);
      for (final listener in List.of(foregroundListeners)) {
        listener(event);
      }
      return event.willDisplay;
    };
    await platform.start();

    platform.tokens.listen((_) => sync());
    platform.clicks.listen(_handleClick);
    platform.displayed.listen((notification) {
      _report(notification.id, 'received');
      unawaited(Carillon.inbox.refresh());
    });

    final launch = await platform.initialClick();
    if (launch != null) _handleClick(launch);

    WidgetsBinding.instance.addObserver(this);
    unawaited(sync(session: true));
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) unawaited(sync(session: true));
  }

  Future<bool> requestPermission() async {
    final granted = await platform.requestPermission();
    await sync();
    return granted;
  }

  Future<void> setOptedOut(bool value) async {
    store.optedOut = value;
    await sync();
  }

  void addClickListener(void Function(CarillonClickEvent) listener) {
    clickListeners.add(listener);
    // The tap that opened the app happened before the listener existed.
    for (final event in List.of(_unhandledClicks)) {
      listener(event);
    }
    _unhandledClicks.clear();
  }

  Future<void> login(String externalId) async {
    store.externalId = externalId;
    final id = store.subscriptionId;
    if (id == null) return sync();
    final result = await _guard(() => api.login(id, externalId));
    _absorb(result);
  }

  Future<void> logout() async {
    store.externalId = null;
    final id = store.subscriptionId;
    if (id == null) return;
    final result = await _guard(() => api.logout(id));
    _absorb(result);
  }

  Future<void> email(String email, {required bool add}) async {
    // Needs the device to be registered first.
    await (_syncing ?? Future.value());
    final id = store.subscriptionId;
    if (id == null) {
      throw StateError('Carillon: this device is not registered yet.');
    }
    final address = email.trim().toLowerCase();
    add ? await api.addEmail(id, address) : await api.removeEmail(id, address);
  }

  Future<void> sms(String phoneNumber, {required bool add}) async {
    await (_syncing ?? Future.value());
    final id = store.subscriptionId;
    if (id == null) {
      throw StateError('Carillon: this device is not registered yet.');
    }
    final number = phoneNumber.trim();
    add ? await api.addSms(id, number) : await api.removeSms(id, number);
  }

  Future<void> customEvent(
    String name, {
    num? value,
    Map<String, Object?>? properties,
  }) async {
    await (_syncing ?? Future.value());
    final id = store.subscriptionId;
    if (id == null) return;
    await _guard(
      () => api.customEvent(id, name, value: value, properties: properties),
    );
    unawaited(_presentInApp('event', event: name));
  }

  Future<void> setTags(Map<String, String?> tags) async {
    final current = store.tags;
    tags.forEach(
      (key, value) =>
          value == null ? current.remove(key) : current[key] = value,
    );
    store.tags = current;

    final id = store.subscriptionId;
    if (id == null) return;
    final result = await _guard(() => api.tags(id, tags));
    _absorb(result);
  }

  /// Registers or updates this device (serialized: one call at a time).
  Future<void> sync({bool session = false}) {
    return _syncing = (_syncing ?? Future.value())
        .then((_) => _sync(session))
        .catchError((Object error) {
          debugPrint('[carillon] $error');
        });
  }

  /// Token given by the app for Huawei or Amazon (see setPushToken).
  String? externalToken;
  String? externalType;

  Future<void> _sync(bool session) async {
    final token = externalToken ?? await platform.token();
    final permission = await platform.permission();
    enabled = permission == 'granted' && !store.optedOut;

    final now = DateTime.now();
    final newSession =
        session &&
        (store.lastSession == null ||
            now.difference(store.lastSession!) > _sessionGap);

    if (token == null) return;

    final body = <String, dynamic>{
      'token': token,
      if (externalType != null) 'type': externalType,
      'enabled': enabled,
      'environment': await platform.environment(),
      ...await _device(),
    }..removeWhere((key, value) => value == null);

    final id = store.subscriptionId;
    Map<String, dynamic>? result;

    if (id != null) {
      try {
        result = await api.update(id, {
          ...body,
          if (newSession) 'session': true,
        });
      } on CarillonException catch (error) {
        if (!error.isGone) rethrow;
        store.subscriptionId = null;
      }
    }

    if (store.subscriptionId == null) {
      result = await api.register({
        'type': platform.type,
        ...body,
        if (store.externalId != null) 'external_id': store.externalId,
        if (store.tags.isNotEmpty) 'tags': store.tags,
      });
      store.subscriptionId = result['id'] as String?;
      if (newSession && store.subscriptionId != null) {
        await api.update(store.subscriptionId!, {'session': true});
      }
    }

    store.token = token;
    if (newSession) store.lastSession = now;
    await platform.share({
      'server': store.serverUrl,
      'app': appId,
      'subscription': store.subscriptionId,
    });
    _absorb(result);
    await _flushEvents();
    if (newSession) await _refreshInApp(showOnOpen: true);
  }

  /// Fetches the in-app messages of this device, then shows the ones for
  /// the opening of the app.
  Future<void> _refreshInApp({bool showOnOpen = false}) async {
    final id = store.subscriptionId;
    if (id == null) return;
    final result = await _guard(() => api.inAppMessages(id));
    final list = result?['in_app_messages'];
    if (list is! List) return;
    _inApp = [
      for (final item in list)
        InAppMessage(Map<String, dynamic>.from(item as Map)),
    ];
    if (showOnOpen) unawaited(_presentInApp('app_open'));
  }

  /// Shows the first message due for this trigger (one at a time).
  Future<void> _presentInApp(String trigger, {String? event}) async {
    final navigator = Carillon.navigatorKey.currentState;
    final id = store.subscriptionId;
    if (navigator == null ||
        id == null ||
        _showingInApp ||
        Carillon.inAppMessages.paused) {
      return;
    }

    final shown = store.inAppShown;
    final session = store.lastSession?.millisecondsSinceEpoch ?? 0;
    final now = DateTime.now().millisecondsSinceEpoch;
    InAppMessage? message;
    for (final candidate in _inApp) {
      final type = candidate.trigger['type'];
      if (type != trigger ||
          (trigger == 'event' && candidate.trigger['event'] != event)) {
        continue;
      }
      final last = shown[candidate.id];
      final due = switch (candidate.frequency) {
        'every_session' => last == null || last['session'] != session,
        'every_days' =>
          last == null ||
              now - (last['at'] ?? 0) >= candidate.frequencyDays * 86400000,
        _ => last == null,
      };
      if (due) {
        message = candidate;
        break;
      }
    }
    if (message == null) return;

    _showingInApp = true;
    store.inAppShown = {
      ...shown,
      message.id: {'at': now, 'session': session},
    };
    unawaited(_guard(() => api.inAppEvent(message!.id, id, 'shown')));
    try {
      final (outcome, button) = await showInAppMessage(navigator, message);
      unawaited(
        _guard(
          () => api.inAppEvent(
            message!.id,
            id,
            outcome == InAppOutcome.clicked ? 'clicked' : 'dismissed',
            buttonId: button?['id'] as String?,
          ),
        ),
      );
      if (outcome == InAppOutcome.clicked && button != null) {
        for (final listener in List.of(
          Carillon.inAppMessages._clickListeners,
        )) {
          listener(message.id, button);
        }
        final url = button['url'] as String?;
        final uri = url == null ? null : Uri.tryParse(url);
        if (button['action'] == 'url' && uri != null) {
          unawaited(
            launchUrl(
              uri,
              mode: uri.scheme.startsWith('http')
                  ? LaunchMode.externalApplication
                  : LaunchMode.platformDefault,
            ),
          );
        }
      }
    } finally {
      _showingInApp = false;
    }
  }

  Future<Map<String, dynamic>> _device() async {
    final info = DeviceInfoPlugin();
    String? model;
    String? os;
    try {
      if (Platform.isIOS) {
        final ios = await info.iosInfo;
        model = ios.utsname.machine;
        os = ios.systemVersion;
      } else {
        final android = await info.androidInfo;
        model = android.model;
        os = android.version.release;
      }
    } catch (_) {}

    String? timezone;
    try {
      timezone = (await FlutterTimezone.getLocalTimezone()).identifier;
    } catch (_) {}

    String? version;
    try {
      version = (await PackageInfo.fromPlatform()).version;
    } catch (_) {}

    return {
      'language': PlatformDispatcher.instance.locale.languageCode,
      'country': PlatformDispatcher.instance.locale.countryCode,
      'timezone': timezone,
      'app_version': version,
      'device_model': model,
      'device_os': os,
      'sdk': Carillon.sdkVersion,
    };
  }

  void _absorb(Map<String, dynamic>? result) {
    final tags = result?['tags'];
    if (tags is Map) {
      store.tags = tags.map((key, value) => MapEntry('$key', '$value'));
    }
  }

  void _handleClick(CarillonClickEvent event) {
    _report(event.notification.id, 'clicked', actionId: event.actionId);

    final url = event.actionId == null ? event.notification.url : null;
    if (openUrls && url != null) {
      final uri = Uri.tryParse(url);
      if (uri != null) {
        unawaited(launchUrl(uri, mode: LaunchMode.externalApplication));
      }
    }

    if (clickListeners.isEmpty) {
      _unhandledClicks.add(event);
    } else {
      for (final listener in List.of(clickListeners)) {
        listener(event);
      }
    }
  }

  Future<void> _report(
    String messageId,
    String event, {
    String? actionId,
  }) async {
    final id = store.subscriptionId;
    if (id == null) return;
    try {
      await api.event(id, messageId, event, actionId: actionId);
    } catch (_) {
      store.pendingEvents = [
        ...store.pendingEvents,
        {'message_id': messageId, 'event': event, 'action_id': ?actionId},
      ];
    }
  }

  Future<void> _flushEvents() async {
    final id = store.subscriptionId;
    final pending = store.pendingEvents;
    if (id == null || pending.isEmpty) return;
    store.pendingEvents = [];
    for (final event in pending) {
      try {
        await api.event(
          id,
          '${event['message_id']}',
          '${event['event']}',
          actionId: event['action_id'] as String?,
        );
      } on CarillonException {
        // Unknown message (deleted): dropped.
      } catch (_) {
        store.pendingEvents = [...store.pendingEvents, event];
      }
    }
  }

  Future<Map<String, dynamic>?> _guard(
    Future<Map<String, dynamic>> Function() call,
  ) async {
    try {
      return await call();
    } catch (error) {
      debugPrint('[carillon] $error');
      return null;
    }
  }
}
