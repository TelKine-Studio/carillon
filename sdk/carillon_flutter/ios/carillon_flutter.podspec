#
# To learn more about a Podspec see http://guides.cocoapods.org/syntax/podspec.html.
# Run `pod lib lint carillon_flutter.podspec` to validate before publishing.
#
Pod::Spec.new do |s|
  s.name             = 'carillon_flutter'
  s.version          = '0.1.0'
  s.summary          = 'Carillon push notifications for Flutter (iOS part).'
  s.description      = <<-DESC
Native APNs registration and notification handling for the Carillon Flutter SDK.
                       DESC
  s.homepage         = 'https://github.com/TelKine-Studio/carillon'
  s.license          = { :type => 'MIT', :file => '../LICENSE' }
  s.author           = { 'TelKine Studio' => 'https://github.com/TelKine-Studio' }
  s.source           = { :path => '.' }
  s.source_files = 'carillon_flutter/Sources/carillon_flutter/**/*'
  s.dependency 'Flutter'
  s.platform = :ios, '15.0'

  # Flutter.framework does not contain a i386 slice.
  s.pod_target_xcconfig = { 'DEFINES_MODULE' => 'YES', 'EXCLUDED_ARCHS[sdk=iphonesimulator*]' => 'i386' }
  s.swift_version = '5.0'

  # If your plugin requires a privacy manifest, for example if it uses any
  # required reason APIs, update the PrivacyInfo.xcprivacy file to describe your
  # plugin's privacy impact, and then uncomment this line. For more information,
  # see https://developer.apple.com/documentation/bundleresources/privacy_manifest_files
  # s.resource_bundles = {'carillon_flutter_privacy' => ['carillon_flutter/Sources/carillon_flutter/PrivacyInfo.xcprivacy']}
end
