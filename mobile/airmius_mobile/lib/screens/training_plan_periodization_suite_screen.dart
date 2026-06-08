import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class TrainingPlanPeriodizationSuiteScreen extends StatelessWidget {
  const TrainingPlanPeriodizationSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final blocks = [
      _PlanBlock('Grundlage', 'Woche 1-4', 'Ausdauer, Technik, Mobilitaet, Belastung langsam steigern.', AirmiusColors.green, Icons.timeline_outlined),
      _PlanBlock('Aufbau', 'Woche 5-8', 'Intensitaet, Kraft, Teamdrills, Coach-Feedback und Tests.', AirmiusColors.blue, Icons.fitness_center_outlined),
      _PlanBlock('Wettkampf', 'Woche 9-12', 'Tapering, Spielvorbereitung, Regeneration und Risiko-Check.', AirmiusColors.amber, Icons.emoji_events_outlined),
      _PlanBlock('Recovery', 'Optional', 'Ruhe, Verletzungsnotizen, Wiedereinstieg und Guardian-Hinweise.', AirmiusColors.pink, Icons.health_and_safety_outlined),
    ];

    final checks = [
      _CheckItem('Coach-Freigabe', 'Trainer prueft Plan, Belastung, Ziele und Teamfreigabe.'),
      _CheckItem('Athletendaten', 'Alter, Leistungslevel, Verletzungen, Ziele, Verfuegbarkeit und Datenschutz.'),
      _CheckItem('Kalender-Sync', 'Trainings, Events, Abwesenheiten und Erinnerungen werden verbunden.'),
      _CheckItem('Fortschritt', 'Logs, RPE, Notizen, Messwerte, Badges und Anpassungsvorschlaege.'),
    ];

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Trainingsplanung', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Trainingsplanung',
        subtitle: 'Periodisierung, Coach-Freigabe, Kalender, Belastung, Logs und Fortschritt als mobile Sport-UI.',
        trailing: const StatusPill('Sport', color: AirmiusColors.green),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('TRAINING SUITE'),
                  SizedBox(height: 10),
                  Text('Trainer planen nicht nur Termine, sondern Entwicklung.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  SizedBox(height: 8),
                  Text('Diese mobile Ansicht bildet Trainingszyklen, Belastung, Freigaben, Kalender-Sync, Fortschritt und sichere Anpassungen fuer Teams und Sportler ab.', style: TextStyle(color: AirmiusColors.muted, height: 1.42)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '12W', label: 'Zyklus')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '84%', label: 'Plan')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: 'Coach', label: 'OK')),
              ],
            ),
            const SizedBox(height: 14),
            for (final block in blocks) ...[
              AirmiusPanel(
                borderColor: block.color.withValues(alpha: .44),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 50,
                      height: 50,
                      decoration: BoxDecoration(
                        color: block.color.withValues(alpha: .14),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: block.color.withValues(alpha: .45)),
                      ),
                      child: Icon(block.icon, color: block.color),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(block.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                          const SizedBox(height: 5),
                          Text(block.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                          const SizedBox(height: 10),
                          Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(block.range, color: block.color), const StatusPill('Planbar')]),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('COACH WORKFLOW'),
                  const SizedBox(height: 12),
                  for (final check in checks) ...[
                    _CheckRow(item: check),
                    if (check != checks.last) const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('API & DATEN'),
                  SizedBox(height: 10),
                  _ApiLine(label: 'plan_cycle', value: 'Sportart, Team, Ziel, Wochen, Einheiten, Intensitaet, Coach'),
                  _ApiLine(label: 'athlete_scope', value: 'Alter, Level, Guardian, Verletzung, Verfuegbarkeit, Datenschutz'),
                  _ApiLine(label: 'calendar_sync', value: 'Training, Event, Abwesenheit, Erinnerung, Check-in'),
                  _ApiLine(label: 'progress_log', value: 'RPE, Notiz, Leistung, Medien, Feedback, Anpassung, Audit'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PlanBlock {
  const _PlanBlock(this.title, this.range, this.body, this.color, this.icon);

  final String title;
  final String range;
  final String body;
  final Color color;
  final IconData icon;
}

class _CheckItem {
  const _CheckItem(this.title, this.body);

  final String title;
  final String body;
}

class _CheckRow extends StatelessWidget {
  const _CheckRow({required this.item});

  final _CheckItem item;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: AirmiusColors.green.withValues(alpha: .16), borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.green.withValues(alpha: .42))),
            child: const Icon(Icons.check_circle_outline, color: AirmiusColors.green, size: 19),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
        ],
      );
}

class _ApiLine extends StatelessWidget {
  const _ApiLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 9),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(width: 118, child: Text(label, style: const TextStyle(color: AirmiusColors.green, fontWeight: FontWeight.w900))),
            Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))),
          ],
        ),
      );
}
