import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/website_project_copy.dart';
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
    if (_busy || body.isEmpty) return;
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
      await _client.sendWebsiteProjectMessage(widget.projectId, {
        'body': body,
        'request_key': _requestKey,
      });
      if (!mounted) return;
      _message.clear();
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
                      t('commerceAdv.websiteStatus.${project['status']}'),
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
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final entry in {
                      'domain': t('commerceAdv.domain'),
                      'goals': w('brief'),
                      'target_audience': w('audience'),
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
                              SelectableText('${project[entry.key]}'),
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
              if (widget.admin) ...[
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
              TextField(
                controller: _message,
                enabled: !_busy,
                minLines: 2,
                maxLines: 5,
                maxLength: 4000,
                decoration: InputDecoration(labelText: w('message')),
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: _busy ? null : _send,
                icon: const Icon(Icons.send_outlined),
                label: Text(w('send')),
              ),
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
                              ? '${w('statusChanged')}: ${t('commerceAdv.websiteStatus.${entry['status']}')}'
                              : '${entry['body'] ?? ''}',
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
