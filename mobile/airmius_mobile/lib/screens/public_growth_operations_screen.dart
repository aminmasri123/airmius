import 'package:flutter/material.dart';

import 'guest_portal_screen.dart';

/// Compatibility route for the former public-growth demo screen.
///
/// Lead counts, qualification and follow-up actions require a protected
/// server workflow. Until that API surface exists, this route only exposes
/// the guest-safe public catalogue and contact entry points.
class PublicGrowthOperationsScreen extends StatelessWidget {
  const PublicGrowthOperationsScreen({super.key, this.initialTab = 'Leads'});

  final String initialTab;

  @override
  Widget build(BuildContext context) {
    return const GuestPortalScreen();
  }
}
