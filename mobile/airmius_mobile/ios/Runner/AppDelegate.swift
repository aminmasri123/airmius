import Flutter
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  private let deepLinkChannelName = "com.airmius.app/deep_links"
  private let browserChannelName = "com.airmius.app/browser"
  private var initialLink: String?
  private var deepLinkChannel: FlutterMethodChannel?
  private var browserChannel: FlutterMethodChannel?

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    if let url = launchOptions?[.url] as? URL {
      initialLink = url.absoluteString
    }

    let result = super.application(application, didFinishLaunchingWithOptions: launchOptions)
    configureDeepLinkChannel()
    return result
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)
  }

  override func application(
    _ app: UIApplication,
    open url: URL,
    options: [UIApplication.OpenURLOptionsKey : Any] = [:]
  ) -> Bool {
    openDeepLink(url.absoluteString)
    return true
  }

  override func application(
    _ application: UIApplication,
    continue userActivity: NSUserActivity,
    restorationHandler: @escaping ([UIUserActivityRestoring]?) -> Void
  ) -> Bool {
    guard userActivity.activityType == NSUserActivityTypeBrowsingWeb,
          let url = userActivity.webpageURL else {
      return false
    }

    openDeepLink(url.absoluteString)
    return true
  }

  private func configureDeepLinkChannel() {
    guard deepLinkChannel == nil,
          let controller = window?.rootViewController as? FlutterViewController else {
      return
    }

    let channel = FlutterMethodChannel(
      name: deepLinkChannelName,
      binaryMessenger: controller.binaryMessenger
    )
    channel.setMethodCallHandler { [weak self] call, result in
      guard call.method == "getInitialLink" else {
        result(FlutterMethodNotImplemented)
        return
      }
      result(self?.initialLink)
    }
    deepLinkChannel = channel

    let browser = FlutterMethodChannel(
      name: browserChannelName,
      binaryMessenger: controller.binaryMessenger
    )
    browser.setMethodCallHandler { call, result in
      guard call.method == "open" else {
        result(FlutterMethodNotImplemented)
        return
      }
      guard let rawUrl = call.arguments as? String,
            let url = URL(string: rawUrl) else {
        result(FlutterError(code: "invalid_url", message: "No valid URL was provided.", details: nil))
        return
      }
      UIApplication.shared.open(url)
      result(nil)
    }
    browserChannel = browser
  }

  private func openDeepLink(_ link: String) {
    initialLink = link
    configureDeepLinkChannel()
    deepLinkChannel?.invokeMethod("openDeepLink", arguments: link)
  }
}
