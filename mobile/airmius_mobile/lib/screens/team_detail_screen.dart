import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'chat_detail_screen.dart';
import 'club_metadata_subject_screen.dart';
import 'conversations_center_screen.dart';
import 'event_management_screen.dart';
import 'file_manager_screen.dart';
import 'file_preview_screen.dart';
import 'team_operations_screen.dart';
import 'club_finance_workspace_screen.dart';
import 'training_event_detail_screen.dart';

String _safeTeamError(BuildContext context, Object error) {
  return error is AirmiusApiException
      ? error.userMessage
      : AirmiusScope.of(context).t('common.errorDetails');
}

class TeamDetailScreen extends StatefulWidget {
  const TeamDetailScreen({
    super.key,
    required this.title,
    required this.mode,
    this.teamId,
    this.team,
  });

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
  Future<JsonMap>? _competitionFuture;
  bool _joinRequests = true;
  bool _teamChat = true;
  bool _guardianGate = true;
  bool _requestingJoin = false;
  bool _reviewingJoinRequest = false;
  int? _updatingRoleUserId;
  bool _uploadingLogo = false;

  String _tr(String key) => AirmiusScope.of(context).t(key);

  String _errorMessage(Object error) => error is AirmiusApiException
      ? error.userMessage
      : _tr('common.errorDetails');

