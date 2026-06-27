import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'blog_media_detail_screen.dart';
import 'club_membership_admin_screen.dart';
import 'file_manager_screen.dart';
import 'team_detail_screen.dart';
import 'ui_action_result_screen.dart';

class ClubCockpitScreen extends StatefulWidget {
  const ClubCockpitScreen({super.key});

  @override
  State<ClubCockpitScreen> createState() => _ClubCockpitScreenState();
}

class _ClubCockpitScreenState extends State<ClubCockpitScreen> {
  String _filter = 'Übersicht';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Vereins-Cockpit', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Vereins-Cockpit',
        subtitle: 'Profil, Teams, Mitglieder, Beiträge, Dokumente und Sichtbarkeit für Vereinsadmins',
        trailing: const StatusPill('ZBB'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(
            gradient: true,
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              const Eyebrow('Vereinsbereich'),
              const SizedBox(height: 8),
              const Text('Alles Wichtige für den Verein an einem mobilen Ort.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
              const SizedBox(height: 8),
              const Text('Das Cockpit verbindet Vereinsprofil, Teams, Rollen, Mitgliedsanfragen, Dateien, Beiträge und Sichtbarkeit wie in der Web-App.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
              const SizedBox(height: 14),
              Wrap(spacing: 8, runSpacing: 8, children: ['Übersicht', 'Mitglieder', 'Teams', 'Dokumente', 'Sichtbarkeit'].map((item) => ChoiceChip(
                selected: _filter == item,
                label: Text(item),
                onSelected: (_) => setState(() => _filter = item),
                selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                backgroundColor: AirmiusColors.cardSoft,
                side: BorderSide(color: _filter == item ? AirmiusColors.blue : AirmiusColors.border),
                labelStyle: TextStyle(color: _filter == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
              )).toList()),
            ]),
          ),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '1', label: 'Anfrage')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Dokumente')), SizedBox(width: 10), Expanded(child: MetricCard(value: '82%', label: 'Profil'))]),
          const SizedBox(height: 14),
          _CockpitAction(icon: Icons.assignment_ind_outlined, title: 'Mitgliedschaftsanfragen', body: 'Antraege prüfen, Formularfelder vergleichen, Nachricht senden und annehmen.', status: '1 offen', color: AirmiusColors.green, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMembershipAdminScreen()))),
          const SizedBox(height: 12),
          _CockpitAction(icon: Icons.groups_2_outlined, title: 'Teams & Rollen', body: 'Teams, Trainer, Captain, Einladungen und Zugriffsrechte verwalten.', status: '11 Teams', color: AirmiusColors.blue, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TeamDetailScreen(title: 'Teams & Rollen', mode: 'Rollen')))),
          const SizedBox(height: 12),
          _CockpitAction(icon: Icons.folder_outlined, title: 'Vereinsdokumente', body: 'Datenschutz, Beitragsordnung und Regeln aus dem Dateimanager verknuepfen.', status: '3 Pflicht', color: AirmiusColors.amber, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FileManagerScreen()))),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Schnellaktionen'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Profil bearbeiten', icon: Icons.edit_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Vereinsprofil bearbeiten', body: 'Name, Beschreibung, Adresse, Sichtbarkeit und Public-Profil des Vereins bearbeiten.', status: 'Profil', icon: Icons.edit_outlined)))),
              AirmiusButton(label: 'Beitrag posten', icon: Icons.dynamic_feed_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BlogMediaDetailScreen(title: 'Vereinsbeitrag posten', status: 'Entwurf')))),
              AirmiusButton(label: 'Team erstellen', icon: Icons.group_add_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TeamDetailScreen(title: 'Neues Team', mode: 'Profil')))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _CockpitAction extends StatelessWidget {
  const _CockpitAction({required this.icon, required this.title, required this.body, required this.status, required this.color, required this.onTap});

  final IconData icon;
  final String title;
  final String body;
  final String status;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: onTap,
      borderColor: color.withValues(alpha: 0.45),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: color, size: 28),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)), const SizedBox(height: 10), StatusPill(status, color: color)])),
        const Icon(Icons.chevron_right, color: AirmiusColors.muted),
      ]),
    );
  }
}
