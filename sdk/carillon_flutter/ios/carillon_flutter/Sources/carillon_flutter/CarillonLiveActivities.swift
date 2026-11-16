import Foundation
#if canImport(ActivityKit)
import ActivityKit
#endif

#if canImport(ActivityKit)
/// ActivityAttributes that Carillon can start, update and end. Carillon
/// puts the activity_id in the attributes under "carillon" when it starts an
/// activity by push; keep it in a field shared with the widget:
///
///     struct DeliveryAttributes: ActivityAttributes {   // app and widget
///       struct ContentState: Codable, Hashable { var step: String }
///       var reference: String
///       var carillon: [String: String]                    // {"activityId": …}
///     }
///
///     extension DeliveryAttributes: CarillonLiveActivityAttributes {  // app only
///       var carillonActivityId: String { carillon["activityId"] ?? "" }
///     }
#if compiler(>=6.2)
@available(iOS 16.1, *)
public protocol CarillonLiveActivityAttributes: ActivityAttributes, SendableMetatype {
  var carillonActivityId: String { get }
}
#else
@available(iOS 16.1, *)
public protocol CarillonLiveActivityAttributes: ActivityAttributes {
  var carillonActivityId: String { get }
}
#endif

/// Sends Carillon the tokens of the Live Activities of one type:
///
///     if #available(iOS 16.1, *) {
///       CarillonLiveActivities.observe(DeliveryAttributes.self)
///     }
///
/// Call it at launch (AppDelegate, after GeneratedPluginRegistrant): the
/// push-to-start token (iOS 17.2+), the update token of each activity on
/// screen, and the end of each activity reach the server through the SDK.
@available(iOS 16.1, *)
public enum CarillonLiveActivities {
  /// Set by the plugin: forwards an event to Dart.
  @MainActor static var sink: ((String, [String: Any]) -> Void)?

  @MainActor private static var watched = Set<String>()

  public static func observe<Attributes: CarillonLiveActivityAttributes>(_ type: Attributes.Type) {
    let name = String(describing: type)

    if #available(iOS 17.2, *) {
      Task { @MainActor in
        for await data in Activity<Attributes>.pushToStartTokenUpdates {
          emit("onLiveActivityStartToken", ["activity_type": name, "token": hex(data)])
        }
      }
    }

    Task { @MainActor in
      for activity in Activity<Attributes>.activities {
        watch(activity, type: name)
      }
      for await activity in Activity<Attributes>.activityUpdates {
        watch(activity, type: name)
      }
    }
  }

  @MainActor
  private static func watch<Attributes: CarillonLiveActivityAttributes>(_ activity: Activity<Attributes>, type: String) {
    guard watched.insert(activity.id).inserted else { return }
    let activityId = activity.attributes.carillonActivityId
    guard !activityId.isEmpty else { return }

    Task { @MainActor in
      for await data in activity.pushTokenUpdates {
        emit("onLiveActivityToken", ["activity_id": activityId, "activity_type": type, "token": hex(data)])
      }
    }
    Task { @MainActor in
      for await state in activity.activityStateUpdates where state == .ended || state == .dismissed {
        emit("onLiveActivityEnded", ["activity_id": activityId])
        break
      }
    }
  }

  @MainActor
  private static func emit(_ method: String, _ arguments: [String: Any]) {
    sink?(method, arguments)
  }

  private static func hex(_ data: Data) -> String {
    data.map { String(format: "%02x", $0) }.joined()
  }
}
#endif
