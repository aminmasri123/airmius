import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'route_detail_screen.dart';
import 'wellbeing_operations_screen.dart';

class SportMapCenterScreen extends StatefulWidget {
  const SportMapCenterScreen({super.key});

  @override
  State<SportMapCenterScreen> createState() => _SportMapCenterScreenState();
}

class _SportMapCenterScreenState extends State<SportMapCenterScreen> {
  String _layer = 'Routen';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Sportkarte', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Sportkarte',
        subtitle: 'Routen, Tracks, Orte und Vorschlaege',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Container(
                    height: 220,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: AirmiusColors.borderStrong),
                      gradient: LinearGradient(colors: [AirmiusColors.blueDeep.withValues(alpha: 0.55), AirmiusColors.green.withValues(alpha: 0.20), AirmiusColors.cardSoft]),
                    ),
                    child: Stack(
                      children: const [
                        Positioned(left: 28, top: 36, child: Icon(Icons.place, color: AirmiusColors.green, size: 34)),
                        Positioned(right: 38, top: 74, child: Icon(Icons.route_outlined, color: AirmiusColors.blue, size: 42)),
                        Positioned(left: 86, bottom: 38, child: Icon(Icons.gps_fixed, color: AirmiusColors.text, size: 34)),
                        Center(child: Text('Karten-Vorschau', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 18))),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final layer in const ['Routen', 'Tracks', 'Orte'])
                        ChoiceChip(
                          selected: _layer == layer,
                          label: Text(layer),
                          onSelected: (_) => setState(() => _layer = layer),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _layer == layer ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _layer == layer ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '8', label: 'Routen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Tracks')), SizedBox(width: 10), Expanded(child: MetricCard(value: '6', label: 'Orte'))]),
            const SizedBox(height: 14),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Route planen', icon: Icons.add_location_alt_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RouteDetailScreen(title: 'Neue Route planen', body: 'Start, Wegpunkte, Ziel, Sichtbarkeit und Teamfreigabe festlegen.', status: 'Neu', icon: Icons.add_location_alt_outlined)))),
              AirmiusButton(label: 'Live Track starten', icon: Icons.gps_fixed, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RouteDetailScreen(title: 'Live Track', body: 'GPS-Rechte pruefen, Team teilen und Track sicher starten.', status: 'Live', icon: Icons.gps_fixed, mode: 'live')))),
              AirmiusButton(label: 'Map Operations', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WellbeingOperationsScreen()))),
            ]),
            const SizedBox(height: 14),
            for (final item in _itemsForLayer()) ...[
              _MapItemCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }

  List<_MapItem> _itemsForLayer() {
    if (_layer == 'Tracks') return _tracks;
    if (_layer == 'Orte') return _places;
    return _routes;
  }
}

class _MapItemCard extends StatelessWidget {
  const _MapItemCard({required this.item});

  final _MapItem item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RouteDetailScreen(title: item.title, body: item.body, status: item.status, icon: item.icon, mode: item.status == 'Neu' ? 'live' : 'route'))),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(item.icon, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)), const SizedBox(height: 8), StatusPill(item.status)])),
          const Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ],
      ),
    );
  }
}

class _MapItem {
  const _MapItem({required this.title, required this.body, required this.status, required this.icon});

  final String title;
  final String body;
  final String status;
  final IconData icon;
}

const _routes = [
  _MapItem(title: 'Saar Runde', body: '8.4 km - flach - oeffentlich', status: 'Route', icon: Icons.route_outlined),
  _MapItem(title: 'Wald Intervall', body: '5.2 km - Trail - Team intern', status: 'Team', icon: Icons.forest_outlined),
];

const _tracks = [
  _MapItem(title: 'Track Montag', body: '6.2 km - 38 min - abgeschlossen', status: 'Track', icon: Icons.timeline_outlined),
  _MapItem(title: 'Live Track', body: 'Bereit zum Starten und Punkte speichern', status: 'Neu', icon: Icons.gps_fixed),
];

const _places = [
  _MapItem(title: 'Sportplatz Kleinblittersdorf', body: 'Trainingsort - Verein', status: 'Ort', icon: Icons.place_outlined),
  _MapItem(title: 'Saarbruecken Treffpunkt', body: 'Lauftreff - oeffentlich', status: 'Public', icon: Icons.location_city_outlined),
];
