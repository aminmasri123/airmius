import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class MeetingMinutesDecisionLogSuiteScreen extends StatelessWidget {
  const MeetingMinutesDecisionLogSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final agenda = [
      _AgendaItem('Vorstandssitzung', '18:30', 'Budget, Beitragsordnung, neue Mitglieder', AirmiusColors.blue),
      _AgendaItem('Teamleiter-Runde', '19:15', 'Trainingszeiten, Hallenbuchung, Material', AirmiusColors.green),
      _AgendaItem('Mitgliederversammlung', '20:00', 'Antraege, Abstimmungen, Satzung, Beschluesse', AirmiusColors.amber),
    ];

    final decisions = [
      _DecisionItem('Beitragsordnung aktualisieren', 'Beschlossen', 'Dokumentversion 2026-06 verknuepfen und Mitglieder informieren.', AirmiusColors.green),
      _DecisionItem('Neue Trikots bestellen', 'Aufgabe offen', 'Angebote pruefen, Sponsorfreigabe einholen, Bestellung vorbereiten.', AirmiusColors.amber),
      _DecisionItem('Datenschutzregel bestaetigen', 'Audit', 'Consent-Pflicht fuer neue Mitgliedsantraege aktivieren.', AirmiusColors.blue),
    ];

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Sitzungen & Beschluesse', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Sitzungen & Beschluesse',
        subtitle: 'Agenda, Protokolle, Abstimmungen, Aufgaben, Dokumente und Beschlusslog als mobile Vereinsverwaltung.',
        trailing: const StatusPill('Admin', color: AirmiusColors.amber),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('MEETING SUITE'),
                  SizedBox(height: 10),
                  Text('Vereinsentscheidungen sollen nicht im Chat verloren gehen.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  SizedBox(height: 8),
                  Text('Diese mobile UI fuehrt Sitzungen, Protokolle, Beschluesse, Aufgaben und verknuepfte Dateien zusammen, damit spaeter alles revisionssicher ueber Laravel gespeichert werden kann.', style: TextStyle(color: AirmiusColors.muted, height: 1.42)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '3', label: 'Sitzungen')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '12', label: 'Aufgaben')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: 'Audit', label: 'Log')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('AGENDA HEUTE'),
                  const SizedBox(height: 12),
                  for (final item in agenda) ...[
                    _AgendaRow(item: item),
                    if (item != agenda.last) const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final item in decisions) ...[
              AirmiusPanel(
                borderColor: item.color.withValues(alpha: .44),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.fact_check_outlined, color: item.color, size: 30),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                          const SizedBox(height: 5),
                          Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                          const SizedBox(height: 10),
                          Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.status, color: item.color), const StatusPill('Dokument verknuepft')]),
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
                children: const [
                  Eyebrow('VERKNUEPFUNGEN'),
                  SizedBox(height: 10),
                  _LinkLine(icon: Icons.folder_copy_outlined, title: 'Dateimanager', body: 'Protokolle, Satzung, Beitragsordnung und Anhaenge direkt mit Vereinsdateien verbinden.'),
                  _LinkLine(icon: Icons.how_to_vote_outlined, title: 'Abstimmungen', body: 'Beschluesse koennen aus Umfragen oder Live-Abstimmungen entstehen.'),
                  _LinkLine(icon: Icons.task_alt_outlined, title: 'Aufgaben', body: 'Beschluss erzeugt Verantwortliche, Fristen, Erinnerung und Fortschritt.'),
                  _LinkLine(icon: Icons.history_outlined, title: 'Audit', body: 'Versionen, Freigaben, Aenderungen und Widerrufe bleiben nachvollziehbar.'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AgendaItem {
  const _AgendaItem(this.title, this.time, this.body, this.color);

  final String title;
  final String time;
  final String body;
  final Color color;
}

class _DecisionItem {
  const _DecisionItem(this.title, this.status, this.body, this.color);

  final String title;
  final String status;
  final String body;
  final Color color;
}

class _AgendaRow extends StatelessWidget {
  const _AgendaRow({required this.item});

  final _AgendaItem item;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 58,
            padding: const EdgeInsets.symmetric(vertical: 8),
            alignment: Alignment.center,
            decoration: BoxDecoration(color: item.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(13), border: Border.all(color: item.color.withValues(alpha: .42))),
            child: Text(item.time, style: TextStyle(color: item.color, fontWeight: FontWeight.w900, fontSize: 12)),
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
  const _LinkLine({required this.icon, required this.title, required this.body});

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: AirmiusColors.blue),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 3),
                  Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
          ],
        ),
      );
}
