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
  bool _requestingJoin = false;
  bool _reviewingJoinRequest = false;

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

  Future<void> _deleteTeam(AirmiusTeam team) async {
    final confirmed = await confirmDanger(context, 'Team "${team.name}" löschen', 'Dieses Team wird gelöscht. Diese Aktion kann nicht rückgängig gemacht werden.', 'Löschen');
    if (confirmed != true || !mounted) return;

    try {
      await AirmiusServicesScope.of(context).repositories.clubs.deleteTeam(team.id);
      if (!mounted) return;
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Team gelöscht.')));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Team konnte nicht gelöscht werden: $error')));
    }
  }

  Future<void> _requestJoin(AirmiusTeam team) async {
    if (_requestingJoin) return;
    setState(() => _requestingJoin = true);

    try {
      final updatedTeam = await AirmiusServicesScope.of(context).repositories.clubs.requestTeamJoin(team.id);
      if (!mounted) return;
      setState(() {
        _teamFuture = Future.value(updatedTeam);
        _requestingJoin = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Beitrittsanfrage gesendet.')));
    } catch (error) {
      if (!mounted) return;
      setState(() => _requestingJoin = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Beitrittsanfrage konnte nicht gesendet werden: $error')));
    }
  }

  Future<void> _reviewJoinRequest(AirmiusTeam team, AirmiusTeamJoinRequest request, {required bool approve}) async {
    if (_reviewingJoinRequest) return;
    final confirmed = approve
        ? true
        : await confirmDanger(context, 'Team-Anfrage ablehnen', 'Moechtest du die Anfrage von ${request.name} ablehnen?', 'Ablehnen');
    if (confirmed != true || !mounted) return;

    setState(() => _reviewingJoinRequest = true);
    try {
      final repository = AirmiusServicesScope.of(context).repositories.clubs;
      final updatedTeam = approve
          ? await repository.approveTeamJoinRequest(team.id, request.id, role: request.roleHint ?? 'Player')
          : await repository.declineTeamJoinRequest(team.id, request.id);
      if (!mounted) return;
      setState(() {
        _teamFuture = Future.value(updatedTeam);
        _section = 'Einladungen';
        _reviewingJoinRequest = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(approve ? 'Team-Anfrage angenommen.' : 'Team-Anfrage abgelehnt.')));
    } catch (error) {
      if (!mounted) return;
      setState(() => _reviewingJoinRequest = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Team-Anfrage konnte nicht verarbeitet werden: $error')));
    }
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
    final canManageTeam = team?.canManage == true || (team == null && widget.teamId == null);
    final sections = [
      'Profil',
      'Kader',
      if (canManageTeam) 'Rollen',
      if (canManageTeam) 'Einladungen',
      'Kalender',
      'Dateien',
      'Chat',
    ];
    if (!sections.contains(_section)) {
      _section = 'Profil';
    }
    if (canManageTeam && (team?.pendingJoinRequests.isNotEmpty ?? false) && _section == 'Kader') {
      _section = 'Einladungen';
    }
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
            Wrap(spacing: 8, runSpacing: 8, children: sections.map((item) => ChoiceChip(
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
          if (_section == 'Profil') _ProfilePanel(team: team, fallbackTitle: title, canManageTeam: canManageTeam, joinRequests: _joinRequests, teamChat: _teamChat, guardianGate: _guardianGate, onJoin: (value) => setState(() => _joinRequests = value), onChat: (value) => setState(() => _teamChat = value), onGuardian: (value) => setState(() => _guardianGate = value)),
          if (_section == 'Kader') _RosterPanel(team: team),
          if (_section == 'Rollen' && canManageTeam) const _RolesPanel(),
          if (_section == 'Einladungen' && canManageTeam)
            _InvitePanel(
              team: team,
              isReviewing: _reviewingJoinRequest,
              onApprove: team == null ? null : (request) => _reviewJoinRequest(team, request, approve: true),
              onDecline: team == null ? null : (request) => _reviewJoinRequest(team, request, approve: false),
            ),
          if (_section == 'Kalender') const _CalendarPanel(),
          if (_section == 'Dateien') const _FilesPanel(),
          if (_section == 'Chat') const _ChatPanel(),
          const SizedBox(height: 14),
          if (team?.viewerPendingJoinRequestId != null) ...[
            const _JoinRequestPendingNotice(),
            const SizedBox(height: 10),
          ],
          Wrap(spacing: 10, runSpacing: 10, children: [
            if (team?.canRequestJoin == true) AirmiusButton(label: _requestingJoin ? 'Wird gesendet...' : 'Beitritt anfragen', icon: Icons.how_to_reg_outlined, onPressed: _requestingJoin ? null : () => _requestJoin(team!)),
            if (widget.teamId != null && widget.teamId! > 0) AirmiusButton(label: 'Neu laden', icon: Icons.refresh_outlined, onPressed: _reloadTeam),
            if (team?.canDelete == true) AirmiusButton(label: 'Team löschen', icon: Icons.delete_outline, danger: true, onPressed: () => _deleteTeam(team!)),
            if (canManageTeam) AirmiusButton(label: 'Operations', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TeamOperationsScreen()))),
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

class _JoinRequestPendingNotice extends StatelessWidget {
  const _JoinRequestPendingNotice();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AirmiusColors.blue.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AirmiusColors.blue.withValues(alpha: 0.35)),
      ),
      child: const Row(children: [
        Icon(Icons.hourglass_top_outlined, color: AirmiusColors.blue, size: 20),
        SizedBox(width: 10),
        Expanded(child: Text('Deine Beitrittsanfrage wartet auf Freigabe.', style: TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))),
      ]),
    );
  }
}

class _ProfilePanel extends StatelessWidget {
  const _ProfilePanel({required this.team, required this.fallbackTitle, required this.canManageTeam, required this.joinRequests, required this.teamChat, required this.guardianGate, required this.onJoin, required this.onChat, required this.onGuardian});

  final AirmiusTeam? team;
  final String fallbackTitle;
  final bool canManageTeam;
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
      _TeamInfoGrid(rows: [
        _TeamInfoData(label: 'Teamname', value: team?.name ?? fallbackTitle, icon: Icons.groups_2_outlined),
        _TeamInfoData(label: 'Verein', value: team?.clubName ?? 'Nicht angegeben', icon: Icons.shield_outlined),
        _TeamInfoData(label: 'Sportart', value: team?.sportType ?? 'Nicht angegeben', icon: Icons.sports_soccer_outlined),
        _TeamInfoData(label: 'Altersgruppe', value: team?.ageGroup ?? 'Nicht angegeben', icon: Icons.group_outlined),
        _TeamInfoData(label: 'Sichtbarkeit', value: team?.visibility ?? 'Teamspace', icon: Icons.visibility_outlined),
        _TeamInfoData(label: 'Beschreibung', value: team?.description ?? 'Keine Beschreibung vorhanden.', icon: Icons.notes_outlined),
      ]),
      const SizedBox(height: 12),
      if (team?.attendanceStats != null) ...[
        _AttendanceStatsPanel(stats: team!.attendanceStats!),
        const SizedBox(height: 12),
      ],
      if (canManageTeam) ...[
        const Eyebrow('Mobile Teamfunktionen'),
        const SizedBox(height: 8),
        SwitchListTile(value: joinRequests, onChanged: onJoin, activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Beitrittsanfragen erlauben', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Interessierte können sich direkt beim Team melden.', style: TextStyle(color: AirmiusColors.muted))),
        SwitchListTile(value: teamChat, onChanged: onChat, activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Teamchat aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Chat wird mit Kalender und Dateien verbunden.', style: TextStyle(color: AirmiusColors.muted))),
        SwitchListTile(value: guardianGate, onChanged: onGuardian, activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Jugendschutz prüfen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Minderjährige brauchen passende Freigaben.', style: TextStyle(color: AirmiusColors.muted))),
      ],
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
                  Text('Dabei ${member.yes} · Verspätet ${member.late} · Absage ${member.no} · Keine Antwort ${member.noResponse}', style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700)),
                ]),
              )),
      ]),
    );
  }
}

