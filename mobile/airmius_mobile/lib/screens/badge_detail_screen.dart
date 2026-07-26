import 'package:flutter/material.dart';

import 'badges_center_screen.dart';

/// Compatibility route for legacy badge links.
///
/// Badge data, progress and detail dialogs are owned by the API-backed badge
/// center. Keeping one source avoids showing fabricated XP or achievement
/// history when a profile link is opened.
class BadgeDetailScreen extends StatelessWidget {
  const BadgeDetailScreen({
    super.key,
    required this.title,
    required this.body,
    required this.status,
  });

  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) => const BadgesCenterScreen();
}
