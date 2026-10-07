import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_date_input.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';

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
  });

  final int clubId;
  final String subjectType;
  final int subjectId;
  final String subjectTitle;

  @override
  State<ClubMetadataSubjectScreen> createState() =>
      _ClubMetadataSubjectScreenState();
}

class _ClubMetadataSubjectScreenState extends State<ClubMetadataSubjectScreen> {
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

  Future<void> _load() async {
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
      final data = raw is Map<String, dynamic> ? raw : <String, dynamic>{};
      _replaceState(data);
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

  Future<void> _save() async {
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
        return;
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
      if (raw is Map<String, dynamic>) _replaceState(raw);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('clubMetadataValues.saved'))));
    } catch (error) {
      if (mounted) setState(() => _error = _safeError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(title: Text(t('clubMetadataValues.title'))),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              widget.subjectTitle,
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 4),
            Text(
              t('clubMetadataValues.hint'),
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
            if (_loading || _saving) ...[
              const SizedBox(height: 12),
              const LinearProgressIndicator(),
            ],
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
            if (!_loading) ...[
              const SizedBox(height: 16),
              if (_items('fields').isEmpty && _items('categories').isEmpty)
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
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: _saving ? null : _save,
                icon: const Icon(Icons.save_outlined),
                label: Text(t('common.save')),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _field(Map<String, dynamic> field) {
    final t = AirmiusScope.of(context).t;
    final id = _int(field['id']);
    if (id == null) return const SizedBox.shrink();
    final active = field['is_active'] == true;
    final type = '${field['field_type'] ?? 'text'}';
    final badges = [
      if (field['is_required'] == true) t('clubMetadataValues.required'),
      if (field['is_sensitive'] == true) t('clubMetadataValues.sensitive'),
      if (!active) t('clubMetadataValues.inactive'),
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
            ? (value) => setState(() => _booleans[id] = value)
            : null,
      );
    } else if (type == 'select') {
      final options = field['options'] is List
          ? (field['options'] as List).map((item) => '$item').toList()
          : const <String>[];
      final value = _controllers[id]?.text;
      input = DropdownButtonFormField<String>(
        initialValue: options.contains(value) ? value : null,
        decoration: InputDecoration(labelText: label),
        items: options
            .map((item) => DropdownMenuItem(value: item, child: Text(item)))
            .toList(),
        onChanged: active
            ? (value) => setState(() => _controllers[id]?.text = value ?? '')
            : null,
      );
    } else {
      input = TextFormField(
        controller: _controllers[id],
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
    final active = category['is_active'] == true;
    return CheckboxListTile(
      value: _categories.contains(id),
      onChanged: active
          ? (selected) => setState(() {
              if (selected == true) {
                _categories.add(id);
              } else {
                _categories.remove(id);
              }
            })
          : null,
      title: Text('${category['name'] ?? ''}'),
      subtitle: active ? null : Text(t('clubMetadataValues.inactiveHistory')),
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
