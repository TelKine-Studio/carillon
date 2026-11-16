// swift-tools-version: 5.9
// Swift package of the Carillon notification service extension (iOS): add
// https://github.com/TelKine-Studio/carillon to the extension target of your
// app, pinned to the revision of carillon_flutter, product CarillonExtension.

import PackageDescription

let package = Package(
    name: "CarillonExtension",
    platforms: [.iOS(.v13)],
    products: [
        .library(name: "CarillonExtension", targets: ["CarillonExtension"]),
    ],
    targets: [
        .target(
            name: "CarillonExtension",
            path: "sdk/carillon_flutter/ios/CarillonExtension/Sources/CarillonExtension"
        ),
    ]
)
