import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';
import 'training_operations_screen.dart';
import 'training_log_detail_screen.dart';
import 'training_plan_detail_screen.dart';

class CoachActionDetailScreen extends StatefulWidget {
  const CoachActionDetailScreen({super.key, required this.title, required this.body, required this.status, required this.icon});

  final String title;
  final String body;
  final String status;
  final IconData icon;

  @override
  State<CoachActionDetailScreen> createState() => _CoachActionDetailScreenState();
}

class _CoachActionDetailScreenState extends State<CoachActionDetailScreen> {
  String _priority = 'Normal';
  bool _notifyAthlete = true;
  bool _markResolved = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Coach-Aktion', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.body,
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(widget.icon, color: AirmiusColors.blue, size: 34),
            const SizedBox(width: 12),
            const Expanded(child: Text('Coach-Aufgaben bleiben mit Athlet, Plan, Log und Risiko-Hinweis verbunden.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '2', label: 'Logs')), SizedBox(width: 10), Expanded(child: MetricCard(value: '68%', label: 'Readiness')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Risiko'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Bearbeitung'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: _priority,
              dropdownColor: AirmiusColors.card,
              decoration: _fieldDecoration('Prioritaet'),
              items: const ['Niedrig', 'Normal', 'Hoch', 'Kritisch'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _priority = value ?? _priority),
            ),
            const SizedBox(height: 10),
            const AirmiusTextField(label: 'Coach-Feedback', hint: 'Antwort, Anpassung oder Planhinweis schreiben', icon: Icons.rate_review_outlined, maxLines: 4),
            SwitchListTile(value: _notifyAthlete, onChanged: (value) => setState(() => _notifyAthlete = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Athlet benachrichtigen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Push und Inbox-Eintrag vorbereiten.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _markResolved, onChanged: (value) => setState(() => _markResolved = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Aufgabe abschliessen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Coach-Aktion als erledigt markieren.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Log ansehen', icon: Icons.assignment_turned_in_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TrainingLogDetailScreen(title: widget.title, body: widget.body, status: 'Log', icon: widget.icon)))),
            AirmiusButton(label: 'Plan oeffnen', icon: Icons.calendar_month_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TrainingPlanDetailScreen(title: widget.title, body: widget.body, status: 'Plan', icon: widget.icon)))),
            AirmiusButton(label: 'Feedback senden', icon: Icons.send_outlined, onPressed: () => openUiAction(context, title: 'Feedback senden', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.send_outlined)),
            AirmiusButton(label: 'Operations', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TrainingOperationsScreen()))),
          ]),
        ]),
      ),
    );
  }

  InputDecoration _fieldDecoration(String label) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800),
      filled: true,
      fillColor: AirmiusColors.input,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.blue, width: 1.4)),
    );
  }
}
