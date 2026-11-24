import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/work_automation_labels.dart';
import '../core/work_automation_request_store.dart';

class WorkAutomationScreen extends StatefulWidget {
  const WorkAutomationScreen({
    super.key,
    this.initialClubId,
    this.requestStore,
  });
  final int? initialClubId;
  final WorkAutomationRequestStore? requestStore;

  @override
  State<WorkAutomationScreen> createState() => _WorkAutomationScreenState();
}

class _WorkAutomationScreenState extends State<WorkAutomationScreen> {
  late final _store = widget.requestStore ?? WorkAutomationRequestStore();
  List<AirmiusJson> _clubs = [], _jobs = [], _permissions = [], _users = [];
  AirmiusJson? _pending;
  int? _clubId;
  int _page = 1, _lastPage = 1, _revision = 0;
  String _status = '';
  String? _error;
  bool _initialized = false, _loading = true, _busy = false, _canCreate = false;
  Timer? _poll;
  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String get _account =>
      '${_client.baseUrl}:${AirmiusServicesScope.of(context).authState.user?.id ?? 'signed-out'}';
  String _t(String key) =>
      workAutomationLabel(AirmiusScope.of(context).language, key);
  String _date(dynamic value) => value == null
      ? ''
      : DateFormat.yMd(
          AirmiusScope.of(context).language.locale.languageCode,
        ).add_Hm().format(DateTime.parse('$value').toLocal());
  String _errorText(Object error) =>
      error is AirmiusApiException && error.statusCode == 403
      ? _t('noAccess')
      : _t('error');

  String _recipientSummary(AirmiusJson job) {
    final roles = ((job['recipient_roles'] as List?) ?? []).cast<String>();
    final userIds = ((job['recipient_user_ids'] as List?) ?? [])
        .map((id) => int.tryParse('$id'))
        .whereType<int>()
        .toSet();
    final parts = <String>[
      if (job['include_creator'] == true) _t('creator'),
      ..._users
          .where((user) => userIds.contains(user['id']))
          .map((user) => '${user['name']}'),
      ...roles,
    ];
    return parts.isEmpty ? _t('defaultRecipients') : parts.join(', ');
  }

  String _timeLabel(AirmiusJson job) {
    final status = '${job['status']}';
    final key = switch (status) {
      'scheduled' => 'scheduledFor',
      'completed' => 'sentAt',
      'failed' => 'failedAt',
      'running' => 'started',
      _ => 'waitingSince',
    };
    final value = switch (status) {
      'scheduled' => job['scheduled_at'],
      'completed' => job['completed_at'],
      'failed' => job['failed_at'] ?? job['queued_at'],
      'running' => job['started_at'] ?? job['queued_at'],
      _ => job['queued_at'],
    };
    return '${_t(key)}: ${_date(value)}';
  }

