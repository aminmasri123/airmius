import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'file_operations_screen.dart';
import 'notification_chat_operations_screen.dart';
import 'support_helpdesk_screen.dart';

class WorkspaceOperationsCenterScreen extends StatefulWidget {
  const WorkspaceOperationsCenterScreen({super.key});

  @override
  State<WorkspaceOperationsCenterScreen> createState() =>
      _WorkspaceOperationsCenterState();
}

class _WorkspaceOperationsCenterState
    extends State<WorkspaceOperationsCenterScreen> {
  String _tab = 'Alle';
  bool _showMembers = true;
  bool _showFiles = true;
  bool _showTasks = true;
  bool _showPermissions = true;

  final List<_WorkspaceItem> _items = const [
    _WorkspaceItem(
      title: 'Vereinsvorstand',
      area: 'Aktiv',
      body:
          'Gemeinsamer Arbeitsbereich für Vorstand, Dokumente, Aufgaben und Beschluesse.',
      status: 'Aktiv',
      meta: '5 Mitglieder',
      icon: Icons.groups_2_outlined,
      color: AirmiusColors.blue,
    ),
    _WorkspaceItem(
      title: 'Trainerteam U16',
      area: 'Teams',
      body:
          'Training, Anwesenheit, Teamdateien, Medienfreigabe und interne Abstimmung.',
      status: 'Team',
      meta: '3 Trainer',
      icon: Icons.sports_outlined,
      color: AirmiusColors.green,
    ),
    _WorkspaceItem(
      title: 'Finanzen 2026',
      area: 'Finanzen',
      body:
          'Beiträge, Rechnungen, Zahlungsstatus, Exporte und Vorstandsauswertung.',
      status: 'Sensibel',
      meta: 'Rollenpflicht',
      icon: Icons.account_balance_wallet_outlined,
      color: AirmiusColors.amber,
    ),
    _WorkspaceItem(
      title: 'Archiv',
      area: 'Archiv',
      body:
          'Abgeschlossene Projekte, alte Dokumente, Versionen und Datenschutzaufbewahrung.',
      status: 'Archiv',
      meta: '12 Dateien',
      icon: Icons.archive_outlined,
      color: AirmiusColors.red,
    ),
  ];

  List<_WorkspaceItem> get _visibleItems =>
      _items.where((item) => _tab == 'Alle' || item.area == _tab).toList();

  @override
  Widget build(BuildContext context) {
    final items = _visibleItems;

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const PageTitle(
                          title: 'Workspaces',
                          subtitle:
                              'Arbeitsbereiche, Mitglieder, Dateien, Aufgaben, Rechte, Archiv und Teamzusammenarbeit.',
                        ),
                        const SizedBox(height: 16),
                        _WorkspaceHero(
                          onCreate: () =>
                              _toast('Workspace erstellen vorbereitet'),
                        ),
                        const SizedBox(height: 16),
                        _ChoicePanel(
                          title: 'Bereich',
                          value: _tab,
                          values: const [
                            'Alle',
                            'Aktiv',
                            'Teams',
                            'Finanzen',
                            'Archiv',
                          ],
                          onChanged: (value) => setState(() => _tab = value),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Workspace-Optionen',
                          child: Column(
                            children: [
                              _SwitchRow(
                                title: 'Mitglieder anzeigen',
                                subtitle:
                                    'Mitglieder, Rollen, Einladungen und Verantwortliche sichtbar machen.',
                                value: _showMembers,
                                onChanged: (value) =>
                                    setState(() => _showMembers = value),
                              ),
                              _SwitchRow(
                                title: 'Dateien anzeigen',
                                subtitle:
                                    'Workspace-Dateien, Versionen und Verknuepfung zum Dateimanager.',
                                value: _showFiles,
                                onChanged: (value) =>
                                    setState(() => _showFiles = value),
                              ),
                              _SwitchRow(
                                title: 'Aufgaben anzeigen',
                                subtitle:
                                    'To-dos, Beschluesse, offene Punkte und Verantwortlichkeiten.',
                                value: _showTasks,
                                onChanged: (value) =>
                                    setState(() => _showTasks = value),
                              ),
                              _SwitchRow(
                                title: 'Rechte anzeigen',
                                subtitle:
                                    'Admin-, Schreib-, Lese- und sensible Finanzrechte.',
                                value: _showPermissions,
                                onChanged: (value) =>
                                    setState(() => _showPermissions = value),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in items) ...[
                          _WorkspaceCard(
                            item: item,
                            onOpen: () => _toast(
                              '${item.title}: Workspace-Detail vorbereitet',
                            ),
                          ),
                          const SizedBox(height: 12),
                        ],
                        if (items.isEmpty)
                          const EmptyPanel(
                            'Keine Workspaces für diesen Bereich gefunden.',
                          ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Workspace-Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(
                                label: 'Workspace erstellen',
                                icon: Icons.add_circle_outline,
                                onPressed: () =>
                                    _toast('Workspace erstellen vorbereitet'),
                              ),
                              AirmiusButton(
                                label: 'Dateien',
                                icon: Icons.folder_copy_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => FileOperationsScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Chat',
                                icon: Icons.forum_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        NotificationChatOperationsScreen(
                                          initialTab: 'Chat',
                                        ),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Support',
                                icon: Icons.support_agent_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => SupportHelpdeskScreen(),
                                  ),
                                ),
                              ),
                            ],
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

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _WorkspaceHero extends StatelessWidget {
  const _WorkspaceHero({required this.onCreate});

  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF10243B), Color(0xFF0B111B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AirmiusColors.borderStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusLogo(size: 42),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow('WORKSPACES'),
                    SizedBox(height: 4),
                    Text(
                      'Arbeitsbereiche organisieren',
                      style: TextStyle(
                        color: AirmiusColors.text,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              AirmiusButton(
                label: 'Neu',
                icon: Icons.add_circle_outline,
                onPressed: onCreate,
              ),
            ],
          ),
          const SizedBox(height: 14),
          const Text(
            'Das Workspaces-Webmodul wird als mobile UI abgebildet: Zusammenarbeit, Mitglieder, Aufgaben, Dateien, Rechte und Archiv.',
            style: TextStyle(
              color: AirmiusColors.muted,
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          const Row(
            children: [
              Expanded(
                child: MetricCard(value: '4', label: 'Workspaces'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '20', label: 'Mitglieder'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '12', label: 'Dateien'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({
    required this.title,
    required this.value,
    required this.values,
    required this.onChanged,
  });

  final String title;
  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final item in values)
            ChoiceChip(
              label: Text(item),
              selected: value == item,
              onSelected: (_) => onChanged(item),
              selectedColor: AirmiusColors.blue.withValues(alpha: .24),
              backgroundColor: AirmiusColors.card,
              labelStyle: TextStyle(
                color: value == item ? AirmiusColors.text : AirmiusColors.muted,
                fontWeight: FontWeight.w900,
              ),
              side: BorderSide(
                color: value == item
                    ? AirmiusColors.blue
                    : AirmiusColors.border,
              ),
            ),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({
    required this.title,
    required this.subtitle,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AirmiusColors.input,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: AirmiusColors.text,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    fontSize: 12,
                    height: 1.35,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          Switch.adaptive(
            value: value,
            onChanged: onChanged,
            activeThumbColor: AirmiusColors.blue,
          ),
        ],
      ),
    );
  }
}

class _WorkspaceCard extends StatelessWidget {
  const _WorkspaceCard({required this.item, required this.onOpen});

  final _WorkspaceItem item;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: item.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: item.color.withValues(alpha: .18),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: item.color.withValues(alpha: .5)),
            ),
            child: Icon(item.icon, color: item.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                StatusPill(item.status, color: item.color),
                const SizedBox(height: 8),
                Text(
                  item.meta,
                  style: const TextStyle(
                    color: AirmiusColors.blue,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  item.body,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    height: 1.45,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onOpen,
            icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ),
        ],
      ),
    );
  }
}

class _WorkspaceItem {
  const _WorkspaceItem({
    required this.title,
    required this.area,
    required this.body,
    required this.status,
    required this.meta,
    required this.icon,
    required this.color,
  });

  final String title;
  final String area;
  final String body;
  final String status;
  final String meta;
  final IconData icon;
  final Color color;
}
