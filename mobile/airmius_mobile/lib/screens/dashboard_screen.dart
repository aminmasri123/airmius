import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_mvp_surface.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/app_tab.dart';
import '../models/module_definition.dart';
import '../widgets/airmius_widgets.dart';

Color _dashText(BuildContext context) {
  return Theme.of(context).textTheme.bodyLarge?.color ?? AirmiusColors.text;
}

Color _dashMuted(BuildContext context) {
  return Theme.of(context).textTheme.bodyMedium?.color ?? AirmiusColors.muted;
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
  final Set<String> _visibleWidgets = {
    for (final widget in _dashboardWidgets)
      if (AirmiusMvpSurface.isDashboardWidgetVisible(widget.key)) widget.key,
  };

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final authState = AirmiusServicesScope.of(context).authState;
    final firstName = authState.user?.firstName?.trim();
    final userName = firstName != null && firstName.isNotEmpty
        ? firstName
        : authState.user?.name ?? 'Sportler';

    return PageFrame(
      title: scope.t('dashboard'),
      subtitle:
          'Deine wichtigsten Werte, Aufgaben und Schnellstarts auf einen Blick.',
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
          ),
          const SizedBox(height: 14),
          _StatsGrid(stats: _stats),
          const SizedBox(height: 14),
          _DashboardWidgets(
            visibleWidgets: _visibleWidgets,
            onOpenTab: widget.onOpenTab,
            onOpenModule: widget.onOpenModule,
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
                      AirmiusColors.pink.withValues(
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
                            const Eyebrow('Dashboard'),
                            const SizedBox(height: 7),
                            Text(
                              'Hallo $userName',
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
                              'Deine wichtigsten Werte, Aufgaben und Schnellstarts auf einen Blick.',
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
                        label: const Text(
                          'Anpassen',
                          style: TextStyle(fontWeight: FontWeight.w900),
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
                                      'Widgets',
                                      style: TextStyle(
                                        color: text,
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      'Wähle aus, was sichtbar ist.',
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
                                child: const Text('Alles zeigen'),
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
              item.label,
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
  const _QuickActions({required this.onOpenTab, required this.onOpenModule});

  final ValueChanged<AppTab> onOpenTab;
  final ValueChanged<ModuleDefinition> onOpenModule;

  @override
  Widget build(BuildContext context) {
    final text = _dashText(context);
    final muted = _dashMuted(context);
    final actions = [
      _QuickAction(
        title: 'Training',
        subtitle: 'Dokumentieren',
        icon: Icons.assignment_turned_in_outlined,
        color: AirmiusColors.blue,
        onTap: () => onOpenModule(_module('Events & Training')),
      ),
      _QuickAction(
        title: 'Dateien',
        subtitle: 'Verwalten',
        icon: Icons.folder_outlined,
        color: AirmiusColors.green,
        onTap: () => onOpenModule(_module('Dateien')),
      ),
      _QuickAction(
        title: 'Updates',
        subtitle: 'Prüfen',
        icon: Icons.notifications_outlined,
        color: AirmiusColors.amber,
        onTap: () => onOpenTab(AppTab.updates),
      ),
      _QuickAction(
        title: 'Feed',
        subtitle: 'Posten',
        icon: Icons.dynamic_feed_outlined,
        color: AirmiusColors.pink,
        onTap: () => onOpenTab(AppTab.feed),
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
        final childAspectRatio = columns == 4 ? 1.25 : 1.35;
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
                  stat.label.toUpperCase(),
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
                  stat.meta,
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
  });

  final Set<String> visibleWidgets;
  final ValueChanged<AppTab> onOpenTab;
  final ValueChanged<ModuleDefinition> onOpenModule;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        if (visibleWidgets.contains('training')) ...[
          _TrainingWidget(
            onOpen: () => onOpenModule(_module('Events & Training')),
          ),
          const SizedBox(height: 14),
        ],
        if (visibleWidgets.contains('focus')) ...[
          _FocusWidget(onOpenTab: onOpenTab, onOpenModule: onOpenModule),
          const SizedBox(height: 14),
        ],
        if (AirmiusMvpSurface.showDeveloperSuites &&
            visibleWidgets.contains('nutrition')) ...[
          _CompactWidget(
            title: 'Ernährung',
            eyebrow: 'Heute',
            action: 'Öffnen',
            icon: Icons.restaurant_menu_outlined,
            color: AirmiusColors.amber,
            metrics: const [
              ('1840', 'kcal'),
              ('120 g', 'Protein'),
              ('3', 'Mahlzeiten'),
            ],
            onOpen: () => onOpenModule(_module('Ernährung')),
          ),
          const SizedBox(height: 14),
        ],
        if (visibleWidgets.contains('events')) ...[
          _ListWidget(
            title: 'Termine',
            eyebrow: '5 geplant',
            action: 'Kalender',
            icon: Icons.event_outlined,
            color: AirmiusColors.green,
            lines: const [
              'Training heute 18:30 - Sporthalle',
              'Teammeeting morgen 19:00',
              'Spieltag Samstag 14:00',
            ],
            onOpen: () => onOpenModule(_module('Events')),
          ),
          const SizedBox(height: 14),
        ],
        if (AirmiusMvpSurface.showDeveloperSuites &&
            visibleWidgets.contains('sport_map')) ...[
          _CompactWidget(
            title: 'Sportkarte',
            eyebrow: 'Routen & Orte',
            action: 'Karte',
            icon: Icons.map_outlined,
            color: AirmiusColors.blue,
            metrics: const [('8', 'Routen'), ('4', 'Tracks'), ('6', 'Plaetze')],
            onOpen: () => onOpenModule(_module('Sportkarte')),
          ),
          const SizedBox(height: 14),
        ],
        if (visibleWidgets.contains('files')) ...[
          _CompactWidget(
            title: 'Dateien',
            eyebrow: 'Speicher',
            action: 'Dateien',
            icon: Icons.folder_outlined,
            color: AirmiusColors.pink,
            metrics: const [
              ('1.8 GB', 'frei'),
              ('24', 'Dateien'),
              ('4', 'Freigaben'),
            ],
            onOpen: () => onOpenModule(_module('Dateien')),
          ),
          const SizedBox(height: 14),
        ],
        if (visibleWidgets.contains('notifications')) ...[
          _ListWidget(
            title: 'Inbox',
            eyebrow: '3 ungelesen',
            action: 'Öffnen',
            icon: Icons.notifications_outlined,
            color: AirmiusColors.amber,
            lines: const [
              'Neue Reaktion auf deinen Beitrag',
              'Vereinsanfrage wartet',
              'Trainingserinnerung für heute',
            ],
            onOpen: () => onOpenTab(AppTab.updates),
          ),
        ],
      ],
    );
  }
}

class _TrainingWidget extends StatelessWidget {
  const _TrainingWidget({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final muted = _dashMuted(context);
    final bars = [35, 72, 48, 88, 42, 64, 28];
    final labels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _WidgetHeader(
            eyebrow: 'Wochenübersicht',
            title: 'Training',
            action: 'Öffnen',
            icon: Icons.running_with_errors_outlined,
            color: AirmiusColors.blue,
            onOpen: onOpen,
          ),
          const SizedBox(height: 18),
          SizedBox(
            height: 150,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                for (var index = 0; index < bars.length; index++) ...[
                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Expanded(
                          child: Align(
                            alignment: Alignment.bottomCenter,
                            child: FractionallySizedBox(
                              heightFactor: bars[index] / 100,
                              widthFactor: 0.72,
                              child: DecoratedBox(
                                decoration: BoxDecoration(
                                  gradient: const LinearGradient(
                                    begin: Alignment.bottomCenter,
                                    end: Alignment.topCenter,
                                    colors: [
                                      AirmiusColors.blueDeep,
                                      AirmiusColors.blue,
                                    ],
                                  ),
                                  borderRadius: BorderRadius.circular(999),
                                ),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          labels[index],
                          style: TextStyle(
                            color: muted,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (index != bars.length - 1) const SizedBox(width: 6),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          const Row(
            children: [
              Expanded(
                child: _MiniMetric(value: '12.4 km', label: 'Distanz'),
              ),
              Expanded(
                child: _MiniMetric(value: '1840', label: 'Kalorien'),
              ),
              Expanded(
                child: _MiniMetric(value: '2', label: 'Plaene'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _FocusWidget extends StatelessWidget {
  const _FocusWidget({required this.onOpenTab, required this.onOpenModule});

  final ValueChanged<AppTab> onOpenTab;
  final ValueChanged<ModuleDefinition> onOpenModule;

  @override
  Widget build(BuildContext context) {
    final items = [
      _FocusItem(
        title: 'Training dokumentieren',
        body: 'Heute offen',
        meta: 'Jetzt',
        icon: Icons.assignment_turned_in_outlined,
        onTap: () => onOpenModule(_module('Events & Training')),
      ),
      _FocusItem(
        title: 'Feed prüfen',
        body: 'Kommentare & Reaktionen',
        meta: '3 neu',
        icon: Icons.dynamic_feed_outlined,
        onTap: () => onOpenTab(AppTab.feed),
      ),
      _FocusItem(
        title: 'Verein ansehen',
        body: 'Anfrage und Profil',
        meta: 'Offen',
        icon: Icons.groups_outlined,
        onTap: () => onOpenTab(AppTab.clubs),
      ),
    ];
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Expanded(
                child: _WidgetTitle(
                  eyebrow: 'Heute wichtig',
                  title: 'Naechste Schritte',
                ),
              ),
              StatusPill('${items.length}', color: AirmiusColors.blue),
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
    required this.onOpen,
  });

  final String title;
  final String eyebrow;
  final String action;
  final IconData icon;
  final Color color;
  final List<String> lines;
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

const _dashboardWidgets = [
  _DashboardWidgetDef(
    key: 'training',
    label: 'Training',
    icon: Icons.directions_run_outlined,
  ),
  _DashboardWidgetDef(
    key: 'focus',
    label: 'Heute wichtig',
    icon: Icons.bolt_outlined,
  ),
  _DashboardWidgetDef(
    key: 'nutrition',
    label: 'Ernährung',
    icon: Icons.restaurant_menu_outlined,
  ),
  _DashboardWidgetDef(
    key: 'events',
    label: 'Termine',
    icon: Icons.calendar_month_outlined,
  ),
  _DashboardWidgetDef(
    key: 'sport_map',
    label: 'Sportkarte',
    icon: Icons.map_outlined,
  ),
  _DashboardWidgetDef(
    key: 'files',
    label: 'Dateien',
    icon: Icons.folder_outlined,
  ),
  _DashboardWidgetDef(
    key: 'notifications',
    label: 'Inbox',
    icon: Icons.notifications_outlined,
  ),
];

Iterable<_DashboardWidgetDef> get _visibleDashboardWidgets => _dashboardWidgets
    .where((widget) => AirmiusMvpSurface.isDashboardWidgetVisible(widget.key));

const _stats = [
  _DashboardStat(
    label: 'Trainings diese Woche',
    value: '4',
    meta: '+12% zur Vorwoche',
    icon: Icons.directions_run_outlined,
    color: AirmiusColors.blue,
  ),
  _DashboardStat(
    label: 'Trainingszeit',
    value: '320 min',
    meta: '5 aktive Tage',
    icon: Icons.timer_outlined,
    color: AirmiusColors.green,
  ),
  _DashboardStat(
    label: 'Aktivitaetswert',
    value: '82%',
    meta: '7 Tage Serie',
    icon: Icons.trending_up_outlined,
    color: AirmiusColors.pink,
  ),
  _DashboardStat(
    label: 'Speicher frei',
    value: '1.8 GB',
    meta: '24 Dateien',
    icon: Icons.storage_outlined,
    color: AirmiusColors.amber,
  ),
];

ModuleDefinition _module(String title) {
  return appModules.firstWhere(
    (module) => module.title == title,
    orElse: () => appModules.first,
  );
}
