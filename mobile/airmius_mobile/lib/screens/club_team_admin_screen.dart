import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'access_operations_screen.dart';
import 'club_member_directory_screen.dart';
import 'file_operations_screen.dart';
import 'notification_chat_operations_screen.dart';
import 'team_operations_screen.dart';
import 'training_operations_screen.dart';

class ClubTeamAdminScreen extends StatefulWidget {
  const ClubTeamAdminScreen({super.key, this.initialTab = 'Teams'});

  final String initialTab;

  @override
  State<ClubTeamAdminScreen> createState() => _ClubTeamAdminScreenState();
}

class _ClubTeamAdminScreenState extends State<ClubTeamAdminScreen> {
  late String _tab = widget.initialTab;
  bool _showJoinRequests = true;
  bool _showInvitations = true;
  bool _showCalendar = true;
  bool _syncChatRights = true;

  @override
  Widget build(BuildContext context) {
    final teams = _teams.where((team) => _tab == 'Alle' || team.area == _tab).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Teamverwaltung', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Teamverwaltung',
        subtitle: 'Teams, Kader, Rollen, Einladungen, Join-Requests, Kalender, Dateien und Teamchat',
        trailing: const StatusPill('Teams', color: AirmiusColors.green),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const AirmiusLogo(),
            const SizedBox(height: 14),
            const Text('Vereine verwalten Teams mobil wie in der Web-App, nur handlicher.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
            const SizedBox(height: 8),
            const Text('Diese UI verbindet Teamprofile, Kader, Trainer, Captain, Einladungen, Join-Requests, Kalender, Teamdateien und Chatrechte. Laravel synchronisiert spaeter Rollen und Teamkontext.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '6', label: 'Teams')), SizedBox(width: 10), Expanded(child: MetricCard(value: '42', label: 'Kader')), SizedBox(width: 10), Expanded(child: MetricCard(value: '5', label: 'Anfragen'))]),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: [for (final tab in _tabs) ChoiceChip(label: Text(tab), selected: _tab == tab, onSelected: (_) => setState(() => _tab = tab), selectedColor: AirmiusColors.green.withValues(alpha: .22), backgroundColor: AirmiusColors.cardSoft, side: BorderSide(color: _tab == tab ? AirmiusColors.green : AirmiusColors.border), labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900))]),
          ])),
          const SizedBox(height: 16),
          _TeamAdminControls(showJoinRequests: _showJoinRequests, showInvitations: _showInvitations, showCalendar: _showCalendar, syncChatRights: _syncChatRights, onJoinRequests: (value) => setState(() => _showJoinRequests = value), onInvitations: (value) => setState(() => _showInvitations = value), onCalendar: (value) => setState(() => _showCalendar = value), onChatRights: (value) => setState(() => _syncChatRights = value)),
          const SizedBox(height: 16),
          for (final team in teams) ...[
            _TeamAdminCard(team: team, showJoinRequests: _showJoinRequests, showInvitations: _showInvitations, showCalendar: _showCalendar, syncChatRights: _syncChatRights),
            const SizedBox(height: 12),
          ],
          _TeamWorkflowPanel(tab: _tab),
        ]),
      ),
    );
  }
}

class _TeamAdminControls extends StatelessWidget {
  const _TeamAdminControls({required this.showJoinRequests, required this.showInvitations, required this.showCalendar, required this.syncChatRights, required this.onJoinRequests, required this.onInvitations, required this.onCalendar, required this.onChatRights});

