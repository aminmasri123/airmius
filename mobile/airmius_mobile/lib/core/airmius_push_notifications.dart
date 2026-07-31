import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../firebase_options.dart';

/// FCM calls this function from a separate Dart isolate for background data.
/// Server messages also contain a notification payload, so Android/iOS render
/// them natively while the Flutter process is stopped or the screen is locked.
@pragma('vm:entry-point')
Future<void> airmiusFirebaseMessagingBackgroundHandler(
  RemoteMessage message,
) async {
  if (Firebase.apps.isEmpty) {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
  }
}

class AirmiusPushNotifications {
  const AirmiusPushNotifications._();

  static bool get supported =>
      !kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.android ||
          defaultTargetPlatform == TargetPlatform.iOS);

  static Future<void> initialize() async {
    if (!supported) return;

    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp(
        options: DefaultFirebaseOptions.currentPlatform,
      );
    }

    FirebaseMessaging.onBackgroundMessage(
      airmiusFirebaseMessagingBackgroundHandler,
    );

    await FirebaseMessaging.instance.setForegroundNotificationPresentationOptions(
      alert: true,
      badge: true,
      sound: true,
    );
  }

  static String? deepLinkFor(RemoteMessage message) {
    final value = message.data['deep_link']?.toString().trim();
    return value == null || value.isEmpty ? null : value;
  }
}
