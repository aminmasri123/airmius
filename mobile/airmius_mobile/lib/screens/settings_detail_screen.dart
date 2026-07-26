import 'package:flutter/material.dart';

import 'settings_center_screen.dart';

/// Compatibility entry point for legacy settings deep links.
///
/// Account, notification, privacy and deletion actions are maintained in the
/// API-backed settings centre instead of the former local-only detail form.
class SettingsDetailScreen extends StatelessWidget {
  const SettingsDetailScreen({
    super.key,
    required this.section,
    required this.status,
  });

  final String section;
  final String status;

  @override
  Widget build(BuildContext context) => const SettingsCenterScreen();
}
