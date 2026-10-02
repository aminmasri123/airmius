import 'package:flutter/material.dart';

import 'dart:async';

import 'operations_hub_screen.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_module_access.dart';
import '../core/airmius_mvp_surface.dart';
import '../core/airmius_persona.dart';
import '../core/airmius_preferences.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_services_scope.dart';
import '../navigation/airmius_module_destination.dart';
import '../models/app_tab.dart';
import '../models/club_summary.dart';
import '../models/footer_navigation_destination.dart';
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
import 'support_helpdesk_screen.dart';
import 'training_plans_logs_screen.dart';
import 'nutrition_center_screen.dart';
import 'marketplace_screen.dart';
import 'admin_backoffice_screen.dart';
import 'outfit_operations_screen.dart';
import 'platform_admin_screen.dart';

class ShellScreen extends StatefulWidget {
  const ShellScreen({super.key, this.preferences});

  final AirmiusPreferences? preferences;

  @override
  State<ShellScreen> createState() => _ShellScreenState();
}

class _ShellScreenState extends State<ShellScreen> {
  late final AirmiusPreferences _preferences;
  AppTab _tab = AppTab.feed;
  NutritionSection _nutritionSection = NutritionSection.overview;
  ModuleDefinition? _openedModule;
  final List<AppTab> _tabHistory = [];
  final Set<int> _requestedClubIds = {};
  int _messageCount = 0;
  int _notificationCount = 0;
  Timer? _badgeTimer;
  bool _refreshingBadges = false;
  int? _footerLoadedForUserId;
  int? _roleExperienceLoadedForUserId;
  String? _roleHomeModuleTitle;
  List<FooterNavigationDestination> _footerDestinations =
      FooterNavigationDestination.defaultDestinations;

  @override
  void initState() {
    super.initState();
    _preferences = widget.preferences ?? AirmiusPreferences();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refreshBadgeCounts());
    _badgeTimer = Timer.periodic(
      const Duration(seconds: 30),
      (_) => _refreshBadgeCounts(),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final user = AirmiusServicesScope.of(context).authState.user;
    final userId = user?.id;
    if (user != null && _roleExperienceLoadedForUserId != user.id) {
      _roleExperienceLoadedForUserId = user.id;
      _roleHomeModuleTitle = AirmiusPersonaResolver.homeModuleTitle(user);
      _footerDestinations = FooterNavigationDestination.defaultsFor(user);
      _openedModule = _moduleByTitle(_roleHomeModuleTitle);
      _tab = AppTab.feed;
      _tabHistory.clear();
    }
    if (userId == null || _footerLoadedForUserId == userId) return;
    _footerLoadedForUserId = userId;
    unawaited(_loadFooterNavigation());
  }

  @override
  void dispose() {
    _badgeTimer?.cancel();
    super.dispose();
  }

  Future<void> _refreshBadgeCounts() async {
    if (_refreshingBadges) return;
    _refreshingBadges = true;
    try {
      final repositories = AirmiusServicesScope.of(context).repositories;
      // Await each best-effort request as soon as it starts. Starting both
      // futures before attaching an error handler allows a fast conversation
      // failure to reach the root zone while notifications are still loading.
      final notifications = await repositories.notifications.notifications();
      final conversations = await repositories.conversations.conversations();
      if (!mounted) return;
      final nextNotificationCount =
          notifications.unreadCount ??
          notifications.items.where((item) => item.unread).length;
      final nextMessageCount = conversations.items.fold<int>(
        0,
        (sum, conversation) => sum + conversation.unreadCount,
      );
      if (_notificationCount == nextNotificationCount &&
          _messageCount == nextMessageCount) {
        return;
      }
      setState(() {
        _notificationCount = nextNotificationCount;
        _messageCount = nextMessageCount;
      });
    } catch (_) {
      // Badge refresh is best-effort; both centers retain their own error state.
    } finally {
      _refreshingBadges = false;
    }
  }

