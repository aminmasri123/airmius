import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_services_scope.dart';
import 'competition_planning_screen.dart';
import 'planning_widgets.dart';

class CompetitionPlanningScreen extends PlanningWorkspaceScreen {
  const CompetitionPlanningScreen({
    super.key,
    super.initialClubId,
    super.initialTeamId,
  }) : super(seasons: false);
}

class SeasonPlanningScreen extends PlanningWorkspaceScreen {
  const SeasonPlanningScreen({
    super.key,
    super.initialClubId,
    super.initialTeamId,
  }) : super(seasons: true);
}

class PlanningWorkspaceScreen extends StatefulWidget {
  const PlanningWorkspaceScreen({
    super.key,
    required this.seasons,
    this.initialClubId,
    this.initialTeamId,
  });
  final bool seasons;
  final int? initialClubId, initialTeamId;
  @override
  State<PlanningWorkspaceScreen> createState() =>
      _PlanningWorkspaceScreenState();
}

class _PlanningWorkspaceScreenState extends State<PlanningWorkspaceScreen> {
  late AirmiusApiClient client;
  List<AirmiusJson> clubs = [], rows = [], events = [];
  int? clubId, teamId;
  bool initialized = false, loading = true, canEdit = false;
  bool initialSelectionApplied = false;
  int generation = 0;
  String? error;
  AirmiusJson get club =>
      clubs.firstWhere((c) => planningId(c['id']) == clubId, orElse: () => {});
  List<AirmiusJson> get teams => planningRows(club['teams']);
  String t(String key) => planningText(context, key);
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (initialized) return;
    initialized = true;
    final services = AirmiusServicesScope.of(context);
    client = services.clientForSession(services.authState.session);
    reload();
  }

  Future<void> reload() async {
    final run = ++generation;
    setState(() {
      loading = true;
      error = null;
      rows = [];
      events = [];
      canEdit = false;
    });
    try {
      final workspace = planningData(await client.planningWorkspace());
      if (!mounted || run != generation) return;
      clubs = planningRows(workspace['clubs']);
      final preferred =
          clubId ?? (initialSelectionApplied ? null : widget.initialClubId);
      clubId = clubs.any((c) => planningId(c['id']) == preferred)
          ? preferred
          : null;
      if (clubId == null &&
          !initialSelectionApplied &&
          widget.initialTeamId != null) {
        for (final c in clubs) {
          if (planningRows(
            c['teams'],
          ).any((team) => planningId(team['id']) == widget.initialTeamId)) {
            clubId = planningId(c['id']);
          }
        }
      }
      clubId ??= clubs.isEmpty ? null : planningId(clubs.first['id']);
      final preferredTeam =
          teamId ?? (initialSelectionApplied ? null : widget.initialTeamId);
      teamId = teams.any((c) => planningId(c['id']) == preferredTeam)
          ? preferredTeam
          : null;
      if (widget.seasons) {
        teamId ??= teams.isEmpty ? null : planningId(teams.first['id']);
      }
      initialSelectionApplied = true;
      if (clubId != null) {
        if (widget.seasons && teamId != null) {
          final data = planningData(await client.seasonPlans(teamId!));
          if (!mounted || run != generation) return;
          rows = planningRows(data['plans']);
          events = planningRows(data['events']);
          canEdit = data['can_edit'] == true;
        } else if (!widget.seasons) {
          final response = await client.planningCompetitions(clubId!);
          if (!mounted || run != generation) return;
          rows = planningRows(response['data']);
          canEdit = club['can_edit_competitions'] == true;
        }
      }
    } catch (e) {
      if (!mounted || run != generation) return;
      rows = [];
      events = [];
      clubs = [];
      clubId = null;
      teamId = null;
      canEdit = false;
      error = planningError(context, e);
    } finally {
      if (mounted && run == generation) setState(() => loading = false);
    }
  }

  Future<void> edit([AirmiusJson? row]) async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => widget.seasons
            ? seasonPlanningEditor(
                context,
                client,
                club,
                teamId!,
                events,
                row: row,
              )
            : competitionPlanningEditor(context, client, club, row: row),
      ),
    );
    if (mounted && changed == true) await reload();
  }

  Future<void> remove(AirmiusJson row) async {
    try {
      if (await planningDelete(
            context,
            () => client.deleteSeasonPlan(teamId!, planningId(row['id'])!),
          ) &&
          mounted) {
        await reload();
      }
    } catch (e) {
      if (mounted) setState(() => error = planningError(context, e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final visible = widget.seasons || teamId == null
        ? rows
        : rows.where((r) => planningId(r['team_id']) == teamId).toList();
    return Scaffold(
      appBar: AppBar(
        title: Text(t(widget.seasons ? 'seasons' : 'competitions')),
        actions: [
          IconButton(
            tooltip: t('refresh'),
            onPressed: loading ? null : reload,
            icon: const Icon(Icons.refresh),
          ),
          if (canEdit &&
              (!widget.seasons || planningRows(club['periods']).isNotEmpty))
            IconButton(
              tooltip: t('new'),
              onPressed: loading ? null : edit,
              icon: const Icon(Icons.add),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: reload,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            if (clubs.isNotEmpty)
              DropdownButtonFormField<int>(
                key: ValueKey('club:$clubId'),
                initialValue: clubId,
                isExpanded: true,
                decoration: InputDecoration(labelText: t('club')),
                items: [
                  for (final c in clubs)
                    DropdownMenuItem(
                      value: planningId(c['id']),
                      child: Text(
                        '${c['name']}',
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                ],
                onChanged: loading
                    ? null
                    : (v) {
                        clubId = v;
                        teamId = null;
                        reload();
                      },
              ),
            const SizedBox(height: 12),
            if (teams.isNotEmpty)
              DropdownButtonFormField<int>(
                key: ValueKey('team:$clubId:$teamId'),
                initialValue: teamId,
                isExpanded: true,
                decoration: InputDecoration(labelText: t('team')),
                items: [
                  if (!widget.seasons)
                    DropdownMenuItem(value: null, child: Text(t('allTeams'))),
                  for (final c in teams)
                    DropdownMenuItem(
                      value: planningId(c['id']),
                      child: Text(
                        '${c['name']}',
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                ],
                onChanged: loading
                    ? null
                    : (v) {
                        teamId = v;
                        reload();
                      },
              ),
            if (error != null) PlanningError(error!),
            if (loading)
              const Padding(
                padding: EdgeInsets.all(24),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (error == null) ...[
              if (clubs.isEmpty)
                Text(t('noClubs'))
              else if (widget.seasons && teams.isEmpty)
                Text(t('noTeams'))
              else ...[
                if (!canEdit)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Text(t('readOnly')),
                  ),
                if (widget.seasons && planningRows(club['periods']).isEmpty)
                  Text(t('noPeriods')),
                if (visible.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 24),
                    child: Text(t('empty')),
                  ),
                for (final row in visible) ...[
                  const Divider(),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text('${row['name']}'),
                    subtitle: Text(
                      '${t('${row['status']}')} ${widget.seasons ? '' : '· ${planningDay(row['starts_on'])} – ${planningDay(row['ends_on'])}'}',
                    ),
                    trailing: widget.seasons
                        ? (canEdit
                              ? IconButton(
                                  tooltip: t('edit'),
                                  onPressed: () => edit(row),
                                  icon: const Icon(Icons.edit_outlined),
                                )
                              : null)
                        : const Icon(Icons.chevron_right),
                    onTap: widget.seasons
                        ? (canEdit ? () => edit(row) : null)
                        : () async {
                            await Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => CompetitionPlanningDetailScreen(
                                  competitionId: planningId(row['id'])!,
                                  club: club,
                                ),
                              ),
                            );
                            if (mounted) await reload();
                          },
                  ),
                  if (widget.seasons) ...[
                    if ('${row['goals'] ?? ''}'.isNotEmpty)
                      Text('${row['goals']}'),
                    for (final phase in planningRows(row['phases']))
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text('${phase['name']}'),
                        subtitle: Text(
                          '${planningDay(phase['starts_on'])} – ${planningDay(phase['ends_on'])}\n${phase['goal'] ?? ''}',
                        ),
                      ),
                    for (final event in planningRows(row['events']))
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.event_outlined),
                        title: Text('${event['title']}'),
                        subtitle: Text(planningTime(event['start_time'])),
                      ),
                    if (canEdit)
                      Align(
                        alignment: AlignmentDirectional.centerEnd,
                        child: IconButton(
                          tooltip: t('delete'),
                          onPressed: () => remove(row),
                          icon: const Icon(Icons.delete_outline),
                        ),
                      ),
                  ],
                ],
              ],
            ],
          ],
        ),
      ),
    );
  }
}

