import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import '../core/club_policy_upload_client.dart';
import '../core/airmius_date_input.dart';
import '../widgets/airmius_form.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import 'file_manager_screen.dart';

class ClubPolicyDocumentsScreen extends StatefulWidget {
  const ClubPolicyDocumentsScreen({
    super.key,
    this.club,
    this.pickFile,
    this.uploadFile,
  });
  final Future<PlatformFile?> Function()? pickFile;
  final Future<AirmiusJson> Function(
    int clubId,
    String type,
    PlatformFile file,
  )?
  uploadFile;

  final ClubSummary? club;

  @override
  State<ClubPolicyDocumentsScreen> createState() =>
      _ClubPolicyDocumentsScreenState();
}

class _ClubPolicyDocumentsScreenState extends State<ClubPolicyDocumentsScreen> {
  Map<String, dynamic> _data = const {};
  bool _loading = true;
  bool _busy = false;
  String? _error;
  ClubSummary? _club;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  bool get _canManage => _data['can_manage'] == true;
  bool get _canEdit =>
      _data['can_edit'] == true ||
      (!_data.containsKey('can_edit') && _canManage);
  bool get _canDelete =>
      _data['can_delete'] == true ||
      (!_data.containsKey('can_delete') && _canManage);
  bool get _canDownload =>
      _data['can_download'] == true ||
      (!_data.containsKey('can_download') && _canManage);

  List<Map<String, dynamic>> get _documents {
    final value = _data['documents'];
    return value is List
        ? value.whereType<Map<String, dynamic>>().toList()
        : const [];
  }

  @override
  void initState() {
    super.initState();
    _club = widget.club;
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  String _safeError(Object error) =>
      error is AirmiusApiException && error.statusCode == 404
      ? AirmiusScope.of(context).t('clubPolicyDocuments.unavailable')
      : error is AirmiusApiException
      ? error.userMessage
      : AirmiusScope.of(context).t('clubPolicyDocuments.error');

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      var club = _club;
      if (club == null) {
        final services = AirmiusServicesScope.of(context);
        final page = await services.repositories.clubs.searchClubs(mine: true);
        final candidates = page.items
            .map(ClubSummary.fromAirmiusClub)
            .where((item) => item.canManage)
            .toList();
        if (candidates.isEmpty) {
          throw StateError('no_managed_club');
        }
        club = candidates.first;
        _club = club;
      }
      final response = await _client.clubPolicyDocuments(club.id);
      final data = response['data'];
      final parsed = data is Map<String, dynamic> ? data : <String, dynamic>{};
      if (mounted) {
        setState(() {
          _data = parsed;
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = _safeError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<List<Map<String, dynamic>>> _loadPolicyFiles() async {
    final files = <Map<String, dynamic>>[];
    var page = 1;
    while (true) {
      final response = await _client.clubPolicyFiles(_club!.id, page: page);
      final rows = response['data'];
      if (rows is List) files.addAll(rows.whereType<Map<String, dynamic>>());
      final meta = response['meta'];
      final last = meta is Map ? _int(meta['last_page']) ?? page : page;
      if (page >= last) return files;
      page++;
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

  Future<void> _edit([Map<String, dynamic>? document]) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => _PolicyDocumentPage(
          document: document,
          loadFiles: _loadPolicyFiles,
          clubId: _club!.id,
          pickFile: widget.pickFile,
          uploadFile: (type, file) => widget.uploadFile != null
              ? widget.uploadFile!(_club!.id, type, file)
              : uploadClubPolicyFile(_client, _club!.id, type, file),
          onSave: (payload) => _client.saveClubPolicyDocument(
            _club!.id,
            payload,
            documentId: _int(document?['id']),
          ),
        ),
      ),
    );
    if (saved == true && mounted) await _load();
  }

  Future<void> _delete(Map<String, dynamic> document) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('clubPolicyDocuments.deleteTitle')),
        content: Text(t('clubPolicyDocuments.deleteMessage')),
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
    );
    if (confirmed != true || !mounted) return;
    await _write(
      () => _client.deleteClubPolicyDocument(_club!.id, _int(document['id'])!),
    );
  }

