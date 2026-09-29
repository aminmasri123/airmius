import 'package:flutter/material.dart';
import '../widgets/country_field.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import 'club_governance_screen.dart';
import 'club_metadata_screen.dart';
import 'club_policy_documents_screen.dart';
import 'club_year_periods_screen.dart';

class ClubOrganizationScreen extends StatefulWidget {
  const ClubOrganizationScreen({super.key, required this.club});

  final ClubSummary club;

  @override
  State<ClubOrganizationScreen> createState() => _ClubOrganizationScreenState();
}

class _ClubOrganizationScreenState extends State<ClubOrganizationScreen> {
  Map<String, dynamic> _data = const {};
  bool _loading = true;
  bool _busy = false;
  String? _error;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  List<Map<String, dynamic>> _items(String key) {
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
  bool get _canEditTeamAssignments =>
      _data['can_edit_team_assignments'] == true ||
      (!_data.containsKey('can_edit_team_assignments') && _canManage);

  bool _canCreate(String type) {
    final key = switch (type) {
      'departments' => 'can_create_departments',
      'locations' => 'can_create_locations',
      _ => 'can_create_training_groups',
    };
    return _data[key] == true || (!_data.containsKey(key) && _canEdit);
  }

  bool _canEditItem(Map<String, dynamic> item) =>
      item['can_edit'] == true ||
      (!item.containsKey('can_edit') && _canEdit);

  bool _canDeleteItem(Map<String, dynamic> item) =>
      item['can_delete'] == true ||
      (!item.containsKey('can_delete') && _canDelete);

  List<Map<String, dynamic>> get _editableDepartments =>
      _items('departments').where(_canEditItem).toList();

  List<Map<String, dynamic>> get _assignableDepartments => _items(
    'departments',
  ).where(
    (item) =>
        item['can_assign_teams'] == true ||
        (!item.containsKey('can_assign_teams') && _canEditTeamAssignments),
  ).toList();

  List<Map<String, dynamic>> get _assignableTrainingGroups => _items(
    'training_groups',
  ).where(
    (item) =>
        item['can_assign_teams'] == true ||
        (!item.containsKey('can_assign_teams') && _canEditTeamAssignments),
  ).toList();

  List<Map<String, dynamic>> get _editableTeams => _items(
    'team_assignments',
  ).where(
    (item) =>
        item['can_edit_assignment'] == true ||
        (!item.containsKey('can_edit_assignment') &&
            _canEditTeamAssignments),
  ).toList();

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
      final response = await _client.clubOrganization(widget.club.id);
      final data = response['data'];
      if (!mounted) return;
      setState(() => _data = data is Map<String, dynamic> ? data : const {});
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = _safeError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _safeError(Object error) => error is AirmiusApiException
      ? error.userMessage
      : AirmiusScope.of(context).t('clubOrganization.error');

  Future<void> _edit(String type, [Map<String, dynamic>? item]) async {
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _OrganizationUnitDialog(
        type: type,
        item: item,
        departments: type == 'training-groups'
            ? _editableDepartments
            : _items('departments'),
        locations: _items('locations'),
        allowUnassignedDepartment: _data['can_create_departments'] == true,
      ),
    );
    if (payload == null || !mounted) return;
    await _write(
      () => _client.saveClubOrganizationUnit(
        widget.club.id,
        type,
        payload,
        id: _int(item?['id']),
      ),
    );
  }

  Future<void> _delete(String type, Map<String, dynamic> item) async {
    final t = AirmiusScope.of(context).t;
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: Text(t('clubOrganization.deleteTitle')),
            content: Text(t('clubOrganization.deleteMessage')),
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
    if (!confirmed || !mounted) return;
    await _write(
      () => _client.deleteClubOrganizationUnit(
        widget.club.id,
        type,
        _int(item['id'])!,
      ),
    );
  }

