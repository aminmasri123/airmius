import 'package:flutter/material.dart';

import 'platform_admin_screen.dart';

/// Legacy entry point for trust, moderation and verification operations.
class TrustOperationsScreen extends StatelessWidget {
  const TrustOperationsScreen({super.key, this.initialTab = 'Verifizierung'});

  final String initialTab;

  @override
  Widget build(BuildContext context) => const PlatformAdminScreen();
}
