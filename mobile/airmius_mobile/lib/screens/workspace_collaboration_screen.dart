import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'file_operations_screen.dart';
import 'support_helpdesk_screen.dart';

class WorkspaceCollaborationScreen extends StatefulWidget {
  const WorkspaceCollaborationScreen({super.key});

  @override
  State<WorkspaceCollaborationScreen> createState() =>
      _WorkspaceCollaborationScreenState();
}

class _WorkspaceCollaborationScreenState
    extends State<WorkspaceCollaborationScreen> {
  String _filter = 'Alle';
  bool _showMembers = true;
  bool _showFiles = true;
  bool _showTasks = true;
  bool _showPermissions = true;

  final List<_WorkspaceItem> _items = const [
    _WorkspaceItem(
      title: 'Vereins-Workspace',
      area: 'Verein',
      status: 'Aktiv',
      meta: 'ZBB',
      description:
          'Zentrale Arbeitsfläche für Verein, Admins, Mitglieder, Dokumente und interne Aufgaben.',
      icon: Icons.apartment_outlined,
      color: Color(0xFF5BA7FF),
      points: [
        'Mitgliederlisten',
        'Vereinsdateien',
        'Aufgabenboard',
        'Rollenrechte',
      ],
    ),
    _WorkspaceItem(
      title: 'Team-Workspace',
      area: 'Team',
      status: 'Team',
      meta: 'U16',
      description:
          'Mobile Teamseite für Trainer, Spieler, Eltern, Termine, Training und schnelle Absprachen.',
      icon: Icons.groups_2_outlined,
      color: Color(0xFF2EE59D),
      points: [
        'Trainerzugriff',
        'Teamdateien',
        'Trainingstermine',
        'Elterninfos',
      ],
    ),
    _WorkspaceItem(
      title: 'Projekt-Workspace',
      area: 'Projekt',
      status: 'Planung',
      meta: 'Event',
      description:
          'Planungsbereich für Turniere, Vereinsfeste, Sponsoring-Aktionen und wiederkehrende Projekte.',
      icon: Icons.task_alt_outlined,
      color: Color(0xFFF8B84E),
      points: [
        'Checklisten',
        'Zuständigkeiten',
        'Budgetnotizen',
        'Dateianhänge',
      ],
    ),
    _WorkspaceItem(
      title: 'Geschützter Bereich',
      area: 'Rechte',
      status: 'Rollen',
      meta: 'Admin',
      description:
          'Sensible Arbeitsbereiche mit Rollen, Sichtbarkeit, Datenschutz-Hinweisen und Zugriffskontrolle.',
      icon: Icons.lock_outline,
      color: Color(0xFFFF6B6B),
      points: ['Admin-only', 'DSGVO-Dokumente', 'Freigaben', 'Audit-Hinweise'],
    ),
  ];

  List<_WorkspaceItem> get _visibleItems {
    if (_filter == 'Alle') return _items;
    return _items.where((item) => item.area == _filter).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF070B12),
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              sliver: SliverToBoxAdapter(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _TopBar(onHelp: () => _openSupport(context)),
                    const SizedBox(height: 18),
                    const _PageIntro(
                      eyebrow: 'ARBEITSBEREICHE',
                      title: 'Workspaces',
                      subtitle:
                          'Mobile Zusammenarbeit für Vereine, Teams, Projekte, Dateien und Rechte.',
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(
                          child: _MetricTile(value: '4', label: 'Workspaces'),
                        ),
                        SizedBox(width: 10),
                        Expanded(
                          child: _MetricTile(value: '12', label: 'Mitglieder'),
                        ),
                        SizedBox(width: 10),
                        Expanded(
                          child: _MetricTile(value: '9', label: 'Dateien'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _FilterBar(
                      value: _filter,
                      values: const [
                        'Alle',
                        'Verein',
                        'Team',
                        'Projekt',
                        'Rechte',
                      ],
                      onChanged: (value) => setState(() => _filter = value),
                    ),
                    const SizedBox(height: 14),
                    _SettingsPanel(
                      showMembers: _showMembers,
                      showFiles: _showFiles,
                      showTasks: _showTasks,
                      showPermissions: _showPermissions,
                      onMembers: (value) =>
                          setState(() => _showMembers = value),
                      onFiles: (value) => setState(() => _showFiles = value),
                      onTasks: (value) => setState(() => _showTasks = value),
                      onPermissions: (value) =>
                          setState(() => _showPermissions = value),
                    ),
                    const SizedBox(height: 14),
                    for (final item in _visibleItems) ...[
                      _WorkspaceCard(
                        item: item,
                        showMembers: _showMembers,
                        showFiles: _showFiles,
                        showTasks: _showTasks,
                        showPermissions: _showPermissions,
                      ),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      onCreate: () => openUiAction(
                        context,
                        title: 'Workspace erstellen',
                        message:
                            'Im API-Schritt wird hier ein neuer Vereins- oder Team-Workspace angelegt.',
                      ),
                      onFiles: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => FileOperationsScreen(),
                        ),
                      ),
                      onSupport: () => _openSupport(context),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _openSupport(BuildContext context) {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _WorkspaceItem {
  const _WorkspaceItem({
    required this.title,
    required this.area,
    required this.status,
    required this.meta,
    required this.description,
    required this.icon,
    required this.color,
    required this.points,
  });

  final String title;
  final String area;
  final String status;
  final String meta;
  final String description;
  final IconData icon;
  final Color color;
  final List<String> points;
}

class _TopBar extends StatelessWidget {
  const _TopBar({required this.onHelp});

  final VoidCallback onHelp;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const AirmiusLogo(markOnly: true, size: 34),
        const SizedBox(width: 10),
        const Expanded(
          child: Text(
            'Airmius',
            style: TextStyle(
              color: Colors.white,
              fontSize: 20,
              fontWeight: FontWeight.w900,
              letterSpacing: .2,
            ),
          ),
        ),
        IconButton(
          onPressed: onHelp,
          icon: const Icon(Icons.help_outline, color: Color(0xFFAFC0D8)),
        ),
      ],
    );
  }
}

class _PageIntro extends StatelessWidget {
  const _PageIntro({
    required this.eyebrow,
    required this.title,
    required this.subtitle,
  });

  final String eyebrow;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: const LinearGradient(
          colors: [Color(0xFF121A27), Color(0xFF0B111B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        border: Border.all(color: Color(0xFF243348)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            eyebrow,
            style: const TextStyle(
              color: Color(0xFF5BA7FF),
              fontSize: 12,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            title,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 30,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: const TextStyle(
              color: Color(0xFFAFC0D8),
              height: 1.45,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _MetricTile extends StatelessWidget {
  const _MetricTile({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFF101722),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFF26364D)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: const TextStyle(
              color: Color(0xFFAFC0D8),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({
    required this.value,
    required this.values,
    required this.onChanged,
  });

  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: values.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final item = values[index];
          final active = item == value;
          return ChoiceChip(
            label: Text(item),
            selected: active,
            onSelected: (_) => onChanged(item),
            labelStyle: TextStyle(
              color: active ? Colors.white : const Color(0xFFAFC0D8),
              fontWeight: FontWeight.w900,
            ),
            selectedColor: const Color(0xFF173D68),
            backgroundColor: const Color(0xFF101722),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(999),
              side: const BorderSide(color: Color(0xFF26364D)),
            ),
          );
        },
      ),
    );
  }
}

class _SettingsPanel extends StatelessWidget {
  const _SettingsPanel({
    required this.showMembers,
    required this.showFiles,
    required this.showTasks,
    required this.showPermissions,
    required this.onMembers,
    required this.onFiles,
    required this.onTasks,
    required this.onPermissions,
  });

  final bool showMembers;
  final bool showFiles;
  final bool showTasks;
  final bool showPermissions;
  final ValueChanged<bool> onMembers;
  final ValueChanged<bool> onFiles;
  final ValueChanged<bool> onTasks;
  final ValueChanged<bool> onPermissions;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Sichtbarkeit wie in der Web-App',
      child: Column(
        children: [
          _SwitchLine(
            label: 'Mitglieder anzeigen',
            value: showMembers,
            onChanged: onMembers,
          ),
          _SwitchLine(
            label: 'Dateien anzeigen',
            value: showFiles,
            onChanged: onFiles,
          ),
          _SwitchLine(
            label: 'Aufgaben anzeigen',
            value: showTasks,
            onChanged: onTasks,
          ),
          _SwitchLine(
            label: 'Rechte und Rollen anzeigen',
            value: showPermissions,
            onChanged: onPermissions,
          ),
        ],
      ),
    );
  }
}

class _SwitchLine extends StatelessWidget {
  const _SwitchLine({
    required this.label,
    required this.value,
    required this.onChanged,
  });

  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      value: value,
      onChanged: onChanged,
      dense: true,
      contentPadding: EdgeInsets.zero,
      activeThumbColor: const Color(0xFF5BA7FF),
      title: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _WorkspaceCard extends StatelessWidget {
  const _WorkspaceCard({
    required this.item,
    required this.showMembers,
    required this.showFiles,
    required this.showTasks,
    required this.showPermissions,
  });

  final _WorkspaceItem item;
  final bool showMembers;
  final bool showFiles;
  final bool showTasks;
  final bool showPermissions;

  @override
  Widget build(BuildContext context) {
    final details = [
      if (showMembers) item.points[0],
      if (showFiles) item.points[1],
      if (showTasks) item.points[2],
      if (showPermissions) item.points[3],
    ];

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF101722),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFF26364D)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 54,
                height: 54,
                decoration: BoxDecoration(
                  color: item.color.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: item.color.withValues(alpha: .45)),
                ),
                child: Icon(item.icon, color: item.color, size: 28),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.title,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${item.meta} - ${item.area}',
                      style: const TextStyle(
                        color: Color(0xFFAFC0D8),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              _Pill(label: item.status, color: item.color),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            item.description,
            style: const TextStyle(
              color: Color(0xFFDDE7F5),
              height: 1.45,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [for (final detail in details) _SmallTag(label: detail)],
          ),
        ],
      ),
    );
  }
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({
    required this.onCreate,
    required this.onFiles,
    required this.onSupport,
  });

  final VoidCallback onCreate;
  final VoidCallback onFiles;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Schnellaktionen',
      child: Column(
        children: [
          _ActionButton(
            icon: Icons.add_circle_outline,
            label: 'Workspace erstellen',
            onTap: onCreate,
          ),
          const SizedBox(height: 10),
          _ActionButton(
            icon: Icons.folder_copy_outlined,
            label: 'Dateimanager öffnen',
            onTap: onFiles,
          ),
          const SizedBox(height: 10),
          _ActionButton(
            icon: Icons.support_agent_outlined,
            label: 'Support kontaktieren',
            onTap: onSupport,
          ),
        ],
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF0D131D),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFF26364D)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: const Color(0xFF111A27),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFF26364D)),
        ),
        child: Row(
          children: [
            Icon(icon, color: airmiusAccentColor(context)),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            const Icon(Icons.chevron_right, color: Color(0xFFAFC0D8)),
          ],
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: .55)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _SmallTag extends StatelessWidget {
  const _SmallTag({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: const Color(0xFF172235),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFF26364D)),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Color(0xFFDDE7F5),
          fontSize: 12,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}