  Future<void> _editTeam(Map<String, dynamic> team) async {
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _TeamOrganizationDialog(
        team: team,
        departments: _assignableDepartments,
        locations: _items('locations'),
        trainingGroups: _assignableTrainingGroups,
        allowUnassignedDepartment:
            _data['can_assign_teams_globally'] == true,
      ),
    );
    if (payload == null || !mounted) return;
    await _write(() => _client.updateTeam(_int(team['id'])!, payload));
  }

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

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(title: Text(t('clubOrganization.title'))),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              t('clubOrganization.hint'),
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
            ] else ...[
              const SizedBox(height: 16),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.account_balance_outlined),
                  title: Text(t('clubGovernance.title')),
                  subtitle: Text(t('clubGovernance.hint')),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => ClubGovernanceScreen(club: widget.club),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              _section(
                'departments',
                'clubOrganization.departments',
                Icons.account_tree_outlined,
              ),
              const SizedBox(height: 14),
              _section(
                'locations',
                'clubOrganization.locations',
                Icons.location_on_outlined,
              ),
              const SizedBox(height: 14),
              _section(
                'training-groups',
                'clubOrganization.trainingGroups',
                Icons.fitness_center_outlined,
                dataKey: 'training_groups',
              ),
              if (_editableTeams.isNotEmpty) ...[
                const SizedBox(height: 14),
                _teamSection(),
              ],
              if (widget.club.isMember || widget.club.canManage) ...[
                const SizedBox(height: 14),
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.date_range_outlined),
                    title: Text(t('clubYearPeriods.title')),
                    subtitle: Text(t('clubYearPeriods.hint')),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) =>
                            ClubYearPeriodsScreen(club: widget.club),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.description_outlined),
                    title: Text(t('clubPolicyDocuments.title')),
                    subtitle: Text(t('clubPolicyDocuments.hint')),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) =>
                            ClubPolicyDocumentsScreen(club: widget.club),
                      ),
                    ),
                  ),
                ),
              ],
              if (widget.club.canViewMetadata) ...[
                const SizedBox(height: 14),
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.tune_outlined),
                    title: Text(t('clubMetadata.title')),
                    subtitle: Text(t('clubMetadata.hint')),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => ClubMetadataScreen(club: widget.club),
                      ),
                    ),
                  ),
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }

  Widget _section(
    String type,
    String titleKey,
    IconData icon, {
    String? dataKey,
  }) {
    final t = AirmiusScope.of(context).t;
    final items = _items(dataKey ?? type);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(icon),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    titleKey == '' ? '' : t(titleKey),
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
                if (_canCreate(type))
                  IconButton(
                    tooltip: t('clubOrganization.add'),
                    onPressed: _busy ? null : () => _edit(type),
                    icon: const Icon(Icons.add_circle_outline),
                  ),
              ],
            ),
            if (items.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text(t('clubOrganization.empty')),
              )
            else
              for (final item in items)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text('${item['name'] ?? ''}'),
                  subtitle: Text(_subtitle(type, item)),
                  trailing: _canEditItem(item) || _canDeleteItem(item)
                      ? PopupMenuButton<String>(
                          onSelected: (action) => action == 'edit'
                              ? _edit(type, item)
                              : _delete(type, item),
                          itemBuilder: (_) => [
                            if (_canEditItem(item)) PopupMenuItem(
                              value: 'edit',
                              child: Text(t('common.edit')),
                            ),
                            if (_canDeleteItem(item)) PopupMenuItem(
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

  String _subtitle(String type, Map<String, dynamic> item) {
    final t = AirmiusScope.of(context).t;
    final values = <String>[
      if ('${item['sport_type'] ?? ''}'.trim().isNotEmpty)
        '${item['sport_type']}',
      if (type == 'locations')
        [
          item['street'],
          item['house_number'],
          item['postal_code'],
          item['city'],
        ].where((value) => '${value ?? ''}'.trim().isNotEmpty).join(' '),
      if ('${item['description'] ?? ''}'.trim().isNotEmpty)
        '${item['description']}',
      item['is_public'] == true
          ? t('clubOrganization.public')
          : t('clubOrganization.internal'),
    ].where((value) => value.trim().isNotEmpty).toList();
    return values.join(' · ');
  }

  Widget _teamSection() {
    final t = AirmiusScope.of(context).t;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              t('clubOrganization.teamAssignments'),
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            for (final team in _editableTeams)
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.groups_outlined),
                title: Text('${team['name'] ?? ''}'),
                subtitle: Text(_teamSubtitle(team)),
                trailing: const Icon(Icons.edit_outlined),
                onTap: _busy ? null : () => _editTeam(team),
              ),
          ],
        ),
      ),
    );
  }

  String _teamSubtitle(Map<String, dynamic> team) {
    final labels = <String>[];
    for (final entry in [
      ('departments', 'club_department_id'),
      ('locations', 'club_location_id'),
      ('training_groups', 'club_training_group_id'),
    ]) {
      final id = _int(team[entry.$2]);
      final match = _items(
        entry.$1,
      ).where((item) => _int(item['id']) == id).firstOrNull;
      if (match != null) labels.add('${match['name']}');
    }
    return labels.isEmpty
        ? AirmiusScope.of(context).t('clubOrganization.unassigned')
        : labels.join(' · ');
  }
}

