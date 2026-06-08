import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_finance_cockpit_screen.dart';
import 'club_member_directory_screen.dart';
import 'club_request_inbox_screen.dart';
import 'club_team_admin_screen.dart';

class ClubRolePermissionsScreen extends StatefulWidget {
  const ClubRolePermissionsScreen({super.key});

  @override
  State<ClubRolePermissionsScreen> createState() => _ClubRolePermissionsScreenState();
}

class _ClubRolePermissionsScreenState extends State<ClubRolePermissionsScreen> {
  bool _strictApprovals = true;
  bool _twoPersonFinance = true;
  bool _trainerCanCheckIn = true;
  bool _documentReview = true;

  final List<_RoleCardData> _roles = const [
    _RoleCardData(
      title: 'Vereinsinhaber',
      subtitle: 'Kann alle Einstellungen, Rollen, Zahlungsregeln und Sichtbarkeit steuern.',
      people: '1 Person',
      status: 'Vollzugriff',
      icon: Icons.verified_user_outlined,
      color: AirmiusColors.blue,
      permissions: ['Profil', 'Mitglieder', 'Teams', 'Finanzen', 'Dokumente', 'Sichtbarkeit'],
    ),
    _RoleCardData(
      title: 'Admin',
      subtitle: 'Bearbeitet Mitgliedsanfragen, Teamzuordnung, Dateien und Vereinskommunikation.',
      people: '2 Personen',
      status: 'Operativ',
      icon: Icons.admin_panel_settings_outlined,
      color: AirmiusColors.green,
      permissions: ['Anfragen', 'Mitglieder', 'Teams', 'Events', 'Chat'],
    ),
    _RoleCardData(
      title: 'Trainer',
      subtitle: 'Pflegt Training, Anwesenheit, Teamchat und Teilnehmerlisten fuer eigene Teams.',
      people: '4 Personen',
      status: 'Teamzugriff',
      icon: Icons.sports_outlined,
      color: AirmiusColors.amber,
      permissions: ['Training', 'Anwesenheit', 'Teamchat', 'Termine'],
    ),
    _RoleCardData(
      title: 'Finanzrolle',
      subtitle: 'Sieht Beitraege, Zahlungsstatus, SEPA-Hinweise und Exportfunktionen.',
      people: '1 Person',
      status: 'Sensibel',
      icon: Icons.account_balance_wallet_outlined,
      color: AirmiusColors.red,
      permissions: ['Beitraege', 'Zahlungen', 'Export', 'Mahnung'],
    ),
  ];

  final List<_PermissionRow> _matrix = const [
    _PermissionRow(area: 'Mitgliedsanfragen', owner: 'Admin', rule: 'Annehmen, ablehnen, Rueckfragen senden', risk: 'Mittel'),
    _PermissionRow(area: 'Vereinsdokumente', owner: 'Inhaber', rule: 'Upload, Version, Sichtbarkeit und Pflichtdokument', risk: 'Hoch'),
    _PermissionRow(area: 'Beitragsregeln', owner: 'Inhaber + Finanzen', rule: 'Preis, Intervall, Zahlmethode und Dokumentverknuepfung', risk: 'Hoch'),
    _PermissionRow(area: 'Teams & Rollen', owner: 'Admin', rule: 'Kader, Trainer, Teambeitritt und Chatfreigabe', risk: 'Mittel'),
    _PermissionRow(area: 'Events & Training', owner: 'Trainer', rule: 'Termin, Teilnehmer, Warteliste und Anwesenheit', risk: 'Niedrig'),
    _PermissionRow(area: 'Oeffentliche Sichtbarkeit', owner: 'Admin', rule: 'Profilfelder, Kontakt, Beitraege und Suche', risk: 'Mittel'),
  ];

