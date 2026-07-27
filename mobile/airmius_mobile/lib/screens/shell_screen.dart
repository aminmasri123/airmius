import 'package:flutter/material.dart';

import 'dart:async';

import 'operations_hub_screen.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_mvp_surface.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_services_scope.dart';
import '../models/app_tab.dart';
import '../models/club_summary.dart';
import '../models/module_definition.dart';
import '../widgets/airmius_widgets.dart';
import 'club_cockpit_screen.dart';
import 'clubs_screen.dart';
import 'conversations_center_screen.dart';
import 'feed_center_screen.dart';
import 'global_search_screen.dart';
import 'guest_portal_screen.dart';
import 'module_screen.dart';
import 'notifications_center_screen.dart';
import 'profile_screen.dart';
import 'settings_center_screen.dart';
import 'workspace_center_screen.dart';
import 'teams_center_screen.dart';
import 'roles_permissions_screen.dart';
import 'sports_center_screen.dart';
import 'sport_integrations_screen.dart';
import 'event_management_screen.dart';
import 'trainer_cockpit_screen.dart';
import 'training_center_screen.dart';
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
import 'admin_backoffice_screen.dart';
import 'admin_center_screen.dart';
import 'outfit_operations_screen.dart';
import 'platform_admin_screen.dart';

class ShellScreen extends StatefulWidget {
  const ShellScreen({super.key});

  @override
  State<ShellScreen> createState() => _ShellScreenState();
}

