import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class VolunteerShiftTaskPlannerSuiteScreen extends StatefulWidget {
  const VolunteerShiftTaskPlannerSuiteScreen({super.key, this.initialClubId});

  final int? initialClubId;

  @override
  State<VolunteerShiftTaskPlannerSuiteScreen> createState() =>
      _VolunteerShiftTaskPlannerSuiteScreenState();
}

class _VolunteerShiftTaskPlannerSuiteScreenState
    extends State<VolunteerShiftTaskPlannerSuiteScreen> {
  List<Map<String, dynamic>> _clubs = const [];
  List<Map<String, dynamic>> _assignments = const [];
  List<Map<String, dynamic>> _members = const [];
  List<Map<String, dynamic>> _audiencePeople = const [];
  List<Map<String, dynamic>> _externalMembers = const [];
  List<Map<String, dynamic>> _teams = const [];
  List<Map<String, dynamic>> _events = const [];
  List<Map<String, dynamic>> _changeRequests = const [];
  Map<String, dynamic>? _club;
  bool _loading = true;
  bool _busy = false;
  bool _canManage = false;
  int? _currentUserId;
  String? _error;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadClubs());
  }

  Future<void> _loadClubs() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final rows = _maps((await _client.clubs(mine: true))['data']);
      final selected = rows.cast<Map<String, dynamic>?>().firstWhere(
        (row) => _int(row?['id']) == widget.initialClubId,
        orElse: () => rows.isEmpty ? null : rows.first,
      );
      if (!mounted) return;
      setState(() {
        _clubs = rows;
        _club = selected;
        _loading = false;
      });
      if (selected != null) await _loadShifts();
    } on AirmiusApiException catch (error) {
      if (mounted) setState(() => _setError(error.userMessage));
    } catch (_) {
      if (mounted) {
        setState(() => _setError('Helferplanung konnte nicht geladen werden.'));
      }
    }
  }

  Future<void> _loadShifts() async {
    final clubId = _int(_club?['id']);
    if (clubId == null || _busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final now = DateTime.now();
    try {
      final response = await _client.clubStaffScheduling(
        clubId,
        from: DateTime(now.year, now.month, 1),
        to: DateTime(now.year + 1, now.month, 1),
      );
      final data = _map(response['data']);
      if (!mounted) return;
      setState(() {
        _assignments = _maps(data['assignments']);
        _members = _maps(data['members']);
        _audiencePeople = _maps(data['audience_people']);
        _externalMembers = _maps(data['external_members']);
        _teams = _maps(data['teams']);
        _events = _maps(data['events']);
        _changeRequests = _maps(data['change_requests']);
        _canManage = data['can_manage'] == true;
        _currentUserId = _int(data['current_user_id']);
        _busy = false;
      });
    } on AirmiusApiException catch (error) {
      if (mounted) setState(() => _setError(error.userMessage));
    } catch (_) {
      if (mounted) {
        setState(() => _setError('Schichten konnten nicht geladen werden.'));
      }
    }
  }

  void _setError(String message) {
    _error = message;
    _loading = false;
    _busy = false;
  }

  List<_ShiftGroup> get _groups {
    final grouped = <String, List<Map<String, dynamic>>>{};
    for (final item in _assignments) {
      if (const {'cancelled', 'declined'}.contains('${item['status']}')) {
        continue;
      }
      final key = [
        item['shift_key'] ?? item['role'],
        if (item['shift_key'] == null) item['starts_at'],
        if (item['shift_key'] == null) item['ends_at'],
        if (item['shift_key'] == null) item['team_id'],
        if (item['shift_key'] == null) item['event_id'],
      ].join('|');
      grouped.putIfAbsent(key, () => []).add(item);
    }
    final result = grouped.values.map(_ShiftGroup.new).toList();
    result.sort((a, b) => a.startsAt.compareTo(b.startsAt));
    return result;
  }

  List<_ShiftSection> get _sections {
    final grouped = <String, List<_ShiftGroup>>{};
    for (final group in _groups) {
      final day = DateFormat('yyyy-MM-dd').format(group.startsAt);
      final event = group.eventTitle.isEmpty
          ? 'Vereinsbetrieb'
          : group.eventTitle;
      grouped.putIfAbsent('$day|$event', () => []).add(group);
    }
    return grouped.entries.map((entry) {
      final groups = entry.value
        ..sort((a, b) => a.startsAt.compareTo(b.startsAt));
      return _ShiftSection(
        eventTitle: entry.key.split('|').last,
        groups: groups,
      );
    }).toList()..sort(
      (a, b) => a.groups.first.startsAt.compareTo(b.groups.first.startsAt),
    );
  }

  Future<void> _createShift() async {
    final clubId = _int(_club?['id']);
    if (clubId == null) return;
    final payload = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (_) => _CreateShiftScreen(
          teams: _teams,
          events: _events,
          members: _members,
          audiencePeople: _audiencePeople,
        ),
      ),
    );
    if (payload == null || !mounted) return;
    await _run(
      () => _client.createClubStaffShift(clubId, payload),
      'Einsatz wurde angelegt.',
    );
  }

  Future<void> _editShift(_ShiftGroup group) async {
    final clubId = _int(_club?['id']);
    final assignmentId = _int(group.items.first['id']);
    if (clubId == null || assignmentId == null) return;
    final payload = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (_) => _CreateShiftScreen(
          teams: _teams,
          events: _events,
          members: _members,
          audiencePeople: _audiencePeople,
          initial: group.items.first,
          capacity: group.capacity,
        ),
      ),
    );
    if (payload == null || !mounted) return;
    await _run(
      () => _client.updateClubStaffShift(clubId, assignmentId, payload),
      'Einsatz wurde aktualisiert.',
    );
  }

  Future<void> _deleteShift(_ShiftGroup group) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Einsatz löschen?'),
        content: Text(
          '${group.title} wird mit Teilnehmern, Warteliste und Fragen gelöscht. Betroffene Mitglieder werden informiert.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Abbrechen'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Löschen'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    final clubId = _int(_club?['id']);
    final assignmentId = _int(group.items.first['id']);
    if (clubId == null || assignmentId == null) return;
    await _run(
      () => _client.deleteClubStaffShift(clubId, assignmentId),
      'Einsatz wurde gelöscht.',
    );
  }

  Future<void> _commentShift(_ShiftGroup group) async {
    final controller = TextEditingController();
    final send = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => Padding(
        padding: EdgeInsets.fromLTRB(
          20,
          0,
          20,
          MediaQuery.viewInsetsOf(context).bottom + 20,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'Frage oder Antwort',
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              autofocus: true,
              minLines: 3,
              maxLines: 6,
              maxLength: 2000,
              decoration: const InputDecoration(
                labelText: 'Nachricht',
                hintText: 'Offene Frage stellen oder für alle beantworten',
              ),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Senden'),
            ),
          ],
        ),
      ),
    );
    final message = controller.text.trim();
    controller.dispose();
    if (send != true || message.isEmpty || !mounted) return;
    final clubId = _int(_club?['id']);
    final assignmentId = _int(group.items.first['id']);
    if (clubId == null || assignmentId == null) return;
    await _run(
      () => _client.commentClubStaffShift(clubId, assignmentId, message),
      'Nachricht wurde veröffentlicht.',
    );
  }

  Future<void> _assignExternal(_ShiftGroup group) async {
    final selected = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: [
            const ListTile(
              title: Text('Externes Mitglied eintragen'),
              subtitle: Text(
                'Personen ohne Airmius-Konto werden manuell dokumentiert.',
              ),
            ),
            for (final member in _externalMembers)
              ListTile(
                leading: const CircleAvatar(child: Icon(Icons.person_outline)),
                title: Text('${member['name']}'),
                subtitle: Text('${member['email'] ?? 'Kein Konto'}'),
                onTap: () => Navigator.pop(context, member),
              ),
          ],
        ),
      ),
    );
    if (selected == null || !mounted) return;
    final target = group.openSlots.firstOrNull;
    final clubId = _int(_club?['id']);
    final assignmentId = _int(target?['id']);
    final externalId = _int(selected['id']);
    if (clubId == null || assignmentId == null || externalId == null) return;
    await _run(
      () => _client.assignExternalMemberToClubStaffShift(
        clubId,
        assignmentId,
        externalId,
      ),
      'Externes Mitglied wurde eingetragen.',
    );
  }

  Future<void> _offerAlternative(
    _ShiftGroup source,
    Map<String, dynamic> waitlisted,
  ) async {
    final alternatives = _groups
        .where(
          (candidate) =>
              candidate.key != source.key &&
              candidate.startsAt == source.startsAt &&
              candidate.endsAt == source.endsAt &&
              candidate.openSlots.isNotEmpty,
        )
        .toList();
    if (alternatives.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Im gleichen Zeitraum ist kein alternativer Einsatz frei.',
          ),
        ),
      );
      return;
    }
    final target = await showModalBottomSheet<_ShiftGroup>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: [
            const ListTile(
              title: Text('Alternativen Einsatz anbieten'),
              subtitle: Text(
                'Die Person entscheidet selbst, ob sie wechseln möchte.',
              ),
            ),
            for (final candidate in alternatives)
              ListTile(
                leading: const Icon(Icons.lightbulb_outline),
                title: Text(candidate.title),
                subtitle: Text(
                  '${candidate.occupiedCount}/${candidate.capacity} belegt',
                ),
                onTap: () => Navigator.pop(context, candidate),
              ),
          ],
        ),
      ),
    );
    if (target == null || !mounted) return;
    final clubId = _int(_club?['id']);
    final destinationId = _int(target.openSlots.first['id']);
    final targetUserId = _int(waitlisted['user_id']);
    final sourceId = _int(waitlisted['id']);
    if (clubId == null ||
        destinationId == null ||
        targetUserId == null ||
        sourceId == null) {
      return;
    }
    await _run(
      () => _client.offerAlternativeClubStaffShift(
        clubId,
        destinationId,
        targetUserId,
        sourceId,
      ),
      'Alternativangebot wurde gesendet.',
    );
  }

  Future<void> _join(_ShiftGroup group) async {
    final clubId = _int(_club?['id']);
    final target = group.openSlots.isNotEmpty
        ? group.openSlots.first
        : group.capacitySlots.firstOrNull;
    final assignmentId = _int(target?['id']);
    if (clubId == null || assignmentId == null) return;
    final message = group.openSlots.isNotEmpty
        ? 'Du bist für den Einsatz angemeldet.'
        : 'Der Einsatz ist voll. Du wurdest auf die Warteliste gesetzt.';
    await _run(() => _client.joinClubStaffShift(clubId, assignmentId), message);
  }

  Future<void> _leave(Map<String, dynamic> assignment) async {
    final clubId = _int(_club?['id']);
    final assignmentId = _int(assignment['id']);
    if (clubId == null || assignmentId == null) return;
    await _run(
      () => _client.leaveClubStaffShift(clubId, assignmentId),
      '${assignment['status']}' == 'waitlisted'
          ? 'Wartelistenplatz wurde storniert.'
          : 'Einsatz wurde freigegeben. Die nächste Person kann nachrücken.',
    );
  }

  Future<void> _changePerson(
    Map<String, dynamic> assignment, {
    required bool substitute,
  }) async {
    final selected = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
          children: [
            Text(
              substitute ? 'Vertretung auswählen' : 'Einsatz übertragen',
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            for (final member in _members)
              if (_int(member['id']) != _int(assignment['user_id']))
                ListTile(
                  leading: const CircleAvatar(
                    child: Icon(Icons.person_outline),
                  ),
                  title: Text('${member['name'] ?? 'Mitglied'}'),
                  onTap: () => Navigator.pop(context, member),
                ),
          ],
        ),
      ),
    );
    if (selected == null || !mounted) return;
    final clubId = _int(_club?['id']);
    final assignmentId = _int(assignment['id']);
    final userId = _int(selected['id']);
    if (clubId == null || assignmentId == null || userId == null) return;
    await _run(
      () => substitute
          ? _client.substituteClubStaffShift(clubId, assignmentId, userId)
          : _client.swapClubStaffShift(clubId, assignmentId, userId),
      substitute
          ? 'Vertretungsanfrage wurde gesendet.'
          : 'Tauschanfrage wurde gesendet.',
    );
  }

  Future<void> _respondChange(
    Map<String, dynamic> request,
    String decision,
  ) async {
    final clubId = _int(_club?['id']);
    final requestId = _int(request['id']);
    if (clubId == null || requestId == null) return;
    await _run(
      () => _client.respondClubStaffShiftChange(clubId, requestId, decision),
      decision == 'accept'
          ? 'Anfrage wurde angenommen.'
          : 'Anfrage wurde abgelehnt.',
    );
  }

  Future<void> _run(
    Future<Map<String, dynamic>> Function() action,
    String success,
  ) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(success), duration: const Duration(seconds: 4)),
      );
      setState(() => _busy = false);
      await _loadShifts();
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _setError(error.userMessage));
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } catch (_) {
      if (mounted) {
        setState(() => _setError('Aktion konnte nicht gespeichert werden.'));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Helfer & Schichten'),
        actions: [
          IconButton(
            tooltip: 'Aktualisieren',
            onPressed: _busy ? null : _loadShifts,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      floatingActionButton: _canManage && _club != null
          ? FloatingActionButton.extended(
              onPressed: _busy ? null : _createShift,
              icon: const Icon(Icons.add_task_outlined),
              label: const Text('Einsatz anlegen'),
            )
          : null,
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadShifts,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 104),
                children: [
                  if (_clubs.length > 1) ...[
                    DropdownButtonFormField<int>(
                      initialValue: _int(_club?['id']),
                      decoration: const InputDecoration(
                        labelText: 'Verein',
                        prefixIcon: Icon(Icons.apartment_outlined),
                      ),
                      items: _clubs
                          .map(
                            (club) => DropdownMenuItem<int>(
                              value: _int(club['id']),
                              child: Text('${club['name'] ?? 'Verein'}'),
                            ),
                          )
                          .where((item) => item.value != null)
                          .toList(),
                      onChanged: (id) async {
                        setState(
                          () => _club = _clubs.firstWhere(
                            (club) => _int(club['id']) == id,
                          ),
                        );
                        await _loadShifts();
                      },
                    ),
                    const SizedBox(height: 16),
                  ],
                  AirmiusPanel(
                    gradient: true,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Eyebrow('EINSATZPLANUNG'),
                        const SizedBox(height: 8),
                        Text(
                          'Helferbedarf verlässlich besetzen',
                          style: theme.textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          _canManage
                              ? 'Plane Plätze, erkenne Überschneidungen und verwalte Warteliste, Tausch und Vertretung.'
                              : 'Melde dich für freie Einsätze an oder verwalte deine Wartelistenplätze.',
                          style: TextStyle(color: airmiusMutedColor(context)),
                        ),
                      ],
                    ),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    _MessagePanel(message: _error!, error: true),
                  ],
                  for (final request in _changeRequests.where(
                    (item) =>
                        item['status'] == 'pending' &&
                        _int(item['target_user_id']) == _currentUserId,
                  )) ...[
                    const SizedBox(height: 12),
                    _ChangeRequestPanel(
                      request: request,
                      busy: _busy,
                      onAccept: () => _respondChange(request, 'accept'),
                      onDecline: () => _respondChange(request, 'decline'),
                    ),
                  ],
                  const SizedBox(height: 20),
                  if (_groups.isNotEmpty) ...[
                    Row(
                      children: [
                        Expanded(
                          child: _PlanningMetric(
                            label: 'Einsätze',
                            value: '${_groups.length}',
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: _PlanningMetric(
                            label: 'Unterbesetzt',
                            value:
                                '${_groups.where((group) => group.occupiedCount < group.capacity).length}',
                            alert: _groups.any(
                              (group) => group.occupiedCount < group.capacity,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: _PlanningMetric(
                            label: 'Warteliste',
                            value:
                                '${_groups.fold<int>(0, (sum, group) => sum + group.waitlisted.length)}',
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
                  ],
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          'Kommende Einsätze',
                          style: theme.textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      if (_busy)
                        const SizedBox.square(
                          dimension: 22,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  if (_groups.isEmpty)
                    const _MessagePanel(
                      message: 'Noch keine kommenden Einsätze angelegt.',
                    )
                  else
                    for (final section in _sections) ...[
                      _ShiftSectionHeader(section: section),
                      const SizedBox(height: 8),
                      for (final group in section.groups) ...[
                        _ShiftCard(
                          group: group,
                          currentUserId: _currentUserId,
                          canManage: _canManage,
                          busy: _busy,
                          onJoin: () => _join(group),
                          onLeave: _leave,
                          onSwap: (assignment) =>
                              _changePerson(assignment, substitute: false),
                          onSubstitute: (assignment) =>
                              _changePerson(assignment, substitute: true),
                          onEdit: () => _editShift(group),
                          onDelete: () => _deleteShift(group),
                          onComment: () => _commentShift(group),
                          onAssignExternal: () => _assignExternal(group),
                          onOfferAlternative: (assignment) =>
                              _offerAlternative(group, assignment),
                        ),
                        const SizedBox(height: 12),
                      ],
                      const SizedBox(height: 8),
                    ],
                ],
              ),
            ),
    );
  }
}

class _CreateShiftScreen extends StatefulWidget {
  const _CreateShiftScreen({
    required this.teams,
    required this.events,
    required this.members,
    required this.audiencePeople,
    this.initial,
    this.capacity,
  });

  final List<Map<String, dynamic>> teams;
  final List<Map<String, dynamic>> events;
  final List<Map<String, dynamic>> members;
  final List<Map<String, dynamic>> audiencePeople;
  final Map<String, dynamic>? initial;
  final int? capacity;

  @override
  State<_CreateShiftScreen> createState() => _CreateShiftScreenState();
}

class _CreateShiftScreenState extends State<_CreateShiftScreen> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _location = TextEditingController();
  final _instructions = TextEditingController();
  final _requirements = TextEditingController();
  final _contactName = TextEditingController();
  final _contactDetails = TextEditingController();
  DateTime _date = DateTime.now().add(const Duration(days: 1));
  TimeOfDay _start = const TimeOfDay(hour: 9, minute: 0);
  TimeOfDay _end = const TimeOfDay(hour: 12, minute: 0);
  int _capacity = 2;
  int? _eventId;
  String _visibility = 'club';
  final Set<int> _teamIds = {};
  final Set<int> _userIds = {};
  bool _notifyAudience = true;

  @override
  void initState() {
    super.initState();
    final initial = widget.initial;
    if (initial == null) return;
    _title.text = '${initial['role'] ?? ''}';
    _description.text = '${initial['note'] ?? ''}';
    _location.text = '${initial['location'] ?? ''}';
    _instructions.text = '${initial['instructions'] ?? ''}';
    _requirements.text = '${initial['requirements'] ?? ''}';
    _contactName.text = '${initial['contact_name'] ?? ''}';
    _contactDetails.text = '${initial['contact_details'] ?? ''}';
    final start = DateTime.tryParse('${initial['starts_at']}')?.toLocal();
    final end = DateTime.tryParse('${initial['ends_at']}')?.toLocal();
    if (start != null) {
      _date = DateTime(start.year, start.month, start.day);
      _start = TimeOfDay.fromDateTime(start);
    }
    if (end != null) _end = TimeOfDay.fromDateTime(end);
    _capacity = widget.capacity ?? 1;
    _eventId = _int(initial['event_id']);
    _visibility = '${initial['visibility_scope'] ?? 'club'}';
    _teamIds.addAll(_ints(initial['audience_team_ids']));
    _userIds.addAll(_ints(initial['audience_user_ids']));
    _notifyAudience = initial['notify_audience'] != false;
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _location.dispose();
    _instructions.dispose();
    _requirements.dispose();
    _contactName.dispose();
    _contactDetails.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final value = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (value != null) setState(() => _date = value);
  }

  Future<void> _pickTime(bool start) async {
    final value = await showTimePicker(
      context: context,
      initialTime: start ? _start : _end,
    );
    if (value != null) {
      setState(() {
        if (start) {
          _start = value;
        } else {
          _end = value;
        }
      });
    }
  }

  void _save() {
    if (!_formKey.currentState!.validate()) return;
    if ((_visibility == 'teams' && _teamIds.isEmpty) ||
        (_visibility == 'people' && _userIds.isEmpty)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _visibility == 'teams'
                ? 'Bitte mindestens ein Team auswählen.'
                : 'Bitte mindestens eine Person auswählen.',
          ),
        ),
      );
      return;
    }
    final startsAt = DateTime(
      _date.year,
      _date.month,
      _date.day,
      _start.hour,
      _start.minute,
    );
    final endsAt = DateTime(
      _date.year,
      _date.month,
      _date.day,
      _end.hour,
      _end.minute,
    );
    if (!endsAt.isAfter(startsAt)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Das Ende muss nach dem Beginn liegen.')),
      );
      return;
    }
    Navigator.pop(context, <String, dynamic>{
      'role': _title.text.trim(),
      'note': _description.text.trim().isEmpty
          ? null
          : _description.text.trim(),
      'starts_at': startsAt.toUtc().toIso8601String(),
      'ends_at': endsAt.toUtc().toIso8601String(),
      'capacity': _capacity,
      'status': 'open',
      'event_id': _eventId,
      'location': _optional(_location),
      'instructions': _optional(_instructions),
      'requirements': _optional(_requirements),
      'contact_name': _optional(_contactName),
      'contact_details': _optional(_contactDetails),
      'visibility_scope': _visibility,
      'audience_team_ids': _teamIds.toList(),
      'audience_user_ids': _userIds.toList(),
      'notify_audience': _notifyAudience,
    });
  }

  String? _optional(TextEditingController controller) {
    final value = controller.text.trim();
    return value.isEmpty ? null : value;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          widget.initial == null ? 'Einsatz anlegen' : 'Einsatz bearbeiten',
        ),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 120),
          children: [
            TextFormField(
              controller: _title,
              autofocus: true,
              decoration: const InputDecoration(
                labelText: 'Name des Einsatzes',
                hintText: 'z. B. Sommerfest Aufbau',
                prefixIcon: Icon(Icons.badge_outlined),
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Bitte einen Namen eingeben.'
                  : null,
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<int?>(
              initialValue: _eventId,
              decoration: const InputDecoration(
                labelText: 'Veranstaltung (optional)',
                prefixIcon: Icon(Icons.event_outlined),
              ),
              items: [
                const DropdownMenuItem<int?>(
                  value: null,
                  child: Text('Ohne Veranstaltung'),
                ),
                ...widget.events.map(
                  (event) => DropdownMenuItem<int?>(
                    value: _int(event['id']),
                    child: Text('${event['title']}'),
                  ),
                ),
              ],
              onChanged: (value) => setState(() => _eventId = value),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _description,
              maxLines: 4,
              decoration: const InputDecoration(
                labelText: 'Beschreibung (optional)',
                hintText: 'Treffpunkt, Aufgaben oder Hinweise',
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _location,
              decoration: const InputDecoration(
                labelText: 'Ort oder Treffpunkt (optional)',
                prefixIcon: Icon(Icons.place_outlined),
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _instructions,
              minLines: 2,
              maxLines: 5,
              decoration: const InputDecoration(
                labelText: 'Aufgaben und Ablauf (optional)',
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _requirements,
              minLines: 2,
              maxLines: 5,
              decoration: const InputDecoration(
                labelText: 'Was ist zu beachten oder mitzubringen? (optional)',
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _contactName,
                    decoration: const InputDecoration(
                      labelText: 'Ansprechpartner',
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextFormField(
                    controller: _contactDetails,
                    decoration: const InputDecoration(labelText: 'Kontakt'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            ListTile(
              shape: RoundedRectangleBorder(
                side: BorderSide(color: Theme.of(context).dividerColor),
                borderRadius: BorderRadius.circular(8),
              ),
              leading: const Icon(Icons.calendar_month_outlined),
              title: const Text('Datum'),
              subtitle: Text(DateFormat('dd.MM.yyyy').format(_date)),
              trailing: const Icon(Icons.chevron_right),
              onTap: _pickDate,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _TimeTile(
                    label: 'Beginn',
                    value: _start.format(context),
                    onTap: () => _pickTime(true),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _TimeTile(
                    label: 'Ende',
                    value: _end.format(context),
                    onTap: () => _pickTime(false),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),
            Text(
              'Sichtbarkeit',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(
                  value: 'club',
                  label: Text('Verein'),
                  icon: Icon(Icons.apartment_outlined),
                ),
                ButtonSegment(
                  value: 'teams',
                  label: Text('Teams'),
                  icon: Icon(Icons.groups_outlined),
                ),
                ButtonSegment(
                  value: 'people',
                  label: Text('Personen'),
                  icon: Icon(Icons.person_outline),
                ),
              ],
              selected: {_visibility},
              onSelectionChanged: (values) =>
                  setState(() => _visibility = values.first),
            ),
            if (_visibility == 'teams') ...[
              const SizedBox(height: 10),
              for (final team in widget.teams)
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text('${team['name']}'),
                  value: _teamIds.contains(_int(team['id'])),
                  onChanged: (selected) => setState(() {
                    final id = _int(team['id']);
                    if (id == null) return;
                    selected == true ? _teamIds.add(id) : _teamIds.remove(id);
                  }),
                ),
            ],
            if (_visibility == 'people') ...[
              const SizedBox(height: 10),
              for (final member in widget.audiencePeople)
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text('${member['name']}'),
                  subtitle: Text('${member['subtitle'] ?? ''}'),
                  value: _userIds.contains(_int(member['user_id'])),
                  onChanged: member['selectable'] == false
                      ? null
                      : (selected) => setState(() {
                          final id = _int(member['user_id']);
                          if (id == null) return;
                          selected == true
                              ? _userIds.add(id)
                              : _userIds.remove(id);
                        }),
                ),
            ],
            SwitchListTile.adaptive(
              contentPadding: EdgeInsets.zero,
              title: const Text('Zielgruppe informieren'),
              subtitle: const Text(
                'Berechtigte Mitglieder erhalten eine Benachrichtigung.',
              ),
              value: _notifyAudience,
              onChanged: (value) => setState(() => _notifyAudience = value),
            ),
            const SizedBox(height: 18),
            Text('Kapazität', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Row(
              children: [
                IconButton.filledTonal(
                  tooltip: 'Kapazität verringern',
                  onPressed: _capacity > 1
                      ? () => setState(() => _capacity--)
                      : null,
                  icon: const Icon(Icons.remove),
                ),
                SizedBox(
                  width: 76,
                  child: Text(
                    '$_capacity',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton.filledTonal(
                  tooltip: 'Kapazität erhöhen',
                  onPressed: _capacity < 100
                      ? () => setState(() => _capacity++)
                      : null,
                  icon: const Icon(Icons.add),
                ),
              ],
            ),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.all(16),
        child: FilledButton.icon(
          onPressed: _save,
          icon: const Icon(Icons.check),
          label: Text(
            widget.initial == null
                ? 'Einsatz speichern'
                : 'Änderungen speichern',
          ),
        ),
      ),
    );
  }
}

class _TimeTile extends StatelessWidget {
  const _TimeTile({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => ListTile(
    shape: RoundedRectangleBorder(
      side: BorderSide(color: Theme.of(context).dividerColor),
      borderRadius: BorderRadius.circular(8),
    ),
    leading: const Icon(Icons.schedule_outlined),
    title: Text(label),
    subtitle: Text(value),
    onTap: onTap,
  );
}

class _PlanningMetric extends StatelessWidget {
  const _PlanningMetric({
    required this.label,
    required this.value,
    this.alert = false,
  });

  final String label;
  final String value;
  final bool alert;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: alert
          ? Theme.of(context).colorScheme.errorContainer
          : Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(8),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          value,
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
        Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
      ],
    ),
  );
}

class _ShiftSectionHeader extends StatelessWidget {
  const _ShiftSectionHeader({required this.section});

  final _ShiftSection section;

  @override
  Widget build(BuildContext context) {
    final locale = Localizations.localeOf(context).toLanguageTag();
    return Row(
      children: [
        const Icon(Icons.event_note_outlined, size: 20),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            section.eventTitle,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
        ),
        Text(
          DateFormat(
            'dd.MM.yyyy',
            locale,
          ).format(section.groups.first.startsAt),
        ),
      ],
    );
  }
}

class _ShiftCard extends StatelessWidget {
  const _ShiftCard({
    required this.group,
    required this.currentUserId,
    required this.canManage,
    required this.busy,
    required this.onJoin,
    required this.onLeave,
    required this.onSwap,
    required this.onSubstitute,
    required this.onEdit,
    required this.onDelete,
    required this.onComment,
    required this.onAssignExternal,
    required this.onOfferAlternative,
  });

  final _ShiftGroup group;
  final int? currentUserId;
  final bool canManage;
  final bool busy;
  final VoidCallback onJoin;
  final ValueChanged<Map<String, dynamic>> onLeave;
  final ValueChanged<Map<String, dynamic>> onSwap;
  final ValueChanged<Map<String, dynamic>> onSubstitute;
  final VoidCallback onEdit;
  final VoidCallback onDelete;
  final VoidCallback onComment;
  final VoidCallback onAssignExternal;
  final ValueChanged<Map<String, dynamic>> onOfferAlternative;

  @override
  Widget build(BuildContext context) {
    final canEdit = canManage || group.canEdit;
    final own = group.items.cast<Map<String, dynamic>?>().firstWhere(
      (item) =>
          _int(item?['user_id']) == currentUserId &&
          const {
            'confirmed',
            'planned',
            'swap_requested',
            'waitlisted',
          }.contains('${item?['status']}'),
      orElse: () => null,
    );
    final isWaitlisted = own?['status'] == 'waitlisted';
    final full = group.occupiedCount >= group.capacity;
    final locale = Localizations.localeOf(context).toLanguageTag();
    final date = DateFormat('EEE, dd.MM.yyyy', locale).format(group.startsAt);
    final time =
        '${DateFormat('HH:mm').format(group.startsAt)}–${DateFormat('HH:mm').format(group.endsAt)}';
    return Card(
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
        side: BorderSide(color: airmiusBorderColor(context)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.primaryContainer,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Icon(Icons.volunteer_activism_outlined),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        group.title,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 3),
                      Text('$date · $time'),
                    ],
                  ),
                ),
                StatusPill(
                  '${group.occupiedCount}/${group.capacity}',
                  color: full
                      ? Theme.of(context).colorScheme.tertiary
                      : Theme.of(context).colorScheme.secondary,
                ),
                if (canEdit)
                  PopupMenuButton<String>(
                    tooltip: 'Einsatz verwalten',
                    onSelected: (value) {
                      if (value == 'edit') onEdit();
                      if (value == 'delete') onDelete();
                    },
                    itemBuilder: (context) => const [
                      PopupMenuItem(value: 'edit', child: Text('Bearbeiten')),
                      PopupMenuItem(value: 'delete', child: Text('Löschen')),
                    ],
                  ),
              ],
            ),
            if (group.eventTitle.isNotEmpty || group.location.isNotEmpty) ...[
              const SizedBox(height: 10),
              if (group.eventTitle.isNotEmpty)
                Text('Veranstaltung: ${group.eventTitle}'),
              if (group.location.isNotEmpty)
                Text('Treffpunkt: ${group.location}'),
            ],
            if (group.note.isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(group.note),
            ],
            if (group.instructions.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text('Aufgaben: ${group.instructions}'),
            ],
            if (group.requirements.isNotEmpty) ...[
              const SizedBox(height: 5),
              Text('Bitte beachten: ${group.requirements}'),
            ],
            const SizedBox(height: 12),
            LinearProgressIndicator(
              value: group.capacity == 0
                  ? 0
                  : group.occupiedCount / group.capacity,
              borderRadius: BorderRadius.circular(4),
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final assignment in group.confirmed)
                  InputChip(
                    avatar: const Icon(Icons.person_outline, size: 18),
                    label: Text('${assignment['user_name'] ?? 'Mitglied'}'),
                    onPressed: canEdit
                        ? () => _showManageMenu(context, assignment)
                        : null,
                  ),
                for (final assignment in group.externalConfirmed)
                  Chip(
                    avatar: const Icon(Icons.person_outline, size: 18),
                    label: Text(
                      '${assignment['external_member_name'] ?? 'Externes Mitglied'} · extern',
                    ),
                  ),
                if (group.waitlisted.isNotEmpty)
                  Chip(
                    avatar: const Icon(Icons.hourglass_top_outlined, size: 18),
                    label: Text('Warteliste ${group.waitlisted.length}'),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            if (canEdit &&
                group.openSlots.isNotEmpty &&
                group.externalCandidatesAllowed)
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton.icon(
                  onPressed: busy ? null : onAssignExternal,
                  icon: const Icon(Icons.person_add_alt_outlined),
                  label: const Text('Externes Mitglied eintragen'),
                ),
              ),
            if (canEdit && group.waitlisted.isNotEmpty) ...[
              const Divider(),
              Text(
                'Warteliste',
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
              ),
              for (final assignment in group.waitlisted)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  leading: const Icon(Icons.hourglass_top_outlined),
                  title: Text('${assignment['user_name'] ?? 'Mitglied'}'),
                  trailing: TextButton(
                    onPressed: busy
                        ? null
                        : () => onOfferAlternative(assignment),
                    child: const Text('Alternative'),
                  ),
                ),
            ],
            if (group.comments.isNotEmpty) ...[
              const Divider(),
              Text(
                'Fragen & Antworten',
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 6),
              for (final comment in group.comments.take(3))
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Text(
                    '${_map(comment['user'])['name'] ?? 'Mitglied'}: ${comment['message']}',
                  ),
                ),
            ],
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                onPressed: busy ? null : onComment,
                icon: const Icon(Icons.forum_outlined),
                label: Text(
                  group.comments.isEmpty
                      ? 'Frage stellen'
                      : 'Antworten (${group.comments.length})',
                ),
              ),
            ),
            if (own != null)
              Row(
                children: [
                  Expanded(
                    child: _MessagePanel(
                      message: isWaitlisted
                          ? 'Du stehst auf der Warteliste.'
                          : 'Du bist für diesen Einsatz angemeldet.',
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.outlined(
                    tooltip: isWaitlisted
                        ? 'Wartelistenplatz stornieren'
                        : 'Einsatz freigeben',
                    onPressed: busy ? null : () => onLeave(own),
                    icon: const Icon(Icons.close),
                  ),
                ],
              )
            else
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: busy ? null : onJoin,
                  icon: Icon(
                    full
                        ? Icons.hourglass_top_outlined
                        : Icons.person_add_alt_1_outlined,
                  ),
                  label: Text(full ? 'Auf Warteliste' : 'Anmelden'),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _showManageMenu(
    BuildContext context,
    Map<String, dynamic> assignment,
  ) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.swap_horiz),
              title: const Text('Einsatz übertragen'),
              subtitle: const Text('Die verantwortliche Person wechseln'),
              onTap: () => Navigator.pop(context, 'swap'),
            ),
            ListTile(
              leading: const Icon(Icons.people_alt_outlined),
              title: const Text('Vertretung eintragen'),
              subtitle: const Text('Eine zusätzliche Vertretung dokumentieren'),
              onTap: () => Navigator.pop(context, 'substitute'),
            ),
          ],
        ),
      ),
    );
    if (action == 'swap') onSwap(assignment);
    if (action == 'substitute') onSubstitute(assignment);
  }
}

