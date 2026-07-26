import 'package:flutter/material.dart';

import 'conversations_center_screen.dart';
import 'notifications_center_screen.dart';

/// Routes legacy Inbox/Push links to the corresponding real API surface.
class NotificationChatOperationsScreen extends StatelessWidget {
  const NotificationChatOperationsScreen({
    super.key,
    this.initialTab = 'Inbox',
  });

  final String initialTab;

  @override
  Widget build(BuildContext context) {
    final normalized = initialTab.toLowerCase();
    final notifications =
        normalized.contains('push') ||
        normalized.contains('notification') ||
        normalized.contains('benach');
    return notifications
        ? const NotificationsCenterScreen()
        : const ConversationsCenterScreen();
  }
}
