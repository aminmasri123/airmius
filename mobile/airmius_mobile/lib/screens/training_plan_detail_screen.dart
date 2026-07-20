import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'training_log_detail_screen.dart';

class TrainingPlanDetailScreen extends StatefulWidget {
  const TrainingPlanDetailScreen({
    super.key,
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
  });

  final String title;
  final String body;
  final String status;
  final IconData icon;

  @override
  State<TrainingPlanDetailScreen> createState() => _TrainingPlanDetailScreenState();
}

class _TrainingPlanDetailScreenState extends State<TrainingPlanDetailScreen> {
  String _cycle = 'Woche 24';
  bool _publishToAthletes = true;
  bool _requireLog = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Trainingsplan', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: widget.title,
        subtitle: 'Plan, Zuweisung, Regeln und Log-Pflicht',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(widget.icon, color: AirmiusColors.blue, size: 30),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Eyebrow('Planvorlage'),
                            const SizedBox(height: 4),
                            Text(widget.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                          ],
                        ),
                      ),
                      StatusPill(widget.status),
                    ],
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _cycle,
                    dropdownColor: AirmiusColors.card,
                    decoration: _fieldDecoration('Trainingszyklus'),
                    items: const ['Woche 24', 'Woche 25', 'Monatsblock Juni', 'Vorbereitung']
                        .map((item) => DropdownMenuItem(value: item, child: Text(item)))
                        .toList(),
                    onChanged: (value) => setState(() => _cycle = value ?? _cycle),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '6', label: 'Einheiten')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '4', label: 'Athleten')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '2', label: 'Offen')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('Planbausteine'),
                  SizedBox(height: 12),
                  _PlanBlock(title: 'Warm-up', body: '10 Minuten Mobilitaet, Lauf-ABC, Aktivierung', icon: Icons.self_improvement_outlined),
                  SizedBox(height: 10),
                  _PlanBlock(title: 'Hauptteil', body: '4 x 8 Minuten Belastung, 3 Minuten Pause, Zone kontrollieren', icon: Icons.speed_outlined),
                  SizedBox(height: 10),
                  _PlanBlock(title: 'Cooldown', body: 'Auslaufen, Dehnen, kurze Selbstbewertung im Log', icon: Icons.spa_outlined),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Zuweisung & Kontrolle'),
                  const SizedBox(height: 8),
                  SwitchListTile(
                    value: _publishToAthletes,
                    onChanged: (value) => setState(() => _publishToAthletes = value),
                    activeThumbColor: AirmiusColors.blue,
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Sichtbar für Athleten', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    subtitle: const Text('Plan erscheint in App, Kalender und Wochenübersicht.', style: TextStyle(color: AirmiusColors.muted)),
                  ),
                  SwitchListTile(
                    value: _requireLog,
                    onChanged: (value) => setState(() => _requireLog = value),
                    activeThumbColor: AirmiusColors.blue,
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Log nach Einheit verlangen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    subtitle: const Text('Athleten werden nach Abschluss an die Rückmeldung erinnert.', style: TextStyle(color: AirmiusColors.muted)),
                  ),
                  const SizedBox(height: 8),
                  Wrap(spacing: 8, runSpacing: 8, children: const [StatusPill('Team U18'), StatusPill('Coach sichtbar'), StatusPill('PDF-Regel verknuepft')]),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.blue.withValues(alpha: 0.45),
              child: const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Eyebrow('Dokumente & Regeln'),
                  SizedBox(height: 8),
                  Text('Verknuepfte Dateien aus dem Vereins-Dateimanager: Datenschutz, Trainingsordnung und Einverstaendnis für Minderjaehrige.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusButton(
              label: 'Einheit als Log erfassen',
              icon: Icons.assignment_turned_in_outlined,
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => TrainingLogDetailScreen(title: widget.title, body: widget.body, status: 'Log', icon: widget.icon),
                ),
              ),
            ),
          ],
        ),
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

class _PlanBlock extends StatelessWidget {
  const _PlanBlock({required this.title, required this.body, required this.icon});

  final String title;
  final String body;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AirmiusColors.blue),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