  String _sectionLabel(String section) => switch (section) {
    'Profil' => _tr('teamDetail.tab.profile'),
    'Kader' => _tr('teamDetail.tab.roster'),
    'Rollen' => _tr('teamDetail.tab.roles'),
    'Einladungen' => _tr('teamDetail.tab.invites'),
    'Einladen' => _tr('teamDetail.tab.invites'),
    'Kalender' => _tr('teamDetail.tab.calendar'),
    'Dateien' => _tr('teamDetail.tab.files'),
    'Chat' => _tr('teamDetail.tab.chat'),
    'Strafen' => _tr('teamDetail.tab.penalties'),
    'Wettbewerb' => _tr('teamDetail.tab.competition'),
    _ => section,
  };

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
      final repository = AirmiusServicesScope.of(context).repositories.clubs;
      _teamFuture = repository.team(teamId);
    }
  }

  void _reloadTeam() {
    final teamId = widget.teamId;
    if (teamId == null || teamId <= 0) return;
    setState(() {
      final repository = AirmiusServicesScope.of(context).repositories.clubs;
      _teamFuture = repository.team(teamId);
      if (_competitionFuture != null || _section == 'Wettbewerb') {
        _competitionFuture = repository.teamCompetitivenessInsights(teamId);
      }
    });
  }

  Future<void> _deleteTeam(AirmiusTeam team) async {
    final confirmed = await confirmDanger(
      context,
      '${_tr('teamDetail.deleteTeam')} "${team.name}"',
      _tr('teamDetail.deleteWarning'),
      _tr('teamDetail.delete'),
    );
    if (confirmed != true || !mounted) return;

    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.deleteTeam(team.id);
      if (!mounted) return;
      Navigator.pop(context);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_tr('teamDetail.deleted'))));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${_tr('teamDetail.deleteFailed')}: ${_errorMessage(error)}',
          ),
        ),
      );
    }
  }

  Future<void> _requestJoin(AirmiusTeam team) async {
    if (_requestingJoin) return;
    setState(() => _requestingJoin = true);

    try {
      final updatedTeam = await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.requestTeamJoin(team.id);
      if (!mounted) return;
      setState(() {
        _teamFuture = Future.value(updatedTeam);
        _requestingJoin = false;
      });
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_tr('teamDetail.joinSent'))));
    } catch (error) {
      if (!mounted) return;
      setState(() => _requestingJoin = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${_tr('teamDetail.joinFailed')}: ${_errorMessage(error)}',
          ),
        ),
      );
    }
  }

  Future<void> _reviewJoinRequest(
    AirmiusTeam team,
    AirmiusTeamJoinRequest request, {
    required bool approve,
  }) async {
    if (_reviewingJoinRequest) return;
    final confirmed = approve
        ? true
        : await confirmDanger(
            context,
            _tr('teamDetail.declineRequest'),
            '${_tr('teamDetail.declineRequestBefore')} ${request.name}?',
            _tr('teamDetail.decline'),
          );
    if (confirmed != true || !mounted) return;

    setState(() => _reviewingJoinRequest = true);
    try {
      final repository = AirmiusServicesScope.of(context).repositories.clubs;
      final updatedTeam = approve
          ? await repository.approveTeamJoinRequest(
              team.id,
              request.id,
              role: request.roleHint ?? 'Player',
            )
          : await repository.declineTeamJoinRequest(team.id, request.id);
      if (!mounted) return;
      setState(() {
        _teamFuture = Future.value(updatedTeam);
        _section = 'Einladen';
        _reviewingJoinRequest = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            approve
                ? _tr('teamDetail.requestApproved')
                : _tr('teamDetail.requestDeclined'),
          ),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _reviewingJoinRequest = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${_tr('teamDetail.requestFailed')}: ${_errorMessage(error)}',
          ),
        ),
      );
    }
  }

  Future<void> _updateTeamMemberRole(
    AirmiusTeam team,
    AirmiusUser user,
    String role,
  ) async {
    if (_updatingRoleUserId != null || user.role == role) return;
    setState(() => _updatingRoleUserId = user.id);

    try {
      final updatedTeam = await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.updateTeamMemberRole(team.id, user.id, role);
      if (!mounted) return;
      setState(() {
        _teamFuture = Future.value(updatedTeam);
        _section = 'Kader';
        _updatingRoleUserId = null;
      });
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_tr('teamDetail.roleUpdated'))));
    } catch (error) {
      if (!mounted) return;
      setState(() => _updatingRoleUserId = null);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${_tr('teamDetail.roleFailed')}: ${_errorMessage(error)}',
          ),
        ),
      );
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
        return _buildScaffold(
          team,
          isLoading:
              snapshot.connectionState == ConnectionState.waiting &&
              team == null,
          error: snapshot.error,
        );
      },
    );
  }

  Widget _buildScaffold(
    AirmiusTeam? team, {
    bool isLoading = false,
    Object? error,
  }) {
    final title = team?.name ?? widget.title;
    final subtitle = _teamSubtitle(team);
    if (_section == 'Einladungen') {
      _section = 'Einladen';
    }
    final canManageTeam =
        team?.canManage == true || (team == null && widget.teamId == null);
    final sections = [
      'Profil',
      'Kader',
      if (canManageTeam) 'Rollen',
      if (canManageTeam) 'Einladen',
      'Kalender',
      'Dateien',
      'Chat',
      'Strafen',
      'Wettbewerb',
    ];
    if (!sections.contains(_section)) {
      _section = 'Profil';
    }
    if (canManageTeam &&
        (team?.pendingJoinRequests.isNotEmpty ?? false) &&
        _section == 'Kader') {
      _section = 'Einladen';
    }
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          _tr('teamDetail.team'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          if (team?.canDelete == true)
            PopupMenuButton<String>(
              tooltip: _tr('teamDetail.operations'),
              onSelected: (value) {
                if (value == 'delete') _deleteTeam(team!);
              },
              itemBuilder: (_) => [
                PopupMenuItem<String>(
                  value: 'delete',
                  child: Row(
                    children: [
                      const Icon(
                        Icons.delete_outline,
                        color: AirmiusColors.red,
                      ),
                      const SizedBox(width: 12),
                      Text(_tr('teamDetail.deleteTeam')),
                    ],
                  ),
                ),
              ],
            ),
        ],
      ),
      body: PageFrame(
        title: title,
        subtitle: subtitle,
        trailing: StatusPill(team?.visibility ?? _tr('teamDetail.teamspace')),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Stack(
                        children: [
                          AirmiusAvatar(title, imageUrl: team?.logoUrl),
                          if (team != null && canManageTeam && !_uploadingLogo)
                            Positioned(
                              right: 0,
                              bottom: 0,
                              child: InkWell(
                                onTap: () => _pickAndUploadTeamLogo(team),
                                child: Container(
                                  height: 28,
                                  width: 28,
                                  decoration: BoxDecoration(
                                    color: airmiusAccentColor(context),
                                    borderRadius: BorderRadius.circular(14),
                                  ),
                                  child: Icon(
                                    Icons.camera_alt_outlined,
                                    color: airmiusTextColor(context),
                                    size: 16,
                                  ),
                                ),
                              ),
                            ),
                          if (team != null && canManageTeam && _uploadingLogo)
                            const Positioned(
                              right: 4,
                              bottom: 4,
                              child: SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Eyebrow(_tr('teamDetail.teamProfile')),
                            const SizedBox(height: 4),
                            Text(
                              title,
                              style: TextStyle(
                                color: airmiusTextColor(context),
                                fontSize: 22,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            Text(
                              subtitle,
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  if (isLoading) ...[
                    const SizedBox(height: 12),
                    LinearProgressIndicator(
                      color: airmiusAccentColor(context),
                      backgroundColor: airmiusSurfaceSoftColor(context),
                    ),
                  ],
                  if (error != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      '${_tr('teamDetail.loadFailed')}: ${_errorMessage(error)}',
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                  const SizedBox(height: 14),
                  DropdownButtonFormField<String>(
                    key: ValueKey('team-detail-section-$_section'),
                    initialValue: _section,
                    isExpanded: true,
                    decoration: InputDecoration(
                      labelText: _tr('teamDetail.section'),
                      prefixIcon: const Icon(
                        Icons.dashboard_customize_outlined,
                      ),
                    ),
                    items: sections
                        .map(
                          (item) => DropdownMenuItem(
                            value: item,
                            child: Text(_sectionLabel(item)),
                          ),
                        )
                        .toList(),
                    onChanged: (item) {
                      if (item == null) return;
                      setState(() {
                        _section = item;
                        if (item == 'Wettbewerb' &&
                            _competitionFuture == null) {
                          final teamId = team?.id ?? widget.teamId;
                          if (teamId != null && teamId > 0) {
                            _competitionFuture =
                                AirmiusServicesScope.of(context)
                                    .repositories
                                    .clubs
                                    .teamCompetitivenessInsights(teamId);
                          }
                        }
                      });
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            if (canManageTeam) ...[
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if (_section != 'Einladen')
                    FilledButton.icon(
                      onPressed: () => setState(() => _section = 'Einladen'),
                      icon: const Icon(Icons.person_add_alt_1_outlined),
                      label: Text(_tr('teamDetail.tab.invites')),
                    ),
                  if (_section != 'Kalender')
                    OutlinedButton.icon(
                      onPressed: () => setState(() => _section = 'Kalender'),
                      icon: const Icon(Icons.event_outlined),
                      label: Text(_tr('teamDetail.tab.calendar')),
                    ),
                  if (_section != 'Kader')
                    OutlinedButton.icon(
                      onPressed: () => setState(() => _section = 'Kader'),
                      icon: const Icon(Icons.groups_2_outlined),
                      label: Text(_tr('teamDetail.roster')),
                    ),
                ],
              ),
              const SizedBox(height: 14),
            ],
            Material(
              color: Theme.of(context).colorScheme.surface,
              child: ExpansionTile(
                title: Text(_tr('teamDetail.metrics')),
                children: [
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      MetricCard(
                        value: '${team?.usersCount ?? '-'}',
                        label: _tr('teamDetail.roster'),
                      ),
                      MetricCard(
                        value: '${team?.eventsCount ?? '-'}',
                        label: _tr('teamDetail.events'),
                      ),
                      MetricCard(
                        value:
                            '${team?.attendanceStats?.trainingsTotal ?? '-'}',
                        label: _tr('teamDetail.trainings'),
                      ),
                      if (team?.memberInvitationRemainingToday != null &&
                          team?.memberInvitationDailyLimit != null)
                        MetricCard(
                          value:
                              '${team!.memberInvitationRemainingToday} / ${team.memberInvitationDailyLimit}',
                          label: _tr('teamDetail.invitesRemaining'),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            if (_section == 'Profil' &&
                team != null &&
                team.canManageMetadata) ...[
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: ClubMetadataSubjectButton(
                  clubId: team.clubId,
                  subjectType: 'team',
                  subjectId: team.id,
                  subjectTitle: team.name,
                ),
              ),
              const SizedBox(height: 12),
            ],
            if (_section == 'Profil')
              _ProfilePanel(
                team: team,
                fallbackTitle: title,
                canManageTeam: canManageTeam,
                joinRequests: _joinRequests,
                teamChat: _teamChat,
                guardianGate: _guardianGate,
                onSectionSelected: (section) =>
                    setState(() => _section = section),
                onJoin: (value) => setState(() => _joinRequests = value),
                onChat: (value) => setState(() => _teamChat = value),
                onGuardian: (value) => setState(() => _guardianGate = value),
                onUpdated: (updatedTeam) => setState(() {
                  _teamFuture = Future.value(updatedTeam);
                }),
              ),
            if (_section == 'Kader')
              _RosterPanel(
                team: team,
                canManageTeam: canManageTeam,
                updatingUserId: _updatingRoleUserId,
                onRoleChanged: team == null
                    ? null
                    : (user, role) => _updateTeamMemberRole(team, user, role),
              ),
            if (_section == 'Rollen' && canManageTeam) const _RolesPanel(),
            if (_section == 'Einladen' && canManageTeam)
              _InvitePanel(
                team: team,
                isReviewing: _reviewingJoinRequest,
                onApprove: team == null
                    ? null
                    : (request) =>
                          _reviewJoinRequest(team, request, approve: true),
                onDecline: team == null
                    ? null
                    : (request) =>
                          _reviewJoinRequest(team, request, approve: false),
              ),
            if (_section == 'Kalender') _CalendarPanel(team: team),
            if (_section == 'Dateien' && team != null)
              _TeamFilesPanel(team: team),
            if (_section == 'Dateien' && team == null) const _FilesPanel(),
            if (_section == 'Chat' && team != null) _TeamChatPanel(team: team),
            if (_section == 'Chat' && team == null) const _ChatPanel(),
            if (_section == 'Strafen' && team != null)
              _PenaltiesPanel(team: team, canManageTeam: canManageTeam),
            if (_section == 'Wettbewerb')
              _CompetitionPanel(
                future: _competitionFuture,
                onRetry: () {
                  final teamId = team?.id ?? widget.teamId;
                  if (teamId == null || teamId <= 0) return;
                  setState(() {
                    _competitionFuture = AirmiusServicesScope.of(
                      context,
                    ).repositories.clubs.teamCompetitivenessInsights(teamId);
                  });
                },
              ),
            const SizedBox(height: 14),
            if (team?.viewerPendingJoinRequestId != null) ...[
              const _JoinRequestPendingNotice(),
              const SizedBox(height: 10),
            ],
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (team?.canRequestJoin == true)
                  AirmiusButton(
                    label: _requestingJoin
                        ? _tr('teamDetail.sending')
                        : _tr('teamDetail.requestJoin'),
                    icon: Icons.how_to_reg_outlined,
                    onPressed: _requestingJoin
                        ? null
                        : () => _requestJoin(team!),
                  ),
                if (widget.teamId != null && widget.teamId! > 0)
                  AirmiusButton(
                    label: _tr('teamDetail.reload'),
                    icon: Icons.refresh_outlined,
                    onPressed: _reloadTeam,
                  ),
                if (canManageTeam)
                  AirmiusButton(
                    label: _tr('teamDetail.operations'),
                    icon: Icons.tune_outlined,
                    secondary: true,
                    onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const TeamOperationsScreen(),
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _teamSubtitle(AirmiusTeam? team) {
    if (team == null) return _tr('teamDetail.subtitle');
    final parts = [
      team.clubName,
      team.sportType?.toLowerCase() == 'strassenlauf'
          ? _tr('clubHub.sport.strassenlauf')
          : team.sportType,
      team.ageGroup,
      team.description,
    ].whereType<String>().where((value) => value.trim().isNotEmpty).toList();
    return parts.isEmpty ? _tr('teamDetail.subtitle') : parts.join(' - ');
  }

  Future<void> _pickAndUploadTeamLogo(AirmiusTeam team) async {
    if (_uploadingLogo) return;

    final selection = await FilePicker.platform.pickFiles(
      type: FileType.image,
      withData: true,
    );
    final file = selection?.files.single;
    if (file == null || !mounted) return;

    setState(() => _uploadingLogo = true);

    try {
      final services = AirmiusServicesScope.of(context);
      final session = services.authState.session;
      if (session == null) {
        throw StateError('Keine Session vorhanden.');
      }

      final base = Uri.parse(services.clientForSession(session).baseUrl);
      final rootPath = base.path.endsWith('/') ? base.path : '${base.path}/';
      final path = '${rootPath}api/v1/teams/${team.id}/images';

      final request =
          http.MultipartRequest(
              'POST',
              base.replace(path: path, query: null, fragment: null),
            )
            ..headers['Authorization'] = 'Bearer ${session.token}'
            ..headers['Accept'] = 'application/json'
            ..headers['Accept-Language'] = AirmiusScope.of(
              context,
            ).language.locale.languageCode;

      if (file.bytes != null) {
        request.files.add(
          http.MultipartFile.fromBytes(
            'logo',
            file.bytes!,
            filename: file.name,
          ),
        );
      } else if (file.path != null) {
        request.files.add(
          await http.MultipartFile.fromPath(
            'logo',
            file.path!,
            filename: file.name,
          ),
        );
      } else {
        throw StateError('Datei konnte nicht gelesen werden.');
      }

      final response = await http.Response.fromStream(await request.send());
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw AirmiusApiException(
          statusCode: response.statusCode,
          body: response.body,
          path: '/api/v1/teams/${team.id}/images',
        );
      }

      if (!mounted) return;
      _reloadTeam();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Teamlogo gespeichert.')));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${_tr('common.errorDetails')}: ${_errorMessage(error)}',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _uploadingLogo = false);
    }
  }
}

Map<String, dynamic> _teamPenaltyMap(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _teamPenaltyList(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <Map<String, dynamic>>[];

String _teamPenaltyText(Object? value, [String fallback = '']) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

double _teamPenaltyNumber(Object? value) => value is num
    ? value.toDouble()
    : double.tryParse('$value'.replaceAll(',', '.')) ?? 0;

int _teamPenaltyInt(Object? value) => _teamPenaltyNumber(value).round();

bool _teamPenaltyBool(Object? value) =>
    value == true || value == 1 || value?.toString() == '1';

String _teamPenaltyMoney(Object? value) =>
    '${_teamPenaltyNumber(value).toStringAsFixed(2)} EUR';

String _teamPenaltyTriggerLabel(String value, String Function(String) t) =>
    switch (value) {
      'late' => t('teamDetail.penaltyTriggerLate'),
      'absence' => t('teamDetail.penaltyTriggerAbsence'),
      'forgotten_equipment' => t('teamDetail.penaltyTriggerEquipment'),
      'custom' => t('teamDetail.penaltyTriggerCustom'),
      _ => value,
    };

String _teamPenaltyCalculationLabel(String value, String Function(String) t) =>
    switch (value) {
      'fixed' => t('teamDetail.penaltyCalculationFixed'),
      'per_minute' => t('teamDetail.penaltyCalculationMinute'),
      'threshold_fixed' => t('teamDetail.penaltyCalculationThreshold'),
      'item' => t('teamDetail.penaltyCalculationItem'),
      _ => value,
    };

class _PenaltiesPanel extends StatefulWidget {
  const _PenaltiesPanel({required this.team, required this.canManageTeam});

  final AirmiusTeam team;
  final bool canManageTeam;

  @override
  State<_PenaltiesPanel> createState() => _PenaltiesPanelState();
}

class _PenaltiesPanelState extends State<_PenaltiesPanel> {
  Future<AirmiusJson>? _future;
  bool _busy = false;

  String t(String key) => AirmiusScope.of(context).t(key);

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _client.teamPenalties(widget.team.id);
  }

  void _reload() {
    setState(() => _future = _client.teamPenalties(widget.team.id));
  }

  Future<void> _run(
    Future<dynamic> Function() action, {
    required String success,
  }) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(success)));
      _reload();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(_safeTeamError(context, error))));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _addRule() async {
    final titleController = TextEditingController();
    final amountController = TextEditingController();
    final descriptionController = TextEditingController();
    var trigger = 'late';
    var calculation = 'fixed';
    String? formError;
    final payload = await showDialog<AirmiusJson>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('teamDetail.addPenaltyRule')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: titleController,
                  autofocus: true,
                  decoration: InputDecoration(
                    labelText: t('teamDetail.penaltyTitle'),
                  ),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  initialValue: trigger,
                  isExpanded: true,
                  decoration: InputDecoration(
                    labelText: t('teamDetail.penaltyTrigger'),
                  ),
                  items: [
                    for (final value in const [
                      'late',
                      'absence',
                      'forgotten_equipment',
                      'custom',
                    ])
                      DropdownMenuItem(
                        value: value,
                        child: Text(_teamPenaltyTriggerLabel(value, t)),
                      ),
                  ],
                  onChanged: (value) =>
                      setDialogState(() => trigger = value ?? trigger),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  initialValue: calculation,
                  isExpanded: true,
                  decoration: InputDecoration(
                    labelText: t('teamDetail.penaltyCalculation'),
                  ),
                  items: [
                    for (final value in const [
                      'fixed',
                      'per_minute',
                      'threshold_fixed',
                      'item',
                    ])
                      DropdownMenuItem(
                        value: value,
                        child: Text(_teamPenaltyCalculationLabel(value, t)),
                      ),
                  ],
                  onChanged: (value) =>
                      setDialogState(() => calculation = value ?? calculation),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: amountController,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: InputDecoration(
                    labelText: t('teamDetail.penaltyAmount'),
                    hintText: '5,00',
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: descriptionController,
                  maxLines: 2,
                  decoration: InputDecoration(
                    labelText: t('teamDetail.penaltyDescription'),
                  ),
                ),
                if (formError != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    formError!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: () {
                final title = titleController.text.trim();
                final amount = double.tryParse(
                  amountController.text.trim().replaceAll(',', '.'),
                );
                if (title.isEmpty ||
                    (calculation != 'item' && amount == null)) {
                  setDialogState(
                    () => formError = t('teamDetail.penaltyRequired'),
                  );
                  return;
                }
                Navigator.pop(dialogContext, <String, dynamic>{
                  'title': title,
                  'trigger': trigger,
                  'calculation_type': calculation,
                  'amount': amount,
                  'currency': 'EUR',
                  'description': descriptionController.text.trim().isEmpty
                      ? null
                      : descriptionController.text.trim(),
                  'is_active': true,
                });
              },
              child: Text(t('teamDetail.savePenaltyRule')),
            ),
          ],
        ),
      ),
    );
    titleController.dispose();
    amountController.dispose();
    descriptionController.dispose();
    if (payload == null || !mounted) return;
    await _run(
      () => _client.createTeamPenaltyRule(widget.team.id, payload),
      success: t('teamDetail.penaltyRuleSaved'),
    );
  }

  Future<void> _addFee() async {
    if (widget.team.users.isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('teamDetail.noMembers'))));
      return;
    }
    final amountController = TextEditingController();
    final noteController = TextEditingController();
    var selectedUser = widget.team.users.first.id;
    String? formError;
    final payload = await showDialog<AirmiusJson>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('teamDetail.addPenaltyFee')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<int>(
                  initialValue: selectedUser,
                  isExpanded: true,
                  decoration: InputDecoration(
                    labelText: t('teamDetail.member'),
                  ),
                  items: [
                    for (final user in widget.team.users)
                      DropdownMenuItem(value: user.id, child: Text(user.name)),
                  ],
                  onChanged: (value) => setDialogState(
                    () => selectedUser = value ?? selectedUser,
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: amountController,
                  autofocus: true,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: InputDecoration(
                    labelText: t('teamDetail.penaltyAmount'),
                    hintText: '5,00',
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: noteController,
                  maxLines: 2,
                  decoration: InputDecoration(
                    labelText: t('teamDetail.penaltyNote'),
                  ),
                ),
                if (formError != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    formError!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: () {
                final amount = double.tryParse(
                  amountController.text.trim().replaceAll(',', '.'),
                );
                if (amount == null || amount <= 0) {
                  setDialogState(
                    () => formError = t('teamDetail.penaltyRequired'),
                  );
                  return;
                }
                Navigator.pop(dialogContext, <String, dynamic>{
                  'user_id': selectedUser,
                  'amount': amount,
                  if (noteController.text.trim().isNotEmpty)
                    'note': noteController.text.trim(),
                });
              },
              child: Text(t('teamDetail.savePenaltyFee')),
            ),
          ],
        ),
      ),
    );
    amountController.dispose();
    noteController.dispose();
    if (payload == null || !mounted) return;
    await _run(
      () => _client.createTeamPenaltyFee(widget.team.id, payload),
      success: t('teamDetail.penaltyFeeSaved'),
    );
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<AirmiusJson>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const AirmiusPanel(
            child: Center(child: CircularProgressIndicator()),
          );
        }
        if (snapshot.hasError) {
          return AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(t('teamDetail.penaltiesLoadFailed')),
                const SizedBox(height: 10),
                AirmiusButton(
                  label: t('teamDetail.reload'),
                  icon: Icons.refresh_outlined,
                  onPressed: _reload,
                ),
              ],
            ),
          );
        }
        final payload = _teamPenaltyMap(
          snapshot.data?['data'] ?? snapshot.data,
        );
        final rules = _teamPenaltyList(payload['rules']);
        final fees = _teamPenaltyList(payload['fees']);
        final summary = _teamPenaltyMap(payload['summary']);
        final canManage =
            widget.canManageTeam && payload['can_manage'] != false;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          t('teamDetail.penalties'),
                          style: const TextStyle(fontWeight: FontWeight.w900),
                        ),
                      ),
                      IconButton(
                        tooltip: t('teamDetail.reload'),
                        onPressed: _busy ? null : _reload,
                        icon: const Icon(Icons.refresh_outlined),
                      ),
                    ],
                  ),
                  Text(
                    t('teamDetail.penaltiesBody'),
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _PenaltyMetric(
                        value: _teamPenaltyMoney(summary['open_amount']),
                        label: t('teamDetail.openPenalties'),
                      ),
                      _PenaltyMetric(
                        value: '${_teamPenaltyInt(summary['open_count'])}',
                        label: t('teamDetail.openPenaltyCount'),
                      ),
                      if (canManage)
                        AirmiusButton(
                          label: t('teamDetail.addPenaltyRule'),
                          icon: Icons.add_task_outlined,
                          secondary: true,
                          onPressed: _busy ? null : _addRule,
                        ),
                      if (canManage)
                        AirmiusButton(
                          label: t('teamDetail.addPenaltyFee'),
                          icon: Icons.receipt_long_outlined,
                          secondary: true,
                          onPressed: _busy ? null : _addFee,
                        ),
                    ],
                  ),
                  if (_busy) ...[
                    const SizedBox(height: 10),
                    const LinearProgressIndicator(minHeight: 3),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 10),
            _PenaltyListPanel(
              title: t('teamDetail.penaltyRules'),
              empty: t('teamDetail.noPenaltyRules'),
              children: rules
                  .map(
                    (rule) => _PenaltyRuleRow(
                      rule: rule,
                      canManage: canManage,
                      onDeactivate: () => _run(
                        () => _client.deactivateTeamPenaltyRule(
                          widget.team.id,
                          _teamPenaltyInt(rule['id']),
                        ),
                        success: t('teamDetail.penaltyRuleDeactivated'),
                      ),
                    ),
                  )
                  .toList(),
            ),
            const SizedBox(height: 10),
            _PenaltyListPanel(
              title: t('teamDetail.penaltyFees'),
              empty: t('teamDetail.noPenaltyFees'),
              children: fees
                  .map(
                    (fee) => _PenaltyFeeRow(
                      fee: fee,
                      canManage: canManage,
                      onPaid: () async {
                        final value = await teamFinancePaymentDialog(
                          context,
                          _teamPenaltyList(payload['money_accounts']),
                        );
                        if (value == null || !mounted) return;
                        await _run(
                          () => _client.markTeamPenaltyFeePaid(
                            widget.team.id,
                            _teamPenaltyInt(fee['id']),
                            payload: value,
                          ),
                          success: t('teamDetail.penaltyFeePaid'),
                        );
                      },
                      onRefund: () async {
                        final success = financeWorkspaceLabel(
                          context,
                          'Rückzahlung',
                        );
                        final value = await teamFinancePaymentDialog(
                          context,
                          const [],
                          refund: true,
                        );
                        if (value == null || !mounted) return;
                        await _run(
                          () => _client.refundTeamPenaltyFee(
                            widget.team.id,
                            _teamPenaltyInt(fee['id']),
                            value,
                          ),
                          success: success,
                        );
                      },
                      onCancel: () => _run(
                        () => _client.cancelTeamPenaltyFee(
                          widget.team.id,
                          _teamPenaltyInt(fee['id']),
                        ),
                        success: t('teamDetail.penaltyFeeCancelled'),
                      ),
                    ),
                  )
                  .toList(),
            ),
          ],
        );
      },
    );
  }
}

