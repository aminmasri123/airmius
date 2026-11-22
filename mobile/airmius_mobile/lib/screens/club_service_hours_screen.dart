import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_services_scope.dart';
import '../models/club_summary.dart';

List<Map<String, dynamic>> serviceHourContributionPeriods(Object? value) {
  if (value is! List) return const [];
  return value
      .whereType<Map<String, dynamic>>()
      // The endpoint is contribution-only. Older servers omitted `type`.
      .where(
        (period) =>
            period['type'] == null || period['type'] == 'contribution',
      )
      .toList();
}

class ClubServiceHoursScreen extends StatefulWidget {
  const ClubServiceHoursScreen({super.key, required this.club});
  final ClubSummary club;

  @override
  State<ClubServiceHoursScreen> createState() => _ClubServiceHoursScreenState();
}

class _ClubServiceHoursScreenState extends State<ClubServiceHoursScreen> {
  List<Map<String, dynamic>> periods = [];
  List<Map<String, dynamic>> roles = [];
  Map<String, dynamic> data = {};
  int? periodId;
  bool busy = true;
  String? error;

  AirmiusApiClient get client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  List<Map<String, dynamic>> items(String key) =>
      (data[key] as List? ?? []).whereType<Map<String, dynamic>>().toList();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  Future<void> load() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final years = await client.clubServiceHourPeriods(widget.club.id);
      final available = serviceHourContributionPeriods(years['data']);
      final chosen = available.any((p) => p['id'] == periodId)
          ? periodId
          : (available.isEmpty ? null : (available.first['id'] as num).toInt());
      Map<String, dynamic> summary = {};
      List<Map<String, dynamic>> roleList = [];
      if (chosen != null) {
        summary = Map<String, dynamic>.from(
          (await client.clubServiceHours(widget.club.id, chosen))['data']
              as Map,
        );
        roleList = (summary['roles'] as List? ?? [])
            .whereType<Map<String, dynamic>>()
            .toList();
      }
      if (mounted) {
        setState(() {
          periods = available;
          periodId = chosen;
          data = summary;
          roles = roleList;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(
          () => error = e is AirmiusApiException
              ? e.userMessage
              : 'Dienststunden konnten nicht geladen werden.',
        );
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  String nameFor(Object? id) =>
      items('members')
          .where((m) => m['user_id'] == id)
          .map((m) => '${m['name']}')
          .firstOrNull ??
      'Mitglied #$id';
  String hours(Object? minutes) =>
      '${((minutes as num?)?.toDouble() ?? 0) / 60} Std.';
  bool get locked => items('requirements').any((r) => r['locked'] == true);

  Future<void> action(
    String title,
    String resource,
    List<_Field> fields,
  ) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => _ServiceHourForm(
          title: title,
          fields: fields,
          onSave: (payload) async {
            try {
              await client.writeClubServiceHours(
                widget.club.id,
                resource,
                payload,
              );
              return null;
            } catch (e) {
              return e is AirmiusApiException
                  ? e.userMessage
                  : 'Speichern fehlgeschlagen. Bitte erneut versuchen.';
            }
          },
        ),
      ),
    );
    if (saved == true && mounted) {
      await load();
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$title gespeichert.')));
      }
    }
  }

  Future<void> execute(
    String title,
    String resource,
    Map<String, dynamic> result,
  ) async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await client.writeClubServiceHours(widget.club.id, resource, result);
      await load();
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$title gespeichert.')));
      }
    } catch (e) {
      if (mounted) {
        setState(
          () => error = e is AirmiusApiException
              ? e.userMessage
              : 'Speichern fehlgeschlagen. Bitte erneut versuchen.',
        );
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  _Field choice(
    String key,
    String label,
    List<Map<String, dynamic>> options,
    String idKey,
    String labelKey,
  ) => _Field(
    key,
    label,
    options: [
      for (final option in options)
        (value: '${option[idKey]}', label: '${option[labelKey]}'),
    ],
  );

  void addRequirement() =>
      action('Stundenpflicht', 'service-hour-requirements', [
        _Field('period_id', 'Beitragsjahr', fixed: periodId),
        choice('role_definition_id', 'Vereinsrolle', roles, 'id', 'name'),
        _Field('required_minutes', 'Pflicht in Minuten', number: true),
      ]);

  void addRecord() => action('Dienst erfassen', 'service-hour-records', [
    _Field('period_id', 'Beitragsjahr', fixed: periodId),
    choice('user_id', 'Mitglied', items('members'), 'user_id', 'name'),
    _Field('title', 'Tätigkeit'),
    _Field('served_on', 'Datum (JJJJ-MM-TT)'),
    _Field('minutes', 'Dauer in Minuten', number: true),
    _Field('notes', 'Notiz', optional: true),
  ]);

  void addExemption() => action('Befreiung', 'service-hour-exemptions', [
    _Field('period_id', 'Beitragsjahr', fixed: periodId),
    choice('user_id', 'Mitglied', items('members'), 'user_id', 'name'),
    _Field('minutes', 'Befreite Minuten', number: true),
    _Field('reason', 'Begründung'),
  ]);

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Dienststunden'),
      actions: [
        IconButton(
          onPressed: busy ? null : load,
          icon: const Icon(Icons.refresh),
          tooltip: 'Aktualisieren',
        ),
      ],
    ),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text(
          'Pflicht, geleistete Dienste, Korrekturen und Befreiungen je Beitragsjahr.',
        ),
        if (busy) const LinearProgressIndicator(),
        if (error != null)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Text(
              error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ),
        if (periods.isEmpty && !busy)
          const ListTile(
            title: Text('Kein Beitragsjahr vorhanden'),
            subtitle: Text(
              'Lege zuerst unter Vereinsorganisation → Vereinsjahre ein Beitragsjahr an.',
            ),
          ),
        if (periods.isNotEmpty)
          DropdownButtonFormField<int>(
            key: ValueKey(periodId),
            initialValue: periodId,
            decoration: const InputDecoration(labelText: 'Beitragsjahr'),
            items: [
              for (final p in periods)
                DropdownMenuItem(
                  value: (p['id'] as num).toInt(),
                  child: Text('${p['name']}'),
                ),
            ],
            onChanged: busy
                ? null
                : (value) {
                    setState(() => periodId = value);
                    load();
                  },
          ),
        if (periodId != null && !busy) ...[
          const SizedBox(height: 16),
          if (locked)
            const Padding(
              padding: EdgeInsets.only(bottom: 12),
              child: Text(
                'Dieser Zeitraum ist gesperrt. Neue Einträge und Änderungen sind nicht möglich.',
              ),
            ),
          if (data['can_manage'] == true && !locked)
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                OutlinedButton.icon(
                  onPressed: roles.isEmpty ? null : addRequirement,
                  icon: const Icon(Icons.rule),
                  label: const Text('Pflicht festlegen'),
                ),
                OutlinedButton.icon(
                  onPressed: addRecord,
                  icon: const Icon(Icons.add),
                  label: const Text('Dienst erfassen'),
                ),
                OutlinedButton.icon(
                  onPressed: addExemption,
                  icon: const Icon(Icons.verified_outlined),
                  label: const Text('Befreiung'),
                ),
              ],
            ),
          const SizedBox(height: 20),
          Text('Stundenpflicht', style: Theme.of(context).textTheme.titleLarge),
          for (final requirement in items('requirements'))
            Card(
              child: ListTile(
                title: Text('${requirement['role_name']}'),
                subtitle: Text(
                  '${hours(requirement['required_minutes'])} Pflicht'
                  '${requirement['locked'] == true ? ' · Gesperrt' : ''}',
                ),
                trailing: data['can_manage'] == true && !locked
                    ? IconButton(
                        icon: const Icon(Icons.lock_outline),
                        tooltip: 'Zeitraum sperren',
                        onPressed: () async {
                          final confirmed = await showDialog<bool>(
                            context: context,
                            builder: (context) => AlertDialog(
                              title: const Text('Zeitraum sperren?'),
                              content: const Text(
                                'Danach können Dienste und Korrekturen '
                                'in diesem Beitragsjahr nicht mehr erfasst werden.',
                              ),
                              actions: [
                                TextButton(
                                  onPressed: () =>
                                      Navigator.pop(context, false),
                                  child: const Text('Abbrechen'),
                                ),
                                FilledButton(
                                  onPressed: () => Navigator.pop(context, true),
                                  child: const Text('Sperren'),
                                ),
                              ],
                            ),
                          );
                          if (confirmed == true && mounted) {
                            await execute(
                              'Zeitraum sperren',
                              'service-hour-requirements/${requirement['id']}/lock',
                              {},
                            );
                          }
                        },
                      )
                    : null,
              ),
            ),
          const SizedBox(height: 20),
          Text(
            'Saldo je Mitglied',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          for (final member in items('members'))
            Card(
              child: ListTile(
                title: Text('${member['name']}'),
                subtitle: Text(
                  'Pflicht ${hours(member['required_minutes'])} · '
                  'bestätigt ${hours(member['confirmed_minutes'])} · '
                  'Korrektur ${hours(member['correction_minutes'])} · '
                  'Befreiung ${hours(member['exempted_minutes'])}',
                ),
                trailing: Text(hours(member['remaining_minutes'])),
              ),
            ),
          const SizedBox(height: 20),
          Text('Dienste', style: Theme.of(context).textTheme.titleLarge),
          if (items('records').isEmpty)
            const Text('Noch keine Dienste erfasst.'),
          for (final record in items('records'))
            Card(
              child: Column(
                children: [
                  ListTile(
                    title: Text(
                      '${record['title']} · ${nameFor(record['user_id'])}',
                    ),
                    subtitle: Text(
                      '${record['served_on']} · ${hours(record['minutes'])} · '
                      '${record['status'] == 'confirmed' ? 'Bestätigt' : 'Offen'}',
                    ),
                  ),
                  for (final correction
                      in (record['corrections'] as List? ?? [])
                          .whereType<Map>())
                    ListTile(
                      dense: true,
                      title: Text(
                        'Korrektur ${hours(correction['minutes_delta'])}',
                      ),
                      subtitle: Text('${correction['reason']}'),
                    ),
                  if (data['can_manage'] == true && !locked)
                    Row(
                      children: [
                        if (record['status'] == 'pending')
                          TextButton(
                            onPressed: () => action(
                              'Dienst bestätigen',
                              'service-hour-records/${record['id']}/confirm',
                              [],
                            ),
                            child: const Text('Bestätigen'),
                          ),
                        if (record['status'] == 'confirmed')
                          TextButton(
                            onPressed: () => action(
                              'Korrektur',
                              'service-hour-records/${record['id']}/corrections',
                              [
                                _Field(
                                  'minutes_delta',
                                  'Änderung in Minuten (+/−)',
                                  number: true,
                                ),
                                _Field('reason', 'Begründung'),
                              ],
                            ),
                            child: const Text('Korrigieren'),
                          ),
                      ],
                    ),
                ],
              ),
            ),
          const SizedBox(height: 20),
          Text('Befreiungen', style: Theme.of(context).textTheme.titleLarge),
          for (final exemption in items('exemptions'))
            ListTile(
              title: Text(nameFor(exemption['user_id'])),
              subtitle: Text('${exemption['reason']}'),
              trailing: Text(hours(exemption['minutes'])),
            ),
        ],
      ],
    ),
  );
}

