#if canImport(ActivityKit) && canImport(WidgetKit) && canImport(SwiftUI)
import ActivityKit
import SwiftUI
import WidgetKit

/// Ready-made look of `CarillonActivityAttributes`: lock screen and Dynamic
/// Island, light and dark, progress ring or segmented bar, stale state.
/// In the widget extension of the app:
///
///     @main
///     struct Widgets: WidgetBundle {
///         var body: some Widget { CarillonLiveActivityWidget() }
///     }
@available(iOS 16.2, *)
public struct CarillonLiveActivityWidget: Widget {
    public init() {}

    public var body: some WidgetConfiguration {
        ActivityConfiguration(for: CarillonActivityAttributes.self) { context in
            LockScreenView(model: Model(context))
                .activitySystemActionForegroundColor(Model(context).accent)
        } dynamicIsland: { context in
            let model = Model(context)
            return DynamicIsland {
                DynamicIslandExpandedRegion(.leading) {
                    IconView(model: model, size: 36).padding(.leading, 4)
                }
                DynamicIslandExpandedRegion(.trailing) {
                    if let status = model.status {
                        Text(status).font(.callout.weight(.semibold)).foregroundStyle(model.accent)
                            .lineLimit(1).padding(.trailing, 4)
                    }
                }
                DynamicIslandExpandedRegion(.center) {
                    Text(model.title).font(.headline).lineLimit(1)
                }
                DynamicIslandExpandedRegion(.bottom) {
                    VStack(alignment: .leading, spacing: 8) {
                        if let body = model.body {
                            Text(body).font(.subheadline).foregroundStyle(.secondary).lineLimit(2)
                        }
                        ProgressBar(model: model)
                    }
                    .padding(.horizontal, 4)
                }
            } compactLeading: {
                Image(systemName: model.symbol).foregroundStyle(model.markColor)
            } compactTrailing: {
                Ring(model: model).frame(width: 18, height: 18)
            } minimal: {
                Ring(model: model).frame(width: 18, height: 18)
            }
            .keylineTint(model.accent)
        }
    }
}

@available(iOS 16.2, *)
struct Model {
    let title: String
    let body: String?
    let status: String?
    let name: String?
    let accent: Color
    let fraction: Double?
    let step: Int?
    let steps: Int?
    let state: String
    let stale: Bool
    let staleLabel: String
    let icon: String

    init(_ context: ActivityViewContext<CarillonActivityAttributes>) {
        let state = context.state
        let attributes = context.attributes
        title = state.title ?? attributes.name ?? ""
        body = state.body
        status = context.isStale ? nil : state.status
        name = attributes.name
        accent = Color(hex: attributes.color) ?? .accentColor
        steps = (state.steps ?? 0) > 0 ? state.steps : nil
        step = state.step.map { max(0, min($0, state.steps ?? $0)) }
        if let steps, let step {
            fraction = Double(step) / Double(steps)
        } else {
            fraction = state.progress.map { max(0, min(1, $0)) }
        }
        self.state = state.state ?? "active"
        stale = context.isStale
        staleLabel = attributes.staleLabel ?? "…"
        icon = attributes.icon ?? "bell.fill"
    }

    var symbol: String {
        switch state {
        case "success": "checkmark.circle.fill"
        case "failure": "xmark.circle.fill"
        default: icon
        }
    }

    var markColor: Color {
        switch state {
        case "success": .green
        case "failure": .red
        default: accent
        }
    }
}

@available(iOS 16.2, *)
struct LockScreenView: View {
    let model: Model

    var body: some View {
        HStack(alignment: .top, spacing: 14) {
            IconView(model: model, size: 44)
            VStack(alignment: .leading, spacing: 4) {
                HStack(spacing: 8) {
                    if let name = model.name {
                        Text(name).font(.caption.weight(.medium)).foregroundStyle(.secondary).lineLimit(1)
                    }
                    Spacer(minLength: 0)
                    if model.stale {
                        Label(model.staleLabel, systemImage: "clock").font(.caption).foregroundStyle(.secondary)
                    } else if let status = model.status {
                        Text(status).font(.caption.weight(.semibold)).foregroundStyle(model.accent).lineLimit(1)
                    }
                }
                Text(model.title).font(.headline).lineLimit(1)
                if let body = model.body {
                    Text(body).font(.subheadline).foregroundStyle(.secondary).lineLimit(2)
                }
                ProgressBar(model: model).padding(.top, 4)
            }
        }
        .padding(16)
        .opacity(model.stale ? 0.7 : 1)
    }
}

@available(iOS 16.2, *)
struct IconView: View {
    let model: Model
    let size: CGFloat

    var body: some View {
        Image(systemName: model.symbol)
            .font(.system(size: size * 0.45, weight: .semibold))
            .foregroundStyle(model.markColor)
            .frame(width: size, height: size)
            .background(model.markColor.opacity(0.15), in: RoundedRectangle(cornerRadius: size * 0.3, style: .continuous))
    }
}

/// Segmented bar with steps, plain bar with a progress, nothing otherwise.
@available(iOS 16.2, *)
struct ProgressBar: View {
    let model: Model

    var body: some View {
        if let steps = model.steps {
            HStack(spacing: 4) {
                ForEach(0..<steps, id: \.self) { index in
                    Capsule().fill(index < (model.step ?? 0) ? model.markColor : model.markColor.opacity(0.2))
                }
            }
            .frame(height: 5)
        } else if let fraction = model.fraction {
            GeometryReader { proxy in
                ZStack(alignment: .leading) {
                    Capsule().fill(model.markColor.opacity(0.2))
                    Capsule().fill(model.markColor).frame(width: max(5, proxy.size.width * fraction))
                }
            }
            .frame(height: 5)
        }
    }
}

/// Progress ring drawn inside its frame (the stroke never goes past it).
@available(iOS 16.2, *)
struct Ring: View {
    let model: Model
    private let line: CGFloat = 3

    var body: some View {
        if model.state != "active" {
            Image(systemName: model.symbol).foregroundStyle(model.markColor)
        } else if let fraction = model.fraction {
            ZStack {
                Circle().inset(by: line / 2).stroke(model.accent.opacity(0.25), lineWidth: line)
                Circle().inset(by: line / 2).trim(from: 0, to: fraction)
                    .stroke(model.accent, style: StrokeStyle(lineWidth: line, lineCap: .round))
                    .rotationEffect(.degrees(-90))
            }
        } else {
            Image(systemName: model.symbol).foregroundStyle(model.accent)
        }
    }
}

extension Color {
    /// "#0F766E", "0F766E" or "#RGB".
    init?(hex: String?) {
        guard var value = hex?.trimmingCharacters(in: .whitespaces), !value.isEmpty else { return nil }
        if value.hasPrefix("#") { value.removeFirst() }
        if value.count == 3 { value = value.map { "\($0)\($0)" }.joined() }
        guard value.count == 6, let number = UInt32(value, radix: 16) else { return nil }
        self.init(
            red: Double((number >> 16) & 0xFF) / 255,
            green: Double((number >> 8) & 0xFF) / 255,
            blue: Double(number & 0xFF) / 255
        )
    }
}
#endif
