import 'dart:math' as math;
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

  static const sdkVersion = 'carillon-flutter/0.4.0';

  static _Engine? _engine;
  static final _started = Completer<_Engine>();

  static _Engine get _ready {
    final engine = _engine;
    if (engine == null) {
      throw StateError('Call Carillon.initialize() first.');
    }
    return engine;
  }

  /// The engine once [initialize] ran: calls made before wait for it
  /// instead of failing (e.g. login right after the app starts).
  static Future<_Engine> get _whenReady =>
      _engine != null ? Future.value(_engine) : _started.future;

  static Future<T> _whenReadyCall<T>(Future<T> Function(_Engine) call) =>
      _whenReady.then(call);

  /// The last error of the SDK (registration refused, server unreachable…),
  /// or null. Errors are also given to the onError of [initialize].
  static final lastError = ValueNotifier<CarillonError?>(null);

  /// Starts the SDK: registers this device with the project of [appId]
  /// on the Carillon server at [serverUrl].
  ///
  /// [openUrls]: tapping a notification, or an in-app button, with a link
  /// opens it. With false, the click listeners get the link and the app
  /// routes it (an https link of your site to a screen of the app…).
  ///
  /// [onError]: called when something fails in the background (the device
  /// could not register, the server refused a call…), to report it to your
  /// own monitoring: without it, a release build fails silently.
  static Future<void> initialize(
    String appId, {
    required String serverUrl,
    bool openUrls = true,
    void Function(CarillonError error)? onError,
  }) async {
    if (_engine != null) return;
    WidgetsFlutterBinding.ensureInitialized();

    final engine = _Engine(
      appId,
      serverUrl,
      await CarillonStore.open(),
      openUrls: openUrls,
      onError: onError,
    );
    _engine = engine;
    if (!_started.isCompleted) _started.complete(engine);
    await engine.start();
  }

  /// Links this device to a person of your app (their id in your system).
  static Future<void> login(String externalId) =>
      _whenReady.then((engine) => engine.login(externalId));

  /// Detaches this device from the person (e.g. on sign out).
  static Future<void> logout() => _whenReady.then((engine) => engine.logout());

  static final notifications = CarillonNotifications._();
  static final user = CarillonUser._();
  static final inAppMessages = CarillonInAppMessages._();

  /// Give it to MaterialApp(navigatorKey: Carillon.navigatorKey): in-app
  /// messages are shown above the current page.
  static final navigatorKey = GlobalKey<NavigatorState>();
  static final session = CarillonSession._();

  /// Live Activities of iOS (see CarillonLiveActivities.observe on the
  /// native side, which reports the tokens by itself).
  static final liveActivities = CarillonLiveActivitiesApi._();

  /// Position of the device: read only when the app already has the
  /// location permission (Carillon never asks for it).
  static final location = CarillonLocation._();
}

/// Same calls as OneSignal.LiveActivities, for activities managed by hand.
/// The SDK sends the last known position of the device when the app
/// already has the location permission, at most once a day (or after a
/// move of more than 500 m). It never asks for the permission and never
/// turns the GPS on. Stop it with [setShared] (for a person who refused),
/// or for the whole project in Carillon (Settings → General).
class CarillonLocation {
  CarillonLocation._();

  /// Whether the app lets Carillon read the position (true by default).
  Future<bool> get isShared =>
      Carillon._whenReady.then((engine) => engine.store.locationShared);

  /// false: stops reading it and erases the position known by Carillon.
  Future<void> setShared(bool shared) => Carillon._whenReadyCall((engine) async {
    engine.store.locationShared = shared;
    engine.store.sentLocation = null;
    await engine.sync();
  });
}

class CarillonLiveActivitiesApi {
  CarillonLiveActivitiesApi._();

  /// Push-to-start token of [activityType] (iOS 17.2+), to start activities
  /// of this type by push.
  Future<void> setPushToStartToken(String activityType, String token) =>
      Carillon._whenReadyCall(
        (engine) => engine.liveActivity(
          'start:$activityType',
          (id) => engine.api.liveActivityStartToken(id, activityType, token),
        ),
      );

  Future<void> removePushToStartToken(String activityType) =>
      Carillon._whenReadyCall(
        (engine) => engine.liveActivity(
          'start:$activityType',
          (id) => engine.api.liveActivityStartToken(id, activityType, null),
        ),
      );

