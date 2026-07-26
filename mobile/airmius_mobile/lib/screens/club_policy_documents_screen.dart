import 'package:flutter/material.dart';

import 'club_membership_management_screen.dart';

/// Compatibility entry point for club policies and documents.
///
/// The membership management screen now loads the actual club-scoped document
/// links and application rules from the protected API. Keeping a second page
/// with hard-coded policy rows would be misleading and could not persist the
/// switches it displayed.
class ClubPolicyDocumentsScreen extends StatelessWidget {
  const ClubPolicyDocumentsScreen({super.key, this.initialTab = 'Dokumente'});

  final String initialTab;

  @override
  Widget build(BuildContext context) => const ClubMembershipManagementScreen();
}
