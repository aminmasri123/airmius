import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_mvp_surface.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/app_tab.dart';
import '../models/module_definition.dart';
import '../widgets/airmius_widgets.dart';
import 'daily_flow_screen.dart';

Color _dashText(BuildContext context) {
  return airmiusTextColor(context);
}

Color _dashMuted(BuildContext context) {
  return airmiusMutedColor(context);
}

Color _dashSurface(BuildContext context) {
  return Theme.of(context).colorScheme.surface;
}

Color _dashSurfaceSoft(BuildContext context) {
  final theme = Theme.of(context);
  final fill = theme.inputDecorationTheme.fillColor;
  return fill ??
      Color.lerp(
        theme.colorScheme.surface,
        theme.colorScheme.primary,
        theme.brightness == Brightness.dark ? 0.16 : 0.08,
      ) ??
      theme.colorScheme.surface;
}

Color _dashBorder(BuildContext context) {
  return Theme.of(context).dividerColor;
}

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({
    super.key,
    required this.onOpenTab,
    required this.onOpenModule,
    required this.requestedClubIds,
  });

  final ValueChanged<AppTab> onOpenTab;
  final ValueChanged<ModuleDefinition> onOpenModule;
  final Set<int> requestedClubIds;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  bool _showCustomize = false;
  String? _loadError;
  _DashboardLiveData? _liveData;
  final Set<String> _visibleWidgets = {
    for (final widget in _dashboardWidgets)
      if (AirmiusMvpSurface.isDashboardWidgetVisible(widget.key)) widget.key,
  };

  @override
  void initState() {
    super.initState();
    // Live data is loaded after the first frame so the initial layout remains
    // deterministic on low-end devices and in accessibility previews.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _loadDashboard();
    });
  }

  Future<void> _loadDashboard() async {
    try {
      final services = AirmiusServicesScope.of(context);
      final response = await services
          .clientForSession(services.authState.session)
          .dashboardDailyFlow();
      final raw = response['data'];
      if (!mounted) return;
      setState(() {
        _liveData = raw is JsonMap ? _DashboardLiveData.fromJson(raw) : null;
        _loadError = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loadError = error is AirmiusApiException
            ? error.userMessage
            : AirmiusScope.of(context).t('common.errorDetails');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final authState = AirmiusServicesScope.of(context).authState;
    final firstName = authState.user?.firstName?.trim();
    final userName = firstName != null && firstName.isNotEmpty
        ? firstName
        : authState.user?.name ?? scope.t('dashboard.athlete');

    return PageFrame(
      title: scope.t('dashboard'),
      subtitle: scope.t('dashboard.subtitle'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _DashboardHero(
            userName: userName,
            showCustomize: _showCustomize,
            visibleWidgets: _visibleWidgets,
            onToggleCustomize: () =>
                setState(() => _showCustomize = !_showCustomize),
            onToggleWidget: _toggleWidget,
            onShowAll: () => setState(
              () => _visibleWidgets
                ..clear()
                ..addAll(_visibleDashboardWidgets.map((widget) => widget.key)),
            ),
          ),
          const SizedBox(height: 14),
          _QuickActions(
            onOpenTab: widget.onOpenTab,
            onOpenModule: widget.onOpenModule,
            onOpenDailyFlow: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const DailyFlowScreen()),
            ),
          ),
          const SizedBox(height: 14),
          if (_loadError != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: _DashboardLoadState(
                error: _loadError!,
                onRetry: _loadDashboard,
              ),
            ),
          _StatsGrid(stats: _dashboardStats(context, scope, _liveData)),
          const SizedBox(height: 14),
          _DashboardWidgets(
            visibleWidgets: _visibleWidgets,
            onOpenTab: widget.onOpenTab,
            onOpenModule: widget.onOpenModule,
            liveData: _liveData,
          ),
        ],
      ),
    );
  }

  void _toggleWidget(String key) {
    setState(() {
      if (_visibleWidgets.contains(key)) {
        _visibleWidgets.remove(key);
        return;
      }
      _visibleWidgets.add(key);
    });
  }
}