class _PenaltyMetric extends StatelessWidget {
  const _PenaltyMetric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => MetricCard(value: value, label: label);
}

class _PenaltyListPanel extends StatelessWidget {
  const _PenaltyListPanel({
    required this.title,
    required this.empty,
    required this.children,
  });

  final String title;
  final String empty;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
        const SizedBox(height: 8),
        if (children.isEmpty)
          Text(empty, style: TextStyle(color: airmiusMutedColor(context)))
        else
          ...children,
      ],
    ),
  );
}

class _PenaltyRuleRow extends StatelessWidget {
  const _PenaltyRuleRow({
    required this.rule,
    required this.canManage,
    required this.onDeactivate,
  });

  final Map<String, dynamic> rule;
  final bool canManage;
  final VoidCallback onDeactivate;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final amount = _teamPenaltyNumber(rule['amount']);
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Container(
        padding: const EdgeInsets.all(11),
        decoration: BoxDecoration(
          color: airmiusSurfaceSoftColor(context),
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: airmiusBorderColor(context)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.rule_outlined, color: airmiusAccentColor(context)),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _teamPenaltyText(
                      rule['title'],
                      t('teamDetail.penaltyRule'),
                    ),
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${_teamPenaltyTriggerLabel(_teamPenaltyText(rule['trigger']), t)} · ${_teamPenaltyCalculationLabel(_teamPenaltyText(rule['calculation_type']), t)}${amount > 0 ? ' · ${_teamPenaltyMoney(amount)}' : ''}',
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                ],
              ),
            ),
            if (canManage && _teamPenaltyBool(rule['is_active']))
              IconButton(
                tooltip: t('teamDetail.deactivatePenaltyRule'),
                onPressed: onDeactivate,
                icon: const Icon(Icons.pause_circle_outline),
              ),
          ],
        ),
      ),
    );
  }
}

