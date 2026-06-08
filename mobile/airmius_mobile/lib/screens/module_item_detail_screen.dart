import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class ModuleItemDetailScreen extends StatefulWidget {
  const ModuleItemDetailScreen({super.key, required this.title, required this.body, required this.trailing, required this.icon});

  final String title;
  final String body;
  final String trailing;
  final IconData icon;

  @override
  State<ModuleItemDetailScreen> createState() => _ModuleItemDetailScreenState();
}

class _ModuleItemDetailScreenState extends State<ModuleItemDetailScreen> {
  bool _visible = true;
  bool _notify = false;
  bool _requiresReview = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Moduldetail', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.body,
        trailing: StatusPill(widget.trailing),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(widget.icon, color: AirmiusColors.blue, size: 34),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Eyebrow('Modulbaustein'),
              const SizedBox(height: 5),
              Text(widget.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
            ])),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: 'UI', label: 'Status')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'API', label: 'Spaeter')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'OK', label: 'Mobile'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Mobile Modulsteuerung'),
            const SizedBox(height: 8),
            SwitchListTile(value: _visible, onChanged: (value) => setState(() => _visible = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Im Modul sichtbar', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Karte bleibt im mobilen Modulkontext sichtbar.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _notify, onChanged: (value) => setState(() => _notify = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Benachrichtigung aktivieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Push/Inbox-Regeln werden spaeter an API gekoppelt.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _requiresReview, onChanged: (value) => setState(() => _requiresReview = value), activeColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Review erforderlich', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Sensible Modulaktionen koennen Freigabe verlangen.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.blue.withValues(alpha: 0.45), child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Eyebrow('API-Kontext'),
            SizedBox(height: 8),
            Text('Dieser Detailtyp zeigt Modulzeilen als nativen Mobile-Flow. Spaeter kann jede Karte mit passender Laravel-Route, Berechtigung und Datensatz-ID verbunden werden.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          ])),
          const SizedBox(height: 14),
          AirmiusButton(label: 'Moduldetail speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Moduldetail speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.save_outlined)),
        ]),
      ),
    );
  }
}
