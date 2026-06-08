import 'package:flutter/material.dart';
import 'sports_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'sport_profile_detail_screen.dart';
import 'sports_operations_screen.dart';

class SportsCenterScreen extends StatefulWidget {
  const SportsCenterScreen({super.key});

  @override
  State<SportsCenterScreen> createState() => _SportsCenterScreenState();
}

class _SportsCenterScreenState extends State<SportsCenterScreen> {
  String _sport = 'Laufen';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.sports_outlined), label: const Text('Sport Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => SportsOperationsScreen(initialTab: 'Profil')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Sportarten', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Sportarten',
        subtitle: 'Sportprofile, Disziplinen, Leistungsdaten, Ziele und KI-Plan-Voraussetzungen',
        trailing: const StatusPill('Profil'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Sportprofil'),
            const SizedBox(height: 8),
            const Text('Deine Sportdaten fuer Training und KI-Coach.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('Disziplinen, Erfahrung, Wochenstunden, Ziele, Leistungswerte und fehlende Daten werden so vorbereitet, dass Laravel spaeter die echten Profile liefert.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: ['Laufen', 'Kraft', 'Tennis', 'Fussball', 'Allgemein'].map((item) => ChoiceChip(
              selected: _sport == item,
              label: Text(item),
              onSelected: (_) => setState(() => _sport = item),
              selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
              backgroundColor: AirmiusColors.cardSoft,
              side: BorderSide(color: _sport == item ? AirmiusColors.blue : AirmiusColors.border),
              labelStyle: TextStyle(color: _sport == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
            )).toList()),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '5', label: 'Sportarten')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Profile')), SizedBox(width: 10), Expanded(child: MetricCard(value: '82%', label: 'Bereit'))]),
          const SizedBox(height: 14),
          _SportsLine(
            icon: Icons.directions_run_outlined,
            title: 'Laufen',
            body: 'Pace, Distanz, Pulsbereiche, Wochenziel und Leistungsstand.',
            status: 'Aktiv',
            color: AirmiusColors.green,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SportProfileDetailScreen(title: 'Laufen', status: 'Aktiv'))),
          ),
          const SizedBox(height: 12),
          _SportsLine(
            icon: Icons.fitness_center_outlined,
            title: 'Krafttraining',
            body: 'Uebungen, Volumen, Belastung, Regeneration und Ziele.',
            status: 'Profil',
            color: AirmiusColors.blue,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SportProfileDetailScreen(title: 'Krafttraining', status: 'Profil'))),
          ),
          const SizedBox(height: 12),
          _SportsLine(
            icon: Icons.auto_awesome_outlined,
            title: 'KI-Plan Bereitschaft',
            body: 'Fehlende Leistungsdaten werden vor KI-Trainingsplaenen sichtbar gemacht.',
            status: '82%',
            color: AirmiusColors.amber,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SportProfileDetailScreen(title: 'KI-Plan Bereitschaft', status: '82%'))),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Sport-Aktionen'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Profil bearbeiten', icon: Icons.edit_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SportProfileDetailScreen(title: 'Sportprofil bearbeiten', status: 'Profil')))),
              AirmiusButton(label: 'Ziel setzen', icon: Icons.flag_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SportProfileDetailScreen(title: 'Ziel setzen', status: 'Ziel')))),
              AirmiusButton(label: 'Leistungsdaten', icon: Icons.monitor_heart_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SportProfileDetailScreen(title: 'Leistungsdaten', status: 'Daten')))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _SportsLine extends StatelessWidget {
  const _SportsLine({required this.icon, required this.title, required this.body, required this.status, required this.color, required this.onTap});

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