class _Field {
  const _Field(
    this.key,
    this.label, {
    this.number = false,
    this.optional = false,
    this.fixed,
    this.options,
  });
  final String key, label;
  final bool number, optional;
  final int? fixed;
  final List<({String value, String label})>? options;
}

class _ServiceHourForm extends StatefulWidget {
  const _ServiceHourForm({
    required this.title,
    required this.fields,
    required this.onSave,
  });
  final String title;
  final List<_Field> fields;
  final Future<String?> Function(Map<String, dynamic>) onSave;
  @override
  State<_ServiceHourForm> createState() => _ServiceHourFormState();
}

class _ServiceHourFormState extends State<_ServiceHourForm> {
  final formKey = GlobalKey<FormState>();
  final values = <String, String>{};
  bool saving = false;
  String? error;
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.title)),
    body: Form(
      key: formKey,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (widget.fields.isEmpty)
            const Text('Diesen Dienst verbindlich bestätigen?'),
          if (error != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text(
                error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          for (final field in widget.fields)
            if (field.fixed == null) ...[
              const SizedBox(height: 12),
              if (field.options != null)
                DropdownButtonFormField<String>(
                  decoration: InputDecoration(labelText: field.label),
                  items: [
                    for (final o in field.options!)
                      DropdownMenuItem(value: o.value, child: Text(o.label)),
                  ],
                  onChanged: (value) => values[field.key] = value ?? '',
                  validator: (value) =>
                      value == null ? 'Bitte auswählen.' : null,
                )
              else
                TextFormField(
                  decoration: InputDecoration(labelText: field.label),
                  keyboardType: field.number
                      ? TextInputType.number
                      : TextInputType.text,
                  onChanged: (value) => values[field.key] = value,
                  validator: (value) =>
                      !field.optional && (value == null || value.trim().isEmpty)
                      ? 'Bitte ${field.label.toLowerCase()} eingeben.'
                      : null,
                ),
            ],
          const SizedBox(height: 24),
          FilledButton(
            onPressed: saving
                ? null
                : () async {
                    if (!formKey.currentState!.validate()) return;
                    final payload = <String, dynamic>{};
                    for (final field in widget.fields) {
                      final value = field.fixed ?? values[field.key];
                      if (value == null || value == '') continue;
                      payload[field.key] =
                          field.number ||
                              field.fixed != null ||
                              field.options != null
                          ? int.tryParse('$value') ?? value
                          : value;
                    }
                    setState(() {
                      saving = true;
                      error = null;
                    });
                    final message = await widget.onSave(payload);
                    if (!mounted) return;
                    if (message == null) {
                      Navigator.pop(this.context, true);
                    } else {
                      setState(() {
                        saving = false;
                        error = message;
                      });
                    }
                  },
            child: Text(saving ? 'Speichere …' : 'Speichern'),
          ),
        ],
      ),
    ),
  );
}