PlanningEditor competitionPlanningEditor(
  BuildContext context,
  AirmiusApiClient client,
  AirmiusJson club, {
  AirmiusJson? row,
}) {
  final initial = <String, dynamic>{
    'name': '',
    'team_id': null,
    'season_name': '',
    'status': 'planned',
    'starts_on': planningApiDay(DateTime.now()),
    'ends_on': planningApiDay(DateTime.now()),
    'registration_deadline_at': '',
    ...?row,
  };
  initial['starts_on'] = planningApiDay(initial['starts_on']);
  initial['ends_on'] = planningApiDay(initial['ends_on']);
  initial['registration_deadline_at'] = planningApiTime(
    initial['registration_deadline_at'],
  );
  return PlanningEditor(
    title: planningText(context, 'competitions'),
    initial: initial,
    fields: (f) => [
      f.text('name', required: true),
      f.options(
        'team_id',
        planningRows(club['teams']),
        label: f.t('team'),
        nullable: true,
      ),
      f.text('season_name', max: 120),
      f.status(['planned', 'open', 'closed', 'completed', 'cancelled']),
      f.date('starts_on'),
      f.date('ends_on'),
      f.date('registration_deadline_at', time: true, required: false),
    ],
    validate: (v) {
      if (planningDate(v['ends_on'])!.isBefore(planningDate(v['starts_on'])!)) {
        return planningText(context, 'dateOrder');
      }
      final deadline = planningDate(v['registration_deadline_at']);
      if (deadline != null && deadline.isAfter(planningDate(v['ends_on'])!)) {
        return planningText(context, 'dateOrder');
      }
      return null;
    },
    save: (v) => client.savePlanningCompetition(planningId(club['id'])!, {
      for (final key in [
        'name',
        'team_id',
        'season_name',
        'status',
        'starts_on',
        'ends_on',
      ])
        key: v[key],
      'registration_deadline_at': planningDate(
        v['registration_deadline_at'],
      )?.toUtc().toIso8601String(),
    }, id: planningId(row?['id'])),
  );
}