  String _statusExplanation(AirmiusJson job) => switch ('${job['status']}') {
    'scheduled' => _t('scheduledHelp'),
    'queued' => _t('queuedHelp'),
    'running' => _t('runningHelp'),
    'completed' => _t('completedHelp'),
    'failed' => _t('failedHelp'),
    _ => '',
  };

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialized) return;
    _initialized = true;
    _initialize();
    _poll = Timer.periodic(const Duration(seconds: 10), (_) {
      if (!_busy && !_loading && ModalRoute.of(context)?.isCurrent == true) {
        _load(silent: true);
      }
    });
  }

  @override
  void dispose() {
    _revision++;
    _poll?.cancel();
    super.dispose();
  }

  Future<void> _initialize() async {
    try {
      final response = await _client.workAutomationClubs();
      if (!mounted) return;
      _clubs = ((response['data'] as List?) ?? []).cast<AirmiusJson>();
      _clubId = _clubs.any((club) => club['id'] == widget.initialClubId)
          ? widget.initialClubId
          : (_clubs.isEmpty ? null : _clubs.first['id'] as int);
      if (_clubId == null) {
        setState(() {
          _loading = false;
          _error = _t('noAccess');
        });
        return;
      }
      _pending = await _store.read(_account, _clubId!);
      if (mounted) await _load();
    } catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = _errorText(error);
        });
      }
    }
  }

  Future<void> _load({bool silent = false}) async {
    if (_clubId == null) return;
    final revision = ++_revision;
    if (!silent) setState(() => _loading = true);
    try {
      final response = await _client.workAutomationJobs(
        _clubId!,
        status: _status,
        page: _page,
      );
      if (!mounted || revision != _revision) return;
      final meta = response['meta'] as AirmiusJson;
      setState(() {
        _jobs = (response['data'] as List).cast<AirmiusJson>();
        _canCreate = meta['can_create'] == true;
        _permissions = (meta['recipient_permissions'] as List)
            .cast<AirmiusJson>();
        _users = (meta['recipient_users'] as List).cast<AirmiusJson>();
        _lastPage = meta['last_page'] as int;
        _error = null;
      });
    } catch (error) {
      if (mounted && revision == _revision) {
        setState(() {
          _error = _errorText(error);
          if (error is AirmiusApiException && error.statusCode == 403) {
            _jobs = [];
            _canCreate = false;
          }
        });
      }
    } finally {
      if (mounted && revision == _revision) setState(() => _loading = false);
    }
  }

  Future<void> _selectClub(int clubId) async {
    if (_busy) return;
    final revision = ++_revision;
    setState(() {
      _clubId = clubId;
      _page = 1;
      _status = '';
      _jobs = [];
      _pending = null;
      _canCreate = false;
      _loading = true;
    });
    final pending = await _store.read(_account, clubId);
    if (!mounted || revision != _revision) {
      return;
    }
    _pending = pending;
    await _load();
  }

  Future<void> _create() async {
    final clubId = _clubId!;
    final payload = await showDialog<AirmiusJson>(
      context: context,
      builder: (_) => WorkAutomationCreateDialog(
        permissions: _permissions,
        users: _users,
        preview: (roles, userIds, includeCreator) async =>
            (await _client.workAutomationRecipients(
                  clubId,
                  roles,
                  userIds,
                  includeCreator,
                ))['data']['count']
                as int,
      ),
    );
    if (!mounted || payload == null || _clubId != clubId) return;
    _pending = {
      ...payload,
      'idempotency_key': WorkAutomationRequestStore.newKey(),
    };
    await _submit();
  }

  Future<void> _submit() async {
    if (_busy || _pending == null) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final clubId = _clubId!;
    final account = _account;
    try {
      await _store.save(account, clubId, _pending!);
      final response = await _client.createWorkAutomationJob(clubId, _pending!);
      await _store.clear(account, clubId);
      if (!mounted) return;
      _pending = null;
      await _load();
      if (mounted) setState(() => _busy = false);
      if (mounted) await _details(response['data'] as AirmiusJson);
    } catch (error) {
      final uncertain =
          error is! AirmiusApiException || error.statusCode >= 500;
      if (!uncertain) {
        await _store.clear(account, clubId);
        _pending = null;
      }
      if (mounted) {
        setState(
          () => _error = uncertain ? _t('uncertain') : error.userMessage,
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _retry(AirmiusJson job) async {
    if (_busy) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(_t('confirmRetry')),
        content: Text('${job['title'] ?? _t(job['kind'])} #${job['id']}'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(_t('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(_t('retry')),
          ),
        ],
      ),
    );
    if (!mounted || confirmed != true) return;
    setState(() => _busy = true);
    try {
      await _client.retryWorkAutomationJob(
        _clubId!,
        job['id'] as int,
        job['attempts'] as int,
      );
      await _load();
    } catch (error) {
      if (mounted) setState(() => _error = _errorText(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _edit(AirmiusJson job) async {
    final clubId = _clubId!;
    final details =
        (await _client.workAutomationJob(clubId, job['id'] as int))['data']
            as AirmiusJson;
    if (!mounted || _clubId != clubId) return;
    final payload = await showDialog<AirmiusJson>(
      context: context,
      builder: (_) => WorkAutomationCreateDialog(
        permissions: _permissions,
        users: _users,
        preview: (roles, userIds, includeCreator) async =>
            (await _client.workAutomationRecipients(
                  clubId,
                  roles,
                  userIds,
                  includeCreator,
                ))['data']['count']
                as int,
        initial: details,
      ),
    );
    if (!mounted || payload == null || _clubId != clubId) return;
    setState(() => _busy = true);
    try {
      await _client.updateWorkAutomationJob(clubId, job['id'] as int, payload);
      await _load();
    } catch (_) {
      if (mounted) setState(() => _error = _t('changedMeanwhile'));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _delete(AirmiusJson job) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(_t('confirmDelete')),
        content: Text('${job['title'] ?? _t(job['kind'])} #${job['id']}'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(_t('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(_t('delete')),
          ),
        ],
      ),
    );
    if (!mounted || confirmed != true) return;
    setState(() => _busy = true);
    try {
      await _client.deleteWorkAutomationJob(_clubId!, job['id'] as int);
      await _load();
    } catch (_) {
      if (mounted) setState(() => _error = _t('changedMeanwhile'));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _details(AirmiusJson job) => showDialog<void>(
    context: context,
    builder: (_) => WorkAutomationDetailsDialog(
      load: () async =>
          (await _client.workAutomationJob(_clubId!, job['id'] as int))['data']
              as AirmiusJson,
      retry: _retry,
      edit: _edit,
      delete: _delete,
    ),
  );

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(_t('title')),
      actions: [
        IconButton(
          tooltip: _t('refresh'),
          onPressed: _loading || _busy
              ? null
              : () => _clubId == null ? _initialize() : _load(),
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    floatingActionButton: _canCreate && _pending == null
        ? FloatingActionButton.extended(
            onPressed: _busy ? null : _create,
            tooltip: _t('new'),
            icon: const Icon(Icons.add),
            label: Text(_t('new')),
          )
        : null,
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(_t('supported'), style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 12),
          if (_clubs.isNotEmpty)
            DropdownButtonFormField<int>(
              initialValue: _clubId,
              isExpanded: true,
              decoration: InputDecoration(labelText: _t('club')),
              items: _clubs
                  .map(
                    (club) => DropdownMenuItem(
                      value: club['id'] as int,
                      child: Text(
                        '${club['name']}',
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  )
                  .toList(),
              onChanged: _busy
                  ? null
                  : (value) {
                      if (value != null) _selectClub(value);
                    },
            ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            key: ValueKey('status:$_status'),
            initialValue: _status,
            isExpanded: true,
            items: ['', 'scheduled', 'queued', 'running', 'completed', 'failed']
                .map(
                  (status) => DropdownMenuItem(
                    value: status,
                    child: Text(_t(status.isEmpty ? 'all' : status)),
                  ),
                )
                .toList(),
            onChanged: _busy
                ? null
                : (value) {
                    setState(() {
                      _status = value ?? '';
                      _page = 1;
                    });
                    _load();
                  },
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          if (_pending != null) ...[
            const Divider(),
            Text(_t('uncertain')),
            Text('${_pending!['payload']['title']}'),
            SelectableText('${_pending!['idempotency_key']}'),
            Wrap(
              spacing: 8,
              children: [
                TextButton(
                  onPressed: _busy ? null : _submit,
                  child: Text(_t('resend')),
                ),
                TextButton(
                  onPressed: _busy
                      ? null
                      : () async {
                          await _store.clear(_account, _clubId!);
                          if (mounted) setState(() => _pending = null);
                        },
                  child: Text(_t('discard')),
                ),
              ],
            ),
          ],
          if (_loading)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_jobs.isEmpty && _error == null)
            Padding(
              padding: const EdgeInsets.all(24),
              child: Text(_t('empty')),
            ),
          for (final job in _jobs)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Card(
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: () => _details(job),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: Text(
                                '${job['title'] ?? _t(job['kind'])}',
                                style: Theme.of(context).textTheme.titleMedium,
                              ),
                            ),
                            Chip(label: Text(_t(job['status']))),
                          ],
                        ),
                        Text('${_t(job['kind'])} · ${_recipientSummary(job)}'),
                        const SizedBox(height: 8),
                        Text(_statusExplanation(job)),
                        Text(_timeLabel(job)),
                        if ((job['attempts'] as num? ?? 0) > 0)
                          Text('${_t('attempts')}: ${job['attempts']}'),
                        if (job['error_code'] != null)
                          Text(
                            _t(_failureLabel(job['error_code'])),
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.error,
                            ),
                          ),
                        if (job['can_retry'] != true &&
                            job['can_edit'] != true &&
                            job['can_delete'] != true &&
                            const ['queued', 'running'].contains(job['status']))
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(
                              _t('processingLocked'),
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ),
                        if (job['can_retry'] == true ||
                            job['can_edit'] == true ||
                            job['can_delete'] == true)
                          Wrap(
                            spacing: 4,
                            children: [
                              if (job['can_retry'] == true)
                                TextButton.icon(
                                  onPressed: _busy ? null : () => _retry(job),
                                  icon: const Icon(Icons.replay),
                                  label: Text(_t('retry')),
                                ),
                              if (job['can_edit'] == true)
                                TextButton.icon(
                                  onPressed: _busy ? null : () => _edit(job),
                                  icon: const Icon(Icons.edit_outlined),
                                  label: Text(_t('edit')),
                                ),
                              if (job['can_delete'] == true)
                                TextButton.icon(
                                  onPressed: _busy ? null : () => _delete(job),
                                  icon: const Icon(Icons.delete_outline),
                                  label: Text(_t('delete')),
                                ),
                            ],
                          ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          if (_lastPage > 1)
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                IconButton(
                  tooltip: _t('previous'),
                  onPressed: _page <= 1 || _loading || _busy
                      ? null
                      : () {
                          _page--;
                          _load();
                        },
                  icon: const Icon(Icons.chevron_left),
                ),
                Text('$_page / $_lastPage'),
                IconButton(
                  tooltip: _t('next'),
                  onPressed: _page >= _lastPage || _loading || _busy
                      ? null
                      : () {
                          _page++;
                          _load();
                        },
                  icon: const Icon(Icons.chevron_right),
                ),
              ],
            ),
        ],
      ),
    ),
  );
}

String _failureLabel(dynamic code) => switch (code) {
  'queue_dispatch_failed' => 'dispatchError',
  'queue_worker_failed' => 'workerError',
  'no_recipients' => 'noRecipients',
  _ => 'deliveryError',
};

class WorkAutomationCreateDialog extends StatefulWidget {
  const WorkAutomationCreateDialog({
    super.key,
    required this.permissions,
    required this.users,
    required this.preview,
    this.initial,
  });
  final List<AirmiusJson> permissions;
  final List<AirmiusJson> users;
  final Future<int> Function(List<String>, List<int>, bool) preview;
  final AirmiusJson? initial;
  @override
  State<WorkAutomationCreateDialog> createState() =>
      _WorkAutomationCreateDialogState();
}

class _WorkAutomationCreateDialogState
    extends State<WorkAutomationCreateDialog> {
  final _title = TextEditingController(), _body = TextEditingController();
  final _roles = <String>[];
  final _userIds = <int>[];
  bool _includeCreator = true;
  String _kind = 'reminder';
  DateTime? _scheduled;
  int? _count;
  int _revision = 0;
  String? _error;
  String _t(String key) =>
      workAutomationLabel(AirmiusScope.of(context).language, key);
  @override
  void initState() {
    super.initState();
    final initial = widget.initial;
    if (initial != null) {
      _kind = initial['kind'] as String;
      _title.text = '${initial['title'] ?? ''}';
      _body.text = '${initial['body'] ?? ''}';
      _roles.addAll(
        ((initial['recipient_roles'] as List?) ?? []).cast<String>(),
      );
      _userIds.addAll(
        ((initial['recipient_user_ids'] as List?) ?? [])
            .map((id) => int.tryParse('$id'))
            .whereType<int>(),
      );
      _includeCreator = initial['include_creator'] == true;
      if (initial['scheduled_at'] != null) {
        _scheduled = DateTime.parse('${initial['scheduled_at']}').toLocal();
      }
    }
    _preview();
  }

  @override
  void dispose() {
    _revision++;
    _title.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _preview() async {
    final revision = ++_revision;
    if (mounted) {
      setState(() {
        _count = null;
        _error = null;
      });
    }
    try {
      final count = await widget.preview(
        List.of(_roles),
        List.of(_userIds),
        _includeCreator,
      );
      if (mounted && revision == _revision) setState(() => _count = count);
    } catch (_) {
      if (mounted && revision == _revision) {
        setState(() => _error = _t('error'));
      }
    }
  }

  Future<void> _pickTime() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: _scheduled ?? now.add(const Duration(days: 1)),
      firstDate: now,
      lastDate: now.add(const Duration(days: 3650)),
    );
    if (!mounted || date == null) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_scheduled ?? now),
    );
    if (!mounted || time == null) return;
    setState(
      () => _scheduled = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      ),
    );
  }

  Future<void> _pickRoles() async {
    await showDialog<void>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: Text(_t('recipients')),
          content: SizedBox(
            width: 440,
            height: 360,
            child: ListView(
              children: [
                for (final permission in widget.permissions)
                  CheckboxListTile(
                    title: Text('${permission['key']}'),
                    value: _roles.contains(permission['key']),
                    onChanged: (value) {
                      update(() {
                        if (value == true && _roles.length < 8) {
                          _roles.add(permission['key'] as String);
                        } else if (value == false) {
                          _roles.remove(permission['key']);
                        }
                      });
                    },
                  ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(_t('close')),
            ),
          ],
        ),
      ),
    );
    if (mounted) {
      setState(() {});
      _preview();
    }
  }

  Future<void> _pickPeople() async {
    await showDialog<void>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: Text(_t('specificPeople')),
          content: SizedBox(
            width: 440,
            height: 360,
            child: ListView(
              children: [
                for (final user in widget.users)
                  CheckboxListTile(
                    title: Text('${user['name']}'),
                    value: _userIds.contains(user['id']),
                    onChanged: (value) {
                      update(() {
                        final id = user['id'] as int;
                        value == true ? _userIds.add(id) : _userIds.remove(id);
                      });
                    },
                  ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(_t('close')),
            ),
          ],
        ),
      ),
    );
    if (mounted) {
      setState(() {});
      _preview();
    }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(_t(widget.initial == null ? 'new' : 'editJob')),
    content: SizedBox(
      width: 440,
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _kind,
              decoration: InputDecoration(labelText: _t('kind')),
              items: ['reminder', 'escalation']
                  .map(
                    (kind) =>
                        DropdownMenuItem(value: kind, child: Text(_t(kind))),
                  )
                  .toList(),
              onChanged: (value) => _kind = value!,
            ),
            const SizedBox(height: 12),
            TextField(
              key: const ValueKey('automation-title'),
              controller: _title,
              maxLength: 160,
              decoration: InputDecoration(labelText: _t('subject')),
            ),
            TextField(
              key: const ValueKey('automation-body'),
              controller: _body,
              maxLength: 2000,
              maxLines: 3,
              decoration: InputDecoration(labelText: _t('body')),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(_t('when')),
              subtitle: Text(
                _scheduled == null
                    ? _t('immediate')
                    : DateFormat.yMd().add_Hm().format(_scheduled!),
              ),
              onTap: _pickTime,
              trailing: _scheduled == null
                  ? const Icon(Icons.schedule)
                  : IconButton(
                      tooltip: _t('immediate'),
                      onPressed: () => setState(() => _scheduled = null),
                      icon: const Icon(Icons.close),
                    ),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(_t('recipients')),
              subtitle: Text(
                _roles.isEmpty ? _t('defaultRecipients') : _roles.join(', '),
              ),
              trailing: const Icon(Icons.people_outline),
              onTap: _pickRoles,
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(_t('includeCreator')),
              value: _includeCreator,
              onChanged: (value) {
                setState(() => _includeCreator = value == true);
                _preview();
              },
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(_t('specificPeople')),
              subtitle: Text(
                _userIds.isEmpty
                    ? _t('noSpecificPeople')
                    : '${_userIds.length} ${_t('people')}',
              ),
              trailing: const Icon(Icons.person_add_alt_1_outlined),
              onTap: _pickPeople,
            ),
            if (_count == null)
              const LinearProgressIndicator()
            else ...[
              Text('${_t('recipientCount')}: $_count'),
              const SizedBox(height: 12),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(
                    '${_t('preview')}: ${_t(_kind)} · $_count ${_t('people')} · '
                    '${_scheduled == null ? _t('immediate') : DateFormat.yMd().add_Hm().format(_scheduled!)}',
                  ),
                ),
              ),
            ],
            if (_error != null)
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
          ],
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: Text(_t('cancel')),
      ),
      FilledButton(
        onPressed: _count == null || _count == 0
            ? null
            : () {
                if (_title.text.trim().isEmpty || _body.text.trim().isEmpty) {
                  setState(() => _error = _t('required'));
                  return;
                }
                Navigator.pop(context, <String, dynamic>{
                  'kind': _kind,
                  'recipient_roles': List.of(_roles),
                  'recipient_user_ids': List.of(_userIds),
                  'include_creator': _includeCreator,
                  'payload': {
                    'title': _title.text.trim(),
                    'body': _body.text.trim(),
                  },
                  'scheduled_at': _scheduled?.toUtc().toIso8601String(),
                });
              },
        child: Text(_t(widget.initial == null ? 'create' : 'save')),
      ),
    ],
  );
}

