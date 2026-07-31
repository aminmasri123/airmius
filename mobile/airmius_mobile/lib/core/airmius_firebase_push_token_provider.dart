import 'package:firebase_messaging/firebase_messaging.dart';

import 'airmius_push_notifications.dart';
import 'airmius_push_device_registry.dart';

class AirmiusFirebasePushTokenProvider implements AirmiusPushTokenProvider {
  AirmiusFirebasePushTokenProvider();

  Future<FirebaseMessaging> _messaging() async {
    await AirmiusPushNotifications.initialize();
    return FirebaseMessaging.instance;
  }

  @override
  Future<AirmiusPushToken?> requestToken() async {
    final messaging = await _messaging();
    final permission = await messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );
    if (permission.authorizationStatus == AuthorizationStatus.denied) {
      return null;
    }
    return _token(messaging);
  }

  @override
  Future<AirmiusPushToken?> currentToken() async {
    final messaging = await _messaging();
    final settings = await messaging.getNotificationSettings();
    if (settings.authorizationStatus == AuthorizationStatus.denied ||
        settings.authorizationStatus == AuthorizationStatus.notDetermined) {
      return null;
    }
    return _token(messaging);
  }

  Future<AirmiusPushToken?> _token(FirebaseMessaging messaging) async {
    final token = (await messaging.getToken())?.trim() ?? '';
    if (token.isEmpty) return null;
    return AirmiusPushToken(token: token, provider: 'fcm');
  }
}