class _PenaltyFeeRow extends StatelessWidget {
  const _PenaltyFeeRow({
    required this.fee,
    required this.canManage,
    required this.onPaid,
    required this.onCancel,
    required this.onRefund,
  });

  final Map<String, dynamic> fee;
  final bool canManage;
  final VoidCallback onPaid;
  final VoidCallback onCancel;
  final VoidCallback onRefund;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final member = _teamPenaltyMap(fee['member']);
    final status = _teamPenaltyText(fee['status'], 'open');
    final statusLabel = switch (status) {
      'paid' => t('teamDetail.penaltyPaid'),
      'cancelled' => t('teamDetail.penaltyCancelled'),
      _ => t('teamDetail.penaltyOpen'),
    };
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Container(
        padding: const EdgeInsets.all(11),
        decoration: BoxDecoration(
          color: airmiusSurfaceSoftColor(context),
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: airmiusBorderColor(context)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    _teamPenaltyText(member['name'], t('teamDetail.member')),
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
                StatusPill(statusLabel),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              '${_teamPenaltyMoney(fee['amount'])}${_teamPenaltyText(fee['note']).isEmpty ? '' : ' · ${_teamPenaltyText(fee['note'])}'}',
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
            if (canManage && status == 'open') ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                children: [
                  TextButton.icon(
                    onPressed: onPaid,
                    icon: const Icon(Icons.check_circle_outline),
                    label: Text(t('teamDetail.markPenaltyPaid')),
                  ),
                  TextButton.icon(
                    onPressed: onCancel,
                    icon: const Icon(Icons.cancel_outlined),
                    label: Text(t('teamDetail.cancelPenalty')),
                  ),
                ],
              ),
            ],
            if (canManage &&
                status == 'paid' &&
                fee['finance_entry_id'] != null)
              TextButton.icon(
                onPressed: onRefund,
                icon: const Icon(Icons.undo),
                label: Text(financeWorkspaceLabel(context, 'Rückzahlung')),
              ),
          ],
        ),
      ),
    );
  }
}

class _CompetitionPanel extends StatelessWidget {
  const _CompetitionPanel({required this.future, required this.onRetry});

  final Future<JsonMap>? future;
  final VoidCallback onRetry;

  JsonMap _map(dynamic value) => value is JsonMap ? value : const {};
  List<JsonMap> _maps(dynamic value) => value is List
      ? value.whereType<JsonMap>().toList(growable: false)
      : const [];
  num _number(dynamic value) =>
      value is num ? value : num.tryParse('$value') ?? 0;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final request = future;
    if (request == null) {
      return AirmiusPanel(child: Text(t('teamDetail.competition.empty')));
    }

