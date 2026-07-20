import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class TrainingOperationsScreen extends StatefulWidget {
  const TrainingOperationsScreen({super.key});

  @override
  State<TrainingOperationsScreen> createState() => _TrainingOperationsScreenState();
}

class _TrainingOperationsScreenState extends State<TrainingOperationsScreen> {
  String _tab = 'Events';
  bool _notify = true;
  bool _coachReview = true;

  @override
  Widget build(BuildContext context) {
    final items = _items.where((item) => _tab == 'Alle' || item.tab == _tab).toList();
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Training Operations', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Training Operations',
        subtitle: 'Events, Teilnahme, Warteliste, Absagen, Plaene, Logs, Coach-Feedback und Risiko',
        trailing: StatusPill(_tab),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Training & Event Workflows'),
                  const SizedBox(height: 8),
                  const Text('Diese mobile Operations-Ansicht buendelt die Web-App-Funktionen für Vereins-Events, Trainingsplaene, Logs und Coach-Aufgaben. Sie bleibt nah am dunklen Airmius-Design und bereitet später Laravel-API-Mutationen vor.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final tab in const ['Events', 'Teilnahme', 'Plaene', 'Logs', 'Coach', 'Alle'])
                        ChoiceChip(
                          selected: _tab == tab,
                          label: Text(tab),
                          onSelected: (_) => setState(() => _tab = tab),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '5', label: 'Events')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4/7', label: 'Plan')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Feedback'))]),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Automationen'),
                  SwitchListTile(value: _notify, onChanged: (value) => setState(() => _notify = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Teilnehmer informieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Teilnahme, Absage, Warteliste, Planfreigabe und Feedback senden später Push/In-App Hinweise.', style: TextStyle(color: AirmiusColors.muted))),
                  SwitchListTile(value: _coachReview, onChanged: (value) => setState(() => _coachReview = value), activeThumbColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Coach Review erforderlich', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Logs, Risiko-Hinweise und Wochenplaene werden für Trainer sichtbar gemacht.', style: TextStyle(color: AirmiusColors.muted))),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final item in items) ...[
              _TrainingOperationCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _TrainingOperationCard extends StatelessWidget {
  const _TrainingOperationCard({required this.item});

  final _TrainingOperation item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: item.danger ? AirmiusColors.red.withValues(alpha: 0.45) : AirmiusColors.border,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(item.icon, color: item.danger ? AirmiusColors.red : AirmiusColors.blue, size: 28),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                    const SizedBox(height: 9),
                    Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.tab), StatusPill(item.status, color: item.danger ? AirmiusColors.red : AirmiusColors.blue)]),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(label: item.action, icon: item.icon, danger: item.danger, secondary: !item.danger, onPressed: () => _run(context, item)),
              AirmiusButton(label: 'Kontext', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Kontext', body: 'Event, Team, Verein, Athlet, Plan, Log, Sichtbarkeit, Benachrichtigung und Audit für ${item.title} anzeigen.', status: 'Context', icon: Icons.manage_search_outlined)),
            ],
          ),
        ],
      ),
    );
  }

  void _run(BuildContext context, _TrainingOperation item) {
    void action() => openUiAction(context, title: item.action, body: '${item.action}: ${item.body}', status: item.status, icon: item.icon);
    if (item.danger) {
      confirmDanger(context, '${item.action}?', 'Diese Event-/Training-Aktion kann Teilnahme, Plan, Log oder Coachstatus verändern und wird später auditiert.', item.action, action);
      return;
    }
    action();
  }
}

class _TrainingOperation {
  const _TrainingOperation({required this.tab, required this.title, required this.body, required this.status, required this.icon, required this.action, this.danger = false});

  final String tab;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String action;
  final bool danger;
}