class _ShellScreenState extends State<ShellScreen> {
  AppTab _tab = AppTab.training;
  NutritionSection _nutritionSection = NutritionSection.overview;
  ModuleDefinition? _openedModule;
  final List<AppTab> _tabHistory = [];
  final Set<int> _requestedClubIds = {};
  int _notificationCount = 0;
  Timer? _notificationTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => _refreshNotificationCount(),
    );
    _notificationTimer = Timer.periodic(
      const Duration(seconds: 45),
      (_) => _refreshNotificationCount(),
    );
  }

  @override
  void dispose() {
    _notificationTimer?.cancel();
    super.dispose();
  }

  Future<void> _refreshNotificationCount() async {
    try {
      final page = await AirmiusServicesScope.of(
        context,
      ).repositories.notifications.notifications();
      if (!mounted) return;
      setState(
        () => _notificationCount =
            page.unreadCount ?? page.items.where((item) => item.unread).length,
      );
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
  }

  Future<void> _chooseNutritionTab() async {
    final t = AirmiusScope.of(context).t;
    final selected = await showModalBottomSheet<NutritionSection>(
      context: context,
      backgroundColor: airmiusSurfaceColor(context),
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                t('nutrition.title'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
              _NutritionChoiceTile(
                icon: Icons.restaurant_menu_outlined,
                title: t('nutrition.title'),
                subtitle: t('nutrition.dailyOverview'),
                onTap: () =>
                    Navigator.pop(context, NutritionSection.overview),
              ),
              const SizedBox(height: 8),
              _NutritionChoiceTile(
                icon: Icons.water_drop_outlined,
                title: t('nutrition.addWater'),
                subtitle: t('nutrition.waterAmount'),
                onTap: () => Navigator.pop(context, NutritionSection.drink),
              ),
            ],
          ),
        ),
      ),
    );
    if (selected == null || !mounted) return;
    setState(() => _nutritionSection = selected);
    _openTab(AppTab.nutrition);
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

    if (_tab != AppTab.training) {
      _openTab(AppTab.training, remember: false);
      return true;
    }

    return false;
  }

  void _openModule(ModuleDefinition module) {
    if (!AirmiusMvpSurface.isModuleVisible(module)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('module.hiddenInMvp')),
        ),
      );
      return;
    }

    if (module.title == 'Feed') {
      _openTab(AppTab.feed);
      return;
    }
    if (module.title == 'Nachrichten') {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const ConversationsCenterScreen()),
      );
      return;
    }
    if (module.title == 'Vereine & Teams') {
      _openTab(AppTab.clubs);
      return;
    }
    if (module.title == 'Einstellungen') {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const SettingsCenterScreen()),
      );
      return;
    }
    if (module.title == 'Eltern & Jugendschutz' &&
        !_canOpenGuardianCenter(
          AirmiusServicesScope.of(context).authState.user,
        )) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('guardian.forbiddenBody')),
        ),
      );
      return;
    }
    if (module.title == 'Trainer-Cockpit' &&
        !_canOpenTrainerCockpit(
          AirmiusServicesScope.of(context).authState.user,
        )) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AirmiusScope.of(context).t('coach.forbidden'))),
      );
      return;
    }
    if (module.title == 'Vereins-Cockpit' &&
        !_canOpenClubCockpit(AirmiusServicesScope.of(context).authState.user)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('clubHub.forbidden')),
        ),
      );
      return;
    }
    if (module.title == 'Admin' &&
        !_canOpenPlatformAdmin(
          AirmiusServicesScope.of(context).authState.user,
        )) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('adminHub.forbidden')),
        ),
      );
      return;
    }
    if (const {
          'Rollen & Rechte',
          'Gamification-Regeln',
          'Nutzer',
        }.contains(module.title) &&
        !_canOpenPlatformAdmin(
          AirmiusServicesScope.of(context).authState.user,
        )) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('adminHub.forbiddenBody')),
        ),
      );
      return;
    }
    if (module.title == 'Admin') {
      final user = AirmiusServicesScope.of(context).authState.user!;
      final canManageCommerce =
          user.can('subscriptions.manage') || user.can('marketplace.manage');
      final canManageBackoffice =
          user.can('subscriptions.manage') ||
          user.can('billing.manage') ||
          user.can('finance.view') ||
          user.can('finance.edit') ||
          user.can('system.manage');
      final canManageOutfits = user.can('outfit-subscriptions.manage');
      if (canManageOutfits &&
          !canManageCommerce &&
          !canManageBackoffice &&
          !user.can('system.manage')) {
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const OutfitOperationsScreen()),
        );
        return;
      }
      if (canManageBackoffice &&
          !canManageCommerce &&
          !user.can('system.manage')) {
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const AdminBackofficeScreen()),
        );
        return;
      }
      if (user.can('system.manage') && !canManageCommerce) {
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const PlatformAdminScreen()),
        );
        return;
      }
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
      'Sport-Apps & Gesundheitsdaten' => SportIntegrationsScreen(),
      'Events & Training' => const EventManagementScreen(),
      'Trainer-Cockpit' => const TrainerCockpitScreen(),
      'Vereins-Cockpit' => const ClubCockpitScreen(),
      'Ernährung' => const NutritionCenterScreen(),
      'Sportkarte' => const SportMapCenterScreen(),
      'Freunde' => const FriendsSocialGraphScreen(),
      'Fahrgemeinschaften' => const CarpoolCenterScreen(),
      'Dateien' => const FileManagerScreen(),
      'Badges' => const BadgesCenterScreen(),
      'Altersfreigaben' => const MaturityCenterScreen(),
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
      'Outfit-Abos' => const OutfitSubscriptionCenterScreen(),
      'Admin' => const AdminCenterScreen(),
      _ => null,
    };
  }

  void _requestClub(ClubSummary club) {
    setState(() {
      _requestedClubIds.add(club.id);
      if (_tab != AppTab.clubs) {
        _tabHistory.add(_tab);
      }
      _tab = AppTab.clubs;
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
        ? ModuleScreen(
            module: _openedModule!,
            requestedClubIds: _requestedClubIds,
            onRequestClub: _requestClub,
            onWithdrawClub: _withdrawClub,
          )
        : switch (_tab) {
            AppTab.training => const TrainingCenterScreen(),
            AppTab.clubs => ClubsScreen(
              requestedClubIds: _requestedClubIds,
              onRequestClub: _requestClub,
              onWithdrawClub: _withdrawClub,
            ),
            AppTab.feed => const FeedCenterScreen(),
            AppTab.nutrition => NutritionCenterScreen(
              initialSection: _nutritionSection,
            ),
            AppTab.profile => const ProfileScreen(),
          };

    return PopScope(
      canPop:
          _openedModule == null &&
          _tabHistory.isEmpty &&
          _tab == AppTab.training,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        _handleBackNavigation();
      },
      child: Scaffold(
        backgroundColor: theme.scaffoldBackgroundColor,
        appBar: AirmiusTopBar(
          title: _openedModule == null
              ? scope.t(_tab.i18nKey)
              : scope.copy(_openedModule!.title),
          onLogoTap: () => _openTab(AppTab.feed),
          onSearch: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => GlobalSearchScreen()),
          ),
          onMessages: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => ConversationsCenterScreen()),
          ),
          onNotifications: () async {
            await Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => NotificationsCenterScreen()),
            );
            if (mounted) _refreshNotificationCount();
          },
          notificationCount: _notificationCount,
          userLabel: userLabel,
          userImageUrl: authState.user?.avatarUrl,
          onOpenProfile: authState.isAuthenticated
              ? () => _openTab(AppTab.profile)
              : null,
          onOpenSettings: authState.isAuthenticated
              ? () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => const SettingsCenterScreen(),
                  ),
                )
              : null,
          onSignOut: authState.isAuthenticated ? authState.signOut : null,
        ),
        drawer: _ModuleDrawer(
          currentTab: _tab,
          onOpenTab: _openTab,
          onChooseNutritionTab: _chooseNutritionTab,
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
                color: Colors.black.withValues(
                  alpha: theme.brightness == Brightness.dark ? 0.22 : 0.08,
                ),
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
            indicatorColor: theme.colorScheme.primary.withValues(
              alpha: theme.brightness == Brightness.dark ? 0.22 : 0.14,
            ),
            selectedIndex: AppTab.values.indexOf(_tab),
            onDestinationSelected: (index) {
              final tab = AppTab.values[index];
              if (tab == AppTab.nutrition) {
                _chooseNutritionTab();
                return;
              }
              _openTab(tab);
            },
            destinations: [
              NavigationDestination(
                icon: const Icon(Icons.fitness_center_outlined),
                selectedIcon: const Icon(Icons.fitness_center),
                label: scope.t('training.nav'),
              ),
              NavigationDestination(
                icon: const Icon(Icons.groups_outlined),
                selectedIcon: const Icon(Icons.groups),
                label: scope.t('teams'),
              ),
              NavigationDestination(
                icon: const Icon(Icons.dynamic_feed_outlined),
                selectedIcon: const Icon(Icons.dynamic_feed),
                label: scope.t('feed.title'),
              ),
              NavigationDestination(
                icon: const Icon(Icons.restaurant_menu_outlined),
                selectedIcon: const Icon(Icons.restaurant_menu),
                label: scope.t('nutrition.title'),
              ),
              NavigationDestination(
                icon: const Icon(Icons.person_outline),
                selectedIcon: const Icon(Icons.person),
                label: scope.t('profile'),
              ),
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
  return Color.lerp(
        theme.scaffoldBackgroundColor,
        theme.colorScheme.surface,
        mix,
      ) ??
      theme.colorScheme.surface;
}

Color _drawerBackground(BuildContext context) {
  final theme = Theme.of(context);
  if (theme.brightness == Brightness.dark) {
    return theme.appBarTheme.backgroundColor ?? theme.colorScheme.surface;
  }
  return Color.lerp(
        theme.scaffoldBackgroundColor,
        theme.colorScheme.surface,
        0.92,
      ) ??
      theme.colorScheme.surface;
}

Color _shellText(BuildContext context) {
  return Theme.of(context).textTheme.bodyLarge?.color ?? AirmiusColors.text;
}

Color _shellMuted(BuildContext context) {
  return Theme.of(context).textTheme.bodyMedium?.color ?? AirmiusColors.muted;
}

String? _userInitials({String? firstName, String? lastName, String? name}) {
  final fullName = [firstName, lastName]
      .whereType<String>()
      .map((part) => part.trim())
      .where((part) => part.isNotEmpty)
      .join(' ');
  final source = fullName.trim().isNotEmpty ? fullName : name;
  final initials = initialsFromName(source, fallback: '');
  return initials.isEmpty ? null : initials;
}

class _ModuleDrawer extends StatelessWidget {
  const _ModuleDrawer({
    required this.currentTab,
    required this.onOpenTab,
    required this.onChooseNutritionTab,
    required this.onOpenModule,
    required this.onSignOut,
  });

  final AppTab currentTab;
  final ValueChanged<AppTab> onOpenTab;
  final Future<void> Function() onChooseNutritionTab;
  final ValueChanged<ModuleDefinition> onOpenModule;
  final VoidCallback onSignOut;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final drawerModules = appModules
        .where(AirmiusMvpSurface.isModuleVisible)
        .where(
          (module) =>
              module.title != 'Eltern & Jugendschutz' ||
              _canOpenGuardianCenter(
                AirmiusServicesScope.of(context).authState.user,
              ),
        )
        .where(
          (module) =>
              module.title != 'Trainer-Cockpit' ||
              _canOpenTrainerCockpit(
                AirmiusServicesScope.of(context).authState.user,
              ),
        )
        .where(
          (module) =>
              module.title != 'Vereins-Cockpit' ||
              _canOpenClubCockpit(
                AirmiusServicesScope.of(context).authState.user,
              ),
        )
        .where(
          (module) =>
              module.title != 'Admin' ||
              _canOpenPlatformAdmin(
                AirmiusServicesScope.of(context).authState.user,
              ),
        )
        .where(
          (module) =>
              !const {
                'Rollen & Rechte',
                'Gamification-Regeln',
                'Nutzer',
              }.contains(module.title) ||
              _canOpenPlatformAdmin(
                AirmiusServicesScope.of(context).authState.user,
              ),
        )
        .where((module) => !_hiddenDrawerModuleTitles.contains(module.title));
    final theme = Theme.of(context);
    final drawerBackground = _drawerBackground(context);
    return SizedBox(
      width: MediaQuery.of(context).size.width,
      child: Material(
        color: drawerBackground,
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
                      constraints: const BoxConstraints.tightFor(
                        width: 44,
                        height: 44,
                      ),
                      tooltip: scope.t('shell.closeMenu'),
                      onPressed: () => Navigator.pop(context),
                      icon: Icon(
                        Icons.close_rounded,
                        color: _shellText(context),
                      ),
                    ),
                  ],
                ),
              ),
              Divider(height: 1, color: theme.dividerColor),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(14, 14, 14, 20),
                  children: [
                    _DrawerTab(
                      icon: Icons.fitness_center_outlined,
                      label: scope.t('training.nav'),
                      active: currentTab == AppTab.training,
                      onTap: () => _selectTab(context, AppTab.training),
                    ),
                    _DrawerTab(
                      icon: Icons.groups_outlined,
                      label: scope.t('clubs'),
                      active: currentTab == AppTab.clubs,
                      onTap: () => _selectTab(context, AppTab.clubs),
                    ),
                    _DrawerTab(
                      icon: Icons.dynamic_feed_outlined,
                      label: scope.t('feed.title'),
                      active: currentTab == AppTab.feed,
                      onTap: () => _selectTab(context, AppTab.feed),
                    ),
                    _DrawerTab(
                      icon: Icons.restaurant_menu_outlined,
                      label: scope.t('nutrition.title'),
                      active: currentTab == AppTab.nutrition,
                      onTap: () {
                        Navigator.pop(context);
                        onChooseNutritionTab();
                      },
                    ),
                    if (AirmiusMvpSurface.showDeveloperSuites)
                      _DrawerTab(
                        icon: Icons.public_outlined,
                        label: scope.t('shell.guestPage'),
                        active: false,
                        onTap: () =>
                            _openScreen(context, const GuestPortalScreen()),
                      ),
                    if (AirmiusMvpSurface.isOperationsHubVisible)
                      _DrawerTab(
                        icon: Icons.hub_outlined,
                        label: scope.t('ops.hub'),
                        active: false,
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => OperationsHubScreen(),
                          ),
                        ),
                      ),
                    const SizedBox(height: 18),
                    Eyebrow(scope.t('shell.allModules')),
                    const SizedBox(height: 8),
                    for (final module in drawerModules)
                      _DrawerTab(
                        icon: module.icon,
                        label: scope.copy(module.title),
                        active: false,
                        onTap: () {
                          Navigator.pop(context);
                          onOpenModule(module);
                        },
                      ),
                    _DrawerTab(
                      icon: Icons.logout_outlined,
                      label: scope.t('shell.signOut'),
                      active: false,
                      onTap: onSignOut,
                    ),
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

