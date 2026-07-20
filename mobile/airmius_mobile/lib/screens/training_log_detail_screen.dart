import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class TrainingLogDetailScreen extends StatefulWidget {
  const TrainingLogDetailScreen({
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
  State<TrainingLogDetailScreen> createState() => _TrainingLogDetailScreenState();
}

class _TrainingLogDetailScreenState extends State<TrainingLogDetailScreen> {
  double _rpe = 6;
  String _visibility = 'Trainer';
  bool _requestFeedback = true;
  bool _shareWithTeam = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Trainingslog', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: widget.title,
        subtitle: 'Einheit erfassen, bewerten und Feedback einholen',
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
                            const Eyebrow('Trainingseinheit'),
                            const SizedBox(height: 4),
                            Text(widget.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                          ],
                        ),
                      ),
                      StatusPill(widget.status),
                    ],
                  ),
                  const SizedBox(height: 16),
                  const Text('Belastung', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  Slider(
                    value: _rpe,
                    min: 1,
                    max: 10,
                    divisions: 9,
                    label: 'RPE ${_rpe.round()}',
                    activeColor: AirmiusColors.blue,
                    inactiveColor: AirmiusColors.border,
                    onChanged: (value) => setState(() => _rpe = value),
                  ),
                  Text('RPE ${_rpe.round()} von 10', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '42m', label: 'Dauer')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '6.2', label: 'Kilometer')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '86%', label: 'Planfit')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('Leistungsdaten'),
                  SizedBox(height: 12),
                  AirmiusTextField(label: 'Dauer', hint: 'z. B. 45 Minuten', icon: Icons.timer_outlined),
                  SizedBox(height: 10),
                  AirmiusTextField(label: 'Distanz', hint: 'z. B. 6.2 km', icon: Icons.route_outlined),
                  SizedBox(height: 10),
                  AirmiusTextField(label: 'Puls / Zone', hint: 'z. B. Zone 2, 145 bpm', icon: Icons.monitor_heart_outlined),
                  SizedBox(height: 10),
                  AirmiusTextField(label: 'Notizen', hint: 'Wie hat sich die Einheit angefuehlt?', icon: Icons.notes_outlined, maxLines: 4),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Feedback & Sichtbarkeit'),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final option in const ['Privat', 'Trainer', 'Team'])
                        ChoiceChip(
                          selected: _visibility == option,
                          label: Text(option),
                          onSelected: (_) => setState(() => _visibility = option),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _visibility == option ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _visibility == option ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  SwitchListTile(
                    value: _requestFeedback,
                    onChanged: (value) => setState(() => _requestFeedback = value),
                    activeThumbColor: AirmiusColors.blue,
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Trainerfeedback anfordern', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    subtitle: const Text('Trainer erhaelt eine Aufgabe im Cockpit.', style: TextStyle(color: AirmiusColors.muted)),
                  ),
                  SwitchListTile(
                    value: _shareWithTeam,
                    onChanged: (value) => setState(() => _shareWithTeam = value),
                    activeThumbColor: AirmiusColors.blue,
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Kurzupdate im Teamfeed teilen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    subtitle: const Text('Nur Zusammenfassung, keine sensiblen Daten.', style: TextStyle(color: AirmiusColors.muted)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: 0.45),
              child: const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Eyebrow('Trainerfeedback'),
                  SizedBox(height: 8),
                  Text('Letzter Kommentar: Gute Grundlage, naechste Einheit etwas ruhiger starten und Pulsbereich stabil halten.', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800, height: 1.35)),
                  SizedBox(height: 10),
                  StatusPill('Antwort offen'),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusButton(label: 'Log speichern', icon: Icons.check_circle_outline, onPressed: () => openUiAction(context, title: 'Log speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.check_circle_outline)),
          ],
        ),
      ),
    );
  }
}
