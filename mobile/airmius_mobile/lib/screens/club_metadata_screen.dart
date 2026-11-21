import 'package:flutter/material.dart';
import '../widgets/airmius_form.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';

class ClubMetadataScreen extends StatefulWidget {
  const ClubMetadataScreen({super.key, required this.club});

  final ClubSummary club;

  @override
  State<ClubMetadataScreen> createState() => _ClubMetadataScreenState();
}

class _ClubMetadataScreenState extends State<ClubMetadataScreen> {
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
  bool get _canEdit =>
      _data['can_edit'] == true ||
      (!_data.containsKey('can_edit') && _canManage);
  bool get _canDelete =>
      _data['can_delete'] == true ||
      (!_data.containsKey('can_delete') && _canManage);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  String _safeError(Object error) => error is AirmiusApiException
      ? error.userMessage
      : AirmiusScope.of(context).t('clubMetadata.error');

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await _client.clubMetadata(widget.club.id);
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

  Future<bool> _confirmDelete() async {
    final t = AirmiusScope.of(context).t;
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: Text(t('clubMetadata.deleteTitle')),
            content: Text(t('clubMetadata.deleteMessage')),
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

  Future<void> _editField([Map<String, dynamic>? item]) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => _CustomFieldPage(
          item: item,
          onSave: (payload) async {
            await _client.saveClubMetadataCustomField(
              widget.club.id,
              payload,
              fieldId: _int(item?['id']),
            );
          },
        ),
      ),
    );
    if (saved == true && mounted) await _load();
  }

  Future<void> _editCategory([Map<String, dynamic>? item]) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => _CategoryPage(
          item: item,
          onSave: (payload) async {
            await _client.saveClubMetadataCategory(
              widget.club.id,
              payload,
              categoryId: _int(item?['id']),
            );
          },
        ),
      ),
    );
    if (saved == true && mounted) await _load();
  }

  Future<void> _editRange([Map<String, dynamic>? item]) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => _NumberRangePage(
          item: item,
          onSave: (payload) async {
            await _client.saveClubMetadataNumberRange(
              widget.club.id,
              payload,
              numberRangeId: _int(item?['id']),
            );
          },
        ),
      ),
    );
    if (saved == true && mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: Text(t('clubMetadata.title')),
          bottom: TabBar(
            tabs: [
              Tab(text: t('clubMetadata.fields')),
              Tab(text: t('clubMetadata.categories')),
              Tab(text: t('clubMetadata.ranges')),
            ],
          ),
        ),
        body: Column(
          children: [
            if (_busy) const LinearProgressIndicator(),
            if (_error != null)
              MaterialBanner(
                content: Text(_error!),
                actions: [
                  TextButton(onPressed: _load, child: Text(t('common.retry'))),
                ],
              ),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : TabBarView(
                      children: [
                        _list(
                          keyName: 'custom_fields',
                          add: _editField,
                          itemBuilder: _fieldTile,
                        ),
                        _list(
                          keyName: 'categories',
                          add: _editCategory,
                          itemBuilder: _categoryTile,
                        ),
                        _list(
                          keyName: 'number_ranges',
                          add: _editRange,
                          itemBuilder: _rangeTile,
                        ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _list({
    required String keyName,
    required VoidCallback add,
    required Widget Function(Map<String, dynamic>) itemBuilder,
  }) {
    final t = AirmiusScope.of(context).t;
    final items = _items(keyName);
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_canEdit) ...[
            FilledButton.icon(
              onPressed: _busy ? null : add,
              icon: const Icon(Icons.add),
              label: Text(t('clubMetadata.add')),
            ),
            const SizedBox(height: 12),
          ],
          if (items.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 32),
              child: Center(
                child: Text(
                  t('clubMetadata.empty'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
              ),
            ),
          ...items.map(itemBuilder),
        ],
      ),
    );
  }

  Widget _fieldTile(Map<String, dynamic> item) {
    final t = AirmiusScope.of(context).t;
    final flags = [
      if (item['is_required'] == true) t('clubMetadata.required'),
      if (item['is_sensitive'] == true) t('clubMetadata.sensitive'),
      t(
        'clubMetadata.status.${item['is_active'] == true ? 'active' : 'inactive'}',
      ),
    ];
    return Card(
      child: ListTile(
        leading: const Icon(Icons.dynamic_form_outlined),
        title: Text('${item['label'] ?? ''}'),
        subtitle: Text(
          '${t('clubMetadata.entity.${item['entity_type']}')} · '
          '${t('clubMetadata.type.${item['field_type']}')}\n'
          '${item['key'] ?? ''} · ${flags.join(' · ')}',
        ),
        isThreeLine: true,
        trailing: _menu(
          edit: () => _editField(item),
          delete: () async {
            if (await _confirmDelete() && mounted) {
              await _write(
                () => _client.deleteClubMetadataCustomField(
                  widget.club.id,
                  _int(item['id'])!,
                ),
              );
            }
          },
        ),
      ),
    );
  }

  Widget _categoryTile(Map<String, dynamic> item) {
    final t = AirmiusScope.of(context).t;
    return Card(
      child: ListTile(
        leading: const Icon(Icons.sell_outlined),
        title: Text('${item['name'] ?? ''}'),
        subtitle: Text(
          '${t('clubMetadata.entity.${item['scope']}')} · '
          '${t('clubMetadata.status.${item['is_active'] == true ? 'active' : 'inactive'}')}',
        ),
        trailing: _menu(
          edit: () => _editCategory(item),
          delete: () async {
            if (await _confirmDelete() && mounted) {
              await _write(
                () => _client.deleteClubMetadataCategory(
                  widget.club.id,
                  _int(item['id'])!,
                ),
              );
            }
          },
        ),
      ),
    );
  }

  Widget _rangeTile(Map<String, dynamic> item) {
    final t = AirmiusScope.of(context).t;
    final isDefault = item['is_default'] == true;
    return Card(
      child: Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Column(
          children: [
            ListTile(
              leading: const Icon(Icons.pin_outlined),
              title: Text('${item['name'] ?? ''}'),
              subtitle: Text(
                '${t('clubMetadata.entity.${item['scope']}')} · '
                '${t('clubMetadata.preview')}: ${item['preview'] ?? '—'}\n'
                '${t('clubMetadata.next')}: ${item['next_number'] ?? '—'} · '
                '${t('clubMetadata.allocations')}: ${item['allocations_count'] ?? 0}',
              ),
              isThreeLine: true,
              trailing: _menu(
                edit: () => _editRange(item),
                delete: () async {
                  if (await _confirmDelete() && mounted) {
                    await _write(
                      () => _client.deleteClubMetadataNumberRange(
                        widget.club.id,
                        _int(item['id'])!,
                      ),
                    );
                  }
                },
              ),
            ),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton.icon(
                onPressed:
                    !_canEdit ||
                        _busy ||
                        (item['is_active'] != true && !isDefault)
                    ? null
                    : () => _write(
                        () => _client.setClubMetadataNumberRangeDefault(
                          widget.club.id,
                          _int(item['id'])!,
                          enabled: !isDefault,
                        ),
                      ),
                icon: Icon(isDefault ? Icons.star : Icons.star_border),
                label: Text(
                  t(
                    isDefault
                        ? 'clubMetadata.clearDefault'
                        : 'clubMetadata.setDefault',
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _menu({required VoidCallback edit, required VoidCallback delete}) {
    final t = AirmiusScope.of(context).t;
    if (!_canEdit && !_canDelete) return const SizedBox.shrink();
    return PopupMenuButton<String>(
      enabled: !_busy,
      onSelected: (value) => value == 'edit' ? edit() : delete(),
      itemBuilder: (_) => [
        if (_canEdit)
          PopupMenuItem(value: 'edit', child: Text(t('common.edit'))),
        if (_canDelete)
          PopupMenuItem(value: 'delete', child: Text(t('common.delete'))),
      ],
    );
  }
}

class _CustomFieldPage extends StatefulWidget {
  const _CustomFieldPage({this.item, required this.onSave});
  final Map<String, dynamic>? item;
  final Future<void> Function(Map<String, dynamic>) onSave;

  @override
  State<_CustomFieldPage> createState() => _CustomFieldPageState();
}

class _CustomFieldPageState extends _MetadataFormState<_CustomFieldPage> {
  static const _entities = [
    'member',
    'external_member',
    'team',
    'event',
    'inventory_item',
  ];
  static const _types = [
    'text',
    'textarea',
    'number',
    'date',
    'boolean',
    'select',
  ];
  late String _entity;
  late String _type;
  late final TextEditingController _label;
  late final TextEditingController _key;
  late final TextEditingController _options;
  late final TextEditingController _order;
  late bool _required;
  late bool _sensitive;
  late bool _active;

  @override
  void initState() {
    super.initState();
    final item = widget.item ?? const {};
    _entity = '${item['entity_type'] ?? 'member'}';
    _type = '${item['field_type'] ?? 'text'}';
    _label = TextEditingController(text: '${item['label'] ?? ''}');
    _key = TextEditingController(text: '${item['key'] ?? ''}');
    _options = TextEditingController(
      text: item['options'] is List ? (item['options'] as List).join('\n') : '',
    );
    _order = TextEditingController(text: '${item['sort_order'] ?? 0}');
    _required = item['is_required'] == true;
    _sensitive = item['is_sensitive'] == true;
    _active = item['is_active'] != false;
  }

  @override
  void dispose() {
    _label.dispose();
    _key.dispose();
    _options.dispose();
    _order.dispose();
    super.dispose();
  }

  void _submit() {
    savePayload({
      'entity_type': _entity,
      'key': _key.text.trim(),
      'label': _label.text.trim(),
      'field_type': _type,
      'options': _type == 'select' ? _optionValues : null,
      'is_required': _required,
      'is_sensitive': _sensitive,
      'is_active': _active,
      'sort_order': int.tryParse(_order.text) ?? 0,
    }, widget.onSave);
  }

  List<String> get _optionValues => _options.text
      .split(RegExp(r'\r?\n'))
      .map((value) => value.trim())
      .where((value) => value.isNotEmpty)
      .toList();

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _MetadataEditorPage(
      formKey: formKey,
      busy: busy,
      error: error,
      title: Text(
        t(
          widget.item == null
              ? 'clubMetadata.addField'
              : 'clubMetadata.editField',
        ),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _dropdown(
              t,
              'clubMetadata.target',
              _entity,
              _entities,
              (v) => setState(() => _entity = v),
            ),
            _dropdown(
              t,
              'clubMetadata.fieldType',
              _type,
              _types,
              (v) => setState(() => _type = v),
            ),
            _field('label', t('clubMetadata.name'), _label),
            _field('key', t('clubMetadata.key'), _key),
            if (_type == 'select')
              _field('options', t('clubMetadata.options'), _options, lines: 3),
            _field(
              'sort_order',
              t('clubMetadata.sortOrder'),
              _order,
              number: true,
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _required,
              onChanged: (value) => setState(() => _required = value),
              title: Text(t('clubMetadata.required')),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _sensitive,
              onChanged: (value) => setState(() => _sensitive = value),
              title: Text(t('clubMetadata.sensitive')),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _active,
              onChanged: (value) => setState(() => _active = value),
              title: Text(t('clubMetadata.active')),
            ),
          ],
        ),
      ),
      onSave: _submit,
    );
  }
}

class _CategoryPage extends StatefulWidget {
  const _CategoryPage({this.item, required this.onSave});
  final Map<String, dynamic>? item;
  final Future<void> Function(Map<String, dynamic>) onSave;

  @override
  State<_CategoryPage> createState() => _CategoryPageState();
}

class _CategoryPageState extends _MetadataFormState<_CategoryPage> {
  static const _scopes = ['member', 'team', 'event', 'inventory_item'];
  late String _scope;
  late final TextEditingController _name;
  late final TextEditingController _color;
  late final TextEditingController _order;
  late bool _active;

  @override
  void initState() {
    super.initState();
    final item = widget.item ?? const {};
    _scope = '${item['scope'] ?? 'member'}';
    _name = TextEditingController(text: '${item['name'] ?? ''}');
    _color = TextEditingController(text: '${item['color'] ?? '#2563EB'}');
    _order = TextEditingController(text: '${item['sort_order'] ?? 0}');
    _active = item['is_active'] != false;
  }

  @override
  void dispose() {
    _name.dispose();
    _color.dispose();
    _order.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _MetadataEditorPage(
      formKey: formKey,
      busy: busy,
      error: error,
      title: Text(
        t(
          widget.item == null
              ? 'clubMetadata.addCategory'
              : 'clubMetadata.editCategory',
        ),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _dropdown(
              t,
              'clubMetadata.target',
              _scope,
              _scopes,
              (v) => setState(() => _scope = v),
            ),
            _field('name', t('clubMetadata.name'), _name),
            _field('color', t('clubMetadata.color'), _color),
            _field(
              'sort_order',
              t('clubMetadata.sortOrder'),
              _order,
              number: true,
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _active,
              onChanged: (value) => setState(() => _active = value),
              title: Text(t('clubMetadata.active')),
            ),
          ],
        ),
      ),
      onSave: () {
        savePayload({
          'scope': _scope,
          'name': _name.text.trim(),
          'color': _color.text.trim(),
          'is_active': _active,
          'sort_order': int.tryParse(_order.text) ?? 0,
        }, widget.onSave);
      },
    );
  }
}

class _NumberRangePage extends StatefulWidget {
  const _NumberRangePage({this.item, required this.onSave});
  final Map<String, dynamic>? item;
  final Future<void> Function(Map<String, dynamic>) onSave;

  @override
  State<_NumberRangePage> createState() => _NumberRangePageState();
}

class _NumberRangePageState extends _MetadataFormState<_NumberRangePage> {
  @override
  bool get requiresYear => _resetPolicy == 'yearly';
  static const _scopes = [
    'member',
    'invoice',
    'receipt',
    'donation',
    'inventory_item',
    'shop_invoice',
    'shop_credit_note',
    'shop_sku',
  ];
  static const _resetPolicies = ['never', 'yearly'];
  late String _scope;
  late String _resetPolicy;
  late final TextEditingController _name;
  late final TextEditingController _prefix;
  late final TextEditingController _suffix;
  late final TextEditingController _padding;
  late final TextEditingController _start;
  late bool _active;

  @override
  void initState() {
    super.initState();
    final item = widget.item ?? const {};
    _scope = '${item['scope'] ?? 'member'}';
    _resetPolicy = '${item['reset_policy'] ?? 'never'}';
    _name = TextEditingController(text: '${item['name'] ?? ''}');
    _prefix = TextEditingController(text: '${item['prefix'] ?? ''}');
    _suffix = TextEditingController(text: '${item['suffix'] ?? ''}');
    _padding = TextEditingController(text: '${item['padding'] ?? 4}');
    _start = TextEditingController(text: '${item['start_number'] ?? 1}');
    _active = item['is_active'] != false;
  }

  @override
  void dispose() {
    for (final controller in [_name, _prefix, _suffix, _padding, _start]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _MetadataEditorPage(
      formKey: formKey,
      busy: busy,
      error: error,
      title: Text(
        t(
          widget.item == null
              ? 'clubMetadata.addRange'
              : 'clubMetadata.editRange',
        ),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _dropdown(
              t,
              'clubMetadata.target',
              _scope,
              _scopes,
              (v) => setState(() => _scope = v),
            ),
            _field('name', t('clubMetadata.name'), _name),
            _field('prefix', t('clubMetadata.prefix'), _prefix),
            _field('suffix', t('clubMetadata.suffix'), _suffix),
            _field(
              'padding',
              t('clubMetadata.padding'),
              _padding,
              number: true,
            ),
            _field(
              'start_number',
              t('clubMetadata.start'),
              _start,
              number: true,
            ),
            _dropdown(
              t,
              'clubMetadata.resetPolicy',
              _resetPolicy,
              _resetPolicies,
              (v) => setState(() => _resetPolicy = v),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _active,
              onChanged: (value) => setState(() => _active = value),
              title: Text(t('clubMetadata.active')),
            ),
          ],
        ),
      ),
      onSave: () {
        savePayload({
          'scope': _scope,
          'name': _name.text.trim(),
          'prefix': _prefix.text.trim(),
          'suffix': _suffix.text.trim(),
          'padding': int.tryParse(_padding.text) ?? 4,
          'start_number': int.tryParse(_start.text) ?? 1,
          'reset_policy': _resetPolicy,
          'is_active': _active,
        }, widget.onSave);
      },
    );
  }
}

abstract class _MetadataFormState<T extends StatefulWidget> extends State<T> {
  final formKey = GlobalKey<FormState>();
  final inputs = <String, TextEditingController>{};
  bool busy = false;
  String? error;
  Map<String, String> serverErrors = {};
  bool get requiresYear => false;

  Future<void> savePayload(
    Map<String, dynamic> payload,
    Future<void> Function(Map<String, dynamic>) action,
  ) async {
    if (busy || !(formKey.currentState?.validate() ?? false)) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await action(payload);
      if (mounted) Navigator.of(context).pop(true);
    } catch (exception) {
      if (!mounted) return;
      setState(() {
        error = formErrorMessage(context, exception);
        serverErrors = formServerErrors(exception);
      });
      if (serverErrors.isNotEmpty) formKey.currentState?.validate();
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  String? _validate(String key, String? raw) {
    for (final entry in serverErrors.entries) {
      if (entry.key == key || entry.key.startsWith('$key.')) return entry.value;
    }
    final value = (raw ?? '').trim();
    String message(String name) => formMessage(context, name);
    if (['name', 'label'].contains(key) &&
        (value.isEmpty || value.length > 160)) {
      return message('name');
    }
    if (key == 'key' &&
        (value.length > 80 || !RegExp(r'^[a-z][a-z0-9_]*$').hasMatch(value))) {
      return message('key');
    }
    if (key == 'color' &&
        value.isNotEmpty &&
        !RegExp(r'^#[0-9A-Fa-f]{6}$').hasMatch(value)) {
      return message('color');
    }
    if (key == 'options') {
      final values = value
          .split(RegExp(r'\r?\n'))
          .map((s) => s.trim())
          .where((s) => s.isNotEmpty)
          .toList();
      if (values.isEmpty ||
          values.length > 50 ||
          values.toSet().length != values.length ||
          values.any((s) => s.length > 160)) {
        return message('options');
      }
    }
    final number = int.tryParse(value);
    if (key == 'sort_order' &&
        (number == null || number < 0 || number > 10000)) {
      return message('order');
    }
    if (key == 'padding' && (number == null || number < 1 || number > 12)) {
      return message('padding');
    }
    if (key == 'start_number' &&
        (number == null || number < 1 || number > 999999999999)) {
      return message('start');
    }
    if (key == 'prefix' || key == 'suffix') {
      if (value.length > 40 ||
          !RegExp(r'^(?:[^{}]|\{YYYY\}|\{YY\})*$').hasMatch(value)) {
        return message('token');
      }
      final combined =
          (inputs['prefix']?.text ?? '') + (inputs['suffix']?.text ?? '');
      if (key == 'prefix' &&
          requiresYear &&
          !combined.contains('{YYYY}') &&
          !combined.contains('{YY}')) {
        return message('year');
      }
    }
    return null;
  }

  Widget _field(
    String key,
    String label,
    TextEditingController controller, {
    int lines = 1,
    bool number = false,
  }) {
    inputs[key] = controller;
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextFormField(
        key: ValueKey('metadata-field-$key'),
        controller: controller,
        enabled: !busy,
        maxLines: lines,
        autovalidateMode: AutovalidateMode.onUserInteraction,
        keyboardType: number
            ? const TextInputType.numberWithOptions(signed: true)
            : TextInputType.text,
        decoration: InputDecoration(labelText: label, errorMaxLines: 4),
        validator: (value) => _validate(key, value),
        onChanged: (_) {
          if (serverErrors.isNotEmpty || error != null) {
            setState(() {
              serverErrors.removeWhere(
                (field, _) => field == key || field.startsWith('$key.'),
              );
              error = null;
            });
          }
        },
      ),
    );
  }
}

/// Form pages close only after a successful API response.
class _MetadataEditorPage extends StatelessWidget {
  const _MetadataEditorPage({
    required this.title,
    required this.content,
    required this.formKey,
    required this.onSave,
    required this.busy,
    this.error,
  });
  final Widget title, content;
  final GlobalKey<FormState> formKey;
  final VoidCallback onSave;
  final bool busy;
  final String? error;

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !busy,
    child: Scaffold(
      appBar: AppBar(title: title),
      body: SafeArea(
        bottom: false,
        child: Align(
          alignment: Alignment.topCenter,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 720),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: AirmiusForm(
                key: formKey,
                child: AbsorbPointer(absorbing: busy, child: content),
              ),
            ),
          ),
        ),
      ),
      bottomNavigationBar: Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Semantics(
                      liveRegion: true,
                      child: Text(
                        error!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                    ),
                  ),
                if (busy)
                  const Padding(
                    padding: EdgeInsets.only(bottom: 12),
                    child: LinearProgressIndicator(),
                  ),
                Row(
                  children: [
                    Expanded(
                      child: TextButton(
                        onPressed: busy ? null : () => Navigator.pop(context),
                        child: Text(
                          AirmiusScope.of(context).t('common.cancel'),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: FilledButton(
                        onPressed: busy ? null : onSave,
                        child: Text(AirmiusScope.of(context).t('common.save')),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

Widget _dropdown(
  String Function(String) t,
  String labelKey,
  String value,
  List<String> values,
  ValueChanged<String> onChanged,
) => Padding(
  padding: const EdgeInsets.only(bottom: 12),
  child: DropdownButtonFormField<String>(
    isExpanded: true,
    initialValue: value,
    decoration: InputDecoration(labelText: t(labelKey)),
    items: values
        .map(
          (item) => DropdownMenuItem(
            value: item,
            child: Text(t('clubMetadata.entity.$item')),
          ),
        )
        .toList(),
    onChanged: (value) {
      if (value != null) onChanged(value);
    },
  ),
);

int? _int(Object? value) => switch (value) {
  int number => number,
  String text => int.tryParse(text),
  _ => null,
};