    return FutureBuilder<JsonMap>(
      future: request,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const AirmiusPanel(
            child: Center(child: CircularProgressIndicator()),
          );
        }
        if (snapshot.hasError) {
          return AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  '${t('teamDetail.competition.loadFailed')}: ${_safeTeamError(context, snapshot.error!)}',
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                const SizedBox(height: 12),
                AirmiusButton(
                  label: t('teamDetail.reload'),
                  icon: Icons.refresh_outlined,
                  onPressed: onRetry,
                ),
              ],
            ),
          );
        }

        final data = snapshot.data ?? const <String, dynamic>{};
        final events = _map(data['events']);
        final participation = _map(data['participation']);
        final feeCounts = _map(_map(data['fees'])['counts']);
        final next = _map(events['next']);
        final nextParticipation = _map(next['participation']);
        final season = _map(_map(data['team_organizer'])['season_plan']);
        final missing = _maps(participation['missing_responses']);
        final actions = _maps(data['team_actions']);
        final start = DateTime.tryParse('${next['start_time'] ?? ''}');

        Widget metric(String label, String value) => SizedBox(
          width: 145,
          child: MetricCard(value: value, label: label),
        );

        return AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('teamDetail.competition.title')),
              const SizedBox(height: 6),
              Text(
                t('teamDetail.competition.intro'),
                style: TextStyle(color: airmiusMutedColor(context)),
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  metric(t('teamDetail.roster'), '${_number(data['members'])}'),
                  metric(
                    t('teamDetail.competition.upcoming'),
                    '${_number(events['upcoming'])}',
                  ),
                  metric(
                    t('teamDetail.competition.responseRate'),
                    '${_number(participation['response_rate_30d']).toStringAsFixed(1)}%',
                  ),
                  metric(
                    t('teamDetail.openPenaltyCount'),
                    '${_number(feeCounts['open'])}',
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Text(
                t('teamDetail.competition.nextEvent'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 6),
              if (next.isEmpty)
                Text(
                  t('teamDetail.competition.noNextEvent'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                )
              else ...[
                Text(
                  '${next['title'] ?? ''}',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (start != null)
                  Text(
                    DateFormat.yMMMd(
                      Localizations.localeOf(context).toLanguageTag(),
                    ).add_Hm().format(start.toLocal()),
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                const SizedBox(height: 8),
                Text(
                  '${t('teamDetail.competition.responded')}: ${_number(nextParticipation['responded'])} · '
                  '${t('teamDetail.competition.missing')}: ${_number(nextParticipation['missing'])} · '
                  '${t('teamDetail.competition.attendance')}: ${_number(nextParticipation['attendance_rate']).toStringAsFixed(1)}%',
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
              ],
              const SizedBox(height: 14),
              Text(
                t('teamDetail.competition.missingResponses'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 6),
              if (missing.isEmpty)
                Text(
                  t('teamDetail.competition.allResponded'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                )
              else
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: missing
                      .map(
                        (member) => Chip(
                          label: Text('${member['name'] ?? ''}'),
                          avatar: const Icon(Icons.schedule_outlined, size: 16),
                        ),
                      )
                      .toList(),
                ),
              const SizedBox(height: 14),
              Text(
                t('teamDetail.competition.actions'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 6),
              ...actions.map((action) {
                final key = '${action['key'] ?? ''}';
                final count = _number(action['count']);
                return Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(
                    children: [
                      Icon(
                        Icons.check_circle_outline,
                        size: 18,
                        color: airmiusAccentColor(context),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          '${count > 0 ? '$count · ' : ''}${t('teamDetail.competition.action.$key')}',
                          style: TextStyle(color: airmiusTextColor(context)),
                        ),
                      ),
                    ],
                  ),
                );
              }),
              if (actions.isEmpty)
                Text(
                  t('teamDetail.competition.noActions'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
              const SizedBox(height: 10),
              Text(
                '${t('teamDetail.competition.season')}: '
                '${season['planning_state'] == 'planned' ? t('teamDetail.competition.planned') : t('teamDetail.competition.needsMoreEvents')}',
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${t('teamDetail.sportYear')}: '
                '${_map(season['sport_year_period'])['name'] ?? t('teamDetail.sportYearUnassigned')}',
                style: TextStyle(color: airmiusMutedColor(context)),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _JoinRequestPendingNotice extends StatelessWidget {
  const _JoinRequestPendingNotice();

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusAccentColor(context).withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: airmiusAccentColor(context).withValues(alpha: 0.35),
        ),
      ),
      child: Row(
        children: [
          Icon(
            Icons.hourglass_top_outlined,
            color: airmiusAccentColor(context),
            size: 20,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              t('teamDetail.joinPending'),
              style: TextStyle(
                color: airmiusAccentColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfilePanel extends StatelessWidget {
  const _ProfilePanel({
    required this.team,
    required this.fallbackTitle,
    required this.canManageTeam,
    required this.joinRequests,
    required this.teamChat,
    required this.guardianGate,
    required this.onSectionSelected,
    required this.onJoin,
    required this.onChat,
    required this.onGuardian,
    required this.onUpdated,
  });

  final AirmiusTeam? team;
  final String fallbackTitle;
  final bool canManageTeam;
  final bool joinRequests;
  final bool teamChat;
  final bool guardianGate;
  final ValueChanged<String> onSectionSelected;
  final ValueChanged<bool> onJoin;
  final ValueChanged<bool> onChat;
  final ValueChanged<bool> onGuardian;
  final ValueChanged<AirmiusTeam> onUpdated;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('teamDetail.teamData')),
          const SizedBox(height: 12),
          _TeamInfoGrid(
            rows: [
              _TeamInfoData(
                label: t('teamDetail.teamName'),
                value: team?.name ?? fallbackTitle,
                icon: Icons.groups_2_outlined,
              ),
              _TeamInfoData(
                label: t('teamDetail.club'),
                value: team?.clubName ?? t('teamDetail.notProvided'),
                icon: Icons.shield_outlined,
              ),
              _TeamInfoData(
                label: t('teamDetail.sport'),
                value: team?.sportType ?? t('teamDetail.notProvided'),
                icon: Icons.sports_soccer_outlined,
              ),
              _TeamInfoData(
                label: t('teamDetail.ageGroup'),
                value: team?.ageGroup ?? t('teamDetail.notProvided'),
                icon: Icons.group_outlined,
              ),
              _TeamInfoData(
                label: t('teamDetail.visibility'),
                value: team?.visibility ?? t('teamDetail.teamspace'),
                icon: Icons.visibility_outlined,
              ),
              _TeamInfoData(
                label: t('teamDetail.description'),
                value: team?.description ?? t('teamDetail.noDescription'),
                icon: Icons.notes_outlined,
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (team?.attendanceStats != null) ...[
            _AttendanceStatsPanel(stats: team!.attendanceStats!),
            const SizedBox(height: 12),
          ],
          if (canManageTeam && team != null) ...[
            _TeamEditSection(team: team!, onUpdated: onUpdated),
            const SizedBox(height: 12),
          ],
          if (canManageTeam) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                AirmiusButton(
                  label: t('teamDetail.tab.invites'),
                  icon: Icons.mail_outline,
                  secondary: true,
                  onPressed: () => onSectionSelected('Einladen'),
                ),
                AirmiusButton(
                  label: t('teamDetail.tab.penalties'),
                  icon: Icons.gavel_outlined,
                  secondary: true,
                  onPressed: () => onSectionSelected('Strafen'),
                ),
                AirmiusButton(
                  label: t('teamDetail.tab.chat'),
                  icon: Icons.chat_outlined,
                  secondary: true,
                  onPressed: () => onSectionSelected('Chat'),
                ),
                AirmiusButton(
                  label: t('teamDetail.settings'),
                  icon: Icons.settings_outlined,
                  secondary: true,
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const TeamOperationsScreen(),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Eyebrow(t('teamDetail.mobileFunctions')),
            const SizedBox(height: 8),
            Material(
              type: MaterialType.transparency,
              child: SwitchListTile(
                value: joinRequests,
                onChanged: onJoin,
                activeThumbColor: airmiusAccentColor(context),
                contentPadding: EdgeInsets.zero,
                title: Text(
                  t('teamDetail.allowJoinRequests'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                subtitle: Text(
                  t('teamDetail.allowJoinRequestsBody'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
              ),
            ),
            Material(
              type: MaterialType.transparency,
              child: SwitchListTile(
                value: teamChat,
                onChanged: onChat,
                activeThumbColor: airmiusAccentColor(context),
                contentPadding: EdgeInsets.zero,
                title: Text(
                  t('teamDetail.teamChatActive'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                subtitle: Text(
                  t('teamDetail.teamChatBody'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
              ),
            ),
            Material(
              type: MaterialType.transparency,
              child: SwitchListTile(
                value: guardianGate,
                onChanged: onGuardian,
                activeThumbColor: airmiusAccentColor(context),
                contentPadding: EdgeInsets.zero,
                title: Text(
                  t('teamDetail.guardianCheck'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                subtitle: Text(
                  t('teamDetail.guardianCheckBody'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _TeamEditSection extends StatefulWidget {
  const _TeamEditSection({required this.team, required this.onUpdated});

  final AirmiusTeam team;
  final ValueChanged<AirmiusTeam> onUpdated;

  @override
  State<_TeamEditSection> createState() => _TeamEditSectionState();
}

class _TeamEditSectionState extends State<_TeamEditSection> {
  bool _editing = false;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusButton(
          label: _editing
              ? t('teamDetail.closeEditing')
              : t('teamDetail.editData'),
          icon: _editing ? Icons.close_outlined : Icons.edit_outlined,
          secondary: true,
          onPressed: () => setState(() => _editing = !_editing),
        ),
        if (_editing) ...[
          const SizedBox(height: 10),
          _TeamEditPanel(
            team: widget.team,
            onUpdated: (team) {
              widget.onUpdated(team);
              if (mounted) setState(() => _editing = false);
            },
          ),
        ],
      ],
    );
  }
}

class _TeamEditPanel extends StatefulWidget {
  const _TeamEditPanel({required this.team, required this.onUpdated});

  final AirmiusTeam team;
  final ValueChanged<AirmiusTeam> onUpdated;

  @override
  State<_TeamEditPanel> createState() => _TeamEditPanelState();
}

class _TeamEditPanelState extends State<_TeamEditPanel> {
  late final TextEditingController _nameController;
  late final TextEditingController _sportController;
  Future<List<AirmiusSport>>? _sportsFuture;
  Future<List<JsonMap>>? _sportYearsFuture;
  String? _selectedSportSlug;
  int? _selectedSportYearId;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.team.name);
    _sportController = TextEditingController(text: widget.team.sportType ?? '');
    _selectedSportSlug = widget.team.sportType;
    _selectedSportYearId = widget.team.sportYearPeriodId;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _sportsFuture ??= AirmiusServicesScope.of(
      context,
    ).repositories.sports.sports().then((page) => page.items);
    _sportYearsFuture ??= _loadSportYears();
  }

  Future<List<JsonMap>> _loadSportYears() async {
    final services = AirmiusServicesScope.of(context);
    final response = await services
        .clientForSession(services.authState.session)
        .clubYearPeriods(widget.team.clubId);
    final data = response['data'];
    final periods = data is JsonMap ? data['periods'] : null;
    return periods is List
        ? periods
              .whereType<JsonMap>()
              .where((period) => period['type'] == 'sport')
              .toList()
        : const [];
  }

  @override
  void dispose() {
    _nameController.dispose();
    _sportController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final name = _nameController.text.trim();
    final sportType = (_selectedSportSlug ?? _sportController.text).trim();
    if (name.isEmpty || _saving) return;

    setState(() => _saving = true);
    try {
      final updatedTeam = await AirmiusServicesScope.of(context)
          .repositories
          .clubs
          .updateTeam(widget.team.id, {
            'name': name,
            'sport_type': sportType.isEmpty ? null : sportType,
            'sport_year_period_id': _selectedSportYearId,
          });
      if (!mounted) return;
      widget.onUpdated(updatedTeam);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('teamDetail.dataSaved')),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      final message = _safeTeamError(context, error);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${AirmiusScope.of(context).t('teamDetail.dataSaveFailed')}: $message',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('teamDetail.editData')),
          const SizedBox(height: 10),
          TextField(
            controller: _nameController,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
            decoration: InputDecoration(labelText: t('teamDetail.teamName')),
          ),
          const SizedBox(height: 10),
          FutureBuilder<List<AirmiusSport>>(
            future: _sportsFuture,
            builder: (context, snapshot) {
              final sports = snapshot.data ?? const <AirmiusSport>[];
              return _TeamSportField(
                controller: _sportController,
                sports: sports,
                loading: snapshot.connectionState == ConnectionState.waiting,
                onTextChanged: () => _selectedSportSlug = null,
                onSelected: (sport) {
                  _sportController.text = sport.name;
                  _selectedSportSlug = sport.slug;
                },
              );
            },
          ),
          const SizedBox(height: 10),
          FutureBuilder<List<JsonMap>>(
            future: _sportYearsFuture,
            builder: (context, snapshot) {
              final periods = snapshot.data ?? const <JsonMap>[];
              final hasSelectedPeriod = periods.any(
                (period) =>
                    int.tryParse('${period['id'] ?? ''}') ==
                    _selectedSportYearId,
              );
              return DropdownButtonFormField<int?>(
                initialValue: _selectedSportYearId,
                decoration: InputDecoration(
                  labelText: t('teamDetail.sportYear'),
                  helperText: t('teamDetail.sportYearHint'),
                ),
                items: [
                  DropdownMenuItem<int?>(
                    value: null,
                    child: Text(t('teamDetail.sportYearUnassigned')),
                  ),
                  if (_selectedSportYearId != null && !hasSelectedPeriod)
                    DropdownMenuItem<int?>(
                      value: _selectedSportYearId,
                      child: Text(
                        widget.team.sportYearPeriodName ??
                            t('teamDetail.sportsLoading'),
                      ),
                    ),
                  ...periods.map(
                    (period) => DropdownMenuItem<int?>(
                      value: int.tryParse('${period['id'] ?? ''}'),
                      child: Text('${period['name'] ?? ''}'),
                    ),
                  ),
                ],
                onChanged: snapshot.connectionState == ConnectionState.waiting
                    ? null
                    : (value) => setState(() => _selectedSportYearId = value),
              );
            },
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: _saving ? t('teamDetail.saving') : t('teamDetail.saveData'),
            icon: Icons.save_outlined,
            onPressed: _saving ? null : _save,
          ),
        ],
      ),
    );
  }
}

class _TeamSportField extends StatelessWidget {
  const _TeamSportField({
    required this.controller,
    required this.sports,
    required this.loading,
    required this.onTextChanged,
    required this.onSelected,
  });

  final TextEditingController controller;
  final List<AirmiusSport> sports;
  final bool loading;
  final VoidCallback onTextChanged;
  final ValueChanged<AirmiusSport> onSelected;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    if (sports.isEmpty) {
      return TextField(
        controller: controller,
        style: TextStyle(
          color: airmiusTextColor(context),
          fontWeight: FontWeight.w900,
        ),
        decoration: InputDecoration(
          labelText: t('teamDetail.sport'),
          hintText: loading
              ? t('teamDetail.sportsLoading')
              : t('teamDetail.searchSport'),
        ),
        onChanged: (_) => onTextChanged(),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          t('teamDetail.sport'),
          style: TextStyle(
            color: airmiusTextColor(context),
            fontSize: 13,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 6),
        Autocomplete<AirmiusSport>(
          initialValue: TextEditingValue(text: controller.text),
          displayStringForOption: (sport) => sport.name,
          optionsBuilder: (value) {
            final query = value.text.trim().toLowerCase();
            final options = query.isEmpty
                ? sports
                : sports.where((sport) {
                    final name = sport.name.toLowerCase();
                    final slug = sport.slug.toLowerCase();
                    return name.contains(query) || slug.contains(query);
                  });
            return options.take(10);
          },
          onSelected: onSelected,
          fieldViewBuilder:
              (context, textController, focusNode, onFieldSubmitted) {
                if (textController.text.isEmpty && controller.text.isNotEmpty) {
                  textController.text = controller.text;
                }
                return TextField(
                  controller: textController,
                  focusNode: focusNode,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                  decoration: InputDecoration(
                    hintText: t('teamDetail.searchSport'),
                    suffixIcon: Icon(
                      Icons.search,
                      color: airmiusMutedColor(context),
                    ),
                  ),
                  onChanged: (value) {
                    controller.text = value;
                    onTextChanged();
                  },
                );
              },
        ),
      ],
    );
  }
}

class _AttendanceStatsPanel extends StatelessWidget {
  const _AttendanceStatsPanel({required this.stats});

  final AirmiusTeamAttendanceStats stats;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final members = stats.members.take(8).toList();
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Eyebrow(t('teamDetail.trainingAttendance'))),
              StatusPill(
                '${stats.trainingsTotal} ${t('teamDetail.trainings')}',
                color: airmiusAccentColor(context),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (members.isEmpty)
            Text(
              t('teamDetail.noAttendance'),
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
              ),
            )
          else
            ...members.map(
              (member) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            member.name,
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        Text(
                          '${member.attendanceRate.toStringAsFixed(1)}%',
                          style: TextStyle(
                            color: AirmiusColors.green,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 5),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(99),
                      child: LinearProgressIndicator(
                        value: (member.attendanceRate / 100)
                            .clamp(0, 1)
                            .toDouble(),
                        minHeight: 7,
                        color: airmiusAccentColor(context),
                        backgroundColor: airmiusSurfaceColor(context),
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      '${t('teamDetail.attendance.yes')} ${member.yes} · '
                      '${t('teamDetail.attendance.late')} ${member.late} · '
                      '${t('teamDetail.attendance.no')} ${member.no} · '
                      '${t('teamDetail.attendance.noResponse')} ${member.noResponse}',
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _TeamInfoData {
  const _TeamInfoData({
    required this.label,
    required this.value,
    required this.icon,
  });

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
            for (final row in rows)
              SizedBox(
                width: width,
                child: _TeamInfoTile(row: row),
              ),
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
      constraints: const BoxConstraints(minHeight: 78),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Icon(row.icon, color: airmiusAccentColor(context), size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  row.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  row.value,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                    height: 1.2,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RosterPanel extends StatelessWidget {
  const _RosterPanel({
    required this.team,
    required this.canManageTeam,
    required this.updatingUserId,
    required this.onRoleChanged,
  });

  final AirmiusTeam? team;
  final bool canManageTeam;
  final int? updatingUserId;
  final void Function(AirmiusUser user, String role)? onRoleChanged;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final users = team?.users ?? const <AirmiusUser>[];
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('teamDetail.roster')),
          const SizedBox(height: 12),
          if (users.isEmpty)
            Text(
              t('teamDetail.noMembers'),
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
              ),
            )
          else
            for (final user in users) ...[
              _MemberRow(
                user: user,
                canManageTeam: canManageTeam,
                isUpdating: updatingUserId == user.id,
                onRoleChanged: onRoleChanged == null
                    ? null
                    : (role) => onRoleChanged!(user, role),
              ),
              const SizedBox(height: 10),
            ],
        ],
      ),
    );
  }
}

class _RolesPanel extends StatelessWidget {
  const _RolesPanel();

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('teamDetail.teamRoles')),
          const SizedBox(height: 12),
          for (final role in _teamRoleValues) ...[
            _RoleRow(
              role: _teamRoleLabel(role, t),
              rights: _teamRoleDescription(role, t),
            ),
            if (role != _teamRoleValues.last) const SizedBox(height: 10),
          ],
        ],
      ),
    );
  }
}

class _InvitePanel extends StatefulWidget {
  const _InvitePanel({
    required this.team,
    required this.isReviewing,
    required this.onApprove,
    required this.onDecline,
  });

  final AirmiusTeam? team;
  final bool isReviewing;
  final ValueChanged<AirmiusTeamJoinRequest>? onApprove;
  final ValueChanged<AirmiusTeamJoinRequest>? onDecline;

  @override
  State<_InvitePanel> createState() => _InvitePanelState();
}

class _InvitePanelState extends State<_InvitePanel> {
  final TextEditingController _emailController = TextEditingController();
  String _role = 'Player';
  bool _sending = false;

  String _tr(String key) => AirmiusScope.of(context).t(key);

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _sendInvitation() async {
    final team = widget.team;
    final email = _emailController.text.trim();
    if (team == null || email.isEmpty || _sending) return;

    setState(() => _sending = true);
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.inviteTeamMember(team.id, email: email, role: _role);
      if (!mounted) return;
      _emailController.clear();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${_tr('teamDetail.invitationSentAs')} '
            '${_teamRoleLabel(_role, _tr)}.',
          ),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      final message = _safeTeamError(context, error);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${_tr('teamDetail.invitationFailed')}: $message'),
        ),
      );
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final team = widget.team;
    final requests =
        widget.team?.pendingJoinRequests
            .where((request) => request.status == 'pending')
            .toList() ??
        const <AirmiusTeamJoinRequest>[];
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Eyebrow(t('teamDetail.openRequests'))),
              StatusPill(
                '${requests.length} ${t('teamDetail.openAfter')}',
                color: requests.isEmpty
                    ? AirmiusColors.green
                    : AirmiusColors.amber,
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (requests.isEmpty)
            Text(
              t('teamDetail.noOpenRequests'),
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
              ),
            )
          else
            for (final request in requests) ...[
              _TeamJoinRequestCard(
                request: request,
                isBusy: widget.isReviewing,
                onApprove: widget.onApprove == null
                    ? null
                    : () => widget.onApprove!(request),
                onDecline: widget.onDecline == null
                    ? null
                    : () => widget.onDecline!(request),
              ),
              const SizedBox(height: 10),
            ],
          const SizedBox(height: 10),
          Divider(color: airmiusBorderColor(context)),
          const SizedBox(height: 10),
          if (team?.memberInvitationRemainingToday != null &&
              team?.memberInvitationDailyLimit != null) ...[
            StatusPill(
              '${t('teamDetail.invitesRemaining')}: ${team!.memberInvitationRemainingToday} / ${team.memberInvitationDailyLimit}',
              color: airmiusAccentColor(context),
            ),
            const SizedBox(height: 10),
          ],
          Eyebrow(t('teamDetail.sendInvitation')),
          const SizedBox(height: 12),
          TextField(
            controller: _emailController,
            keyboardType: TextInputType.emailAddress,
            enabled: !_sending && widget.team != null,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
            decoration: InputDecoration(
              labelText: t('teamDetail.email'),
              hintText: 'mitglied@example.com',
              prefixIcon: Icon(Icons.mail_outline),
            ),
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: _role,
            isExpanded: true,
            dropdownColor: airmiusSurfaceColor(context),
            decoration: InputDecoration(
              labelText: t('teamDetail.role'),
              labelStyle: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w800,
              ),
              prefixIcon: Icon(Icons.admin_panel_settings_outlined),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: airmiusBorderColor(context)),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: airmiusAccentColor(context)),
              ),
              filled: true,
              fillColor: airmiusSurfaceSoftColor(context),
            ),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
            items: [
              for (final item in _teamRoleValues)
                DropdownMenuItem(
                  value: item,
                  child: Text(_teamRoleLabel(item, t)),
                ),
            ],
            onChanged: _sending
                ? null
                : (value) => setState(() => _role = value ?? 'Player'),
          ),
          const SizedBox(height: 10),
          AirmiusButton(
            label: _sending
                ? t('teamDetail.invitationSending')
                : t('teamDetail.sendInvitation'),
            icon: Icons.send_outlined,
            onPressed: _sending || widget.team == null ? null : _sendInvitation,
          ),
          const SizedBox(height: 10),
          Text(
            t('teamDetail.invitationExplanation'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
        ],
      ),
    );
  }
}

class _TeamJoinRequestCard extends StatelessWidget {
  const _TeamJoinRequestCard({
    required this.request,
    required this.isBusy,
    required this.onApprove,
    required this.onDecline,
  });

  final AirmiusTeamJoinRequest request;
  final bool isBusy;
  final VoidCallback? onApprove;
  final VoidCallback? onDecline;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              AirmiusAvatar(request.name),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      request.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      request.email.isEmpty
                          ? t('teamDetail.noEmail')
                          : request.email,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(_teamRoleLabel(request.roleHint ?? 'Player', t)),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(
                label: isBusy ? t('teamDetail.saving') : t('teamDetail.accept'),
                icon: Icons.check_circle_outline,
                onPressed: isBusy ? null : onApprove,
              ),
              AirmiusButton(
                label: t('teamDetail.decline'),
                icon: Icons.cancel_outlined,
                danger: true,
                secondary: true,
                onPressed: isBusy ? null : onDecline,
              ),
            ],
          ),
        ],
      ),
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
      Text('Einladungstoken, Ablaufdatum und Guardian-Prüfung werden später über die API erzeugt.', style: TextStyle(color: airmiusMutedColor(context), height: 1.35)),
    ]));
  }
}

