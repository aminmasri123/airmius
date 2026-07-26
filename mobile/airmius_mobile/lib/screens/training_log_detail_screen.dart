import 'package:flutter/material.dart';

import 'training_plans_logs_screen.dart';

/// Compatibility route for legacy training-log links.
class TrainingLogDetailScreen extends StatelessWidget {
  const TrainingLogDetailScreen({
    super.key,
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
  });

  final String title;
  final String body;
  final String status;
  final IconData icon;

  @override
  Widget build(BuildContext context) => const TrainingPlansLogsScreen();
}
