import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_date_input.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';

class ClubGovernanceBodyForm extends StatefulWidget {
  const ClubGovernanceBodyForm({
    super.key,
    required this.body,
    required this.people,
    required this.templates,
    required this.onSave,
  });

  final Map<String, dynamic>? body;
  final List<Map<String, dynamic>> people;
  final List<Map<String, dynamic>> templates;
  final Future<void> Function(Map<String, dynamic> payload) onSave;

  @override
  State<ClubGovernanceBodyForm> createState() => _ClubGovernanceBodyFormState();
}

class _ClubGovernanceBodyFormState extends State<ClubGovernanceBodyForm> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _description;
  late String _type;
  String? _templateKey;
  DateTime? _startsOn;
  DateTime? _endsOn;
  late bool _isPublic;
  bool _saving = false;
  String? _error;
  late List<_GovernancePositionDraft> _positions;

  bool get _creating => widget.body == null;

  List<Map<String, dynamic>> get _templates =>
      widget.templates.isNotEmpty ? widget.templates : _fallbackTemplates;

  @override
  void initState() {
    super.initState();
    final body = widget.body ?? const <String, dynamic>{};
    _name = TextEditingController(text: '${body['name'] ?? ''}');
    _description = TextEditingController(text: '${body['description'] ?? ''}');
    _type = '${body['type'] ?? 'board'}';
    _templateKey = body['template_key']?.toString();
    _startsOn = parseAirmiusDate(body['starts_on']?.toString());
    _endsOn = parseAirmiusDate(body['ends_on']?.toString());
    _isPublic = body['is_public'] == true;
    _positions = _positionsFromBody(body);
  }

  @override
  void dispose() {
    _name.dispose();
    _description.dispose();
    for (final position in _positions) {
      position.dispose();
    }
    super.dispose();
  }

  List<_GovernancePositionDraft> _positionsFromBody(Map<String, dynamic> body) {
    final rawPositions = body['positions'];
    if (rawPositions is List && rawPositions.isNotEmpty) {
      return rawPositions
          .whereType<Map<String, dynamic>>()
          .map(_GovernancePositionDraft.fromMap)
          .toList();
    }

    final rawAssignments = body['assignments'];
    if (rawAssignments is! List) return [];
    return rawAssignments.whereType<Map<String, dynamic>>().map((assignment) {
      return _GovernancePositionDraft.fromLegacyAssignment(assignment);
    }).toList();
  }

  void _applyTemplate(Map<String, dynamic>? template) {
    for (final position in _positions) {
      position.dispose();
    }
    if (template == null) {
      setState(() {
        _templateKey = null;
        _type = 'committee';
        _name.clear();
        _description.clear();
        _positions = [];
      });
      return;
    }

    final positions = template['positions'];
    setState(() {
      _templateKey = '${template['key']}';
      _type = '${template['type'] ?? 'committee'}';
      _name.text = '${template['name'] ?? ''}';
      _description.text = '${template['description'] ?? ''}';
      _positions = positions is List
          ? positions
                .whereType<Map<String, dynamic>>()
                .map(_GovernancePositionDraft.fromTemplate)
                .toList()
          : [];
    });
  }

  Future<void> _pickBodyDate({required bool end}) async {
    final initial = end ? _endsOn : _startsOn;
    final selected = await showDatePicker(
      context: context,
      initialDate: initial ?? _startsOn ?? DateTime.now(),
      firstDate: DateTime(1900),
      lastDate: DateTime(2200),
    );
    if (selected == null || !mounted) return;
    setState(() {
      if (end) {
        _endsOn = selected;
      } else {
        _startsOn = selected;
      }
    });
  }

  Future<void> _selectPerson(_GovernancePositionDraft position) async {
    final selected = await Navigator.push<Map<String, dynamic>>(
      context,
      MaterialPageRoute(
        builder: (_) => _GovernancePersonPickerPage(
          people: widget.people,
          excludedKeys: position.assignees.map((item) => item.key).toSet(),
        ),
      ),
    );
    if (selected == null || !mounted) return;
    setState(() {
      final assignee = _GovernanceAssigneeDraft.fromPerson(selected);
      if (position.allowsMultiple) {
        position.assignees.add(assignee);
      } else {
        position.assignees = [assignee];
      }
    });
  }

  void _addPosition() {
    setState(() {
      _positions.add(_GovernancePositionDraft.empty());
    });
  }

  void _removePosition(int index) {
    final removed = _positions.removeAt(index);
    removed.dispose();
    setState(() {});
  }

  Future<void> _save() async {
    FocusManager.instance.primaryFocus?.unfocus();
    final valid = _formKey.currentState?.validate() ?? false;
    final duplicateTitles = <String>{};
    final seenTitles = <String>{};
    for (final position in _positions) {
      final normalized = position.title.text.trim().toLowerCase();
      if (normalized.isNotEmpty && !seenTitles.add(normalized)) {
        duplicateTitles.add(normalized);
      }
    }
    if (!valid || duplicateTitles.isNotEmpty) {
      setState(() {
        _error = duplicateTitles.isNotEmpty
            ? AirmiusScope.of(context).t('clubGovernance.duplicatePosition')
            : AirmiusScope.of(context).t('clubGovernance.completeRequired');
      });
      return;
    }
    if (_startsOn != null && _endsOn != null && _endsOn!.isBefore(_startsOn!)) {
      setState(() {
        _error = AirmiusScope.of(context).t('clubGovernance.invalidPeriod');
      });
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.onSave({
        'template_key': _templateKey,
        'type': _type,
        'name': _name.text.trim(),
        'description': _nullIfEmpty(_description.text),
        'starts_on': formatAirmiusApiDate(_startsOn),
        'ends_on': formatAirmiusApiDate(_endsOn),
        'is_public': _isPublic,
        'replace_positions': true,
        'positions': [
          for (var index = 0; index < _positions.length; index++)
            _positions[index].toPayload(index, _startsOn, _endsOn),
        ],
      });
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is AirmiusApiException
            ? error.userMessage
            : AirmiusScope.of(context).t('clubGovernance.error');
      });
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _creating
              ? t('clubGovernance.addBody')
              : t('clubGovernance.editBody'),
        ),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: Theme.of(context).scaffoldBackgroundColor,
            border: Border(
              top: BorderSide(color: Theme.of(context).dividerColor),
            ),
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _saving ? null : () => Navigator.pop(context),
                    child: Text(t('common.cancel')),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: _saving ? null : _save,
                    icon: _saving
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.check),
                    label: Text(t('common.save')),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
          children: [
            Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 680),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_error != null) ...[
                      MaterialBanner(
                        content: Text(_error!),
                        leading: const Icon(Icons.error_outline),
                        actions: [
                          TextButton(
                            onPressed: () => setState(() => _error = null),
                            child: Text(t('common.close')),
                          ),
                        ],
                      ),
                      const SizedBox(height: 24),
                    ],
                    if (_creating) ...[
                      _sectionTitle(
                        t('clubGovernance.templateSection'),
                        t('clubGovernance.templateHint'),
                      ),
                      const SizedBox(height: 12),
                      _templateChooser(),
                      const SizedBox(height: 28),
                    ],
                    _sectionTitle(
                      t('clubGovernance.basicsSection'),
                      t('clubGovernance.basicsHint'),
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String>(
                      initialValue: _type,
                      decoration: InputDecoration(
                        labelText: t('clubGovernance.type'),
                        prefixIcon: const Icon(Icons.account_balance_outlined),
                      ),
                      items: const ['board', 'committee', 'working_group']
                          .map(
                            (type) => DropdownMenuItem(
                              value: type,
                              child: Text(t('clubGovernance.type.$type')),
                            ),
                          )
                          .toList(),
                      onChanged: _saving
                          ? null
                          : (value) => setState(() => _type = value ?? _type),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _name,
                      maxLength: 160,
                      textInputAction: TextInputAction.next,
                      decoration: InputDecoration(
                        labelText: '${t('clubGovernance.name')} *',
                        prefixIcon: const Icon(Icons.label_outline),
                      ),
                      validator: (value) => (value ?? '').trim().isEmpty
                          ? t('clubGovernance.requiredName')
                          : null,
                    ),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _description,
                      maxLength: 3000,
                      minLines: 3,
                      maxLines: 5,
                      decoration: InputDecoration(
                        labelText: t('clubGovernance.description'),
                        alignLabelWithHint: true,
                        prefixIcon: const Padding(
                          padding: EdgeInsets.only(bottom: 56),
                          child: Icon(Icons.notes_outlined),
                        ),
                      ),
                    ),
                    const SizedBox(height: 28),
                    _sectionTitle(
                      t('clubGovernance.positionsSection'),
                      t('clubGovernance.positionsHint'),
                    ),
                    const SizedBox(height: 12),
                    if (_positions.isEmpty)
                      _emptyPositions()
                    else
                      for (
                        var index = 0;
                        index < _positions.length;
                        index++
                      ) ...[
                        _positionEditor(index),
                        if (index != _positions.length - 1)
                          const SizedBox(height: 12),
                      ],
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: _saving ? null : _addPosition,
                      icon: const Icon(Icons.add),
                      label: Text(t('clubGovernance.addPosition')),
                    ),
                    const SizedBox(height: 28),
                    _sectionTitle(
                      t('clubGovernance.termSection'),
                      t('clubGovernance.termHint'),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: _dateField(
                            label: t('clubGovernance.startsOnShort'),
                            value: _startsOn,
                            onTap: () => _pickBodyDate(end: false),
                            onClear: () => setState(() => _startsOn = null),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: _dateField(
                            label: t('clubGovernance.endsOnShort'),
                            value: _endsOn,
                            onTap: () => _pickBodyDate(end: true),
                            onClear: () => setState(() => _endsOn = null),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 28),
                    _sectionTitle(
                      t('clubGovernance.visibilitySection'),
                      t('clubGovernance.visibilityHint'),
                    ),
                    const SizedBox(height: 8),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(t('clubGovernance.showPublicly')),
                      subtitle: Text(t('clubGovernance.publicBodyHint')),
                      secondary: const Icon(Icons.public_outlined),
                      value: _isPublic,
                      onChanged: _saving
                          ? null
                          : (value) => setState(() => _isPublic = value),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _sectionTitle(String title, String hint) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 4),
        Text(
          hint,
          style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
        ),
      ],
    );
  }

  Widget _templateChooser() {
    final t = AirmiusScope.of(context).t;
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final template in _templates)
          ChoiceChip(
            selected: _templateKey == '${template['key']}',
            label: Text('${template['name']}'),
            avatar: Icon(_templateIcon('${template['key']}'), size: 18),
            onSelected: _saving ? null : (_) => _applyTemplate(template),
          ),
        ChoiceChip(
          selected: _templateKey == null,
          label: Text(t('clubGovernance.customTemplate')),
          avatar: const Icon(Icons.edit_outlined, size: 18),
          onSelected: _saving ? null : (_) => _applyTemplate(null),
        ),
      ],
    );
  }

  IconData _templateIcon(String key) => switch (key) {
    'board' => Icons.account_balance_outlined,
    'youth_committee' => Icons.groups_2_outlined,
    'finance_committee' => Icons.account_balance_wallet_outlined,
    'procurement_committee' => Icons.shopping_bag_outlined,
    'events_committee' => Icons.event_outlined,
    'disciplinary_committee' => Icons.gavel_outlined,
    'cash_audit' => Icons.fact_check_outlined,
    'coaching_team' => Icons.sports_outlined,
    _ => Icons.group_work_outlined,
  };

  Widget _emptyPositions() {
    final t = AirmiusScope.of(context).t;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        border: Border.all(color: Theme.of(context).dividerColor),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        children: [
          const Icon(Icons.badge_outlined),
          const SizedBox(width: 12),
          Expanded(child: Text(t('clubGovernance.emptyPositionsHint'))),
        ],
      ),
    );
  }

  Widget _positionEditor(int index) {
    final t = AirmiusScope.of(context).t;
    final position = _positions[index];
    return Material(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      shape: RoundedRectangleBorder(
        side: BorderSide(color: Theme.of(context).dividerColor),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${t('clubGovernance.position')} ${index + 1}',
                    style: Theme.of(context).textTheme.labelLarge,
                  ),
                ),
                IconButton(
                  tooltip: t('clubGovernance.removePosition'),
                  onPressed: _saving ? null : () => _removePosition(index),
                  icon: const Icon(Icons.delete_outline),
                ),
              ],
            ),
            const SizedBox(height: 4),
            TextFormField(
              controller: position.title,
              maxLength: 160,
              decoration: InputDecoration(
                labelText: '${t('clubGovernance.position')} *',
              ),
              validator: (value) => (value ?? '').trim().isEmpty
                  ? t('clubGovernance.requiredPosition')
                  : null,
            ),
            const SizedBox(height: 6),
            TextFormField(
              controller: position.responsibilities,
              maxLength: 3000,
              minLines: 2,
              maxLines: 4,
              decoration: InputDecoration(
                labelText: t('clubGovernance.responsibilities'),
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              t('clubGovernance.occupants'),
              style: Theme.of(context).textTheme.labelLarge,
            ),
            const SizedBox(height: 8),
            if (position.assignees.isEmpty)
              Text(
                t('clubGovernance.vacant'),
                style: TextStyle(color: airmiusMutedColor(context)),
              )
            else
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final assignee in position.assignees)
                    InputChip(
                      avatar: Icon(
                        assignee.kind == 'external'
                            ? Icons.person_outline
                            : Icons.person,
                        size: 18,
                      ),
                      label: Text(
                        assignee.kind == 'external'
                            ? '${assignee.name} · ${t('clubGovernance.externalShort')}'
                            : assignee.name,
                      ),
                      onDeleted: _saving
                          ? null
                          : () => setState(
                              () => position.assignees.remove(assignee),
                            ),
                    ),
                ],
              ),
            const SizedBox(height: 10),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: OutlinedButton.icon(
                onPressed: _saving ? null : () => _selectPerson(position),
                icon: Icon(
                  position.assignees.isEmpty
                      ? Icons.person_add_alt
                      : Icons.swap_horiz,
                ),
                label: Text(
                  position.assignees.isEmpty
                      ? t('clubGovernance.assignPerson')
                      : position.allowsMultiple
                      ? t('clubGovernance.addAnotherPerson')
                      : t('clubGovernance.changePerson'),
                ),
              ),
            ),
            const Divider(height: 24),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              title: Text(t('clubGovernance.requiredPositionLabel')),
              value: position.isRequired,
              onChanged: _saving
                  ? null
                  : (value) => setState(
                      () => position.isRequired = value ?? position.isRequired,
                    ),
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              title: Text(t('clubGovernance.allowMultiple')),
              value: position.allowsMultiple,
              onChanged: _saving
                  ? null
                  : (value) => setState(() {
                      position.allowsMultiple =
                          value ?? position.allowsMultiple;
                      if (!position.allowsMultiple &&
                          position.assignees.length > 1) {
                        position.assignees = [position.assignees.first];
                      }
                    }),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(t('clubGovernance.publishPosition')),
              subtitle: Text(t('clubGovernance.publishPositionHint')),
              value: position.isPublic,
              onChanged: _saving
                  ? null
                  : (value) => setState(() => position.isPublic = value),
            ),
          ],
        ),
      ),
    );
  }

  Widget _dateField({
    required String label,
    required DateTime? value,
    required VoidCallback onTap,
    required VoidCallback onClear,
  }) {
    final t = AirmiusScope.of(context).t;
    return InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: const Icon(Icons.calendar_today_outlined),
        suffixIcon: value == null
            ? null
            : IconButton(
                tooltip: t('common.delete'),
                onPressed: _saving ? null : onClear,
                icon: const Icon(Icons.close),
              ),
      ),
      child: InkWell(
        onTap: _saving ? null : onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Text(
            value == null
                ? t('clubGovernance.dateNotSet')
                : formatAirmiusDate(value),
            style: value == null
                ? TextStyle(color: airmiusMutedColor(context))
                : null,
          ),
        ),
      ),
    );
  }
}

