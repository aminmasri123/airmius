import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_profile_editor_screen.dart';
import 'club_role_permissions_screen.dart';
import 'system_admin_operations_screen.dart';

class AdminClubVerificationScreen extends StatefulWidget {
  const AdminClubVerificationScreen({super.key});

  @override
  State<AdminClubVerificationScreen> createState() => _AdminClubVerificationScreenState();
}

class _AdminClubVerificationScreenState extends State<AdminClubVerificationScreen> {
  String _status = 'Offen';
  bool _showDocuments = true;
  bool _showOwnerCheck = true;
  bool _showRiskNotes = true;
  bool _showPublicProfile = true;

  final List<_VerificationItem> _items = const [
    _VerificationItem(title: 'ZBB', body: 'Vereinsprofil, Adminrolle, Adresse und Dokumente prüfen.', status: 'Offen', owner: 'verein airmius', icon: Icons.apartment_outlined, color: AirmiusColors.blue),
    _VerificationItem(title: 'Airmius Running Club', body: 'Öffentlicher Verein mit Sichtbarkeit, Teams und Kontaktfreigabe.', status: 'Rückfrage', owner: 'Admin Ops', icon: Icons.directions_run_outlined, color: AirmiusColors.amber),
    _VerificationItem(title: 'Tennis Zentrum West', body: 'Nachweis, Vereinsdaten, Beitragsordnung und Impressumsangaben vorhanden.', status: 'Geprüft', owner: 'Trust Team', icon: Icons.sports_tennis_outlined, color: AirmiusColors.green),
    _VerificationItem(title: 'Neue Vereinsanfrage', body: 'Admin muss Inhaber, Dokumente, Standort und Regelwerke verifizieren.', status: 'Risiko', owner: 'Moderation', icon: Icons.warning_amber_outlined, color: AirmiusColors.red),
  ];

  List<_VerificationItem> get _visibleItems => _items.where((item) => _status == 'Alle' || item.status == _status).toList();

  @override
  Widget build(BuildContext context) {
    final visibleItems = _visibleItems;

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
                        const PageTitle(title: 'Admin Vereinsverifizierungen', subtitle: 'Vereine, Inhaber, Dokumente, Sichtbarkeit, Risiko, Rückfragen und Freigabe.'),
                        const SizedBox(height: 16),
                        _VerificationHero(onApprove: () => _toast('Verifizierung freigeben vorbereitet')),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Status', value: _status, values: const ['Alle', 'Offen', 'Rückfrage', 'Geprüft', 'Risiko'], onChanged: (value) => setState(() => _status = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Prüfregeln',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Dokumente prüfen', subtitle: 'Vereinsregeln, Datenschutz, Beitragsordnung und Nachweise.', value: _showDocuments, onChanged: (value) => setState(() => _showDocuments = value)),
                              _SwitchRow(title: 'Inhaber prüfen', subtitle: 'Adminrolle, Kontakt, Identitaet und Verantwortlichkeit.', value: _showOwnerCheck, onChanged: (value) => setState(() => _showOwnerCheck = value)),
                              _SwitchRow(title: 'Risikonotizen zeigen', subtitle: 'Moderation, falsche Daten, Spam oder Missbrauch erkennen.', value: _showRiskNotes, onChanged: (value) => setState(() => _showRiskNotes = value)),
                              _SwitchRow(title: 'Öffentliches Profil prüfen', subtitle: 'Sichtbarkeit, Kontakt, Adresse, Teams und Clubseite kontrollieren.', value: _showPublicProfile, onChanged: (value) => setState(() => _showPublicProfile = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in visibleItems) ...[
                          _VerificationCard(item: item, onOpen: () => _toast('${item.title}: Verifizierungsdetail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        if (visibleItems.isEmpty) const EmptyPanel('Keine Vereinsverifizierung für diesen Status gefunden.'),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Admin-Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Freigeben', icon: Icons.verified_outlined, onPressed: () => _toast('Verein freigeben vorbereitet')),
                              AirmiusButton(label: 'Rückfrage', icon: Icons.forum_outlined, secondary: true, onPressed: () => _toast('Rückfrage an Verein vorbereitet')),
                              AirmiusButton(label: 'Vereinsprofil', icon: Icons.edit_note_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubProfileEditorScreen()))),
                              AirmiusButton(label: 'Rollen', icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubRolePermissionsScreen()))),
                              AirmiusButton(label: 'System Admin', icon: Icons.hub_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SystemAdminOperationsScreen()))),
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

class _VerificationHero extends StatelessWidget {
  const _VerificationHero({required this.onApprove});

  final VoidCallback onApprove;

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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('CLUB VERIFICATION'), SizedBox(height: 4), Text('Vereine sicher freigeben', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Freigeben', icon: Icons.verified_outlined, onPressed: onApprove),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Admin-UI für ClubVerifications: Vereine werden anhand von Inhaber, Dokumenten, Profil, Sichtbarkeit und Risiko mobil geprüft.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '4', label: 'Vereine')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Offen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Risiko'))]),
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

class _VerificationCard extends StatelessWidget {
  const _VerificationCard({required this.item, required this.onOpen});

  final _VerificationItem item;
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
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.owner, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _VerificationItem {
  const _VerificationItem({required this.title, required this.body, required this.status, required this.owner, required this.icon, required this.color});

  final String title;
  final String body;
  final String status;
  final String owner;
  final IconData icon;
  final Color color;
}