class _ShiftGroup {
  _ShiftGroup(this.items)
    : startsAt = DateTime.parse('${items.first['starts_at']}').toLocal(),
      endsAt = DateTime.parse('${items.first['ends_at']}').toLocal();

  final List<Map<String, dynamic>> items;
  final DateTime startsAt;
  final DateTime endsAt;

  String get title => '${items.first['role'] ?? 'Einsatz'}';
  String get key =>
      '${items.first['shift_key'] ?? '${title}_${startsAt.toIso8601String()}'}';
  String get note => '${items.first['note'] ?? ''}'.trim();
  String get eventTitle => '${items.first['event_title'] ?? ''}'.trim();
  String get location => '${items.first['location'] ?? ''}'.trim();
  String get instructions => '${items.first['instructions'] ?? ''}'.trim();
  String get requirements => '${items.first['requirements'] ?? ''}'.trim();
  List<Map<String, dynamic>> get comments => _maps(items.first['comments']);
  List<Map<String, dynamic>> get waitlisted =>
      items.where((item) => item['status'] == 'waitlisted').toList();
  List<Map<String, dynamic>> get capacitySlots =>
      items.where((item) => item['status'] != 'waitlisted').toList();
  List<Map<String, dynamic>> get confirmed => capacitySlots
      .where(
        (item) =>
            item['user_id'] != null &&
            const {
              'confirmed',
              'planned',
              'swap_requested',
            }.contains('${item['status']}'),
      )
      .toList();
  List<Map<String, dynamic>> get externalConfirmed => capacitySlots
      .where(
        (item) =>
            item['external_member_id'] != null && item['status'] == 'confirmed',
      )
      .toList();
  List<Map<String, dynamic>> get openSlots => capacitySlots
      .where(
        (item) =>
            item['user_id'] == null &&
            item['external_member_id'] == null &&
            const {'open', 'planned'}.contains('${item['status']}'),
      )
      .toList();
  int get capacity => capacitySlots.length;
  int get occupiedCount => confirmed.length + externalConfirmed.length;
  bool get externalCandidatesAllowed => true;
  bool get canEdit => items.any((item) => item['can_edit'] == true);
}