*/
class _CalendarPanel extends StatefulWidget {
  const _CalendarPanel({this.team});

  final AirmiusTeam? team;

  @override
  State<_CalendarPanel> createState() => _CalendarPanelState();
}

class _CalendarPanelState extends State<_CalendarPanel> {
  Future<AirmiusEventWorkspace>? _workspaceFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_workspaceFuture == null && widget.team != null) {
      _workspaceFuture = _loadWorkspace();
    }
  }

  Future<AirmiusEventWorkspace> _loadWorkspace() {
    return AirmiusServicesScope.of(context).repositories.events.workspace(
      teamId: widget.team!.id,
      period: 'upcoming',
    );
  }

  void _reload() {
    setState(() => _workspaceFuture = _loadWorkspace());
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final team = widget.team;
    if (team == null) {
      return _calendarFallback(context, t);
    }

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('teamDetail.teamCalendar')),
          const SizedBox(height: 12),
          FutureBuilder<AirmiusEventWorkspace>(
            future: _workspaceFuture,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 18),
                  child: Center(
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: airmiusAccentColor(context),
                    ),
                  ),
                );
              }
              if (snapshot.hasError) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      t('teamDetail.calendarLoadFailed'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 10),
                    AirmiusButton(
                      label: t('teamDetail.reload'),
                      icon: Icons.refresh_outlined,
                      secondary: true,
                      onPressed: _reload,
                    ),
                  ],
                );
              }
              final workspace = snapshot.data;
              final events =
                  [
                      ...(workspace?.calendarEvents ?? const <AirmiusEvent>[]),
                      ...(workspace?.events ?? const <AirmiusEvent>[]),
                    ].fold<List<AirmiusEvent>>([], (items, event) {
                      if (items.every((item) => item.id != event.id)) {
                        items.add(event);
                      }
                      return items;
                    })
                    ..sort((a, b) => a.startsAt.compareTo(b.startsAt));
              final visibleEvents = events.take(6).toList();
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (visibleEvents.isEmpty)
                    Text(
                      t('teamDetail.noEvents'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.4,
                      ),
                    )
                  else
                    ...visibleEvents.map(
                      (event) => _TeamCalendarEventTile(event: event),
                    ),
                  if (events.length > visibleEvents.length) ...[
                    const SizedBox(height: 4),
                    Text(
                      t('teamDetail.moreEvents'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontSize: 12,
                      ),
                    ),
                  ],
                ],
              );
            },
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('teamDetail.openCalendar'),
            icon: Icons.event_outlined,
            secondary: true,
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const EventManagementScreen()),
            ),
          ),
        ],
      ),
    );
  }

  Widget _calendarFallback(BuildContext context, String Function(String) t) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('teamDetail.teamCalendar')),
          const SizedBox(height: 12),
          Text(
            t('teamDetail.noEvents'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('teamDetail.openCalendar'),
            icon: Icons.event_outlined,
            secondary: true,
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const EventManagementScreen()),
            ),
          ),
        ],
      ),
    );
  }
}

