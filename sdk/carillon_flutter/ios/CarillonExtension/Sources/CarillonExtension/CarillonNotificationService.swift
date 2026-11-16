// Carillon notification service extension, as a Swift package.
//
// Adds the image and the buttons of Carillon notifications and, when the app
// and the extension share an App Group (CarillonAppGroup key in both
// Info.plist), reports their reception.

import UserNotifications

/// The service of the Notification Service Extension: subclass it.
///
///     import CarillonExtension
///     class NotificationService: CarillonNotificationService {}
open class CarillonNotificationService: UNNotificationServiceExtension {
  /// Version of the extension, to compare with the Dart SDK (Carillon.sdkVersion).
  public static let version = "0.2.0"

  private var contentHandler: ((UNNotificationContent) -> Void)?
  private var content: UNMutableNotificationContent?

  override open func didReceive(_ request: UNNotificationRequest, withContentHandler contentHandler: @escaping (UNNotificationContent) -> Void) {
    self.contentHandler = contentHandler
    guard let content = request.content.mutableCopy() as? UNMutableNotificationContent,
          let payload = content.userInfo["carillon"] as? [String: Any]
    else {
      contentHandler(request.content)
      return
    }
    self.content = content

    let group = DispatchGroup()

    if let buttons = payload["b"] as? [[String: Any]], !buttons.isEmpty {
      group.enter()
      Self.register(buttons: buttons) { category in
        content.categoryIdentifier = category
        group.leave()
      }
    }

    if let image = payload["img"] as? String, let url = URL(string: image) {
      group.enter()
      Self.attachment(from: url) { attachment in
        if let attachment { content.attachments = [attachment] }
        group.leave()
      }
    }

    if let id = payload["i"] as? String {
      group.enter()
      Self.report(messageId: id) { group.leave() }
    }

    group.notify(queue: .main) { contentHandler(content) }
  }

  override open func serviceExtensionTimeWillExpire() {
    if let contentHandler, let content { contentHandler(content) }
  }

  /// A category per set of buttons, added to the ones already known.
  private static func register(buttons: [[String: Any]], completion: @escaping (String) -> Void) {
    let actions = buttons.compactMap { button -> UNNotificationAction? in
      guard let id = button["id"] as? String, let text = button["text"] as? String else { return nil }
      return UNNotificationAction(identifier: id, title: text, options: [.foreground])
    }
    let identifier = "carillon_" + actions.map(\.identifier).joined(separator: "_")
    let center = UNUserNotificationCenter.current()
    center.getNotificationCategories { categories in
      if !categories.contains(where: { $0.identifier == identifier }) {
        let category = UNNotificationCategory(identifier: identifier, actions: actions, intentIdentifiers: [])
        center.setNotificationCategories(categories.union([category]))
      }
      completion(identifier)
    }
  }

  private static func attachment(from url: URL, completion: @escaping (UNNotificationAttachment?) -> Void) {
    URLSession.shared.downloadTask(with: url) { location, response, _ in
      guard let location else { return completion(nil) }
      let ext = response?.suggestedFilename.map { ($0 as NSString).pathExtension }.flatMap { $0.isEmpty ? nil : $0 } ?? "jpg"
      let file = FileManager.default.temporaryDirectory.appendingPathComponent(UUID().uuidString + "." + ext)
      try? FileManager.default.moveItem(at: location, to: file)
      completion(try? UNNotificationAttachment(identifier: "image", url: file))
    }.resume()
  }

  private static func report(messageId: String, completion: @escaping () -> Void) {
    guard let groupName = Bundle.main.object(forInfoDictionaryKey: "CarillonAppGroup") as? String,
          let defaults = UserDefaults(suiteName: groupName),
          let server = defaults.string(forKey: "carillon.server"),
          let app = defaults.string(forKey: "carillon.app"),
          let subscription = defaults.string(forKey: "carillon.subscription"),
          let url = URL(string: "\(server.trimmingCharacters(in: CharacterSet(charactersIn: "/")))/api/v1/sdk/apps/\(app)/subscriptions/\(subscription)/events")
    else { return completion() }

    var request = URLRequest(url: url, timeoutInterval: 10)
    request.httpMethod = "POST"
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")
    request.setValue("application/json", forHTTPHeaderField: "Accept")
    request.httpBody = try? JSONSerialization.data(withJSONObject: ["message_id": messageId, "event": "received"])
    URLSession.shared.dataTask(with: request) { _, _, _ in completion() }.resume()
  }
}
