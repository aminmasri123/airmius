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
    final hasPeriods = planningRows(club['periods']).isNotEmpty;
    final seasonEnabled = club['season_planning_enabled'] == true;
    final canCreateSeason =
        widget.seasons && canEdit && teamId != null && hasPeriods;
    return Scaffold(
      appBar: AppBar(
        title: Text(t(widget.seasons ? 'seasons' : 'competitions')),
        actions: [
          IconButton(
            tooltip: t('refresh'),
            onPressed: loading ? null : reload,
            icon: const Icon(Icons.refresh),
          ),
          if (canEdit && (!widget.seasons || hasPeriods))
            IconButton(
              tooltip: t('new'),
              onPressed: loading ? null : edit,
              icon: const Icon(Icons.add),
            ),
        ],
      ),
      floatingActionButton: canCreateSeason
          ? FloatingActionButton.extended(
              onPressed: loading ? null : edit,
              icon: const Icon(Icons.add),
              label: Text(t('createSeasonPlan')),
            )
          : null,
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
              if (widget.seasons && clubs.isNotEmpty && teams.isNotEmpty) ...[
                const SizedBox(height: 16),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          t('seasons'),
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 8),
                        Text(t('seasonIntro')),
                        if (!seasonEnabled) ...[
                          const SizedBox(height: 12),
                          Text(t('seasonProRequired')),
                        ] else if (!hasPeriods) ...[
                          const SizedBox(height: 12),
                          Text(t('noPeriodsHelp')),
                        ] else if (!canEdit) ...[
                          const SizedBox(height: 12),
                          Text(t('seasonReadOnlyHelp')),
                        ] else ...[
                          const SizedBox(height: 12),
                          FilledButton.icon(
                            onPressed: edit,
                            icon: const Icon(Icons.add),
                            label: Text(t('createSeasonPlan')),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ],
              if (clubs.isEmpty)
                Text(t('noClubs'))
              else if (widget.seasons && teams.isEmpty)
                Text(t('noTeams'))
              else ...[
                if (!canEdit && !widget.seasons)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Text(t('readOnly')),
                  ),
                if (widget.seasons && !hasPeriods) Text(t('noPeriods')),
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
                      '${t('${row['status']}')} ${widget.seasons ? '' : '· ${planningDay(row['starts_on'])} ${row['calendar_start_time'] ?? ''} – ${planningDay(row['ends_on'])} ${row['calendar_end_time'] ?? ''}'}',
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
    'venue_status': 'tbd',
    'venue_name': '',
    'street': '',
    'house_number': '',
    'postal_code': '',
    'city': '',
    'country': '',
    'participation_fee_type': 'unknown',
    'participation_fee_cents': null,
    'participation_fee_basis': null,
    'calendar_enabled': true,
    'calendar_scope': 'club',
    'calendar_user_ids': <dynamic>[],
    'calendar_start_time': '09:00',
    'calendar_end_time': '18:00',
    'meeting_at': '',
    'meeting_point': '',
    'departure_at': '',
    'expected_return_at': '',
    'travel_mode': 'individual',
    'responsible_user_id': null,
    'responsible_name': '',
    'responsible_phone': '',
    'description': '',
    'equipment_notes': '',
    'document_url': '',
    'schedule_url': '',
    'participant_list_url': '',
    'website_url': '',
    'transport_notes': '',
    'drivers_needed': null,
    'seats_needed': null,
    'accommodation_required': false,
    'accommodation_name': '',
    'accommodation_address': '',
    'accommodation_notes': '',
    'accommodation_check_in_at': '',
    'accommodation_check_out_at': '',
    'accommodation_cost_cents': null,
    'notify_participants': true,
    'reminder_hours_before': 24,
    'cancellation_reason': '',
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
    fields: (f) {
      final venueKnown = f.values['venue_status'] == 'known';
      final paid = f.values['participation_fee_type'] == 'paid';
      final calendarEnabled = f.values['calendar_enabled'] == true;
      final accommodationRequired = f.values['accommodation_required'] == true;
      final cancelled = f.values['status'] == 'cancelled';
      final calendarScope = '${f.values['calendar_scope'] ?? 'club'}';
      final calendarUserIds = (f.values['calendar_user_ids'] as List? ?? []);
      return [
        f.text('name', required: true),
        f.options(
          'team_id',
          planningRows(club['teams']),
          label: f.t('team'),
          nullable: true,
        ),
        f.text('season_name', max: 120),
        f.status(['planned', 'open', 'closed', 'completed', 'cancelled']),
        if (cancelled)
          f.text('cancellation_reason', required: true, max: 1000, lines: 3),
        f.date('starts_on'),
        f.date('ends_on'),
        Row(
          children: [
            Expanded(
              child: f.text('calendar_start_time', required: true, max: 5),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: f.text('calendar_end_time', required: true, max: 5),
            ),
          ],
        ),
        f.date('registration_deadline_at', time: true, required: false),
        const Divider(),
        Text(
          f.t('scheduleAndMeeting'),
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 12),
        f.date('meeting_at', time: true, required: false),
        f.text('meeting_point', required: false, max: 500),
        f.date('departure_at', time: true, required: false),
        f.date('expected_return_at', time: true, required: false),
        f.options('travel_mode', [
          {'id': 'individual', 'name': f.t('travelIndividual')},
          {'id': 'club', 'name': f.t('travelClub')},
          {'id': 'carpool', 'name': f.t('travelCarpool')},
          {'id': 'public_transport', 'name': f.t('travelPublic')},
          {'id': 'other', 'name': f.t('other')},
        ]),
        const Divider(),
        Text(
          f.t('responsibilityAndInfo'),
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 12),
        f.options(
          'responsible_user_id',
          planningRows(club['members']),
          label: f.t('responsible_user_id'),
          nullable: true,
        ),
        f.text('responsible_name', required: false, max: 120),
        f.text('responsible_phone', required: false, max: 60),
        f.text('description', required: false, max: 5000, lines: 4),
        f.text('equipment_notes', required: false, max: 3000, lines: 3),
        f.text('document_url', required: false, max: 2048),
        f.text('schedule_url', required: false, max: 2048),
        f.text('participant_list_url', required: false, max: 2048),
        f.text('website_url', required: false, max: 2048),
        Text(
          f.t('locationAndAddress'),
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 12),
        f.status(['tbd', 'known'], key: 'venue_status'),
        if (venueKnown) ...[
          f.text('venue_name', required: false, max: 120),
          f.text('street', required: false, max: 160),
          f.text('house_number', required: false, max: 30),
          f.text('postal_code', required: false, max: 20),
          f.text('city', required: true, max: 120),
          f.text('country', required: false, max: 120),
        ],
        Text(
          f.t('participationCost'),
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 12),
        f.options('participation_fee_type', [
          {'id': 'unknown', 'name': f.t('unknown')},
          {'id': 'free', 'name': f.t('free')},
          {'id': 'paid', 'name': f.t('paidFee')},
        ], label: f.t('participation_fee_type')),
        if (paid) ...[
          f.money('participation_fee_cents'),
          f.options('participation_fee_basis', [
            {'id': 'person', 'name': f.t('feePerson')},
            {'id': 'team', 'name': f.t('teamFee')},
            {'id': 'flat', 'name': f.t('flat')},
          ], label: f.t('participation_fee_basis')),
        ],
        const Divider(),
        Text(
          f.t('transportAndStay'),
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 12),
        f.number('drivers_needed', required: false, min: 0, max: 100),
        f.number('seats_needed', required: false, min: 0, max: 1000),
        f.text('transport_notes', required: false, max: 3000, lines: 3),
        f.check('accommodation_required', f.t('accommodation_required')),
        if (accommodationRequired) ...[
          f.text('accommodation_name', required: true, max: 200),
          f.text('accommodation_address', required: true, max: 500),
          f.date('accommodation_check_in_at', time: true, required: false),
          f.date('accommodation_check_out_at', time: true, required: false),
          f.money('accommodation_cost_cents', required: false),
          f.text('accommodation_notes', required: false, max: 3000, lines: 3),
        ],
        const Divider(),
        f.check('calendar_enabled', f.t('calendarPublish')),
        f.check('notify_participants', f.t('notify_participants')),
        f.options('reminder_hours_before', [
          {'id': 2, 'name': f.t('reminder2h')},
          {'id': 6, 'name': f.t('reminder6h')},
          {'id': 12, 'name': f.t('reminder12h')},
          {'id': 24, 'name': f.t('reminder24h')},
          {'id': 48, 'name': f.t('reminder48h')},
          {'id': 72, 'name': f.t('reminder72h')},
          {'id': 168, 'name': f.t('reminder7d')},
        ]),
        if (calendarEnabled) ...[
          f.options(
            'calendar_scope',
            [
              {'id': 'club', 'name': f.t('calendarClub')},
              {'id': 'team', 'name': f.t('calendarTeam')},
              {'id': 'people', 'name': f.t('calendarPeople')},
            ],
            label: f.t('calendarAudience'),
            after: () {
              if (f.values['calendar_scope'] != 'people') {
                f.change('calendar_user_ids', <dynamic>[]);
              }
            },
          ),
          if (calendarScope == 'people') ...[
            Text(
              f.t('calendarSelectPeople'),
              style: Theme.of(context).textTheme.titleSmall,
            ),
            for (final member in planningRows(club['members']))
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('${member['name']}'),
                value: calendarUserIds.contains(member['id']),
                onChanged: f.busy
                    ? null
                    : (selected) => f.update(() {
                        if (selected == true) {
                          calendarUserIds.add(member['id']);
                        } else {
                          calendarUserIds.remove(member['id']);
                        }
                      }),
              ),
          ],
        ],
      ];
    },
    validate: (v) {
      if (planningDate(v['ends_on'])!.isBefore(planningDate(v['starts_on'])!)) {
        return planningText(context, 'dateOrder');
      }
      final timePattern = RegExp(r'^(?:[01]\d|2[0-3]):[0-5]\d$');
      if (!timePattern.hasMatch('${v['calendar_start_time']}') ||
          !timePattern.hasMatch('${v['calendar_end_time']}')) {
        return planningText(context, 'calendarTimeInvalid');
      }
      final startsAt = planningDate(
        '${planningApiDay(v['starts_on'])} ${v['calendar_start_time']}',
      );
      final endsAt = planningDate(
        '${planningApiDay(v['ends_on'])} ${v['calendar_end_time']}',
      );
      if (startsAt == null || endsAt == null || !endsAt.isAfter(startsAt)) {
        return planningText(context, 'dateOrder');
      }
      final departure = planningDate(v['departure_at']);
      final expectedReturn = planningDate(v['expected_return_at']);
      if (departure != null &&
          expectedReturn != null &&
          expectedReturn.isBefore(departure)) {
        return planningText(context, 'dateOrder');
      }
      final deadline = planningDate(v['registration_deadline_at']);
      if (deadline != null && deadline.isAfter(planningDate(v['ends_on'])!)) {
        return planningText(context, 'dateOrder');
      }
      if (v['calendar_enabled'] == true) {
        if (v['calendar_scope'] == 'team' && v['team_id'] == null) {
          return planningText(context, 'calendarTeamRequired');
        }
        if (v['calendar_scope'] == 'people' &&
            (v['calendar_user_ids'] as List? ?? []).isEmpty) {
          return planningText(context, 'calendarPeopleRequired');
        }
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
      for (final key in [
        'venue_status',
        'venue_name',
        'street',
        'house_number',
        'postal_code',
        'city',
        'country',
        'participation_fee_type',
        'participation_fee_cents',
        'participation_fee_basis',
        'calendar_enabled',
        'calendar_scope',
        'calendar_user_ids',
        'calendar_start_time',
        'calendar_end_time',
        'meeting_at',
        'meeting_point',
        'departure_at',
        'expected_return_at',
        'travel_mode',
        'responsible_user_id',
        'responsible_name',
        'responsible_phone',
        'description',
        'equipment_notes',
        'document_url',
        'schedule_url',
        'participant_list_url',
        'website_url',
        'transport_notes',
        'drivers_needed',
        'seats_needed',
        'accommodation_required',
        'accommodation_name',
        'accommodation_address',
        'accommodation_notes',
        'accommodation_check_in_at',
        'accommodation_check_out_at',
        'accommodation_cost_cents',
        'notify_participants',
        'reminder_hours_before',
        'cancellation_reason',
      ])
        key: v[key],
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
