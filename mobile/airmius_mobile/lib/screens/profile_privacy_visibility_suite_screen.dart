import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ProfilePrivacyVisibilitySuiteScreen extends StatefulWidget {
  const ProfilePrivacyVisibilitySuiteScreen({super.key});

  @override
  State<ProfilePrivacyVisibilitySuiteScreen> createState() => _ProfilePrivacyVisibilitySuiteScreenState();
}

class _ProfilePrivacyVisibilitySuiteScreenState extends State<ProfilePrivacyVisibilitySuiteScreen> {
  String visibility = 'Verein';
  bool showClubMemberships = true;
  bool showTeams = true;
  bool allowMessages = true;
  bool allowSearch = true;
  bool dataExportReady = true;
  bool blockedUsers = false;

  @override
  Widget build(BuildContext context) {
    final privacyRows = [
      _PrivacyRow(
        title: 'Vereinsmitgliedschaften anzeigen',
        body: 'User entscheidet, ob aktive Vereine im Profil sichtbar sind oder nur intern bleiben.',
        value: showClubMemberships,
        onChanged: (value) => setState(() => showClubMemberships = value),
        icon: Icons.badge_outlined,
        color: AirmiusColors.green,
      ),
      _PrivacyRow(
        title: 'Teams anzeigen',
        body: 'Teamzugehoerigkeit kann für Kontakte, Verein oder nur für Admins sichtbar sein.',
        value: showTeams,
        onChanged: (value) => setState(() => showTeams = value),
        icon: Icons.groups_2_outlined,
        color: AirmiusColors.blue,
      ),
      _PrivacyRow(
        title: 'Nachrichten erlauben',
        body: 'Kontaktrechte für private Nachrichten, Vereinsadmins, Teamchats und Support-Konversationen.',
        value: allowMessages,
        onChanged: (value) => setState(() => allowMessages = value),
        icon: Icons.chat_bubble_outline,
        color: AirmiusColors.amber,
      ),
      _PrivacyRow(
        title: 'In Suche auffindbar',
        body: 'Profil kann in globaler Suche, Vereinslisten und Teamlisten sichtbar oder verborgen sein.',
        value: allowSearch,
        onChanged: (value) => setState(() => allowSearch = value),
        icon: Icons.manage_search_outlined,
        color: AirmiusColors.pink,
      ),
      _PrivacyRow(
        title: 'Datenexport vorbereiten',
        body: 'Personendaten, Mitgliedschaften, Zahlungen, Dokumente, Nachrichten und Consent-Verlauf exportierbar machen.',
        value: dataExportReady,
        onChanged: (value) => setState(() => dataExportReady = value),
        icon: Icons.download_outlined,
        color: AirmiusColors.blue,
      ),
      _PrivacyRow(
        title: 'Blockierte Nutzer',
        body: 'Blockieren, Melden und Kontaktbeschraenkungen werden für private Nachrichten und Feed vorbereitet.',
        value: blockedUsers,
        onChanged: (value) => setState(() => blockedUsers = value),
        icon: Icons.block_outlined,
        color: AirmiusColors.amber,
      ),
    ];

    return PageFrame(
      title: 'Profil & Datenschutz',
      subtitle: 'Sichtbarkeit, Kontakt und Datenrechte',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('PRIVACY CENTER'),
                const SizedBox(height: 8),
                const Text(
                  'User brauchen Kontrolle über Profil, Suche, Vereinszugehoerigkeit, Teams, Nachrichten, blockierte Nutzer und Datenrechte.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '6', label: 'Regeln'),
                    Metric(value: 'DSGVO', label: 'Rechte'),
                    Metric(value: 'Search', label: 'Sichtbar'),
                    Metric(value: 'Block', label: 'Schutz'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('PROFIL-SICHTBARKEIT'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Privat', label: Text('Privat')),
                    ButtonSegment(value: 'Verein', label: Text('Verein')),
                    ButtonSegment(value: 'Kontakte', label: Text('Kontakte')),
                    ButtonSegment(value: 'Öffentlich', label: Text('Public')),
                  ],
                  selected: {visibility},
                  onSelectionChanged: (value) => setState(() => visibility = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final row in privacyRows) ...[
            _PrivacyCard(row: row),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('DATENRECHTE'),
                const SizedBox(height: 8),
                Text(
                  'Aktuelle Sichtbarkeit: $visibility. Später können Datenexport, Datenkorrektur, Löschanfrage, Consent-Historie und Sichtbarkeits-Audit per API angebunden werden.',
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    AirmiusButton(
                      label: 'Export',
                      icon: Icons.download_outlined,
                      onPressed: () => openUiAction(
                        context,
                        title: 'Datenexport',
                        body: 'Diese UI bereitet Datenexport für Profil, Mitgliedschaften, Zahlungen, Dokumente, Nachrichten und Consent-Verlauf vor.',
                        status: 'UI vorbereitet',
                        icon: Icons.download_outlined,
                      ),
                    ),
                    AirmiusButton(
                      label: 'Löschanfrage',
                      icon: Icons.delete_outline,
                      secondary: true,
                      onPressed: () => openUiAction(
                        context,
                        title: 'Löschanfrage',
                        body: 'Lösch- und Korrekturanfragen werden später mit Datenschutz, Audit und Adminfreigabe verbunden.',
                        status: 'UI vorbereitet',
                        icon: Icons.delete_outline,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PrivacyRow {
  const _PrivacyRow({
    required this.title,
    required this.body,
    required this.value,
    required this.onChanged,
    required this.icon,
    required this.color,
  });

  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;
  final IconData icon;
  final Color color;
}

class _PrivacyCard extends StatelessWidget {
  const _PrivacyCard({required this.row});

  final _PrivacyRow row;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(icon: row.icon, color: row.color),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(row.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 16, fontWeight: FontWeight.w900))),
                    Switch.adaptive(value: row.value, activeColor: row.color, onChanged: row.onChanged),
                  ],
                ),
                const SizedBox(height: 6),
                Text(row.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.4, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
