import Foundation

/// Last known position of the device, read only when the app already has
/// the location permission: Carillon never asks for it.
///
/// CoreLocation is loaded at run time, so that apps which never use the
/// location do not link it (App Store Connect asks for a purpose string as
/// soon as an app references the location APIs).
enum CarillonLocation {
  static func lastKnown() -> [String: Any]? {
    guard dlopen("/System/Library/Frameworks/CoreLocation.framework/CoreLocation", RTLD_LAZY) != nil,
          let managerClass = NSClassFromString("CLLocationManager") as? NSObject.Type
    else { return nil }

    let manager = managerClass.init()
    // CLAuthorizationStatus: 3 = always, 4 = when in use.
    guard let status = (manager.value(forKey: "authorizationStatus") as? NSNumber)?.intValue,
          status == 3 || status == 4,
          let location = manager.value(forKey: "location") as? NSObject,
          let coordinate = location.value(forKey: "coordinate") as? NSValue
    else { return nil }

    // CLLocationCoordinate2D: latitude then longitude, two doubles.
    var degrees = (0.0, 0.0)
    coordinate.getValue(&degrees, size: MemoryLayout<(Double, Double)>.size)
    let accuracy = (location.value(forKey: "horizontalAccuracy") as? NSNumber)?.doubleValue ?? -1
    guard accuracy >= 0, abs(degrees.0) <= 90, abs(degrees.1) <= 180 else { return nil }

    let time = (location.value(forKey: "timestamp") as? Date) ?? Date()
    return [
      "lat": degrees.0,
      "long": degrees.1,
      "accuracy": accuracy,
      "time": Int(time.timeIntervalSince1970 * 1000),
    ]
  }
}