class _DashboardLoadState extends StatelessWidget {
  const _DashboardLoadState({required this.error, required this.onRetry});

  final String error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      borderColor: Theme.of(context).colorScheme.error.withValues(alpha: 0.45),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final compact = constraints.maxWidth < 390;
          final message = Row(
            children: [
              Icon(
                Icons.cloud_off_outlined,
                color: Theme.of(context).colorScheme.error,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  t('dashboard.liveDataUnavailable'),
                  style: TextStyle(
                    color: _dashText(context),
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          );
          final retry = TextButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_outlined),
            label: Text(t('common.retry')),
          );
          return compact
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    message,
                    Align(
                      alignment: AlignmentDirectional.centerEnd,
                      child: retry,
                    ),
                  ],
                )
              : Row(
                  children: [
                    Expanded(child: message),
                    retry,
                  ],
                );
        },
      ),
    );
  }
}

class _DashboardHero extends StatelessWidget {
  const _DashboardHero({
    required this.userName,
    required this.showCustomize,
    required this.visibleWidgets,
    required this.onToggleCustomize,
    required this.onToggleWidget,
    required this.onShowAll,
  });

  final String userName;
  final bool showCustomize;
  final Set<String> visibleWidgets;
  final VoidCallback onToggleCustomize;
  final ValueChanged<String> onToggleWidget;
  final VoidCallback onShowAll;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final scheme = Theme.of(context).colorScheme;
    final text = _dashText(context);
    final muted = _dashMuted(context);
    final surface = _dashSurface(context);
    final surfaceSoft = _dashSurfaceSoft(context);
    final border = _dashBorder(context);
    return AirmiusPanel(
      padding: const EdgeInsets.all(0),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(22),
        child: Stack(
          children: [
            Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      scheme.primary.withValues(
                        alpha: Theme.of(context).brightness == Brightness.dark
                            ? 0.30
                            : 0.18,
                      ),
                      scheme.secondary.withValues(
                        alpha: Theme.of(context).brightness == Brightness.dark
                            ? 0.16
                            : 0.12,
                      ),
                      scheme.tertiary.withValues(
                        alpha: Theme.of(context).brightness == Brightness.dark
                            ? 0.16
                            : 0.08,
                      ),
                      surface,
                    ],
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Eyebrow(t('dashboard')),
                            const SizedBox(height: 7),
                            Text(
                              '${t('dashboard.hello')} $userName',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: text,
                                fontSize: 28,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              t('dashboard.subtitle'),
                              style: TextStyle(
                                color: muted,
                                height: 1.45,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 10),
                      OutlinedButton.icon(
                        onPressed: onToggleCustomize,
                        icon: const Icon(Icons.tune_outlined, size: 18),
                        label: Text(
                          t('dashboard.customize'),
                          style: const TextStyle(fontWeight: FontWeight.w900),
                        ),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: text,
                          side: BorderSide(color: border),
                          backgroundColor: surface.withValues(alpha: 0.76),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 12,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                        ),
                      ),
                    ],
                  ),
                  if (showCustomize) ...[
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: surfaceSoft.withValues(alpha: 0.78),
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(color: border),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      t('dashboard.widgets'),
                                      style: TextStyle(
                                        color: text,
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      t('dashboard.widgetsBody'),
                                      style: TextStyle(
                                        color: muted,
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              TextButton(
                                onPressed: onShowAll,
                                child: Text(t('dashboard.showAll')),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              for (final widget in _visibleDashboardWidgets)
                                _WidgetToggle(
                                  item: widget,
                                  active: visibleWidgets.contains(widget.key),
                                  onTap: () => onToggleWidget(widget.key),
                                ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _WidgetToggle extends StatelessWidget {
  const _WidgetToggle({
    required this.item,
    required this.active,
    required this.onTap,
  });

  final _DashboardWidgetDef item;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final muted = _dashMuted(context);
    final surface = _dashSurface(context);
    final border = _dashBorder(context);
    final accent = Theme.of(context).colorScheme.primary;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
        decoration: BoxDecoration(
          color: active ? accent.withValues(alpha: 0.15) : surface,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
            color: active ? accent.withValues(alpha: 0.75) : border,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(item.icon, size: 16, color: active ? accent : muted),
            const SizedBox(width: 7),
            Text(
              t(item.label),
              style: TextStyle(
                color: active ? accent : muted,
                fontWeight: FontWeight.w900,
                fontSize: 12,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuickActions extends StatelessWidget {
  const _QuickActions({
    required this.onOpenTab,
    required this.onOpenModule,
    required this.onOpenDailyFlow,
  });

  final ValueChanged<AppTab> onOpenTab;
  final ValueChanged<ModuleDefinition> onOpenModule;
  final VoidCallback onOpenDailyFlow;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final text = _dashText(context);
    final muted = _dashMuted(context);
    final actions = [
      _QuickAction(
        title: t('dashboard.training'),
        subtitle: t('dashboard.document'),
        icon: Icons.assignment_turned_in_outlined,
        color: airmiusAccentColor(context),
        onTap: () => onOpenModule(_module('Events & Training')),
      ),
      _QuickAction(
        title: t('dashboard.files'),
        subtitle: t('dashboard.manage'),
        icon: Icons.folder_outlined,
        color: Theme.of(context).colorScheme.secondary,
        onTap: () => onOpenModule(_module('Dateien')),
      ),
      _QuickAction(
        title: t('dashboard.updates'),
        subtitle: t('dashboard.review'),
        icon: Icons.notifications_outlined,
        color: Theme.of(context).colorScheme.tertiary,
        onTap: () => onOpenTab(AppTab.updates),
      ),
      _QuickAction(
        title: t('dashboard.feed'),
        subtitle: t('dashboard.post'),
        icon: Icons.dynamic_feed_outlined,
        color: Theme.of(context).colorScheme.primary,
        onTap: () => onOpenTab(AppTab.feed),
      ),
      _QuickAction(
        title: t('dailyFlow.title'),
        subtitle: t('dailyFlow.open'),
        icon: Icons.today_outlined,
        color: airmiusAccentColor(context),
        onTap: onOpenDailyFlow,
      ),
    ];

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: actions.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 1.55,
      ),
      itemBuilder: (context, index) {
        final action = actions[index];
        return InkWell(
          onTap: action.onTap,
          borderRadius: BorderRadius.circular(20),
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: action.color.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: action.color.withValues(alpha: 0.34)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(action.icon, color: action.color, size: 24),
                const Spacer(),
                Text(
                  action.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: text, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 2),
                Text(
                  action.subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _StatsGrid extends StatelessWidget {
  const _StatsGrid({required this.stats});

  final List<_DashboardStat> stats;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth > 640 ? 4 : 2;
        final textScale = MediaQuery.textScalerOf(context).scale(1);
        final childAspectRatio = columns == 4
            ? (textScale > 1.2 ? 1.0 : 1.25)
            : constraints.maxWidth <= 480
            ? (textScale > 1.2 ? 0.78 : 1.05)
            : 1.2;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: stats.length,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: childAspectRatio,
          ),
          itemBuilder: (context, index) => _StatCard(stat: stats[index]),
        );
      },
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.stat});

  final _DashboardStat stat;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final text = _dashText(context);
    final muted = _dashMuted(context);
    return AirmiusPanel(
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  t(stat.label).toUpperCase(),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: muted,
                    fontSize: 10,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.7,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  stat.value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: text,
                    fontSize: 22,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  t(stat.meta),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: muted,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: stat.color.withValues(alpha: 0.20),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: stat.color.withValues(alpha: 0.35)),
            ),
            child: Icon(stat.icon, color: stat.color, size: 21),
          ),
        ],
      ),
    );
  }
}

class _DashboardWidgets extends StatelessWidget {
  const _DashboardWidgets({
    required this.visibleWidgets,
    required this.onOpenTab,
    required this.onOpenModule,
    required this.liveData,
  });

  final Set<String> visibleWidgets;
  final ValueChanged<AppTab> onOpenTab;
  final ValueChanged<ModuleDefinition> onOpenModule;
  final _DashboardLiveData? liveData;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final dashboardData = liveData;
    return Column(
      children: [
        if (visibleWidgets.contains('training')) ...[
          _TrainingWidget(
            onOpen: () => onOpenModule(_module('Events & Training')),
            liveData: liveData,
          ),
          const SizedBox(height: 14),
        ],
        if (visibleWidgets.contains('focus')) ...[
          _FocusWidget(
            onOpenTab: onOpenTab,
            onOpenModule: onOpenModule,
            liveData: liveData,
          ),
          const SizedBox(height: 14),
        ],
        if (AirmiusMvpSurface.showDeveloperSuites &&
            visibleWidgets.contains('nutrition')) ...[
          _CompactWidget(
            title: t('dashboard.nutrition'),
            eyebrow: t('dashboard.liveData'),
            action: t('dashboard.open'),
            icon: Icons.restaurant_menu_outlined,
            color: Theme.of(context).colorScheme.tertiary,
            metrics: [
              (
                liveData?.step('nutrition')?.progress.toString() ?? '—',
                t('dashboard.dailyScore'),
              ),
              (
                liveData?.step('hydration')?.progress.toString() ?? '—',
                t('dashboard.hydration'),
              ),
              (liveData?.step('nutrition')?.meta ?? '—', t('dashboard.plans')),
            ],
            onOpen: () => onOpenModule(_module('Ernährung')),
          ),
          const SizedBox(height: 14),
        ],
        if (visibleWidgets.contains('events')) ...[
          _ListWidget(
            title: t('dashboard.events'),
            eyebrow: liveData?.eventCountLabel(t) ?? t('dashboard.liveData'),
            action: t('dashboard.calendar'),
            icon: Icons.event_outlined,
            color: Theme.of(context).colorScheme.secondary,
            lines: liveData?.eventLines ?? const [],
            emptyLabel: t('dashboard.noLiveData'),
            onOpen: () => onOpenModule(_module('Events & Training')),
          ),
          const SizedBox(height: 14),
        ],
        if (AirmiusMvpSurface.showDeveloperSuites &&
            visibleWidgets.contains('sport_map')) ...[
          _CompactWidget(
            title: t('dashboard.sportMap'),
            eyebrow: t('dashboard.liveData'),
            action: t('dashboard.open'),
            icon: Icons.map_outlined,
            color: airmiusAccentColor(context),
            metrics: [
              (
                liveData?.step('route')?.progress.toString() ?? '—',
                t('dashboard.dailyScore'),
              ),
              (liveData?.step('route')?.meta ?? '—', t('dashboard.distance')),
              (liveData?.step('route')?.body ?? '—', t('dashboard.openStatus')),
            ],
            onOpen: () => onOpenModule(_module('Sportkarte')),
          ),
          const SizedBox(height: 14),
        ],
        if (visibleWidgets.contains('files')) ...[
          _CompactWidget(
            title: t('dashboard.files'),
            eyebrow: t('dashboard.storage'),
            action: t('dashboard.files'),
            icon: Icons.folder_outlined,
            color: Theme.of(context).colorScheme.tertiary,
            metrics: [
              (
                dashboardData == null
                    ? '—'
                    : _formatDashboardBytes(dashboardData.filesBytes),
                t('dashboard.storage'),
              ),
              (
                dashboardData == null ? '—' : '${dashboardData.filesCount}',
                t('dashboard.files'),
              ),
              (
                dashboardData == null ? '—' : t('dashboard.liveData'),
                t('dashboard.openStatus'),
              ),
            ],
            onOpen: () => onOpenModule(_module('Dateien')),
          ),
          const SizedBox(height: 14),
        ],
        if (visibleWidgets.contains('notifications')) ...[
          _ListWidget(
            title: t('dashboard.inbox'),
            eyebrow:
                liveData?.notificationCountLabel(t) ?? t('dashboard.liveData'),
            action: t('dashboard.open'),
            icon: Icons.notifications_outlined,
            color: Theme.of(context).colorScheme.tertiary,
            lines: liveData?.notificationLines ?? const [],
            emptyLabel: t('dashboard.noLiveData'),
            onOpen: () => onOpenTab(AppTab.updates),
          ),
        ],
      ],
    );
  }
}

class _TrainingWidget extends StatelessWidget {
  const _TrainingWidget({required this.onOpen, required this.liveData});

