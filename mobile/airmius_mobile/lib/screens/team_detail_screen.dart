import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'team_operations_screen.dart';

class TeamDetailScreen extends StatefulWidget {
  const TeamDetailScreen({super.key, required this.title, required this.mode, this.teamId, this.team});

  final String title;
  final String mode;
  final int? teamId;
  final AirmiusTeam? team;

  @override
  State<TeamDetailScreen> createState() => _TeamDetailScreenState();
}

class _TeamDetailScreenState extends State<TeamDetailScreen> {
  late String _section;
  Future<AirmiusTeam>? _teamFuture;
  bool _joinRequests = true;
  bool _teamChat = true;
  bool _guardianGate = true;

  @override
  void initState() {
    super.initState();
    _section = widget.mode;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final teamId = widget.teamId;
    if (_teamFuture == null && teamId != null && teamId > 0) {
      _teamFuture = AirmiusServicesScope.of(context).repositories.clubs.team(teamId);
    }
  }

  void _reloadTeam() {
    final teamId = widget.teamId;
    if (teamId == null || teamId <= 0) return;
    setState(() => _teamFuture = AirmiusServicesScope.of(context).repositories.clubs.team(teamId));
  }

  @override
  Widget build(BuildContext context) {
    final future = _teamFuture;
    if (future == null) {
      return _buildScaffold(widget.team);
    }
    return FutureBuilder<AirmiusTeam>(
      future: future,
      builder: (context, snapshot) {
        final team = snapshot.data ?? widget.team;
        return _buildScaffold(team, isLoading: snapshot.connectionState == ConnectionState.waiting && team == null, error: snapshot.error);
      },
    );
  }

  Widget _buildScaffold(AirmiusTeam? team, {bool isLoading = false, Object? error}) {
    final title = team?.name ?? widget.title;
    final subtitle = _teamSubtitle(team);
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Team', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: title,
        subtitle: subtitle,
        trailing: StatusPill(team?.visibility ?? 'Teamspace'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Row(children: [
              AirmiusAvatar(title, imageUrl: team?.logoUrl),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Eyebrow('Teamprofil'),
                const SizedBox(height: 4),
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900)),
                Text(subtitle, style: const TextStyle(color: AirmiusColors.muted)),
              ])),
            ]),
            if (isLoading) ...[
              const SizedBox(height: 12),
              const LinearProgressIndicator(color: AirmiusColors.blue, backgroundColor: AirmiusColors.cardSoft),
            ],
            if (error != null) ...[
              const SizedBox(height: 12),
              Text('Teamdetails konnten gerade nicht geladen werden: $error', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
            ],
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: ['Profil', 'Kader', 'Rollen', 'Einladungen', 'Kalender', 'Dateien', 'Chat'].map((item) => ChoiceChip(
              selected: _section == item,
              label: Text(item),
              onSelected: (_) => setState(() => _section = item),
              selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
              backgroundColor: AirmiusColors.cardSoft,
              side: BorderSide(color: _section == item ? AirmiusColors.blue : AirmiusColors.border),
              labelStyle: TextStyle(color: _section == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
            )).toList()),
          ])),
          const SizedBox(height: 14),
          Row(children: [
            Expanded(child: MetricCard(value: '${team?.usersCount ?? '-'}', label: 'Kader')),
            const SizedBox(width: 10),
            Expanded(child: MetricCard(value: '${team?.eventsCount ?? '-'}', label: 'Events')),
            const SizedBox(width: 10),
            Expanded(child: MetricCard(value: '${team?.attendanceStats?.trainingsTotal ?? '-'}', label: 'Trainings')),
          ]),
          const SizedBox(height: 14),
          if (_section == 'Profil') _ProfilePanel(team: team, fallbackTitle: title, joinRequests: _joinRequests, teamChat: _teamChat, guardianGate: _guardianGate, onJoin: (value) => setState(() => _joinRequests = value), onChat: (value) => setState(() => _teamChat = value), onGuardian: (value) => setState(() => _guardianGate = value)),
          if (_section == 'Kader') const _RosterPanel(),
          if (_section == 'Rollen') const _RolesPanel(),
          if (_section == 'Einladungen') const _InvitePanel(),
          if (_section == 'Kalender') const _CalendarPanel(),
          if (_section == 'Dateien') const _FilesPanel(),
          if (_section == 'Chat') const _ChatPanel(),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            if (widget.teamId != null && widget.teamId! > 0) AirmiusButton(label: 'Neu laden', icon: Icons.refresh_outlined, onPressed: _reloadTeam),
            AirmiusButton(label: 'Operations', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TeamOperationsScreen()))),
          ]),
        ]),
      ),
    );
  }

  String _teamSubtitle(AirmiusTeam? team) {
    if (team == null) return 'Teamprofil, Kader, Rollen, Einladungen, Kalender und Dateien';
    final parts = [
      team.clubName,
      team.sportType,
      team.ageGroup,
      team.description,
    ].whereType<String>().where((value) => value.trim().isNotEmpty).toList();
    return parts.isEmpty ? 'Teamprofil, Kader, Rollen, Einladungen, Kalender und Dateien' : parts.join(' - ');
  }
}

