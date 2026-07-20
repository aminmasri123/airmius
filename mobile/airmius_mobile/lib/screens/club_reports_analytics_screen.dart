import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_finance_cockpit_screen.dart';
import 'club_member_directory_screen.dart';
import 'club_event_attendance_screen.dart';

class ClubReportsAnalyticsScreen extends StatefulWidget {
  const ClubReportsAnalyticsScreen({super.key});

  @override
  State<ClubReportsAnalyticsScreen> createState() => _ClubReportsAnalyticsScreenState();
}

class _ClubReportsAnalyticsScreenState extends State<ClubReportsAnalyticsScreen> {
  String _period = 'Monat';
  bool _includeFinance = true;
  bool _includeAttendance = true;
  bool _includeRequests = true;
  bool _includeExports = true;

  final List<_ReportCardData> _reports = const [
    _ReportCardData(
      title: 'Mitgliederentwicklung',
      subtitle: 'Eintritte, Austritte, offene Anfragen, Altersgruppen und Teams.',
      value: '+12%',
      trend: 'Wachstum',
      icon: Icons.trending_up_outlined,
      color: AirmiusColors.green,
      points: ['Neue Mitglieder: 8', 'Offene Anfragen: 5', 'Rückzug: 1'],
    ),
    _ReportCardData(
      title: 'Beiträge & Zahlungen',
      subtitle: 'Zahlstatus, Intervall, offene Beiträge, Barzahlung, Überweisung und SEPA.',
      value: '93%',
      trend: 'Bezahlt',
      icon: Icons.account_balance_wallet_outlined,
      color: AirmiusColors.blue,
      points: ['Offen: 4', 'Bar: 7', 'Überweisung: 31'],
    ),
    _ReportCardData(
      title: 'Anwesenheit',
      subtitle: 'Training, Events, Check-in-Quote, Warteliste und No-Shows.',
      value: '78%',
      trend: 'Quote',
      icon: Icons.fact_check_outlined,
      color: AirmiusColors.amber,
      points: ['Trainings: 14', 'Events: 3', 'No-Shows: 6'],
    ),
    _ReportCardData(
      title: 'Vereinsaktivitaet',
      subtitle: 'Beiträge, Kommentare, Chat-Aktivitaet, Dateien und sichtbare Inhalte.',
      value: '42',
      trend: 'Aktionen',
      icon: Icons.insights_outlined,
      color: AirmiusColors.blueDeep,
      points: ['Posts: 9', 'Kommentare: 18', 'Dateien: 5'],
    ),
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
                          title: 'Vereinsberichte & Auswertungen',
                          subtitle: 'Mitglieder, Zahlungen, Anwesenheit, Aktivitaet, Exporte und Vorstandsauswertung.',
                        ),
                        const SizedBox(height: 16),
                        _ReportsHero(onExport: () => _toast('Export vorbereitet')),
                        const SizedBox(height: 16),
                        _PeriodPicker(value: _period, onChanged: (value) => setState(() => _period = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Berichtsinhalt',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Finanzen einbeziehen', subtitle: 'Beiträge, Zahlstatus, Zahlungsart und offene Posten.', value: _includeFinance, onChanged: (value) => setState(() => _includeFinance = value)),
                              _SwitchRow(title: 'Anwesenheit einbeziehen', subtitle: 'Training, Events, Warteliste, Check-ins und No-Shows.', value: _includeAttendance, onChanged: (value) => setState(() => _includeAttendance = value)),
                              _SwitchRow(title: 'Mitgliedsanfragen einbeziehen', subtitle: 'Offene, angenommene, abgelehnte und zurückgezogene Anfragen.', value: _includeRequests, onChanged: (value) => setState(() => _includeRequests = value)),
                              _SwitchRow(title: 'Export vorbereiten', subtitle: 'CSV, PDF und Vorstandszusammenfassung für die API vormerken.', value: _includeExports, onChanged: (value) => setState(() => _includeExports = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final report in _reports) ...[
                          _ReportCard(data: report, period: _period),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Verknuepfte Datenquellen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Mitglieder', icon: Icons.badge_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMemberDirectoryScreen()))),
                              AirmiusButton(label: 'Finanzen', icon: Icons.account_balance_wallet_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubFinanceCockpitScreen()))),
                              AirmiusButton(label: 'Events', icon: Icons.event_available_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubEventAttendanceScreen()))),
                              AirmiusButton(label: 'Export', icon: Icons.download_outlined, secondary: true, onPressed: () => _toast('CSV/PDF Export vorbereitet')),
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

class _ReportsHero extends StatelessWidget {
  const _ReportsHero({required this.onExport});

  final VoidCallback onExport;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF102033), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
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
                    Eyebrow('VEREINSREPORTING'),
                    SizedBox(height: 4),
                    Text('Auswertungen für Vorstand und Admins', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900)),
                  ],
                ),
              ),
              AirmiusButton(label: 'Export', icon: Icons.download_outlined, onPressed: onExport),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Die mobile App bereitet Reports für Mitglieder, Finanzen, Anwesenheit und Aktivitaet vor, damit Vereine nicht im Blindflug arbeiten.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(
            children: [
              Expanded(child: MetricCard(value: '4', label: 'Reports')),
              SizedBox(width: 10),
              Expanded(child: MetricCard(value: '6', label: 'Quellen')),
              SizedBox(width: 10),
              Expanded(child: MetricCard(value: '3', label: 'Exports')),
            ],
          ),
        ],
      ),
    );
  }
}

class _PeriodPicker extends StatelessWidget {
  const _PeriodPicker({required this.value, required this.onChanged});

  final String value;
  final ValueChanged<String> onChanged;

  static const periods = ['Woche', 'Monat', 'Quartal', 'Jahr'];

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final period in periods) ...[
            ChoiceChip(
              label: Text(period),
              selected: value == period,
              onSelected: (_) => onChanged(period),
              selectedColor: AirmiusColors.blue.withValues(alpha: .24),
              backgroundColor: AirmiusColors.card,
              labelStyle: TextStyle(color: value == period ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
              side: BorderSide(color: value == period ? AirmiusColors.blue : AirmiusColors.border),
            ),
            const SizedBox(width: 8),
          ],
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
          Switch.adaptive(value: value, onChanged: onChanged, activeThumbColor: AirmiusColors.blue),
        ],
      ),
    );
  }
}

class _ReportCard extends StatelessWidget {
  const _ReportCard({required this.data, required this.period});

  final _ReportCardData data;
  final String period;

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
                width: 52,
                height: 52,
                decoration: BoxDecoration(color: data.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(18), border: Border.all(color: data.color.withValues(alpha: .5))),
                child: Icon(data.icon, color: data.color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(data.value, style: const TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900)),
                    Text('$period - ${data.trend}', style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)),
                  ],
                ),
              ),
              StatusPill(data.trend, color: data.color),
            ],
          ),
          const SizedBox(height: 12),
          Text(data.subtitle, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [for (final point in data.points) StatusPill(point, color: AirmiusColors.blue)]),
        ],
      ),
    );
  }
}

class _ReportCardData {
  const _ReportCardData({required this.title, required this.subtitle, required this.value, required this.trend, required this.icon, required this.color, required this.points});

  final String title;
  final String subtitle;
  final String value;
  final String trend;
  final IconData icon;
  final Color color;
  final List<String> points;
}
