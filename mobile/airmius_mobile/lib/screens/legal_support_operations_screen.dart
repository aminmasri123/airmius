import 'package:flutter/material.dart';

import 'legal_status_center_screen.dart';
import 'support_helpdesk_screen.dart';

/// Routes legacy legal/contact links to the real public document or support
/// flow instead of showing a non-persisted action card.
class LegalSupportOperationsScreen extends StatelessWidget {
  const LegalSupportOperationsScreen({super.key, this.initialTab = 'Legal'});

  final String initialTab;

  @override
  Widget build(BuildContext context) {
    final support =
        initialTab.toLowerCase().contains('support') ||
        initialTab.toLowerCase().contains('kontakt');
    return support
        ? const SupportHelpdeskScreen()
        : const LegalStatusCenterScreen();
  }
}
