import 'package:flutter/material.dart';

import 'sponsor_management_screen.dart';

/// Legacy entry point for sponsor and advertising operations.
class SponsorAdsOperationsScreen extends StatelessWidget {
  const SponsorAdsOperationsScreen({super.key, this.initialTab = 'Sponsoren'});

  final String initialTab;

  @override
  Widget build(BuildContext context) => const SponsorManagementScreen();
}
