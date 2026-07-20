import 'package:flutter/services.dart';

class AirmiusExternalAuthLauncher {
  const AirmiusExternalAuthLauncher({
    this._channel = const MethodChannel('com.airmius.app/browser'),
  });

  final MethodChannel _channel;

  Future<void> open(String url) async {
    await _channel.invokeMethod<void>('open', url);
  }
}
