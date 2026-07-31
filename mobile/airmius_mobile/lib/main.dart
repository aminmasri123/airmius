import 'package:flutter/material.dart';

import 'airmius_app.dart';
import 'core/airmius_push_notifications.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  try {
    await AirmiusPushNotifications.initialize();
  } catch (_) {
    // Firebase errors must not prevent the app from opening.
  }
  runApp(const AirmiusApp());
}
