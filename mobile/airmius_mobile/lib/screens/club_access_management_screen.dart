import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

class ClubAccessManagementScreen extends StatefulWidget {
  const ClubAccessManagementScreen({
    super.key,
    required this.clubId,
    required this.clubName,
    required this.memberId,
    required this.memberName,
  });

  final int clubId;
  final String clubName;
  final int memberId;
  final String memberName;

  @override
  State<ClubAccessManagementScreen> createState() =>
      _ClubAccessManagementScreenState();
}

class _ClubAccessManagementScreenState
    extends State<ClubAccessManagementScreen> {
  bool _loading = true;
  bool _saving = false;
  String? _error;
  JsonMap _permissionData = {};
  JsonMap _roleData = {};
  JsonMap _assignmentData = {};
  JsonMap _delegationData = {};
  JsonMap _organizationData = {};
  JsonMap _handoverData = {};
  final Map<String, Object?> _overrides = {};

  String _tr(String key) => AirmiusScope.of(context).t(key);
  AirmiusClubRepository get _repo =>
      AirmiusServicesScope.of(context).repositories.clubs;

  List<JsonMap> _maps(Object? value) => value is List
      ? value.whereType<JsonMap>().map(JsonMap.from).toList()
      : <JsonMap>[];
  List<JsonMap> get _catalog => _maps(_permissionData['catalog']);
  List<JsonMap> get _permissionCatalog => _maps(_roleData['permission_catalog']);
  List<JsonMap> get _roles => _maps(_roleData['roles']);
  List<JsonMap> get _assignments => _maps(_assignmentData['assignments']);
  List<JsonMap> get _departments => _maps(_organizationData['departments']);
  List<JsonMap> get _teams => _maps(_organizationData['team_assignments']);
  List<JsonMap> get _handoverReviews => _maps(_handoverData['reviews']);
  List<JsonMap> get _eligibleSuccessors => _maps(
    _handoverData['eligible_successors'],
  ).where((item) => _integer(item['id']) != widget.memberId).toList();
  JsonMap? get _memberHandover {
    final reviews = _handoverReviews
        .where(
          (item) =>
              _integer((item['departing_user'] as JsonMap?)?['id']) ==
              widget.memberId,
        )
        .toList();
    if (reviews.isEmpty) return null;

    return reviews.cast<JsonMap?>().firstWhere(
      (item) => item?['status'] != 'applied',
      orElse: () => reviews.first,
    );
  }
  Set<String> get _delegablePermissionKeys =>
      (_delegationData['delegable_permissions'] as List? ?? const [])
          .map((item) => '$item')
          .toSet();
  List<JsonMap> get _memberDelegations => _maps(
    _delegationData['delegations'],
  ).where((item) => _integer(item['grantee_user_id']) == widget.memberId).toList();
  Map<String, dynamic> get _effective => _permissionData['effective'] is JsonMap
      ? JsonMap.from(_permissionData['effective'] as JsonMap)
      : <String, dynamic>{};

  int _integer(Object? value) => value is int
      ? value
      : (value is num ? value.toInt() : int.tryParse('$value') ?? 0);

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
      final results = await Future.wait<JsonMap>([
        _repo.clubMemberPermissions(widget.clubId, widget.memberId),
        _repo.clubRoleDefinitions(widget.clubId),
        _repo.clubMemberRoleDefinitions(widget.clubId, widget.memberId),
        _repo.clubPermissionDelegations(widget.clubId),
        _repo.clubOrganization(widget.clubId),
        _repo.clubAccessHandoverReviews(widget.clubId),
      ]);
      if (!mounted) return;
      setState(() {
        _permissionData = results[0];
        _roleData = results[1];
        _assignmentData = results[2];
        _delegationData = results[3];
        _organizationData = results[4];
        _handoverData = results[5];
        _overrides
          ..clear()
          ..addAll(
            _permissionData['overrides'] is JsonMap
                ? JsonMap.from(_permissionData['overrides'] as JsonMap)
                : <String, dynamic>{},
          );
      });
    } catch (error) {
      if (mounted) setState(() => _error = '$error');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _run(Future<void> Function() action) async {
    if (_saving) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await action();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_tr('membership.access.saved'))),
      );
    } catch (error) {
      if (mounted) setState(() => _error = '$error');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _savePermissions() => _run(() async {
    _permissionData = await _repo.updateClubMemberPermissions(
      widget.clubId,
      widget.memberId,
      {'permissions': _overrides},
    );
    if (mounted) setState(() {});
  });

  Future<void> _saveAssignments(List<JsonMap> assignments) => _run(() async {
    _assignmentData = await _repo.updateClubMemberRoleDefinitions(
      widget.clubId,
      widget.memberId,
      {'assignments': assignments},
    );
    _roleData = await _repo.clubRoleDefinitions(widget.clubId);
    if (mounted) setState(() {});
  });

  List<JsonMap> _scopeTargets(String scopeType) =>
      scopeType == 'department' ? _departments : _teams;

  String _scopeName(JsonMap item) {
    final type = '${item['scope_type'] ?? 'club'}';
    if (type == 'club') return _tr('membership.access.scopeClub');
    final id = _integer(item['scope_id']);
    final target = _scopeTargets(type).cast<JsonMap?>().firstWhere(
      (candidate) => _integer(candidate?['id']) == id,
      orElse: () => null,
    );
    return '${target?['name'] ?? _tr('membership.access.scopeUnknown')}';
  }

  Future<void> _editAssignment([JsonMap? existing]) async {
    if (_roles.isEmpty) return;
    var roleId = _integer(existing?['role_definition_id']);
    if (roleId == 0) roleId = _integer(_roles.first['id']);
    var scopeType = '${existing?['scope_type'] ?? 'club'}';
    int? scopeId = _integer(existing?['scope_id']);
    if (scopeId == 0) scopeId = null;
    final result = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(_tr('membership.access.assignment')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<int>(
                  initialValue: roleId,
                  decoration: InputDecoration(
                    labelText: _tr('membership.access.role'),
                  ),
                  items: _roles
                      .where(
                        (role) =>
                            role['is_active'] == true ||
                            _integer(role['id']) == roleId,
                      )
                      .map(
                        (role) => DropdownMenuItem(
                          value: _integer(role['id']),
                          child: Text('${role['name']}'),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => roleId = value ?? roleId,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: scopeType,
                  decoration: InputDecoration(
                    labelText: _tr('membership.access.scope'),
                  ),
                  items: [
                    ('club', _tr('membership.access.scopeClub')),
                    ('department', _tr('membership.access.scopeDepartment')),
                    ('team', _tr('membership.access.scopeTeam')),
                  ]
                      .map(
                        (item) => DropdownMenuItem(
                          value: item.$1,
                          child: Text(item.$2),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => setDialogState(() {
                    scopeType = value ?? 'club';
                    scopeId = null;
                  }),
                ),
                if (scopeType != 'club') ...[
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    initialValue: scopeId,
                    decoration: InputDecoration(
                      labelText: _tr('membership.access.scopeTarget'),
                    ),
                    items: _scopeTargets(scopeType)
                        .map(
                          (target) => DropdownMenuItem(
                            value: _integer(target['id']),
                            child: Text('${target['name']}'),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setDialogState(() => scopeId = value),
                  ),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(_tr('membership.cancel')),
            ),
            FilledButton(
              onPressed: scopeType != 'club' && scopeId == null
                  ? null
                  : () => Navigator.pop(dialogContext, {
                      'role_definition_id': roleId,
                      'scope_type': scopeType,
                      'scope_id': scopeType == 'club' ? null : scopeId,
                    }),
              child: Text(_tr('membership.save')),
            ),
          ],
        ),
      ),
    );
    if (result == null || !mounted) return;
    final next = _assignments.map(JsonMap.from).toList();
    if (existing == null) {
      next.add(result);
    } else {
      final index = next.indexWhere(
        (item) =>
            _integer(item['role_definition_id']) ==
                _integer(existing['role_definition_id']) &&
            '${item['scope_type']}' == '${existing['scope_type']}' &&
            _integer(item['scope_id']) == _integer(existing['scope_id']),
      );
      if (index >= 0) next[index] = result;
    }
    await _saveAssignments(next);
  }

  Future<void> _removeAssignment(JsonMap assignment) async {
    final next = _assignments
        .where(
          (item) =>
              !(_integer(item['role_definition_id']) ==
                      _integer(assignment['role_definition_id']) &&
                  '${item['scope_type']}' == '${assignment['scope_type']}' &&
                  _integer(item['scope_id']) ==
                      _integer(assignment['scope_id'])),
        )
        .map(JsonMap.from)
        .toList();
    await _saveAssignments(next);
  }

  Future<void> _editRole([JsonMap? existing]) async {
    final name = TextEditingController(text: '${existing?['name'] ?? ''}');
    final key = TextEditingController(text: '${existing?['key'] ?? ''}');
    final selected = <String>{
      ...((existing?['permissions'] is List)
          ? (existing!['permissions'] as List).map((item) => '$item')
          : const <String>[]),
    };
    var active = existing?['is_active'] != false;
    final result = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(
            existing == null
                ? _tr('membership.access.roleCreate')
                : _tr('membership.access.roleEdit'),
          ),
          content: SizedBox(
            width: 480,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: name,
                    decoration: InputDecoration(
                      labelText: _tr('membership.access.name'),
                    ),
                    onChanged: (_) => setDialogState(() {}),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: key,
                    decoration: InputDecoration(
                      labelText: _tr('membership.access.key'),
                    ),
                  ),
                  const SizedBox(height: 12),
                  for (final permission in _permissionCatalog)
                    CheckboxListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text('${permission['label'] ?? permission['key']}'),
                      value: selected.contains('${permission['key']}'),
                      onChanged: (value) => setDialogState(() {
                        final permissionKey = '${permission['key']}';
                        value == true
                            ? selected.add(permissionKey)
                            : selected.remove(permissionKey);
                      }),
                    ),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(_tr('membership.access.active')),
                    value: active,
                    onChanged: (value) => setDialogState(() => active = value),
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(_tr('membership.cancel')),
            ),
            FilledButton(
              onPressed: name.text.trim().isEmpty
                  ? null
                  : () => Navigator.pop(dialogContext, {
                      'name': name.text.trim(),
                      'key': key.text.trim(),
                      'permissions': selected.toList(),
                      'is_active': active,
                    }),
              child: Text(_tr('membership.save')),
            ),
          ],
        ),
      ),
    );
    name.dispose();
    key.dispose();
    if (result == null || !mounted) return;
    await _run(() async {
      existing == null
          ? await _repo.createClubRoleDefinition(widget.clubId, result)
          : await _repo.updateClubRoleDefinition(
              widget.clubId,
              _integer(existing['id']),
              result,
            );
      _roleData = await _repo.clubRoleDefinitions(widget.clubId);
      if (mounted) setState(() {});
    });
  }

  Future<void> _deleteRole(JsonMap role) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(_tr('membership.access.roleDelete')),
        content: Text('${role['name']}'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(_tr('membership.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(_tr('membership.access.delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(() async {
      await _repo.deleteClubRoleDefinition(
        widget.clubId,
        _integer(role['id']),
      );
      _roleData = await _repo.clubRoleDefinitions(widget.clubId);
      if (mounted) setState(() {});
    });
  }

  Future<void> _createDelegation() async {
    final selected = <String>{};
    var scopeType = 'club';
    int? scopeId;
    var startsAt = DateTime.now();
    var endsAt = startsAt.add(const Duration(days: 7));
    final result = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(_tr('membership.access.delegationCreate')),
          content: SizedBox(
            width: 480,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: scopeType,
                    decoration: InputDecoration(
                      labelText: _tr('membership.access.scope'),
                    ),
                    items: [
                      ('club', _tr('membership.access.scopeClub')),
                      ('department', _tr('membership.access.scopeDepartment')),
                      ('team', _tr('membership.access.scopeTeam')),
                    ]
                        .map(
                          (item) => DropdownMenuItem(
                            value: item.$1,
                            child: Text(item.$2),
                          ),
                        )
                        .toList(),
                    onChanged: (value) => setDialogState(() {
                      scopeType = value ?? 'club';
                      scopeId = null;
                    }),
                  ),
                  if (scopeType != 'club') ...[
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      initialValue: scopeId,
                      decoration: InputDecoration(
                        labelText: _tr('membership.access.scopeTarget'),
                      ),
                      items: _scopeTargets(scopeType)
                          .map(
                            (target) => DropdownMenuItem(
                              value: _integer(target['id']),
                              child: Text('${target['name']}'),
                            ),
                          )
                          .toList(),
                      onChanged: (value) =>
                          setDialogState(() => scopeId = value),
                    ),
                  ],
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(_tr('membership.access.startsAt')),
                    subtitle: Text(MaterialLocalizations.of(context).formatMediumDate(startsAt)),
                    onTap: () async {
                      final selectedDate = await showDatePicker(
                        context: context,
                        firstDate: DateTime.now(),
                        lastDate: DateTime.now().add(const Duration(days: 90)),
                        initialDate: startsAt,
                      );
                      if (selectedDate != null) {
                        final now = DateTime.now();
                        setDialogState(() {
                          startsAt = DateUtils.isSameDay(selectedDate, now)
                              ? now
                              : DateTime(
                                  selectedDate.year,
                                  selectedDate.month,
                                  selectedDate.day,
                                  12,
                                );
                          if (!endsAt.isAfter(startsAt)) {
                            endsAt = startsAt.add(const Duration(days: 1));
                          }
                        });
                      }
                    },
                  ),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(_tr('membership.access.endsAt')),
                    subtitle: Text(MaterialLocalizations.of(context).formatMediumDate(endsAt)),
                    onTap: () async {
                      final selectedDate = await showDatePicker(
                        context: context,
                        firstDate: startsAt.add(const Duration(days: 1)),
                        lastDate: startsAt.add(const Duration(days: 90)),
                        initialDate: endsAt.isAfter(startsAt) ? endsAt : startsAt.add(const Duration(days: 1)),
                      );
                      if (selectedDate != null) {
                        setDialogState(
                          () => endsAt = DateTime(
                            selectedDate.year,
                            selectedDate.month,
                            selectedDate.day,
                            23,
                            59,
                          ),
                        );
                      }
                    },
                  ),
                  for (final permission in _permissionCatalog.where(
                    (item) => _delegablePermissionKeys.contains('${item['key']}'),
                  ))
                    CheckboxListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text('${permission['label'] ?? permission['key']}'),
                      value: selected.contains('${permission['key']}'),
                      onChanged: (value) => setDialogState(() {
                        final permissionKey = '${permission['key']}';
                        value == true
                            ? selected.add(permissionKey)
                            : selected.remove(permissionKey);
                      }),
                    ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(_tr('membership.cancel')),
            ),
            FilledButton(
              onPressed:
                  selected.isEmpty || (scopeType != 'club' && scopeId == null)
                  ? null
                  : () => Navigator.pop(dialogContext, {
                      'grantee_user_id': widget.memberId,
                      'permissions': selected.toList(),
                      'scope_type': scopeType,
                      'scope_id': scopeType == 'club' ? null : scopeId,
                      'starts_at': startsAt.toIso8601String(),
                      'ends_at': endsAt.toIso8601String(),
                    }),
              child: Text(_tr('membership.access.grant')),
            ),
          ],
        ),
      ),
    );
    if (result == null || !mounted) return;
    await _run(() async {
      await _repo.createClubPermissionDelegation(widget.clubId, result);
      _delegationData = await _repo.clubPermissionDelegations(widget.clubId);
      if (mounted) setState(() {});
    });
  }

  Future<void> _revokeDelegation(JsonMap delegation) => _run(() async {
    await _repo.revokeClubPermissionDelegation(
      widget.clubId,
      _integer(delegation['id']),
    );
    _delegationData = await _repo.clubPermissionDelegations(widget.clubId);
    if (mounted) setState(() {});
  });

  Future<void> _refreshHandovers() async {
    _handoverData = await _repo.clubAccessHandoverReviews(widget.clubId);
    if (mounted) setState(() {});
  }

  Future<void> _proposeHandover(JsonMap review) async {
    var decision = '${review['decision'] ?? 'remove'}';
    var successorId = _integer((review['successor'] as JsonMap?)?['id']);
    if (successorId == 0) successorId = 0;
    final note = TextEditingController(text: '${review['proposal_note'] ?? ''}');
    final result = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(_tr('membership.access.handover')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<String>(
                  initialValue: decision,
                  decoration: InputDecoration(
                    labelText: _tr('membership.access.decision'),
                  ),
                  items: [
                    DropdownMenuItem(
                      value: 'remove',
                      child: Text(_tr('membership.access.decisionRemove')),
                    ),
                    DropdownMenuItem(
                      value: 'assign_successor',
                      child: Text(_tr('membership.access.decisionSuccessor')),
                    ),
                  ],
                  onChanged: (value) => setDialogState(() {
                    decision = value ?? 'remove';
                    if (decision == 'remove') successorId = 0;
                  }),
                ),
                if (decision == 'assign_successor') ...[
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    initialValue: successorId == 0 ? null : successorId,
                    decoration: InputDecoration(
                      labelText: _tr('membership.access.successor'),
                    ),
                    items: _eligibleSuccessors
                        .map(
                          (candidate) => DropdownMenuItem(
                            value: _integer(candidate['id']),
                            child: Text('${candidate['name']}'),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setDialogState(() => successorId = value ?? 0),
                  ),
                ],
                const SizedBox(height: 12),
                TextField(
                  controller: note,
                  maxLength: 2000,
                  maxLines: 3,
                  decoration: InputDecoration(
                    labelText: _tr('membership.access.note'),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(_tr('membership.cancel')),
            ),
            FilledButton(
              onPressed: decision == 'assign_successor' && successorId == 0
                  ? null
                  : () => Navigator.pop(dialogContext, {
                      'decision': decision,
                      'successor_user_id': decision == 'assign_successor'
                          ? successorId
                          : null,
                      'note': note.text.trim().isEmpty ? null : note.text.trim(),
                    }),
              child: Text(_tr('membership.access.propose')),
            ),
          ],
        ),
      ),
    );
    note.dispose();
    if (result == null || !mounted) return;
    await _run(() async {
      await _repo.proposeClubAccessHandover(
        widget.clubId,
        _integer(review['id']),
        result,
      );
      await _refreshHandovers();
    });
  }

  Future<void> _approveHandover(JsonMap review) => _run(() async {
    await _repo.approveClubAccessHandover(
      widget.clubId,
      _integer(review['id']),
    );
    await _refreshHandovers();
  });

  Widget _permissionTab() => ListView(
    padding: const EdgeInsets.all(16),
    children: [
      Text(
        _tr('membership.access.permissionHint'),
        style: Theme.of(context).textTheme.bodySmall,
      ),
      const SizedBox(height: 12),
      for (final permission in _catalog)
        Card(
          child: ListTile(
            title: Text('${permission['label'] ?? permission['key']}'),
            subtitle: Text(
              '${_tr('membership.access.effective')}: ${_effective['${permission['key']}'] == true ? _tr('membership.access.yes') : _tr('membership.access.no')}',
            ),
            trailing: DropdownButton<Object?>(
              value: _overrides.containsKey('${permission['key']}')
                  ? _overrides['${permission['key']}']
                  : null,
              items: [
                DropdownMenuItem(value: null, child: Text(_tr('membership.access.inherit'))),
                DropdownMenuItem(value: true, child: Text(_tr('membership.access.allow'))),
                DropdownMenuItem(value: false, child: Text(_tr('membership.access.deny'))),
              ],
              onChanged: (value) => setState(() {
                final key = '${permission['key']}';
                value == null ? _overrides.remove(key) : _overrides[key] = value;
              }),
            ),
          ),
        ),
      const SizedBox(height: 12),
      FilledButton.icon(
        onPressed: _saving ? null : _savePermissions,
        icon: const Icon(Icons.save_outlined),
        label: Text(_tr('membership.save')),
      ),
    ],
  );

  Widget _roleTab() => ListView(
    padding: const EdgeInsets.all(16),
    children: [
      Row(
        children: [
          Expanded(child: Text(_tr('membership.access.assignments'), style: Theme.of(context).textTheme.titleMedium)),
          IconButton(onPressed: _saving ? null : () => _editAssignment(), icon: const Icon(Icons.add_circle_outline)),
        ],
      ),
      for (final assignment in _assignments)
        Card(
          child: ListTile(
            title: Text('${_roles.cast<JsonMap?>().firstWhere((role) => _integer(role?['id']) == _integer(assignment['role_definition_id']), orElse: () => null)?['name'] ?? _tr('membership.access.roleUnknown')}'),
            subtitle: Text(_scopeName(assignment)),
            onTap: () => _editAssignment(assignment),
            trailing: IconButton(
              tooltip: _tr('membership.access.delete'),
              onPressed: _saving ? null : () => _removeAssignment(assignment),
              icon: const Icon(Icons.delete_outline),
            ),
          ),
        ),
      const SizedBox(height: 16),
      Row(
        children: [
          Expanded(child: Text(_tr('membership.access.roleDefinitions'), style: Theme.of(context).textTheme.titleMedium)),
          IconButton(onPressed: _saving ? null : () => _editRole(), icon: const Icon(Icons.add_circle_outline)),
        ],
      ),
      for (final role in _roles)
        Card(
          child: ListTile(
            title: Text('${role['name']}'),
            subtitle: Text('${(role['permissions'] as List?)?.length ?? 0} ${_tr('membership.access.permissions')}'),
            onTap: () => _editRole(role),
            trailing: IconButton(
              tooltip: _tr('membership.access.delete'),
              onPressed: _integer(role['assigned_count']) > 0 || _saving ? null : () => _deleteRole(role),
              icon: const Icon(Icons.delete_outline),
            ),
          ),
        ),
    ],
  );

  Widget _delegationTab() => ListView(
    padding: const EdgeInsets.all(16),
    children: [
      Align(
        alignment: AlignmentDirectional.centerEnd,
        child: FilledButton.icon(
          onPressed: _saving ? null : _createDelegation,
          icon: const Icon(Icons.add),
          label: Text(_tr('membership.access.delegationCreate')),
        ),
      ),
      const SizedBox(height: 12),
      if (_memberDelegations.isEmpty)
        EmptyPanel(_tr('membership.access.noDelegations')),
      for (final delegation in _memberDelegations)
        Card(
          child: ListTile(
            title: Text(_scopeName(delegation)),
            subtitle: Text(
              '${(delegation['permissions'] as List?)?.map((item) => '$item').join(', ') ?? ''}\n${_tr('membership.access.status')}: ${_tr('membership.access.status.${delegation['status']}')}',
            ),
            isThreeLine: true,
            trailing: ['active', 'scheduled'].contains('${delegation['status']}')
                ? IconButton(
                    tooltip: _tr('membership.access.revoke'),
                    onPressed: _saving ? null : () => _revokeDelegation(delegation),
                    icon: const Icon(Icons.cancel_outlined),
                  )
                : null,
          ),
        ),
    ],
  );

  Widget _handoverTab() {
    final review = _memberHandover;
    if (review == null) {
      return ListView(
        padding: const EdgeInsets.all(16),
        children: [EmptyPanel(_tr('membership.access.handoverNone'))],
      );
    }
    final status = '${review['status']}';

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          _tr('membership.access.handoverIntro'),
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _tr('membership.access.handoverStatus.$status'),
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 8),
                Text(
                  '${_tr('membership.access.handoverDue')}: ${review['due_on']}\n'
                  '${_tr('membership.access.assignments')}: ${review['assignment_count']} · '
                  '${_tr('membership.access.delegations')}: ${review['delegation_count']}',
                ),
                if (['pending', 'proposed', 'stale'].contains(status)) ...[
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    onPressed: _saving ? null : () => _proposeHandover(review),
                    icon: const Icon(Icons.redo_outlined),
                    label: Text(_tr('membership.access.propose')),
                  ),
                ],
                if (status == 'proposed') ...[
                  const SizedBox(height: 12),
                  Text(
                    _tr('membership.access.secondPersonHint'),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: _saving ? null : () => _approveHandover(review),
                    icon: const Icon(Icons.verified_user_outlined),
                    label: Text(_tr('membership.access.approve')),
                  ),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 4,
      child: Scaffold(
        appBar: AppBar(
          title: Text(_tr('membership.access.title')),
          bottom: TabBar(
            tabs: [
              Tab(text: _tr('membership.access.permissions')),
              Tab(text: _tr('membership.access.roles')),
              Tab(text: _tr('membership.access.delegations')),
              Tab(text: _tr('membership.access.handover')),
            ],
          ),
        ),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null && _catalog.isEmpty
            ? Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!, textAlign: TextAlign.center),
                      const SizedBox(height: 12),
                      FilledButton(
                        onPressed: _load,
                        child: Text(_tr('membership.retry')),
                      ),
                    ],
                  ),
                ),
              )
            : Column(
                children: [
                  if (_error != null)
                    MaterialBanner(
                      content: Text(_error!),
                      actions: [
                        TextButton(onPressed: _load, child: Text(_tr('membership.retry'))),
                      ],
                    ),
                  ListTile(
                    leading: AirmiusAvatar(widget.memberName),
                    title: Text(widget.memberName),
                    subtitle: Text(widget.clubName),
                  ),
                  if (_saving) const LinearProgressIndicator(),
                  Expanded(
                    child: TabBarView(
                      children: [
                        _permissionTab(),
                        _roleTab(),
                        _delegationTab(),
                        _handoverTab(),
                      ],
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}
