import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_module_access.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/admin_access_denied_screen.dart';
import 'admin_backoffice_screen.dart';
import 'admin_members_screen.dart';
import 'admin_media_screen.dart';
import 'admin_clubs_screen.dart';
import 'admin_insights_screen.dart';
import 'admin_trainer_applications_screen.dart';
import 'admin_commerce_operations_screen.dart';
import 'admin_mail_center_screen.dart';
import 'admin_platform_settings_screen.dart';
import 'editorial_management_screen.dart';
import 'outfit_operations_screen.dart';
import 'platform_admin_screen.dart';
import 'sponsor_management_screen.dart';
import 'admin_support_ticket_screen.dart';

class AdminWorkspaceScreen extends StatelessWidget {
  const AdminWorkspaceScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final user = AirmiusServicesScope.of(context).authState.user;
    if (!AirmiusModuleAccess.canOpenAdmin(user)) {
      return const AdminAccessDeniedScreen();
    }
    final t = AirmiusScope.of(context).t;
    bool can(String permission) => user?.can(permission) == true;
    final entries = <(String, IconData, Widget)>[
      if (can('users.view'))
        ('platformAdmin.users', Icons.people_outline, const AdminMembersScreen()),
      if (can('admin.operations.view'))
        (
          'adminNative.operations',
          Icons.monitor_heart_outlined,
          const AdminInsightsScreen(),
        ),
      if (can('analytics.view'))
        (
          'adminNative.analytics',
          Icons.analytics_outlined,
          const AdminInsightsScreen(analytics: true),
        ),
      if (can('system.manage')) ...[
        (
          'adminNative.trainerApplications',
          Icons.sports_outlined,
          const AdminTrainerApplicationsScreen(),
        ),
        (
          'platformAdmin.club',
          Icons.apartment_outlined,
          const AdminClubsScreen(),
        ),
        (
          'platformAdmin.clubReview',
          Icons.verified_outlined,
          const PlatformAdminScreen(initialSection: 'clubs'),
        ),
        (
          'platformAdmin.sports',
          Icons.sports_outlined,
          const PlatformAdminScreen(initialSection: 'sports'),
        ),
        (
          'platformAdmin.badges',
          Icons.workspace_premium_outlined,
          const PlatformAdminScreen(initialSection: 'badges'),
        ),
        (
          'platformAdmin.gamification',
          Icons.emoji_events_outlined,
          const PlatformAdminScreen(initialSection: 'gamification'),
        ),
      ],
      if (AirmiusModuleAccess.canOpenPlatformSection(user, 'roles'))
        (
          'platformAdmin.roles',
          Icons.admin_panel_settings_outlined,
          const PlatformAdminScreen(initialSection: 'roles'),
        ),
      if (AirmiusModuleAccess.canOpenPlatformSection(user, 'moderation'))
        (
          'platformAdmin.moderation',
          Icons.fact_check_outlined,
          const PlatformAdminScreen(initialSection: 'moderation'),
        ),
      if (can('subscriptions.manage') ||
          can('marketplace.manage') ||
          can('system.manage'))
        (
          'commerceOps.open',
          Icons.storefront_outlined,
          const AdminCommerceOperationsScreen(),
        ),
      if (can('subscriptions.manage') ||
          can('billing.manage') ||
          can('finance.view') ||
          can('finance.edit') ||
          can('system.manage'))
        (
          'backoffice.title',
          Icons.account_balance_wallet_outlined,
          const AdminBackofficeScreen(),
        ),
      if (can('outfit-subscriptions.manage'))
        (
          'outfitAdmin.title',
          Icons.checkroom_outlined,
          const OutfitOperationsScreen(),
        ),
      if (can('subscriptions.manage'))
        ('backoffice.subscriptions', Icons.subscriptions_outlined, const AdminBackofficeScreen(initialSection: 'subscriptions')),
      if (can('billing.manage'))
        ('backoffice.billing', Icons.receipt_long_outlined, const AdminBackofficeScreen(initialSection: 'billing')),
      if (can('finance.view') || can('billing.manage'))
        ('backoffice.contracts', Icons.description_outlined, const AdminBackofficeScreen(initialSection: 'contracts')),
      if (can('blog.view'))
        ('adminNative.media', Icons.image_outlined, const AdminMediaScreen()),
      if (can('blog.view') || can('blog.manage'))
        (
          'editorial.title',
          Icons.article_outlined,
          const EditorialManagementScreen(),
        ),
      if (can('sponsors.view'))
        (
          'sponsorAdmin.title',
          Icons.handshake_outlined,
          const SponsorManagementScreen(),
        ),
      if (can('support.tickets'))
        (
          'support.title',
          Icons.support_agent_outlined,
          const AdminSupportTicketScreen(),
        ),
      if (can('system.manage')) ...[
        ('systemAdmin.providers', Icons.cloud_outlined, const AdminPlatformSettingsScreen(initialSection: 'providers')),
        ('mailAdmin.title', Icons.mail_outline, const AdminMailCenterScreen()),
        (
          'systemAdmin.title',
          Icons.settings_outlined,
          const AdminPlatformSettingsScreen(),
        ),
      ],
    ];
    return Scaffold(
      appBar: AppBar(title: Text(t('platformAdmin.title'))),
      body: ListView.separated(
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: entries.length,
        separatorBuilder: (_, _) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final entry = entries[index];
          return ListTile(
            leading: Icon(entry.$2),
            title: Text(t(entry.$1)),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.of(
              context,
            ).push(MaterialPageRoute<void>(builder: (_) => entry.$3)),
          );
        },
      ),
    );
  }
}