  @override
  Widget build(BuildContext context) {
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
                          title: 'Vereinsrollen & Rechte',
                          subtitle: 'Admins, Trainer, Finanzen, Dokumente, Freigaben und sensible Berechtigungen.',
                        ),
                        const SizedBox(height: 16),
                        _RolesHero(onSave: () => _toast('Rollenmodell gespeichert vorbereitet')),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Sicherheitsregeln',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Strenge Freigaben', subtitle: 'Sensible Aenderungen brauchen Inhaberfreigabe.', value: _strictApprovals, onChanged: (value) => setState(() => _strictApprovals = value)),
                              _SwitchRow(title: 'Vier-Augen-Prinzip Finanzen', subtitle: 'Beitraege, SEPA und Zahlungsexport brauchen zweite Rolle.', value: _twoPersonFinance, onChanged: (value) => setState(() => _twoPersonFinance = value)),
                              _SwitchRow(title: 'Trainer duerfen einchecken', subtitle: 'Trainer sehen nur eigene Teams und Anwesenheit.', value: _trainerCanCheckIn, onChanged: (value) => setState(() => _trainerCanCheckIn = value)),
                              _SwitchRow(title: 'Dokumente pruefen', subtitle: 'Datenschutz, Regeln und Pflichtdateien werden versioniert.', value: _documentReview, onChanged: (value) => setState(() => _documentReview = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final role in _roles) ...[
                          _RoleCard(data: role, onTap: () => _toast('${role.title}: Rollen-Detail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Berechtigungsmatrix',
                          child: Column(
                            children: [
                              for (final row in _matrix) _MatrixRow(row: row),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Direkt zu verbundenen Bereichen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Anfragen', icon: Icons.inbox_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubRequestInboxScreen()))),
                              AirmiusButton(label: 'Mitglieder', icon: Icons.badge_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMemberDirectoryScreen()))),
                              AirmiusButton(label: 'Teams', icon: Icons.groups_2_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubTeamAdminScreen()))),
                              AirmiusButton(label: 'Finanzen', icon: Icons.account_balance_wallet_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubFinanceCockpitScreen()))),
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

class _RolesHero extends StatelessWidget {
  const _RolesHero({required this.onSave});

  final VoidCallback onSave;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF0F1A2A), Color(0xFF13253B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
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
                    Eyebrow('ZUGRIFF & VERANTWORTUNG'),
                    SizedBox(height: 4),
                    Text('Vereinsrollen sauber steuern', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900)),
                  ],
                ),
              ),
              AirmiusButton(label: 'Speichern', icon: Icons.save_outlined, onPressed: onSave),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Jeder Verein kann entscheiden, wer sensible Daten sieht, wer Anfragen bearbeitet und welche Aktionen eine Freigabe brauchen.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(
            children: [
              Expanded(child: MetricCard(value: '8', label: 'Rollen')),
              SizedBox(width: 10),
              Expanded(child: MetricCard(value: '21', label: 'Rechte')),
              SizedBox(width: 10),
              Expanded(child: MetricCard(value: '4', label: 'Risiken')),
            ],
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
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
          Switch.adaptive(value: value, onChanged: onChanged, activeColor: AirmiusColors.blue),
        ],
      ),
    );
  }
}

class _RoleCard extends StatelessWidget {
  const _RoleCard({required this.data, required this.onTap});

  final _RoleCardData data;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: data.title,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(color: data.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: data.color.withValues(alpha: .5))),
                child: Icon(data.icon, color: data.color),
              ),
              const SizedBox(width: 12),
              Expanded(child: Text(data.subtitle, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))),
              StatusPill(data.status, color: data.color),
            ],
          ),
          const SizedBox(height: 12),
          Text(data.people, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [for (final permission in data.permissions) StatusPill(permission, color: AirmiusColors.blue)],
          ),
          const SizedBox(height: 12),
          Align(alignment: Alignment.centerRight, child: AirmiusButton(label: 'Bearbeiten', icon: Icons.tune_outlined, secondary: true, onPressed: onTap)),
        ],
      ),
    );
  }
}

class _MatrixRow extends StatelessWidget {
  const _MatrixRow({required this.row});

  final _PermissionRow row;

  @override
  Widget build(BuildContext context) {
    final color = row.risk == 'Hoch' ? AirmiusColors.red : row.risk == 'Mittel' ? AirmiusColors.amber : AirmiusColors.green;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(row.area, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
              StatusPill(row.risk, color: color),
            ],
          ),
          const SizedBox(height: 6),
          Text(row.owner, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(row.rule, style: const TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

class _RoleCardData {
  const _RoleCardData({required this.title, required this.subtitle, required this.people, required this.status, required this.icon, required this.color, required this.permissions});

  final String title;
  final String subtitle;
  final String people;
  final String status;
  final IconData icon;
  final Color color;
  final List<String> permissions;
}

class _PermissionRow {
  const _PermissionRow({required this.area, required this.owner, required this.rule, required this.risk});

  final String area;
  final String owner;
  final String rule;
  final String risk;
}
