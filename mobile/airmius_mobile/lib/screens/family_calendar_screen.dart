import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_services_scope.dart';
import 'planning_widgets.dart';

class FamilyCalendarScreen extends StatefulWidget {
  const FamilyCalendarScreen({super.key});
  @override
  State<FamilyCalendarScreen> createState() => _FamilyCalendarScreenState();
}

class _FamilyCalendarScreenState extends State<FamilyCalendarScreen> {
  late AirmiusApiClient client;
  DateTime month = DateTime(DateTime.now().year, DateTime.now().month);
  AirmiusJson data = {};
  String? person, error;
  bool initialized = false, loading = true, busy = false, blocked = false;
  int generation = 0;
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
      data = {};
      error = null;
      blocked = false;
    });
    try {
      final loaded = planningData(
        await client.familyCalendar(DateFormat('yyyy-MM').format(month)),
      );
      if (!mounted || run != generation) return;
      setState(() {
        data = loaded;
        if (person != null && !people.any((p) => p['id'] == person)) {
          person = null;
        }
      });
    } catch (e) {
      if (mounted && run == generation) {
        setState(() => error = planningError(context, e));
      }
    } finally {
      if (mounted && run == generation) setState(() => loading = false);
    }
  }

  List<AirmiusJson> get people => [
    if (data['self'] != null) {'id': 'self', 'name': data['self']['name']},
    for (final c in planningRows(data['children']))
      {
        'id': '${c['id']}:${c['club_id']}',
        'name': '${c['name']} · #${c['club_id']}',
      },
  ];
  Future<void> editor(PlanningEditor form) async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => form),
    );
    if (mounted && changed == true) await reload();
  }

  Future<void> entry([AirmiusJson? row]) => editor(
    PlanningEditor(
      title: t('entries'),
      initial: {
        'title': '',
        'location': '',
        'starts_at': planningApiTime(month.add(const Duration(hours: 9))),
        'ends_at': planningApiTime(month.add(const Duration(hours: 10))),
        ...?row,
        'person': row?['child_user_id'] == null
            ? 'self'
            : '${row!['child_user_id']}:${row['club_id']}',
        if (row != null) 'starts_at': planningApiTime(row['starts_at']),
        if (row != null) 'ends_at': planningApiTime(row['ends_at']),
      },
      fields: (f) => [
        f.text('title', required: true),
        f.options('person', people, label: t('person')),
        f.text('location'),
        f.date('starts_at', time: true),
        f.date('ends_at', time: true),
      ],
      validate: (v) =>
          planningDate(v['ends_at'])!.isBefore(planningDate(v['starts_at'])!)
          ? t('dateOrder')
          : null,
      save: (v) {
        final child = planningRows(
          data['children'],
        ).where((c) => '${c['id']}:${c['club_id']}' == v['person']).firstOrNull;
        return client.saveFamilyCalendarEntry({
          'title': v['title'],
          'location': v['location'],
          'starts_at': planningDate(v['starts_at'])!.toUtc().toIso8601String(),
          'ends_at': planningDate(v['ends_at'])!.toUtc().toIso8601String(),
          'child_user_id': child?['id'],
          'club_id': child?['club_id'],
        }, id: planningId(row?['id']));
      },
    ),
  );
  Future<void> response(AirmiusJson event) => editor(
    PlanningEditor(
      title: '${event['person_name']} · ${event['title']}',
      initial: {
        'status': ['yes', 'no', 'maybe'].contains(event['response_status'])
            ? event['response_status']
            : 'maybe',
        'requirements_accepted': false,
        'consent_accepted': false,
        'camp_privacy_notice_accepted': false,
        'camp_travel_consent_accepted': false,
        'camp_group_key': event['camp_group_key'],
        'response_reason': '',
        'contact_name': '',
        'contact_phone': '',
        'contact_relationship': '',
        'camp_dietary_notes': '',
      },
      fields: (f) => [
        Text(planningTime(event['starts_at'])),
        f.status(['yes', 'no', 'maybe']),
        f.text('response_reason', max: 500, lines: 2),
        if (f.values['status'] == 'yes') ...[
          if (hasRequirements(event)) ...[
            Text('${event['participation_requirements']}'),
            f.check('requirements_accepted', t('requirements')),
          ],
          if (event['participation_consent_required'] == true) ...[
            if (event['participation_consent_version'] != null)
              Text('${event['participation_consent_version']}'),
            f.check('consent_accepted', t('consent')),
          ],
          if (planningRows(event['camp_groups']).isNotEmpty)
            f.options('camp_group_key', [
              for (final g in planningRows(event['camp_groups']))
                {'id': g['key'], 'name': g['name'] ?? g['label'] ?? g['key']},
            ]),
          if ('${event['camp_privacy_notice_version'] ?? ''}'.isNotEmpty) ...[
            Text('${event['camp_privacy_notice_version']}'),
            f.check('camp_privacy_notice_accepted', t('campPrivacy')),
          ],
          if (event['camp_travel_consent_required'] == true)
            f.check('camp_travel_consent_accepted', t('campTravel')),
          if (event['camp_emergency_contact_required'] == true) ...[
            f.text('contact_name', required: true, max: 120),
            f.text('contact_phone', required: true, max: 80),
            f.text('contact_relationship', max: 80),
          ],
          if (event['requires_camp_details'] == true)
            f.text('camp_dietary_notes', max: 1000, lines: 2),
        ],
      ],
      validate: (v) {
        if (v['status'] != 'yes') return null;
        if ((hasRequirements(event) && v['requirements_accepted'] != true) ||
            (event['participation_consent_required'] == true &&
                v['consent_accepted'] != true) ||
            ('${event['camp_privacy_notice_version'] ?? ''}'.isNotEmpty &&
                v['camp_privacy_notice_accepted'] != true) ||
            (event['camp_travel_consent_required'] == true &&
                v['camp_travel_consent_accepted'] != true)) {
          return t('acceptRequired');
        }
        return null;
      },
      save: (v) => client.respondFamilyCalendar(
        planningId(event['id'])!,
        planningId(event['person_id'])!,
        {
          'status': v['status'],
          'response_reason': v['response_reason'],
          if (v['status'] == 'yes') ...{
            'requirements_accepted': v['requirements_accepted'],
            'consent_accepted': v['consent_accepted'],
            'consent_version': event['participation_consent_version'],
            'camp_group_key': v['camp_group_key'],
            'camp_privacy_notice_accepted': v['camp_privacy_notice_accepted'],
            'camp_privacy_notice_version': event['camp_privacy_notice_version'],
            'camp_travel_consent_accepted': v['camp_travel_consent_accepted'],
            if (event['camp_emergency_contact_required'] == true)
              'camp_emergency_contact': {
                'name': v['contact_name'],
                'phone': v['contact_phone'],
                'relationship': v['contact_relationship'],
              },
            if (event['requires_camp_details'] == true)
              'camp_dietary_notes': v['camp_dietary_notes'],
          },
        },
      ),
    ),
  );
  bool hasRequirements(AirmiusJson event) {
    final v = event['participation_requirements'];
    return v != null && (v is List ? v.isNotEmpty : '$v'.trim().isNotEmpty);
  }

  Future<void> remove(AirmiusJson row) async {
    if (busy || blocked) return;
    setState(() => busy = true);
    try {
      if (await planningDelete(
            context,
            () => client.deleteFamilyCalendarEntry(planningId(row['id'])!),
          ) &&
          mounted) {
        await reload();
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          final code = e is AirmiusApiException ? e.statusCode : 0;
          blocked = [0, 401, 403, 404, 503, 599].contains(code);
          if ([401, 403, 404].contains(code)) data = {};
          error = planningError(context, e);
        });
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  bool visibleEvent(AirmiusJson e) =>
      person == null ||
      (person == 'self'
          ? e['person_id'] == (data['self'] as Map)['id']
          : '${e['person_id']}' == person!.split(':').first);
  bool visibleEntry(AirmiusJson e) =>
      person == null ||
      (person == 'self'
          ? e['child_user_id'] == null
          : '${e['child_user_id']}:${e['club_id']}' == person);
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(t('family')),
      actions: [
        IconButton(
          tooltip: t('refresh'),
          onPressed: loading || busy ? null : reload,
          icon: const Icon(Icons.refresh),
        ),
        if (data.isNotEmpty)
          IconButton(
            tooltip: t('new'),
            onPressed: loading || busy || blocked ? null : entry,
            icon: const Icon(Icons.add),
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
          Row(
            children: [
              IconButton(
                tooltip: t('previous'),
                onPressed: loading || busy
                    ? null
                    : () {
                        month = DateTime(month.year, month.month - 1);
                        reload();
                      },
                icon: const Icon(Icons.chevron_left),
              ),
              Expanded(
                child: Text(
                  DateFormat.yMMMM().format(month),
                  textAlign: TextAlign.center,
                ),
              ),
              IconButton(
                tooltip: t('next'),
                onPressed: loading || busy
                    ? null
                    : () {
                        month = DateTime(month.year, month.month + 1);
                        reload();
                      },
                icon: const Icon(Icons.chevron_right),
              ),
            ],
          ),
          if (people.isNotEmpty)
            DropdownButtonFormField<String>(
              key: ValueKey('person:$person'),
              initialValue: person,
              isExpanded: true,
              decoration: InputDecoration(labelText: t('person')),
              items: [
                DropdownMenuItem(value: null, child: Text(t('family'))),
                for (final p in people)
                  DropdownMenuItem(
                    value: p['id'] as String,
                    child: Text(
                      '${p['name']}',
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
              ],
              onChanged: loading || busy
                  ? null
                  : (v) => setState(() => person = v),
            ),
          if (error != null) PlanningError(error!),
          if (loading || busy) const Center(child: CircularProgressIndicator()),
          if (data.isNotEmpty) ...[
            const SizedBox(height: 16),
            Text(t('events'), style: Theme.of(context).textTheme.titleMedium),
            if (planningRows(data['events']).where(visibleEvent).isEmpty)
              Text(t('empty')),
            for (final event in planningRows(
              data['events'],
            ).where(visibleEvent)) ...[
              const Divider(),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('${event['title']}'),
                subtitle: Text(
                  '${event['person_name']} · ${planningTime(event['starts_at'])}\n${event['location'] ?? ''}\n${t('${event['response_status'] ?? event['status']}')}',
                ),
                trailing: event['can_respond'] == true
                    ? IconButton(
                        tooltip: t('response'),
                        onPressed: busy || blocked
                            ? null
                            : () => response(event),
                        icon: const Icon(Icons.how_to_reg_outlined),
                      )
                    : const Icon(Icons.lock_outline),
              ),
            ],
            const SizedBox(height: 16),
            Text(t('entries'), style: Theme.of(context).textTheme.titleMedium),
            if (planningRows(data['entries']).where(visibleEntry).isEmpty)
              Text(t('empty')),
            for (final row in planningRows(
              data['entries'],
            ).where(visibleEntry)) ...[
              const Divider(),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('${row['title']}'),
                subtitle: Text(
                  '${planningTime(row['starts_at'])} – ${planningTime(row['ends_at'])}\n${row['location'] ?? ''}',
                ),
                trailing: IconButton(
                  tooltip: t('edit'),
                  onPressed: busy || blocked ? null : () => entry(row),
                  icon: const Icon(Icons.edit_outlined),
                ),
              ),
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: IconButton(
                  tooltip: t('delete'),
                  onPressed: busy || blocked ? null : () => remove(row),
                  icon: const Icon(Icons.delete_outline),
                ),
              ),
            ],
          ],
        ],
      ),
    ),
  );
}