class WorkAutomationDetailsDialog extends StatefulWidget {
  const WorkAutomationDetailsDialog({
    super.key,
    required this.load,
    required this.retry,
    required this.edit,
    required this.delete,
  });
  final Future<AirmiusJson> Function() load;
  final Future<void> Function(AirmiusJson) retry;
  final Future<void> Function(AirmiusJson) edit;
  final Future<void> Function(AirmiusJson) delete;
  @override
  State<WorkAutomationDetailsDialog> createState() =>
      _WorkAutomationDetailsDialogState();
}

class _WorkAutomationDetailsDialogState
    extends State<WorkAutomationDetailsDialog> {
  late Future<AirmiusJson> _future = widget.load();
  bool _busy = false;
  String _t(String key) =>
      workAutomationLabel(AirmiusScope.of(context).language, key);
  String _date(dynamic value) =>
      DateFormat.yMd().add_Hm().format(DateTime.parse('$value').toLocal());
  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Row(
      children: [
        Expanded(child: Text(_t('details'))),
        IconButton(
          tooltip: _t('refresh'),
          onPressed: _busy
              ? null
              : () => setState(() => _future = widget.load()),
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    content: SizedBox(
      width: 440,
      child: FutureBuilder<AirmiusJson>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const SizedBox(
              height: 80,
              child: Center(child: CircularProgressIndicator()),
            );
          }
          if (snapshot.hasError) return Text(_t('error'));
          final job = snapshot.data!;
          return SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  '${job['title'] ?? _t(job['kind'])} #${job['id']}',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                Text(
                  '${_t(job['status'])} · ${_t('attempts')}: ${job['attempts']}',
                ),
                if (job['body'] != null) Text('${job['body']}'),
                if (job['error_code'] != null)
                  Text(
                    _t(_failureLabel(job['error_code'])),
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                const SizedBox(height: 12),
                Text(_t('key')),
                SelectableText('${job['idempotency_key']}'),
                Text(
                  '${_t('recipients')}: ${(job['recipient_roles'] as List).isEmpty ? _t('defaultRecipients') : (job['recipient_roles'] as List).join(', ')}',
                ),
                for (final entry in {
                  'scheduled_at': 'when',
                  'queued_at': 'created',
                  'started_at': 'started',
                  'completed_at': 'finished',
                  'failed_at': 'failedAt',
                  'retry_queued_at': 'retryAt',
                }.entries)
                  if (job[entry.key] != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(
                        '${_t(entry.value)}: ${_date(job[entry.key])}',
                      ),
                    ),
                if (job['result'] != null) ...[
                  const Divider(),
                  Text(
                    '${_t('stored')}: ${job['result']['stored_notifications']}',
                  ),
                  Text(
                    '${_t('skipped')}: ${job['result']['skipped_notifications']}',
                  ),
                ],
                const Divider(),
                Text(
                  _t('history'),
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                for (final event in (job['history'] as List? ?? []))
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(
                      '${_t((event['event'] as String).split('.').last)} · ${_date(event['at'])}${event['data']['error_code'] == null ? '' : '\n${_t(_failureLabel(event['data']['error_code']))}'}',
                    ),
                  ),
                if (job['can_retry'] == true)
                  TextButton.icon(
                    onPressed: _busy
                        ? null
                        : () async {
                            setState(() => _busy = true);
                            await widget.retry(job);
                            if (mounted) {
                              setState(() {
                                _busy = false;
                                _future = widget.load();
                              });
                            }
                          },
                    icon: const Icon(Icons.replay),
                    label: Text(_t('retry')),
                  ),
                if (job['can_edit'] == true)
                  TextButton.icon(
                    onPressed: _busy
                        ? null
                        : () async {
                            Navigator.pop(context);
                            await widget.edit(job);
                          },
                    icon: const Icon(Icons.edit_outlined),
                    label: Text(_t('edit')),
                  ),
                if (job['can_delete'] == true)
                  TextButton.icon(
                    onPressed: _busy
                        ? null
                        : () async {
                            Navigator.pop(context);
                            await widget.delete(job);
                          },
                    icon: const Icon(Icons.delete_outline),
                    label: Text(_t('delete')),
                  ),
              ],
            ),
          );
        },
      ),
    ),
    actions: [
      TextButton(
        onPressed: _busy ? null : () => Navigator.pop(context),
        child: Text(_t('close')),
      ),
    ],
  );
}