  /// The app shows the activity [activityId]; [token] is its update token.
  Future<void> enterLiveActivity(
    String activityId,
    String token, {
    String? activityType,
  }) => Carillon._whenReadyCall(
    (engine) => engine.liveActivity(
      'activity:$activityId',
      (id) => engine.api.liveActivityEnter(
        id,
        activityId,
        token,
        activityType: activityType,
      ),
    ),
  );

  /// The activity [activityId] left the screen.
  Future<void> exitLiveActivity(String activityId) => Carillon._whenReadyCall(
    (engine) => engine.liveActivity(
      'activity:$activityId',
      (id) => engine.api.liveActivityExit(id, activityId),
    ),
  );
}

class CarillonNotifications {
  CarillonNotifications._();

  /// Whether the user allows notifications.
  Future<bool> get permission async =>
      await Carillon._ready.platform.permission() == 'granted';

  /// Asks the user (once: the system remembers the answer).
  Future<bool> requestPermission() =>
      Carillon._whenReady.then((engine) => engine.requestPermission());

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
      Carillon._whenReadyCall((engine) => engine.email(email, add: true));

  Future<void> removeEmail(String email) =>
      Carillon._whenReadyCall((engine) => engine.email(email, add: false));

  /// Adds a phone number (international format, +2250700000000), for SMS.
  Future<void> addSms(String phoneNumber) =>
      Carillon._whenReadyCall((engine) => engine.sms(phoneNumber, add: true));

  Future<void> removeSms(String phoneNumber) =>
      Carillon._whenReadyCall((engine) => engine.sms(phoneNumber, add: false));

  /// Adds a WhatsApp number, once the person agreed to receive messages there.
  Future<void> addWhatsApp(String phoneNumber) => Carillon._whenReadyCall(
    (engine) => engine.sms(phoneNumber, add: true, whatsApp: true),
  );

  Future<void> removeWhatsApp(String phoneNumber) => Carillon._whenReadyCall(
    (engine) => engine.sms(phoneNumber, add: false, whatsApp: true),
  );

  Future<void> addTags(Map<String, Object> tags) => Carillon._whenReadyCall(
    (engine) =>
        engine.setTags(tags.map((key, value) => MapEntry(key, '$value'))),
  );

  Future<void> removeTag(String key) => removeTags([key]);

  Future<void> removeTags(List<String> keys) => Carillon._whenReadyCall(
    (engine) => engine.setTags({for (final key in keys) key: null}),
  );

  /// Reports something the person did (purchase, top-up…): it can start a
  /// journey and is attributed to the last notification (conversions).
  Future<void> trackEvent(String name, [Map<String, Object?>? properties]) =>
      Carillon._whenReadyCall(
        (engine) => engine.customEvent(name, properties: properties),
      );

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

/// Outcomes, as OneSignal.Session: conversions attributed to notifications.
class CarillonSession {
  CarillonSession._();

  Future<void> addOutcome(String name) =>
      Carillon._whenReadyCall((engine) => engine.customEvent(name));

  Future<void> addOutcomeWithValue(String name, num value) =>
      Carillon._whenReadyCall(
        (engine) => engine.customEvent(name, value: value),
      );
}

class CarillonPushSubscription {
  CarillonPushSubscription._();

  /// Id of this device on the server (to target it, e.g. for tests), null
  /// until the device is registered.
  String? get id => Carillon._ready.store.subscriptionId;

  /// The id, as it changes: null, then the id once registered.
  ValueListenable<String?> get idListenable => Carillon._ready.subscriptionId;

  /// Completes with the id once the device is registered (at once if it
  /// is), or with null after [timeout].
  Future<String?> ready({
    Duration timeout = const Duration(seconds: 30),
  }) async {
    final engine = await Carillon._whenReady;
    if (engine.subscriptionId.value != null) return engine.subscriptionId.value;
    final completer = Completer<String?>();
    void listener() {
      final id = engine.subscriptionId.value;
      if (id != null && !completer.isCompleted) completer.complete(id);
    }

    engine.subscriptionId.addListener(listener);
    try {
      return await completer.future.timeout(timeout, onTimeout: () => null);
    } finally {
      engine.subscriptionId.removeListener(listener);
    }
  }

