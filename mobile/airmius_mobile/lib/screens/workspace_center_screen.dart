import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'access_operations_screen.dart';
import 'workspace_detail_screen.dart';

class WorkspaceCenterScreen extends StatefulWidget {
  const WorkspaceCenterScreen({super.key});

  @override
  State<WorkspaceCenterScreen> createState() => _WorkspaceCenterScreenState();
}

class _WorkspaceCenterScreenState extends State<WorkspaceCenterScreen> {
  String _workspace = 'Vereinsbereich';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Arbeitsbereiche', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Arbeitsbereiche',
        subtitle: 'Gastseite, Dashboard, Vereinsbereich, Trainerbereich, Rollen und Einladungen',
        trailing: const StatusPill('Workspace'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Kontext wechseln'),
            const SizedBox(height: 8),
            const Text('Airmius arbeitet in klaren Bereichen.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('Nutzer wechseln zwischen Gastseite, eigenem Dashboard, Vereinsverwaltung, Trainer-Cockpit und Admin-Kontexten, ohne den mobilen Flow zu verlieren.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: ['Gastseite', 'Dashboard', 'Vereinsbereich', 'Trainerbereich', 'Admin'].map((item) => ChoiceChip(
              selected: _workspace == item,
              label: Text(item),
              onSelected: (_) => setState(() => _workspace = item),
              selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
              backgroundColor: AirmiusColors.cardSoft,
              side: BorderSide(color: _workspace == item ? AirmiusColors.blue : AirmiusColors.border),
              labelStyle: TextStyle(color: _workspace == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
            )).toList()),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '3', label: 'Aktiv')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Einladung')), SizedBox(width: 10), Expanded(child: MetricCard(value: '6', label: 'Rollen'))]),
          const SizedBox(height: 14),
          _WorkspaceLine(
            icon: Icons.open_in_new_outlined,
            title: 'Gastseite',
            body: 'Public Portal, Vereine, Kurse, Marketplace, Blog, Jobs und Legal.',
            status: 'Public',
            color: AirmiusColors.blue,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WorkspaceDetailScreen(title: 'Gastseite', status: 'Public'))),
          ),
          const SizedBox(height: 12),
          _WorkspaceLine(
            icon: Icons.apartment_outlined,
            title: 'Vereinsbereich',
            body: 'Vereinsprofil, Teams, Mitglieder, Dokumente und Beiträge.',
            status: 'ZBB',
            color: AirmiusColors.green,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WorkspaceDetailScreen(title: 'Vereinsbereich', status: 'ZBB'))),
          ),
          const SizedBox(height: 12),
          _WorkspaceLine(
            icon: Icons.sports_score_outlined,
            title: 'Trainerbereich',
            body: 'Athleten, Trainingsplaene, Feedback und Wochenaktionen.',
            status: 'Coach',
            color: AirmiusColors.amber,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WorkspaceDetailScreen(title: 'Trainerbereich', status: 'Coach'))),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Workspace-Aktionen'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Bereich wechseln', icon: Icons.swap_horiz_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WorkspaceDetailScreen(title: 'Bereich wechseln', status: 'Aktiv')))),
              AirmiusButton(label: 'Einladungen', icon: Icons.mark_email_read_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WorkspaceDetailScreen(title: 'Einladungen', status: 'Offen')))),
              AirmiusButton(label: 'Rollen prüfen', icon: Icons.verified_user_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WorkspaceDetailScreen(title: 'Rollen prüfen', status: 'Audit')))),
              AirmiusButton(label: 'Access Ops', icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AccessOperationsScreen()))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _WorkspaceLine extends StatelessWidget {
  const _WorkspaceLine({required this.icon, required this.title, required this.body, required this.status, required this.color, required this.onTap});

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
