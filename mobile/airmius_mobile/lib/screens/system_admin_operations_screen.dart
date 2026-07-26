import 'package:flutter/material.dart';

import 'admin_platform_settings_screen.dart';

/// Compatibility entry for older navigation targets.
///
/// The former screen contained only prepared demo actions. All callers now
/// land in the real, permission-protected system and provider administration.
class SystemAdminOperationsScreen extends StatelessWidget {
  const SystemAdminOperationsScreen({super.key, this.initialTab = 'system'});

  final String initialTab;

  @override
  Widget build(BuildContext context) {
    return const AdminPlatformSettingsScreen();
  }
}
