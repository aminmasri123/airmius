import 'package:flutter/material.dart';

import 'club_membership_management_screen.dart';

/// Keeps legacy directory routes usable while delegating to the real,
/// permission-scoped member management implementation.
class ClubMemberDirectoryScreen extends StatelessWidget {
  const ClubMemberDirectoryScreen({super.key, this.initialTab = 'Aktiv'});

  final String initialTab;

  @override
  Widget build(BuildContext context) {
    return const ClubMembershipManagementScreen();
  }
}
