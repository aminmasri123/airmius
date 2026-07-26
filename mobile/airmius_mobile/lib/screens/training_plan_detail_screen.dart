import 'package:flutter/material.dart';

import 'training_plans_logs_screen.dart';

/// Compatibility route for legacy coach-action links.
///
/// Plans and logs are now loaded and edited through the API-backed hub. The
/// old detail route accepted display-only demo data, so it forwards to the
/// real workspace while preserving the public constructor used by old links.
class TrainingPlanDetailScreen extends StatelessWidget {
  const TrainingPlanDetailScreen({
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