PlanningEditor seasonPlanningEditor(
  BuildContext context,
  AirmiusApiClient client,
  AirmiusJson club,
  int teamId,
  List<AirmiusJson> events, {
  AirmiusJson? row,
}) {
  final periods = planningRows(club['periods']);
  final team = planningRows(
    club['teams'],
  ).firstWhere((t) => planningId(t['id']) == teamId, orElse: () => {});
  final periodId = row?['sport_year_period_id'] ?? team['sport_year_period_id'];
  final selectedPeriod = periods.firstWhere(
    (p) => p['id'] == periodId,
    orElse: () => periods.first,
  );
  return PlanningEditor(
    title: planningText(context, 'seasons'),
    initial: {
      'name': '',
      'goals': '',
      'status': 'draft',
      'sport_year_period_id': selectedPeriod['id'],
      'phases': [
        {
          'name': '',
          'goal': '',
          'starts_on': planningApiDay(selectedPeriod['starts_on']),
          'ends_on': planningApiDay(selectedPeriod['ends_on']),
        },
      ],
      ...?row,
      'event_ids': [for (final e in planningRows(row?['events'])) e['id']],
    },
    fields: (f) {
      final phases = (f.values['phases'] as List).cast<AirmiusJson>();
      final availableEvents = events
          .where(
            (e) =>
                e['sport_year_period_id'] == f.values['sport_year_period_id'],
          )
          .toList();
      final eventIds = f.values['event_ids'] as List;
      return [
        f.text('name', required: true),
        f.options(
          'sport_year_period_id',
          periods,
          label: f.t('period'),
          after: () => f.change('event_ids', []),
        ),
        f.status(['draft', 'published', 'completed']),
        f.text('goals', max: 5000, lines: 3),
        Text(f.t('phases'), style: Theme.of(f.context).textTheme.titleMedium),
        for (var i = 0; i < phases.length; i++) ...[
          Row(
            children: [
              Expanded(child: Text('${f.t('phase')} ${i + 1}')),
              IconButton(
                tooltip: f.t('delete'),
                onPressed: f.busy || phases.length == 1
                    ? null
                    : () => f.update(() => phases.removeAt(i)),
                icon: const Icon(Icons.delete_outline),
              ),
            ],
          ),
          f.text(
            'name',
            label: f.t('phase'),
            required: true,
            max: 120,
            target: phases[i],
          ),
          f.date('starts_on', target: phases[i]),
          f.date('ends_on', target: phases[i]),
          f.text('goal', max: 1000, lines: 2, target: phases[i]),
        ],
        TextButton.icon(
          onPressed: f.busy || phases.length >= 52
              ? null
              : () => f.update(
                  () => phases.add({
                    'name': '',
                    'goal': '',
                    'starts_on': '',
                    'ends_on': '',
                  }),
                ),
          icon: const Icon(Icons.add),
          label: Text(f.t('addPhase')),
        ),
        const Divider(),
        Text(f.t('events'), style: Theme.of(f.context).textTheme.titleMedium),
        for (final e in availableEvents)
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: Text('${e['title']}'),
            subtitle: Text(planningTime(e['start_time'])),
            value: eventIds.contains(e['id']),
            onChanged: f.busy
                ? null
                : (v) => f.update(() {
                    if (v == true) {
                      eventIds.add(e['id']);
                    } else {
                      eventIds.remove(e['id']);
                    }
                  }),
          ),
      ];
    },
    validate: (v) {
      final period = periods.firstWhere(
        (p) => p['id'] == v['sport_year_period_id'],
      );
      DateTime? previous;
      for (final p in (v['phases'] as List).cast<AirmiusJson>()) {
        final start = planningDate(p['starts_on'])!,
            end = planningDate(p['ends_on'])!;
        if (end.isBefore(start) ||
            start.isBefore(planningDate(period['starts_on'])!) ||
            end.isAfter(planningDate(period['ends_on'])!) ||
            (previous != null && !start.isAfter(previous))) {
          return planningText(context, 'phaseRange');
        }
        previous = end;
      }
      return null;
    },
    save: (v) => client.saveSeasonPlan(teamId, {
      for (final key in [
        'name',
        'goals',
        'status',
        'sport_year_period_id',
        'phases',
        'event_ids',
      ])
        key: v[key],
      if (row != null) 'revision': v['revision'],
    }, id: planningId(row?['id'])),
  );
}