  void _openFile(Map<String, dynamic> document) {
    final file = document['file'];
    final name = file is Map<String, dynamic>
        ? '${file['display_name'] ?? ''}'
        : '';
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => FileManagerScreen(
          initialScope: 'club',
          initialClubId: _club!.id,
          initialSearch: name,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(t('clubPolicyDocuments.title')),
        actions: [
          if (_canEdit)
            IconButton(
              tooltip: t('clubPolicyDocuments.add'),
              onPressed: _busy ? null : () => _edit(),
              icon: const Icon(Icons.note_add_outlined),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              t('clubPolicyDocuments.hint'),
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
            ] else if (_documents.isEmpty) ...[
              const SizedBox(height: 24),
              if (_error == null) Text(t('clubPolicyDocuments.empty')),
            ] else
              for (final type in const [
                'statutes',
                'regulation',
                'contribution_model',
              ]) ...[
                const SizedBox(height: 20),
                Text(
                  t('clubPolicyDocuments.type.$type'),
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 8),
                for (final document in _documents.where(
                  (item) => item['type'] == type,
                ))
                  _card(document),
              ],
          ],
        ),
      ),
    );
  }

  Widget _card(Map<String, dynamic> document) {
    final t = AirmiusScope.of(context).t;
    final file = document['file'];
    final fileName = file is Map<String, dynamic>
        ? '${file['display_name'] ?? ''}'
        : '';
    final end = '${document['valid_until'] ?? ''}'.trim();
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.description_outlined),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${document['title'] ?? ''}',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      Text(
                        '${t('clubPolicyDocuments.version')} ${document['version_label'] ?? ''}',
                        style: TextStyle(color: airmiusMutedColor(context)),
                      ),
                    ],
                  ),
                ),
                Text(t('clubPolicyDocuments.status.${document['status']}')),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              '${document['valid_from'] ?? ''} – ${end.isEmpty ? t('clubPolicyDocuments.unlimited') : end}',
            ),
            Text(
              document['is_public'] == true
                  ? t('clubPolicyDocuments.public')
                  : t('clubPolicyDocuments.internal'),
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
            if ('${document['notes'] ?? ''}'.trim().isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text('${document['notes']}'),
              ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              children: [
                if (_canDownload || document['is_public'] == true)
                  TextButton.icon(
                    onPressed: () => _openFile(document),
                    icon: const Icon(Icons.folder_open_outlined),
                    label: Text(
                      fileName.isEmpty
                          ? t('clubPolicyDocuments.openFile')
                          : fileName,
                    ),
                  ),
                if (_canEdit)
                  TextButton(
                    onPressed: _busy ? null : () => _edit(document),
                    child: Text(t('common.edit')),
                  ),
                if (_canDelete)
                  TextButton(
                    onPressed: _busy ? null : () => _delete(document),
                    child: Text(t('common.delete')),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _PolicyDocumentPage extends StatefulWidget {
  const _PolicyDocumentPage({
    required this.document,
    required this.loadFiles,
    required this.clubId,
    required this.onSave,
    required this.uploadFile,
    this.pickFile,
  });
  final Map<String, dynamic>? document;
  final Future<List<Map<String, dynamic>>> Function() loadFiles;
  final int clubId;
  final Future<AirmiusJson> Function(Map<String, dynamic>) onSave;
  final Future<AirmiusJson> Function(String, PlatformFile) uploadFile;
  final Future<PlatformFile?> Function()? pickFile;
  @override
  State<_PolicyDocumentPage> createState() => _PolicyDocumentPageState();
}

class _PolicyDocumentPageState extends State<_PolicyDocumentPage> {
  final _formKey = GlobalKey<FormState>();
  late String _type;
  late final TextEditingController _title, _version, _start, _end, _notes;
  int? _fileId;
  bool _public = false, _busy = false;
  String _source = 'existing';
  String? _error, _fileName, _uploadedType;
  PlatformFile? _pending;
  @override
  void initState() {
    super.initState();
    final data = widget.document ?? const {};
    _type = '${data['type'] ?? 'statutes'}';
    _title = TextEditingController(text: '${data['title'] ?? ''}');
    _version = TextEditingController(text: '${data['version_label'] ?? ''}');
    _start = TextEditingController(
      text: formatAirmiusDate(parseAirmiusDate(data['valid_from']?.toString())),
    );
    _end = TextEditingController(
      text: formatAirmiusDate(
        parseAirmiusDate(data['valid_until']?.toString()),
      ),
    );
    _notes = TextEditingController(text: '${data['notes'] ?? ''}');
    _public = data['is_public'] == true;
    final file = data['file'];
    if (file is Map) {
      _fileId = _int(file['id']);
      _fileName = file['display_name']?.toString();
    }
  }

  @override
  void dispose() {
    for (final field in [_title, _version, _start, _end, _notes]) {
      field.dispose();
    }
    super.dispose();
  }

  Future<void> _pickUpload() async {
    try {
      final file = widget.pickFile != null
          ? await widget.pickFile!()
          : (await FilePicker.platform.pickFiles(withData: true))?.files.single;
      if (!mounted || file == null) return;
      if (file.size == 0 || file.size > 50 * 1024 * 1024) {
        setState(
          () => _error = AirmiusScope.of(
            context,
          ).t('clubPolicyDocuments.fileLimit'),
        );
        return;
      }
      setState(() {
        _pending = file;
        _fileId = null;
        _fileName = file.name;
        _uploadedType = null;
        _error = null;
      });
    } catch (error) {
      if (mounted) setState(() => _error = formErrorMessage(context, error));
    }
  }

  Future<void> _pickExisting() async {
    final files = <Map<String, dynamic>>[];
    if (_fileId != null && !files.any((f) => _int(f['id']) == _fileId)) {
      files.add({'id': _fileId, 'display_name': _fileName});
    }
    final file = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (_) =>
            _PolicyFilePage(files: files, loadFiles: widget.loadFiles),
      ),
    );
    if (mounted && file != null) {
      setState(() {
        _fileId = _int(file['id']);
        _fileName = file['display_name']?.toString();
        _error = null;
      });
    }
  }

  Future<void> _calendar(TextEditingController controller) async {
    final current = parseAirmiusDate(controller.text) ?? DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: current.year < 1900 || current.year > 2200
          ? DateTime.now()
          : current,
      firstDate: DateTime(1900),
      lastDate: DateTime(2200, 12, 31),
    );
    if (selected != null && mounted) {
      setState(() => controller.text = formatAirmiusDate(selected));
    }
  }

  Future<void> _submit() async {
    if (_busy || !(_formKey.currentState?.validate() ?? false)) return;
    final t = AirmiusScope.of(context).t;
    if ((_source == 'existing' && _fileId == null) ||
        (_source == 'upload' && _pending == null)) {
      setState(() => _error = t('clubPolicyDocuments.required'));
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      if (_source == 'upload' && (_fileId == null || _uploadedType != _type)) {
        final file = await widget.uploadFile(_type, _pending!);
        _fileId = _int(file['id']);
        if (_fileId == null) throw StateError('Invalid upload');
        _uploadedType = _type;
      }
      await widget.onSave({
        'type': _type,
        'title': _title.text.trim(),
        'version_label': _version.text.trim(),
        'valid_from': formatAirmiusApiDate(parseAirmiusDate(_start.text)),
        'valid_until': _end.text.trim().isEmpty
            ? null
            : formatAirmiusApiDate(parseAirmiusDate(_end.text)),
        'is_public': _public,
        'notes': _notes.text.trim().isEmpty ? null : _notes.text.trim(),
        'file_id': _fileId,
      });
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) setState(() => _error = formErrorMessage(context, error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _field(
    TextEditingController controller,
    String key, {
    bool required = false,
    bool date = false,
    int lines = 1,
  }) {
    final t = AirmiusScope.of(context).t;
    return TextFormField(
      key: ValueKey('policy-$key'),
      controller: controller,
      enabled: !_busy,
      maxLines: lines,
      keyboardType: date
          ? TextInputType.datetime
          : lines > 1
          ? TextInputType.multiline
          : TextInputType.text,
      textInputAction: lines > 1
          ? TextInputAction.newline
          : TextInputAction.next,
      inputFormatters: date
          ? const [AirmiusDateInputFormatter(trailingSeparator: true)]
          : null,
      decoration: InputDecoration(
        labelText: t('clubPolicyDocuments.$key'),
        hintText: date ? t('clubPolicyDocuments.dateFormat') : null,
        errorMaxLines: 3,
        suffixIcon: date
            ? IconButton(
                tooltip: t('clubPolicyDocuments.calendar'),
                onPressed: _busy ? null : () => _calendar(controller),
                icon: const Icon(Icons.calendar_month_outlined),
              )
            : null,
      ),
      validator: (value) {
        final text = (value ?? '').trim();
        if (required && text.isEmpty) return t('clubPolicyDocuments.required');
        if (date && text.isNotEmpty && parseAirmiusDate(text) == null) {
          return t('clubPolicyDocuments.invalidRange');
        }
        if (key == 'validUntil' && text.isNotEmpty) {
          final end = parseAirmiusDate(text),
              start = parseAirmiusDate(_start.text);
          if (end != null && start != null && end.isBefore(start)) {
            return t('clubPolicyDocuments.invalidRange');
          }
        }
        return null;
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return PopScope(
      canPop: !_busy,
      child: Scaffold(
        appBar: AppBar(
          title: Text(
            t(
              widget.document == null
                  ? 'clubPolicyDocuments.add'
                  : 'clubPolicyDocuments.edit',
            ),
          ),
        ),
        body: AirmiusForm(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              DropdownButtonFormField<String>(
                initialValue: _type,
                decoration: InputDecoration(
                  labelText: t('clubPolicyDocuments.type'),
                ),
                items: ['statutes', 'regulation', 'contribution_model']
                    .map(
                      (type) => DropdownMenuItem(
                        value: type,
                        child: Text(t('clubPolicyDocuments.type.$type')),
                      ),
                    )
                    .toList(),
                onChanged: _busy
                    ? null
                    : (value) => setState(() {
                        _type = value ?? _type;
                      }),
              ),
              const SizedBox(height: 16),
              Text(
                t('clubPolicyDocuments.fileSource'),
                style: Theme.of(context).textTheme.titleMedium,
              ),
              RadioGroup<String>(
                groupValue: _source,
                onChanged: (value) {
                  if (!_busy && value != null && value != _source) {
                    setState(() {
                      _source = value;
                      _fileId = null;
                      _fileName = value == 'upload' ? _pending?.name : null;
                    });
                  }
                },
                child: Column(
                  children: [
                    RadioListTile<String>(
                      contentPadding: EdgeInsets.zero,
                      enabled: !_busy,
                      title: Text(t('clubPolicyDocuments.useExisting')),
                      value: 'existing',
                    ),
                    RadioListTile<String>(
                      contentPadding: EdgeInsets.zero,
                      enabled: !_busy,
                      title: Text(t('clubPolicyDocuments.uploadNew')),
                      value: 'upload',
                    ),
                  ],
                ),
              ),
              OutlinedButton.icon(
                onPressed: _busy
                    ? null
                    : _source == 'existing'
                    ? _pickExisting
                    : _pickUpload,
                icon: Icon(
                  _source == 'existing' ? Icons.folder_open : Icons.upload_file,
                ),
                label: Text(
                  t(
                    _source == 'existing'
                        ? 'clubPolicyDocuments.chooseExisting'
                        : 'clubPolicyDocuments.chooseUpload',
                  ),
                ),
              ),
              if (_fileName != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(_fileName!),
                ),
              if (_source == 'upload')
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(t('clubPolicyDocuments.folderHint')),
                ),
              const SizedBox(height: 20),
              _field(_title, 'documentTitle', required: true),
              const SizedBox(height: 16),
              _field(_version, 'version', required: true),
              const SizedBox(height: 16),
              _field(_start, 'validFrom', required: true, date: true),
              const SizedBox(height: 16),
              _field(_end, 'validUntil', date: true),
              const SizedBox(height: 16),
              _field(_notes, 'notes', lines: 3),
              const SizedBox(height: 8),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                value: _public,
                title: Text(t('clubPolicyDocuments.public')),
                onChanged: _busy ? null : (v) => setState(() => _public = v),
              ),
              const SizedBox(height: 24),
            ],
          ),
        ),
        bottomNavigationBar: SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              16,
              12,
              16,
              12 + MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
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
                if (_busy) const LinearProgressIndicator(),
                Row(
                  children: [
                    Expanded(
                      child: TextButton(
                        onPressed: _busy
                            ? null
                            : () => Navigator.pop(context, false),
                        child: Text(t('common.cancel')),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: FilledButton(
                        onPressed: _busy ? null : _submit,
                        child: Text(t('common.save')),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _PolicyFilePage extends StatefulWidget {
  const _PolicyFilePage({required this.files, required this.loadFiles});
  final Future<List<Map<String, dynamic>>> Function() loadFiles;
  final List<Map<String, dynamic>> files;
  @override
  State<_PolicyFilePage> createState() => _PolicyFilePageState();
}

class _PolicyFilePageState extends State<_PolicyFilePage> {
  String _search = '';
  bool _loading = true;
  String? _error;
  late List<Map<String, dynamic>> _files;
  @override
  void initState() {
    super.initState();
    _files = [...widget.files];
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final files = await widget.loadFiles();
      if (mounted) {
        setState(() {
          _files = files;
        });
      }
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException && error.statusCode == 404
              ? AirmiusScope.of(
                  context,
                ).t('clubPolicyDocuments.filesUnavailable')
              : formErrorMessage(context, error),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final files = _files
        .where(
          (f) => '${f['display_name'] ?? ''}'.toLowerCase().contains(
            _search.toLowerCase(),
          ),
        )
        .toList();
    return Scaffold(
      appBar: AppBar(title: Text(t('clubPolicyDocuments.chooseExisting'))),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              decoration: InputDecoration(
                labelText: t('clubPolicyDocuments.searchFile'),
                prefixIcon: const Icon(Icons.search),
              ),
              onChanged: (v) => setState(() => _search = v),
            ),
          ),
          Expanded(
            child: ListView(
              children: [
                if (_loading) const LinearProgressIndicator(),
                if (_error != null)
                  ListTile(
                    title: Text(_error!),
                    trailing: TextButton(
                      onPressed: _load,
                      child: Text(t('common.retry')),
                    ),
                  ),
                if (!_loading && _error == null && files.isEmpty)
                  ListTile(title: Text(t('clubPolicyDocuments.noFiles'))),
                ...files.map(
                  (file) => ListTile(
                    leading: const Icon(Icons.description_outlined),
                    title: Text('${file['display_name'] ?? ''}'),
                    onTap: () => Navigator.pop(context, file),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

int? _int(Object? value) => switch (value) {
  int n => n,
  String s => int.tryParse(s),
  _ => null,
};
