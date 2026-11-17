import 'dart:convert';

import 'package:http/http.dart' as http;

/// Calls the SDK endpoints of a Carillon server.
class CarillonApi {
  CarillonApi(String serverUrl, this.appId, {http.Client? client})
    : base =
          '${serverUrl.replaceAll(RegExp(r'/+$'), '')}/api/v1/sdk/apps/$appId',
      _client = client ?? http.Client();

  final String appId;
  final String base;
  final http.Client _client;

  Future<Map<String, dynamic>> register(Map<String, dynamic> body) =>
      _send('POST', '/subscriptions', body);

  Future<Map<String, dynamic>> update(String id, Map<String, dynamic> body) =>
      _send('PATCH', '/subscriptions/$id', body);

  Future<Map<String, dynamic>> login(
    String id,
    String externalId, {
    String? identityHash,
  }) => _send('POST', '/subscriptions/$id/login', {
    'external_id': externalId,
    'identity_hash': ?identityHash,
  });

  Future<Map<String, dynamic>> logout(String id) =>
      _send('POST', '/subscriptions/$id/logout', const {});

  Future<Map<String, dynamic>> addEmail(String id, String email) =>
      _send('POST', '/subscriptions/$id/email', {'email': email});

  Future<Map<String, dynamic>> removeEmail(String id, String email) =>
      _send('DELETE', '/subscriptions/$id/email', {'email': email});

  Future<Map<String, dynamic>> addSms(String id, String phoneNumber) =>
      _send('POST', '/subscriptions/$id/sms', {'phone_number': phoneNumber});

  Future<Map<String, dynamic>> removeSms(String id, String phoneNumber) =>
      _send('DELETE', '/subscriptions/$id/sms', {'phone_number': phoneNumber});

  Future<Map<String, dynamic>> addWhatsApp(String id, String phoneNumber) =>
      _send('POST', '/subscriptions/$id/whatsapp', {
        'phone_number': phoneNumber,
      });

  Future<Map<String, dynamic>> removeWhatsApp(String id, String phoneNumber) =>
      _send('DELETE', '/subscriptions/$id/whatsapp', {
        'phone_number': phoneNumber,
      });

  Future<Map<String, dynamic>> tags(String id, Map<String, dynamic> tags) =>
      _send('PUT', '/subscriptions/$id/tags', {'tags': tags});

  Future<Map<String, dynamic>> customEvent(
    String id,
    String name, {
    num? value,
    Map<String, Object?>? properties,
  }) => _send('POST', '/subscriptions/$id/custom-events', {
    'name': name,
    'value': ?value,
    'properties': ?properties,
  });

  /// Settings of the project for this app (Firebase options of an Android
  /// package, web push key…).
  Future<Map<String, dynamic>> config({String? androidPackage}) => _send(
    'GET',
    '/config${androidPackage == null ? '' : '?android_package=${Uri.encodeQueryComponent(androidPackage)}'}',
    const {},
  );

  /// Push-to-start token of a type of Live Activity (null removes it).
  Future<Map<String, dynamic>> liveActivityStartToken(
    String id,
    String activityType,
    String? token,
  ) => _send('PUT', '/subscriptions/$id/live-activities/start-token', {
    'activity_type': activityType,
    'token': token,
  });

  Future<Map<String, dynamic>> liveActivityEnter(
    String id,
    String activityId,
    String token, {
    String? activityType,
  }) => _send('POST', '/subscriptions/$id/live-activities/enter', {
    'activity_id': activityId,
    'token': token,
    'activity_type': ?activityType,
  });

  Future<Map<String, dynamic>> liveActivityExit(String id, String activityId) =>
      _send('POST', '/subscriptions/$id/live-activities/exit', {
        'activity_id': activityId,
      });

  Future<Map<String, dynamic>> heartbeat(String id) =>
      _send('POST', '/subscriptions/$id/heartbeat', const {});

  Future<Map<String, dynamic>> inAppMessages(String id) =>
      _send('GET', '/subscriptions/$id/in-app-messages', const {});

  Future<Map<String, dynamic>> inAppEvent(
    String messageId,
    String subscriptionId,
    String event, {
    String? buttonId,
    String? variant,
  }) => _send('POST', '/in-app-messages/$messageId/events', {
    'subscription_id': subscriptionId,
    'event': event,
    'button_id': ?buttonId,
    'variant': ?variant,
  });

  Future<void> event(
    String id,
    String messageId,
    String event, {
    String? actionId,
  }) => _send('POST', '/subscriptions/$id/events', {
    'message_id': messageId,
    'event': event,
    'action_id': ?actionId,
  });

  Future<Map<String, dynamic>> _send(
    String method,
    String path,
    Map<String, dynamic> body,
  ) async {
    final request = http.Request(method, Uri.parse('$base$path'))
      ..headers.addAll({
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      })
      ..body = method == 'GET' ? '' : jsonEncode(body);

    final response = await http.Response.fromStream(
      await _client.send(request).timeout(const Duration(seconds: 20)),
    );
    final json = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body);

    if (response.statusCode >= 400) {
      final errors = json is Map ? json['errors'] : null;
      throw CarillonException(
        response.statusCode,
        errors is List && errors.isNotEmpty
            ? '${errors.first}'
            : 'HTTP ${response.statusCode}',
      );
    }

    return json is Map ? Map<String, dynamic>.from(json) : <String, dynamic>{};
  }
}

class CarillonException implements Exception {
  const CarillonException(this.status, this.message);

  final int status;
  final String message;

  /// The device is unknown to the server (deleted from the dashboard...).
  bool get isGone => status == 404;

  @override
  String toString() => 'CarillonException($status): $message';
}