const _hiddenDrawerModuleTitles = {'Vereine & Teams', 'Teams', 'Feed'};

bool _canOpenGuardianCenter(AirmiusUser? user) {
  return user?.hasAnyRole(const ['guardian', 'parent']) == true ||
      user?.can('guardians.children.view') == true;
}

bool _canOpenTrainerCockpit(AirmiusUser? user) {
  if (user == null) return false;
  const globalRoles = {
    'super_admin',
    'admin',
    'system_admin',
    'coach',
    'assistant_coach',
    'performance_coach',
    'fitness_coach',
    'team_manager',
    'captain',
    'trainer',
    'academy_manager',
    'club_owner',
    'club_admin',
    'club_manager',
  };
  const teamRoles = {'coach', 'trainer', 'captain', 'admin', 'manager'};
  return user.hasAnyRole(globalRoles) ||
      user.teams.any(
        (team) => teamRoles.contains(team.membershipRole?.toLowerCase()),
      ) ||
      user.clubs.any(
        (club) => globalRoles.contains(club.membershipRole?.toLowerCase()),
      );
}

bool _canOpenClubCockpit(AirmiusUser? user) {
  if (user == null) return false;
  const globalRoles = {
    'super_admin',
    'admin',
    'system_admin',
    'club_owner',
    'club_admin',
    'club_manager',
    'academy_manager',
    'financial_controller',
  };
  const clubRoles = {
    'owner',
    'admin',
    'manager',
    'academy_manager',
    'financial_controller',
  };
  return user.hasAnyRole(globalRoles) ||
      user.clubs.any(
        (club) => clubRoles.contains(club.membershipRole?.toLowerCase()),
      );
}

