// swift-tools-version: 5.9
// Swift packages of Carillon for iOS apps, pinned to the revision of
// carillon_flutter (https://github.com/TelKine-Studio/carillon):
// - CarillonExtension: the notification service extension (images, buttons,
//   receptions), for the extension target;
// - CarillonLiveActivity: the standard Live Activity and its ready-made
//   widget, for the widget extension and the app.

import PackageDescription

let package = Package(
    name: "CarillonExtension",
    platforms: [.iOS(.v13)],
    products: [
        .library(name: "CarillonExtension", targets: ["CarillonExtension"]),
        .library(name: "CarillonLiveActivity", targets: ["CarillonLiveActivity"]),
    ],
    targets: [
        .target(
            name: "CarillonExtension",
            path: "sdk/carillon_flutter/ios/CarillonExtension/Sources/CarillonExtension"
        ),
        .target(
            name: "CarillonLiveActivity",
            path: "sdk/carillon_flutter/ios/CarillonLiveActivity/Sources/CarillonLiveActivity"
        ),
    ]
)