  Future<void> _loadFooterNavigation() async {
    final user = AirmiusServicesScope.of(context).authState.user;
    if (user == null) return;
    final stored = await _preferences.readFooterNavigation(user.id);
    if (!mounted ||
        AirmiusServicesScope.of(context).authState.user?.id != user.id) {
      return;
    }
    final destinations = sanitizeFooterNavigation(
      destinations: stored ?? FooterNavigationDestination.defaultsFor(user),
      user: user,
    );
    setState(() => _footerDestinations = destinations);
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
                onTap: () => Navigator.pop(context, NutritionSection.overview),
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
      if (_openedModule!.title == _roleHomeModuleTitle) return false;
      setState(() => _openedModule = _moduleByTitle(_roleHomeModuleTitle));
      return true;
    }

    if (_tabHistory.isNotEmpty) {
      final previousTab = _tabHistory.removeLast();
      _openTab(previousTab, remember: false);
      return true;
    }

    if (_tab != AppTab.feed) {
      _openTab(AppTab.feed, remember: false);
      return true;
    }

    return false;
  }

  ModuleDefinition? _moduleByTitle(String? title) {
    if (title == null) return null;
    for (final module in appModules) {
      if (module.title == title) return module;
    }
    return null;
  }

  void _openRoleHome() {
    final module = _moduleByTitle(_roleHomeModuleTitle);
    if (module == null) {
      _openTab(AppTab.feed, remember: false);
      return;
    }
    setState(() {
      _openedModule = module;
      _tabHistory.clear();
    });
  }

  bool get _isAtRoleHome =>
      _tabHistory.isEmpty &&
      ((_roleHomeModuleTitle == null &&
              _openedModule == null &&
              _tab == AppTab.feed) ||
          (_openedModule?.title == _roleHomeModuleTitle));

  void _openModule(ModuleDefinition module) {
    if (!AirmiusMvpSurface.isModuleVisible(module)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('module.hiddenInMvp')),
        ),
      );
      return;
    }

    final user = AirmiusServicesScope.of(context).authState.user;
    if (!AirmiusModuleAccess.canOpen(user, module.title)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('adminHub.forbiddenBody')),
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
      unawaited(_openSettings());
      return;
    }
    // Marketplace uses the real shell so the shared app bar, drawer,
    // messages, notifications and profile actions remain available.
    if (module.title == 'Marketplace') {
      setState(() => _openedModule = module);
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
        !AirmiusModuleAccess.canOpenAdmin(
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
        !AirmiusModuleAccess.canOpenPlatformSection(
          AirmiusServicesScope.of(context).authState.user,
          module.title == 'Rollen & Rechte' ? 'roles' : 'users',
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

  Future<void> _openSettings() async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => SettingsCenterScreen(
          preferences: _preferences,
          onFooterNavigationChanged: () {
            unawaited(_loadFooterNavigation());
          },
        ),
      ),
    );
  }

  void _openFooterDestination(FooterNavigationDestination destination) {
    switch (destination) {
      case FooterNavigationDestination.training:
        _openTab(AppTab.training);
      case FooterNavigationDestination.teams:
        final user = AirmiusServicesScope.of(context).authState.user;
        if (AirmiusModuleAccess.canOpenClubCockpit(user)) {
          unawaited(
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => const ClubCockpitScreen(initialAction: 'teams'),
              ),
            ),
          );
          return;
        }
        _openTab(AppTab.clubs);
      case FooterNavigationDestination.feed:
        _openTab(AppTab.feed);
      case FooterNavigationDestination.nutrition:
        setState(() => _nutritionSection = NutritionSection.overview);
        _openTab(AppTab.nutrition);
      case FooterNavigationDestination.profile:
        _openTab(AppTab.profile);
      case FooterNavigationDestination.drink:
        setState(() => _nutritionSection = NutritionSection.drink);
        _openTab(AppTab.nutrition);
      case FooterNavigationDestination.settings:
        unawaited(_openSettings());
      case FooterNavigationDestination.messages:
        unawaited(_openMessages());
      case FooterNavigationDestination.clubTodos:
        unawaited(
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => const ClubCockpitScreen(initialAction: 'todos'),
            ),
          ),
        );
        return;
      case FooterNavigationDestination.clubCalendar:
        unawaited(
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) =>
                  const ClubCockpitScreen(initialAction: 'calendar'),
            ),
          ),
        );
        return;
      case _:
        final moduleTitle = destination.moduleTitle;
        if (moduleTitle == null) return;
        for (final module in appModules) {
          if (module.title == moduleTitle) {
            _openModule(module);
            return;
          }
        }
    }
  }

  Future<void> _openMessages() async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const ConversationsCenterScreen()),
    );
    if (mounted) unawaited(_refreshBadgeCounts());
  }

  FooterNavigationDestination? get _activeFooterDestination {
    if (_openedModule != null) return null;
    return switch (_tab) {
      AppTab.training => FooterNavigationDestination.training,
      AppTab.clubs => FooterNavigationDestination.teams,
      AppTab.feed => FooterNavigationDestination.feed,
      AppTab.nutrition =>
        _nutritionSection == NutritionSection.drink &&
                _footerDestinations.contains(FooterNavigationDestination.drink)
            ? FooterNavigationDestination.drink
            : FooterNavigationDestination.nutrition,
      AppTab.profile => FooterNavigationDestination.profile,
    };
  }

  Widget? _screenForModule(String title) {
    return AirmiusModuleDestination.resolve(
      context,
      title,
      requestedClubIds: _requestedClubIds,
      onRequestClub: _requestClub,
      onWithdrawClub: _withdrawClub,
      preferences: _preferences,
      onFooterNavigationChanged: () => unawaited(_loadFooterNavigation()),
    );
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
    _refreshBadgeCounts();
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
        ? _openedModule!.title == 'Vereins-Cockpit'
              ? _screenForModule('Vereins-Cockpit') ?? const SizedBox.shrink()
              : _openedModule!.title == 'Marketplace'
              ? const MarketplaceScreen()
              : ModuleScreen(
                  module: _openedModule!,
                  requestedClubIds: _requestedClubIds,
                  onRequestClub: _requestClub,
                  onWithdrawClub: _withdrawClub,
                  autoOpen: _openedModule!.title == _roleHomeModuleTitle,
                )
        : switch (_tab) {
            AppTab.training => const TrainingPlansLogsScreen(),
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
      canPop: _isAtRoleHome,
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
          onLogoTap: _openRoleHome,
          onSearch: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => GlobalSearchScreen()),
          ),
          onMessages: () async {
            await _openMessages();
          },
          onNotifications: () async {
            await Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => NotificationsCenterScreen()),
            );
            if (mounted) _refreshBadgeCounts();
          },
          messageCount: _messageCount,
          notificationCount: _notificationCount,
          userLabel: userLabel,
          userImageUrl: authState.user?.avatarUrl,
          onOpenProfile: authState.isAuthenticated
              ? () => _openTab(AppTab.profile)
              : null,
          onOpenSettings: authState.isAuthenticated
              ? () => unawaited(_openSettings())
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
          child: _PersonalizedBottomNavigation(
            destinations: _footerDestinations,
            activeDestination: _activeFooterDestination,
            messageCount: _messageCount,
            onSelected: _openFooterDestination,
          ),
        ),
      ),
    );
  }
}

