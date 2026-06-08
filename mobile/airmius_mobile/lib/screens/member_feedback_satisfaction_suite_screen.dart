import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class MemberFeedbackSatisfactionSuiteScreen extends StatelessWidget {
  const MemberFeedbackSatisfactionSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final signals = [
      _SignalItem('Vereinszufriedenheit', '92%', 'Mitglieder bewerten Kommunikation, Training, Events und Vereinsleben.', AirmiusColors.green, Icons.sentiment_satisfied_alt_outlined),
      _SignalItem('Kritische Hinweise', '7 offen', 'Beschwerden, Risiken, Datenschutz, Safety oder Eskalation an Admins.', AirmiusColors.red, Icons.report_problem_outlined),
      _SignalItem('Ideen & Wuensche', '24', 'Verbesserungen, neue Teams, Events, Kurse, Ausstattung und Services.', AirmiusColors.blue, Icons.lightbulb_outlined),
      _SignalItem('Trainerfeedback', 'Team', 'Feedback nach Training, Belastung, Stimmung und individuelle Rueckmeldung.', AirmiusColors.amber, Icons.sports_outlined),
    ];

    final workflow = [
      _WorkflowItem('Feedback erfassen', 'Kurzes Formular, Skala, Freitext, Kategorie, Anonymitaet und Datei.'),
      _WorkflowItem('Einordnen', 'Verein, Team, Event, Training, Mitgliedschaft, Zahlung, Support oder Safety.'),
      _WorkflowItem('Bearbeiten', 'Adminantwort, interne Notiz, Aufgabe, Eskalation oder Rueckfrage starten.'),
      _WorkflowItem('Lernen', 'Trend, Score, Export, Massnahmen und Follow-up fuer Vereinsentwicklung.'),
    ];

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Feedback & Zufriedenheit', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Feedback & Zufriedenheit',
        subtitle: 'Mitgliederfeedback, Zufriedenheit, Beschwerden, Ideen, Trainerfeedback und Follow-ups als mobile Vereins-UI.',
        trailing: const StatusPill('Member', color: AirmiusColors.blue),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('FEEDBACK CENTER'),
                  SizedBox(height: 10),
                  Text('Die App soll merken, wie es den Mitgliedern wirklich geht.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  SizedBox(height: 8),
                  Text('Feedback wird nicht nur gesammelt, sondern in Aufgaben, Trends, Adminantworten, Safety-Eskalationen und Vereinsverbesserungen ueberfuehrt.', style: TextStyle(color: AirmiusColors.muted, height: 1.42)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '92%', label: 'Score')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '31', label: 'Signale')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '8', label: 'Tasks')),
              ],
            ),
            const SizedBox(height: 14),
            for (final signal in signals) ...[
              AirmiusPanel(
                borderColor: signal.color.withValues(alpha: .44),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 50,
                      height: 50,
                      decoration: BoxDecoration(
                        color: signal.color.withValues(alpha: .14),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: signal.color.withValues(alpha: .45)),
                      ),
                      child: Icon(signal.icon, color: signal.color),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(signal.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                          const SizedBox(height: 5),
                          Text(signal.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                          const SizedBox(height: 10),
                          Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(signal.status, color: signal.color), const StatusPill('Follow-up')]),
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
                  const Eyebrow('BEARBEITUNGSFLOW'),
                  const SizedBox(height: 12),
                  for (final item in workflow) ...[
                    _WorkflowRow(item: item),
                    if (item != workflow.last) const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('VERKNUEPFUNGEN'),
                  SizedBox(height: 10),
                  _LinkLine(label: 'Support', value: 'Kritisches Feedback wird als Ticket oder Eskalation fortgefuehrt.'),
                  _LinkLine(label: 'Umfragen', value: 'Feedback kann in strukturierte Vereinsumfragen uebergehen.'),
                  _LinkLine(label: 'Training', value: 'Trainerfeedback beeinflusst Belastung, Planung und Teamstimmung.'),
                  _LinkLine(label: 'Audit', value: 'Bearbeitung, Antworten, Eskalationen und Loeschfristen bleiben sichtbar.'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SignalItem {
  const _SignalItem(this.title, this.status, this.body, this.color, this.icon);

  final String title;
  final String status;
  final String body;
  final Color color;
  final IconData icon;
}

class _WorkflowItem {
  const _WorkflowItem(this.title, this.body);

  final String title;
  final String body;
}

class _WorkflowRow extends StatelessWidget {
  const _WorkflowRow({required this.item});

  final _WorkflowItem item;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: AirmiusColors.blue.withValues(alpha: .16), borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.blue.withValues(alpha: .42))),
            child: const Icon(Icons.arrow_forward_outlined, color: AirmiusColors.blue, size: 19),
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

class _LinkLine extends StatelessWidget {
  const _LinkLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 9),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(width: 94, child: Text(label, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))),
            Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))),
          ],
        ),
      );
}
