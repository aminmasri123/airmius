import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_date_input.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_form.dart';
import 'club_metadata_screen.dart';

class ClubMetadataSubjectButton extends StatelessWidget {
  const ClubMetadataSubjectButton({
    super.key,
    required this.clubId,
    required this.subjectType,
    required this.subjectId,
    required this.subjectTitle,
    this.compact = false,
  });

  final int clubId;
  final String subjectType;
  final int subjectId;
  final String subjectTitle;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final label = AirmiusScope.of(context).t('clubMetadataValues.action');
    void open() => Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ClubMetadataSubjectScreen(
          clubId: clubId,
          subjectType: subjectType,
          subjectId: subjectId,
          subjectTitle: subjectTitle,
        ),
      ),
    );
    if (compact) {
      return IconButton(
        tooltip: label,
        onPressed: open,
        icon: const Icon(Icons.fact_check_outlined),
      );
    }
    return OutlinedButton.icon(
      onPressed: open,
      icon: const Icon(Icons.fact_check_outlined),
      label: Text(label),
    );
  }
}

class ClubMetadataSubjectScreen extends StatefulWidget {
  const ClubMetadataSubjectScreen({
    super.key,
    required this.clubId,
    required this.subjectType,
    required this.subjectId,
    required this.subjectTitle,
    this.embedded = false,
    this.onManage,
  });

  final bool embedded;
  final Future<void> Function()? onManage;
  final int clubId;
  final String subjectType;
  final int subjectId;
  final String subjectTitle;

  @override
  ClubMetadataSubjectScreenState createState() =>
      ClubMetadataSubjectScreenState();
}

