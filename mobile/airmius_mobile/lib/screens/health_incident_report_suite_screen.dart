import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class HealthIncidentReportSuiteScreen extends StatefulWidget {
  const HealthIncidentReportSuiteScreen({super.key});

  @override
  State<HealthIncidentReportSuiteScreen> createState() => _HealthIncidentReportSuiteScreenState();
}

class _HealthIncidentReportSuiteScreenState extends State<HealthIncidentReportSuiteScreen> {
  String incidentType = 'Training';
  bool notifyGuardian = true;
  bool notifyClubAdmin = true;
  bool attachMedicalNote = true;
  bool createSupportTicket = false;

  @override
  Widget build(BuildContext context) {
    final reports = [
      const _IncidentRow(
        title: 'Knieverletzung beim Training',
        status: 'Dokumentiert',
        body: 'Trainer erfasst Zeitpunkt, Team, Erste-Hilfe-Notiz, betroffene Person und naechste Schritte.',
        icon: Icons.healing_outlined,
        color: AirmiusColors.amber,
      ),
      const _IncidentRow(
        title: 'Notfallkontakt informiert',
        status: 'Erledigt',
        body: 'Guardian oder Notfallkontakt wurde informiert; Verlauf bleibt für berechtigte Rollen sichtbar.',
        icon: Icons.family_restroom_outlined,
        color: AirmiusColors.green,
      ),
      const _IncidentRow(
        title: 'Gesundheitshinweis aktualisiert',
        status: 'Privat',
        body: 'User kann Allergien, medizinische Hinweise und Trainingsfreigaben kontrolliert bereitstellen.',
        icon: Icons.health_and_safety_outlined,
        color: AirmiusColors.blue,
      ),
      const _IncidentRow(
        title: 'Vorfall eskaliert',
        status: 'Support',
        body: 'Bei Streitfall oder schwerem Vorfall kann ein Supportticket mit Audit-Verlauf entstehen.',
        icon: Icons.support_agent_outlined,
        color: AirmiusColors.pink,
      ),
    ];

    return PageFrame(
      title: 'Gesundheit & Vorfaelle',
      subtitle: 'Training, Notfall und Dokumentation',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('SAFETY FLOW'),
                const SizedBox(height: 8),
                const Text(
                  'Vereine brauchen mobil eine sichere Strecke für Verletzungen, Gesundheitshinweise, Notfallkontakte, Guardian-Infos, Dokumentation und Eskalation.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '4', label: 'Faelle'),
                    Metric(value: 'SOS', label: 'Kontakt'),
                    Metric(value: 'Docs', label: 'Notiz'),
                    Metric(value: 'Audit', label: 'Verlauf'),
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
                const SectionLabel('VORFALLTYP'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Training', label: Text('Training')),
                    ButtonSegment(value: 'Event', label: Text('Event')),
                    ButtonSegment(value: 'Team', label: Text('Team')),
                    ButtonSegment(value: 'Support', label: Text('Support')),
                  ],
                  selected: {incidentType},
                  onSelectionChanged: (value) => setState(() => incidentType = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('OPTIONEN'),
                const SizedBox(height: 8),
                _IncidentSwitch(title: 'Guardian informieren', value: notifyGuardian, color: AirmiusColors.green, onChanged: (value) => setState(() => notifyGuardian = value)),
                _IncidentSwitch(title: 'Vereinsadmin informieren', value: notifyClubAdmin, color: AirmiusColors.blue, onChanged: (value) => setState(() => notifyClubAdmin = value)),
                _IncidentSwitch(title: 'Medizinische Notiz anhaengen', value: attachMedicalNote, color: AirmiusColors.amber, onChanged: (value) => setState(() => attachMedicalNote = value)),
                _IncidentSwitch(title: 'Supportticket erstellen', value: createSupportTicket, color: AirmiusColors.pink, onChanged: (value) => setState(() => createSupportTicket = value)),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final report in reports) ...[
            _IncidentCard(report: report),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Aktueller Kontext: $incidentType. Später verbindet die API Vorfall, Training/Event, Team, Rollenrechte, Guardian, Notfallkontakt, Dokumente und Audit-Verlauf.',
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Vorfall erfassen',
                  icon: Icons.report_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Vorfall erfassen',
                    body: 'Diese UI bereitet Vorfallmeldungen, Gesundheitshinweise, Guardian-Benachrichtigung, Dokumentation und Eskalation für die spätere Laravel-API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.report_outlined,
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

class _IncidentRow {
  const _IncidentRow({
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

class _IncidentSwitch extends StatelessWidget {
  const _IncidentSwitch({
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

class _IncidentCard extends StatelessWidget {
  const _IncidentCard({required this.report});

  final _IncidentRow report;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(icon: report.icon, color: report.color),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(report.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                    StatusPill(report.status, color: report.color),
                  ],
                ),
                const SizedBox(height: 8),
                Text(report.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
