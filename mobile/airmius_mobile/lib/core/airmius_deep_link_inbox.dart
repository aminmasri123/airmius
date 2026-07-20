import 'package:flutter/services.dart';

class AirmiusDeepLinkInbox {
  AirmiusDeepLinkInbox({
    this._channel = const MethodChannel('com.airmius.app/deep_links'),
  });

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
    String? link;
    try {
      link = await _channel.invokeMethod<String>('getInitialLink');
    } on MissingPluginException {
      return;
    }
    final normalized = link?.trim();
    if (normalized == null || normalized.isEmpty) {
      return;
    }
    onLink(normalized);
  }
}
