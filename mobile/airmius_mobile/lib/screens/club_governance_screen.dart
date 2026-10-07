import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_date_input.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';

class ClubGovernanceScreen extends StatefulWidget {
  const ClubGovernanceScreen({super.key, required this.club});

  final ClubSummary club;

  @override
  State<ClubGovernanceScreen> createState() => _ClubGovernanceScreenState();
}

class _ClubGovernanceScreenState extends State<ClubGovernanceScreen> {
  Map<String, dynamic> _data = const {};
  bool _loading = true;
  bool _busy = false;
  String? _error;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  List<Map<String, dynamic>> _list(String key) {
    final value = _data[key];
    return value is List
        ? value.whereType<Map<String, dynamic>>().toList()
        : const [];
  }

  bool get _canManage => _data['can_manage'] == true;
  bool get _canEdit => _data['can_edit'] == true ||
      (!_data.containsKey('can_edit') && _canManage);
  bool get _canDelete => _data['can_delete'] == true ||
      (!_data.containsKey('can_delete') && _canManage);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await _client.clubGovernance(widget.club.id);
      final data = response['data'];
      if (mounted) {
        setState(() => _data = data is Map<String, dynamic> ? data : const {});
      }
    } catch (error) {
      if (mounted) setState(() => _error = _safeError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _safeError(Object error) => error is AirmiusApiException
      ? error.userMessage
      : AirmiusScope.of(context).t('clubGovernance.error');

  Future<void> _write(Future<AirmiusJson> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
      await _load();
    } catch (error) {
      if (mounted) setState(() => _error = _safeError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _editBody([Map<String, dynamic>? body]) async {
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _GovernanceBodyDialog(body: body),
    );
    if (payload == null || !mounted) return;
    await _write(
      () => _client.saveClubGovernanceBody(
        widget.club.id,
        payload,
        bodyId: _asInt(body?['id']),
      ),
    );
  }

  Future<void> _editAssignment(
    Map<String, dynamic> body, [
    Map<String, dynamic>? assignment,
  ]) async {
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _GovernanceAssignmentDialog(
        assignment: assignment,
        people: _list('member_options'),
      ),
    );
    if (payload == null || !mounted) return;
    await _write(
      () => _client.saveClubGovernanceAssignment(
        widget.club.id,
        _asInt(body['id'])!,
        payload,
        assignmentId: _asInt(assignment?['id']),
      ),
    );
  }

  Future<bool> _confirm(String titleKey, String messageKey) async {
    final t = AirmiusScope.of(context).t;
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: Text(t(titleKey)),
            content: Text(t(messageKey)),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: Text(t('common.cancel')),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: Text(t('common.delete')),
              ),
            ],
          ),
        ) ??
        false;
  }

  Future<void> _deleteBody(Map<String, dynamic> body) async {
    if (!await _confirm(
      'clubGovernance.deleteBodyTitle',
      'clubGovernance.deleteBodyMessage',
    )) {
      return;
    }
    await _write(
      () =>
          _client.deleteClubGovernanceBody(widget.club.id, _asInt(body['id'])!),
    );
  }

  Future<void> _deleteAssignment(
    Map<String, dynamic> body,
    Map<String, dynamic> assignment,
  ) async {
    if (!await _confirm(
      'clubGovernance.deleteAssignmentTitle',
      'clubGovernance.deleteAssignmentMessage',
    )) {
      return;
    }
    await _write(
      () => _client.deleteClubGovernanceAssignment(
        widget.club.id,
        _asInt(body['id'])!,
        _asInt(assignment['id'])!,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(t('clubGovernance.title')),
        actions: [
          if (_canEdit)
            IconButton(
              tooltip: t('clubGovernance.addBody'),
              onPressed: _busy ? null : () => _editBody(),
              icon: const Icon(Icons.add_circle_outline),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              t('clubGovernance.hint'),
              style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
            ),
            if (_busy)
              const Padding(
                padding: EdgeInsets.only(top: 12),
                child: LinearProgressIndicator(),
              ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Semantics(
                liveRegion: true,
                child: MaterialBanner(
                  content: Text(_error!),
                  actions: [
                    TextButton(
                      onPressed: _load,
                      child: Text(t('common.retry')),
                    ),
                  ],
                ),
              ),
            ],
            if (_loading) ...[
              const SizedBox(height: 32),
              const Center(child: CircularProgressIndicator()),
            ] else if (_list('bodies').isEmpty) ...[
              const SizedBox(height: 24),
              Text(t('clubGovernance.empty')),
            ] else ...[
              const SizedBox(height: 12),
              for (final body in _list('bodies')) ...[
                _bodyCard(body),
                const SizedBox(height: 12),
              ],
            ],
          ],
        ),
      ),
    );
  }

  Widget _bodyCard(Map<String, dynamic> body) {
    final t = AirmiusScope.of(context).t;
    final rawAssignments = body['assignments'];
    final assignments = rawAssignments is List
        ? rawAssignments.whereType<Map<String, dynamic>>().toList()
        : <Map<String, dynamic>>[];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.account_balance_outlined),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        t('clubGovernance.type.${body['type']}'),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontSize: 12,
                        ),
                      ),
                      Text(
                        '${body['name'] ?? ''}',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ),
                Text(
                  body['is_public'] == true
                      ? t('clubGovernance.public')
                      : t('clubGovernance.internal'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 12,
                  ),
                ),
                if (_canEdit || _canDelete)
                  PopupMenuButton<String>(
                    onSelected: (action) {
                      if (action == 'edit') _editBody(body);
                      if (action == 'assignment') _editAssignment(body);
                      if (action == 'delete') _deleteBody(body);
                    },
                    itemBuilder: (_) => [
                      if (_canEdit) PopupMenuItem(
                        value: 'edit',
                        child: Text(t('common.edit')),
                      ),
                      if (_canEdit) PopupMenuItem(
                        value: 'assignment',
                        child: Text(t('clubGovernance.addAssignment')),
                      ),
                      if (_canDelete) PopupMenuItem(
                        value: 'delete',
                        child: Text(t('common.delete')),
                      ),
                    ],
                  ),
              ],
            ),
            if ('${body['description'] ?? ''}'.trim().isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text('${body['description']}'),
              ),
            if (_period(body).isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  '${t('clubGovernance.period')}: ${_period(body)}',
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
              ),
            const Divider(height: 24),
            if (assignments.isEmpty)
              Text(t('clubGovernance.emptyAssignments'))
            else
              for (final assignment in assignments)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.badge_outlined),
                  title: Text(
                    '${(assignment['person'] as Map?)?['name'] ?? ''}',
                  ),
                  subtitle: Text(
                    [
                          assignment['position_title'],
                          assignment['responsibilities'],
                          _period(assignment),
                        ]
                        .where((value) => '${value ?? ''}'.trim().isNotEmpty)
                        .join(' · '),
                  ),
                  trailing: _canEdit || _canDelete
                      ? PopupMenuButton<String>(
                          onSelected: (action) => action == 'edit'
                              ? _editAssignment(body, assignment)
                              : _deleteAssignment(body, assignment),
                          itemBuilder: (_) => [
                            if (_canEdit) PopupMenuItem(
                              value: 'edit',
                              child: Text(t('common.edit')),
                            ),
                            if (_canDelete) PopupMenuItem(
                              value: 'delete',
                              child: Text(t('common.delete')),
                            ),
                          ],
                        )
                      : null,
                ),
          ],
        ),
      ),
    );
  }

  String _period(Map<String, dynamic> item) => [
    item['starts_on'],
    item['ends_on'],
  ].where((value) => '${value ?? ''}'.trim().isNotEmpty).join(' – ');
}

