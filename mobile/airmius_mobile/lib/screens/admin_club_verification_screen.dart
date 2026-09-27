import 'package:flutter/material.dart';

import 'platform_admin_screen.dart';

class AdminClubVerificationScreen extends StatelessWidget {
  const AdminClubVerificationScreen({super.key});

  @override
  Widget build(BuildContext context) =>
      const PlatformAdminScreen(initialSection: 'clubs');
}