  final VoidCallback onOpen;
  final _DashboardLiveData? liveData;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final muted = _dashMuted(context);
    final training = liveData?.step('training');
    final progress = training?.progress;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _WidgetHeader(
            eyebrow: t('dashboard.liveData'),
            title: t('dashboard.training'),
            action: t('dashboard.open'),
            icon: Icons.running_with_errors_outlined,
            color: airmiusAccentColor(context),
            onOpen: onOpen,
          ),
          const SizedBox(height: 18),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              value: progress == null ? 0 : progress / 100,
              minHeight: 12,
              backgroundColor: _dashSurfaceSoft(context),
              valueColor: AlwaysStoppedAnimation<Color>(
                airmiusAccentColor(context),
              ),
            ),
          ),
          const SizedBox(height: 10),
          Text(
            training?.body ?? t('dashboard.noLiveData'),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(color: muted, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _MiniMetric(
                  value: training?.meta ?? '—',
                  label: t('dashboard.distance'),
                ),
              ),
              Expanded(
                child: _MiniMetric(
                  value: progress == null ? '—' : '$progress%',
                  label: t('dashboard.calories'),
                ),
              ),
              Expanded(
                child: _MiniMetric(
                  value: training == null ? '—' : t('dashboard.openStatus'),
                  label: t('dashboard.plans'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _FocusWidget extends StatelessWidget {
  const _FocusWidget({
    required this.onOpenTab,
    required this.onOpenModule,
    required this.liveData,
  });

  final ValueChanged<AppTab> onOpenTab;
  final ValueChanged<ModuleDefinition> onOpenModule;
  final _DashboardLiveData? liveData;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final liveSteps = liveData?.steps ?? const <_DashboardStep>[];
    final items = liveSteps.isEmpty
        ? [
            _FocusItem(
              title: t('dashboard.importantToday'),
              body: t('dashboard.noLiveData'),
              meta: '—',
              icon: Icons.check_circle_outline,
              onTap: () => onOpenModule(_module('Events & Training')),
            ),
          ]
        : liveSteps.take(3).map((step) {
            final action = switch (step.key) {
              'training' => () => onOpenModule(_module('Events & Training')),
              'route' => () => onOpenModule(_module('Sportkarte')),
              'nutrition' ||
              'hydration' => () => onOpenModule(_module('Ernährung')),
              'reminders' => () => onOpenTab(AppTab.updates),
              _ => () => onOpenModule(_module('Events & Training')),
            };
            final icon = switch (step.key) {
              'training' => Icons.assignment_turned_in_outlined,
              'route' => Icons.map_outlined,
              'nutrition' => Icons.restaurant_menu_outlined,
              'hydration' => Icons.water_drop_outlined,
              _ => Icons.notifications_outlined,
            };
            return _FocusItem(
              title: _dashboardStepTitle(t, step.key),
              body: step.body,
              meta: step.meta,
              icon: icon,
              onTap: action,
            );
          }).toList();
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: _WidgetTitle(
                  eyebrow: t('dashboard.importantToday'),
                  title: t('dashboard.nextSteps'),
                ),
              ),
              StatusPill('${items.length}', color: airmiusAccentColor(context)),
            ],
          ),
          const SizedBox(height: 12),
          for (final item in items) _FocusRow(item: item),
        ],
      ),
    );
  }
}

