import 'package:flutter/material.dart';

import '../core/airmius_services_scope.dart';
import 'admin_support_ticket_screen.dart';
import 'support_helpdesk_screen.dart';

/// Compatibility entry point for legacy operations navigation.
///
/// The former screen was a UI-only preview. Keep the public class name so
/// existing navigation and deep links continue to work, but route users to
/// the real, API-backed support helpdesk.
class SupportTicketServiceCenterSuiteScreen extends StatelessWidget {
  const SupportTicketServiceCenterSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final user = AirmiusServicesScope.of(context).authState.user;
    return user?.can('support.tickets') == true
        ? const AdminSupportTicketScreen()
        : const SupportHelpdeskScreen();
  }
}
