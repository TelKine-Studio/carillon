import Foundation
#if canImport(ActivityKit)
import ActivityKit

/// The standard Live Activity of Carillon: started, updated and ended from
/// your server with the "live" object of the API (activity_type
/// "CarillonActivityAttributes"), shown by `CarillonLiveActivityWidget`.
/// Every field is optional: a field the server does not send keeps its
/// default instead of making iOS drop the update.
@available(iOS 16.1, *)
public struct CarillonActivityAttributes: ActivityAttributes {
    public struct ContentState: Codable, Hashable {
        /// Main line ("Top-up in progress").
        public var title: String?
        /// Second line ("Sending 1 000 F to Orange").
        public var body: String?
        /// Short state, top right and in the Dynamic Island ("2/3", "On its way").
        public var status: String?
        /// From 0 to 1; with `steps`, `step` is used instead.
        public var progress: Double?
        /// Current step (1-based) of `steps`: a segmented bar.
        public var step: Int?
        public var steps: Int?
        /// "active" (default), "success" or "failure": the icon becomes a
        /// check mark or a cross.
        public var state: String?

        public init(title: String? = nil, body: String? = nil, status: String? = nil, progress: Double? = nil, step: Int? = nil, steps: Int? = nil, state: String? = nil) {
            self.title = title
            self.body = body
            self.status = status
            self.progress = progress
            self.step = step
            self.steps = steps
            self.state = state
        }
    }

    /// Name shown above the title (your app, the service: "MaRecharge").
    public var name: String?
    /// SF Symbol of the activity ("bolt.fill", "shippingbox.fill"…).
    public var icon: String?
    /// Accent color, hex ("#0F766E").
    public var color: String?
    /// Shown when the activity is stale ("Updating…").
    public var staleLabel: String?
    /// Filled by Carillon: {"activityId": …}.
    public var carillon: [String: String]?

    public init(name: String? = nil, icon: String? = nil, color: String? = nil, staleLabel: String? = nil, activityId: String) {
        self.name = name
        self.icon = icon
        self.color = color
        self.staleLabel = staleLabel
        carillon = ["activityId": activityId]
    }

    /// The activity_id of Carillon (for `CarillonLiveActivityAttributes`).
    public var carillonActivityId: String {
        carillon?["activityId"] ?? ""
    }
}
#endif
