import 'package:flutter/material.dart';

import 'club_membership_management_screen.dart';

/// Legacy route for contribution rules.
///
/// Contribution rules are edited in the same permission-scoped management
/// surface as membership types and invoices, so settings cannot drift between
/// two separate mobile implementations.
class ClubContributionRulesScreen extends StatelessWidget {
  const ClubContributionRulesScreen({super.key, this.initialTab = 'Regeln'});

  final String initialTab;

  @override
  Widget build(BuildContext context) {
    return const ClubMembershipManagementScreen();
  }
}