  final bool showJoinRequests;
  final bool showInvitations;
  final bool showCalendar;
  final bool syncChatRights;
  final ValueChanged<bool> onJoinRequests;
  final ValueChanged<bool> onInvitations;
  final ValueChanged<bool> onCalendar;
  final ValueChanged<bool> onChatRights;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: AirmiusColors.green.withValues(alpha: .44), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    const Eyebrow('Team-Schalter'),
    const SizedBox(height: 8),
    const Text('Diese Schalter spiegeln Web-App-Funktionen mobil: Join-Requests, Einladungen, Teamkalender und Chatrechte koennen pro Verein gesteuert werden.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
    const SizedBox(height: 10),
    _TeamSwitch(icon: Icons.how_to_reg_outlined, title: 'Join-Requests anzeigen', body: 'Beitrittsanfragen fuer Teams mit Status und Adminentscheidung anzeigen.', value: showJoinRequests, onChanged: onJoinRequests, color: AirmiusColors.green),
    _TeamSwitch(icon: Icons.mail_outline, title: 'Einladungen anzeigen', body: 'Offene Team-Einladungen, Tokens und Ablaufdatum mobil sichtbar machen.', value: showInvitations, onChanged: onInvitations, color: AirmiusColors.blue),
    _TeamSwitch(icon: Icons.event_available_outlined, title: 'Teamkalender anzeigen', body: 'Events, Training, Anwesenheit und Erinnerungen im Teamkontext zeigen.', value: showCalendar, onChanged: onCalendar, color: AirmiusColors.amber),
    _TeamSwitch(icon: Icons.forum_outlined, title: 'Chatrechte synchronisieren', body: 'Teamrolle steuert spaeter automatisch Teamchat-Zugriff und Benachrichtigungen.', value: syncChatRights, onChanged: onChatRights, color: AirmiusColors.green),
  ]));
}

class _TeamAdminCard extends StatelessWidget {
  const _TeamAdminCard({required this.team, required this.showJoinRequests, required this.showInvitations, required this.showCalendar, required this.syncChatRights});

  final _ClubTeam team;
  final bool showJoinRequests;
  final bool showInvitations;
  final bool showCalendar;
  final bool syncChatRights;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: team.color.withValues(alpha: .44), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 52, height: 52, decoration: BoxDecoration(color: team.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: team.color.withValues(alpha: .45))), child: Icon(team.icon, color: team.color)),
      const SizedBox(width: 14),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(team.name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
        const SizedBox(height: 5),
        Text(team.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
        const SizedBox(height: 10),
        Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(team.area, color: team.color), StatusPill('${team.members} Mitglieder'), StatusPill(team.status, color: team.color)]),
      ])),
    ]),
    const SizedBox(height: 12),
    _TeamMeta(team: team),
    const SizedBox(height: 12),
    Wrap(spacing: 8, runSpacing: 8, children: [
      AirmiusButton(label: 'Kader', icon: Icons.people_outline, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMemberDirectoryScreen()))),
      AirmiusButton(label: 'Rollen', icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AccessOperationsScreen()))),
      if (showJoinRequests) AirmiusButton(label: 'Join-Requests', icon: Icons.how_to_reg_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${team.name} Join-Requests', body: 'Team-Beitrittsanfragen pruefen, annehmen, ablehnen und Nutzer benachrichtigen.', status: 'Join', icon: Icons.how_to_reg_outlined)),
      if (showInvitations) AirmiusButton(label: 'Einladen', icon: Icons.mail_outline, secondary: true, onPressed: () => openUiAction(context, title: '${team.name} Einladung', body: 'Team-Einladung mit Rolle, Ablaufdatum, Nachricht und Token vorbereiten.', status: 'Einladung', icon: Icons.mail_outline)),
      if (showCalendar) AirmiusButton(label: 'Kalender', icon: Icons.event_available_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TrainingOperationsScreen()))),
      AirmiusButton(label: 'Dateien', icon: Icons.folder_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FileOperationsScreen()))),
      if (syncChatRights) AirmiusButton(label: 'Teamchat', icon: Icons.forum_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Chat')))),
    ]),
  ]));
}

class _TeamMeta extends StatelessWidget {
  const _TeamMeta({required this.team});

  final _ClubTeam team;

  @override
  Widget build(BuildContext context) => Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    _MetaLine(label: 'Trainer', value: team.coach),
    _MetaLine(label: 'Captain', value: team.captain),
    _MetaLine(label: 'Naechstes Event', value: team.nextEvent),
    _MetaLine(label: 'Dateien', value: team.files),
  ]));
}

class _MetaLine extends StatelessWidget {
  const _MetaLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(padding: const EdgeInsets.symmetric(vertical: 4), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [SizedBox(width: 110, child: Text(label, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800))), Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)))]));
}

