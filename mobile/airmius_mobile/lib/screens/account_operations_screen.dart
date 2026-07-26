import 'package:flutter/material.dart';

import 'account_management_screen.dart';

/// Legacy entry point retained for deep links and older navigation actions.
class AccountOperationsScreen extends StatelessWidget {
  const AccountOperationsScreen({super.key, this.initialTab = 'Auth'});

  final String initialTab;

  @override
  Widget build(BuildContext context) => const AccountManagementScreen();
}