class _OrganizationUnitDialog extends StatefulWidget {
  const _OrganizationUnitDialog({
    required this.type,
    required this.item,
    required this.departments,
    required this.locations,
    required this.allowUnassignedDepartment,
  });

  final String type;
  final Map<String, dynamic>? item;
  final List<Map<String, dynamic>> departments;
  final List<Map<String, dynamic>> locations;
  final bool allowUnassignedDepartment;

  @override
  State<_OrganizationUnitDialog> createState() =>
      _OrganizationUnitDialogState();
}

class _OrganizationUnitDialogState extends State<_OrganizationUnitDialog> {
  final _formKey = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _fields;
  int? _departmentId;
  int? _locationId;
  bool _isPublic = false;

  @override
  void initState() {
    super.initState();
    final item = widget.item ?? const <String, dynamic>{};
    _fields = {
      for (final key in [
        'name',
        'sport_type',
        'description',
        'street',
        'house_number',
        'postal_code',
        'city',
        'country',
        'notes',
      ])
        key: TextEditingController(
          text: '${item[key] ?? (key == 'country' ? 'DE' : '')}',
        ),
    };
    _departmentId = _int(item['club_department_id']);
    _locationId = _int(item['club_location_id']);
    _isPublic = item['is_public'] == true;
  }

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final isLocation = widget.type == 'locations';
    final isGroup = widget.type == 'training-groups';
    return AlertDialog(
      title: Text(
        t(
          widget.item == null
              ? 'clubOrganization.add'
              : 'clubOrganization.edit',
        ),
      ),
      content: SingleChildScrollView(
        child: SizedBox(
          width: 520,
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _field(context, 'name', required: true),
                if (!isLocation) _field(context, 'sport_type'),
                if (isLocation) ...[
                  _field(context, 'street'),
                  _field(context, 'house_number'),
                  _field(context, 'postal_code'),
                  _field(context, 'city'),
                  CountryField(
                    controller: _fields['country'],
                    label: t('clubs.wizard.country'),
                    required: true,
                  ),
                  _field(context, 'notes'),
                ],
                if (isGroup) ...[
                  DropdownButtonFormField<int?>(
                    key: ValueKey('department-$_departmentId'),
                    initialValue: _departmentId,
                    decoration: InputDecoration(
                      labelText: t('clubOrganization.department'),
                    ),
                    items: [
                      if (widget.allowUnassignedDepartment)
                        DropdownMenuItem(
                          value: null,
                          child: Text(t('clubOrganization.unassigned')),
                        ),
                      for (final item in widget.departments)
                        DropdownMenuItem(
                          value: _int(item['id']),
                          child: Text('${item['name']}'),
                        ),
                    ],
                    onChanged: (value) => setState(() => _departmentId = value),
                  ),
                  DropdownButtonFormField<int?>(
                    key: ValueKey('location-$_locationId'),
                    initialValue: _locationId,
                    decoration: InputDecoration(
                      labelText: t('clubOrganization.location'),
                    ),
                    items: [
                      DropdownMenuItem(
                        value: null,
                        child: Text(t('clubOrganization.unassigned')),
                      ),
                      for (final item in widget.locations)
                        DropdownMenuItem(
                          value: _int(item['id']),
                          child: Text('${item['name']}'),
                        ),
                    ],
                    onChanged: (value) => setState(() => _locationId = value),
                  ),
                ],
                if (!isLocation) _field(context, 'description', maxLines: 3),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(t('clubOrganization.showPublicly')),
                  value: _isPublic,
                  onChanged: (value) => setState(() => _isPublic = value),
                ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
        FilledButton(onPressed: _submit, child: Text(t('common.save'))),
      ],
    );
  }

