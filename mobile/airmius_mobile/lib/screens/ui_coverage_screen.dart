import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../models/module_definition.dart';
import '../widgets/airmius_widgets.dart';
import 'operations_hub_screen.dart';

class UiCoverageScreen extends StatelessWidget {
  const UiCoverageScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('UI Coverage', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'UI Coverage',
        subtitle: 'Modulabdeckung, Operations-Center und spaetere API-Anbindung',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Flutter-Abdeckung'),
                  const SizedBox(height: 8),
                  const Text('Diese Ansicht fasst zusammen, welche Web-App-Module als native mobile UI vorbereitet sind. Backend kommt spaeter ueber Laravel API.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  Row(children: const [Expanded(child: MetricCard(value: '31', label: 'Module')), SizedBox(width: 10), Expanded(child: MetricCard(value: '25', label: 'Ops')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'DE/EN/FR/AR', label: 'Lang'))]),
                  const SizedBox(height: 14),
                  AirmiusButton(label: 'Operations Hub oeffnen', icon: Icons.hub_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => OperationsHubScreen()))),
                ],
              ),
            ),
            const SizedBox(height: 16),
            for (final module in appModules) ...[
              _CoverageLine(module: module),
              const SizedBox(height: 10),
            ],
          ],
        ),
      ),
    );
  }
}

class _CoverageLine extends StatelessWidget {
  const _CoverageLine({required this.module});

  final ModuleDefinition module;

  @override
  Widget build(BuildContext context) {
    final status = _status(module.title);
    return AirmiusPanel(
      borderColor: status.color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 46, height: 46, decoration: BoxDecoration(color: status.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(15), border: Border.all(color: status.color.withValues(alpha: .42))), child: Icon(module.icon, color: status.color)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(module.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                const SizedBox(height: 4),
                Text(module.subtitle, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 9),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(status.label, color: status.color), StatusPill(status.ops), StatusPill('API spaeter')]),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

_CoverageStatus _status(String title) {
  const opsReady = {
    'Arbeitsbereiche', 'Vereins-Cockpit', 'Vereine & Teams', 'Teams', 'Rollen & Rechte', 'Sportarten', 'Feed', 'Events', 'Events & Training', 'Trainer-Cockpit', 'Ernaehrung', 'Sportkarte', 'Freunde', 'Nachrichten', 'Fahrgemeinschaften', 'Dateien', 'Badges', 'Gamification-Regeln', 'Kurse', 'Marketplace', 'Commerce', 'Sponsoren', 'Medienrichtlinien', 'Blog & Medien', 'Nutzer', 'Abos & Rechnungen', 'Eltern & Jugendschutz', 'Altersfreigaben', 'Outfit-Abos', 'Einstellungen', 'Admin',
  };
  if (opsReady.contains(title)) return const _CoverageStatus('Native UI bereit', 'Ops verknuepft', AirmiusColors.green);
  return const _CoverageStatus('UI vorhanden', 'Review', AirmiusColors.amber);
}

class _CoverageStatus {
  const _CoverageStatus(this.label, this.ops, this.color);
  final String label;
  final String ops;
  final Color color;
}
