import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_services_scope.dart';
import '../models/club_summary.dart';

List<Map<String, dynamic>> serviceHourContributionPeriods(Object? value) {
  if (value is! List) return const [];
  return value
      .whereType<Map<String, dynamic>>()
      // The endpoint is contribution-only. Older servers omitted `type`.
      .where(
        (period) => period['type'] == null || period['type'] == 'contribution',
      )
      .toList();
}

String formatServiceHourDateDigits(String value) {
  var digits = value.replaceAll(RegExp(r'\D'), '');
  if (digits.length > 8) digits = digits.substring(0, 8);
  if (digits.length < 2) return digits;
  if (digits.length == 2) return '$digits.';
  if (digits.length < 4) {
    return '${digits.substring(0, 2)}.${digits.substring(2)}';
  }
  if (digits.length == 4) {
    return '${digits.substring(0, 2)}.${digits.substring(2)}.';
  }
  return '${digits.substring(0, 2)}.${digits.substring(2, 4)}.${digits.substring(4)}';
}

String? serviceHourApiDate(String value) {
  final match = RegExp(r'^(\d{2})\.(\d{2})\.(\d{4})$').firstMatch(value.trim());
  if (match == null) return null;
  final day = int.parse(match.group(1)!);
  final month = int.parse(match.group(2)!);
  final year = int.parse(match.group(3)!);
  if (year < 1900) return null;
  final parsed = DateTime(year, month, day);
  if (parsed.year != year || parsed.month != month || parsed.day != day) {
    return null;
  }
  return '${match.group(3)}-${match.group(2)}-${match.group(1)}';
}

String serviceHourDisplayDate(Object? value) {
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch('$value');
  if (match == null) return '$value';
  return '${match.group(3)}.${match.group(2)}.${match.group(1)}';
}

class ServiceHourDateInputFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    var digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final oldDigits = oldValue.text.replaceAll(RegExp(r'\D'), '');
    if (newValue.text.length < oldValue.text.length &&
        digits.length == oldDigits.length &&
        digits.isNotEmpty) {
      final selectionEnd = newValue.selection.end;
      final cursor = selectionEnd < 0
          ? 0
          : (selectionEnd > newValue.text.length
                ? newValue.text.length
                : selectionEnd);
      final digitsBeforeCursor = newValue.text
          .substring(0, cursor)
          .replaceAll(RegExp(r'\D'), '')
          .length;
      final candidate = digitsBeforeCursor - 1;
      final removeAt = candidate < 0
          ? 0
          : (candidate >= digits.length ? digits.length - 1 : candidate);
      digits = digits.substring(0, removeAt) + digits.substring(removeAt + 1);
    }
    if (digits.length > 8) digits = digits.substring(0, 8);
    final formatted = formatServiceHourDateDigits(digits);
    return TextEditingValue(
      text: formatted,
      selection: TextSelection.collapsed(offset: formatted.length),
    );
  }
}

class ClubServiceHoursScreen extends StatefulWidget {
  const ClubServiceHoursScreen({
    super.key,
    required this.club,
    this.initialPeriodId,
    this.highlightedRecordId,
  });
  final ClubSummary club;
  final int? initialPeriodId;
  final int? highlightedRecordId;

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
  final GlobalKey _highlightedRecordKey = GlobalKey();
  bool _didRevealHighlightedRecord = false;

  AirmiusApiClient get client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  List<Map<String, dynamic>> items(String key) =>
      (data[key] as List? ?? []).whereType<Map<String, dynamic>>().toList();