class _PersonalizedBottomNavigation extends StatelessWidget {
  const _PersonalizedBottomNavigation({
    required this.destinations,
    required this.activeDestination,
    required this.messageCount,
    required this.onSelected,
  });

  final List<FooterNavigationDestination> destinations;
  final FooterNavigationDestination? activeDestination;
  final int messageCount;
  final ValueChanged<FooterNavigationDestination> onSelected;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final theme = Theme.of(context);
    final accent = theme.colorScheme.primary;
    final muted = airmiusMutedColor(context);
    final text = airmiusTextColor(context);

    return SafeArea(
      top: false,
      child: SizedBox(
        height: 72,
        child: Row(
          children: [
            for (final destination in destinations)
              Expanded(
                child: Semantics(
                  button: true,
                  selected: activeDestination == destination,
                  label: scope.t(destination.labelKey),
                  child: InkWell(
                    onTap: () => onSelected(destination),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(2, 6, 2, 5),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            constraints: const BoxConstraints(
                              minWidth: 48,
                              minHeight: 32,
                            ),
                            padding: const EdgeInsets.symmetric(horizontal: 10),
                            decoration: BoxDecoration(
                              color: activeDestination == destination
                                  ? accent.withValues(
                                      alpha: theme.brightness == Brightness.dark
                                          ? 0.22
                                          : 0.14,
                                    )
                                  : Colors.transparent,
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Center(
                              child: _FooterNavigationIcon(
                                destination: destination,
                                active: activeDestination == destination,
                                messageCount: messageCount,
                                activeColor: accent,
                                inactiveColor: muted,
                              ),
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            scope.t(destination.labelKey),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: activeDestination == destination
                                  ? text
                                  : muted,
                              fontSize: destinations.length <= 3 ? 12 : 10.5,
                              fontWeight: activeDestination == destination
                                  ? FontWeight.w900
                                  : FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _FooterNavigationIcon extends StatelessWidget {
  const _FooterNavigationIcon({
    required this.destination,
    required this.active,
    required this.messageCount,
    required this.activeColor,
    required this.inactiveColor,
  });

  final FooterNavigationDestination destination;
  final bool active;
  final int messageCount;
  final Color activeColor;
  final Color inactiveColor;

  @override
  Widget build(BuildContext context) {
    final icon = Icon(
      active ? destination.selectedIcon : destination.icon,
      size: 24,
      color: active ? activeColor : inactiveColor,
    );
    if (destination != FooterNavigationDestination.messages ||
        messageCount <= 0) {
      return icon;
    }
    final count = messageCount > 99 ? '99+' : '$messageCount';
    return Stack(
      clipBehavior: Clip.none,
      children: [
        icon,
        PositionedDirectional(
          top: -8,
          end: -12,
          child: Container(
            constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
            padding: const EdgeInsets.symmetric(horizontal: 4),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.error,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(
                color: _bottomNavBackground(context),
                width: 1.5,
              ),
            ),
            child: Center(
              child: Text(
                count,
                style: TextStyle(
                  color: Theme.of(context).colorScheme.onError,
                  fontSize: 9,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ),
        ),
      ],
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
    final user = AirmiusServicesScope.of(context).authState.user;
    final clubNavigation =
        user != null && AirmiusModuleAccess.canOpenClubCockpit(user);
    final trainerNavigation =
        user != null &&
        !clubNavigation &&
        AirmiusModuleAccess.canOpenTrainerCockpit(user);
    final recommendedModules = user == null
        ? const <String>{}
        : AirmiusPersonaResolver.navigationModules(user);
    const athleteQuickTitles = {
      'Sport-Matching',
      'Events & Training',
      'Freunde',
    };
    final availableModules = appModules
        .where(AirmiusMvpSurface.isModuleVisible)
        .where((module) => AirmiusModuleAccess.canOpen(user, module.title))
        .where((module) => recommendedModules.contains(module.title))
        .toList();
    final quickModules = trainerNavigation || clubNavigation
        ? const <ModuleDefinition>[]
        : availableModules
              .where((module) => athleteQuickTitles.contains(module.title))
              .toList();
    final drawerModules =
        availableModules.where((module) {
          if (clubNavigation) {
            return const {
              'Feed',
              'Vereins-Cockpit',
              'Teams',
              'Trainer-Cockpit',
              'Events & Training',
              'Challenges',
              'Dateien',
              'Kurse',
              'Marketplace',
              'Blog & Medien',
              'Sponsoren',
              'Admin',
            }.contains(module.title);
          }
          if (trainerNavigation) {
            return module.title != 'Arbeitsbereiche' &&
                module.title != 'Vereine & Teams' &&
                module.title != 'Feed';
          }
          return true;
        }).toList()..sort((a, b) {
          const order = {
            'Vereins-Cockpit': 0,
            'Feed': 1,
            'Teams': 2,
            'Events & Training': 3,
            'Kurse': 4,
            'Challenges': 5,
            'Dateien': 6,
            'Marketplace': 7,
            'Blog & Medien': 8,
            'Trainer-Cockpit': 9,
            'Sponsoren': 10,
            'Admin': 11,
          };
          return (order[a.title] ?? 99).compareTo(order[b.title] ?? 99);
        });
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
                    if (trainerNavigation)
                      _DrawerTab(
                        icon: Icons.fitness_center_outlined,
                        label: scope.t('training.nav'),
                        active: currentTab == AppTab.training,
                        onTap: () => _selectTab(context, AppTab.training),
                      ),
                    if (trainerNavigation)
                      _DrawerTab(
                        icon: Icons.groups_outlined,
                        label: trainerNavigation
                            ? scope.copy('Trainer & Teams')
                            : scope.t('clubs'),
                        active: currentTab == AppTab.clubs,
                        onTap: () => _selectTab(context, AppTab.clubs),
                      ),
                    if (trainerNavigation)
                      _DrawerTab(
                        icon: Icons.dynamic_feed_outlined,
                        label: scope.t('feed.title'),
                        active: currentTab == AppTab.feed,
                        onTap: () => _selectTab(context, AppTab.feed),
                      ),
                    if (trainerNavigation)
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
                    if (quickModules.isNotEmpty) ...[
                      Eyebrow(scope.t('shell.quickAccess')),
                      const SizedBox(height: 8),
                      for (final module in quickModules)
                        _DrawerTab(
                          icon: module.icon,
                          label: scope.copy(module.title),
                          active: false,
                          onTap: () {
                            Navigator.pop(context);
                            onOpenModule(module);
                          },
                        ),
                    ],
                    const SizedBox(height: 18),
                    Eyebrow(
                      clubNavigation
                          ? scope.t('shell.clubManagement')
                          : scope.t('shell.allModules'),
                    ),
                    const SizedBox(height: 8),
                    if (clubNavigation)
                      _DrawerTab(
                        icon: Icons.support_agent_outlined,
                        label: scope.copy('Support'),
                        active: false,
                        onTap: () =>
                            _openScreen(context, const SupportHelpdeskScreen()),
                      ),
                    if (clubNavigation)
                      _DrawerTab(
                        icon: Icons.checklist_outlined,
                        label: scope.t('footerNav.clubTodos'),
                        active: false,
                        onTap: () => _openScreen(
                          context,
                          const ClubCockpitScreen(initialAction: 'todos'),
                        ),
                      ),
                    if (clubNavigation)
                      _DrawerTab(
                        icon: Icons.calendar_month_outlined,
                        label: scope.t('footerNav.clubCalendar'),
                        active: false,
                        onTap: () => _openScreen(
                          context,
                          const ClubCockpitScreen(initialAction: 'calendar'),
                        ),
                      ),
                    for (final module in drawerModules)
                      _DrawerTab(
                        icon: module.icon,
                        label: clubNavigation
                            ? _clubDrawerLabel(scope, module.title)
                            : trainerNavigation
                            ? _trainerDrawerLabel(scope, module.title)
                            : scope.copy(module.title),
                        active: false,
                        onTap: () {
                          Navigator.pop(context);
                          if (clubNavigation && module.title == 'Teams') {
                            unawaited(
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => const ClubCockpitScreen(
                                    initialAction: 'teams',
                                  ),
                                ),
                              ),
                            );
                            return;
                          }
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


String _trainerDrawerLabel(AirmiusScope scope, String title) => switch (title) {
  'Events & Training' => scope.copy('Events & Anwesenheit'),
  'Dateien' => scope.copy('Teamdateien'),
  _ => scope.copy(title),
};

String _clubDrawerLabel(AirmiusScope scope, String title) => switch (title) {
  'Events & Training' => scope.copy('Events'),
  'Kurse' => scope.copy('E-Learning'),
  'Teams' => scope.copy('Team'),
  'Challenges' => scope.copy('Challenge'),
  'Dateien' => scope.copy('Datei'),
  'Blog & Medien' => scope.copy('Blog'),
  _ => scope.copy(title),
};

bool _canOpenGuardianCenter(AirmiusUser? user) {
  return AirmiusModuleAccess.canOpenGuardianCenter(user);
}

bool _canOpenTrainerCockpit(AirmiusUser? user) {
  return AirmiusModuleAccess.canOpenTrainerCockpit(user);
}

bool _canOpenClubCockpit(AirmiusUser? user) {
  return AirmiusModuleAccess.canOpenClubCockpit(user);
}

bool _canOpenPlatformAdmin(AirmiusUser? user) {
  return AirmiusModuleAccess.canOpenPlatformAdmin(user);
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
