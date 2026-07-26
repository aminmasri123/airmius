import 'package:flutter/material.dart';

import 'teams_center_screen.dart';

/// Legacy team-management route backed by the real API-powered teams center.
class ClubTeamAdminScreen extends StatelessWidget {
  const ClubTeamAdminScreen({super.key, this.initialTab = 'Teams'});

  final String initialTab;

  @override
  Widget build(BuildContext context) {
    return const TeamsCenterScreen();
  }
}
