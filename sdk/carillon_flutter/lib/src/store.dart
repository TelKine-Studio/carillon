import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// What the SDK remembers between launches.
class CarillonStore {
  CarillonStore(this._prefs);

  final SharedPreferences _prefs;

  static Future<CarillonStore> open() async =>
      CarillonStore(await SharedPreferences.getInstance());

  static const _prefix = 'carillon.';

  String? get serverUrl => _prefs.getString('${_prefix}server');
  set serverUrl(String? value) => _set('server', value);

  String? get appId => _prefs.getString('${_prefix}app_id');
  set appId(String? value) => _set('app_id', value);

  String? get subscriptionId => _prefs.getString('${_prefix}subscription');
  set subscriptionId(String? value) => _set('subscription', value);

  String? get externalId => _prefs.getString('${_prefix}external_id');
  set externalId(String? value) => _set('external_id', value);

  String? get token => _prefs.getString('${_prefix}token');
  set token(String? value) => _set('token', value);

  bool get optedOut => _prefs.getBool('${_prefix}opted_out') ?? false;
  set optedOut(bool value) => _prefs.setBool('${_prefix}opted_out', value);

  DateTime? get lastSession {
    final value = _prefs.getInt('${_prefix}last_session');
    return value == null ? null : DateTime.fromMillisecondsSinceEpoch(value);
  }

  set lastSession(DateTime? value) => value == null
      ? _prefs.remove('${_prefix}last_session')
      : _prefs.setInt('${_prefix}last_session', value.millisecondsSinceEpoch);

  Map<String, String> get tags {
    final raw = _prefs.getString('${_prefix}tags');
    return raw == null ? {} : Map<String, String>.from(jsonDecode(raw) as Map);
  }

  set tags(Map<String, String> value) =>
      _prefs.setString('${_prefix}tags', jsonEncode(value));

  /// When each in-app message was last shown (id => {at, session}).
  Map<String, Map<String, int>> get inAppShown {
    final raw = _prefs.getString('${_prefix}in_app');
    if (raw == null) return {};
    return (jsonDecode(raw) as Map).map(
      (key, value) =>
          MapEntry(key as String, Map<String, int>.from(value as Map)),
    );
  }

  set inAppShown(Map<String, Map<String, int>> value) =>
      _prefs.setString('${_prefix}in_app', jsonEncode(value));

  /// Clicks or receptions not reported yet (no network at the time).
  List<Map<String, dynamic>> get pendingEvents {
    final raw = _prefs.getString('${_prefix}events');
    return raw == null
        ? []
        : (jsonDecode(raw) as List)
              .map((e) => Map<String, dynamic>.from(e as Map))
              .toList();
  }

  set pendingEvents(List<Map<String, dynamic>> value) => _prefs.setString(
    '${_prefix}events',
    jsonEncode(value.take(100).toList()),
  );

  Future<void> reload() => _prefs.reload();

  void _set(String key, String? value) => value == null
      ? _prefs.remove('$_prefix$key')
      : _prefs.setString('$_prefix$key', value);
}
