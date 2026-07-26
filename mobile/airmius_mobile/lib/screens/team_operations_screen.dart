import 'package:flutter/material.dart';

import 'teams_center_screen.dart';

/// Legacy entry point for team operations. The team center is the API-backed
/// source of truth for rosters, roles, invitations and team details.
class TeamOperationsScreen extends StatelessWidget {
  const TeamOperationsScreen({super.key});

  @override
  Widget build(BuildContext context) => const TeamsCenterScreen();
}