bool _canOpenPlatformAdmin(AirmiusUser? user) {
  if (user == null) return false;
  final hasCommercePermission =
      user.can('subscriptions.manage') ||
      user.can('marketplace.manage') ||
      user.can('billing.manage') ||
      user.can('finance.view') ||
      user.can('finance.edit') ||
      user.can('outfit-subscriptions.manage') ||
      user.can('system.manage');
  final platformAdmin = user.hasAnyRole(const [
    'super_admin',
    'admin',
    'system_admin',
  ]);
  if (hasCommercePermission && !platformAdmin) return true;
  return platformAdmin && user.twoFactorEnabled;
}

class _NutritionChoiceTile extends StatelessWidget {
  const _NutritionChoiceTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: airmiusSurfaceColor(context),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: airmiusBorderColor(context)),
        ),
        child: Row(
          children: [
            Icon(icon, color: airmiusAccentColor(context)),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
          ],
        ),
      ),
    );
  }
}

class _DrawerTab extends StatelessWidget {
  const _DrawerTab({
    required this.icon,
    required this.label,
    required this.active,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final activeColor = theme.colorScheme.primary;
    final textColor = _shellText(context);
    final mutedColor = _shellMuted(context);
    final activeBackground = activeColor.withValues(
      alpha: theme.brightness == Brightness.dark ? 0.16 : 0.11,
    );

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          decoration: BoxDecoration(
            color: active ? activeBackground : Colors.transparent,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            children: [
              Icon(icon, size: 20, color: active ? activeColor : mutedColor),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  style: TextStyle(
                    color: active ? textColor : mutedColor,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
