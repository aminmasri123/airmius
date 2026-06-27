import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'coach_action_detail_screen.dart';
import 'training_operations_screen.dart';

class TrainerCockpitScreen extends StatefulWidget {
  const TrainerCockpitScreen({super.key});

  @override
  State<TrainerCockpitScreen> createState() => _TrainerCockpitScreenState();
}

class _TrainerCockpitScreenState extends State<TrainerCockpitScreen> {
  String _filter = 'Woche';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Trainer-Cockpit', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Trainer-Cockpit',
        subtitle: 'Athleten, Planerfuellung, Feedback, Risiken und Wochenaktionen',
        trailing: const StatusPill('Coach'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Coach Woche'),
            const SizedBox(height: 8),
            const Text('3 Aktionen brauchen deine Aufmerksamkeit.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('Feedback beantworten, überfaellige Einheiten prüfen und Risiko-Athleten frueh erkennen.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: ['Heute', 'Woche', 'Athleten', 'Feedback'].map((item) {
              return ChoiceChip(
                selected: _filter == item,
                label: Text(item),
                onSelected: (_) => setState(() => _filter = item),
                selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                backgroundColor: AirmiusColors.cardSoft,
                side: BorderSide(color: _filter == item ? AirmiusColors.blue : AirmiusColors.border),
                labelStyle: TextStyle(color: _filter == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
              );
            }).toList()),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '11', label: 'Athleten')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Offen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '82%', label: 'Readiness'))]),
          const SizedBox(height: 14),
          const _CoachAction(icon: Icons.rate_review_outlined, title: 'Feedback beantworten', body: '2 Trainingseinheiten warten auf Trainerfeedback.', status: '2 offen', color: AirmiusColors.amber),
          const SizedBox(height: 12),
          const _CoachAction(icon: Icons.warning_amber_outlined, title: 'Risiko-Athleten prüfen', body: 'Hohe Belastung, wenig Schlaf oder verpasste Einheiten erkennen.', status: '1 Risiko', color: AirmiusColors.red),
          const SizedBox(height: 12),
          const _CoachAction(icon: Icons.calendar_month_outlined, title: 'Wochenplan freigeben', body: 'Plan für Laufgruppe prüfen und für Teams sichtbar machen.', status: 'Entwurf', color: AirmiusColors.blue),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
            Eyebrow('Athletenübersicht'),
            SizedBox(height: 10),
            _AthleteLine(name: 'ZBB Konto', meta: '4/5 Einheiten - stabil', readiness: '92%'),
            _AthleteLine(name: 'Amir Masri', meta: '2 Einheiten offen - Rückfrage', readiness: '68%'),
            _AthleteLine(name: 'Junior Mitglied', meta: 'Guardian-Freigabe aktiv', readiness: '74%'),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Plan erstellen', icon: Icons.add_task_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CoachActionDetailScreen(title: 'Plan erstellen', body: 'Neuen Wochenplan für Team oder Athleten vorbereiten.', status: 'Entwurf', icon: Icons.add_task_outlined)))),
            AirmiusButton(label: 'Feedback senden', icon: Icons.send_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CoachActionDetailScreen(title: 'Feedback senden', body: 'Coach-Feedback für offene Trainingslogs schreiben.', status: 'Feedback', icon: Icons.send_outlined)))),
            AirmiusButton(label: 'Coach Operations', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TrainingOperationsScreen()))),
          ]),
        ]),
      ),
    );
  }
}

class _CoachAction extends StatelessWidget {
  const _CoachAction({required this.icon, required this.title, required this.body, required this.status, required this.color});

  final IconData icon;
  final String title;
  final String body;
  final String status;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CoachActionDetailScreen(title: title, body: body, status: status, icon: icon))),
      borderColor: color.withValues(alpha: 0.45),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: color, size: 28),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)), const SizedBox(height: 10), StatusPill(status, color: color)])),
        const Icon(Icons.chevron_right, color: AirmiusColors.muted),
      ]),
    );
  }
}

class _AthleteLine extends StatelessWidget {
  const _AthleteLine({required this.name, required this.meta, required this.readiness});

  final String name;
  final String meta;
  final String readiness;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(children: [
        AirmiusAvatar(name),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(meta, style: const TextStyle(color: AirmiusColors.muted, height: 1.3))])),
        StatusPill(readiness, color: AirmiusColors.green),
      ]),
    );
  }
}