const _items = [
  _TrainingOperation(tab: 'Events', title: 'Event erstellen', body: 'Titel, Ort, Zeit, Team, Sichtbarkeit, Kapazitaet und Eventchat vorbereiten.', status: 'Create', icon: Icons.add_circle_outline, action: 'Event speichern'),
  _TrainingOperation(tab: 'Events', title: 'Event aktualisieren', body: 'Zeit, Standort, Beschreibung, Teambezug, Kommentare und Sichtbarkeit ändern.', status: 'Update', icon: Icons.edit_calendar_outlined, action: 'Event aktualisieren'),
  _TrainingOperation(tab: 'Events', title: 'Event absagen', body: 'Absagegrund, Teilnehmerhinweis, Kalenderstatus und Chatmeldung vorbereiten.', status: 'Cancel', icon: Icons.event_busy_outlined, action: 'Event absagen', danger: true),
  _TrainingOperation(tab: 'Events', title: 'Erinnerung senden', body: 'Push, E-Mail oder Chat-Hinweis an Teilnehmer und Warteliste senden.', status: 'Reminder', icon: Icons.notifications_active_outlined, action: 'Reminder senden'),
  _TrainingOperation(tab: 'Teilnahme', title: 'Teilnahme bestätigen', body: 'User als Teilnehmer eintragen, Kapazitaet prüfen und Eventchat freigeben.', status: 'Join', icon: Icons.how_to_reg_outlined, action: 'Teilnehmen'),
  _TrainingOperation(tab: 'Teilnahme', title: 'Teilnahme absagen', body: 'Abmeldung speichern, Warteliste nachrücken lassen und Coach informieren.', status: 'Leave', icon: Icons.logout_outlined, action: 'Absagen', danger: true),
  _TrainingOperation(tab: 'Teilnahme', title: 'Warteliste verwalten', body: 'Wartende Personen sortieren, nachrücken lassen oder ablehnen.', status: 'Waitlist', icon: Icons.pending_actions_outlined, action: 'Warteliste speichern'),
  _TrainingOperation(tab: 'Teilnahme', title: 'Anwesenheit erfassen', body: 'Anwesend, abwesend, entschuldigt und Coach-Notiz für Event speichern.', status: 'Attendance', icon: Icons.fact_check_outlined, action: 'Anwesenheit speichern'),
  _TrainingOperation(tab: 'Plaene', title: 'Trainingsplan erstellen', body: 'Wochenplan, Einheiten, Intensitaet, Ziel, Team und Athletenfreigabe definieren.', status: 'Plan', icon: Icons.calendar_month_outlined, action: 'Plan erstellen'),
  _TrainingOperation(tab: 'Plaene', title: 'Plan freigeben', body: 'Plan für Team oder Athlet sichtbar machen und Push vorbereiten.', status: 'Publish', icon: Icons.task_alt_outlined, action: 'Plan freigeben'),
  _TrainingOperation(tab: 'Plaene', title: 'Plan duplizieren', body: 'Vorlage für naechste Woche kopieren, Belastung anpassen und Review markieren.', status: 'Copy', icon: Icons.copy_outlined, action: 'Plan duplizieren'),
  _TrainingOperation(tab: 'Logs', title: 'Training loggen', body: 'Distanz, Dauer, RPE, Puls, Kommentar, Medien und Sichtbarkeit erfassen.', status: 'Log', icon: Icons.assignment_turned_in_outlined, action: 'Log speichern'),
  _TrainingOperation(tab: 'Logs', title: 'Log auswerten', body: 'Coach-Kommentar, Readiness, Risiko und Plananpassung aus Log ableiten.', status: 'Analyze', icon: Icons.query_stats_outlined, action: 'Auswerten'),
  _TrainingOperation(tab: 'Logs', title: 'Log löschen', body: 'Fehlerhaften Trainingslog entfernen und Auditgrund speichern.', status: 'Delete', icon: Icons.delete_outline, action: 'Log löschen', danger: true),
  _TrainingOperation(tab: 'Coach', title: 'Feedback senden', body: 'Coach-Feedback für Log oder Plan schreiben und Athlet benachrichtigen.', status: 'Feedback', icon: Icons.rate_review_outlined, action: 'Feedback senden'),
  _TrainingOperation(tab: 'Coach', title: 'Risiko markieren', body: 'Hohe Belastung, niedrige Readiness, Verletzungshinweis oder fehlende Erholung markieren.', status: 'Risk', icon: Icons.warning_amber_outlined, action: 'Risiko markieren', danger: true),
  _TrainingOperation(tab: 'Coach', title: 'Coach Weekly laden', body: 'Wochenübersicht mit offenen Logs, Risiko-Athleten und Planstatus anzeigen.', status: 'Weekly', icon: Icons.sports_score_outlined, action: 'Weekly laden'),
];