class _GovernancePersonPickerPage extends StatefulWidget {
  const _GovernancePersonPickerPage({
    required this.people,
    required this.excludedKeys,
  });

  final List<Map<String, dynamic>> people;
  final Set<String> excludedKeys;

  @override
  State<_GovernancePersonPickerPage> createState() =>
      _GovernancePersonPickerPageState();
}

class _GovernancePersonPickerPageState
    extends State<_GovernancePersonPickerPage> {
  final _search = TextEditingController();
  String _filter = 'all';

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final query = _search.text.trim().toLowerCase();
    final people = widget.people.where((person) {
      final key = '${person['kind']}:${person['id']}';
      if (widget.excludedKeys.contains(key)) return false;
      if (_filter != 'all' && '${person['kind']}' != _filter) return false;
      return query.isEmpty ||
          '${person['name'] ?? ''}'.toLowerCase().contains(query);
    }).toList();

    return Scaffold(
      appBar: AppBar(title: Text(t('clubGovernance.selectPerson'))),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _search,
              autofocus: true,
              decoration: InputDecoration(
                hintText: t('clubGovernance.searchPerson'),
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _search.text.isEmpty
                    ? null
                    : IconButton(
                        onPressed: () {
                          _search.clear();
                          setState(() {});
                        },
                        icon: const Icon(Icons.close),
                      ),
              ),
              onChanged: (_) => setState(() {}),
            ),
          ),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                for (final filter in const ['all', 'user', 'external']) ...[
                  ChoiceChip(
                    selected: _filter == filter,
                    label: Text(t('clubGovernance.people.$filter')),
                    onSelected: (_) => setState(() => _filter = filter),
                  ),
                  const SizedBox(width: 8),
                ],
              ],
            ),
          ),
          const SizedBox(height: 8),
          Expanded(
            child: people.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(
                        _filter == 'external'
                            ? t('clubGovernance.noExternalPeople')
                            : t('clubGovernance.noPeopleFound'),
                        textAlign: TextAlign.center,
                      ),
                    ),
                  )
                : ListView.separated(
                    itemCount: people.length,
                    separatorBuilder: (_, _) => const Divider(height: 1),
                    itemBuilder: (context, index) {
                      final person = people[index];
                      final external = person['kind'] == 'external';
                      return ListTile(
                        minVerticalPadding: 12,
                        leading: CircleAvatar(
                          child: Icon(
                            external ? Icons.person_outline : Icons.person,
                          ),
                        ),
                        title: Text('${person['name'] ?? ''}'),
                        subtitle: Text(
                          external
                              ? t('clubGovernance.externalPerson')
                              : t('clubGovernance.registeredMember'),
                        ),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => Navigator.pop(context, person),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}

class _GovernancePositionDraft {
  _GovernancePositionDraft({
    required this.id,
    required String title,
    required String responsibilities,
    required this.isRequired,
    required this.allowsMultiple,
    required this.isPublic,
    required this.assignees,
  }) : title = TextEditingController(text: title),
       responsibilities = TextEditingController(text: responsibilities);

  factory _GovernancePositionDraft.empty() => _GovernancePositionDraft(
    id: null,
    title: '',
    responsibilities: '',
    isRequired: false,
    allowsMultiple: false,
    isPublic: false,
    assignees: [],
  );

  factory _GovernancePositionDraft.fromTemplate(Map<String, dynamic> data) =>
      _GovernancePositionDraft(
        id: null,
        title: '${data['title'] ?? ''}',
        responsibilities: '${data['responsibilities'] ?? ''}',
        isRequired: data['is_required'] == true,
        allowsMultiple: data['allows_multiple'] == true,
        isPublic: data['is_public'] == true,
        assignees: [],
      );

  factory _GovernancePositionDraft.fromMap(Map<String, dynamic> data) {
    final rawAssignments = data['assignments'];
    return _GovernancePositionDraft(
      id: _asInt(data['id']),
      title: '${data['title'] ?? ''}',
      responsibilities: '${data['responsibilities'] ?? ''}',
      isRequired: data['is_required'] == true,
      allowsMultiple: data['allows_multiple'] == true,
      isPublic: data['is_public'] == true,
      assignees: rawAssignments is List
          ? rawAssignments
                .whereType<Map<String, dynamic>>()
                .map(_GovernanceAssigneeDraft.fromAssignment)
                .toList()
          : [],
    );
  }

  factory _GovernancePositionDraft.fromLegacyAssignment(
    Map<String, dynamic> assignment,
  ) => _GovernancePositionDraft(
    id: null,
    title: '${assignment['position_title'] ?? ''}',
    responsibilities: '${assignment['responsibilities'] ?? ''}',
    isRequired: false,
    allowsMultiple: false,
    isPublic: assignment['is_public'] == true,
    assignees: [_GovernanceAssigneeDraft.fromAssignment(assignment)],
  );

  final int? id;
  final TextEditingController title;
  final TextEditingController responsibilities;
  bool isRequired;
  bool allowsMultiple;
  bool isPublic;
  List<_GovernanceAssigneeDraft> assignees;

  Map<String, dynamic> toPayload(
    int index,
    DateTime? bodyStart,
    DateTime? bodyEnd,
  ) => {
    if (id != null) 'id': id,
    'title': title.text.trim(),
    'responsibilities': _nullIfEmpty(responsibilities.text),
    'sort_order': index,
    'is_required': isRequired,
    'allows_multiple': allowsMultiple,
    'is_public': isPublic,
    'replace_assignments': true,
    'assignments': [
      for (final assignee in assignees)
        assignee.toPayload(isPublic, bodyStart, bodyEnd),
    ],
  };

  void dispose() {
    title.dispose();
    responsibilities.dispose();
  }
}

class _GovernanceAssigneeDraft {
  const _GovernanceAssigneeDraft({
    required this.id,
    required this.kind,
    required this.personId,
    required this.name,
  });

  factory _GovernanceAssigneeDraft.fromPerson(Map<String, dynamic> person) =>
      _GovernanceAssigneeDraft(
        id: null,
        kind: '${person['kind']}',
        personId: _asInt(person['id'])!,
        name: '${person['name'] ?? ''}',
      );

  factory _GovernanceAssigneeDraft.fromAssignment(
    Map<String, dynamic> assignment,
  ) {
    final externalId = _asInt(assignment['club_external_member_id']);
    final person = assignment['person'];
    return _GovernanceAssigneeDraft(
      id: _asInt(assignment['id']),
      kind: externalId == null ? 'user' : 'external',
      personId: externalId ?? _asInt(assignment['user_id'])!,
      name: person is Map ? '${person['name'] ?? ''}' : '',
    );
  }

  final int? id;
  final String kind;
  final int personId;
  final String name;

  String get key => '$kind:$personId';

  Map<String, dynamic> toPayload(
    bool isPublic,
    DateTime? bodyStart,
    DateTime? bodyEnd,
  ) => {
    if (id != null) 'id': id,
    'user_id': kind == 'user' ? personId : null,
    'club_external_member_id': kind == 'external' ? personId : null,
    'starts_on': formatAirmiusApiDate(bodyStart),
    'ends_on': formatAirmiusApiDate(bodyEnd),
    'is_public': isPublic,
  };
}

int? _asInt(dynamic value) => value is int ? value : int.tryParse('$value');

String? _nullIfEmpty(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}

const _fallbackTemplates = <Map<String, dynamic>>[
  {
    'key': 'board',
    'type': 'board',
    'name': 'Vorstand',
    'description': 'Leitung und Vertretung des Vereins.',
    'positions': [
      {'title': 'Vorsitz', 'is_required': true},
      {'title': 'Stellvertretung'},
      {'title': 'Kassenwart'},
      {'title': 'Schriftführung'},
    ],
  },
  {
    'key': 'youth_committee',
    'type': 'committee',
    'name': 'Jugendausschuss',
    'description': 'Koordiniert Jugendangebote und Interessen.',
    'positions': [
      {'title': 'Jugendleitung', 'is_required': true},
      {'title': 'Trainervertretung'},
      {'title': 'Jugendsprecher'},
    ],
  },
  {
    'key': 'finance_committee',
    'type': 'committee',
    'name': 'Finanzausschuss',
    'description': 'Bereitet Finanzthemen und Budgets vor.',
    'positions': [
      {'title': 'Leitung Finanzen', 'is_required': true},
      {'title': 'Kassenwart'},
      {'title': 'Vorstandsvertretung'},
    ],
  },
  {
    'key': 'procurement_committee',
    'type': 'committee',
    'name': 'Beschaffungsausschuss',
    'description': 'Plant und bewertet Anschaffungen.',
    'positions': [
      {'title': 'Leitung', 'is_required': true},
      {'title': 'Bedarfserfassung'},
      {'title': 'Budgetfreigabe'},
    ],
  },
  {
    'key': 'events_committee',
    'type': 'committee',
    'name': 'Veranstaltungsausschuss',
    'description': 'Organisiert Veranstaltungen und Ausflüge.',
    'positions': [
      {'title': 'Leitung', 'is_required': true},
      {'title': 'Helferkoordination'},
      {'title': 'Logistik'},
      {'title': 'Kommunikation'},
    ],
  },
  {
    'key': 'disciplinary_committee',
    'type': 'committee',
    'name': 'Disziplinarausschuss',
    'description': 'Behandelt Regelverstöße und Beschwerden.',
    'positions': [
      {'title': 'Vorsitz', 'is_required': true},
      {'title': 'Beisitz', 'allows_multiple': true},
      {'title': 'Protokollführung'},
    ],
  },
  {
    'key': 'cash_audit',
    'type': 'committee',
    'name': 'Kassenprüfung',
    'description': 'Kontrolliert Einnahmen, Ausgaben und Belege.',
    'positions': [
      {'title': 'Erster Kassenprüfer', 'is_required': true},
      {'title': 'Zweiter Kassenprüfer', 'is_required': true},
    ],
  },
  {
    'key': 'coaching_team',
    'type': 'working_group',
    'name': 'Trainerteam',
    'description': 'Bündelt gemeinsam verantwortliche Trainer.',
    'positions': [
      {'title': 'Cheftrainer'},
      {'title': 'Trainer', 'allows_multiple': true},
      {'title': 'Co-Trainer', 'allows_multiple': true},
      {'title': 'Teammanagement'},
    ],
  },
];
