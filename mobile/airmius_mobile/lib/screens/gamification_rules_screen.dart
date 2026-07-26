import 'package:flutter/material.dart';

import 'platform_admin_screen.dart';

/// Compatibility entry point for the legacy gamification rules module.
///
/// The platform admin dashboard is the single source of truth for badges and
/// gamification rules; it removes the old hard-coded sample rules.
class GamificationRulesScreen extends StatelessWidget {
  const GamificationRulesScreen({super.key});

  @override
  Widget build(BuildContext context) =>
      const PlatformAdminScreen(initialSection: 'gamification');
}
