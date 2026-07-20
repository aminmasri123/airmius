import 'package:flutter/material.dart';
import 'gamification_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'gamification_rule_detail_screen.dart';

class GamificationRulesScreen extends StatefulWidget {
  const GamificationRulesScreen({super.key});

  @override
  State<GamificationRulesScreen> createState() => _GamificationRulesScreenState();
}

class _GamificationRulesScreenState extends State<GamificationRulesScreen> {
  String _scope = 'Badges';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFFB88320), foregroundColor: Colors.white, icon: const Icon(Icons.workspace_premium_outlined), label: const Text('Rule Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => GamificationOperationsScreen(initialTab: 'Regeln')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Gamification-Regeln', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Gamification-Regeln',
        subtitle: 'XP, Badges, Level, Achievements, Leaderboard und Regelprüfung',
        trailing: const StatusPill('Rules'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Regelwerk'),
            const SizedBox(height: 8),
            const Text('Motivation steuerbar machen.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('Regeln für XP, Badges, Streaks, Vereinsaktivitaet und Level werden mobil sichtbar und später per API gespeichert.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: ['Badges', 'XP', 'Streaks', 'Leaderboard'].map((item) => ChoiceChip(
              selected: _scope == item,
              label: Text(item),
              onSelected: (_) => setState(() => _scope = item),
              selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
              backgroundColor: AirmiusColors.cardSoft,
              side: BorderSide(color: _scope == item ? AirmiusColors.blue : AirmiusColors.border),
              labelStyle: TextStyle(color: _scope == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
            )).toList()),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '9', label: 'Badges')), SizedBox(width: 10), Expanded(child: MetricCard(value: '420', label: 'XP')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Regeln'))]),
          const SizedBox(height: 14),
          _RuleLine(icon: Icons.workspace_premium_outlined, title: 'Vereinsstarter', body: 'Badge nach erster angenommenen Vereinsmitgliedschaft vergeben.', status: 'Aktiv', color: AirmiusColors.green, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GamificationRuleDetailScreen(title: 'Vereinsstarter', status: 'Aktiv')))),
          const SizedBox(height: 12),
          _RuleLine(icon: Icons.local_fire_department_outlined, title: 'Trainings-Streak', body: 'XP für dokumentierte Trainingstage in Folge.', status: 'XP', color: AirmiusColors.amber, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GamificationRuleDetailScreen(title: 'Trainings-Streak', status: 'XP')))),
          const SizedBox(height: 12),
          _RuleLine(icon: Icons.leaderboard_outlined, title: 'Leaderboard Datenschutz', body: 'Anzeige nur mit Profil-Sichtbarkeit und Opt-in.', status: 'Sicher', color: AirmiusColors.blue, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GamificationRuleDetailScreen(title: 'Leaderboard Datenschutz', status: 'Sicher')))),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Regel-Aktionen'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Regel erstellen', icon: Icons.add_circle_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GamificationRuleDetailScreen(title: 'Regel erstellen', status: 'Entwurf')))),
              AirmiusButton(label: 'Historie ansehen', icon: Icons.history_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GamificationRuleDetailScreen(title: 'Regelhistorie', status: 'Audit')))),
              AirmiusButton(label: 'Leaderboard', icon: Icons.leaderboard_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GamificationRuleDetailScreen(title: 'Leaderboard', status: 'Opt-in')))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _RuleLine extends StatelessWidget {
  const _RuleLine({required this.icon, required this.title, required this.body, required this.status, required this.color, required this.onTap});

  final IconData icon;
  final String title;
  final String body;
  final String status;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(onTap: onTap, borderColor: color.withValues(alpha: 0.45), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(icon, color: color, size: 28),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)), const SizedBox(height: 10), StatusPill(status, color: color)])),
      const Icon(Icons.chevron_right, color: AirmiusColors.muted),
    ]));
  }
}

