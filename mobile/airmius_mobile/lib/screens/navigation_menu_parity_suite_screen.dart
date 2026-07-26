import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class NavigationMenuParitySuiteScreen extends StatefulWidget {
  const NavigationMenuParitySuiteScreen({super.key});

  @override
  State<NavigationMenuParitySuiteScreen> createState() =>
      _NavigationMenuParitySuiteScreenState();
}

class _NavigationMenuParitySuiteScreenState
    extends State<NavigationMenuParitySuiteScreen> {
  String _role = 'Player';
  String _workspace = 'ZBB';
  int _bottomIndex = 0;
  bool _showClubTools = true;
  bool _showAdminTools = false;
  bool _compactMode = true;

  static const _roles = ['Player', 'Verein', 'Trainer', 'Admin', 'Guardian'];
  static const _workspaces = ['ZBB', 'Privat', 'Team U18', 'Airmius'];

  @override
  Widget build(BuildContext context) {
    final visibleGroups = _groups.where((group) {
      if (group.adminOnly && !_showAdminTools) return false;
      if (group.clubOnly && !_showClubTools) return false;
      if (group.roles.isNotEmpty && !group.roles.contains(_role)) return false;
      return true;
    }).toList();

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const AirmiusLogo(compact: true),
        actions: [
          IconButton(
            onPressed: () => openUiAction(
              context,
              title: 'Header Suche',
              body:
                  'Personen, Vereine, Teams, Dateien, Kurse, Events und Marketplace-Objekte werden später über Laravel API gesucht.',
              status: 'Search',
              icon: Icons.search_outlined,
            ),
            icon: const Icon(Icons.search_outlined),
          ),
          IconButton(
            onPressed: () => openUiAction(
              context,
              title: 'Benachrichtigungen',
              body:
                  'Chat, Vereinsanfragen, Events, Zahlungen, Guardian-Freigaben und Systemhinweise als Header-Icon.',
              status: 'Notify',
              icon: Icons.notifications_none_outlined,
            ),
            icon: const Icon(Icons.notifications_none_outlined),
          ),
        ],
      ),
      bottomNavigationBar: _BottomPreview(
        currentIndex: _bottomIndex,
        onChanged: (value) => setState(() => _bottomIndex = value),
      ),
      body: SafeArea(
        child: PageFrame(
          title: 'Navigation Menu Parity',
          subtitle: 'Mobile Web-App Navigation als Flutter-Shell.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                role: _role,
                workspace: _workspace,
                compactMode: _compactMode,
                groupCount: visibleGroups.length,
              ),
              const SizedBox(height: 16),
              _SearchPreview(
                onOpen: () => openUiAction(
                  context,
                  title: 'Globale Suche',
                  body:
                      'Header-Suche zeigt später Vorschläge für Personen, Teams, Vereine, Dateien, Events, Kurse und Produkte.',
                  status: 'Header',
                  icon: Icons.manage_search_outlined,
                ),
              ),
              const SizedBox(height: 16),
              _ContextPanel(
                role: _role,
                workspace: _workspace,
                roles: _roles,
                workspaces: _workspaces,
                onRole: (value) => setState(() => _role = value),
                onWorkspace: (value) => setState(() => _workspace = value),
              ),
              const SizedBox(height: 16),
              _SwitchPanel(
                showClubTools: _showClubTools,
                showAdminTools: _showAdminTools,
                compactMode: _compactMode,
                onClub: (value) => setState(() => _showClubTools = value),
                onAdmin: (value) => setState(() => _showAdminTools = value),
                onCompact: (value) => setState(() => _compactMode = value),
              ),
              const SizedBox(height: 16),
              for (final group in visibleGroups) ...[
                _MenuGroupCard(group: group, compactMode: _compactMode),
                const SizedBox(height: 12),
              ],
              if (visibleGroups.isEmpty)
                const EmptyPanel(
                  'Keine Menübereiche für diese Rolle sichtbar.',
                ),
              const SizedBox(height: 4),
              _NavigationChecklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'Navigation Parity',
                  body:
                      'Header, Suche, Sidebar/Drawer, Modulgruppen, Rollenfilter, Workspace-Kontext und Bottom-Navigation sind als mobile UI-Struktur vorbereitet.',
                  status: 'Navigation',
                  icon: Icons.fact_check_outlined,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({
    required this.role,
    required this.workspace,
    required this.compactMode,
    required this.groupCount,
  });

  final String role;
  final String workspace;
  final bool compactMode;
  final int groupCount;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('APP SHELL'),
          const SizedBox(height: 8),
          Text(
            'Die Web-App Navigation bleibt wiedererkennbar.',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Flutter bekommt dieselbe Logik für Logo, Header-Suche, Workspace, Rollen, Modulgruppen, Bottom Navigation, Badges und mobile Drawer-Struktur.',
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Metric(value: role, label: 'Rolle'),
              _Metric(value: workspace, label: 'Workspace'),
              _Metric(value: '$groupCount', label: 'Menuegruppen'),
              _Metric(
                value: compactMode ? 'Kompakt' : 'Detail',
                label: 'Mobile Modus',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SearchPreview extends StatelessWidget {
  const _SearchPreview({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        children: [
          Icon(Icons.search_outlined, color: airmiusAccentColor(context)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Suche nach Personen, Teams, Vereine',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  'Header-Suche mit Typeahead, Result-Typen und leerem Zustand.',
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onOpen,
            icon: Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
          ),
        ],
      ),
    );
  }
}

class _ContextPanel extends StatelessWidget {
  const _ContextPanel({
    required this.role,
    required this.workspace,
    required this.roles,
    required this.workspaces,
    required this.onRole,
    required this.onWorkspace,
  });

  final String role;
  final String workspace;
  final List<String> roles;
  final List<String> workspaces;
  final ValueChanged<String> onRole;
  final ValueChanged<String> onWorkspace;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Rollen- und Workspace-Kontext',
      subtitle:
          'So entscheidet die App später, welche Module im Menue sichtbar sind.',
      children: [
        const Eyebrow('Rolle'),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: roles
              .map(
                (item) => ChoiceChip(
                  selected: role == item,
                  label: Text(item),
                  onSelected: (_) => onRole(item),
                  selectedColor: airmiusAccentColor(
                    context,
                  ).withValues(alpha: .25),
                  backgroundColor: airmiusSurfaceSoftColor(context),
                  side: BorderSide(
                    color: role == item
                        ? airmiusAccentColor(context)
                        : airmiusBorderColor(context),
                  ),
                  labelStyle: TextStyle(
                    color: role == item
                        ? airmiusTextColor(context)
                        : airmiusMutedColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              )
              .toList(),
        ),
        const SizedBox(height: 14),
        const Eyebrow('Workspace'),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: workspaces
              .map(
                (item) => ChoiceChip(
                  selected: workspace == item,
                  label: Text(item),
                  onSelected: (_) => onWorkspace(item),
                  selectedColor: Theme.of(
                    context,
                  ).colorScheme.secondary.withValues(alpha: .2),
                  backgroundColor: airmiusSurfaceSoftColor(context),
                  side: BorderSide(
                    color: workspace == item
                        ? Theme.of(context).colorScheme.secondary
                        : airmiusBorderColor(context),
                  ),
                  labelStyle: TextStyle(
                    color: workspace == item
                        ? airmiusTextColor(context)
                        : airmiusMutedColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              )
              .toList(),
        ),
      ],
    );
  }
}

class _SwitchPanel extends StatelessWidget {
  const _SwitchPanel({
    required this.showClubTools,
    required this.showAdminTools,
    required this.compactMode,
    required this.onClub,
    required this.onAdmin,
    required this.onCompact,
  });

  final bool showClubTools;
  final bool showAdminTools;
  final bool compactMode;
  final ValueChanged<bool> onClub;
  final ValueChanged<bool> onAdmin;
  final ValueChanged<bool> onCompact;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Menue-Sichtbarkeit',
      subtitle:
          'Rollen, Vereinskontext und Mobile-Modus steuern die Navigation.',
      children: [
        _SwitchLine(
          title: 'Vereinsmodule anzeigen',
          value: showClubTools,
          onChanged: onClub,
        ),
        _SwitchLine(
          title: 'Adminmodule anzeigen',
          value: showAdminTools,
          onChanged: onAdmin,
        ),
        _SwitchLine(
          title: 'Kompakte Mobile-Liste',
          value: compactMode,
          onChanged: onCompact,
        ),
      ],
    );
  }
}

class _SwitchLine extends StatelessWidget {
  const _SwitchLine({
    required this.title,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Switch(
            value: value,
            activeThumbColor: Theme.of(context).colorScheme.secondary,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }
}

class _MenuGroupCard extends StatelessWidget {
  const _MenuGroupCard({required this.group, required this.compactMode});

  final _MenuGroup group;
  final bool compactMode;

  @override
  Widget build(BuildContext context) {
    final color = airmiusSemanticColor(context, group.color);
    return AirmiusPanel(
      borderColor: color.withValues(alpha: .5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(15),
                  border: Border.all(color: color.withValues(alpha: .55)),
                ),
                child: Icon(group.icon, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      group.title,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      group.body,
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill('${group.items.length}', color: color),
            ],
          ),
          const SizedBox(height: 12),
          if (compactMode)
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: group.items
                  .map((item) => StatusPill(item, color: color))
                  .toList(),
            )
          else
            ...group.items.map((item) => _MenuLine(label: item, color: color)),
          const SizedBox(height: 14),
          AirmiusButton(
            label: '${group.title} öffnen',
            icon: group.icon,
            secondary: true,
            onPressed: () => openUiAction(
              context,
              title: group.title,
              body:
                  'Navigationsgruppe ${group.title}: ${group.items.join(', ')}. Später verbunden mit Laravel-Routen, Rollen und Deep Links.',
              status: 'Navigation',
              icon: group.icon,
            ),
          ),
        ],
      ),
    );
  }
}

