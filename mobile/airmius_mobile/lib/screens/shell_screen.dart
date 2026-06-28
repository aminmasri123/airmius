import 'package:flutter/material.dart';

import 'dart:async';

import 'operations_hub_screen.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_services_scope.dart';
import '../models/app_tab.dart';
import '../models/club_summary.dart';
import '../models/module_definition.dart';
import '../widgets/airmius_widgets.dart';
import 'clubs_screen.dart';
import 'conversations_center_screen.dart';
import 'dashboard_screen.dart';
import 'feed_center_screen.dart';
import 'global_search_screen.dart';
import 'guest_portal_screen.dart';
import 'module_screen.dart';
import 'notifications_center_screen.dart';
import 'profile_screen.dart';
import 'settings_center_screen.dart';
import 'updates_center_screen.dart';
import 'workspace_center_screen.dart';
import 'teams_center_screen.dart';
import 'roles_permissions_screen.dart';
import 'sports_center_screen.dart';
import 'event_management_screen.dart';
import 'trainer_cockpit_screen.dart';
import 'nutrition_center_screen.dart';
import 'sport_map_center_screen.dart';
import 'friends_social_graph_screen.dart';
import 'carpool_center_screen.dart';
import 'file_manager_screen.dart';
import 'badges_center_screen.dart';
import 'gamification_rules_screen.dart';
import 'learning_screen.dart';
import 'marketplace_screen.dart';
import 'commerce_center_screen.dart';
import 'sponsors_center_screen.dart';
import 'media_guidelines_screen.dart';
import 'blog_media_center_screen.dart';
import 'users_center_screen.dart';
import 'subscription_center_screen.dart';
import 'guardian_center_screen.dart';
import 'maturity_center_screen.dart';
import 'outfit_subscription_center_screen.dart';
import 'admin_center_screen.dart';

class ShellScreen extends StatefulWidget {
  const ShellScreen({super.key});

  @override
  State<ShellScreen> createState() => _ShellScreenState();
}