class _CompactWidget extends StatelessWidget {
  const _CompactWidget({
    required this.title,
    required this.eyebrow,
    required this.action,
    required this.icon,
    required this.color,
    required this.metrics,
    required this.onOpen,
  });

  final String title;
  final String eyebrow;
  final String action;
  final IconData icon;
  final Color color;
  final List<(String, String)> metrics;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final surfaceSoft = _dashSurfaceSoft(context);
    final border = _dashBorder(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _WidgetHeader(
            eyebrow: eyebrow,
            title: title,
            action: action,
            icon: icon,
            color: color,
            onOpen: onOpen,
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              for (final metric in metrics)
                Expanded(
                  child: _MiniMetric(value: metric.$1, label: metric.$2),
                ),
            ],
          ),
          const SizedBox(height: 16),
          Container(
            height: 84,
            decoration: BoxDecoration(
              color: surfaceSoft,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: border),
            ),
            child: Center(child: Icon(icon, color: color, size: 34)),
          ),
        ],
      ),
    );
  }
}

class _ListWidget extends StatelessWidget {
  const _ListWidget({
    required this.title,
    required this.eyebrow,
    required this.action,
    required this.icon,
    required this.color,
    required this.lines,
    required this.emptyLabel,
    required this.onOpen,
  });

  final String title;
  final String eyebrow;
  final String action;
  final IconData icon;
  final Color color;
  final List<String> lines;
  final String emptyLabel;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final text = _dashText(context);
    final border = _dashBorder(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _WidgetHeader(
            eyebrow: eyebrow,
            title: title,
            action: action,
            icon: icon,
            color: color,
            onOpen: onOpen,
          ),
          const SizedBox(height: 12),
          if (lines.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 14),
              child: Text(
                emptyLabel,
                style: TextStyle(
                  color: _dashMuted(context),
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          for (final line in lines)
            Container(
              padding: const EdgeInsets.symmetric(vertical: 12),
              decoration: BoxDecoration(
                border: Border(bottom: BorderSide(color: border)),
              ),
              child: Text(
                line,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(color: text, fontWeight: FontWeight.w800),
              ),
            ),
        ],
      ),
    );
  }
}

