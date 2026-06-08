import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'profile_security_center_screen.dart';
import 'report_moderation_center_screen.dart';
import 'system_admin_operations_screen.dart';

class AdminUserManagementScreen extends StatefulWidget {
  const AdminUserManagementScreen({super.key});

  @override
  State<AdminUserManagementScreen> createState() => _AdminUserManagementScreenState();
}

class _AdminUserManagementScreenState extends State<AdminUserManagementScreen> {
  String _role = 'Alle';
  bool _showActive = true;
  bool _showPending = true;
  bool _showSuspended = true;
  bool _showIncomplete = true;

  final List<_AdminUserItem> _items = const [
    _AdminUserItem(title: 'ZBB Konto', role: 'Player', body: 'Profil, Mitgliedsanfrage, Datenschutz, Vereine und Kontosicherheit pruefen.', status: 'Aktiv', meta: 'player', icon: Icons.person_outline, color: AirmiusColors.blue),
    _AdminUserItem(title: 'verein airmius', role: 'Club Admin', body: 'Vereinsadmin mit Clubrollen, Verifizierung, Dokumenten und Anfrage-Eingang.', status: 'Admin', meta: 'club', icon: Icons.admin_panel_settings_outlined, color: AirmiusColors.green),
    _AdminUserItem(title: 'Trainer Demo', role: 'Trainer', body: 'Trainerrolle, Teams, Trainings, Anwesenheit und Kommunikationsrechte.', status: 'Pruefen', meta: 'trainer', icon: Icons.sports_outlined, color: AirmiusColors.amber),
    _AdminUserItem(title: 'Gesperrter User', role: 'Suspended', body: 'Sperrgrund, Moderation, Supportticket und moegliche Reaktivierung.', status: 'Gesperrt', meta: 'safety', icon: Icons.block_outlined, color: AirmiusColors.red),
  ];

  List<_AdminUserItem> get _visibleItems => _items.where((item) => _role == 'Alle' || item.role == _role).toList();

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
                        const PageTitle(title: 'Admin User-Verwaltung', subtitle: 'User Index, Create, Edit, Profile, Rollen, Sperren, Profilstatus und Sicherheitsaktionen.'),
                        const SizedBox(height: 16),
                        _UsersHero(onCreate: () => _toast('User erstellen vorbereitet')),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Rolle', value: _role, values: const ['Alle', 'Player', 'Club Admin', 'Trainer', 'Suspended'], onChanged: (value) => setState(() => _role = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'User-Filter',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Aktive anzeigen', subtitle: 'Aktive Konten, Rollen und Profilstatus anzeigen.', value: _showActive, onChanged: (value) => setState(() => _showActive = value)),
                              _SwitchRow(title: 'Pending anzeigen', subtitle: 'Unvollstaendige Profile, VerifyEmail und Onboarding-Faelle.', value: _showPending, onChanged: (value) => setState(() => _showPending = value)),
                              _SwitchRow(title: 'Gesperrte anzeigen', subtitle: 'Suspended-Faelle, Moderation und Reaktivierung sichtbar machen.', value: _showSuspended, onChanged: (value) => setState(() => _showSuspended = value)),
                              _SwitchRow(title: 'Unvollstaendige Profile anzeigen', subtitle: 'CompleteProfile, fehlende Daten und Supporthinweise.', value: _showIncomplete, onChanged: (value) => setState(() => _showIncomplete = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in items) ...[
                          _UserCard(item: item, onOpen: () => _toast('${item.title}: Userdetail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        if (items.isEmpty) const EmptyPanel('Keine User fuer diese Rolle gefunden.'),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Admin-Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'User erstellen', icon: Icons.person_add_outlined, onPressed: () => _toast('User erstellen vorbereitet')),
                              AirmiusButton(label: 'Profil-Sicherheit', icon: Icons.manage_accounts_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ProfileSecurityCenterScreen()))),
                              AirmiusButton(label: 'Moderation', icon: Icons.flag_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ReportModerationCenterScreen()))),
                              AirmiusButton(label: 'System Admin', icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SystemAdminOperationsScreen()))),
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
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _UsersHero extends StatelessWidget {
  const _UsersHero({required this.onCreate});

  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF10243B), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('USER ADMIN'), SizedBox(height: 4), Text('Konten, Rollen und Sicherheit', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Neu', icon: Icons.person_add_outlined, onPressed: onCreate),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Die Users-Webseiten werden als mobile Admin-UI abgebildet: Index, Create, Edit, Profile, Rollen, Sperren und Sicherheitsstatus.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '4', label: 'Rollen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Gesperrt')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Pruefen'))]),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({required this.title, required this.value, required this.values, required this.onChanged});

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
              labelStyle: TextStyle(color: value == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
              side: BorderSide(color: value == item ? AirmiusColors.blue : AirmiusColors.border),
            ),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.title, required this.subtitle, required this.value, required this.onChanged});

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700))])),
        Switch.adaptive(value: value, onChanged: onChanged, activeColor: AirmiusColors.blue),
      ]),
    );
  }
}

class _UserCard extends StatelessWidget {
  const _UserCard({required this.item, required this.onOpen});

  final _AdminUserItem item;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: item.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: item.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .5))), child: Icon(item.icon, color: item.color)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.meta, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _AdminUserItem {
  const _AdminUserItem({required this.title, required this.role, required this.body, required this.status, required this.meta, required this.icon, required this.color});

  final String title;
  final String role;
  final String body;
  final String status;
  final String meta;
  final IconData icon;
  final Color color;
}
