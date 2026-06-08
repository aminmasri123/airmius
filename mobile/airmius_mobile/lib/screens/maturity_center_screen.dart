import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'safety_community_operations_screen.dart';
import 'ui_action_result_screen.dart';

class MaturityCenterScreen extends StatefulWidget {
  const MaturityCenterScreen({super.key});

  @override
  State<MaturityCenterScreen> createState() => _MaturityCenterScreenState();
}

class _MaturityCenterScreenState extends State<MaturityCenterScreen> {
  String _group = 'Jugend';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.security_outlined), label: const Text('Maturity Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => SafetyCommunityOperationsScreen(initialTab: 3)))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Altersfreigaben', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Altersfreigaben',
        subtitle: 'Maturity, Altersgruppen, Content-Gates, Guardian-Freigaben und sichere Sichtbarkeit',
        trailing: const StatusPill('Schutz'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Maturity Center'),
            const SizedBox(height: 8),
            const Text('Sichtbarkeit nach Alter und Zustimmung steuern.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('Altersfreigaben verbinden Profile, Medien, Kurse, Events, Fahrgemeinschaften und Marketplace-Angebote mit Schutzregeln.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: ['Kinder', 'Jugend', 'Erwachsene', 'Public'].map((item) => ChoiceChip(
              selected: _group == item,
              label: Text(item),
              onSelected: (_) => setState(() => _group = item),
              selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
              backgroundColor: AirmiusColors.cardSoft,
              side: BorderSide(color: _group == item ? AirmiusColors.blue : AirmiusColors.border),
              labelStyle: TextStyle(color: _group == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
            )).toList()),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '3', label: 'Gates')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Offen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Consents'))]),
          const SizedBox(height: 14),
          const _MaturityLine(icon: Icons.family_restroom_outlined, title: 'Guardian-Freigabe', body: 'Elternzustimmung fuer Medien, Events und Fahrgemeinschaften pruefen.', status: 'Offen', color: AirmiusColors.amber),
          const SizedBox(height: 12),
          const _MaturityLine(icon: Icons.visibility_off_outlined, title: 'Content-Gate', body: 'Sensible Inhalte nur fuer erlaubte Altersgruppen anzeigen.', status: 'Aktiv', color: AirmiusColors.blue),
          const SizedBox(height: 12),
          const _MaturityLine(icon: Icons.storefront_outlined, title: 'Marketplace Altersregel', body: 'Produkte und Kurse koennen Altersfreigaben verlangen.', status: 'Regel', color: AirmiusColors.green),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Aktionen'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Gate erstellen', icon: Icons.lock_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Gate erstellen', body: 'Content-Gate, Altersgruppe, Sichtbarkeit und Guardian-Freigabe vorbereiten.', status: 'Gate', icon: Icons.lock_outlined)))),
              AirmiusButton(label: 'Consent pruefen', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Consent pruefen', body: 'Guardian Consent, Altersfreigabe und API-Pruefung vorbereiten.', status: 'Consent', icon: Icons.fact_check_outlined)))),
              AirmiusButton(label: 'Regeln bearbeiten', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Maturity-Regeln bearbeiten', body: 'Altersgruppen, Content-Typen und Schutzregeln konfigurieren.', status: 'Regeln', icon: Icons.tune_outlined)))),
              AirmiusButton(label: 'Safety Ops', icon: Icons.health_and_safety_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SafetyCommunityOperationsScreen()))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _MaturityLine extends StatelessWidget {
  const _MaturityLine({required this.icon, required this.title, required this.body, required this.status, required this.color});

  final IconData icon;
  final String title;
  final String body;
  final String status;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(borderColor: color.withValues(alpha: 0.45), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(icon, color: color, size: 28),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)), const SizedBox(height: 10), StatusPill(status, color: color)])),
    ]));
  }
}

