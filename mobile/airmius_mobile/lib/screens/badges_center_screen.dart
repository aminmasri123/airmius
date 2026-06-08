import 'package:flutter/material.dart';
import 'gamification_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'badge_detail_screen.dart';
import 'gamification_operations_screen.dart';

class BadgesCenterScreen extends StatefulWidget {
  const BadgesCenterScreen({super.key});

  @override
  State<BadgesCenterScreen> createState() => _BadgesCenterScreenState();
}

class _BadgesCenterScreenState extends State<BadgesCenterScreen> {
  String _filter = 'Alle';

  @override
  Widget build(BuildContext context) {
    final badges = _badges.where((badge) => _filter == 'Alle' || badge.status == _filter).toList();
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFFB88320), foregroundColor: Colors.white, icon: const Icon(Icons.workspace_premium_outlined), label: const Text('Badge Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => GamificationOperationsScreen(initialTab: 'Badges')))),
        
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Badges', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Badges',
        subtitle: 'Gamification, Fortschritt, Regeln und Auszeichnungen',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('Motivation'),
                  SizedBox(height: 8),
                  Text('Aktivitaet, Vereinsbeitritt, Training, Community und Lernen zahlen auf Badges ein.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  SizedBox(height: 14),
                  _BadgeProgress(title: 'Naechstes Badge: Vereinsstarter', value: 0.82, label: '82% erreicht'),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '9', label: 'Badges')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Neu')), SizedBox(width: 10), Expanded(child: MetricCard(value: '82%', label: 'Naechstes'))]),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final filter in const ['Alle', 'Erhalten', 'Offen'])
                  ChoiceChip(
                    selected: _filter == filter,
                    label: Text(filter),
                    onSelected: (_) => setState(() => _filter = filter),
                    selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                    backgroundColor: AirmiusColors.cardSoft,
                    side: BorderSide(color: _filter == filter ? AirmiusColors.blue : AirmiusColors.border),
                    labelStyle: TextStyle(color: _filter == filter ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            LayoutBuilder(
              builder: (context, constraints) {
                final columns = constraints.maxWidth > 620 ? 3 : 2;
                return GridView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: badges.length,
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: columns, crossAxisSpacing: 12, mainAxisSpacing: 12, childAspectRatio: 0.95),
                  itemBuilder: (context, index) => _BadgeCard(badge: badges[index]),
                );
              },
            ),
            const SizedBox(height: 14),
            const AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow('Regeln'),
                  SizedBox(height: 10),
                  _RuleLine(title: 'Vereinsstarter', body: 'Sende deinen ersten Mitgliedsantrag.'),
                  _RuleLine(title: 'Teamplayer', body: 'Nimm an einem Team-Event teil.'),
                  _RuleLine(title: 'Lernprofi', body: 'Schliesse einen Kurs mit Zertifikat ab.'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _BadgeCard extends StatelessWidget {
  const _BadgeCard({required this.badge});

  final _Badge badge;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BadgeDetailScreen(title: badge.title, body: badge.body, status: badge.status))),
      borderColor: badge.status == 'Erhalten' ? AirmiusColors.amber.withValues(alpha: 0.55) : AirmiusColors.border,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.workspace_premium_outlined, color: badge.status == 'Erhalten' ? AirmiusColors.amber : AirmiusColors.blue, size: 34),
          const Spacer(),
          Text(badge.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 6),
          Text(badge.body, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.25)),
          const SizedBox(height: 9),
          StatusPill(badge.status, color: badge.status == 'Erhalten' ? AirmiusColors.amber : AirmiusColors.blue),
        ],
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
      Row(children: [Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))), Text(label, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12))]),
      const SizedBox(height: 7),
      ClipRRect(borderRadius: BorderRadius.circular(99), child: LinearProgressIndicator(value: value, minHeight: 9, backgroundColor: AirmiusColors.cardSoft, valueColor: const AlwaysStoppedAnimation<Color>(AirmiusColors.amber))),
    ]);
  }
}

class _RuleLine extends StatelessWidget {
  const _RuleLine({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Icon(Icons.rule_outlined, color: AirmiusColors.blue),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3))])),
      ]),
    );
  }
}

class _Badge {
  const _Badge({required this.title, required this.body, required this.status});

  final String title;
  final String body;
  final String status;
}

const _badges = [
  _Badge(title: 'Starter', body: 'Profil angelegt und Sprache gesetzt.', status: 'Erhalten'),
  _Badge(title: 'Vereinsstarter', body: 'Ersten Mitgliedsantrag senden.', status: 'Offen'),
  _Badge(title: 'Teamplayer', body: 'An einem Team-Event teilnehmen.', status: 'Offen'),
  _Badge(title: 'Lernprofi', body: 'Kurs mit Zertifikat abschliessen.', status: 'Erhalten'),
  _Badge(title: 'Community', body: 'Freundschaftsanfrage annehmen.', status: 'Offen'),
  _Badge(title: 'Sportkarte', body: 'Route speichern oder Track abschliessen.', status: 'Offen'),
];

