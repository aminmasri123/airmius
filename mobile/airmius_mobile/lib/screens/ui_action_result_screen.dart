import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class UiActionResultScreen extends StatefulWidget {
  const UiActionResultScreen({
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
  State<UiActionResultScreen> createState() => _UiActionResultScreenState();
}

class _UiActionResultScreenState extends State<UiActionResultScreen> {
  bool _notify = true;
  bool _saveAsDraft = true;
  bool _requiresApi = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.body,
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(widget.icon, color: AirmiusColors.blue, size: 36),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Eyebrow('UI-Aktion'),
              const SizedBox(height: 6),
              Text(widget.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              const SizedBox(height: 12),
              Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(widget.status), const StatusPill('UI fertig'), const StatusPill('API später')]),
            ])),
          ])),
          const SizedBox(height: 14),
          Row(children: const [
            Expanded(child: MetricCard(value: 'UI', label: 'Status')),
            SizedBox(width: 10),
            Expanded(child: MetricCard(value: 'API', label: 'Backend')),
            SizedBox(width: 10),
            Expanded(child: MetricCard(value: 'OK', label: 'Mobile')),
          ]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Aktionsverhalten'),
            const SizedBox(height: 8),
            SwitchListTile(value: _saveAsDraft, onChanged: (value) => setState(() => _saveAsDraft = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Als Entwurf vorbereiten', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('UI-Zustand bleibt sichtbar, echte Persistenz kommt über Laravel.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _notify, onChanged: (value) => setState(() => _notify = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Benachrichtigung ausloesen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Push/Inbox-Regel wird später serverseitig verknuepft.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _requiresApi, onChanged: (value) => setState(() => _requiresApi = value), activeThumbColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('API-Mutation erforderlich', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Die App zeigt den Ziel-Flow, Laravel übernimmt später die Daten.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.green.withValues(alpha: 0.45), child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Eyebrow('Naechster Backend-Schritt'),
            SizedBox(height: 8),
            Text('Diese Aktion ist als native Mobile-UI vorhanden. Später wird hier Request, Loading, Error, Success und Optimistic Update über die Laravel-API angeschlossen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          ])),
          const SizedBox(height: 14),
          AirmiusButton(label: 'Aktion vormerken', icon: Icons.check_circle_outline, onPressed: () => Navigator.pop(context)),
        ]),
      ),
    );
  }
}
