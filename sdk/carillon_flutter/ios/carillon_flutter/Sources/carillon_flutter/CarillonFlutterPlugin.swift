import Flutter
import UIKit
import UserNotifications

/// Native side of Carillon on iOS: APNs registration, permission, and the
/// notification taps / foreground presentations, forwarded to Dart.
public class CarillonFlutterPlugin: NSObject, FlutterPlugin {
  private let channel: FlutterMethodChannel
  private var token: String?
  private var started = false
  /// Tap received before Dart was ready (cold start).
  private var initialResponse: [String: Any]?
  /// Live Activity tokens received before Dart was ready.
  private var pendingEvents: [(String, [String: Any])] = []

  init(channel: FlutterMethodChannel) {
    self.channel = channel
  }

  public static func register(with registrar: FlutterPluginRegistrar) {
    let channel = FlutterMethodChannel(name: "carillon_flutter", binaryMessenger: registrar.messenger())
    let instance = CarillonFlutterPlugin(channel: channel)
    registrar.addMethodCallDelegate(instance, channel: channel)
    // FlutterAppDelegate forwards the APNs token and the notification center
    // callbacks to the plugins registered here.
    registrar.addApplicationDelegate(instance)

    // Plugins are registered on the main thread.
    if #available(iOS 16.1, *) {
      MainActor.assumeIsolated {
        CarillonLiveActivities.sink = { [weak instance] method, arguments in
          guard let instance else { return }
          if instance.started {
            instance.channel.invokeMethod(method, arguments: arguments)
          } else {
            instance.pendingEvents.append((method, arguments))
          }
        }
      }
    }

    let center = UNUserNotificationCenter.current()
    if center.delegate == nil, let delegate = UIApplication.shared.delegate as? UNUserNotificationCenterDelegate {
      center.delegate = delegate
    }
  }

  public func handle(_ call: FlutterMethodCall, result: @escaping FlutterResult) {
    switch call.method {
    case "start":
      started = true
      registerIfAllowed()
      result(nil)
      for (method, arguments) in pendingEvents {
        channel.invokeMethod(method, arguments: arguments)
      }
      pendingEvents.removeAll()
    case "token":
      result(token)
    case "environment":
      result(Self.environment)
    case "permission":
      UNUserNotificationCenter.current().getNotificationSettings { settings in
        DispatchQueue.main.async { result(Self.describe(settings.authorizationStatus)) }
      }
    case "requestPermission":
      UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound]) { granted, _ in
        DispatchQueue.main.async {
          if granted { UIApplication.shared.registerForRemoteNotifications() }
          result(granted)
        }
      }
    case "initialNotification":
      result(initialResponse)
      initialResponse = nil
    case "lastLocation":
      result(CarillonLocation.lastKnown())
    case "clearAll":
      let center = UNUserNotificationCenter.current()
      center.removeAllDeliveredNotifications()
      if #available(iOS 16.0, *) {
        center.setBadgeCount(0)
      } else {
        UIApplication.shared.applicationIconBadgeNumber = 0
      }
      result(nil)
    case "share":
      // For the notification service extension (reception reports, App
      // Group) and the Live Activity tokens sent natively (the app itself).
      if let values = call.arguments as? [String: Any] {
        var stores = [UserDefaults.standard]
        if let group = Bundle.main.object(forInfoDictionaryKey: "CarillonAppGroup") as? String,
           let defaults = UserDefaults(suiteName: group) {
          stores.append(defaults)
        }
        for defaults in stores {
          for (key, value) in values {
            defaults.set(value is NSNull ? nil : value, forKey: "carillon.\(key)")
          }
        }
      }
      result(nil)
    default:
      result(FlutterMethodNotImplemented)
    }
  }

  /// Already allowed (or provisional): fetch the token without prompting.
  private func registerIfAllowed() {
    UNUserNotificationCenter.current().getNotificationSettings { settings in
      guard [.authorized, .provisional, .ephemeral].contains(settings.authorizationStatus) else { return }
      DispatchQueue.main.async { UIApplication.shared.registerForRemoteNotifications() }
    }
  }

  // MARK: - Application delegate

  public func application(_ application: UIApplication, didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data) {
    let token = deviceToken.map { String(format: "%02x", $0) }.joined()
    self.token = token
    channel.invokeMethod("onToken", arguments: ["token": token])
  }

  public func application(_ application: UIApplication, didFailToRegisterForRemoteNotificationsWithError error: Error) {
    NSLog("[carillon] APNs registration failed: %@", error.localizedDescription)
  }

  // MARK: - Notification center (forwarded by FlutterAppDelegate)

  public func userNotificationCenter(
    _ center: UNUserNotificationCenter,
    willPresent notification: UNNotification,
    withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
  ) {
    // Not ours: left to the plugin that posted it.
    guard let payload = notification.request.content.userInfo["carillon"] as? [String: Any] else { return }

    channel.invokeMethod("onForeground", arguments: ["carillon": payload]) { show in
      let display = (show as? Bool) ?? true
      completionHandler(display ? [.banner, .list, .sound, .badge] : [])
    }
  }

  public func userNotificationCenter(
    _ center: UNUserNotificationCenter,
    didReceive response: UNNotificationResponse,
    withCompletionHandler completionHandler: @escaping () -> Void
  ) {
    guard let payload = response.notification.request.content.userInfo["carillon"] as? [String: Any] else { return }

    if response.actionIdentifier == UNNotificationDismissActionIdentifier {
      completionHandler()
      return
    }

    var event: [String: Any] = ["carillon": payload]
    if response.actionIdentifier != UNNotificationDefaultActionIdentifier {
      event["action"] = response.actionIdentifier
    }

    if started {
      channel.invokeMethod("onOpened", arguments: event)
    } else {
      initialResponse = event
    }
    completionHandler()
  }

  // MARK: - Helpers

  private static func describe(_ status: UNAuthorizationStatus) -> String {
    switch status {
    case .authorized, .provisional, .ephemeral: return "granted"
    case .denied: return "denied"
    default: return "not_determined"
    }
  }

  /// APNs environment of this build: "sandbox" for debug builds and
  /// development profiles, "production" for TestFlight and the App Store
  /// (no embedded profile).
  static let environment: String = {
    #if targetEnvironment(simulator)
    return "sandbox"
    #else
    guard let url = Bundle.main.url(forResource: "embedded", withExtension: "mobileprovision"),
          let data = try? Data(contentsOf: url),
          let text = String(data: data, encoding: .isoLatin1),
          let start = text.range(of: "<?xml"),
          let end = text.range(of: "</plist>"),
          let plist = try? PropertyListSerialization.propertyList(
            from: Data(text[start.lowerBound..<end.upperBound].utf8), options: [], format: nil
          ) as? [String: Any],
          let entitlements = plist["Entitlements"] as? [String: Any],
          let aps = entitlements["aps-environment"] as? String
    else { return "production" }
    return aps == "development" ? "sandbox" : "production"
    #endif
  }()
}
