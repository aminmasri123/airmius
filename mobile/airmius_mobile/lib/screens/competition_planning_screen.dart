import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_services_scope.dart';
import 'planning_widgets.dart';
import 'planning_workspace_screen.dart';
import 'competition_game_board.dart';

class CompetitionPlanningDetailScreen extends StatefulWidget {
  const CompetitionPlanningDetailScreen({
    super.key,
    required this.competitionId,
    required this.club,
  });
  final int competitionId;
  final AirmiusJson club;
  @override
  State<CompetitionPlanningDetailScreen> createState() =>
      _CompetitionPlanningDetailScreenState();
}

class _CompetitionPlanningDetailScreenState
    extends State<CompetitionPlanningDetailScreen> {
  late AirmiusApiClient client;
  AirmiusJson data = {};
  bool initialized = false, loading = true, busy = false, blocked = false;
  String? error;
  int get id => widget.competitionId;
  bool get canEdit => data['can_edit'] == true;
  bool get open => ['planned', 'open'].contains(data['status']);
  List<AirmiusJson> get roster => planningRows(data['roster_entries']);
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
    setState(() {
      loading = true;
      error = null;
      data = {};
      blocked = false;
    });
    try {
      final loaded = planningData(await client.planningCompetition(id));
      if (mounted) setState(() => data = loaded);
    } catch (e) {
      if (mounted) setState(() => error = planningError(context, e));
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> editor(PlanningEditor form) async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => form),
    );
    if (mounted && changed == true) await reload();
  }

  Future<void> action(Future<dynamic> Function() save) async {
    if (busy || blocked) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await save();
      if (mounted) await reload();
    } catch (e) {
      if (mounted) {
        setState(() {
          final code = e is AirmiusApiException ? e.statusCode : 0;
          blocked = [0, 401, 403, 404, 503, 599].contains(code);
          error = code == 0 || code == 599
              ? t('uncertain')
              : planningError(context, e);
        });
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Widget heading(String key, {VoidCallback? add}) => Padding(
    padding: const EdgeInsets.only(top: 16),
    child: Row(
      children: [
        Expanded(
          child: Text(t(key), style: Theme.of(context).textTheme.titleMedium),
        ),
        if (add != null)
          IconButton(
            tooltip: t('new'),
            onPressed: busy || blocked ? null : add,
            icon: const Icon(Icons.add),
          ),
      ],
    ),
  );
  Future<void> component(String kind) => editor(
    PlanningEditor(
      title: t(kind),
      initial: kind == 'roster'
          ? {
              'display_name': '',
              'user_id': null,
              'competition_class_id': null,
              'bib_number': '',
            }
          : {'name': ''},
      fields: (f) => kind == 'roster'
          ? [
              f.options(
                'user_id',
                planningRows(widget.club['members']),
                label: t('member'),
                nullable: true,
                after: () {
                  final member = planningRows(
                    widget.club['members'],
                  ).where((m) => m['id'] == f.values['user_id']).firstOrNull;
                  if (member != null) f.change('display_name', member['name']);
                },
              ),
              // Rebuild the display field when choosing a member so the persisted name is visible.
              KeyedSubtree(
                key: ValueKey('member:${f.values['user_id']}'),
                child: f.text('display_name', required: true, max: 120),
              ),
              f.options(
                'competition_class_id',
                planningRows(data['classes']),
                label: t('class'),
                nullable: true,
              ),
              f.text('bib_number', max: 80),
            ]
          : [
              f.text('name', required: true, max: 120),
              if (kind == 'classes') ...[
                f.text('age_group', max: 80),
                f.text('discipline', max: 120),
              ],
              if (kind == 'venues') ...[
                f.text('venue_type', max: 80),
                f.text('street', max: 160),
                f.text('house_number', max: 30),
                f.text('postal_code', max: 20),
                f.text('city', max: 120),
                f.text('country', max: 120),
                f.text('venue_contact_name', max: 120),
                f.text('venue_contact_phone', max: 60),
                f.text('website', max: 500),
                f.text('access_notes', max: 1500, lines: 3),
                f.text('venue_notes', max: 3000, lines: 4),
              ],
            ],
      save: (v) => client.addPlanningComponent(id, kind, v),
    ),
  );

  String venueDetails(AirmiusJson venue) {
    final metadata = venue['metadata'] is Map
        ? Map<String, dynamic>.from(venue['metadata'] as Map)
        : <String, dynamic>{};
    final street = [
      venue['street'],
      venue['house_number'],
    ].where((value) => value != null && '$value'.trim().isNotEmpty).join(' ');
    final locality = [
      venue['postal_code'],
      venue['city'],
    ].where((value) => value != null && '$value'.trim().isNotEmpty).join(' ');
    final address = [
      street,
      locality,
      venue['country'],
    ].where((value) => value != null && '$value'.trim().isNotEmpty).join(', ');
    final contact = [
      metadata['venue_contact_name'],
      metadata['venue_contact_phone'],
    ].where((value) => value != null && '$value'.trim().isNotEmpty).join(' · ');

    return [
      metadata['venue_type'],
      address,
      contact,
      metadata['website'],
      metadata['access_notes'],
      metadata['venue_notes'],
    ].where((value) => value != null && '$value'.trim().isNotEmpty).join('\n');
  }

  List<Widget> selection(PlanningEditorState f, List<AirmiusJson> options) {
    final selected = f.values['roster_ids'] as List;
    return [
      Text(t('selection')),
      for (final row in options)
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          title: Text('${row['display_name']}'),
          value: selected.contains(row['id']),
          onChanged: f.busy
              ? null
              : (v) => f.update(() {
                  if (v == true) {
                    selected.add(row['id']);
                  } else {
                    selected.remove(row['id']);
                  }
                }),
        ),
    ];
  }

  Future<void> nominate() => editor(
    PlanningEditor(
      title: t('nominate'),
      initial: {
        'roster_ids': [],
        'start_fee_cents': 0,
        'team_id': data['team_id'],
        'competition_class_id': null,
      },
      fields: (f) => [
        f.options(
          'team_id',
          planningRows(widget.club['teams']),
          label: t('team'),
          nullable: true,
        ),
        f.options(
          'competition_class_id',
          planningRows(data['classes']),
          label: t('class'),
          nullable: true,
        ),
        f.number('start_fee_cents'),
        ...selection(
          f,
          roster
              .where((r) => r['competition_registration_id'] == null)
              .toList(),
        ),
      ],
      validate: (v) =>
          (v['roster_ids'] as List).isEmpty ? t('minSelection') : null,
      save: (v) => client.nominatePlanningRoster(id, v),
    ),
  );
  Future<void> tournament() => editor(
    PlanningEditor(
      title: t('tournament'),
      initial: {'roster_ids': [], 'group_count': 1},
      fields: (f) => [
        f.number('group_count', min: 1, max: 32),
        ...selection(f, roster),
      ],
      validate: (v) =>
          (v['roster_ids'] as List).length < 2 ||
              v['group_count'] > (v['roster_ids'] as List).length
          ? t('minSelection')
          : null,
      save: (v) => client.generatePlanningTournament(id, v),
    ),
  );
  Future<void> match(AirmiusJson row) => editor(
    PlanningEditor(
      title: '${t('matches')} #${row['match_number']}',
      initial: {
        ...row,
        'reason': '',
        'scheduled_at': planningApiTime(row['scheduled_at']),
      },
      fields: (f) => [
        Text('${row['home_label']} – ${row['away_label']}'),
        f.status(['planned', 'scheduled', 'completed', 'cancelled']),
        f.number('home_score', required: false, max: 100000),
        f.number('away_score', required: false, max: 100000),
        f.date('scheduled_at', time: true, required: false),
        f.text('reason', required: true, max: 1000, lines: 2),
      ],
      validate: (v) =>
          v['status'] == 'completed' &&
              (v['home_score'] == null || v['away_score'] == null)
          ? t('required')
          : null,
      save: (v) => client.savePlanningMatch(id, planningId(row['id'])!, {
        for (final k in ['home_score', 'away_score', 'status', 'reason'])
          k: v[k],
        'scheduled_at': planningDate(
          v['scheduled_at'],
        )?.toUtc().toIso8601String(),
      }),
    ),
  );
  Future<void> lineup() => editor(
    PlanningEditor(
      title: t('lineups'),
      initial: {'name': '', 'roster_ids': [], 'roles': {}, 'positions': {}},
      fields: (f) => [
        f.text('name', required: true, max: 120),
        ...selection(f, roster),
        for (final r in roster.where(
          (r) => (f.values['roster_ids'] as List).contains(r['id']),
        )) ...[
          Text('${r['display_name']}'),
          DropdownButtonFormField<String>(
            key: ValueKey('role:${r['id']}'),
            initialValue: f.values['roles']['${r['id']}'] ?? 'starter',
            isExpanded: true,
            items: [
              for (final s in ['starter', 'substitute'])
                DropdownMenuItem(value: s, child: Text(t(s))),
            ],
            onChanged: f.busy
                ? null
                : (v) => f.values['roles']['${r['id']}'] = v,
          ),
          TextFormField(
            key: ValueKey('position:${r['id']}'),
            initialValue: f.values['positions']['${r['id']}'],
            enabled: !f.busy,
            maxLength: 80,
            decoration: InputDecoration(labelText: t('position')),
            onChanged: (v) => f.values['positions']['${r['id']}'] = v.trim(),
          ),
        ],
      ],
      validate: (v) =>
          (v['roster_ids'] as List).isEmpty ? t('minSelection') : null,
      save: (v) => client.savePlanningLineup(id, {
        'name': v['name'],
        'entries': [
          for (final r in v['roster_ids'])
            {
              'competition_roster_entry_id': r,
              'role': v['roles']['$r'] ?? 'starter',
              'position': v['positions']['$r'],
            },
        ],
      }),
    ),
  );
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text('${data['name'] ?? t('competitions')}'),
      actions: [
        IconButton(
          tooltip: t('refresh'),
          onPressed: loading || busy ? null : reload,
          icon: const Icon(Icons.refresh),
        ),
        if (canEdit)
          IconButton(
            tooltip: t('edit'),
            onPressed: busy || blocked
                ? null
                : () => editor(
                    competitionPlanningEditor(
                      context,
                      client,
                      widget.club,
                      row: data,
                    ),
                  ),
            icon: const Icon(Icons.edit_outlined),
          ),
        if ((data['can_delete_competitions'] == true ||
                widget.club['can_delete_competitions'] == true) &&
            data.isNotEmpty &&
            planningRows(data['games']).isEmpty &&
            planningRows(data['registrations']).isEmpty &&
            planningRows(data['tournament_matches']).isEmpty)
          IconButton(
            tooltip: t('delete'),
            onPressed: busy || blocked
                ? null
                : () => action(() async {
                    if (await planningDelete(
                          context,
                          () => client.deletePlanningCompetition(id),
                        ) &&
                        mounted) {
                      Navigator.pop(this.context);
                    }
                  }),
            icon: const Icon(Icons.delete_outline),
          ),
      ],
    ),
    body: RefreshIndicator(
      onRefresh: () async {
        if (!busy) await reload();
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          if (error != null) PlanningError(error!),
          if (loading) const Center(child: CircularProgressIndicator()),
          if (busy) const LinearProgressIndicator(),
          if (data.isNotEmpty) ...[
            Text(
              '${t('${data['status']}')} · ${planningDay(data['starts_on'])} – ${planningDay(data['ends_on'])}',
            ),
            if (!canEdit) Text(t('readOnly')),
            if (data['registration_deadline_at'] != null)
              Text(
                '${t('registration_deadline_at')}: ${planningTime(data['registration_deadline_at'])}',
              ),
            for (final kind in ['classes', 'venues', 'roster']) ...[
              heading(kind, add: canEdit ? () => component(kind) : null),
              for (final row in planningRows(
                data[kind == 'roster' ? 'roster_entries' : kind],
              ))
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    '${row[kind == 'roster' ? 'display_name' : 'name']}',
                  ),
                  subtitle: Text(
                    kind == 'venues'
                        ? venueDetails(row)
                        : [
                                row['bib_number'],
                                row['age_group'],
                                row['discipline'],
                                row['city'],
                              ]
                              .where((v) => v != null && '$v'.isNotEmpty)
                              .join(' · '),
                    maxLines: kind == 'venues' ? 6 : 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  trailing: canEdit
                      ? IconButton(
                          tooltip: t('delete'),
                          onPressed: busy || blocked
                              ? null
                              : () => action(() async {
                                  await planningDelete(
                                    context,
                                    () => client.removePlanningComponent(
                                      id,
                                      kind,
                                      planningId(row['id'])!,
                                    ),
                                  );
                                }),
                          icon: const Icon(Icons.delete_outline),
                        )
                      : null,
                ),
            ],
            heading(
              'registrations',
              add:
                  canEdit &&
                      open &&
                      roster.any(
                        (r) => r['competition_registration_id'] == null,
                      )
                  ? nominate
                  : null,
            ),
            for (final r in planningRows(data['registrations'])) ...[
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('#${r['id']} · ${t('${r['status']}')}'),
                subtitle: Text(
                  [
                    if (r['start_fee_cents'] != null)
                      '${t('start_fee_cents')}: ${r['start_fee_cents']}',
                    '${r['eligibility_status'] ?? ''}',
                  ].join(' · '),
                ),
              ),
              if (open)
                Wrap(
                  spacing: 8,
                  children: [
                    for (final a in ['eligibility', 'paid', 'confirm'])
                      if ((a == 'eligibility' &&
                              canEdit &&
                              r['status'] != 'confirmed') ||
                          (a == 'paid' &&
                              data['can_pay_fees'] == true &&
                              r['start_fee_paid_at'] == null) ||
                          (a == 'confirm' &&
                              data['can_confirm'] == true &&
                              r['status'] != 'confirmed'))
                        TextButton(
                          onPressed: busy || blocked
                              ? null
                              : () => action(
                                  () => client.planningRegistrationAction(
                                    id,
                                    planningId(r['id'])!,
                                    a,
                                  ),
                                ),
                          child: Text(t(a)),
                        ),
                  ],
                ),
            ],
            heading('matches'),
            if (canEdit &&
                planningRows(data['tournament_matches']).isEmpty &&
                roster.length >= 2)
              TextButton.icon(
                onPressed: busy || blocked ? null : tournament,
                icon: const Icon(Icons.account_tree_outlined),
                label: Text(t('tournament')),
              ),
            for (final m in planningRows(data['tournament_matches']))
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(
                  '#${m['match_number']} ${m['home_label']} – ${m['away_label']}',
                ),
                subtitle: Text(
                  '${m['home_score'] ?? '–'} : ${m['away_score'] ?? '–'} · ${t('${m['status']}')}\n${planningTime(m['scheduled_at'])}',
                ),
                trailing: canEdit
                    ? IconButton(
                        tooltip: t('edit'),
                        onPressed: busy || blocked ? null : () => match(m),
                        icon: const Icon(Icons.edit_outlined),
                      )
                    : null,
              ),
            heading('standings'),
            for (final s in planningRows(data['standings']))
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('${s['rank']}. ${s['display_name']}'),
                subtitle: Text(
                  '${s['points']} P · ${s['played']} · ${s['score_for']}:${s['score_against']}',
                ),
              ),
            heading(
              'lineups',
              add: canEdit && roster.isNotEmpty &&
                      !(data['game_planning_enabled'] == true &&
                          ['fussball', 'football', 'basketball'].contains(data['sport_type']))
                  ? lineup : null,
            ),
            if (data['game_planning_enabled'] == true &&
                ['fussball', 'football', 'basketball'].contains(data['sport_type']))
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.sports_soccer_outlined),
                title: Text(t('games')),
                subtitle: Text('${planningRows(data['games']).length}'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () async {
                  await Navigator.push<void>(context, MaterialPageRoute(builder: (_) =>
                      CompetitionGameBoardScreen(client: client, competitionId: id)));
                  if (mounted) await reload();
                },
              ),
            for (final l in planningRows(data['lineups'])) ...[
              if (l['competition_game_id'] == null) ...[
              Text(
                '${l['name']}',
                style: Theme.of(context).textTheme.titleSmall,
              ),
              for (final e in planningRows(l['entries']))
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    '${roster.where((r) => r['id'] == e['competition_roster_entry_id']).firstOrNull?['display_name'] ?? e['display_name'] ?? ''}',
                  ),
                  subtitle: Text(
                    '${t('${e['role']}')} · ${e['position'] ?? ''}',
                  ),
                ),
              ],
            ],
          ],
        ],
      ),
    ),
  );
}