  @override
  void initState() {
    super.initState();
    periodId = widget.initialPeriodId;
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
      if (mounted) {
        setState(() => busy = false);
        _revealHighlightedRecord();
      }
    }
  }

  void _revealHighlightedRecord() {
    if (_didRevealHighlightedRecord || widget.highlightedRecordId == null) {
      return;
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || _didRevealHighlightedRecord) return;
      final target = _highlightedRecordKey.currentContext;
      if (target == null) return;
      _didRevealHighlightedRecord = true;
      Scrollable.ensureVisible(
        target,
        duration: const Duration(milliseconds: 450),
        curve: Curves.easeOutCubic,
        alignment: .15,
      );
    });
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
    Map<String, dynamic> result, {
    String? successMessage,
  }) async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await client.writeClubServiceHours(widget.club.id, resource, result);
      await load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(successMessage ?? '$title gespeichert.')),
        );
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
    _Field('served_on', 'Datum (TT.MM.JJJJ)', date: true),
    _Field('start_time', 'Beginnzeit (HH:MM)', time: true),
    _Field('end_time', 'Endzeit (HH:MM)', time: true),
    _Field('notes', 'Notiz', optional: true),
    _Field(
      'notification_channel',
      'Mitglied informieren',
      initial: 'app',
      options: const [
        (value: 'none', label: 'Nicht informieren'),
        (value: 'app', label: 'In der App benachrichtigen'),
        (value: 'email', label: 'Per E-Mail informieren'),
        (value: 'app_email', label: 'App und E-Mail'),
      ],
    ),
  ]);

  void addExemption() => action('Befreiung', 'service-hour-exemptions', [
    _Field('period_id', 'Beitragsjahr', fixed: periodId),
    choice('user_id', 'Mitglied', items('members'), 'user_id', 'name'),
    _Field('minutes', 'Befreite Minuten', number: true),
    _Field('reason', 'Begründung'),
  ]);

  Future<void> editRecord(Map<String, dynamic> record) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => _ServiceHourForm(
          title: 'Dienst bearbeiten',
          fields: [
            _Field(
              'user_id',
              'Mitglied',
              initial: '${record['user_id']}',
              options: [
                for (final member in items('members'))
                  (value: '${member['user_id']}', label: '${member['name']}'),
              ],
            ),
            _Field('title', 'Tätigkeit', initial: '${record['title'] ?? ''}'),
            _Field(
              'served_on',
              'Datum (TT.MM.JJJJ)',
              date: true,
              initial: serviceHourDisplayDate(record['served_on']),
            ),
            for (final entry in {
              'start_time': 'Beginnzeit',
              'end_time': 'Endzeit',
            }.entries)
              _Field(
                entry.key,
                '${entry.value} (HH:MM)',
                time: true,
                optional: record['start_time'] == null,
                initial: record[entry.key]?.toString(),
              ),
            if (record['start_time'] == null)
              _Field(
                'minutes',
                'Dauer in Minuten',
                number: true,
                initial: '${record['minutes'] ?? ''}',
              ),
            _Field(
              'notes',
              'Notiz',
              optional: true,
              initial: '${record['notes'] ?? ''}',
            ),
            const _Field(
              'notification_channel',
              'Änderung mitteilen',
              initial: 'app',
              options: [
                (value: 'none', label: 'Nicht informieren'),
                (value: 'app', label: 'In der App benachrichtigen'),
                (value: 'email', label: 'Per E-Mail informieren'),
                (value: 'app_email', label: 'App und E-Mail'),
              ],
            ),
          ],
          onSave: (payload) async {
            try {
              await client.updateClubServiceHourRecord(
                widget.club.id,
                (record['id'] as num).toInt(),
                payload,
              );
              return null;
            } catch (e) {
              return e is AirmiusApiException
                  ? e.userMessage
                  : 'Änderung konnte nicht gespeichert werden.';
            }
          },
        ),
      ),
    );
    if (saved == true && mounted) {
      await load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Dienststunde aktualisiert.')),
        );
      }
    }
  }

  Future<void> deleteRecord(Map<String, dynamic> record) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Dienststunde entfernen?'),
        content: Text(
          '„${record['title']}“ wird dauerhaft entfernt. Diese Aktion kann nicht rückgängig gemacht werden.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Abbrechen'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Entfernen'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() {
      busy = true;
      error = null;
    });
    try {
      await client.deleteClubServiceHourRecord(
        widget.club.id,
        (record['id'] as num).toInt(),
      );
      await load();
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Dienststunde entfernt.')));
      }
    } catch (e) {
      if (mounted) {
        setState(
          () => error = e is AirmiusApiException
              ? e.userMessage
              : 'Dienststunde konnte nicht entfernt werden.',
        );
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> unconfirmRecord(Map<String, dynamic> record) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Bestätigung aufheben?'),
        content: Text(
          '„${record['title']}“ wird wieder als offen markiert. Danach kannst du den Dienst bearbeiten oder entfernen.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Abbrechen'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Bestätigung aufheben'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    await execute(
      'Bestätigung aufgehoben',
      'service-hour-records/${record['id']}/unconfirm',
      {},
      successMessage: 'Bestätigung aufgehoben. Der Dienst ist wieder offen.',
    );
  }

  Future<void> respondToRecord(
    Map<String, dynamic> record,
    String decision,
  ) async {
    String? reason;
    if (decision == 'disputed') {
      final savedReason = await Navigator.of(context).push<bool>(
        MaterialPageRoute(
          builder: (_) => _ServiceHourForm(
            title: 'Dienststunde beanstanden',
            fields: const [_Field('reason', 'Begründung')],
            onSave: (payload) async {
              final value = '${payload['reason'] ?? ''}'.trim();
              if (value.isEmpty) return 'Bitte eine Begründung eingeben.';
              reason = value;
              return null;
            },
          ),
        ),
      );
      if (savedReason != true || !mounted) return;
    } else {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Dienststunde bestätigen?'),
          content: Text(
            'Du bestätigst, dass „${record['title']}“ mit den angegebenen Daten stimmt.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Abbrechen'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Bestätigen'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
    }

    setState(() {
      busy = true;
      error = null;
    });
    try {
      await client.respondToClubServiceHourRecord(
        widget.club.id,
        (record['id'] as num).toInt(),
        decision,
        reason: reason,
      );
      await load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              decision == 'accepted'
                  ? 'Dienststunde bestätigt.'
                  : 'Beanstandung wurde gesendet.',
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(
          () => error = e is AirmiusApiException
              ? e.userMessage
              : 'Rückmeldung konnte nicht gespeichert werden.',
        );
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

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
              key: record['id'] == widget.highlightedRecordId
                  ? _highlightedRecordKey
                  : null,
              color: record['id'] == widget.highlightedRecordId
                  ? Theme.of(context).colorScheme.primaryContainer
                  : null,
              child: Column(
                children: [
                  ListTile(
                    title: Text(
                      '${record['title']} · ${nameFor(record['user_id'])}',
                    ),
                    subtitle: Text(
                      '${serviceHourDisplayDate(record['served_on'])} · '
                      '${record['start_time'] == null ? '' : "${record['start_time']}–${record['end_time']} · "}'
                      '${hours(record['minutes'])} · '
                      '${record['status'] == 'confirmed' ? 'Bestätigt' : 'Offen'}',
                    ),
                  ),
                  if (record['notification_channel'] != null &&
                      record['notification_channel'] != 'none')
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          'Informiert: ${switch (record['notification_channel']) {
                            'email' => 'E-Mail',
                            'app_email' => 'App und E-Mail',
                            _ => 'App',
                          }}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                    ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: Text(switch (record['member_response_status'] ??
                          'pending') {
                        'accepted' => 'Rückmeldung: Vom Mitglied bestätigt',
                        'disputed' =>
                          'Rückmeldung: Beanstandet${record['member_response_reason'] == null ? '' : ' – ${record['member_response_reason']}'}',
                        _ => 'Rückmeldung: Ausstehend',
                      }, style: Theme.of(context).textTheme.bodySmall),
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
                    Wrap(
                      spacing: 4,
                      runSpacing: 4,
                      children: [
                        if (record['status'] == 'pending') ...[
                          TextButton.icon(
                            onPressed: () => editRecord(record),
                            icon: const Icon(Icons.edit_outlined),
                            label: const Text('Bearbeiten'),
                          ),
                          TextButton.icon(
                            onPressed: () => deleteRecord(record),
                            icon: const Icon(Icons.delete_outline),
                            label: const Text('Entfernen'),
                          ),
                          if (record['member_response_status'] == 'accepted')
                            TextButton(
                              onPressed: () => action(
                                'Dienst bestätigen',
                                'service-hour-records/${record['id']}/confirm',
                                [],
                              ),
                              child: const Text('Endgültig bestätigen'),
                            )
                          else
                            Padding(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 10,
                              ),
                              child: Text(
                                record['member_response_status'] == 'disputed'
                                    ? 'Beanstandung prüfen'
                                    : 'Wartet auf Rückmeldung',
                              ),
                            ),
                        ],
                        if (record['status'] == 'confirmed') ...[
                          if ((record['corrections'] as List? ?? []).isEmpty)
                            TextButton.icon(
                              onPressed: () => unconfirmRecord(record),
                              icon: const Icon(Icons.undo),
                              label: const Text('Bestätigung aufheben'),
                            ),
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
                      ],
                    ),
                  if (AirmiusServicesScope.of(context).authState.user?.id ==
                          record['user_id'] &&
                      record['status'] == 'pending' &&
                      (record['member_response_status'] ?? 'pending') ==
                          'pending')
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        FilledButton.icon(
                          onPressed: () => respondToRecord(record, 'accepted'),
                          icon: const Icon(Icons.check),
                          label: const Text('Bestätigen'),
                        ),
                        OutlinedButton.icon(
                          onPressed: () => respondToRecord(record, 'disputed'),
                          icon: const Icon(Icons.flag_outlined),
                          label: const Text('Beanstanden'),
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
    this.date = false,
    this.time = false,
    this.optional = false,
    this.fixed,
    this.initial,
    this.options,
  });
  final String key, label;
  final bool number, date, time, optional;
  final int? fixed;
  final String? initial;
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

  int? clockMinutes(String? value) {
    if (value == null ||
        !RegExp(r'^(?:[01]\d|2[0-3]):[0-5]\d$').hasMatch(value))
      return null;
    return int.parse(value.substring(0, 2)) * 60 +
        int.parse(value.substring(3));
  }

  @override
  void initState() {
    super.initState();
    for (final field in widget.fields) {
      if (field.initial != null) values[field.key] = field.initial!;
    }
  }

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
                  initialValue: field.initial,
                  items: [
                    for (final o in field.options!)
                      DropdownMenuItem(value: o.value, child: Text(o.label)),
                  ],
                  onChanged: (value) => values[field.key] = value ?? '',
                  validator: (value) =>
                      value == null ? 'Bitte auswählen.' : null,
                )
              else if (field.time)
                TextFormField(
                  key: ValueKey('${field.key}-${values[field.key]}'),
                  initialValue: values[field.key],
                  readOnly: true,
                  decoration: InputDecoration(
                    labelText: field.label,
                    hintText: '08:30',
                    suffixIcon: const Icon(Icons.schedule),
                  ),
                  onTap: () async {
                    final minutes =
                        clockMinutes(values[field.key]) ??
                        clockMinutes(values['start_time']);
                    final time = await showTimePicker(
                      context: context,
                      initialTime: minutes == null
                          ? TimeOfDay.now()
                          : TimeOfDay(
                              hour: minutes ~/ 60,
                              minute: minutes % 60,
                            ),
                    );
                    if (time == null || !mounted) return;
                    setState(
                      () => values[field.key] =
                          '${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}',
                    );
                  },
                  validator: (value) {
                    if (field.optional &&
                        (value ?? '').isEmpty &&
                        (values[field.key == 'start_time'
                                    ? 'end_time'
                                    : 'start_time'] ??
                                '')
                            .isEmpty)
                      return null;
                    if (clockMinutes(value) == null)
                      return 'Bitte eine Uhrzeit von 00:00 bis 23:59 eingeben.';
                    if (field.key == 'end_time' &&
                        value == values['start_time'])
                      return 'Beginn und Ende müssen verschieden sein.';
                    return null;
                  },
                )
              else
                TextFormField(
                  initialValue: field.initial,
                  decoration: InputDecoration(labelText: field.label),
                  keyboardType: field.number || field.date
                      ? TextInputType.number
                      : TextInputType.text,
                  inputFormatters: field.date
                      ? [ServiceHourDateInputFormatter()]
                      : null,
                  onChanged: (value) => values[field.key] = value,
                  validator: (value) {
                    if (!field.optional &&
                        (value == null || value.trim().isEmpty)) {
                      return 'Bitte ${field.label.toLowerCase()} eingeben.';
                    }
                    if (field.date && serviceHourApiDate(value ?? '') == null) {
                      return 'Bitte ein gültiges Datum im Format TT.MM.JJJJ eingeben.';
                    }
                    return null;
                  },
                ),
            ],
          if (clockMinutes(values['start_time']) case final int start)
            if (clockMinutes(values['end_time']) case final int end)
              Text(
                'Dauer: ${(end - start + 1440) % 1440} Minuten${end < start ? ' · Ende am Folgetag' : ''}',
              ),
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
                      payload[field.key] = field.date
                          ? serviceHourApiDate('$value')
                          : field.number ||
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