class _TeamInfoData {
  const _TeamInfoData({required this.label, required this.value, required this.icon});

  final String label;
  final String value;
  final IconData icon;
}

class _TeamInfoGrid extends StatelessWidget {
  const _TeamInfoGrid({required this.rows});

  final List<_TeamInfoData> rows;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 430 ? 2 : 1;
        const gap = 10.0;
        final width = (constraints.maxWidth - gap * (columns - 1)) / columns;
        return Wrap(
          spacing: gap,
          runSpacing: gap,
          children: [
            for (final row in rows) SizedBox(width: width, child: _TeamInfoTile(row: row)),
          ],
        );
      },
    );
  }
}

class _TeamInfoTile extends StatelessWidget {
  const _TeamInfoTile({required this.row});

  final _TeamInfoData row;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 78,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
      child: Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
        Icon(row.icon, color: AirmiusColors.blue, size: 20),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
          Text(row.label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
          const SizedBox(height: 3),
          Text(row.value, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, height: 1.2)),
        ])),
      ]),
    );
  }
}

class _RosterPanel extends StatelessWidget {
  const _RosterPanel({required this.team});

  final AirmiusTeam? team;

  @override
  Widget build(BuildContext context) {
    final users = team?.users ?? const <AirmiusUser>[];
    return AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const Eyebrow('Kader'),
      const SizedBox(height: 12),
      if (users.isEmpty)
        const Text('Noch keine Teammitglieder geladen.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700))
      else
        for (final user in users) ...[
          _MemberRow(name: user.name, role: user.email.isNotEmpty ? user.email : user.role, status: 'Aktiv'),
          const SizedBox(height: 10),
        ],
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
  const _InvitePanel({required this.team, required this.isReviewing, required this.onApprove, required this.onDecline});

  final AirmiusTeam? team;
  final bool isReviewing;
  final ValueChanged<AirmiusTeamJoinRequest>? onApprove;
  final ValueChanged<AirmiusTeamJoinRequest>? onDecline;

  @override
  Widget build(BuildContext context) {
    final requests = team?.pendingJoinRequests.where((request) => request.status == 'pending').toList() ?? const <AirmiusTeamJoinRequest>[];
    return AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Row(children: [
        const Expanded(child: Eyebrow('Offene Team-Anfragen')),
        StatusPill('${requests.length} offen', color: requests.isEmpty ? AirmiusColors.green : AirmiusColors.amber),
      ]),
      const SizedBox(height: 12),
      if (requests.isEmpty)
        const Text('Keine offenen Team-Anfragen.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700))
      else
        for (final request in requests) ...[
          _TeamJoinRequestCard(
            request: request,
            isBusy: isReviewing,
            onApprove: onApprove == null ? null : () => onApprove!(request),
            onDecline: onDecline == null ? null : () => onDecline!(request),
          ),
          const SizedBox(height: 10),
        ],
      const SizedBox(height: 10),
      const Divider(color: AirmiusColors.border),
      const SizedBox(height: 10),
      const Eyebrow('Einladung senden'),
      const SizedBox(height: 12),
      const AirmiusTextField(label: 'E-Mail', hint: 'mitglied@example.com', icon: Icons.mail_outline),
      const SizedBox(height: 10),
      const AirmiusTextField(label: 'Rolle', hint: 'Spieler, Trainer, Captain', icon: Icons.admin_panel_settings_outlined),
      const SizedBox(height: 10),
      const Text('Einladungstoken, Ablaufdatum und Guardian-Pruefung werden spaeter ueber die API erzeugt.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
    ]));
  }
}

class _TeamJoinRequestCard extends StatelessWidget {
  const _TeamJoinRequestCard({required this.request, required this.isBusy, required this.onApprove, required this.onDecline});

