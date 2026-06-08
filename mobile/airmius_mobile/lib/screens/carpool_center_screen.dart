import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'carpool_detail_screen.dart';
import 'safety_community_operations_screen.dart';

class CarpoolCenterScreen extends StatefulWidget {
  const CarpoolCenterScreen({super.key});

  @override
  State<CarpoolCenterScreen> createState() => _CarpoolCenterScreenState();
}

class _CarpoolCenterScreenState extends State<CarpoolCenterScreen> {
  String _mode = 'Alle';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.security_outlined), label: const Text('Fahrten Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => SafetyCommunityOperationsScreen(initialTab: 1)))),
        
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Fahrgemeinschaften', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Fahrgemeinschaften',
        subtitle: 'Mitfahrten, Treffpunkte, Routen und Sicherheit fuer Verein und Training',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Heute'),
                  const SizedBox(height: 8),
                  const Text('3 passende Mitfahrten zum Training gefunden.', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 8),
                  const Text('Mitglieder koennen freie Plaetze anbieten, Treffpunkte abstimmen und sichere Fahrten fuer Minderjaehrige nur mit Freigabe anzeigen.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(label: 'Fahrt anbieten', icon: Icons.add_road_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CarpoolDetailScreen(title: 'Fahrt anbieten', status: 'Angebot')))),
                      AirmiusButton(label: 'Mitfahrt suchen', icon: Icons.search_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CarpoolDetailScreen(title: 'Mitfahrt suchen', status: 'Gesuch')))),
                      AirmiusButton(label: 'Safety Ops', icon: Icons.health_and_safety_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SafetyCommunityOperationsScreen()))),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '3', label: 'Fahrten')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '8', label: 'Plaetze')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '2', label: 'Treffpunkte')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: ['Alle', 'Angebote', 'Gesuche', 'Meine'].map((item) {
                  return ChoiceChip(
                    selected: _mode == item,
                    label: Text(item),
                    onSelected: (_) => setState(() => _mode = item),
                    selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                    backgroundColor: AirmiusColors.cardSoft,
                    side: BorderSide(color: _mode == item ? AirmiusColors.blue : AirmiusColors.border),
                    labelStyle: TextStyle(color: _mode == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                  );
                }).toList(),
              ),
            ),
            const SizedBox(height: 14),
            _RideCard(title: 'Zum Intervalltraining', route: 'Kleinblittersdorf -> Sportplatz', time: 'Heute 18:00', seats: '2 Plaetze', status: 'Angebot', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CarpoolDetailScreen(title: 'Zum Intervalltraining', status: 'Angebot')))),
            const SizedBox(height: 12),
            _RideCard(title: 'Suche Mitfahrt', route: 'Saarbruecken Hbf -> ZBB', time: 'Morgen 17:30', seats: '1 Person', status: 'Gesuch', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CarpoolDetailScreen(title: 'Suche Mitfahrt', status: 'Gesuch')))),
            const SizedBox(height: 12),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Treffpunkt & Sicherheit'),
                  const SizedBox(height: 10),
                  const _SafetyLine(icon: Icons.location_on_outlined, title: 'Treffpunkt teilen', body: 'Nur Teilnehmer sehen Adresse, Uhrzeit und Kontakt.'),
                  const _SafetyLine(icon: Icons.verified_user_outlined, title: 'Jugendschutz', body: 'Fahrten fuer Minderjaehrige koennen Guardian-Freigabe verlangen.'),
                  const _SafetyLine(icon: Icons.report_outlined, title: 'Melden & Blockieren', body: 'Unsichere Fahrten oder Profile koennen direkt gemeldet werden.'),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'Regeln ansehen', icon: Icons.rule_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CarpoolDetailScreen(title: 'Fahrgemeinschaftsregeln', status: 'Regeln')))),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RideCard extends StatelessWidget {
  const _RideCard({required this.title, required this.route, required this.time, required this.seats, required this.status, required this.onTap});

  final String title;
  final String route;
  final String time;
  final String seats;
  final String status;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: onTap,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.directions_car_filled_outlined, color: AirmiusColors.blue, size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(route, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
                const SizedBox(height: 10),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(time), StatusPill(seats, color: AirmiusColors.green)]),
              ],
            ),
          ),
          StatusPill(status, color: status == 'Angebot' ? AirmiusColors.blue : AirmiusColors.amber),
        ],
      ),
    );
  }
}

class _SafetyLine extends StatelessWidget {
  const _SafetyLine({required this.icon, required this.title, required this.body});

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])),
        ],
      ),
    );
  }
}