class _ShellScreenState extends State<ShellScreen> {
  AppTab _tab = AppTab.dashboard;
  ModuleDefinition? _openedModule;
  final List<AppTab> _tabHistory = [];
  final Set<int> _requestedClubIds = {};
  int _notificationCount = 0;
  Timer? _notificationTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refreshNotificationCount());
    _notificationTimer = Timer.periodic(const Duration(seconds: 45), (_) => _refreshNotificationCount());
  }

  @override
  void dispose() {
    _notificationTimer?.cancel();
    super.dispose();
  }

  Future<void> _refreshNotificationCount() async {
    try {
      final page = await AirmiusServicesScope.of(context).repositories.notifications.notifications();
      if (!mounted) return;
      setState(() => _notificationCount = page.unreadCount ?? page.items.where((item) => item.unread).length);
    } catch (_) {
      // Badge refresh is best-effort; the notification center still shows its own error state.
    }
  }

  void _openTab(AppTab tab, {bool remember = true}) {
    if (_openedModule == null && _tab == tab) return;
    setState(() {
      if (remember) {
        _tabHistory.add(_tab);
      }
      _tab = tab;
      _openedModule = null;
    });
    if (tab == AppTab.updates) {
      _refreshNotificationCount();
    }
  }

  bool _handleBackNavigation() {
    if (_openedModule != null) {
      setState(() => _openedModule = null);
      return true;
    }

    if (_tabHistory.isNotEmpty) {
      final previousTab = _tabHistory.removeLast();
      _openTab(previousTab, remember: false);
      return true;
    }

    if (_tab != AppTab.dashboard) {
      _openTab(AppTab.dashboard, remember: false);
      return true;
    }

    return false;
  }

  void _openModule(ModuleDefinition module) {
    if (module.title == 'Feed') {
      _openTab(AppTab.feed);
      return;
    }
    if (module.title == 'Nachrichten') {
      _openTab(AppTab.updates);
      return;
    }
    if (module.title == 'Vereine & Teams' || module.title == 'Vereins-Cockpit') {
      _openTab(AppTab.clubs);
      return;
    }
    if (module.title == 'Einstellungen') {
      Navigator.push(context, MaterialPageRoute(builder: (_) => const SettingsCenterScreen()));
      return;
    }

    final screen = _screenForModule(module.title);
    if (screen != null) {
      Navigator.push(context, MaterialPageRoute(builder: (_) => screen));
      return;
    }

    setState(() => _openedModule = module);
  }

  Widget? _screenForModule(String title) {
    return switch (title) {
      'Arbeitsbereiche' => const WorkspaceCenterScreen(),
      'Teams' => const TeamsCenterScreen(),
      'Rollen & Rechte' => const RolesPermissionsScreen(),
      'Sportarten' => const SportsCenterScreen(),
      'Events & Training' => const EventManagementScreen(),
      'Trainer-Cockpit' => const TrainerCockpitScreen(),
      'Ernährung' => const NutritionCenterScreen(),
      'Sportkarte' => const SportMapCenterScreen(),
      'Freunde' => const FriendsSocialGraphScreen(),
      'Fahrgemeinschaften' => const CarpoolCenterScreen(),
      'Dateien' => const FileManagerScreen(),
      'Badges' => const BadgesCenterScreen(),
      'Gamification-Regeln' => const GamificationRulesScreen(),
      'Kurse' => const LearningScreen(),
      'Marketplace' => const MarketplaceScreen(),
      'Commerce' => const CommerceCenterScreen(),
      'Sponsoren' => const SponsorsCenterScreen(),
      'Medienrichtlinien' => const MediaGuidelinesScreen(),
      'Blog & Medien' => const BlogMediaCenterScreen(),
      'Nutzer' => const UsersCenterScreen(),
      'Abos & Rechnungen' => const SubscriptionCenterScreen(),
      'Eltern & Jugendschutz' => const GuardianCenterScreen(),
      'Altersfreigaben' => const MaturityCenterScreen(),
      'Outfit-Abos' => const OutfitSubscriptionCenterScreen(),
      'Admin' => const AdminCenterScreen(),
      _ => null,
    };
  }

  void _requestClub(ClubSummary club) {
    setState(() {
      _requestedClubIds.add(club.id);
      if (_tab != AppTab.updates) {
        _tabHistory.add(_tab);
      }
      _tab = AppTab.updates;
      _openedModule = null;
    });
    _refreshNotificationCount();
  }

  void _withdrawClub(ClubSummary club) {
    setState(() => _requestedClubIds.remove(club.id));
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final theme = Theme.of(context);
    final navBackground = _bottomNavBackground(context);
    final authState = AirmiusServicesScope.of(context).authState;
    final userLabel = _userInitials(
      firstName: authState.user?.firstName,
      lastName: authState.user?.lastName,
      name: authState.user?.name,
    );
    final page = _openedModule != null
        ? ModuleScreen(module: _openedModule!, requestedClubIds: _requestedClubIds, onWithdrawClub: _withdrawClub)
        : switch (_tab) {
            AppTab.dashboard => DashboardScreen(onOpenTab: _openTab, onOpenModule: _openModule, requestedClubIds: _requestedClubIds),
            AppTab.clubs => ClubsScreen(requestedClubIds: _requestedClubIds, onRequestClub: _requestClub, onWithdrawClub: _withdrawClub),
            AppTab.feed => const FeedCenterScreen(),
            AppTab.updates => UpdatesCenterScreen(requestedClubIds: _requestedClubIds, onWithdrawClub: _withdrawClub),
            AppTab.profile => const ProfileScreen(),
          };

    return WillPopScope(
      onWillPop: () async => !_handleBackNavigation(),
      child: Scaffold(
        backgroundColor: theme.scaffoldBackgroundColor,
        appBar: AirmiusTopBar(
          title: _openedModule == null ? scope.t(_tab.i18nKey) : scope.copy(_openedModule!.title),
          onLogoTap: () => _openTab(AppTab.feed),
          onSearch: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GlobalSearchScreen())),
          onMessages: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ConversationsCenterScreen())),
          onNotifications: () async {
            await Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationsCenterScreen()));
            if (mounted) _refreshNotificationCount();
          },
          notificationCount: _notificationCount,
          userLabel: userLabel,
          userImageUrl: authState.user?.avatarUrl,
          onOpenProfile: authState.isAuthenticated ? () => _openTab(AppTab.profile) : null,
          onOpenSettings: authState.isAuthenticated
              ? () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SettingsCenterScreen()))
              : null,
          onSignOut: authState.isAuthenticated ? authState.signOut : null,
        ),
        drawer: _ModuleDrawer(
          currentTab: _tab,
          onOpenTab: _openTab,
          onOpenModule: _openModule,
          onSignOut: () {
            Navigator.pop(context);
            authState.signOut();
          },
        ),
        body: page,
        bottomNavigationBar: DecoratedBox(
          decoration: BoxDecoration(
            color: navBackground,
            border: Border(top: BorderSide(color: theme.dividerColor)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: theme.brightness == Brightness.dark ? 0.22 : 0.08),
                blurRadius: 18,
                offset: const Offset(0, -8),
              ),
            ],
          ),
          child: NavigationBar(
            height: 72,
            elevation: 0,
            backgroundColor: navBackground,
            surfaceTintColor: Colors.transparent,
            indicatorColor: theme.colorScheme.primary.withValues(alpha: theme.brightness == Brightness.dark ? 0.22 : 0.14),
            selectedIndex: AppTab.values.indexOf(_tab),
            onDestinationSelected: (index) => _openTab(AppTab.values[index]),
            destinations: [
              NavigationDestination(icon: const Icon(Icons.grid_view_outlined), selectedIcon: const Icon(Icons.grid_view), label: scope.t('dashboard')),
              NavigationDestination(icon: const Icon(Icons.groups_outlined), selectedIcon: const Icon(Icons.groups), label: scope.t('clubs')),
              NavigationDestination(icon: const Icon(Icons.dynamic_feed_outlined), selectedIcon: const Icon(Icons.dynamic_feed), label: scope.t('feed.title')),
              NavigationDestination(icon: _NavigationBadgeIcon(icon: Icons.notifications_outlined, count: _notificationCount), selectedIcon: _NavigationBadgeIcon(icon: Icons.notifications, count: _notificationCount), label: scope.t('updates')),
              NavigationDestination(icon: const Icon(Icons.person_outline), selectedIcon: const Icon(Icons.person), label: scope.t('profile')),
            ],
          ),
        ),
      ),
    );
  }
}