class _WidgetHeader extends StatelessWidget {
  const _WidgetHeader({
    required this.eyebrow,
    required this.title,
    required this.action,
    required this.icon,
    required this.color,
    required this.onOpen,
  });

  final String eyebrow;
  final String title;
  final String action;
  final IconData icon;
  final Color color;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: color),
        const SizedBox(width: 10),
        Expanded(
          child: _WidgetTitle(eyebrow: eyebrow, title: title),
        ),
        OutlinedButton(onPressed: onOpen, child: Text(action)),
      ],
    );
  }
}

class _WidgetTitle extends StatelessWidget {
  const _WidgetTitle({required this.eyebrow, required this.title});

  final String eyebrow;
  final String title;

  @override
  Widget build(BuildContext context) {
    final text = _dashText(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          eyebrow.toUpperCase(),
          style: TextStyle(
            color: Theme.of(context).colorScheme.primary,
            fontSize: 11,
            fontWeight: FontWeight.w900,
            letterSpacing: 0.7,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: text,
            fontSize: 19,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    );
  }
}

class _MiniMetric extends StatelessWidget {
  const _MiniMetric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    final text = _dashText(context);
    final muted = _dashMuted(context);
    return Column(
      children: [
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: text,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: muted,
            fontSize: 11,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _FocusRow extends StatelessWidget {
  const _FocusRow({required this.item});

  final _FocusItem item;

  @override
  Widget build(BuildContext context) {
    final text = _dashText(context);
    final muted = _dashMuted(context);
    final surfaceSoft = _dashSurfaceSoft(context);
    final border = _dashBorder(context);
    final accent = Theme.of(context).colorScheme.primary;
    return InkWell(
      onTap: item.onTap,
      borderRadius: BorderRadius.circular(16),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: surfaceSoft,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: border),
              ),
              child: Icon(item.icon, color: accent),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: text, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    item.body,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Text(
              item.meta,
              style: TextStyle(
                color: muted,
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuickAction {
  const _QuickAction({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;
}

class _DashboardStat {
  const _DashboardStat({
    required this.label,
    required this.value,
    required this.meta,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final String meta;
  final IconData icon;
  final Color color;
}

class _DashboardWidgetDef {
  const _DashboardWidgetDef({
    required this.key,
    required this.label,
    required this.icon,
  });

  final String key;
  final String label;
  final IconData icon;
}

class _FocusItem {
  const _FocusItem({
    required this.title,
    required this.body,
    required this.meta,
    required this.icon,
    required this.onTap,
  });

  final String title;
  final String body;
  final String meta;
  final IconData icon;
  final VoidCallback onTap;
}

class _DashboardStep {
  const _DashboardStep({
    required this.key,
    required this.title,
    required this.body,
    required this.meta,
    required this.progress,
    required this.cta,
  });

  final String key;
  final String title;
  final String body;
  final String meta;
  final int progress;
  final String cta;

  factory _DashboardStep.fromJson(JsonMap json) {
    return _DashboardStep(
      key: '${json['key'] ?? ''}',
      title: '${json['title'] ?? ''}',
      body: '${json['body'] ?? ''}',
      meta: '${json['meta'] ?? ''}',
      progress: _safeDashboardInt(json['progress']).clamp(0, 100),
      cta: '${json['cta'] ?? ''}',
    );
  }
}

class _DashboardLiveData {
  const _DashboardLiveData({
    required this.score,
    required this.steps,
    required this.filesCount,
    required this.filesBytes,
  });

  final int score;
  final List<_DashboardStep> steps;
  final int filesCount;
  final int filesBytes;

  factory _DashboardLiveData.fromJson(JsonMap json) {
    final rawSteps = json['steps'];
    final parsedSteps = rawSteps is List
        ? rawSteps
              .whereType<Map>()
              .map(
                (item) => _DashboardStep.fromJson(item.cast<String, dynamic>()),
              )
              .toList(growable: false)
        : const <_DashboardStep>[];
    return _DashboardLiveData(
      score: _safeDashboardInt(json['score']).clamp(0, 100),
      steps: parsedSteps,
      filesCount: _safeDashboardInt(
        json['files'] is JsonMap
            ? (json['files'] as JsonMap)['count']
            : json['file_count'],
      ),
      filesBytes: _safeDashboardInt(
        json['files'] is JsonMap
            ? (json['files'] as JsonMap)['bytes']
            : json['file_bytes'],
      ),
    );
  }

  _DashboardStep? step(String key) {
    for (final item in steps) {
      if (item.key == key) return item;
    }
    return null;
  }

  List<String> get eventLines => steps
      .where((step) => step.key == 'training' || step.key == 'reminders')
      .map(
        (step) => [
          step.body,
          step.meta,
        ].where((part) => part.trim().isNotEmpty).join(' · '),
      )
      .where((line) => line.trim().isNotEmpty)
      .take(3)
      .toList(growable: false);

  List<String> get notificationLines => steps
      .where((step) => step.key == 'reminders')
      .map(
        (step) => [
          step.body,
          step.meta,
        ].where((part) => part.trim().isNotEmpty).join(' · '),
      )
      .where((line) => line.trim().isNotEmpty)
      .take(3)
      .toList(growable: false);

  String eventCountLabel(String Function(String) t) {
    final count = eventLines.length;
    return count == 0 ? t('dashboard.liveData') : '$count';
  }

  String notificationCountLabel(String Function(String) t) {
    final count = notificationLines.length;
    return count == 0 ? t('dashboard.liveData') : '$count';
  }
}

String _formatDashboardBytes(int bytes) {
  if (bytes <= 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  var value = bytes.toDouble();
  var unit = 0;
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit += 1;
  }
  return '${value.toStringAsFixed(unit == 0 ? 0 : 2)} ${units[unit]}';
}

int _safeDashboardInt(Object? value) {
  if (value is int) return value;
  if (value is num) return value.round();
  return int.tryParse('$value') ?? 0;
}

String _dashboardStepTitle(String Function(String) t, String key) {
  return switch (key) {
    'training' => t('dashboard.training'),
    'route' => t('dashboard.sportMap'),
    'nutrition' => t('dashboard.nutrition'),
    'hydration' => t('dashboard.hydration'),
    'reminders' => t('dashboard.inbox'),
    _ => t('dashboard.importantToday'),
  };
}

const _dashboardWidgets = [
  _DashboardWidgetDef(
    key: 'training',
    label: 'dashboard.training',
    icon: Icons.directions_run_outlined,
  ),
  _DashboardWidgetDef(
    key: 'focus',
    label: 'dashboard.importantToday',
    icon: Icons.bolt_outlined,
  ),
  _DashboardWidgetDef(
    key: 'nutrition',
    label: 'dashboard.nutrition',
    icon: Icons.restaurant_menu_outlined,
  ),
  _DashboardWidgetDef(
    key: 'events',
    label: 'dashboard.events',
    icon: Icons.calendar_month_outlined,
  ),
  _DashboardWidgetDef(
    key: 'sport_map',
    label: 'dashboard.sportMap',
    icon: Icons.map_outlined,
  ),
  _DashboardWidgetDef(
    key: 'files',
    label: 'dashboard.files',
    icon: Icons.folder_outlined,
  ),
  _DashboardWidgetDef(
    key: 'notifications',
    label: 'dashboard.inbox',
    icon: Icons.notifications_outlined,
  ),
];

Iterable<_DashboardWidgetDef> get _visibleDashboardWidgets => _dashboardWidgets
    .where((widget) => AirmiusMvpSurface.isDashboardWidgetVisible(widget.key));

List<_DashboardStat> _dashboardStats(
  BuildContext context,
  AirmiusScope scope,
  _DashboardLiveData? liveData,
) {
  final training = liveData?.step('training');
  final nutrition = liveData?.step('nutrition');
  final hydration = liveData?.step('hydration');
  String percent(_DashboardStep? step) =>
      step == null ? '—' : '${step.progress}%';
  String meta(_DashboardStep? step) => step?.meta.trim().isNotEmpty == true
      ? step!.meta
      : scope.t('dashboard.noLiveData');
  return [
    _DashboardStat(
      label: 'dashboard.trainingProgress',
      value: percent(training),
      meta: meta(training),
      icon: Icons.directions_run_outlined,
      color: airmiusAccentColor(context),
    ),
    _DashboardStat(
      label: 'dashboard.dailyScore',
      value: liveData == null ? '—' : '${liveData.score}%',
      meta: scope.t('dashboard.liveData'),
      icon: Icons.trending_up_outlined,
      color: Theme.of(context).colorScheme.tertiary,
    ),
    _DashboardStat(
      label: 'dashboard.nutritionProgress',
      value: percent(nutrition),
      meta: meta(nutrition),
      icon: Icons.restaurant_menu_outlined,
      color: Theme.of(context).colorScheme.secondary,
    ),
    _DashboardStat(
      label: 'dashboard.hydrationProgress',
      value: percent(hydration),
      meta: meta(hydration),
      icon: Icons.water_drop_outlined,
      color: Theme.of(context).colorScheme.tertiary,
    ),
  ];
}

ModuleDefinition _module(String title) {
  return appModules.firstWhere(
    (module) => module.title == title,
    orElse: () => appModules.first,
  );
}
