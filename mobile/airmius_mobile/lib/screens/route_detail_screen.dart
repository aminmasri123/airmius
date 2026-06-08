import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class RouteDetailScreen extends StatefulWidget {
  const RouteDetailScreen({super.key, required this.title, required this.body, required this.status, required this.icon, this.mode = 'route'});

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String mode;

  @override
  State<RouteDetailScreen> createState() => _RouteDetailScreenState();
}

class _RouteDetailScreenState extends State<RouteDetailScreen> {
  bool _shareWithTeam = true;
  String _permission = 'Team intern';

  @override
  Widget build(BuildContext context) {
    final isLive = widget.mode == 'live';
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: isLive ? 'Live Track, GPS-Rechte, Sicherheit und Abschluss' : 'Route, Karte, Wegpunkte, Sichtbarkeit und Training',
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Container(height: 230, decoration: BoxDecoration(borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.borderStrong), gradient: LinearGradient(colors: [AirmiusColors.blueDeep.withValues(alpha: 0.55), AirmiusColors.green.withValues(alpha: 0.20), AirmiusColors.cardSoft])), child: Stack(children: [
              const Positioned(left: 28, top: 36, child: Icon(Icons.place, color: AirmiusColors.green, size: 34)),
              const Positioned(right: 38, top: 74, child: Icon(Icons.route_outlined, color: AirmiusColors.blue, size: 42)),
              const Positioned(left: 86, bottom: 38, child: Icon(Icons.gps_fixed, color: AirmiusColors.text, size: 34)),
              Center(child: Icon(widget.icon, color: AirmiusColors.text, size: 58)),
            ])),
            const SizedBox(height: 12),
            Text(widget.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(widget.status), StatusPill(isLive ? 'Live' : 'Route'), const StatusPill('GPS bereit', color: AirmiusColors.green)]),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '8.4', label: 'km')), SizedBox(width: 10), Expanded(child: MetricCard(value: '42', label: 'hm')), SizedBox(width: 10), Expanded(child: MetricCard(value: '48m', label: 'Ziel'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Sichtbarkeit & Sicherheit'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(value: _permission, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Sichtbarkeit'), items: const ['Privat', 'Team intern', 'Verein', 'Oeffentlich'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: (value) => setState(() => _permission = value ?? _permission)),
            SwitchListTile(value: _shareWithTeam, onChanged: (value) => setState(() => _shareWithTeam = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Mit Team teilen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Route, Track oder Live-Standort fuer Team sichtbar machen.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Route & Wegpunkte'),
            SizedBox(height: 10),
            _RouteLine(icon: Icons.flag_outlined, title: 'Start', body: 'Sportplatz Kleinblittersdorf', status: 'Start'),
            _RouteLine(icon: Icons.route_outlined, title: 'Wegpunkt', body: 'Saarpromenade - flacher Abschnitt', status: 'km 4'),
            _RouteLine(icon: Icons.place_outlined, title: 'Ziel', body: 'Rueckkehr zum Sportplatz', status: 'Ziel'),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: isLive ? 'Live Track starten' : 'Route starten', icon: Icons.play_arrow_outlined, onPressed: () => openUiAction(context, title: isLive ? 'Live Track starten' : 'Route starten', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.play_arrow_outlined)),
            AirmiusButton(label: 'Route bearbeiten', icon: Icons.edit_location_alt_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Route bearbeiten', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.edit_location_alt_outlined)),
          ]),
        ]),
      ),
    );
  }
}

class _RouteLine extends StatelessWidget {
  const _RouteLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(padding: const EdgeInsets.only(top: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])), StatusPill(status)]));
  }
}
