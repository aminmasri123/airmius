import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'access_operations_screen.dart';
import 'role_permission_detail_screen.dart';

class RolesPermissionsScreen extends StatefulWidget {
  const RolesPermissionsScreen({super.key});

  @override
  State<RolesPermissionsScreen> createState() => _RolesPermissionsScreenState();
}

class _RolesPermissionsScreenState extends State<RolesPermissionsScreen> {
  String _scope = 'Rollen';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Rollen & Rechte', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Rollen & Rechte',
        subtitle: 'Permissions, Rollenmatrix, Zugriff, Audit und Sicherheitsregeln',
        trailing: const StatusPill('Security'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Berechtigungen'),
            const SizedBox(height: 8),
            const Text('Wer darf was in Airmius?', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('Admin, Coach, Captain, Mitglied, Guardian und Gast bekommen getrennte Rechte für sensible Bereiche wie Finanzen, Medien, Jugend und Vereinsdaten.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: ['Rollen', 'Permissions', 'Audit', 'Sicherheit'].map((item) => ChoiceChip(
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
          Row(children: const [Expanded(child: MetricCard(value: '6', label: 'Rollen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '42', label: 'Rechte')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Audits'))]),
          const SizedBox(height: 14),
          _RoleLine(
            icon: Icons.admin_panel_settings_outlined,
            title: 'Vereinsadmin',
            body: 'Mitglieder, Teams, Dokumente, Beiträge und Zahlungen verwalten.',
            status: 'Vollzugriff',
            color: AirmiusColors.blue,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RolePermissionDetailScreen(title: 'Vereinsadmin', status: 'Vollzugriff'))),
          ),
          const SizedBox(height: 12),
          _RoleLine(
            icon: Icons.sports_outlined,
            title: 'Coach',
            body: 'Trainingsplaene, Feedback, Events und Teamkommunikation verwalten.',
            status: 'Sport',
            color: AirmiusColors.green,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RolePermissionDetailScreen(title: 'Coach', status: 'Sport'))),
          ),
          const SizedBox(height: 12),
          _RoleLine(
            icon: Icons.family_restroom_outlined,
            title: 'Guardian',
            body: 'Kinderkonten, Zustimmung, Medienfreigabe und Jugendschutz prüfen.',
            status: 'Schutz',
            color: AirmiusColors.amber,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RolePermissionDetailScreen(title: 'Guardian', status: 'Schutz'))),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Rollen-Aktionen'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Rolle erstellen', icon: Icons.add_moderator_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RolePermissionDetailScreen(title: 'Neue Rolle', status: 'Entwurf')))),
              AirmiusButton(label: 'Matrix bearbeiten', icon: Icons.grid_on_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RolePermissionDetailScreen(title: 'Permission-Matrix', status: 'Matrix')))),
              AirmiusButton(label: 'Audit ansehen', icon: Icons.history_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RolePermissionDetailScreen(title: 'Audit', status: 'Audit')))),
              AirmiusButton(label: 'Access Operations', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AccessOperationsScreen()))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _RoleLine extends StatelessWidget {
  const _RoleLine({required this.icon, required this.title, required this.body, required this.status, required this.color, required this.onTap});

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