Color _bottomNavBackground(BuildContext context) {
  final theme = Theme.of(context);
  final mix = theme.brightness == Brightness.dark ? 0.78 : 0.96;
  return Color.lerp(theme.scaffoldBackgroundColor, theme.colorScheme.surface, mix) ?? theme.colorScheme.surface;
}

class _NavigationBadgeIcon extends StatelessWidget {
  const _NavigationBadgeIcon({required this.icon, required this.count});

  final IconData icon;
  final int count;

  @override
  Widget build(BuildContext context) {
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Icon(icon),
        if (count > 0)
          Positioned(
            right: -9,
            top: -7,
            child: Container(
              constraints: const BoxConstraints(minWidth: 17, minHeight: 17),
              padding: const EdgeInsets.symmetric(horizontal: 4),
              decoration: BoxDecoration(
                color: AirmiusColors.red,
                borderRadius: BorderRadius.circular(99),
                border: Border.all(color: _bottomNavBackground(context), width: 2),
              ),
              alignment: Alignment.center,
              child: Text(
                count > 99 ? '99+' : '$count',
                style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w900, height: 1),
              ),
            ),
          ),
      ],
    );
  }
}

String? _userInitials({String? firstName, String? lastName, String? name}) {
  final fullName = [firstName, lastName].whereType<String>().map((part) => part.trim()).where((part) => part.isNotEmpty).join(' ');
  final source = fullName.trim().isNotEmpty ? fullName : name;
  final initials = initialsFromName(source, fallback: '');
  return initials.isEmpty ? null : initials;
}

