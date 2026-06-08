import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ClubMemberImportExportSuiteScreen extends StatefulWidget {
  const ClubMemberImportExportSuiteScreen({super.key});

  @override
  State<ClubMemberImportExportSuiteScreen> createState() => _ClubMemberImportExportSuiteScreenState();
}

class _ClubMemberImportExportSuiteScreenState extends State<ClubMemberImportExportSuiteScreen> {
  String mode = 'Import';
  bool csvUpload = true;
  bool duplicateCheck = true;
  bool inviteMembers = true;
  bool exportAllowed = true;

  @override
  Widget build(BuildContext context) {
    final rows = [
      const _ImportExportRow(
        title: 'CSV-Mitgliederliste',
        status: 'Import',
        body: 'Bestehende Vereinsmitglieder koennen spaeter per CSV uebernommen und Feld fuer Feld gemappt werden.',
        icon: Icons.upload_file_outlined,
        color: AirmiusColors.blue,
      ),
      const _ImportExportRow(
        title: 'Dublettenpruefung',
        status: 'Check',
        body: 'E-Mail, Name, Geburtsdatum und Mitgliedsnummer werden gegen vorhandene Profile geprueft.',
        icon: Icons.rule_outlined,
        color: AirmiusColors.amber,
      ),
      const _ImportExportRow(
        title: 'Einladungen senden',
        status: 'Invite',
        body: 'Importierte Kontakte koennen eine Einladung erhalten, um ihr Airmius-Konto zu aktivieren.',
        icon: Icons.person_add_alt_outlined,
        color: AirmiusColors.green,
      ),
      const _ImportExportRow(
        title: 'Vereinsdaten exportieren',
        status: 'Export',
        body: 'Mitglieder, Rollen, Zahlstatus, Dokumentstatus und Anwesenheit koennen rollenbasiert exportiert werden.',
        icon: Icons.file_download_outlined,
        color: AirmiusColors.pink,
      ),
    ];

    return PageFrame(
      title: 'Import & Export',
      subtitle: 'Mitgliederlisten, Dubletten und Exporte',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('DATA OPS'),
                const SizedBox(height: 8),
                const Text(
                  'Vereine brauchen eine mobile Datenstrecke fuer bestehende Mitgliederlisten, externe Kontakte, Dubletten, Einladungen und kontrollierte Exporte.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: 'CSV', label: 'Import'),
                    Metric(value: 'Check', label: 'Dubletten'),
                    Metric(value: 'Invite', label: 'Einladung'),
                    Metric(value: 'Export', label: 'Daten'),
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
                const SectionLabel('MODUS'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Import', label: Text('Import')),
                    ButtonSegment(value: 'Pruefung', label: Text('Pruefung')),
                    ButtonSegment(value: 'Einladung', label: Text('Einladung')),
                    ButtonSegment(value: 'Export', label: Text('Export')),
                  ],
                  selected: {mode},
                  onSelectionChanged: (value) => setState(() => mode = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('REGELN'),
                const SizedBox(height: 8),
                _DataSwitch(title: 'CSV-Upload erlauben', value: csvUpload, color: AirmiusColors.blue, onChanged: (value) => setState(() => csvUpload = value)),
                _DataSwitch(title: 'Dubletten automatisch pruefen', value: duplicateCheck, color: AirmiusColors.amber, onChanged: (value) => setState(() => duplicateCheck = value)),
                _DataSwitch(title: 'Einladungen senden', value: inviteMembers, color: AirmiusColors.green, onChanged: (value) => setState(() => inviteMembers = value)),
                _DataSwitch(title: 'Export rollenbasiert erlauben', value: exportAllowed, color: AirmiusColors.pink, onChanged: (value) => setState(() => exportAllowed = value)),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final row in rows) ...[
            _ImportExportCard(row: row),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Aktueller Modus: $mode. Spaeter verbindet die API CSV-Dateien, Feldmapping, Validierung, Dubletten, Einladungen, Audit und Exporte.',
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Datenlauf vorbereiten',
                  icon: Icons.sync_alt_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Datenlauf vorbereiten',
                    body: 'Diese UI bereitet Mitgliederimport, Feldmapping, Dublettenpruefung, Einladungen, Export und Audit fuer die spaetere Laravel-API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.sync_alt_outlined,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ImportExportRow {
  const _ImportExportRow({
    required this.title,
    required this.status,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String title;
  final String status;
  final String body;
  final IconData icon;
  final Color color;
}

class _DataSwitch extends StatelessWidget {
  const _DataSwitch({
    required this.title,
    required this.value,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final Color color;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
      value: value,
      activeColor: color,
      onChanged: onChanged,
    );
  }
}

class _ImportExportCard extends StatelessWidget {
  const _ImportExportCard({required this.row});

  final _ImportExportRow row;

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
                    Expanded(child: Text(row.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                    StatusPill(row.status, color: row.color),
                  ],
                ),
                const SizedBox(height: 8),
                Text(row.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