class _ProfilePanel extends StatelessWidget {
  const _ProfilePanel({required this.team, required this.fallbackTitle, required this.joinRequests, required this.teamChat, required this.guardianGate, required this.onJoin, required this.onChat, required this.onGuardian});

  final AirmiusTeam? team;
  final String fallbackTitle;
  final bool joinRequests;
  final bool teamChat;
  final bool guardianGate;
  final ValueChanged<bool> onJoin;
  final ValueChanged<bool> onChat;
  final ValueChanged<bool> onGuardian;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const Eyebrow('Teamdaten'),
      const SizedBox(height: 12),
      _TeamInfoRow(label: 'Teamname', value: team?.name ?? fallbackTitle, icon: Icons.groups_2_outlined),
      _TeamInfoRow(label: 'Verein', value: team?.clubName ?? 'Nicht angegeben', icon: Icons.shield_outlined),
      _TeamInfoRow(label: 'Sportart', value: team?.sportType ?? 'Nicht angegeben', icon: Icons.sports_soccer_outlined),
      _TeamInfoRow(label: 'Altersgruppe', value: team?.ageGroup ?? 'Nicht angegeben', icon: Icons.group_outlined),
      _TeamInfoRow(label: 'Sichtbarkeit', value: team?.visibility ?? 'Teamspace', icon: Icons.visibility_outlined),
      _TeamInfoRow(label: 'Beschreibung', value: team?.description ?? 'Keine Beschreibung vorhanden.', icon: Icons.notes_outlined),
      const SizedBox(height: 12),
      if (team?.attendanceStats != null) ...[
        _AttendanceStatsPanel(stats: team!.attendanceStats!),
        const SizedBox(height: 12),
      ],
      const Eyebrow('Mobile Teamfunktionen'),
      const SizedBox(height: 8),
      SwitchListTile(value: joinRequests, onChanged: onJoin, activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Beitrittsanfragen erlauben', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Interessierte koennen sich direkt beim Team melden.', style: TextStyle(color: AirmiusColors.muted))),
      SwitchListTile(value: teamChat, onChanged: onChat, activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Teamchat aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Chat wird mit Kalender und Dateien verbunden.', style: TextStyle(color: AirmiusColors.muted))),
      SwitchListTile(value: guardianGate, onChanged: onGuardian, activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Jugendschutz pruefen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Minderjaehrige brauchen passende Freigaben.', style: TextStyle(color: AirmiusColors.muted))),
    ]));
  }
}

class _AttendanceStatsPanel extends StatelessWidget {
  const _AttendanceStatsPanel({required this.stats});

  final AirmiusTeamAttendanceStats stats;

  @override
  Widget build(BuildContext context) {
    final members = stats.members.take(8).toList();
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(children: [
          const Expanded(child: Eyebrow('Trainingsbeteiligung')),
          StatusPill('${stats.trainingsTotal} Trainings', color: AirmiusColors.blue),
        ]),
        const SizedBox(height: 10),
        if (members.isEmpty)
          const Text('Noch keine Trainingsteilnahmen vorhanden.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700))
        else
          ...members.map((member) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Row(children: [
                    Expanded(child: Text(member.name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                    Text('${member.attendanceRate.toStringAsFixed(1)}%', style: const TextStyle(color: AirmiusColors.green, fontWeight: FontWeight.w900)),
                  ]),
                  const SizedBox(height: 5),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(99),
                    child: LinearProgressIndicator(
                      value: (member.attendanceRate / 100).clamp(0, 1).toDouble(),
                      minHeight: 7,
                      color: AirmiusColors.blue,
                      backgroundColor: AirmiusColors.card,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text('Dabei ${member.yes} · Verspaetet ${member.late} · Absage ${member.no} · Keine Antwort ${member.noResponse}', style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700)),
                ]),
              )),
      ]),
    );
  }
}

