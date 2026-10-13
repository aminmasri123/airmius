import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_date_input.dart';

AirmiusJson governanceMap(dynamic value) =>
    value is Map ? value.cast<String, dynamic>() : {};
List<AirmiusJson> governanceMaps(dynamic value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => item.cast<String, dynamic>())
          .toList()
    : [];
int? governanceInt(dynamic value) =>
    value is int ? value : int.tryParse('$value');
String governanceError(Object error) => error is AirmiusApiException
    ? error.userMessage
    : 'Die Daten konnten nicht gespeichert oder geladen werden.';
String governancePersonKey(AirmiusJson person) =>
    '${person['kind']}:${person['id']}';
Set<String> governanceActiveBodyKeys(AirmiusJson body, DateTime date) {
  final asOf = formatAirmiusApiDate(date)!;
  return governanceMaps(body['assignments'])
      .where((assignment) {
        final starts = assignment['starts_on'] as String?;
        final ends = assignment['ends_on'] as String?;
        return (starts == null || starts.compareTo(asOf) <= 0) &&
            (ends == null || ends.compareTo(asOf) >= 0);
      })
      .map(
        (assignment) => assignment['user_id'] != null
            ? 'user:${assignment['user_id']}'
            : 'external:${assignment['club_external_member_id']}',
      )
      .toSet();
}

String governanceLabel(dynamic value) =>
    const {
      'board': 'Vorstandssitzung',
      'committee': 'Ausschusssitzung',
      'general_assembly': 'Mitgliederversammlung',
      'draft': 'Entwurf',
      'invited': 'Eingeladen',
      'held': 'Durchgeführt',
      'cancelled': 'Abgesagt',
      'body': 'Gremium',
      'club_members': 'Vereinsmitglieder',
      'custom': 'Ausgewählte Personen',
      'pending': 'Ausstehend',
      'sent': 'Versendet',
      'delivered': 'Zugestellt',
      'failed': 'Fehlgeschlagen',
      'bounced': 'Unzustellbar',
      'motion': 'Antrag',
      'election': 'Wahl',
      'open': 'Offen',
      'named': 'Namentlich',
      'secret': 'Geheim',
      'closed': 'Abgeschlossen',
      'simple': 'Einfache Mehrheit',
      'absolute': 'Absolute Mehrheit',
      'yes': 'Ja',
      'no': 'Nein',
      'abstain': 'Enthaltung',
      'accepted': 'Angenommen',
      'rejected': 'Abgelehnt',
      'no_quorum': 'Quorum nicht erreicht',
    }[value] ??
    '$value';

class ClubGovernanceMeetingForm extends StatefulWidget {
  const ClubGovernanceMeetingForm({
    super.key,
    this.meeting,
    required this.governance,
    required this.save,
  });
  final AirmiusJson? meeting;
  final AirmiusJson governance;
  final Future<int> Function(AirmiusJson) save;
  @override
  State<ClubGovernanceMeetingForm> createState() =>
      _ClubGovernanceMeetingFormState();
}

class _ClubGovernanceMeetingFormState extends State<ClubGovernanceMeetingForm> {
  static const _fieldGap = 20.0;
  static const _sectionGap = 28.0;

