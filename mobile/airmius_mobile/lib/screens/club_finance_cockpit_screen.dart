import 'package:flutter/material.dart';

import 'club_membership_management_screen.dart';

/// Compatibility entry point for older navigation links.
///
/// The former cockpit contained illustrative finance rows. The complete
/// membership management screen is the single source of truth now: it loads
/// scoped invoices, payments, finance entries and contribution rules from the
/// API and keeps all write actions behind the server permissions.
class ClubFinanceCockpitScreen extends StatelessWidget {
  const ClubFinanceCockpitScreen({super.key, this.initialTab = 'Offen'});

  final String initialTab;

  @override
  Widget build(BuildContext context) {
    return const ClubMembershipManagementScreen();
  }
}