class _TeamWorkflowPanel extends StatelessWidget {
  const _TeamWorkflowPanel({required this.tab});

  final String tab;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: AirmiusColors.blue.withValues(alpha: .44), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    const Eyebrow('Team-Workflow'),
    const SizedBox(height: 8),
    Text('Aktueller Bereich: $tab. Spaeter verbindet Laravel diese UI mit Teamprofilen, Kader, Rollen, Einladungen, Join-Requests, Kalender, Dateien und Chatrechten.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
    const SizedBox(height: 12),
    Wrap(spacing: 8, runSpacing: 8, children: [
      AirmiusButton(label: 'Team Operations', icon: Icons.groups_2_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TeamOperationsScreen()))),
      AirmiusButton(label: 'Team erstellen', icon: Icons.add_circle_outline, secondary: true, onPressed: () => openUiAction(context, title: 'Team erstellen', body: 'Teamprofil, Sportart, Altersgruppe, Sichtbarkeit, Trainer, Captain, Kaderrechte und Chatrechte vorbereiten.', status: 'Team', icon: Icons.add_circle_outline)),
    ]),
  ]));
}

class _TeamSwitch extends StatelessWidget {
  const _TeamSwitch({required this.icon, required this.title, required this.body, required this.value, required this.onChanged, required this.color});

  final IconData icon;
  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;
  final Color color;

  @override
  Widget build(BuildContext context) => SwitchListTile(value: value, onChanged: onChanged, activeColor: color, contentPadding: EdgeInsets.zero, secondary: Icon(icon, color: color), title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)));
}

class _ClubTeam {
  const _ClubTeam({required this.area, required this.name, required this.body, required this.members, required this.status, required this.coach, required this.captain, required this.nextEvent, required this.files, required this.icon, required this.color});

  final String area;
  final String name;
  final String body;
  final int members;
  final String status;
  final String coach;
  final String captain;
  final String nextEvent;
  final String files;
  final IconData icon;
  final Color color;
}

const _tabs = ['Alle', 'Teams', 'Jugend', 'Training', 'Anfragen', 'Archiv'];

const _teams = <_ClubTeam>[
  _ClubTeam(area: 'Teams', name: 'Herren Aktiv', body: 'Aktives Team mit Kalender, Training, Captain und Teamchat.', members: 18, status: 'Aktiv', coach: 'verein airmius', captain: 'Jonas Weber', nextEvent: 'Training Freitag', files: '3 Dateien', icon: Icons.groups_2_outlined, color: AirmiusColors.green),
  _ClubTeam(area: 'Jugend', name: 'U16 Jugend', body: 'Jugendteam mit Guardian-Gates, Elternfreigabe und eingeschraenktem Chat.', members: 14, status: 'Guardian', coach: 'Mina Coach', captain: 'Noch offen', nextEvent: 'Turnier Samstag', files: 'Consent Pflicht', icon: Icons.family_restroom_outlined, color: AirmiusColors.blue),
  _ClubTeam(area: 'Training', name: 'Laufgruppe', body: 'Offene Trainingsgruppe mit Routen, Events und Anwesenheit.', members: 9, status: 'Offen', coach: 'Trainer Team', captain: 'Ali Hassan', nextEvent: 'Route 5km', files: 'Route GPX', icon: Icons.directions_run_outlined, color: AirmiusColors.amber),
  _ClubTeam(area: 'Anfragen', name: 'Probetraining', body: 'Team fuer neue Interessenten mit Join-Requests und Probemonat.', members: 5, status: '5 Anfragen', coach: 'Admin', captain: 'Nicht gesetzt', nextEvent: 'Probetraining Mittwoch', files: 'Infoblatt', icon: Icons.how_to_reg_outlined, color: AirmiusColors.green),
  _ClubTeam(area: 'Archiv', name: 'Saison 2025', body: 'Archiviertes Team mit Historie, Dateien und abgeschlossenen Events.', members: 22, status: 'Archiv', coach: 'Archiv', captain: 'Archiv', nextEvent: 'Keine', files: '12 Dateien', icon: Icons.archive_outlined, color: AirmiusColors.muted),
];