class ClubMetadataSubjectScreenState extends State<ClubMetadataSubjectScreen>
    with AutomaticKeepAliveClientMixin<ClubMetadataSubjectScreen> {
  @override
  bool get wantKeepAlive => true;
  final _formKey = GlobalKey<FormState>();
  bool _dirty = false;
  bool get canEdit => _data['can_edit'] == true;
  Future<bool> saveIfChanged() async => _dirty ? saveChanges() : !_loading;
  Map<String, dynamic> _data = const {};
  final Map<int, TextEditingController> _controllers = {};
  final Map<int, bool> _booleans = {};
  final Set<int> _categories = {};
  bool _loading = true;
  bool _saving = false;
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

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  String _safeError(Object error) => error is AirmiusApiException
      ? error.userMessage
      : AirmiusScope.of(context).t('clubMetadataValues.error');

  Future<void> _load({bool preserveDraft = false}) async {
    final draft = preserveDraft
        ? {
            for (final entry in _controllers.entries)
              entry.key: entry.value.text,
          }
        : <int, String>{};
    final booleans = Map<int, bool>.from(_booleans);
    final categories = Set<int>.from(_categories);
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await _client.clubMetadataSubject(
        widget.clubId,
        widget.subjectType,
        widget.subjectId,
      );
      final raw = response['data'];
      final data = raw is Map<String, dynamic>
          ? Map<String, dynamic>.from(raw)
          : <String, dynamic>{};
      // Older backends expose this permission on the configuration endpoint,
      // but do not yet include it in subject responses. Missing is not denied.
      if (!data.containsKey('can_edit')) {
        final configuration = await _client.clubMetadata(widget.clubId);
        final permissions = configuration['data'];
        if (permissions is! Map || permissions['can_edit'] is! bool) {
          throw StateError('Metadata edit permission unavailable');
        }
        data['can_edit'] = permissions['can_edit'];
      }
      _replaceState(data);
      if (preserveDraft) {
        for (final entry in draft.entries) {
          _controllers[entry.key]?.text = entry.value;
        }
        for (final entry in booleans.entries) {
          if (_booleans.containsKey(entry.key)) {
            _booleans[entry.key] = entry.value;
          }
        }
        _categories
          ..clear()
          ..addAll(categories);
      }
    } catch (error) {
      if (mounted) setState(() => _error = _safeError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _replaceState(Map<String, dynamic> data) {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    _controllers.clear();
    _booleans.clear();
    _categories.clear();
    final fields = data['fields'];
    if (fields is List) {
      for (final field in fields.whereType<Map<String, dynamic>>()) {
        final id = _int(field['id']);
        if (id == null) continue;
        if (field['field_type'] == 'boolean') {
          _booleans[id] = field['value'] == true;
        } else {
          final rawValue = field['value']?.toString();
          _controllers[id] = TextEditingController(
            text: field['field_type'] == 'date'
                ? formatAirmiusDate(parseAirmiusDate(rawValue))
                : rawValue ?? '',
          );
        }
      }
    }
    final categories = data['categories'];
    if (categories is List) {
      for (final category in categories.whereType<Map<String, dynamic>>()) {
        final id = _int(category['id']);
        if (id != null && category['selected'] == true) _categories.add(id);
      }
    }
    if (mounted) setState(() => _data = data);
  }

  Future<bool> saveChanges() async {
    if (_saving || _loading || !canEdit) return false;
    if (!(_formKey.currentState?.validate() ?? false)) return false;
    final t = AirmiusScope.of(context).t;
    final values = <String, dynamic>{};
    for (final field in _items('fields')) {
      if (field['is_active'] != true) continue;
      final id = _int(field['id']);
      if (id == null) continue;
      dynamic value;
      if (field['field_type'] == 'boolean') {
        value = _booleans[id] ?? false;
      } else {
        final text = _controllers[id]?.text.trim() ?? '';
        value = field['field_type'] == 'date'
            ? formatAirmiusApiDate(parseAirmiusDate(text))
            : text.isEmpty
            ? null
            : text;
      }
      if (field['is_required'] == true && value == null) {
        setState(() => _error = t('clubMetadataValues.requiredError'));
        return false;
      }
      values['$id'] = value;
    }
    final activeCategoryIds = _items('categories')
        .where((item) => item['is_active'] == true)
        .map((item) => _int(item['id']))
        .whereType<int>()
        .where(_categories.contains)
        .toList();

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final response = await _client.saveClubMetadataSubject(
        widget.clubId,
        widget.subjectType,
        widget.subjectId,
        {'values': values, 'category_ids': activeCategoryIds},
      );
      final raw = response['data'];
      if (raw is Map<String, dynamic>) {
        _replaceState({
          ...raw,
          // A successful write was authorized by the server, including on
          // older backends whose response omits the permission flag.
          'can_edit': raw.containsKey('can_edit') ? raw['can_edit'] : true,
        });
      }
      if (!mounted) return false;
      _dirty = false;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('clubMetadataValues.saved'))));
      return true;
    } catch (error) {
      if (mounted) setState(() => _error = _safeError(error));
      return false;
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final t = AirmiusScope.of(context).t;
    final header = Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            widget.embedded
                ? t('clubMetadataValues.title')
                : widget.subjectTitle,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
          ),
          if (canEdit) ...[
            const SizedBox(height: 8),
            OutlinedButton.icon(
              key: const ValueKey('metadata-manage'),
              onPressed: _saving
                  ? null
                  : () async {
                      if (widget.onManage != null) {
                        await widget.onManage!();
                      } else {
                        await Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) =>
                                ClubMetadataScreen(clubId: widget.clubId),
                          ),
                        );
                      }
                      if (mounted) await _load(preserveDraft: true);
                    },
              icon: const Icon(Icons.tune),
              label: Text(t('clubMetadataValues.manageFields')),
            ),
          ],
        ],
      ),
    );
    final content = Column(
      children: [
        header,
        if (_loading || _saving) const LinearProgressIndicator(),
        Expanded(
          child: AirmiusForm(
            key: _formKey,
            child: ListView(
              key: const ValueKey('metadata-values-list'),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
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
                if (_error != null && _data.isEmpty)
                  TextButton(onPressed: _load, child: Text(t('common.retry'))),
                if (!_loading) ...[
                  if (_data.containsKey('can_edit') && !canEdit)
                    Text(t('clubMetadataValues.readOnly')),
                  if (_data.isNotEmpty &&
                      _items('fields').isEmpty &&
                      _items('categories').isEmpty)
                    Text(t('clubMetadataValues.empty')),
                  ..._items('fields').map(_field),
                  if (_items('categories').isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Text(
                      t('clubMetadataValues.categories'),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    ..._items('categories').map(_category),
                  ],
                ],
              ],
            ),
          ),
        ),
      ],
    );
    if (widget.embedded) return content;
    return Scaffold(
      appBar: AppBar(title: Text(t('clubMetadataValues.title'))),
      body: content,
      bottomNavigationBar: !_loading && canEdit
          ? SafeArea(
              child: Padding(
                padding: EdgeInsets.fromLTRB(
                  16,
                  16,
                  16,
                  16 + MediaQuery.viewInsetsOf(context).bottom,
                ),
                child: FilledButton.icon(
                  onPressed: _saving ? null : saveChanges,
                  icon: const Icon(Icons.save_outlined),
                  label: Text(t('common.save')),
                ),
              ),
            )
          : null,
    );
  }

  Widget _field(Map<String, dynamic> field) {
    final t = AirmiusScope.of(context).t;
    final id = _int(field['id']);
    if (id == null) return const SizedBox.shrink();
    final active = field['is_active'] == true && canEdit && !_saving;
    final type = '${field['field_type'] ?? 'text'}';
    final badges = [
      if (field['is_required'] == true) t('clubMetadataValues.required'),
      if (field['is_sensitive'] == true) t('clubMetadataValues.sensitive'),
      if (field['is_active'] != true) t('clubMetadataValues.inactive'),
    ];
    final label = badges.isEmpty
        ? '${field['label'] ?? ''}'
        : '${field['label'] ?? ''} · ${badges.join(' · ')}';
    Widget input;
    if (type == 'boolean') {
      input = SwitchListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(label),
        value: _booleans[id] ?? false,
        onChanged: active
            ? (value) => setState(() {
                _dirty = true;
                _booleans[id] = value;
              })
            : null,
      );
    } else if (type == 'select') {
      final options = field['options'] is List
          ? (field['options'] as List).map((item) => '$item').toList()
          : const <String>[];
      final value = _controllers[id]?.text;
      input = DropdownButtonFormField<String>(
        initialValue: options.contains(value) ? value : null,
        key: ValueKey('metadata-value-$id'),
        validator: (value) =>
            active &&
                field['is_required'] == true &&
                (value == null || value.isEmpty)
            ? t('clubMetadataValues.requiredError')
            : null,
        decoration: InputDecoration(labelText: label),
        items: options
            .map((item) => DropdownMenuItem(value: item, child: Text(item)))
            .toList(),
        onChanged: active
            ? (value) => setState(() {
                _dirty = true;
                _controllers[id]?.text = value ?? '';
              })
            : null,
      );
    } else {
      input = TextFormField(
        key: ValueKey('metadata-value-$id'),
        controller: _controllers[id],
        onChanged: (_) => _dirty = true,
        validator: (value) =>
            active &&
                field['is_required'] == true &&
                (value == null || value.trim().isEmpty)
            ? t('clubMetadataValues.requiredError')
            : null,
        enabled: active,
        minLines: type == 'textarea' ? 3 : 1,
        maxLines: type == 'textarea' ? 5 : 1,
        keyboardType: type == 'number'
            ? const TextInputType.numberWithOptions(decimal: true, signed: true)
            : type == 'date'
            ? TextInputType.datetime
            : TextInputType.text,
        decoration: InputDecoration(
          labelText: label,
          hintText: type == 'date' ? 'DD.MM.YYYY' : null,
        ),
        inputFormatters: type == 'date'
            ? const [AirmiusDateInputFormatter()]
            : null,
      );
    }
    return Card(
      child: Padding(padding: const EdgeInsets.all(12), child: input),
    );
  }

  Widget _category(Map<String, dynamic> category) {
    final t = AirmiusScope.of(context).t;
    final id = _int(category['id']);
    if (id == null) return const SizedBox.shrink();
    final active = category['is_active'] == true && canEdit && !_saving;
    return CheckboxListTile(
      value: _categories.contains(id),
      onChanged: active
          ? (selected) => setState(() {
              _dirty = true;
              if (selected == true) {
                _categories.add(id);
              } else {
                _categories.remove(id);
              }
            })
          : null,
      title: Text('${category['name'] ?? ''}'),
      subtitle: category['is_active'] == true
          ? null
          : Text(t('clubMetadataValues.inactiveHistory')),
      secondary: Icon(Icons.circle, color: _color(category['color'])),
    );
  }
}

Color _color(Object? raw) {
  final value = raw?.toString().replaceFirst('#', '');
  final parsed = value == null ? null : int.tryParse('FF$value', radix: 16);
  return parsed == null ? Colors.blueGrey : Color(parsed);
}

int? _int(Object? value) => switch (value) {
  int number => number,
  String text => int.tryParse(text),
  _ => null,
};
