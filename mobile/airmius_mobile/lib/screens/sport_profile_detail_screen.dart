import 'package:flutter/material.dart';

import 'sports_center_screen.dart';

/// Compatibility entry point for old profile deep links.
///
/// Sport profiles are managed in the API-backed sports centre now. Keeping
/// this wrapper preserves existing navigation/deep links without exposing the
/// former local-only detail form.
class SportProfileDetailScreen extends StatelessWidget {
  const SportProfileDetailScreen({
    super.key,
    required this.title,
    required this.status,
  });

  final String title;
  final String status;

  @override
  Widget build(BuildContext context) => const SportsCenterScreen();
}
