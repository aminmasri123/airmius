import 'package:flutter/material.dart';

import 'platform_admin_screen.dart';

/// Compatibility entry point for the legacy "Nutzer" module.
///
/// User administration must use the same permission-scoped API surface as the
/// platform admin area. Keeping a separate demo list here used to expose
/// misleading sample users and allowed the two views to drift apart.
class UsersCenterScreen extends StatelessWidget {
  const UsersCenterScreen({super.key});

  @override
  Widget build(BuildContext context) =>
      const PlatformAdminScreen(initialSection: 'users');
}
