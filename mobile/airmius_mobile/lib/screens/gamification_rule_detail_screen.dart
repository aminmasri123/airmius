import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class GamificationRuleDetailScreen extends StatefulWidget {
  const GamificationRuleDetailScreen({super.key, required this.title, required this.status});

  final String title;
  final String status;

  @override
  State<GamificationRuleDetailScreen> createState() => _GamificationRuleDetailScreenState();
}

class _GamificationRuleDetailScreenState extends State<GamificationRuleDetailScreen> {
  String _trigger = 'Mitgliedschaft angenommen';
  String _reward = 'Badge + XP';
  bool _active = true;
  bool _leaderboardAllowed = false;
  bool _audit = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Regel', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: 'Trigger, XP, Badges, Datenschutz und Audit',
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
            Eyebrow('Regelprofil'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Regelname', hint: 'Vereinsstarter, Trainings-Streak, Lernprofi', icon: Icons.rule_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Beschreibung', hint: 'Wann wird die Regel ausgeloest?', icon: Icons.notes_outlined, maxLines: 3),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '420', label: 'XP')), SizedBox(width: 10), Expanded(child: MetricCard(value: '9', label: 'Badges')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Rules'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Trigger & Belohnung'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: _trigger,
              dropdownColor: AirmiusColors.card,
              decoration: _fieldDecoration('Trigger'),
              items: const ['Mitgliedschaft angenommen', 'Training geloggt', 'Kurs abgeschlossen', 'Event teilgenommen'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _trigger = value ?? _trigger),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              value: _reward,
              dropdownColor: AirmiusColors.card,
              decoration: _fieldDecoration('Belohnung'),
              items: const ['Badge + XP', 'Nur XP', 'Levelpunkt', 'Streak'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _reward = value ?? _reward),
            ),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Datenschutz & Steuerung'),
            const SizedBox(height: 8),
            SwitchListTile(value: _active, onChanged: (value) => setState(() => _active = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Regel aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Regel wird bei passenden Events ausgewertet.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _leaderboardAllowed, onChanged: (value) => setState(() => _leaderboardAllowed = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Leaderboard erlauben', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Nur mit Opt-in und sichtbarem Profil auswerten.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _audit, onChanged: (value) => setState(() => _audit = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Regeländerungen auditieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Änderungen werden im Admin-Audit erfasst.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.amber.withValues(alpha: 0.55), child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Eyebrow('Regelhistorie'),
            SizedBox(height: 8),
            Text('Regel v1 erstellt, XP-Wert angepasst, Leaderboard-Gate aktiviert. Historie und Event-Auswertung kommen später aus Laravel.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('v1'), StatusPill('Audit'), StatusPill('Opt-in')]),
          ])),
          const SizedBox(height: 14),
          AirmiusButton(label: 'Regel speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Regel speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.save_outlined)),
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