  String? get token => Carillon._ready.store.token;

  /// Receives notifications: allowed by the user and not opted out.
  bool get optedIn => Carillon._ready.enabled;

  /// Stops notifications for this device without removing the permission.
  Future<void> optOut() =>
      Carillon._whenReadyCall((engine) => engine.setOptedOut(true));

  Future<void> optIn() =>
      Carillon._whenReadyCall((engine) => engine.setOptedOut(false));
}

class _Engine with WidgetsBindingObserver {
  _Engine(
    this.appId,
    String serverUrl,
    this.store, {
    required this.openUrls,
    this.onError,
  }) : api = CarillonApi(serverUrl, appId),
       platform = NativePush() {
    store
      ..appId = appId
      ..serverUrl = serverUrl;
    subscriptionId.value = store.subscriptionId;
  }

  final void Function(CarillonError error)? onError;

  /// Id of this device on the server, null until it is registered.
  final subscriptionId = ValueNotifier<String?>(null);

  /// Errors are kept (Carillon.lastError) and given to the app.
  void report(String operation, Object error) {
    debugPrint('[carillon] $operation: $error');
    final failure = CarillonError(operation, error);
    Carillon.lastError.value = failure;
    try {
      onError?.call(failure);
    } catch (_) {}
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
    // Before start: tokens held by iOS until Dart is ready arrive at once.
    platform.liveActivities.listen(_handleLiveActivity);
    await platform.start();

    platform.tokens.listen((_) => sync());
    platform.clicks.listen(_handleClick);
    platform.displayed.listen(
      (notification) => _report(notification.id, 'received'),
    );

    final launch = await platform.initialClick();
    if (launch != null) _handleClick(launch);

    WidgetsBinding.instance.addObserver(this);
    unawaited(sync(session: true));
    _startHeartbeat();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      unawaited(sync(session: true));
      _startHeartbeat();
    } else if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.hidden) {
      _heartbeat?.cancel();
      _heartbeat = null;
    }
  }

  /// "Online now" in Carillon: a sign of life every minute in the foreground.
  Timer? _heartbeat;

  void _startHeartbeat() {
    _heartbeat?.cancel();
    _heartbeat = Timer.periodic(const Duration(seconds: 60), (_) {
      final id = store.subscriptionId;
      if (id != null) unawaited(_guard(() => api.heartbeat(id)));
    });
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
    final result = await _guard(
      () => api.login(id, externalId),
      operation: 'login',
    );
    _absorb(result);
  }

  Future<void> logout() async {
    store.externalId = null;
    final id = store.subscriptionId;
    if (id == null) return;
    final result = await _guard(() => api.logout(id), operation: 'logout');
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

  Future<void> sms(
    String phoneNumber, {
    required bool add,
    bool whatsApp = false,
  }) async {
    await (_syncing ?? Future.value());
    final id = store.subscriptionId;
    if (id == null) {
      throw StateError('Carillon: this device is not registered yet.');
    }
    final number = phoneNumber.trim();
    if (whatsApp) {
      add
          ? await api.addWhatsApp(id, number)
          : await api.removeWhatsApp(id, number);
      return;
    }
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
    final result = await _guard(() => api.tags(id, tags), operation: 'tags');
    _absorb(result);
  }

  /// Registers or updates this device (serialized: one call at a time).
  /// A session asked by the launch (or the return to the foreground) and
  /// not counted yet: kept until a sync goes through (on a cold start the
  /// APNs token comes back after the first sync).
  bool _sessionPending = false;

  Future<void> sync({bool session = false}) {
    if (session) _sessionPending = true;
    return _syncing = (_syncing ?? Future.value())
        .then((_) => _sync(_sessionPending))
        .catchError((Object error) {
          report('register', error);
        });
  }

  /// Token given by the app for Huawei or Amazon (see setPushToken).
  String? externalToken;
  String? externalType;

  Future<void> _sync(bool session) async {
    if (externalToken == null &&
        !await platform.ensureFirebase((package) async {
          final config = await api.config(androidPackage: package);
          final firebase = config['firebase'];
          if (firebase is Map) return Map<String, dynamic>.from(firebase);
          report(
            'firebase',
            StateError(
              'No Firebase for $package: add google-services.json to the app, '
              'or upload it in Carillon (Settings → Platforms → Android).',
            ),
          );
          return null;
        })) {
      return;
    }
    // A known device keeps its token while the system has not given it
    // again yet (cold start on iOS).
    final token =
        externalToken ??
        await platform.token() ??
        (store.subscriptionId != null ? store.token : null);
    final permission = await platform.permission();
    enabled = permission == 'granted' && !store.optedOut;

    final now = DateTime.now();
    final newSession =
        session &&
        (store.lastSession == null ||
            now.difference(store.lastSession!) > _sessionGap);

    // An iPhone without the notification permission has no APNs token: it
    // is still registered when it has Live Activity tokens to keep (they
    // need no permission).
    final tokenless =
        token == null &&
        platform.type == 'ios' &&
        store.subscriptionId == null &&
        _liveActivityCalls.isNotEmpty;
    if (token == null && !tokenless) return;

    final body = <String, dynamic>{
      'token': ?token,
      if (externalType != null) 'type': externalType,
      'enabled': enabled,
      'environment': await platform.environment(),
      ...await _device(),
      ...await _location(),
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
        subscriptionId.value = null;
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
      subscriptionId.value = store.subscriptionId;
      if (store.subscriptionId != null) Carillon.lastError.value = null;
      if (newSession && store.subscriptionId != null) {
        await api.update(store.subscriptionId!, {'session': true});
      }
    }

    store.token = token;
    if (newSession) store.lastSession = now;
    final allowed = result?['location'];
    if (allowed is bool) store.locationAllowed = allowed;
    if (body.containsKey('lat')) {
      store.sentLocation = {
        'lat': body['lat'] as num,
        'long': body['long'] as num,
        'at': now.millisecondsSinceEpoch,
      };
    }
    _sessionPending = false;
    await platform.share({
      'server': store.serverUrl,
      'app': appId,
      'subscription': store.subscriptionId,
    });
    _absorb(result);
    await _flushEvents();
    await _flushLiveActivities();
    // The list lives in memory: fetched at each start of the process too,
    // for the messages triggered by events (app_open only on a new session).
    if (newSession || !_inAppLoaded) {
      await _refreshInApp(showOnOpen: newSession);
    }
  }

  /// Fetches the in-app messages of this device, then shows the ones for
  /// the opening of the app.
  Future<void> _refreshInApp({bool showOnOpen = false}) async {
    final id = store.subscriptionId;
    if (id == null) return;
    final result = await _guard(() => api.inAppMessages(id));
    final list = result?['in_app_messages'];
    if (list is! List) return;
    _inAppLoaded = true;
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
    // An event while another message is on screen (or waiting for its
    // delay): shown after it, not lost.
    if (_showingInApp) {
      final key = (trigger, event);
      if (!_queuedInApp.contains(key)) _queuedInApp.add(key);
      return;
    }
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
    // Shown after a while in the app (the first screen stays visible).
    if (message.delaySeconds > 0) {
      await Future<void>.delayed(Duration(seconds: message.delaySeconds));
      if (Carillon.inAppMessages.paused ||
          Carillon.navigatorKey.currentState == null) {
        _showingInApp = false;
        _nextQueuedInApp();
        return;
      }
    }
    store.inAppShown = {
      ...shown,
      message.id: {'at': now, 'session': session},
    };
    unawaited(
      _guard(
        () =>
            api.inAppEvent(message!.id, id, 'shown', variant: message.variant),
      ),
    );
    try {
      final (outcome, button) = await showInAppMessage(navigator, message);
      unawaited(
        _guard(
          () => api.inAppEvent(
            message!.id,
            id,
            outcome == InAppOutcome.clicked ? 'clicked' : 'dismissed',
            buttonId: button?['id'] as String?,
            variant: message.variant,
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
        // openUrls: false, the app routes the link itself (click listener).
        if (openUrls && button['action'] == 'url' && uri != null) {
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
      _nextQueuedInApp();
    }
  }

  bool _inAppLoaded = false;

  /// Triggers (opening of the app, events) that came while a message was
  /// shown or waiting for its delay.
  final _queuedInApp = <(String, String?)>[];

  void _nextQueuedInApp() {
    if (_queuedInApp.isEmpty) return;
    final (trigger, event) = _queuedInApp.removeAt(0);
    // After the closing animation of the previous message.
    Future<void>.delayed(
      const Duration(milliseconds: 400),
      () => _presentInApp(trigger, event: event),
    );
  }

  /// The position, when there is a new one worth sending.
  Future<Map<String, dynamic>> _location() async {
    if (!store.locationShared) return {'location_shared': false};
    if (!store.locationAllowed) return {};
    final position = await platform.lastLocation();
    final lat = position?['lat'];
    final long = position?['long'];
    if (lat is! num || long is! num) return {};

    final sent = store.sentLocation;
    if (sent != null) {
      final age = DateTime.now().millisecondsSinceEpoch - (sent['at'] ?? 0);
      final moved = _meters(sent['lat']!, sent['long']!, lat, long);
      if (age < const Duration(days: 1).inMilliseconds && moved < 500) {
        return {};
      }
    }
    return {
      'lat': lat,
      'long': long,
      'location_accuracy': ?position?['accuracy'],
    };
  }

  static double _meters(num lat1, num long1, num lat2, num long2) {
    const radius = 6371000.0;
    final dLat = (lat2 - lat1) * math.pi / 180;
    final dLong = (long2 - long1) * math.pi / 180;
    final a =
        math.pow(math.sin(dLat / 2), 2) +
        math.cos(lat1 * math.pi / 180) *
            math.cos(lat2 * math.pi / 180) *
            math.pow(math.sin(dLong / 2), 2);
    return 2 * radius * math.asin(math.sqrt(a));
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
      // flutter_timezone 5 returns a TimezoneInfo, earlier versions a String.
      final dynamic zone = await FlutterTimezone.getLocalTimezone();
      timezone = zone is String ? zone : zone.identifier as String?;
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
    // Live Updates (Android) are not messages: nothing to report.
    if (id == null || messageId.startsWith('live:')) return;
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

  /// Live Activity calls waiting for the device to be registered (iOS
  /// gives no push token before the notification permission). The last
  /// call per key wins.
  final _liveActivityCalls =
      <String, Future<Map<String, dynamic>> Function(String id)>{};

  void _handleLiveActivity(Map<String, dynamic> event) {
    final type = event['activity_type'] as String?;
    final activityId = event['activity_id'] as String?;
    final token = event['token'] as String?;
    switch (event['event']) {
      case 'onLiveActivityStartToken' when type != null:
        unawaited(
          liveActivity(
            'start:$type',
            (id) => api.liveActivityStartToken(id, type, token),
          ),
        );
      case 'onLiveActivityToken' when activityId != null && token != null:
        unawaited(
          liveActivity(
            'activity:$activityId',
            (id) => api.liveActivityEnter(
              id,
              activityId,
              token,
              activityType: type,
            ),
          ),
        );
      case 'onLiveActivityEnded' when activityId != null:
        unawaited(
          liveActivity(
            'activity:$activityId',
            (id) => api.liveActivityExit(id, activityId),
          ),
        );
    }
  }

  Future<void> liveActivity(
    String key,
    Future<Map<String, dynamic>> Function(String id) call,
  ) async {
    // Known device: sent at once (the app may be awake for a few seconds
    // only); else after the registration in progress.
    if (store.subscriptionId == null) await (_syncing ?? Future.value());
    final id = store.subscriptionId;
    if (id == null) {
      _liveActivityCalls[key] = call;
      // Registers the device even without push token (see _sync).
      if (platform.type == 'ios') unawaited(sync());
      return;
    }
    await _guard(() => call(id), operation: 'liveActivity');
  }

  Future<void> _flushLiveActivities() async {
    final id = store.subscriptionId;
    if (id == null || _liveActivityCalls.isEmpty) return;
    final calls = List.of(_liveActivityCalls.values);
    _liveActivityCalls.clear();
    for (final call in calls) {
      await _guard(() => call(id), operation: 'liveActivity');
    }
  }

  Future<Map<String, dynamic>?> _guard(
    Future<Map<String, dynamic>> Function() call, {
    String operation = 'request',
  }) async {
    try {
      return await call();
    } catch (error) {
      report(operation, error);
      return null;
    }
  }
}
