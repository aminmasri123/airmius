import 'package:flutter/material.dart';

import 'platform_admin_screen.dart';

/// Legacy entry point for badges, XP and rules.
class GamificationOperationsScreen extends StatelessWidget {
  const GamificationOperationsScreen({super.key, this.initialTab = 'Badges'});

  final String initialTab;

  @override
  Widget build(BuildContext context) => const PlatformAdminScreen();
}