  final _key = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _location;
  late final TextEditingController _notes;
  late String _type;
  late String _status;
  late String _scope;
  int? _bodyId;
  DateTime? _scheduled;
  DateTime? _eligibility;
  DateTime? _motionsDue;
  late bool _useCustomEligibility;
  late bool _useMotionsDeadline;
  final _recipients = <String>{};
  final _votingRights = <String, bool>{};
  final _agenda = <(TextEditingController, TextEditingController)>[];
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final item = widget.meeting ?? {};
    _title = TextEditingController(text: '${item['title'] ?? ''}');
    _location = TextEditingController(text: '${item['location_name'] ?? ''}');
    _notes = TextEditingController(text: '${item['notes'] ?? ''}');
    final bodies = governanceMaps(widget.governance['bodies']);
    final defaultBody = bodies.isEmpty
        ? null
        : bodies.firstWhere(
            (body) => body['type'] == 'board',
            orElse: () => bodies.first,
          );
    _type =
        '${item['type'] ?? (defaultBody == null ? 'general_assembly' : 'board')}';
    _status = '${item['status'] ?? 'draft'}';
    _scope =
        '${item['participant_scope'] ?? (defaultBody == null ? 'club_members' : 'body')}';
    _bodyId = governanceInt(
      governanceMap(item['governance_body'])['id'] ??
          (widget.meeting == null ? governanceMap(defaultBody)['id'] : null),
    );
    _scheduled = DateTime.tryParse('${item['scheduled_at']}')?.toLocal();
    _eligibility = DateTime.tryParse('${item['eligibility_as_of']}');
    _motionsDue = DateTime.tryParse('${item['motions_due_on']}');
    _useCustomEligibility =
        _eligibility != null &&
        (_scheduled == null || !DateUtils.isSameDay(_eligibility, _scheduled));
    _useMotionsDeadline = _motionsDue != null;
    for (final entry in governanceMaps(item['agenda_items'])) {
      _agenda.add((
        TextEditingController(text: '${entry['title']}'),
        TextEditingController(text: '${entry['description'] ?? ''}'),
      ));
    }
    if (widget.meeting == null && _scope == 'body' && _bodyId != null) {
      _recipients.addAll(_activeKeys);
    }
  }

  DateTime get _asOf =>
      (_useCustomEligibility ? _eligibility : null) ??
      _scheduled ??
      DateTime.now();
  Set<String> get _activeKeys => governanceActiveBodyKeys(
    governanceMaps(widget.governance['bodies']).firstWhere(
      (body) => governanceInt(body['id']) == _bodyId,
      orElse: () => {},
    ),
    _asOf,
  );
  List<AirmiusJson> get _recipientOptions =>
      governanceMaps(widget.governance['member_options'])
          .where(
            (person) =>
                _scope != 'body' ||
                _activeKeys.contains(governancePersonKey(person)),
          )
          .toList();
  bool _defaultVoting(AirmiusJson person) =>
      _activeKeys.contains(governancePersonKey(person)) ||
      (person['voting_eligible'] == true ||
          (person['voting_eligible'] == null &&
              person['kind'] == 'user' &&
              (person['membership_status'] ?? 'active') == 'active'));
  bool _voting(AirmiusJson person) =>
      _votingRights[governancePersonKey(person)] ?? _defaultVoting(person);
  void _syncRecipients() {
    if (widget.meeting != null) return;
    if (_scope == 'body') {
      _recipients.removeWhere((key) => !_activeKeys.contains(key));
    }
    _votingRights.clear();
  }

  void _selectBodyMembers() => setState(() {
    _recipients.clear();
    _recipients.addAll(
      governanceMaps(
        widget.governance['member_options'],
      ).map(governancePersonKey).where(_activeKeys.contains),
    );
    _votingRights.clear();
  });

  @override
  void dispose() {
    _title.dispose();
    _location.dispose();
    _notes.dispose();
    for (final pair in _agenda) {
      pair.$1.dispose();
      pair.$2.dispose();
    }
    super.dispose();
  }

  Future<DateTime?> _pickDate(DateTime? value) => showDatePicker(
    context: context,
    initialDate: value ?? DateTime.now(),
    firstDate: DateTime(1900),
    lastDate: DateTime(2200),
  );

  Future<void> _pickScheduled() async {
    final date = await _pickDate(_scheduled);
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_scheduled ?? DateTime.now()),
    );
    if (time != null && mounted) {
      setState(() {
        _scheduled = DateTime(
          date.year,
          date.month,
          date.day,
          time.hour,
          time.minute,
        );
        _syncRecipients();
      });
    }
  }

  Future<void> _save() async {
    if (_busy || !_key.currentState!.validate()) return;
    if (_scope == 'body' && _bodyId == null) {
      setState(() => _error = 'Bitte ein Gremium auswählen.');
      return;
    }
    if (widget.meeting == null && _recipients.isEmpty) {
      setState(() => _error = 'Bitte mindestens eine Person einladen.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    final original = widget.meeting ?? {};
    final payload = <String, dynamic>{
      'type': _type,
      'title': _title.text.trim(),
      'status': _status,
      'club_governance_body_id': _bodyId,
      'club_year_period_id': governanceMap(original['year_period'])['id'],
      'participant_scope': _scope,
      'scheduled_at': _scheduled?.toUtc().toIso8601String(),
      'location_name': _location.text.trim(),
      'notes': _notes.text.trim(),
      'eligibility_as_of': _useCustomEligibility
          ? formatAirmiusApiDate(_eligibility)
          : null,
      'motions_due_on': _useMotionsDeadline
          ? formatAirmiusApiDate(_motionsDue)
          : null,
      'invitation_sent_at': original['invitation_sent_at'],
      'agenda_items': [
        for (final pair in _agenda)
          {'title': pair.$1.text.trim(), 'description': pair.$2.text.trim()},
      ],
      'materials': original['materials'] ?? [],
      'decision_templates': original['decision_templates'] ?? [],
      if (widget.meeting == null)
        'recipients': [
          for (final key in _recipients)
            {
              key.startsWith('user:') ? 'user_id' : 'club_external_member_id':
                  int.parse(key.split(':').last),
              'delivery_status': 'pending',
              'attendance_eligible': true,
              'voting_eligible': _voting(
                governanceMaps(
                  widget.governance['member_options'],
                ).firstWhere((person) => governancePersonKey(person) == key),
              ),
            },
        ],
    };
    try {
      final id = await widget.save(payload);
      if (mounted) Navigator.pop(context, id);
    } catch (error) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = governanceError(error);
        });
      }
    }
  }

  Widget _text(
    TextEditingController controller,
    String title,
    int max, {
    bool required = false,
    int lines = 1,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: _fieldGap),
    child: TextFormField(
      controller: controller,
      enabled: !_busy,
      maxLength: max,
      minLines: lines,
      maxLines: lines,
      decoration: InputDecoration(labelText: title),
      validator: required
          ? (value) =>
                value?.trim().isEmpty != false ? 'Bitte ausfüllen.' : null
          : null,
    ),
  );

  Widget _select(
    String title,
    String value,
    List<String> values,
    ValueChanged<String> change, {
    bool locked = false,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: _fieldGap),
    child: DropdownButtonFormField<String>(
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(labelText: title),
      items: [
        for (final entry in values)
          DropdownMenuItem(
            value: entry,
            child: Text(
              governanceLabel(entry),
              overflow: TextOverflow.ellipsis,
            ),
          ),
      ],
      onChanged: _busy || locked
          ? null
          : (value) {
              if (value != null) setState(() => change(value));
            },
    ),
  );

  Widget _date(
    String title,
    DateTime? value,
    VoidCallback pick,
    VoidCallback clear, {
    bool locked = false,
  }) {
    final scheme = Theme.of(context).colorScheme;
    final empty = value == null;
    return Padding(
      padding: const EdgeInsets.only(bottom: _fieldGap),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: _busy || locked ? null : pick,
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: title,
            prefixIcon: Icon(
              Icons.event_outlined,
              color: empty ? scheme.onSurfaceVariant : null,
            ),
            suffixIcon: IconButton(
              tooltip: 'Datum entfernen',
              icon: const Icon(Icons.clear),
              onPressed: _busy || locked || empty ? null : clear,
            ),
            helperText: empty ? 'Kein Datum festgelegt.' : null,
            enabled: !_busy && !locked,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
          ),
          child: Text(
            empty ? 'Nicht angegeben' : formatAirmiusDate(value),
            style: TextStyle(
              color: empty ? scheme.onSurfaceVariant : null,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ),
    );
  }

  Widget _optionalDateSwitch({
    required String title,
    required String inactiveHint,
    required bool value,
    required ValueChanged<bool> onChanged,
    bool locked = false,
  }) => Padding(
    padding: EdgeInsets.only(bottom: value ? 8 : _fieldGap),
    child: SwitchListTile.adaptive(
      key: ValueKey('meeting-option-$title'),
      contentPadding: EdgeInsets.zero,
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: Text(
        value ? 'Eigenes Datum festgelegt.' : inactiveHint,
        style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant),
      ),
      value: value,
      onChanged: _busy || locked ? null : onChanged,
    ),
  );

  Widget _scheduledField() {
    final scheme = Theme.of(context).colorScheme;
    final empty = _scheduled == null;
    return Padding(
      padding: const EdgeInsets.only(bottom: _fieldGap),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: _busy ? null : _pickScheduled,
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: 'Termin',
            prefixIcon: Icon(
              Icons.schedule_outlined,
              color: empty ? scheme.error : null,
            ),
            suffixIcon: IconButton(
              tooltip: 'Termin entfernen',
              icon: const Icon(Icons.clear),
              onPressed: _busy || empty
                  ? null
                  : () => setState(() {
                      _scheduled = null;
                      _syncRecipients();
                    }),
            ),
            helperText: empty
                ? 'Noch kein Termin gewählt. Tippe hier, um Datum und Uhrzeit festzulegen.'
                : null,
            helperStyle: empty ? TextStyle(color: scheme.error) : null,
            enabled: !_busy,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
          ),
          child: Text(
            empty
                ? 'Termin fehlt'
                : '${formatAirmiusDate(_scheduled)} ${TimeOfDay.fromDateTime(_scheduled!).format(context)}',
            style: TextStyle(
              color: empty ? scheme.error : null,
              fontWeight: empty ? FontWeight.w800 : FontWeight.w500,
            ),
          ),
        ),
      ),
    );
  }

  Widget _bottomActions() => SafeArea(
    minimum: const EdgeInsets.fromLTRB(16, 8, 16, 16),
    child: Row(
      children: [
        Expanded(
          child: OutlinedButton(
            onPressed: _busy ? null : () => Navigator.pop(context),
            child: const Text('Abbrechen'),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: FilledButton(
            onPressed: _busy ? null : _save,
            child: const Text('Speichern'),
          ),
        ),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !_busy,
    child: Scaffold(
      appBar: AppBar(
        title: Text(
          widget.meeting == null ? 'Sitzung anlegen' : 'Sitzung bearbeiten',
        ),
      ),
      bottomNavigationBar: _bottomActions(),
      body: Form(
        key: _key,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 120),
          children: [
            Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 640),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 16),
                        child: Semantics(
                          liveRegion: true,
                          child: Text(
                            _error!,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.error,
                            ),
                          ),
                        ),
                      ),
                    if (_busy)
                      const Padding(
                        padding: EdgeInsets.only(bottom: 16),
                        child: LinearProgressIndicator(),
                      ),
                    _text(_title, 'Titel', 180, required: true),
                    _select('Sitzungsart', _type, [
                      'board',
                      'committee',
                      'general_assembly',
                    ], (value) => _type = value),
                    _select('Status', _status, [
                      'draft',
                      'invited',
                      'held',
                      'cancelled',
                    ], (value) => _status = value),
                    _scheduledField(),
                    _text(_location, 'Ort', 180),
                    _select(
                      'Teilnehmerkreis',
                      _scope,
                      ['body', 'club_members', 'custom'],
                      (value) {
                        _scope = value;
                        _syncRecipients();
                      },
                      locked: widget.meeting != null,
                    ),
                    Padding(
                      padding: const EdgeInsets.only(bottom: _fieldGap),
                      child: DropdownButtonFormField<int>(
                        initialValue: _bodyId,
                        isExpanded: true,
                        decoration: const InputDecoration(labelText: 'Gremium'),
                        items: [
                          const DropdownMenuItem<int>(
                            value: null,
                            child: Text('Ohne Gremium'),
                          ),
                          for (final body in governanceMaps(
                            widget.governance['bodies'],
                          ))
                            DropdownMenuItem(
                              value: governanceInt(body['id']),
                              child: Text(
                                '${body['name']}',
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                        ],
                        onChanged: _busy || widget.meeting != null
                            ? null
                            : (value) => setState(() {
                                _bodyId = value;
                                _syncRecipients();
                              }),
                      ),
                    ),
                    _optionalDateSwitch(
                      title: 'Abweichenden Stichtag festlegen',
                      inactiveHint: 'Automatisch: Datum des Sitzungstermins.',
                      value: _useCustomEligibility,
                      locked: widget.meeting != null,
                      onChanged: (enabled) => setState(() {
                        _useCustomEligibility = enabled;
                        _eligibility = enabled
                            ? (_eligibility ?? _scheduled ?? DateTime.now())
                            : null;
                        _syncRecipients();
                      }),
                    ),
                    if (_useCustomEligibility)
                      _date(
                        'Stichtag der Berechtigung',
                        _eligibility,
                        () async {
                          final date = await _pickDate(_eligibility);
                          if (date != null && mounted) {
                            setState(() {
                              _eligibility = date;
                              _syncRecipients();
                            });
                          }
                        },
                        () => setState(() {
                          _useCustomEligibility = false;
                          _eligibility = null;
                          _syncRecipients();
                        }),
                        locked: widget.meeting != null,
                      ),
                    _optionalDateSwitch(
                      title: 'Antragsfrist festlegen',
                      inactiveHint: 'Keine Antragsfrist festgelegt.',
                      value: _useMotionsDeadline,
                      onChanged: (enabled) => setState(() {
                        _useMotionsDeadline = enabled;
                        _motionsDue = enabled
                            ? (_motionsDue ?? _scheduled ?? DateTime.now())
                            : null;
                      }),
                    ),
                    if (_useMotionsDeadline)
                      _date(
                        'Antragsfrist',
                        _motionsDue,
                        () async {
                          final date = await _pickDate(_motionsDue);
                          if (date != null && mounted) {
                            setState(() => _motionsDue = date);
                          }
                        },
                        () => setState(() {
                          _useMotionsDeadline = false;
                          _motionsDue = null;
                        }),
                      ),
                    const Padding(
                      padding: EdgeInsets.only(top: 8, bottom: 12),
                      child: Text(
                        'Tagesordnung',
                        style: TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ),
                    for (var i = 0; i < _agenda.length; i++)
                      Column(
                        key: ObjectKey(_agenda[i].$1),
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _text(
                            _agenda[i].$1,
                            '${i + 1}. Tagesordnungspunkt',
                            180,
                            required: true,
                          ),
                          _text(_agenda[i].$2, 'Beschreibung', 2000, lines: 2),
                          Align(
                            alignment: Alignment.centerRight,
                            child: IconButton(
                              tooltip: 'Tagesordnungspunkt entfernen',
                              icon: const Icon(Icons.delete_outline),
                              onPressed: _busy
                                  ? null
                                  : () {
                                      final pair = _agenda[i];
                                      setState(() => _agenda.removeAt(i));
                                      WidgetsBinding.instance
                                          .addPostFrameCallback((_) {
                                            pair.$1.dispose();
                                            pair.$2.dispose();
                                          });
                                    },
                            ),
                          ),
                        ],
                      ),
                    Padding(
                      padding: const EdgeInsets.only(bottom: _sectionGap),
                      child: TextButton.icon(
                        onPressed: _busy || _agenda.length >= 50
                            ? null
                            : () => setState(
                                () => _agenda.add((
                                  TextEditingController(),
                                  TextEditingController(),
                                )),
                              ),
                        icon: const Icon(Icons.add),
                        label: const Text('Tagesordnungspunkt'),
                      ),
                    ),
                    _text(_notes, 'Protokoll / Notizen', 5000, lines: 5),
                    if (widget.meeting == null) ...[
                      const Padding(
                        padding: EdgeInsets.only(top: 8, bottom: 12),
                        child: Text(
                          'Einzuladende Personen',
                          style: TextStyle(fontWeight: FontWeight.w600),
                        ),
                      ),
                      if (_bodyId != null)
                        TextButton.icon(
                          onPressed: _busy ? null : _selectBodyMembers,
                          icon: const Icon(Icons.groups_outlined),
                          label: const Text('Gremiumsmitglieder auswählen'),
                        ),
                      if (_recipientOptions.isEmpty)
                        const Text(
                          'Keine Personen im gewählten Teilnehmerkreis verfügbar.',
                        ),
                      for (final person in _recipientOptions) ...[
                        CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          controlAffinity: ListTileControlAffinity.leading,
                          title: Text(
                            '${person['name']}${person['kind'] == 'external' ? ' (Extern)' : ''}',
                          ),
                          value: _recipients.contains(
                            '${person['kind']}:${person['id']}',
                          ),
                          onChanged: _busy
                              ? null
                              : (checked) => setState(() {
                                  final key =
                                      '${person['kind']}:${person['id']}';
                                  checked == true
                                      ? _recipients.add(key)
                                      : _recipients.remove(key);
                                }),
                        ),
                        if (_recipients.contains(governancePersonKey(person)))
                          CheckboxListTile(
                            contentPadding: const EdgeInsets.only(left: 24),
                            controlAffinity: ListTileControlAffinity.leading,
                            title: const Text('Stimmrecht'),
                            value: _voting(person),
                            onChanged: _busy
                                ? null
                                : (value) => setState(
                                    () =>
                                        _votingRights[governancePersonKey(
                                              person,
                                            )] =
                                            value == true,
                                  ),
                          ),
                      ],
                    ] else
                      const Text(
                        'Empfänger und Stimmrechte sind nach dem Anlegen festgeschrieben.',
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class ClubGovernanceDecisionForm extends StatefulWidget {
  const ClubGovernanceDecisionForm({super.key, required this.save});
  final Future<AirmiusJson> Function(AirmiusJson) save;
  @override
  State<ClubGovernanceDecisionForm> createState() =>
      _ClubGovernanceDecisionFormState();
}

class _ClubGovernanceDecisionFormState
    extends State<ClubGovernanceDecisionForm> {
  final _key = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _candidates = TextEditingController();
  final _quorum = TextEditingController();
  String _type = 'motion';
  String _mode = 'open';
  String _majority = 'simple';
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _candidates.dispose();
    _quorum.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_busy || !_key.currentState!.validate()) return;
    final candidates = _candidates.text
        .split('\n')
        .map((value) => value.trim())
        .where((value) => value.isNotEmpty)
        .toSet()
        .toList();
    if (_type == 'election' &&
        (candidates.isEmpty ||
            candidates.length > 29 ||
            candidates.any(
              (value) =>
                  value.length > 80 || ['yes', 'no', 'abstain'].contains(value),
            ))) {
      setState(
        () => _error =
            'Bitte 1 bis 29 Kandidaturen mit höchstens 80 Zeichen angeben; yes, no und abstain sind reserviert.',
      );
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.save({
        'type': _type,
        'title': _title.text.trim(),
        'description': _description.text.trim(),
        'voting_mode': _mode,
        'majority_rule': _majority,
        'quorum': int.tryParse(_quorum.text.trim()),
        'options': _type == 'motion'
            ? ['yes', 'no', 'abstain']
            : [...candidates, 'abstain'],
      });
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = governanceError(error);
        });
      }
    }
  }

  Widget _select(
    String title,
    String value,
    List<String> values,
    ValueChanged<String> change,
  ) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: DropdownButtonFormField<String>(
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(labelText: title),
      items: [
        for (final entry in values)
          DropdownMenuItem(value: entry, child: Text(governanceLabel(entry))),
      ],
      onChanged: _busy
          ? null
          : (value) {
              if (value != null) setState(() => change(value));
            },
    ),
  );

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !_busy,
    child: AlertDialog(
      title: const Text('Beschluss anlegen'),
      content: SizedBox(
        width: 560,
        child: SingleChildScrollView(
          child: Form(
            key: _key,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_error != null)
                  Semantics(
                    liveRegion: true,
                    child: Text(
                      _error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ),
                if (_busy) const LinearProgressIndicator(),
                TextFormField(
                  controller: _title,
                  enabled: !_busy,
                  maxLength: 180,
                  decoration: const InputDecoration(labelText: 'Titel'),
                  validator: (value) => value?.trim().isEmpty != false
                      ? 'Bitte ausfüllen.'
                      : null,
                ),
                TextFormField(
                  controller: _description,
                  enabled: !_busy,
                  maxLength: 5000,
                  minLines: 2,
                  maxLines: 3,
                  decoration: const InputDecoration(labelText: 'Beschreibung'),
                ),
                _select('Art', _type, [
                  'motion',
                  'election',
                ], (value) => _type = value),
                _select('Abstimmungsart', _mode, [
                  'open',
                  'named',
                  'secret',
                ], (value) => _mode = value),
                _select('Mehrheit', _majority, [
                  'simple',
                  'absolute',
                ], (value) => _majority = value),
                TextFormField(
                  controller: _quorum,
                  enabled: !_busy,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'Quorum (%)'),
                  validator: (value) {
                    if (value == null || value.trim().isEmpty) return null;
                    final number = int.tryParse(value.trim());
                    return number == null || number < 1 || number > 100
                        ? 'Bitte 1 bis 100 angeben.'
                        : null;
                  },
                ),
                if (_type == 'election')
                  TextFormField(
                    controller: _candidates,
                    enabled: !_busy,
                    minLines: 3,
                    maxLines: 5,
                    decoration: const InputDecoration(
                      labelText: 'Kandidaturen',
                    ),
                    validator: (value) => value?.trim().isEmpty != false
                        ? 'Bitte Kandidaturen angeben.'
                        : null,
                  ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _busy ? null : () => Navigator.pop(context),
          child: const Text('Abbrechen'),
        ),
        FilledButton(
          onPressed: _busy ? null : _save,
          child: const Text('Anlegen'),
        ),
      ],
    ),
  );
}