class _ModuleDrawer extends StatelessWidget {
  const _ModuleDrawer({
    required this.currentTab,
    required this.onOpenTab,
    required this.onOpenModule,
    required this.onSignOut,
  });

  final AppTab currentTab;
  final ValueChanged<AppTab> onOpenTab;
  final ValueChanged<ModuleDefinition> onOpenModule;
  final VoidCallback onSignOut;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final drawerModules = appModules.where((module) => !_hiddenDrawerModuleTitles.contains(module.title));
    return SizedBox(
      width: MediaQuery.of(context).size.width,
      child: Material(
        color: AirmiusColors.header,
        elevation: 16,
        child: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 16, 14, 12),
                child: Row(
                  children: [
                    const AirmiusLogo(),
                    const Spacer(),
                    IconButton(
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints.tightFor(width: 44, height: 44),
                      tooltip: 'Menue schliessen',
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.close_rounded, color: AirmiusColors.text),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1, color: AirmiusColors.border),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(14, 14, 14, 20),
                  children: [
                    _DrawerTab(icon: Icons.home_outlined, label: scope.t('dashboard'), active: currentTab == AppTab.dashboard, onTap: () => _selectTab(context, AppTab.dashboard)),
                    _DrawerTab(icon: Icons.groups_outlined, label: scope.t('clubs'), active: currentTab == AppTab.clubs, onTap: () => _selectTab(context, AppTab.clubs)),
                    _DrawerTab(icon: Icons.dynamic_feed_outlined, label: scope.t('feed.title'), active: currentTab == AppTab.feed, onTap: () => _selectTab(context, AppTab.feed)),
                    _DrawerTab(icon: Icons.notifications_outlined, label: scope.t('updates'), active: currentTab == AppTab.updates, onTap: () => _selectTab(context, AppTab.updates)),
                    _DrawerTab(icon: Icons.public_outlined, label: 'Gastseite', active: false, onTap: () => _openScreen(context, const GuestPortalScreen())),
                    _DrawerTab(icon: Icons.hub_outlined, label: scope.t('ops.hub'), active: false, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => OperationsHubScreen()))),
                    const SizedBox(height: 18),
                    const Eyebrow('Alle Module'),
                    const SizedBox(height: 8),
                    for (final module in drawerModules)
                      _DrawerTab(icon: module.icon, label: scope.copy(module.title), active: false, onTap: () {
                        Navigator.pop(context);
                        onOpenModule(module);
                      }),
                    _DrawerTab(icon: Icons.logout_outlined, label: 'Abmelden', active: false, onTap: onSignOut),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _selectTab(BuildContext context, AppTab tab) {
    Navigator.pop(context);
    onOpenTab(tab);
  }

  void _openScreen(BuildContext context, Widget screen) {
    Navigator.pop(context);
    Navigator.push(context, MaterialPageRoute(builder: (_) => screen));
  }
}

const _hiddenDrawerModuleTitles = {
  'Vereins-Cockpit',
  'Vereine & Teams',
  'Teams',
  'Feed',
};

class _DrawerTab extends StatelessWidget {
  const _DrawerTab({required this.icon, required this.label, required this.active, required this.onTap});

  final IconData icon;
  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          decoration: BoxDecoration(
            color: active ? AirmiusColors.cardSoft : Colors.transparent,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            children: [
              Icon(icon, size: 20, color: active ? AirmiusColors.blue : AirmiusColors.muted),
              const SizedBox(width: 12),
              Expanded(child: Text(label, style: TextStyle(color: active ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w800))),
            ],
          ),
        ),
      ),
    );
  }
}
