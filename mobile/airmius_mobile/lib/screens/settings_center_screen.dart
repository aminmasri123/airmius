import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_preferences.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'account_management_screen.dart';
import 'member_invoices_screen.dart';
import 'clubs_screen.dart';
import 'guardian_center_screen.dart';
import 'sport_integrations_screen.dart';
import 'legal_status_center_screen.dart';
import 'notification_preferences_screen.dart';
import 'footer_navigation_settings_screen.dart';
import 'privacy_consent_center_screen.dart';
import 'support_helpdesk_screen.dart';
import 'settings_preference_screens.dart';

class SettingsCenterScreen extends StatelessWidget {
  const SettingsCenterScreen({
    super.key,
    this.preferences,
    this.onFooterNavigationChanged,
  });

  final AirmiusPreferences? preferences;
  final VoidCallback? onFooterNavigationChanged;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final t = scope.t;
    final user = AirmiusServicesScope.of(context).authState.user;
    final canOpenGuardianCenter =
        user?.hasAnyRole(const ['guardian', 'parent']) == true ||
        user?.can('guardians.children.view') == true;
    final accent = Theme.of(context).colorScheme.primary;
    final text = airmiusTextColor(context);
    final muted = airmiusMutedColor(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('settings.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t('settings.title'),
        subtitle: t('settings.subtitle'),
        trailing: StatusPill(scope.language.code, color: accent),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('settings.accountEyebrow')),
                  const SizedBox(height: 8),
                  Text(
                    t('settings.headline'),
                    style: TextStyle(
                      color: text,
                      fontSize: 23,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    t('settings.body'),
                    style: TextStyle(color: muted, height: 1.4),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: MetricCard(
                          value: user?.emailVerified == true
                              ? t('settings.yes')
                              : t('settings.no'),
                          label: t('settings.emailVerified'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: MetricCard(
                          value: user?.twoFactorEnabled == true
                              ? t('settings.active')
                              : t('settings.off'),
                          label: t('settings.twoFactor'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _SettingsSection(
              title: t('settings.sectionAccount'),
              children: [
                _SettingsAction(
                  icon: Icons.receipt_long_outlined,
                  title: scope.language == AirmiusLanguage.de
                      ? 'Meine Rechnungen'
                      : 'My invoices',
                  body: '',
                  status: t('settings.open'),
                  color: accent,
                  onTap: () => _open(context, const MemberInvoicesScreen()),
                ),
                _SettingsAction(
                  icon: Icons.palette_outlined,
                  title: t('accessibility.design'),
                  body: t('accessibility.designDescription'),
                  status: t('settings.open'),
                  color: accent,
                  onTap: () => _open(context, const SettingsAppearanceScreen()),
                ),
                _SettingsAction(
                  icon: Icons.language_outlined,
                  title: t('settings.language'),
                  body: t('settings.languageBody'),
                  status: scope.language.code.toUpperCase(),
                  color: accent,
                  onTap: () => _open(context, const SettingsLanguageScreen()),
                ),
                _SettingsAction(
                  icon: Icons.view_week_outlined,
                  title: t('footerNav.settingsTitle'),
                  body: t('footerNav.settingsBody'),
                  status: t('footerNav.itemRange'),
                  color: accent,
                  onTap: () => _openFooterNavigation(context),
                ),
                _SettingsAction(
                  icon: Icons.manage_accounts_outlined,
                  title: t('settings.account'),
                  body: t('settings.accountBody'),
                  status: t('settings.protected'),
                  color: accent,
                  onTap: () => _open(context, const AccountManagementScreen()),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _SettingsSection(
              title: t('settings.sectionTools'),
              children: [
                _SettingsAction(
                  icon: Icons.notifications_active_outlined,
                  title: t('settings.notifications'),
                  body: t('settings.notificationsBody'),
                  status: t('settings.notificationStatus'),
                  color: accent,
                  onTap: () =>
                      _open(context, const NotificationPreferencesScreen()),
                ),
                _SettingsAction(
                  icon: Icons.watch_outlined,
                  title: t('fitness.title'),
                  body: t('fitness.subtitle'),
                  status: t('fitness.importSupport'),
                  color: accent,
                  onTap: () => _open(context, const SportIntegrationsScreen()),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _SettingsSection(
              title: t('settings.sectionClubs'),
              children: [
                _SettingsAction(
                  icon: Icons.groups_2_outlined,
                  title: t('settings.clubs'),
                  body: t('settings.clubsBody'),
                  status: t('settings.public'),
                  color: accent,
                  onTap: () => _openClubs(context),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _SettingsSection(
              title: t('settings.sectionPrivacy'),
              children: [
                _SettingsAction(
                  icon: Icons.privacy_tip_outlined,
                  title: t('settings.privacy'),
                  body: t('settings.privacyBody'),
                  status: t('settings.dataRights'),
                  color: Theme.of(context).colorScheme.secondary,
                  onTap: () =>
                      _open(context, const PrivacyConsentCenterScreen()),
                ),
                if (canOpenGuardianCenter)
                  _SettingsAction(
                    icon: Icons.family_restroom_outlined,
                    title: t('guardian.title'),
                    body: t('settings.guardianBody'),
                    status: t('settings.family'),
                    color: accent,
                    onTap: () => _open(context, const GuardianCenterScreen()),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            _SettingsSection(
              title: t('settings.sectionSupport'),
              children: [
                _SettingsAction(
                  icon: Icons.support_agent_outlined,
                  title: t('settings.support'),
                  body: t('settings.supportBody'),
                  status: t('settings.contact'),
                  color: Theme.of(context).colorScheme.tertiary,
                  onTap: () => _open(context, const SupportHelpdeskScreen()),
                ),
                _SettingsAction(
                  icon: Icons.gavel_outlined,
                  title: t('settings.legal'),
                  body: t('settings.legalBody'),
                  status: t('settings.public'),
                  color: accent,
                  onTap: () => _open(context, const LegalStatusCenterScreen()),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.verified_user_outlined, color: accent),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      t('settings.securityNote'),
                      style: TextStyle(color: muted, height: 1.4),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _open(BuildContext context, Widget screen) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => screen));
  }

  Future<void> _openFooterNavigation(BuildContext context) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) =>
            FooterNavigationSettingsScreen(preferences: preferences),
      ),
    );
    if (changed == true) onFooterNavigationChanged?.call();
  }

  void _openClubs(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ClubsScreen(
          requestedClubIds: const {},
          onRequestClub: (_) {},
          onWithdrawClub: (_) {},
          showAppBar: true,
        ),
      ),
    );
  }
}

class _SettingsSection extends StatelessWidget {
  const _SettingsSection({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Eyebrow(title),
        const SizedBox(height: 8),
        ...children
            .expand<Widget>((widget) => [widget, const SizedBox(height: 12)])
            .take(children.isEmpty ? 0 : children.length * 2 - 1),
      ],
    );
  }
}

class _SettingsAction extends StatelessWidget {
  const _SettingsAction({
    required this.icon,
    required this.title,
    required this.body,
    required this.status,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String body;
  final String status;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = airmiusTextColor(context);
    final muted = airmiusMutedColor(context);
    return AirmiusPanel(
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon: icon, color: color),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        color: text,
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(body, style: TextStyle(color: muted, height: 1.35)),
                    const SizedBox(height: 8),
                    StatusPill(status, color: color),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Icon(Icons.chevron_right, color: muted),
            ],
          ),
        ),
      ),
    );
  }
}
