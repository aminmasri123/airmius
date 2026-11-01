import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../screens/two_factor_security_screen.dart';

class AdminTwoFactorNotice extends StatelessWidget {
  const AdminTwoFactorNotice({
    super.key,
    required this.user,
    this.closeDrawer = false,
  });

  final AirmiusUser? user;
  final bool closeDrawer;

  @override
  Widget build(BuildContext context) {
    if (user == null ||
        user!.twoFactorEnabled ||
        !user!.hasAnyRole(const ['super_admin', 'admin', 'system_admin'])) {
      return const SizedBox.shrink();
    }
    final t = AirmiusScope.of(context).t;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Icon(Icons.security_outlined),
            const SizedBox(height: 8),
            Text(
              t('adminAccess.setupTitle'),
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Text(t('adminAccess.setupBody')),
            const SizedBox(height: 12),
            FilledButton.icon(
              icon: const Icon(Icons.verified_user_outlined),
              label: Text(t('adminAccess.setupAction')),
              onPressed: () {
                final navigator = Navigator.of(context);
                if (closeDrawer) navigator.pop();
                navigator.push(
                  MaterialPageRoute(
                    builder: (_) => const TwoFactorSecurityScreen(),
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