class _TeamInfoRow extends StatelessWidget {
  const _TeamInfoRow({required this.label, required this.value, required this.icon});

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: AirmiusColors.blue, size: 20),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
          const SizedBox(height: 3),
          Text(value, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, height: 1.3)),
        ])),
      ]),
    );
  }
}

class _RosterPanel extends StatelessWidget {
  const _RosterPanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
      Eyebrow('Kader'),
      SizedBox(height: 12),
      _MemberRow(name: 'ZBB Konto', role: 'Spieler', status: 'Aktiv'),
      SizedBox(height: 10),
      _MemberRow(name: 'verein airmius', role: 'Trainer', status: 'Admin'),
      SizedBox(height: 10),
      _MemberRow(name: 'Max Beispiel', role: 'Captain', status: 'Einladung'),
    ]));
  }
}

class _RolesPanel extends StatelessWidget {
  const _RolesPanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
      Eyebrow('Teamrollen'),
      SizedBox(height: 12),
      _RoleRow(role: 'Trainer', rights: 'Kader, Plaene, Feedback, Events'),
      SizedBox(height: 10),
      _RoleRow(role: 'Captain', rights: 'Teilnahme, Chatmoderation, Anwesenheit'),
      SizedBox(height: 10),
      _RoleRow(role: 'Spieler', rights: 'Profil, Log, Chat, Dateien ansehen'),
    ]));
  }
}

class _InvitePanel extends StatelessWidget {
  const _InvitePanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
      Eyebrow('Einladung senden'),
      SizedBox(height: 12),
      AirmiusTextField(label: 'E-Mail', hint: 'mitglied@example.com', icon: Icons.mail_outline),
      SizedBox(height: 10),
      AirmiusTextField(label: 'Rolle', hint: 'Spieler, Trainer, Captain', icon: Icons.admin_panel_settings_outlined),
      SizedBox(height: 10),
      Text('Einladungstoken, Ablaufdatum und Guardian-Pruefung werden spaeter ueber die API erzeugt.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
    ]));
  }
}

class _CalendarPanel extends StatelessWidget {
  const _CalendarPanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
      Eyebrow('Teamkalender'),
      SizedBox(height: 12),
      _EventLine(title: 'Intervalltraining', body: 'Morgen 18:30 - Sportplatz', status: 'Offen'),
      SizedBox(height: 10),
      _EventLine(title: 'Auswaertsspiel', body: 'Samstag 15:00 - Treffpunkt 13:45', status: 'Geplant'),
      SizedBox(height: 10),
      _EventLine(title: 'Kraftblock', body: 'Montag 19:00 - Halle', status: 'Plan'),
    ]));
  }
}

class _FilesPanel extends StatelessWidget {
  const _FilesPanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
      Eyebrow('Teamdateien'),
      SizedBox(height: 12),
      _EventLine(title: 'Trainingsordnung.pdf', body: 'Aus Vereins-Dateimanager verknuepft', status: 'Pflicht'),
      SizedBox(height: 10),
      _EventLine(title: 'Spielplan.xlsx', body: 'Nur Trainer und Captain duerfen bearbeiten', status: 'Team'),
    ]));
  }
}

class _ChatPanel extends StatelessWidget {
  const _ChatPanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
      Eyebrow('Teamchat'),
      SizedBox(height: 12),
      _EventLine(title: 'Trainer', body: 'Bitte Teilnahme fuer morgen bestaetigen.', status: 'Neu'),
      SizedBox(height: 10),
      AirmiusTextField(label: 'Nachricht', hint: 'Nachricht an das Team', icon: Icons.chat_bubble_outline, maxLines: 2),
    ]));
  }
}

class _MemberRow extends StatelessWidget {
  const _MemberRow({required this.name, required this.role, required this.status});

  final String name;
  final String role;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Row(children: [AirmiusAvatar(name), const SizedBox(width: 10), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), Text(role, style: const TextStyle(color: AirmiusColors.muted))])), StatusPill(status)]);
  }
}

class _RoleRow extends StatelessWidget {
  const _RoleRow({required this.role, required this.rights});

  final String role;
  final String rights;

  @override
  Widget build(BuildContext context) {
    return Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(role, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(rights, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))]));
  }
}

class _EventLine extends StatelessWidget {
  const _EventLine({required this.title, required this.body, required this.status});

  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)), child: Row(children: [Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])), StatusPill(status)]));
  }
}