  Widget _field(
    BuildContext context,
    String key, {
    bool required = false,
    int maxLines = 1,
    int? maxLength,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: TextFormField(
      controller: _fields[key],
      maxLines: maxLines,
      maxLength: maxLength,
      validator: (value) {
        final text = value?.trim() ?? '';
        if ((required && text.isEmpty) ||
            (key == 'country' && text.length != 2)) {
          return AirmiusScope.of(context).t('clubEditor.required');
        }
        return null;
      },
      decoration: InputDecoration(
        labelText: AirmiusScope.of(context).t('clubOrganization.$key'),
      ),
    ),
  );

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final payload = <String, dynamic>{
      'name': _fields['name']!.text.trim(),
      'is_public': _isPublic,
      if (widget.type != 'locations') 'sport_type': _nullable('sport_type'),
      if (widget.type != 'locations') 'description': _nullable('description'),
      if (widget.type == 'locations') ...{
        'street': _nullable('street'),
        'house_number': _nullable('house_number'),
        'postal_code': _nullable('postal_code'),
        'city': _nullable('city'),
        'country': _fields['country']!.text.trim().toUpperCase(),
        'notes': _nullable('notes'),
      },
      if (widget.type == 'training-groups') ...{
        'club_department_id': _departmentId,
        'club_location_id': _locationId,
      },
    };
    Navigator.pop(context, payload);
  }

  String? _nullable(String key) {
    final value = _fields[key]!.text.trim();
    return value.isEmpty ? null : value;
  }
}

class _TeamOrganizationDialog extends StatefulWidget {
  const _TeamOrganizationDialog({
    required this.team,
    required this.departments,
    required this.locations,
    required this.trainingGroups,
    required this.allowUnassignedDepartment,
  });

  final Map<String, dynamic> team;
  final List<Map<String, dynamic>> departments;
  final List<Map<String, dynamic>> locations;
  final List<Map<String, dynamic>> trainingGroups;
  final bool allowUnassignedDepartment;

  @override
  State<_TeamOrganizationDialog> createState() =>
      _TeamOrganizationDialogState();
}

class _TeamOrganizationDialogState extends State<_TeamOrganizationDialog> {
  late int? _departmentId = _int(widget.team['club_department_id']);
  late int? _locationId = _int(widget.team['club_location_id']);
  late int? _groupId = _int(widget.team['club_training_group_id']);

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text('${widget.team['name']}'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          _select(
            t('clubOrganization.department'),
            _departmentId,
            widget.departments,
            (value) => setState(() => _departmentId = value),
            allowNull: widget.allowUnassignedDepartment,
          ),
          _select(
            t('clubOrganization.location'),
            _locationId,
            widget.locations,
            (value) => setState(() => _locationId = value),
          ),
          _select(
            t('clubOrganization.trainingGroup'),
            _groupId,
            widget.trainingGroups,
            (value) {
              setState(() {
                _groupId = value;
                final group = widget.trainingGroups
                    .where((item) => _int(item['id']) == value)
                    .firstOrNull;
                if (group != null) {
                  _departmentId =
                      _int(group['club_department_id']) ?? _departmentId;
                  _locationId = _int(group['club_location_id']) ?? _locationId;
                }
              });
            },
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(context, {
            'name': widget.team['name'],
            'sport_type': widget.team['sport_type'],
            'club_department_id': _departmentId,
            'club_location_id': _locationId,
            'club_training_group_id': _groupId,
          }),
          child: Text(t('common.save')),
        ),
      ],
    );
  }

  Widget _select(
    String label,
    int? value,
    List<Map<String, dynamic>> items,
    ValueChanged<int?> onChanged, {
    bool allowNull = true,
  }) => DropdownButtonFormField<int?>(
    key: ValueKey('$label-$value'),
    initialValue: value,
    decoration: InputDecoration(labelText: label),
    items: [
      if (allowNull)
        DropdownMenuItem(
          value: null,
          child: Text(
            AirmiusScope.of(context).t('clubOrganization.unassigned'),
          ),
        ),
      for (final item in items)
        DropdownMenuItem(
          value: _int(item['id']),
          child: Text('${item['name']}'),
        ),
    ],
    onChanged: onChanged,
  );
}

int? _int(dynamic value) => value is int ? value : int.tryParse('$value');
