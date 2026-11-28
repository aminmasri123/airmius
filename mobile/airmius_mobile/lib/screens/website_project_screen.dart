import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/website_project_copy.dart';
import '../core/website_project_upload.dart';
import '../widgets/airmius_widgets.dart';

class WebsiteProjectScreen extends StatefulWidget {
  const WebsiteProjectScreen({
    super.key,
    required this.projectId,
    this.admin = false,
  });
  final int projectId;
  final bool admin;
  @override
  State<WebsiteProjectScreen> createState() => _WebsiteProjectScreenState();
}

class _WebsiteProjectScreenState extends State<WebsiteProjectScreen> {
  final _message = TextEditingController();
  Map<String, dynamic>? _project;
  final _entries = <Map<String, dynamic>>[];
  int _page = 1;
  bool _more = false, _busy = false;
  String? _error, _requestKey, _retryBody;
  final _files = <PlatformFile>[];
  AirmiusApiClient get _client => AirmiusServicesScope.of(
    context,
  ).clientForSession(AirmiusServicesScope.of(context).authState.session);
  String w(String key) => websiteText(context, key);
  String t(String key) => AirmiusScope.of(context).t(key);
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_project == null && !_busy && _error == null) _load();
  }

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  Future<void> _load({bool more = false}) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final page = more ? _page + 1 : 1;
      final response = await _client.websiteProject(
        widget.projectId,
        page: page,
      );
      if (!mounted) return;
      final messages = Map<String, dynamic>.from(response['messages'] as Map);
      setState(() {
        _project = Map<String, dynamic>.from(response['data'] as Map);
        if (!more) _entries.clear();
        _entries.addAll(
          (messages['data'] as List).map(
            (e) => Map<String, dynamic>.from(e as Map),
          ),
        );
        _page = page;
        _more = page < (messages['last_page'] as num);
      });
    } catch (e) {
      if (mounted) {
        setState(
          () => _error = e is AirmiusApiException
              ? e.userMessage
              : t('common.errorDetails'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _send() async {
    final body = _message.text.trim();
    if (_busy || (body.isEmpty && _files.isEmpty)) return;
    if (_retryBody != body) {
      _retryBody = body;
      _requestKey =
          '${widget.projectId}-${DateTime.now().microsecondsSinceEpoch}';
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      if (_files.isEmpty) {
        await _client.sendWebsiteProjectMessage(widget.projectId, {
          'body': body,
          'request_key': _requestKey,
        });
      } else {
        await sendWebsiteProjectFiles(
          _client,
          widget.projectId,
          body,
          _requestKey!,
          _files,
        );
      }
      if (!mounted) return;
      _message.clear();
      _files.clear();
      _requestKey = null;
      _retryBody = null;
      await _load();
    } catch (e) {
      if (mounted) {
        setState(
          () => _error = e is AirmiusApiException
              ? e.userMessage
              : t('common.errorDetails'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _status(String value) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await _client.adminUpdateWebsiteRequest(widget.projectId, {
        'status': value,
      });
      if (mounted) await _load();
    } catch (e) {
      if (mounted) {
        setState(
          () => _error = e is AirmiusApiException
              ? e.userMessage
              : t('common.errorDetails'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pickFiles() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        allowMultiple: true,
        type: FileType.custom,
        allowedExtensions: [
          'pdf',
          'jpg',
          'jpeg',
          'png',
          'webp',
          'docx',
          'pptx',
          'xlsx',
          'zip',
        ],
      );
      if (!mounted || result == null) return;
      if (result.files.length > 5 ||
          result.files.any((file) => file.size > 10 * 1024 * 1024)) {
        setState(() => _error = w('fileLimits'));
        return;
      }
      setState(() {
        _files
          ..clear()
          ..addAll(result.files);
        _requestKey = null;
        _retryBody = null;
        _error = null;
      });
    } catch (_) {
      if (mounted) setState(() => _error = w('saveError'));
    }
  }

  Future<void> _change(Future<dynamic> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
      if (mounted) await _load();
    } catch (e) {
      if (mounted) {
        setState(
          () => _error = e is AirmiusApiException
              ? e.userMessage
              : w('saveError'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _withdraw() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: Text(w('withdraw')),
        content: Text(w('withdrawConfirm')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialog, false),
            child: Text(w('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialog, true),
            child: Text(w('withdraw')),
          ),
        ],
      ),
    );
    if (confirmed == true && mounted) {
      await _change(() => _client.withdrawWebsiteProject(widget.projectId));
    }
  }

  Future<void> _edit() async {
    final value = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      builder: (_) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: _WebsiteProjectEditor(project: _project!),
      ),
    );
    if (value != null && mounted) {
      await _change(
        () => _client.updateWebsiteProject(widget.projectId, value),
      );
    }
  }

  String _statusLabel(dynamic status) =>
      status == 'draft' ? w('draft') : t('commerceAdv.websiteStatus.$status');

  String _date(dynamic value) {
    final date = DateTime.tryParse('$value');
    return date == null
        ? ''
        : DateFormat.yMd(
            AirmiusScope.of(context).language.locale.languageCode,
          ).add_Hm().format(date.toLocal());
  }

  @override
  Widget build(BuildContext context) {
    final project = _project;
    return Scaffold(
      appBar: AppBar(
        title: Text('${w('project')} #${widget.projectId}'),
        actions: [
          IconButton(
            tooltip: t('common.refresh'),
            onPressed: _busy ? null : () => _load(),
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            if (_busy) const LinearProgressIndicator(),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 16),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            if (project != null) ...[
              const SizedBox(height: 16),
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      '${project['project_title'] ?? project['domain'] ?? w('project')}',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      project['withdrawn_at'] != null
                          ? w('withdrawn')
                          : _statusLabel(project['status']),
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 12),
                    Text(w('steps')),
                    const SizedBox(height: 12),
                    Text('${w('created')}: ${_date(project['created_at'])}'),
                    const SizedBox(height: 16),
                    Text(w('noCharge')),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  if (project['can_edit'] == true)
                    OutlinedButton.icon(
                      onPressed: _busy ? null : _edit,
                      icon: const Icon(Icons.edit_outlined),
                      label: Text(w('edit')),
                    ),
                  if (project['status'] == 'draft' &&
                      project['can_edit'] == true)
                    FilledButton(
                      onPressed: _busy
                          ? null
                          : () => _change(
                              () => _client.updateWebsiteProject(
                                widget.projectId,
                                {'status': 'new'},
                              ),
                            ),
                      child: Text(w('submit')),
                    ),
                  if (project['can_withdraw'] == true)
                    OutlinedButton.icon(
                      onPressed: _busy ? null : _withdraw,
                      icon: const Icon(Icons.undo),
                      label: Text(w('withdraw')),
                    ),
                ],
              ),
              if (project['can_edit'] != true)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  child: Text(w('editLocked')),
                ),
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final entry in {
                      'request_type': websiteText(context, 'requestType'),
                      'domain': t('commerceAdv.domain'),
                      'goals': w('brief'),
                      'target_audience': w('audience'),
                      'budget_range': websiteText(context, 'budget'),
                      'desired_deadline': websiteText(context, 'deadline'),
                      'brand_guidelines': websiteText(context, 'brandGuidelines'),
                      'contact_preference': websiteText(context, 'contactPreference'),
                      'notes': t('commerceAdv.notes'),
                    }.entries)
                      if ('${project[entry.key] ?? ''}'.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                entry.value,
                                style: Theme.of(context).textTheme.titleSmall,
                              ),
                              const SizedBox(height: 6),
                              SelectableText(
                                entry.key == 'request_type'
                                    ? websiteText(context, 'type.${project[entry.key]}')
                                    : entry.key == 'contact_preference'
                                        ? websiteText(context, 'contact.${project[entry.key]}')
                                        : '${project[entry.key]}',
                              ),
                            ],
                          ),
                        ),
                    if ((project['quote_details'] as Map? ?? {}).isNotEmpty)
                      for (final entry in (project['quote_details'] as Map).entries)
                        if ('${entry.value}'.isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('${entry.key}', style: Theme.of(context).textTheme.titleSmall),
                                const SizedBox(height: 6),
                                SelectableText('${entry.value}'),
                              ],
                            ),
                          ),
                    for (final entry in {
                      'deliverables': websiteText(context, 'deliverables'),
                      'channels': websiteText(context, 'channels'),
                    }.entries)
                      if ((project[entry.key] as List? ?? []).isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(entry.value, style: Theme.of(context).textTheme.titleSmall),
                              const SizedBox(height: 6),
                              SelectableText(
                                (project[entry.key] as List)
                                    .map((value) => websiteText(context, '${entry.key == 'deliverables' ? 'deliverable' : 'channel'}.$value'))
                                    .join(' · '),
                              ),
                            ],
                          ),
                        ),
                    if ((project['inspiration_urls'] as List? ?? []).isNotEmpty)
                      Text(
                        w('inspiration'),
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                    for (final link
                        in project['inspiration_urls'] as List? ?? [])
                      TextButton.icon(
                        icon: const Icon(Icons.open_in_new),
                        label: Text('$link'),
                        onPressed: () async {
                          final uri = Uri.tryParse('$link');
                          if (uri != null &&
                              ['http', 'https'].contains(uri.scheme)) {
                            await launchUrl(
                              uri,
                              mode: LaunchMode.externalApplication,
                            );
                          }
                        },
                      ),
                  ],
                ),
              ),
              if ((widget.admin || project['is_admin'] == true) &&
                  project['status'] != 'draft' &&
                  project['withdrawn_at'] == null) ...[
                const SizedBox(height: 24),
                DropdownButtonFormField<String>(
                  key: ValueKey(project['status']),
                  initialValue: '${project['status']}',
                  isExpanded: true,
                  decoration: InputDecoration(labelText: w('status')),
                  items: [
                    for (final status in [
                      'new',
                      'contacted',
                      'quoted',
                      'in_progress',
                      'done',
                      'cancelled',
                    ])
                      DropdownMenuItem(
                        value: status,
                        child: Text(t('commerceAdv.websiteStatus.$status')),
                      ),
                  ],
                  onChanged: _busy
                      ? null
                      : (value) {
                          if (value != null && value != project['status']) {
                            _status(value);
                          }
                        },
                ),
              ],
              const SizedBox(height: 24),
              Text(
                w('conversation'),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 8),
              Text(w('shared')),
              const SizedBox(height: 20),
              if (project['can_comment'] == true) ...[
                TextField(
                  controller: _message,
                  enabled: !_busy,
                  minLines: 2,
                  maxLines: 5,
                  maxLength: 4000,
                  decoration: InputDecoration(labelText: w('message')),
                ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: _busy ? null : _pickFiles,
                  icon: const Icon(Icons.attach_file),
                  label: Text(w('attach')),
                ),
                Text(
                  '${w('fileLimits')} PDF, JPG, PNG, WEBP, DOCX, PPTX, XLSX, ZIP.',
                ),
                for (final file in _files)
                  ListTile(
                    title: Text(file.name),
                    trailing: IconButton(
                      icon: const Icon(Icons.close),
                      onPressed: _busy
                          ? null
                          : () => setState(() {
                              _files.remove(file);
                              _requestKey = null;
                              _retryBody = null;
                            }),
                    ),
                  ),
                FilledButton.icon(
                  onPressed: _busy ? null : _send,
                  icon: const Icon(Icons.send_outlined),
                  label: Text(w('send')),
                ),
              ],
              const SizedBox(height: 24),
              if (_entries.isEmpty) Text(w('empty')),
              for (final entry in _entries)
                Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          '${entry['author'] ?? w('unknown')} · ${_date(entry['created_at'])}',
                          style: Theme.of(context).textTheme.labelLarge,
                        ),
                        const SizedBox(height: 10),
                        SelectableText(
                          entry['kind'] == 'status'
                              ? '${w('statusChanged')}: ${_statusLabel(entry['status'])}'
                              : entry['kind'] == 'updated'
                              ? w('edited')
                              : '${entry['body'] ?? ''}',
                        ),
                        for (final file in entry['attachments'] as List? ?? [])
                          TextButton.icon(
                            icon: const Icon(Icons.download_outlined),
                            label: Text('${file['name']}'),
                            onPressed: () async {
                              final uri = Uri.parse(
                                _client.baseUrl,
                              ).resolve('${file['download_url']}');
                              if (['https', 'http'].contains(uri.scheme)) {
                                await launchUrl(
                                  uri,
                                  mode: LaunchMode.externalApplication,
                                );
                              }
                            },
                          ),
                      ],
                    ),
                  ),
                ),
              if (_more)
                OutlinedButton(
                  onPressed: _busy ? null : () => _load(more: true),
                  child: Text(w('more')),
                ),
            ],
          ],
        ),
      ),
    );
  }
}

