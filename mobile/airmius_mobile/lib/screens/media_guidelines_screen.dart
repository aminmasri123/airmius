import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class MediaGuidelinesScreen extends StatefulWidget {
  const MediaGuidelinesScreen({super.key});

  @override
  State<MediaGuidelinesScreen> createState() => _MediaGuidelinesScreenState();
}

class _MediaGuidelinesScreenState extends State<MediaGuidelinesScreen> {
  String _scope = 'Verein';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Medienrichtlinien', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Medienrichtlinien',
        subtitle: 'Bildrechte, Upload-Regeln, Freigaben, Guardian Consent und Moderation',
        trailing: const StatusPill('Pflicht'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Medien & Datenschutz'),
            const SizedBox(height: 8),
            const Text('Fotos und Videos sicher veröffentlichen.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('Diese UI bildet Regeln für Uploads, Minderjaehrige, Bildrechte, Sichtbarkeit und Freigaben ab.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: ['Verein', 'Team', 'Event', 'Public'].map((item) => ChoiceChip(
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
          const _GuidelineLine(icon: Icons.photo_library_outlined, title: 'Upload-Regeln', body: 'Dateitypen, Groessen, Rechtehinweis und sensible Inhalte vor Upload prüfen.', status: 'Aktiv', color: AirmiusColors.blue),
          const SizedBox(height: 12),
          const _GuidelineLine(icon: Icons.family_restroom_outlined, title: 'Guardian Consent', body: 'Medien mit Minderjaehrigen nur mit passender Zustimmung sichtbar machen.', status: 'Jugendschutz', color: AirmiusColors.amber),
          const SizedBox(height: 12),
          const _GuidelineLine(icon: Icons.visibility_outlined, title: 'Sichtbarkeit', body: 'Public, Verein, Team oder nur Admins für jedes Medium einstellen.', status: 'Regel', color: AirmiusColors.green),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Moderation'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Freigabe prüfen', icon: Icons.fact_check_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Medienfreigabe prüfen', body: 'Bildrechte, Guardian Consent, Sichtbarkeit und Upload-Regeln prüfen.', status: 'Review', icon: Icons.fact_check_outlined)))),
              AirmiusButton(label: 'Regel bearbeiten', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Medienregel bearbeiten', body: 'Dateitypen, Rechtehinweise, Sichtbarkeit und Moderation konfigurieren.', status: 'Regel', icon: Icons.tune_outlined)))),
              AirmiusButton(label: 'Meldung ansehen', icon: Icons.report_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Medienmeldung ansehen', body: 'Report, betroffene Datei, Moderatornotiz und Entscheidung vorbereiten.', status: 'Meldung', icon: Icons.report_outlined)))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _GuidelineLine extends StatelessWidget {
  const _GuidelineLine({required this.icon, required this.title, required this.body, required this.status, required this.color});

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
