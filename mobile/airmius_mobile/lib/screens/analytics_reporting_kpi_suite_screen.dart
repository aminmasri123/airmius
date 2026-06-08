import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class AnalyticsReportingKpiSuiteScreen extends StatefulWidget {
  const AnalyticsReportingKpiSuiteScreen({super.key});

  @override
  State<AnalyticsReportingKpiSuiteScreen> createState() => _AnalyticsReportingKpiSuiteScreenState();
}

class _AnalyticsReportingKpiSuiteScreenState extends State<AnalyticsReportingKpiSuiteScreen> {
  String period = 'Monat';
  bool clubMetrics = true;
  bool financeMetrics = true;
  bool engagementMetrics = true;
  bool exportReports = true;

  @override
  Widget build(BuildContext context) {
    final reports = [
      const _ReportRow(
        title: 'Mitgliederentwicklung',
        status: '+12%',
        body: 'Neue Anfragen, angenommene Mitglieder, Rueckzuege, offene Rueckfragen und Teamzuweisungen.',
        icon: Icons.groups_2_outlined,
        color: AirmiusColors.green,
      ),
      const _ReportRow(
        title: 'Finanzen',
        status: '180 EUR',
        body: 'Offene Beitraege, bezahlte Rechnungen, Rueckerstattungen, Mahnungen und Zahlungsarten.',
        icon: Icons.receipt_long_outlined,
        color: AirmiusColors.amber,
      ),
      const _ReportRow(
        title: 'Community & Events',
        status: '84%',
        body: 'Feed-Aktivitaet, Kommentare, Event-RSVPs, Trainingsteilnahme und Benachrichtigungsrate.',
        icon: Icons.insights_outlined,
        color: AirmiusColors.blue,
      ),
      const _ReportRow(
        title: 'Support & Moderation',
        status: 'SLA',
        body: 'Tickets, Eskalationen, gemeldete Inhalte, Audit-Faelle und Bearbeitungszeiten.',
        icon: Icons.admin_panel_settings_outlined,
        color: AirmiusColors.pink,
      ),
    ];

    return PageFrame(
      title: 'Analytics & Reports',
      subtitle: 'KPIs, Trends und Export',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('REPORTING'),
                const SizedBox(height: 8),
                const Text(
                  'Vereine und Plattformadmins brauchen mobile Kennzahlen: Mitglieder, Zahlungen, Events, Feed, Support, Ads, Marketplace und Moderation.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '4', label: 'Reports'),
                    Metric(value: 'KPI', label: 'Trends'),
                    Metric(value: 'PDF', label: 'Export'),
                    Metric(value: 'API', label: 'Daten'),
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
                const SectionLabel('ZEITRAUM'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Woche', label: Text('Woche')),
                    ButtonSegment(value: 'Monat', label: Text('Monat')),
                    ButtonSegment(value: 'Quartal', label: Text('Quartal')),
                    ButtonSegment(value: 'Jahr', label: Text('Jahr')),
                  ],
                  selected: {period},
                  onSelectionChanged: (value) => setState(() => period = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('DATENBEREICHE'),
                const SizedBox(height: 8),
                _AnalyticsSwitch(title: 'Club-Kennzahlen', value: clubMetrics, color: AirmiusColors.green, onChanged: (value) => setState(() => clubMetrics = value)),
                _AnalyticsSwitch(title: 'Finanz-Kennzahlen', value: financeMetrics, color: AirmiusColors.amber, onChanged: (value) => setState(() => financeMetrics = value)),
                _AnalyticsSwitch(title: 'Engagement-Kennzahlen', value: engagementMetrics, color: AirmiusColors.blue, onChanged: (value) => setState(() => engagementMetrics = value)),
                _AnalyticsSwitch(title: 'Reports exportieren', value: exportReports, color: AirmiusColors.pink, onChanged: (value) => setState(() => exportReports = value)),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final report in reports) ...[
            _ReportCard(report: report),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('EXPORT'),
                const SizedBox(height: 8),
                Text(
                  'Aktueller Zeitraum: $period. Spaeter koennen CSV, PDF, Diagramme, Rollenrechte und geplante Reports per Laravel-API angebunden werden.',
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Report exportieren',
                  icon: Icons.file_download_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Report exportieren',
                    body: 'Diese UI bereitet KPI-Reports, CSV/PDF-Export, Diagramme, Rollenrechte und geplante Auswertungen fuer die spaetere API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.file_download_outlined,
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

class _ReportRow {
  const _ReportRow({
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

class _AnalyticsSwitch extends StatelessWidget {
  const _AnalyticsSwitch({
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

class _ReportCard extends StatelessWidget {
  const _ReportCard({required this.report});

  final _ReportRow report;

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