class _WebsiteProjectEditor extends StatefulWidget {
  const _WebsiteProjectEditor({required this.project});
  final Map<String, dynamic> project;
  @override
  State<_WebsiteProjectEditor> createState() => _WebsiteProjectEditorState();
}

class _WebsiteProjectEditorState extends State<_WebsiteProjectEditor> {
  final _form = GlobalKey<FormState>();
  late final _fields = {
    for (final key in [
      'project_title',
      'domain',
      'goals',
      'target_audience',
      'notes',
    ])
      key: TextEditingController(text: '${widget.project[key] ?? ''}'),
  };
  late final _links = TextEditingController(
    text: (widget.project['inspiration_urls'] as List? ?? []).join('\n'),
  );
  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    _links.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    String w(String key) => websiteText(context, key);
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(w('edit'), style: Theme.of(context).textTheme.titleLarge),
            for (final entry in {
              'project_title': w('title'),
              'domain': AirmiusScope.of(context).t('commerceAdv.domain'),
              'goals': w('brief'),
              'target_audience': w('audience'),
              'notes': AirmiusScope.of(context).t('commerceAdv.notes'),
            }.entries)
              Padding(
                padding: const EdgeInsets.only(top: 16),
                child: TextFormField(
                  controller: _fields[entry.key],
                  maxLength: ['domain', 'project_title'].contains(entry.key)
                      ? 255
                      : 2000,
                  minLines: 1,
                  maxLines: 4,
                  decoration: InputDecoration(labelText: entry.value),
                ),
              ),
            TextFormField(
              controller: _links,
              minLines: 2,
              maxLines: 5,
              decoration: InputDecoration(
                labelText: w('inspiration'),
                helperText: w('linksHelp'),
                helperMaxLines: 4,
              ),
              validator: (value) {
                final links = (value ?? '')
                    .split('\n')
                    .map((e) => e.trim())
                    .where((e) => e.isNotEmpty)
                    .toList();
                return links.length > 5 ||
                        links.any((link) {
                          final uri = Uri.tryParse(link);
                          return uri == null ||
                              !['http', 'https'].contains(uri.scheme) ||
                              uri.host.isEmpty;
                        })
                    ? w('invalidLinks')
                    : null;
              },
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: () {
                if (_form.currentState!.validate()) {
                  Navigator.pop(context, {
                    for (final field in _fields.entries)
                      field.key: field.value.text.trim(),
                    'inspiration_urls': _links.text
                        .split('\n')
                        .map((e) => e.trim())
                        .where((e) => e.isNotEmpty)
                        .toList(),
                  });
                }
              },
              child: Text(w('save')),
            ),
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(w('cancel')),
            ),
          ],
        ),
      ),
    );
  }
}
