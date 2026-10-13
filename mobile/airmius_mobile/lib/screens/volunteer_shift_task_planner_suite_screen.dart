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
        item['role'],
        item['starts_at'],
        item['ends_at'],
        item['team_id'],
        item['event_id'],
      ].join('|');
      grouped.putIfAbsent(key, () => []).add(item);
    }
    final result = grouped.values.map(_ShiftGroup.new).toList();
    result.sort((a, b) => a.startsAt.compareTo(b.startsAt));
    return result;
  }

  Future<void> _createShift() async {
    final clubId = _int(_club?['id']);
    if (clubId == null) return;
    final payload = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(builder: (_) => const _CreateShiftScreen()),
    );
    if (payload == null || !mounted) return;
    await _run(
      () => _client.createClubStaffShift(clubId, payload),
      'Einsatz wurde angelegt.',
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
                    for (final group in _groups) ...[
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
                      ),
                      const SizedBox(height: 12),
                    ],
                ],
              ),
            ),
    );
  }
}

class _CreateShiftScreen extends StatefulWidget {
  const _CreateShiftScreen();

  @override
  State<_CreateShiftScreen> createState() => _CreateShiftScreenState();
}

class _CreateShiftScreenState extends State<_CreateShiftScreen> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _description = TextEditingController();
  DateTime _date = DateTime.now().add(const Duration(days: 1));
  TimeOfDay _start = const TimeOfDay(hour: 9, minute: 0);
  TimeOfDay _end = const TimeOfDay(hour: 12, minute: 0);
  int _capacity = 2;

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
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
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Einsatz anlegen')),
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
          label: const Text('Einsatz speichern'),
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
  });

  final _ShiftGroup group;
  final int? currentUserId;
  final bool canManage;
  final bool busy;
  final VoidCallback onJoin;
  final ValueChanged<Map<String, dynamic>> onLeave;
  final ValueChanged<Map<String, dynamic>> onSwap;
  final ValueChanged<Map<String, dynamic>> onSubstitute;

  @override
  Widget build(BuildContext context) {
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
    final full = group.confirmed.length >= group.capacity;
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
                  '${group.confirmed.length}/${group.capacity}',
                  color: full
                      ? Theme.of(context).colorScheme.tertiary
                      : Theme.of(context).colorScheme.secondary,
                ),
              ],
            ),
            if (group.note.isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(group.note),
            ],
            const SizedBox(height: 12),
            LinearProgressIndicator(
              value: group.capacity == 0
                  ? 0
                  : group.confirmed.length / group.capacity,
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
                    onPressed: canManage
                        ? () => _showManageMenu(context, assignment)
                        : null,
                  ),
                if (group.waitlisted.isNotEmpty)
                  Chip(
                    avatar: const Icon(Icons.hourglass_top_outlined, size: 18),
                    label: Text('Warteliste ${group.waitlisted.length}'),
                  ),
              ],
            ),
            const SizedBox(height: 14),
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
  String get note => '${items.first['note'] ?? ''}'.trim();
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
  List<Map<String, dynamic>> get openSlots => capacitySlots
      .where(
        (item) =>
            item['user_id'] == null &&
            const {'open', 'planned'}.contains('${item['status']}'),
      )
      .toList();
  int get capacity => capacitySlots.length;
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
                substitute ? Icons.people_alt_outlined : Icons.swap_horiz,
                color: Theme.of(context).colorScheme.primary,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  substitute ? 'Vertretungsanfrage' : 'Tauschanfrage',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            '${request['requester_name'] ?? 'Ein Vereinsmitglied'} möchte dich für diesen Einsatz ${substitute ? 'als Vertretung eintragen' : 'einsetzen'}.',
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

List<Map<String, dynamic>> _maps(dynamic value) => value is List
    ? value.whereType<Map>().map(_map).toList()
    : const <Map<String, dynamic>>[];

int? _int(dynamic value) => value is int ? value : int.tryParse('$value');

extension<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
