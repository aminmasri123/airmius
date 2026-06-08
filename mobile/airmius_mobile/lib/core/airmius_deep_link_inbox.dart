import 'package:flutter/services.dart';

class AirmiusDeepLinkInbox {
  AirmiusDeepLinkInbox({
    MethodChannel channel = const MethodChannel('com.airmius.app/deep_links'),
  }) : _channel = channel;

  final MethodChannel _channel;

  void start(void Function(String link) onLink) {
    _channel.setMethodCallHandler((call) async {
      if (call.method != 'openDeepLink') {
        return null;
      }
      final link = call.arguments?.toString().trim();
      if (link == null || link.isEmpty) {
        return null;
      }
      onLink(link);
      return null;
    });
  }

  Future<void> restoreInitialLink(void Function(String link) onLink) async {
    final link = await _channel.invokeMethod<String>('getInitialLink');
    final normalized = link?.trim();
    if (normalized == null || normalized.isEmpty) {
      return;
    }
    onLink(normalized);
  }
}
