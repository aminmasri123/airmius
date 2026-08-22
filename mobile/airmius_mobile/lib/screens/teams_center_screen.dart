import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'team_detail_screen.dart';
import 'team_operations_screen.dart';
import 'clubs_screen.dart';

class TeamsCenterScreen extends StatefulWidget {
  const TeamsCenterScreen({super.key});

  @override
  State<TeamsCenterScreen> createState() => _TeamsCenterScreenState();
}

class _TeamsCenterScreenState extends State<TeamsCenterScreen> {
  String _tab = 'roster';
  Future<AirmiusPage<AirmiusTeam>>? _teamsFuture;
  final List<AirmiusTeam> _createdTeams = <AirmiusTeam>[];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _teamsFuture ??= AirmiusServicesScope.of(
      context,
    ).repositories.clubs.teams();
  }

  void _reloadTeams() {
    setState(() {
      _teamsFuture = AirmiusServicesScope.of(
        context,
      ).repositories.clubs.teams();
    });
  }

  Future<void> _createTeam() async {
    final team = await showDialog<AirmiusTeam>(
      context: context,
      builder: (_) => const _CreateTeamDialog(),
    );
    if (team == null || !mounted) return;
    setState(() {
      _createdTeams
        ..removeWhere((item) => item.id == team.id)
        ..insert(0, team);
      _tab = 'roster';
    });
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(AirmiusScope.of(context).t('clubs.teamCreated'))),
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            Theme.of(context).colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('teamsCenter.teams'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: FutureBuilder<AirmiusPage<AirmiusTeam>>(
        future: _teamsFuture,
        builder: (context, snapshot) {
          final serverTeams = snapshot.data?.items ?? const <AirmiusTeam>[];
          final serverIds = serverTeams.map((team) => team.id).toSet();
          final teams = [
            ..._createdTeams.where((team) => !serverIds.contains(team.id)),
            ...serverTeams,
          ];
          final filteredTeams = _filterTeams(teams);
          return PageFrame(
            title: t('teamsCenter.teams'),
            subtitle: t('teamsCenter.subtitle'),
            showHeader: true,
            trailing: FilledButton.icon(
              key: const ValueKey('teams-center-create'),
              onPressed: _createTeam,
              icon: const Icon(Icons.add, size: 18),
              label: Text(t('clubs.createTeam')),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('teamsCenter.management')),
                      const SizedBox(height: 8),
                      Text(
                        t('teamsCenter.mobileWorkspaces'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 23,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        t('teamsCenter.managementBody'),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 14),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children:
                            [
                                  'roster',
                                  'invitations',
                                  'calendar',
                                  'files',
                                  'chat',
                                  'profile',
                                ]
                                .map(
                                  (item) => ChoiceChip(
                                    selected: _tab == item,
                                    label: Text(t('teamsCenter.tab.$item')),
                                    onSelected: (_) =>
                                        setState(() => _tab = item),
                                    selectedColor: airmiusAccentColor(
                                      context,
                                    ).withValues(alpha: 0.22),
                                    backgroundColor: airmiusSurfaceSoftColor(
                                      context,
                                    ),
                                    side: BorderSide(
                                      color: _tab == item
                                          ? airmiusAccentColor(context)
                                          : airmiusBorderColor(context),
                                    ),
                                    labelStyle: TextStyle(
                                      color: _tab == item
                                          ? airmiusAccentColor(context)
                                          : airmiusMutedColor(context),
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                )
                                .toList(),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: MetricCard(
                        value: '${teams.length}',
                        label: t('teamsCenter.teams'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(
                        value:
                            '${teams.where((team) => _text(team.visibility).toLowerCase().contains('public')).length}',
                        label: t('teamsCenter.public'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(
                        value:
                            '${teams.where((team) => _text(team.ageGroup).isNotEmpty).length}',
                        label: t('teamsCenter.ageGroups'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                if (snapshot.connectionState == ConnectionState.waiting &&
                    teams.isEmpty)
                  AirmiusPanel(
                    child: Center(
                      child: Padding(
                        padding: EdgeInsets.all(14),
                        child: CircularProgressIndicator(
                          color: airmiusAccentColor(context),
                        ),
                      ),
                    ),
                  ),
                if (snapshot.hasError)
                  AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Eyebrow(t('teamsCenter.loadFailed')),
                        const SizedBox(height: 8),
                        Text(
                          snapshot.error is AirmiusApiException
                              ? (snapshot.error! as AirmiusApiException)
                                    .userMessage
                              : t('common.errorDetails'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.35,
                          ),
                        ),
                        const SizedBox(height: 12),
                        AirmiusButton(
                          label: t('teamsCenter.reload'),
                          icon: Icons.refresh_outlined,
                          onPressed: _reloadTeams,
                        ),
                      ],
                    ),
                  ),
                if (!snapshot.hasError &&
                    snapshot.connectionState != ConnectionState.waiting &&
                    filteredTeams.isEmpty)
                  AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Eyebrow(t('teamsCenter.empty')),
                        const SizedBox(height: 8),
                        Text(
                          t('teamsCenter.emptyBody'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.35,
                          ),
                        ),
                        const SizedBox(height: 12),
                        AirmiusButton(
                          label: t('teamsCenter.showProfiles'),
                          icon: Icons.groups_2_outlined,
                          onPressed: () => setState(() => _tab = 'profile'),
                        ),
                      ],
                    ),
                  ),
                for (final team in filteredTeams) ...[
                  _TeamLine(
                    team: team,
                    icon: _teamIcon(team),
                    title: team.name,
                    body: _teamBody(team),
                    status: _text(
                      team.visibility,
                      fallback: t('teamDetail.team'),
                    ),
                    color: _teamColor(team),
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => TeamDetailScreen(
                          title: team.name,
                          mode: _teamMode(_tab),
                          teamId: team.id,
                          team: team,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('teamsCenter.actions')),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: [
                          AirmiusButton(
                            label: t('clubs.createTeam'),
                            icon: Icons.add_circle_outline,
                            onPressed: _createTeam,
                          ),
                          AirmiusButton(
                            label: t('teamsCenter.openClubs'),
                            icon: Icons.group_add_outlined,
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => ClubsScreen(
                                  requestedClubIds: const {},
                                  onRequestClub: (_) {},
                                  onWithdrawClub: (_) {},
                                ),
                              ),
                            ),
                          ),
                          AirmiusButton(
                            label: t('teamsCenter.reload'),
                            icon: Icons.refresh_outlined,
                            secondary: true,
                            onPressed: _reloadTeams,
                          ),
                          AirmiusButton(
                            label: t('teamsCenter.manageRoster'),
                            icon: Icons.badge_outlined,
                            secondary: true,
                            onPressed: () => setState(() => _tab = 'roster'),
                          ),
                          AirmiusButton(
                            label: t('teamsCenter.sendInvitation'),
                            icon: Icons.mark_email_read_outlined,
                            secondary: true,
                            onPressed: () =>
                                setState(() => _tab = 'invitations'),
                          ),
                          AirmiusButton(
                            label: t('teamsCenter.operations'),
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
              ],
            ),
          );
        },
      ),
    );
  }

  List<AirmiusTeam> _filterTeams(List<AirmiusTeam> teams) {
    return teams;
  }

  String _teamMode(String tab) => switch (tab) {
    'invitations' => 'Einladungen',
    'calendar' => 'Kalender',
    'files' => 'Dateien',
    'chat' => 'Chat',
    'profile' => 'Profil',
    _ => 'Kader',
  };

  String _teamBody(AirmiusTeam team) {
    final parts = [
      team.clubName,
      team.sportType,
      team.ageGroup,
      team.description,
    ].whereType<String>().where((value) => value.trim().isNotEmpty).toList();
    return parts.isEmpty
        ? AirmiusScope.of(context).t('teamsCenter.defaultBody')
        : parts.join(' - ');
  }

  IconData _teamIcon(AirmiusTeam team) {
    final text = '${team.name} ${team.ageGroup ?? ''} ${team.description ?? ''}'
        .toLowerCase();
    if (text.contains('jugend') || text.contains('u1')) {
      return Icons.family_restroom_outlined;
    }
    if (text.contains('lauf') || text.contains('training')) {
      return Icons.directions_run_outlined;
    }
    return Icons.groups_2_outlined;
  }

  Color _teamColor(AirmiusTeam team) {
    final visibility = _text(team.visibility).toLowerCase();
    if (visibility.contains('public') || visibility.contains('offen')) {
      return Theme.of(context).colorScheme.secondary;
    }
    if (visibility.contains('private') || visibility.contains('intern')) {
      return Theme.of(context).colorScheme.tertiary;
    }
    return airmiusAccentColor(context);
  }

  String _text(String? value, {String fallback = ''}) {
    final text = value?.trim();
    return text == null || text.isEmpty ? fallback : text;
  }
}

class _TeamCreateOptions {
  const _TeamCreateOptions({required this.clubs, required this.sports});

  final List<AirmiusClub> clubs;
  final List<AirmiusSport> sports;
}

class _CreateTeamDialog extends StatefulWidget {
  const _CreateTeamDialog();

  @override
  State<_CreateTeamDialog> createState() => _CreateTeamDialogState();
}

class _CreateTeamDialogState extends State<_CreateTeamDialog> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  Future<_TeamCreateOptions>? _optionsFuture;
  int? _clubId;
  String? _sport;
  bool _saving = false;
  String? _error;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _optionsFuture ??= _loadOptions();
  }

  @override
  void dispose() {
    _nameController.dispose();
    super.dispose();
  }

  Future<_TeamCreateOptions> _loadOptions() async {
    final services = AirmiusServicesScope.of(context);
    final userId = services.authState.user?.id;
    final results = await Future.wait([
      services.repositories.clubs.searchClubs(mine: true),
      services.repositories.sports.sports(),
    ]);
    final clubsPage = results[0] as AirmiusPage<AirmiusClub>;
    final sportsPage = results[1] as AirmiusPage<AirmiusSport>;
    final clubs = clubsPage.items
        .where((club) => club.canManage || club.ownerId == userId)
        .toList();
    return _TeamCreateOptions(clubs: clubs, sports: sportsPage.items);
  }

  Future<void> _submit() async {
    if (_saving || !(_formKey.currentState?.validate() ?? false)) return;
    final clubId = _clubId;
    if (clubId == null) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final team = await AirmiusServicesScope.of(context).repositories.clubs
          .createTeam({
            'club_id': clubId,
            'name': _nameController.text.trim(),
            if ((_sport ?? '').isNotEmpty) 'sport_type': _sport,
          });
      if (!mounted) return;
      Navigator.of(context).pop(team);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = error is AirmiusApiException
            ? error.userMessage
            : t('common.errorDetails');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(t('clubs.createTeam')),
      content: SizedBox(
        width: 480,
        child: FutureBuilder<_TeamCreateOptions>(
          future: _optionsFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Text(t('common.errorDetails'));
            }
            final options = snapshot.data!;
            if (options.clubs.isEmpty) {
              return Text(t('membership.noManagedClub'));
            }
            _clubId ??= options.clubs.first.id;
            _sport ??= options.clubs.first.sportType;
            return Form(
              key: _formKey,
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    DropdownButtonFormField<int>(
                      key: const ValueKey('team-create-club'),
                      initialValue: _clubId,
                      isExpanded: true,
                      decoration: InputDecoration(labelText: t('clubs.club')),
                      items: options.clubs
                          .map(
                            (club) => DropdownMenuItem<int>(
                              value: club.id,
                              child: Text(club.name),
                            ),
                          )
                          .toList(),
                      onChanged: _saving
                          ? null
                          : (value) => setState(() {
                              _clubId = value;
                              final club = options.clubs
                                  .where((item) => item.id == value)
                                  .firstOrNull;
                              if ((_sport ?? '').isEmpty) {
                                _sport = club?.sportType;
                              }
                            }),
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      key: const ValueKey('team-create-name'),
                      controller: _nameController,
                      enabled: !_saving,
                      textInputAction: TextInputAction.next,
                      decoration: InputDecoration(
                        labelText: t('clubs.teamName'),
                        hintText: t('clubs.teamNameHint'),
                      ),
                      validator: (value) =>
                          value == null || value.trim().isEmpty
                          ? t('publicInterest.required')
                          : null,
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      key: const ValueKey('team-create-sport'),
                      initialValue:
                          options.sports.any((sport) => sport.slug == _sport)
                          ? _sport
                          : null,
                      decoration: InputDecoration(labelText: t('clubs.sport')),
                      isExpanded: true,
                      items: options.sports
                          .map(
                            (sport) => DropdownMenuItem<String>(
                              value: sport.slug,
                              child: Text(sport.name),
                            ),
                          )
                          .toList(),
                      onChanged: _saving
                          ? null
                          : (value) => setState(() => _sport = value),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 12),
                      Text(
                        _error!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            );
          },
        ),
      ),
      actions: [
        SizedBox(
          width: double.infinity,
          child: Wrap(
            alignment: WrapAlignment.end,
            spacing: 8,
            runSpacing: 8,
            children: [
              TextButton(
                onPressed: _saving ? null : () => Navigator.of(context).pop(),
                child: Text(t('common.cancel')),
              ),
              FilledButton.icon(
                key: const ValueKey('team-create-submit'),
                onPressed: _saving ? null : _submit,
                icon: _saving
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.save_outlined),
                label: Text(
                  _saving ? t('clubs.saving') : t('clubs.createTeam'),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _TeamLine extends StatelessWidget {
  const _TeamLine({
    required this.team,
    required this.icon,
    required this.title,
    required this.body,
    required this.status,
    required this.color,
    required this.onTap,
  });

  final AirmiusTeam team;
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
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AirmiusAvatar(title, imageUrl: team.logoUrl),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 10),
                StatusPill(status, color: color),
              ],
            ),
          ),
          Icon(icon, color: color, size: 22),
          const SizedBox(width: 4),
          Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
        ],
      ),
    );
  }
}