class _ShiftSection {
  const _ShiftSection({required this.eventTitle, required this.groups});

  final String eventTitle;
  final List<_ShiftGroup> groups;
}

class _ChangeRequestPanel extends StatelessWidget {
  const _ChangeRequestPanel({
    required this.request,
    required this.busy,
    required this.onAccept,
    required this.onDecline,
  });

  final Map<String, dynamic> request;
  final bool busy;
  final VoidCallback onAccept;
  final VoidCallback onDecline;

  @override
  Widget build(BuildContext context) {
    final substitute = request['type'] == 'substitute';
    final alternative = request['type'] == 'alternative';
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primaryContainer,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Theme.of(context).colorScheme.primary),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                alternative
                    ? Icons.lightbulb_outline
                    : substitute
                    ? Icons.people_alt_outlined
                    : Icons.swap_horiz,
                color: Theme.of(context).colorScheme.primary,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  alternative
                      ? 'Alternativer Einsatz'
                      : substitute
                      ? 'Vertretungsanfrage'
                      : 'Tauschanfrage',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            alternative
                ? 'Der Verein schlägt dir ${request['assignment_role'] ?? 'einen alternativen Einsatz'} vor. Die Änderung erfolgt nur mit deiner Zustimmung.'
                : '${request['requester_name'] ?? 'Ein Vereinsmitglied'} möchte dich für diesen Einsatz ${substitute ? 'als Vertretung eintragen' : 'einsetzen'}.',
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: busy ? null : onDecline,
                  child: const Text('Ablehnen'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton(
                  onPressed: busy ? null : onAccept,
                  child: const Text('Annehmen'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _MessagePanel extends StatelessWidget {
  const _MessagePanel({required this.message, this.error = false});

  final String message;
  final bool error;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: error
          ? Theme.of(context).colorScheme.errorContainer
          : Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(8),
    ),
    child: Text(
      message,
      style: TextStyle(
        color: error ? Theme.of(context).colorScheme.onErrorContainer : null,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}

Map<String, dynamic> _map(dynamic value) => value is Map
    ? value.map((key, item) => MapEntry('$key', item))
    : <String, dynamic>{};

Set<int> _ints(dynamic value) =>
    value is List ? value.map(_int).whereType<int>().toSet() : <int>{};

List<Map<String, dynamic>> _maps(dynamic value) => value is List
    ? value.whereType<Map>().map(_map).toList()
    : const <Map<String, dynamic>>[];

int? _int(dynamic value) => value is int ? value : int.tryParse('$value');

extension<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