class _TeamCalendarEventTile extends StatelessWidget {
  const _TeamCalendarEventTile({required this.event});

  final AirmiusEvent event;

  @override
  Widget build(BuildContext context) {
    final locale = Localizations.localeOf(context).toLanguageTag();
    final date = DateFormat.yMMMd(
      locale,
    ).add_Hm().format(event.startsAt.toLocal());
    final location = [
      event.locationName,
      event.locationCity,
    ].whereType<String>().where((value) => value.trim().isNotEmpty).join(' · ');
    final subtitle = [date, if (location.isNotEmpty) location].join(' · ');
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Semantics(
        button: true,
        label: '${event.title}, $subtitle',
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: () => _openTeamEvent(context, event),
            child: Ink(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: airmiusSurfaceColor(context).withValues(alpha: 0.55),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: airmiusBorderColor(context)),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.event_available_outlined,
                    color: airmiusAccentColor(context),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          event.title,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          subtitle,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

void _openTeamEvent(BuildContext context, AirmiusEvent event) {
  final fallbackBody = [
    DateFormat.yMMMd(
      Localizations.localeOf(context).toLanguageTag(),
    ).add_Hm().format(event.startsAt.toLocal()),
    event.location,
    event.teamName,
  ].whereType<String>().where((value) => value.isNotEmpty).join(' · ');
  Navigator.push(
    context,
    MaterialPageRoute(
      builder: (_) => TrainingEventDetailScreen(
        event: event,
        fallbackBody: event.notes?.isNotEmpty == true
            ? event.notes!
            : fallbackBody,
      ),
    ),
  );
}

class _TeamFilesPanel extends StatefulWidget {
  const _TeamFilesPanel({required this.team});

  final AirmiusTeam team;

  @override
  State<_TeamFilesPanel> createState() => _TeamFilesPanelState();
}

class _TeamFilesPanelState extends State<_TeamFilesPanel> {
  Future<AirmiusFileWorkspace>? _workspaceFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _workspaceFuture ??= _loadWorkspace();
  }

  Future<AirmiusFileWorkspace> _loadWorkspace() {
    return AirmiusServicesScope.of(
      context,
    ).repositories.files.workspace(scope: 'team', teamId: widget.team.id);
  }

  void _reload() {
    setState(() => _workspaceFuture = _loadWorkspace());
  }

  void _openFile(AirmiusManagedFile file) {
    final t = AirmiusScope.of(context).t;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => FilePreviewScreen(
          title: file.name,
          body: file.type.isEmpty ? t('files.backendPreview') : file.type,
          status: t('files.backend'),
          icon: _teamFileIcon(file.type),
          fileId: file.id,
          fileMeta: file.type.isEmpty ? t('files') : file.type,
          fileUrl: file.url.isEmpty ? null : file.url,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('teamDetail.teamFiles')),
          const SizedBox(height: 12),
          FutureBuilder<AirmiusFileWorkspace>(
            future: _workspaceFuture,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 18),
                  child: Center(
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: airmiusAccentColor(context),
                    ),
                  ),
                );
              }
              if (snapshot.hasError) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      t('teamDetail.filesLoadFailed'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 10),
                    AirmiusButton(
                      label: t('teamDetail.reload'),
                      icon: Icons.refresh_outlined,
                      secondary: true,
                      onPressed: _reload,
                    ),
                  ],
                );
              }

              final workspace = snapshot.data;
              final files = workspace?.files ?? const <AirmiusManagedFile>[];
              final folders = workspace?.folders ?? const <AirmiusFolder>[];
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (folders.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: Text(
                        '${t('teamDetail.teamFolderCount')}: ${folders.length}',
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontSize: 12,
                        ),
                      ),
                    ),
                  if (files.isEmpty)
                    Text(
                      t('teamDetail.noFiles'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.4,
                      ),
                    )
                  else
                    ...files
                        .take(6)
                        .map(
                          (file) => _TeamFileTile(
                            file: file,
                            onTap: () => _openFile(file),
                          ),
                        ),
                  if (files.length > 6)
                    Text(
                      t('teamDetail.moreFiles'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontSize: 12,
                      ),
                    ),
                ],
              );
            },
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('teamDetail.openFiles'),
            icon: Icons.folder_open_outlined,
            secondary: true,
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => FileManagerScreen(
                  initialScope: 'team',
                  initialTeamId: widget.team.id,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _TeamFileTile extends StatelessWidget {
  const _TeamFileTile({required this.file, required this.onTap});

  final AirmiusManagedFile file;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final meta = [
      if (file.type.trim().isNotEmpty) file.type,
      if (file.size > 0) _teamFileSize(file.size),
    ].join(' · ');
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Semantics(
        button: true,
        label: '${file.name}${meta.isEmpty ? '' : ', $meta'}',
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: onTap,
            child: Ink(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: airmiusSurfaceColor(context).withValues(alpha: 0.55),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: airmiusBorderColor(context)),
              ),
              child: Row(
                children: [
                  Icon(
                    _teamFileIcon(file.type),
                    color: airmiusAccentColor(context),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          file.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        if (meta.isNotEmpty) ...[
                          const SizedBox(height: 3),
                          Text(
                            meta,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: airmiusMutedColor(context),
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

IconData _teamFileIcon(String type) {
  final normalized = type.toLowerCase();
  if (normalized.contains('pdf')) return Icons.picture_as_pdf_outlined;
  if (normalized.contains('image') ||
      normalized.contains('png') ||
      normalized.contains('jpg') ||
      normalized.contains('jpeg')) {
    return Icons.image_outlined;
  }
  if (normalized.contains('video')) return Icons.video_file_outlined;
  if (normalized.contains('audio')) return Icons.audio_file_outlined;
  if (normalized.contains('zip') || normalized.contains('archive')) {
    return Icons.folder_zip_outlined;
  }
  return Icons.insert_drive_file_outlined;
}

String _teamFileSize(int bytes) {
  if (bytes < 1024) return '$bytes B';
  final kb = bytes / 1024;
  if (kb < 1024) return '${kb.toStringAsFixed(1)} KB';
  final mb = kb / 1024;
  if (mb < 1024) return '${mb.toStringAsFixed(1)} MB';
  return '${(mb / 1024).toStringAsFixed(1)} GB';
}

class _FilesPanel extends StatelessWidget {
  const _FilesPanel();

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('teamDetail.teamFiles')),
          const SizedBox(height: 12),
          Text(
            t('teamDetail.noFiles'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('teamDetail.openFiles'),
            icon: Icons.folder_open_outlined,
            secondary: true,
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const FileManagerScreen()),
            ),
          ),
        ],
      ),
    );
  }
}

class _TeamChatPanel extends StatefulWidget {
  const _TeamChatPanel({required this.team});

  final AirmiusTeam team;

  @override
  State<_TeamChatPanel> createState() => _TeamChatPanelState();
}

class _TeamChatPanelState extends State<_TeamChatPanel> {
  Future<List<AirmiusConversation>>? _conversationsFuture;
  bool _creating = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _conversationsFuture ??= _loadConversations();
  }

  Future<List<AirmiusConversation>> _loadConversations() async {
    final page = await AirmiusServicesScope.of(
      context,
    ).repositories.conversations.conversations(teamId: widget.team.id);
    return page.items.where((conversation) {
      return conversation.teamId == widget.team.id;
    }).toList();
  }

  void _reload() {
    setState(() => _conversationsFuture = _loadConversations());
  }

  Future<void> _createTeamChat() async {
    if (_creating) return;
    setState(() => _creating = true);
    try {
      final conversation = await AirmiusServicesScope.of(context)
          .repositories
          .conversations
          .createConversation(type: 'team', teamId: widget.team.id);
      if (!mounted) return;
      setState(() => _creating = false);
      _openConversation(conversation);
    } catch (_) {
      if (!mounted) return;
      setState(() => _creating = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            AirmiusScope.of(context).t('teamDetail.chatCreateFailed'),
          ),
        ),
      );
    }
  }

  void _openConversation(AirmiusConversation conversation) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ChatDetailScreen(
          conversationId: conversation.id,
          title: conversation.title.isEmpty
              ? AirmiusScope.of(context).t('teamDetail.teamChat')
              : conversation.title,
          kind: conversation.kind,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('teamDetail.teamChat')),
          const SizedBox(height: 12),
          FutureBuilder<List<AirmiusConversation>>(
            future: _conversationsFuture,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting ||
                  _creating) {
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 18),
                  child: Center(
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: airmiusAccentColor(context),
                    ),
                  ),
                );
              }
              if (snapshot.hasError) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      t('teamDetail.chatLoadFailed'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 10),
                    AirmiusButton(
                      label: t('teamDetail.reload'),
                      icon: Icons.refresh_outlined,
                      secondary: true,
                      onPressed: _reload,
                    ),
                  ],
                );
              }
              final conversations = snapshot.data ?? const [];
              if (conversations.isEmpty) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      t('teamDetail.noChat'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.4,
                      ),
                    ),
                    if (widget.team.viewerIsMember ||
                        widget.team.canManage) ...[
                      const SizedBox(height: 10),
                      AirmiusButton(
                        label: _creating
                            ? t('teamDetail.chatOpening')
                            : t('teamDetail.createChat'),
                        icon: Icons.add_comment_outlined,
                        onPressed: _creating ? null : _createTeamChat,
                      ),
                    ],
                  ],
                );
              }
              final conversation = conversations.first;
              return _TeamConversationTile(
                conversation: conversation,
                onTap: () => _openConversation(conversation),
              );
            },
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('teamDetail.openChat'),
            icon: Icons.chat_bubble_outline,
            secondary: true,
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => const ConversationsCenterScreen(),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _TeamConversationTile extends StatelessWidget {
  const _TeamConversationTile({
    required this.conversation,
    required this.onTap,
  });

  final AirmiusConversation conversation;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final memberLabel = conversation.membersCount == null
        ? null
        : '${conversation.membersCount} ${t('teamDetail.chatMembers')}';
    final meta = [
      ?memberLabel,
      if (conversation.lastMessage.trim().isNotEmpty) conversation.lastMessage,
    ].join(' · ');
    return Semantics(
      button: true,
      label: conversation.title,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: onTap,
          child: Ink(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: airmiusSurfaceColor(context).withValues(alpha: 0.55),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: airmiusBorderColor(context)),
            ),
            child: Row(
              children: [
                Icon(Icons.forum_outlined, color: airmiusAccentColor(context)),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        conversation.title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      if (meta.isNotEmpty) ...[
                        const SizedBox(height: 3),
                        Text(
                          meta,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ChatPanel extends StatelessWidget {
  const _ChatPanel();

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('teamDetail.teamChat')),
          const SizedBox(height: 12),
          Text(
            t('teamDetail.noChat'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
          ),
          const SizedBox(height: 10),
          AirmiusButton(
            label: t('teamDetail.openChat'),
            icon: Icons.chat_bubble_outline,
            secondary: true,
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => const ConversationsCenterScreen(),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MemberRow extends StatelessWidget {
  const _MemberRow({
    required this.user,
    required this.canManageTeam,
    required this.isUpdating,
    required this.onRoleChanged,
  });

  final AirmiusUser user;
  final bool canManageTeam;
  final bool isUpdating;
  final ValueChanged<String>? onRoleChanged;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final role = _teamRoleValues.contains(user.role) ? user.role : 'Player';
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              AirmiusAvatar(user.name, imageUrl: user.avatarUrl),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user.name,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      user.email.isNotEmpty
                          ? user.email
                          : _teamRoleLabel(role, t),
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  StatusPill(
                    _teamRoleLabel(role, t),
                    color: role == 'Coach'
                        ? airmiusAccentColor(context)
                        : role == 'Captain'
                        ? AirmiusColors.green
                        : AirmiusColors.amber,
                  ),
                  if (canManageTeam && onRoleChanged != null)
                    PopupMenuButton<String>(
                      enabled: !isUpdating,
                      tooltip: t('teamDetail.teamRole'),
                      onSelected: onRoleChanged,
                      itemBuilder: (_) => [
                        for (final item in _teamRoleValues)
                          PopupMenuItem(
                            value: item,
                            child: Text(_teamRoleLabel(item, t)),
                          ),
                      ],
                      child: Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              isUpdating
                                  ? Icons.sync
                                  : Icons.manage_accounts_outlined,
                              size: 18,
                              color: Theme.of(context).colorScheme.primary,
                            ),
                            const SizedBox(width: 5),
                            Text(
                              isUpdating
                                  ? t('teamDetail.saving')
                                  : t('teamDetail.changeRole'),
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.primary,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}

const _teamRoleValues = [
  'Coach',
  'Captain',
  'Player',
  'Treasurer',
  'ClubPresident',
  'ParentContact',
];

String _teamRoleLabel(String role, String Function(String) t) => switch (role) {
  'Coach' => t('teamDetail.role.coach'),
  'Captain' => t('teamDetail.role.captain'),
  'Player' => t('teamDetail.role.player'),
  'Treasurer' => t('teamDetail.role.treasurer'),
  'ClubPresident' => t('teamDetail.role.president'),
  'ParentContact' => t('teamDetail.role.parentContact'),
  _ => role,
};

String _teamRoleDescription(String role, String Function(String) t) =>
    switch (role) {
      'Coach' => t('teamDetail.role.coachRights'),
      'Captain' => t('teamDetail.role.captainRights'),
      'Player' => t('teamDetail.role.playerRights'),
      'Treasurer' => t('teamDetail.role.treasurerRights'),
      'ClubPresident' => t('teamDetail.role.presidentRights'),
      'ParentContact' => t('teamDetail.role.parentContactRights'),
      _ => role,
    };

class _RoleRow extends StatelessWidget {
  const _RoleRow({required this.role, required this.rights});

  final String role;
  final String rights;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            role,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            rights,
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
        ],
      ),
    );
  }
}