class _MenuLine extends StatelessWidget {
  const _MenuLine({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Icon(Icons.circle, size: 8, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
        ],
      ),
    );
  }
}

class _BottomPreview extends StatelessWidget {
  const _BottomPreview({required this.currentIndex, required this.onChanged});

  final int currentIndex;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return NavigationBar(
      backgroundColor:
          Theme.of(context).appBarTheme.backgroundColor ??
          airmiusSurfaceColor(context),
      indicatorColor: airmiusAccentColor(context).withValues(alpha: .25),
      selectedIndex: currentIndex,
      onDestinationSelected: onChanged,
      destinations: const [
        NavigationDestination(
          icon: Icon(Icons.dashboard_outlined),
          label: 'Home',
        ),
        NavigationDestination(
          icon: Icon(Icons.groups_outlined),
          label: 'Vereine',
        ),
        NavigationDestination(
          icon: Icon(Icons.notifications_none_outlined),
          label: 'Updates',
        ),
        NavigationDestination(
          icon: Icon(Icons.person_outline),
          label: 'Profil',
        ),
      ],
    );
  }
}

class _NavigationChecklist extends StatelessWidget {
  const _NavigationChecklist({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Navigation-Paritaet',
      subtitle: 'Was von der mobilen Web-App übernommen wird.',
      children: [
        const _CheckLine(
          'Airmius-Logo, dunkler Header, Suchfeld und Benachrichtigungen bleiben sichtbar.',
        ),
        const _CheckLine(
          'Sidebar/Drawer-Gruppen werden als mobile Karten und später als Drawer-Struktur abgebildet.',
        ),
        const _CheckLine(
          'Bottom Navigation bleibt auf die häufigsten Mobile-Aktionen reduziert.',
        ),
        const _CheckLine(
          'Rollen und Workspaces steuern Sichtbarkeit, Badges, Deep Links und Empty States.',
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: 'Navigation prüfen',
          icon: Icons.fact_check_outlined,
          onPressed: onOpen,
        ),
      ],
    );
  }
}

