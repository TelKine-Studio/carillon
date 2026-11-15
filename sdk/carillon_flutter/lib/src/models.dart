/// A button of a notification.
class CarillonButton {
  const CarillonButton({required this.id, required this.text});

  final String id;
  final String text;

  factory CarillonButton.fromJson(Map<String, dynamic> json) =>
      CarillonButton(id: '${json['id'] ?? ''}', text: '${json['text'] ?? ''}');
}

/// A notification sent by Carillon, as received by the device.
class CarillonNotification {
  const CarillonNotification({
    required this.id,
    this.title,
    this.subtitle,
    this.body,
    this.data = const {},
    this.url,
    this.image,
    this.buttons = const [],
    this.raw = const {},
  });

  /// Message id (the "id" returned by the API).
  final String id;
  final String? title;
  final String? subtitle;
  final String? body;

  /// The "data" of the message.
  final Map<String, dynamic> data;

  /// Launch URL (web page or deep link).
  final String? url;
  final String? image;
  final List<CarillonButton> buttons;

  /// The whole payload, as sent by the server.
  final Map<String, dynamic> raw;

  /// Builds a notification from the "carillon" part of a push payload.
  static CarillonNotification? fromPayload(Map<String, dynamic>? payload) {
    if (payload == null || payload['i'] == null) return null;
    final buttons = payload['b'];
    final data = payload['d'];

    return CarillonNotification(
      id: '${payload['i']}',
      title: payload['title'] as String?,
      subtitle: payload['subtitle'] as String?,
      body: payload['body'] as String?,
      data: data is Map ? Map<String, dynamic>.from(data) : const {},
      url: payload['u'] as String?,
      image: payload['img'] as String?,
      buttons: buttons is List
          ? buttons
                .whereType<Map>()
                .map((b) => CarillonButton.fromJson(Map<String, dynamic>.from(b)))
                .toList()
          : const [],
      raw: payload,
    );
  }
}

/// A notification (or one of its buttons) tapped by the user.
class CarillonClickEvent {
  const CarillonClickEvent({required this.notification, this.actionId});

  final CarillonNotification notification;

  /// Id of the button tapped, null for the notification itself.
  final String? actionId;
}

/// Lets the app decide whether a notification received while it is open
/// is shown.
class CarillonForegroundEvent {
  CarillonForegroundEvent(this.notification);

  final CarillonNotification notification;
  bool _display = true;

  bool get willDisplay => _display;

  /// Hides this notification (the app shows its own UI instead).
  void preventDefault() => _display = false;
}