  final AirmiusTeamJoinRequest request;
  final bool isBusy;
  final VoidCallback? onApprove;
  final VoidCallback? onDecline;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(children: [
          AirmiusAvatar(request.name),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(request.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            const SizedBox(height: 2),
            Text(request.email.isEmpty ? 'Keine E-Mail hinterlegt' : request.email, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
          ])),
          StatusPill(request.roleHint ?? 'Spieler'),
        ]),
        const SizedBox(height: 12),
        Wrap(spacing: 8, runSpacing: 8, children: [
          AirmiusButton(label: isBusy ? 'Wird gespeichert...' : 'Annehmen', icon: Icons.check_circle_outline, onPressed: isBusy ? null : onApprove),
          AirmiusButton(label: 'Ablehnen', icon: Icons.cancel_outlined, danger: true, secondary: true, onPressed: isBusy ? null : onDecline),
        ]),
      ]),
    );
  }
}

/*
class _LegacyInvitePanel extends StatelessWidget {
  const _LegacyInvitePanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
      Eyebrow('Einladung senden'),
      SizedBox(height: 12),
      AirmiusTextField(label: 'E-Mail', hint: 'mitglied@example.com', icon: Icons.mail_outline),
      SizedBox(height: 10),
      AirmiusTextField(label: 'Rolle', hint: 'Spieler, Trainer, Captain', icon: Icons.admin_panel_settings_outlined),
      SizedBox(height: 10),
      Text('Einladungstoken, Ablaufdatum und Guardian-Prüfung werden später über die API erzeugt.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
    ]));
  }
}

*/
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
      _EventLine(title: 'Trainingsordnung.pdf', body: 'Aus Vereins-Dateimanager verknüpft', status: 'Pflicht'),
      SizedBox(height: 10),
      _EventLine(title: 'Spielplan.xlsx', body: 'Nur Trainer und Captain dürfen bearbeiten', status: 'Team'),
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
      _EventLine(title: 'Trainer', body: 'Bitte Teilnahme für morgen bestätigen.', status: 'Neu'),
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
