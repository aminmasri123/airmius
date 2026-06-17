import 'package:flutter/services.dart';

class AirmiusExternalAuthLauncher {
  const AirmiusExternalAuthLauncher({
    MethodChannel channel = const MethodChannel('com.airmius.app/browser'),
  }) : _channel = channel;

  final MethodChannel _channel;

  Future<void> open(String url) async {
    await _channel.invokeMethod<void>('open', url);
  }
}