class _CheckLine extends StatelessWidget {
  const _CheckLine(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.check_circle_outline,
            color: Theme.of(context).colorScheme.secondary,
            size: 19,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
                height: 1.35,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context).withValues(alpha: .55),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _MenuGroup {
  const _MenuGroup({
    required this.title,
    required this.body,
    required this.items,
    required this.icon,
    required this.color,
    this.roles = const [],
    this.clubOnly = false,
    this.adminOnly = false,
  });

  final String title;
  final String body;
  final List<String> items;
  final IconData icon;
  final Color color;
  final List<String> roles;
  final bool clubOnly;
  final bool adminOnly;
}

const _groups = <_MenuGroup>[
  _MenuGroup(
    title: 'Start & Alltag',
    body: 'Dashboard, Gastseite, Arbeitsbereiche und persoenlicher Tagesfluss.',
    items: ['Gastseite', 'Dashboard', 'Arbeitsbereiche', 'Profil'],
    icon: Icons.dashboard_outlined,
    color: AirmiusColors.blue,
  ),
  _MenuGroup(
    title: 'Vereine & Teams',
    body: 'Club-Profil, Teams, Mitglieder, Rollen, Antraege und Kommunikation.',
    items: [
      'Vereine & Teams',
      'Mitgliedsantrag',
      'Teams',
      'Mitglieder',
      'Rollen',
    ],
    icon: Icons.groups_outlined,
    color: AirmiusColors.green,
    clubOnly: true,
  ),
  _MenuGroup(
    title: 'Training & Sport',
    body: 'Events, Trainingsplaene, Logs, Sportkarte und Coach-Kontext.',
    items: [
      'Events & Training',
      'Trainingsplaene',
      'Training Logs',
      'Sportkarte',
      'Sportprofil',
    ],
    icon: Icons.fitness_center_outlined,
    color: AirmiusColors.green,
    roles: ['Player', 'Trainer', 'Verein'],
  ),
  _MenuGroup(
    title: 'Community',
    body:
        'Feed, Freunde, Fahrgemeinschaften, Nachrichten und Benachrichtigungen.',
    items: ['Feed', 'Freunde', 'Fahrgemeinschaften', 'Nachrichten', 'Updates'],
    icon: Icons.forum_outlined,
    color: AirmiusColors.blue,
  ),
  _MenuGroup(
    title: 'Dateien & Lernen',
    body: 'Dateimanager, Badges, Kurse, Zertifikate und Content.',
    items: [
      'Dateien',
      'Meine Badges',
      'Meine Kurse',
      'Zertifikate',
      'Top Inhalte',
    ],
    icon: Icons.folder_outlined,
    color: AirmiusColors.amber,
  ),
  _MenuGroup(
    title: 'Commerce',
    body: 'Marketplace, Warenkorb, Bestellungen, Abos und Zahlungen.',
    items: [
      'Marketplace',
      'Warenkorb',
      'Bestellungen',
      'Outfit-Abos',
      'Zahlungen',
    ],
    icon: Icons.storefront_outlined,
    color: AirmiusColors.green,
  ),
  _MenuGroup(
    title: 'Gesundheit',
    body: 'Ernährung, Wasser, Wohlbefinden und persoenliche Auswertung.',
    items: ['Ernährung', 'Wasser', 'Wellbeing', 'Routen'],
    icon: Icons.favorite_border_outlined,
    color: AirmiusColors.green,
    roles: ['Player', 'Trainer', 'Guardian'],
  ),
  _MenuGroup(
    title: 'Admin & Betrieb',
    body: 'Moderation, User, Billing, System, Trust und Release-Kontrolle.',
    items: ['User Admin', 'Moderation', 'Billing', 'System', 'Release'],
    icon: Icons.admin_panel_settings_outlined,
    color: AirmiusColors.amber,
    adminOnly: true,
  ),
];
