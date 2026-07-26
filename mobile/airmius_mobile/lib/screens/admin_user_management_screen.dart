import 'package:flutter/material.dart';

import 'platform_admin_screen.dart';

/// Compatibility route for older user-management links.
///
/// User records, roles and account status must come from the protected admin
/// API. The former local list was only a demo and has intentionally been
/// removed from the reachable application surface.
class AdminUserManagementScreen extends StatelessWidget {
  const AdminUserManagementScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const PlatformAdminScreen(initialSection: 'users');
  }
}
