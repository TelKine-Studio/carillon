// Notification Service Extension of a Carillon app.
//
// Recommended: add the Swift package https://github.com/TelKine-Studio/carillon
// (product CarillonExtension) to the extension target, pinned to the same
// revision as carillon_flutter in pubspec.yaml, then keep only:
//
//     import CarillonExtension
//     class NotificationService: CarillonNotificationService {}
//
// The extension then follows the SDK: no copied file to keep up to date.
// Without the package, copy the content of
// ios/CarillonExtension/Sources/CarillonExtension/CarillonNotificationService.swift
// here instead and rename the class NotificationService.

import CarillonExtension

class NotificationService: CarillonNotificationService {}
