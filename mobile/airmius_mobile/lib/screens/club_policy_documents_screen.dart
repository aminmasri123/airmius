import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import 'file_manager_screen.dart';

class ClubPolicyDocumentsScreen extends StatefulWidget {
  const ClubPolicyDocumentsScreen({super.key, this.club});

  final ClubSummary? club;

  @override
  State<ClubPolicyDocumentsScreen> createState() =>
      _ClubPolicyDocumentsScreenState();
}

class _ClubPolicyDocumentsScreenState extends State<ClubPolicyDocumentsScreen> {
  Map<String, dynamic> _data = const {};
  List<Map<String, dynamic>> _files = const [];
  bool _loading = true;
  bool _busy = false;
  String? _error;
  ClubSummary? _club;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  bool get _canManage => _data['can_manage'] == true;
  bool get _canEdit => _data['can_edit'] == true ||
      (!_data.containsKey('can_edit') && _canManage);
  bool get _canDelete => _data['can_delete'] == true ||
      (!_data.containsKey('can_delete') && _canManage);
  bool get _canDownload => _data['can_download'] == true ||
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

  String _safeError(Object error) => error is AirmiusApiException
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
      var files = const <Map<String, dynamic>>[];
      final canEdit = parsed['can_edit'] == true ||
          (!parsed.containsKey('can_edit') && parsed['can_manage'] == true);
      if (canEdit) {
        final workspace = await _client.fileWorkspace(
          scope: 'club',
          clubId: club.id,
          perPage: 100,
        );
        final workspaceData = workspace['data'];
        final rawFiles = workspaceData is Map<String, dynamic>
            ? workspaceData['files']
            : null;
        files = rawFiles is List
            ? rawFiles.whereType<Map<String, dynamic>>().toList()
            : const [];
      }
      if (mounted) {
        setState(() {
          _data = parsed;
          _files = files;
        });
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

  Future<void> _edit([Map<String, dynamic>? document]) async {
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _PolicyDocumentDialog(document: document, files: _files),
    );
    if (payload == null || !mounted) return;
    await _write(
      () => _client.saveClubPolicyDocument(
        _club!.id,
        payload,
        documentId: _int(document?['id']),
      ),
    );
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
              Text(t('clubPolicyDocuments.empty')),
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

class _PolicyDocumentDialog extends StatefulWidget {
  const _PolicyDocumentDialog({required this.document, required this.files});

  final Map<String, dynamic>? document;
  final List<Map<String, dynamic>> files;

  @override
  State<_PolicyDocumentDialog> createState() => _PolicyDocumentDialogState();
}

class _PolicyDocumentDialogState extends State<_PolicyDocumentDialog> {
  late String _type;
  late final TextEditingController _title;
  late final TextEditingController _version;
  late final TextEditingController _start;
  late final TextEditingController _end;
  late final TextEditingController _notes;
  int? _fileId;
  bool _public = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final document = widget.document ?? const {};
    _type = '${document['type'] ?? 'statutes'}';
    _title = TextEditingController(text: '${document['title'] ?? ''}');
    _version = TextEditingController(
      text: '${document['version_label'] ?? ''}',
    );
    _start = TextEditingController(text: '${document['valid_from'] ?? ''}');
    _end = TextEditingController(text: '${document['valid_until'] ?? ''}');
    _notes = TextEditingController(text: '${document['notes'] ?? ''}');
    _public = document['is_public'] == true;
    final file = document['file'];
    _fileId = file is Map<String, dynamic> ? _int(file['id']) : null;
  }

  @override
  void dispose() {
    _title.dispose();
    _version.dispose();
    _start.dispose();
    _end.dispose();
    _notes.dispose();
    super.dispose();
  }

  void _submit() {
    final t = AirmiusScope.of(context).t;
    final start = DateTime.tryParse(_start.text.trim());
    final endText = _end.text.trim();
    final end = endText.isEmpty ? null : DateTime.tryParse(endText);
    if (_title.text.trim().isEmpty ||
        _version.text.trim().isEmpty ||
        start == null ||
        _fileId == null) {
      setState(() => _error = t('clubPolicyDocuments.required'));
      return;
    }
    if ((endText.isNotEmpty && end == null) ||
        (end != null && end.isBefore(start))) {
      setState(() => _error = t('clubPolicyDocuments.invalidRange'));
      return;
    }
    Navigator.pop(context, {
      'type': _type,
      'title': _title.text.trim(),
      'version_label': _version.text.trim(),
      'valid_from': _start.text.trim(),
      'valid_until': endText.isEmpty ? null : endText,
      'is_public': _public,
      'notes': _notes.text.trim().isEmpty ? null : _notes.text.trim(),
      'file_id': _fileId,
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(
        t(
          widget.document == null
              ? 'clubPolicyDocuments.add'
              : 'clubPolicyDocuments.edit',
        ),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _type,
              decoration: InputDecoration(
                labelText: t('clubPolicyDocuments.type'),
              ),
              items: const ['statutes', 'regulation', 'contribution_model']
                  .map(
                    (type) => DropdownMenuItem(
                      value: type,
                      child: Text(t('clubPolicyDocuments.type.$type')),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _type = value ?? _type),
            ),
            DropdownButtonFormField<int>(
              initialValue: _fileId,
              decoration: InputDecoration(
                labelText: t('clubPolicyDocuments.file'),
              ),
              items: widget.files
                  .map(
                    (file) => DropdownMenuItem(
                      value: _int(file['id']),
                      child: Text('${file['display_name'] ?? ''}'),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _fileId = value),
            ),
            TextField(
              controller: _title,
              decoration: InputDecoration(
                labelText: t('clubPolicyDocuments.documentTitle'),
              ),
            ),
            TextField(
              controller: _version,
              decoration: InputDecoration(
                labelText: t('clubPolicyDocuments.version'),
              ),
            ),
            TextField(
              controller: _start,
              decoration: InputDecoration(
                labelText: t('clubPolicyDocuments.validFrom'),
              ),
            ),
            TextField(
              controller: _end,
              decoration: InputDecoration(
                labelText: t('clubPolicyDocuments.validUntil'),
              ),
            ),
            TextField(
              controller: _notes,
              maxLines: 3,
              decoration: InputDecoration(
                labelText: t('clubPolicyDocuments.notes'),
              ),
            ),
            SwitchListTile.adaptive(
              contentPadding: EdgeInsets.zero,
              value: _public,
              title: Text(t('clubPolicyDocuments.public')),
              onChanged: (value) => setState(() => _public = value),
            ),
            if (_error != null)
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
          ],
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
}

int? _int(Object? value) => switch (value) {
  int number => number,
  String text => int.tryParse(text),
  _ => null,
};