class _GovernanceBodyDialog extends StatefulWidget {
  const _GovernanceBodyDialog({required this.body});

  final Map<String, dynamic>? body;

  @override
  State<_GovernanceBodyDialog> createState() => _GovernanceBodyDialogState();
}

class _GovernanceBodyDialogState extends State<_GovernanceBodyDialog> {
  late String _type;
  late final TextEditingController _name;
  late final TextEditingController _description;
  late final TextEditingController _start;
  late final TextEditingController _end;
  late bool _public;

  @override
  void initState() {
    super.initState();
    final body = widget.body ?? const {};
    _type = '${body['type'] ?? 'board'}';
    _name = TextEditingController(text: '${body['name'] ?? ''}');
    _description = TextEditingController(text: '${body['description'] ?? ''}');
    _start = TextEditingController(
      text: formatAirmiusDate(parseAirmiusDate(body['starts_on']?.toString())),
    );
    _end = TextEditingController(
      text: formatAirmiusDate(parseAirmiusDate(body['ends_on']?.toString())),
    );
    _public = body['is_public'] == true;
  }

  @override
  void dispose() {
    _name.dispose();
    _description.dispose();
    _start.dispose();
    _end.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(
        widget.body == null
            ? t('clubGovernance.addBody')
            : t('clubGovernance.editBody'),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _type,
              decoration: InputDecoration(labelText: t('clubGovernance.type')),
              items: const ['board', 'committee', 'working_group']
                  .map(
                    (type) => DropdownMenuItem(
                      value: type,
                      child: Text(t('clubGovernance.type.$type')),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _type = value ?? _type),
            ),
            TextField(
              controller: _name,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(labelText: t('clubGovernance.name')),
            ),
            TextField(
              controller: _description,
              maxLines: 2,
              decoration: InputDecoration(
                labelText: t('clubGovernance.description'),
              ),
            ),
            TextField(
              controller: _start,
              keyboardType: TextInputType.datetime,
              inputFormatters: const [AirmiusDateInputFormatter()],
              decoration: InputDecoration(
                labelText: t('clubGovernance.startsOn'),
              ),
            ),
            TextField(
              controller: _end,
              keyboardType: TextInputType.datetime,
              inputFormatters: const [AirmiusDateInputFormatter()],
              decoration: InputDecoration(
                labelText: t('clubGovernance.endsOn'),
              ),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(t('clubGovernance.showPublicly')),
              value: _public,
              onChanged: (value) => setState(() => _public = value),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
        FilledButton(
          onPressed: _name.text.trim().isEmpty
              ? null
              : () => Navigator.pop(context, {
                  'type': _type,
                  'name': _name.text.trim(),
                  'description': _nullIfEmpty(_description.text),
                  'starts_on': formatAirmiusApiDate(
                    parseAirmiusDate(_start.text),
                  ),
                  'ends_on': formatAirmiusApiDate(parseAirmiusDate(_end.text)),
                  'is_public': _public,
                }),
          child: Text(t('common.save')),
        ),
      ],
    );
  }
}

