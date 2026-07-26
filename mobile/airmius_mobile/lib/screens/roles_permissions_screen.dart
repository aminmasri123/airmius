import 'package:flutter/material.dart';

import 'platform_admin_screen.dart';

/// Compatibility entry point for the legacy roles module.
///
/// Roles and permissions are loaded and changed through the platform admin API
/// so that every action is checked against the current account abilities.
class RolesPermissionsScreen extends StatelessWidget {
  const RolesPermissionsScreen({super.key});

  @override
  Widget build(BuildContext context) =>
      const PlatformAdminScreen(initialSection: 'roles');
}
