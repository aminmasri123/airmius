import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'profile_skill_recommendation_screen.dart';

class BadgeDetailScreen extends StatefulWidget {
  const BadgeDetailScreen({super.key, required this.title, required this.body, required this.status});

  final String title;
  final String body;
  final String status;

  @override
  State<BadgeDetailScreen> createState() => _BadgeDetailScreenState();
}

class _BadgeDetailScreenState extends State<BadgeDetailScreen> {
  bool _visibleOnProfile = true;
  bool _notifyWhenUnlocked = true;
  bool _leaderboardOptIn = false;

  @override
  Widget build(BuildContext context) {
    final unlocked = widget.status == 'Erhalten';

    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Badge', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.body,
        trailing: StatusPill(widget.status, color: unlocked ? AirmiusColors.amber : AirmiusColors.blue),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Icon(Icons.workspace_premium_outlined, color: unlocked ? AirmiusColors.amber : AirmiusColors.blue, size: 58),
            const SizedBox(height: 12),
            Text(widget.title, textAlign: TextAlign.center, style: const TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            Text(widget.body, textAlign: TextAlign.center, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 16),
            const _BadgeProgress(title: 'Fortschritt', value: 0.82, label: '82%'),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '820', label: 'XP')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Tasks')), SizedBox(width: 10), Expanded(child: MetricCard(value: '12', label: 'Rang'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
            Eyebrow('Regelanforderungen'),
            SizedBox(height: 12),
            _RequirementLine(title: 'Mitgliedsantrag senden', body: 'Sende eine gültige Anfrage an einen Verein.', done: true),
            SizedBox(height: 10),
            _RequirementLine(title: 'Profil vervollstaendigen', body: 'Personen-, Kontakt- und Sportdaten pflegen.', done: true),
            SizedBox(height: 10),
            _RequirementLine(title: 'Vereinsantwort erhalten', body: 'Anfrage muss bestätigt oder bearbeitet werden.', done: false),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Sichtbarkeit & Motivation'),
            const SizedBox(height: 8),
            SwitchListTile(value: _visibleOnProfile, onChanged: (value) => setState(() => _visibleOnProfile = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Im Profil anzeigen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Badge erscheint auf dem öffentlichen oder Vereinsprofil.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _notifyWhenUnlocked, onChanged: (value) => setState(() => _notifyWhenUnlocked = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Push bei Freischaltung', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Motivierende Benachrichtigung senden.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _leaderboardOptIn, onChanged: (value) => setState(() => _leaderboardOptIn = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Leaderboard Opt-in', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Ranking nur mit ausdrücklicher Freigabe.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.amber.withValues(alpha: 0.55), child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Eyebrow('Achievement-Historie'),
            SizedBox(height: 8),
            Text('Starter erhalten, Lernprofi erhalten, Vereinsstarter fast erreicht. Historie und XP kommen später aus der Gamification-API.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('XP aktiv'), StatusPill('Regel v1'), StatusPill('Profil OK')]),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Badge-Einstellungen speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Badge-Einstellungen speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.save_outlined)),
            AirmiusButton(label: 'Empfehlungen prüfen', icon: Icons.rate_review_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ProfileSkillRecommendationScreen(skill: widget.title, status: 'Offen')))),
          ]),
        ]),
      ),
    );
  }
}

class _BadgeProgress extends StatelessWidget {
  const _BadgeProgress({required this.title, required this.value, required this.label});

  final String title;
  final double value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Row(children: [Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))), Text(label, style: const TextStyle(color: AirmiusColors.amber, fontWeight: FontWeight.w900))]),
      const SizedBox(height: 8),
      ClipRRect(borderRadius: BorderRadius.circular(99), child: LinearProgressIndicator(value: value, minHeight: 9, backgroundColor: AirmiusColors.cardSoft, valueColor: const AlwaysStoppedAnimation<Color>(AirmiusColors.amber))),
    ]);
  }
}

class _RequirementLine extends StatelessWidget {
  const _RequirementLine({required this.title, required this.body, required this.done});

  final String title;
  final String body;
  final bool done;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: done ? AirmiusColors.green.withValues(alpha: 0.45) : AirmiusColors.border)),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(done ? Icons.check_circle_outline : Icons.radio_button_unchecked, color: done ? AirmiusColors.green : AirmiusColors.muted),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
        ])),
      ]),
    );
  }
}