class _GovernanceAssignmentDialog extends StatefulWidget {
  const _GovernanceAssignmentDialog({
    required this.assignment,
    required this.people,
  });

  final Map<String, dynamic>? assignment;
  final List<Map<String, dynamic>> people;

  @override
  State<_GovernanceAssignmentDialog> createState() =>
      _GovernanceAssignmentDialogState();
}

class _GovernanceAssignmentDialogState
    extends State<_GovernanceAssignmentDialog> {
  String? _personKey;
  late final TextEditingController _position;
  late final TextEditingController _responsibilities;
  late final TextEditingController _start;
  late final TextEditingController _end;
  late bool _public;

  @override
  void initState() {
    super.initState();
    final assignment = widget.assignment ?? const {};
    if (assignment['user_id'] != null) {
      _personKey = 'user:${assignment['user_id']}';
    } else if (assignment['club_external_member_id'] != null) {
      _personKey = 'external:${assignment['club_external_member_id']}';
    }
    _position = TextEditingController(
      text: '${assignment['position_title'] ?? ''}',
    );
    _responsibilities = TextEditingController(
      text: '${assignment['responsibilities'] ?? ''}',
    );
    _start = TextEditingController(
      text: formatAirmiusDate(
        parseAirmiusDate(assignment['starts_on']?.toString()),
      ),
    );
    _end = TextEditingController(
      text: formatAirmiusDate(
        parseAirmiusDate(assignment['ends_on']?.toString()),
      ),
    );
    _public = assignment['is_public'] == true;
  }

  @override
  void dispose() {
    _position.dispose();
    _responsibilities.dispose();
    _start.dispose();
    _end.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(t('clubGovernance.assignment')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _personKey,
              decoration: InputDecoration(
                labelText: t('clubGovernance.person'),
              ),
              items: widget.people
                  .map(
                    (person) => DropdownMenuItem(
                      value: '${person['kind']}:${person['id']}',
                      child: Text('${person['name'] ?? ''}'),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _personKey = value),
            ),
            TextField(
              controller: _position,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                labelText: t('clubGovernance.position'),
              ),
            ),
            TextField(
              controller: _responsibilities,
              maxLines: 2,
              decoration: InputDecoration(
                labelText: t('clubGovernance.responsibilities'),
              ),
            ),
            TextField(
              controller: _start,
              keyboardType: TextInputType.datetime,
              inputFormatters: const [AirmiusDateInputFormatter()],
              decoration: InputDecoration(
                labelText: t('clubGovernance.startsOn'),
              ),
            ),
            TextField(
              controller: _end,
              keyboardType: TextInputType.datetime,
              inputFormatters: const [AirmiusDateInputFormatter()],
              decoration: InputDecoration(
                labelText: t('clubGovernance.endsOn'),
              ),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(t('clubGovernance.showPublicly')),
              value: _public,
              onChanged: (value) => setState(() => _public = value),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
        FilledButton(
          onPressed: _personKey == null || _position.text.trim().isEmpty
              ? null
              : () {
                  final parts = _personKey!.split(':');
                  Navigator.pop(context, {
                    'user_id': parts[0] == 'user' ? int.parse(parts[1]) : null,
                    'club_external_member_id': parts[0] == 'external'
                        ? int.parse(parts[1])
                        : null,
                    'position_title': _position.text.trim(),
                    'responsibilities': _nullIfEmpty(_responsibilities.text),
                    'starts_on': formatAirmiusApiDate(
                      parseAirmiusDate(_start.text),
                    ),
                    'ends_on': formatAirmiusApiDate(
                      parseAirmiusDate(_end.text),
                    ),
                    'is_public': _public,
                  });
                },
          child: Text(t('common.save')),
        ),
      ],
    );
  }
}

int? _asInt(dynamic value) => value is int ? value : int.tryParse('$value');

String? _nullIfEmpty(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}
