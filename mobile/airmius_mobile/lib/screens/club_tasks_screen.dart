import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../models/club_summary.dart';
import 'club_membership_management_screen.dart';
import 'club_request_inbox_screen.dart';
import 'teams_center_screen.dart';

class ClubTasksScreen extends StatefulWidget {
  const ClubTasksScreen({super.key, required this.clubId});

  final int clubId;

  @override
  State<ClubTasksScreen> createState() => _ClubTasksScreenState();
}

class _ClubTasksScreenState extends State<ClubTasksScreen> {
  ClubSummary? _club;
  List<AirmiusJson> _tasks = [];
  bool _loaded = false;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String _t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loaded) {
      _loaded = true;
      _load();
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final club = ClubSummary.fromAirmiusClub(
        await AirmiusServicesScope.of(
          context,
        ).repositories.clubs.club(widget.clubId),
      );
      if (!mounted) return;
      setState(() => _club = club);
      final response = await _client.clubTasks(widget.clubId);
      if (!mounted) return;
      setState(() {
        _tasks = (response['data'] as List).cast<AirmiusJson>();
      });
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : _t('common.errorDetails'),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save(Future<void> Function() action) async {
    if (_saving) return;
    setState(() => _saving = true);
    try {
      await action();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error is AirmiusApiException
                  ? error.userMessage
                  : _t('common.errorDetails'),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _edit([AirmiusJson? task]) async {
    var draft = task?['title'] as String? ?? '';
    final formKey = GlobalKey<FormState>();
    final title = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(_t(task == null ? 'clubTasks.add' : 'clubTasks.edit')),
        content: Form(
          key: formKey,
          child: TextFormField(
            initialValue: draft,
            onChanged: (value) => draft = value,
            autofocus: true,
            maxLength: 255,
            minLines: 1,
            maxLines: 4,
            decoration: InputDecoration(labelText: _t('clubTasks.task')),
            validator: (value) => value == null || value.trim().isEmpty
                ? _t('clubTasks.required')
                : null,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(_t('common.cancel')),
          ),
          FilledButton(
            onPressed: () {
              if (formKey.currentState!.validate()) {
                Navigator.pop(dialogContext, draft.trim());
              }
            },
            child: Text(_t('common.save')),
          ),
        ],
      ),
    );
    if (title == null || !mounted) return;
    await _save(() async {
      final response = task == null
          ? await _client.createClubTask(widget.clubId, title)
          : await _client.updateClubTask(widget.clubId, task['id'] as int, {
              'title': title,
            });
      if (!mounted) return;
      final updated = response['data'] as AirmiusJson;
      setState(() {
        _tasks = [
          updated,
          ..._tasks.where((item) => item['id'] != updated['id']),
        ];
      });
    });
  }

  Future<void> _toggle(AirmiusJson task, bool completed) => _save(() async {
    final response = await _client.updateClubTask(
      widget.clubId,
      task['id'] as int,
      {'completed': completed},
    );
    if (!mounted) return;
    setState(() {
      _tasks = _tasks
          .map(
            (item) => item['id'] == task['id']
                ? response['data'] as AirmiusJson
                : item,
          )
          .toList();
    });
  });

  Future<void> _delete(AirmiusJson task) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(_t('clubTasks.delete')),
        content: Text(task['title'] as String),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(_t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(_t('common.delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _save(() async {
      await _client.deleteClubTask(widget.clubId, task['id'] as int);
      if (mounted) {
        setState(() => _tasks.removeWhere((item) => item['id'] == task['id']));
      }
    });
  }

  Future<void> _open(Widget screen) async {
    await Navigator.of(
      context,
    ).push(MaterialPageRoute<void>(builder: (_) => screen));
    if (mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final club = _club;
    final pendingMembers = club?.pendingMembershipRequests ?? 0;
    final pendingTeams = club?.pendingTeamJoinRequests ?? 0;
    final invoices = club?.management?.openInvoicesCount ?? 0;
    return Scaffold(
      appBar: AppBar(title: Text(_t('clubTasks.title'))),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            if (_loading) const LinearProgressIndicator(),
            if (club != null) ...[
              Text(club.name, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 20),
              Text(
                _t('clubTasks.clubActions'),
                style: Theme.of(context).textTheme.titleMedium,
              ),
              if (pendingMembers > 0)
                ListTile(
                  leading: const Icon(Icons.person_add_outlined),
                  title: Text(_t('clubHub.pendingApplications')),
                  trailing: Text('$pendingMembers'),
                  onTap: () => _open(
                    ClubRequestInboxScreen(initialClubId: widget.clubId),
                  ),
                ),
              if (pendingTeams > 0)
                ListTile(
                  leading: const Icon(Icons.groups_outlined),
                  title: Text(_t('clubHub.pendingTeamRequests')),
                  trailing: Text('$pendingTeams'),
                  onTap: () =>
                      _open(TeamsCenterScreen(initialClubId: widget.clubId)),
                ),
              if (invoices > 0)
                ListTile(
                  leading: const Icon(Icons.receipt_long_outlined),
                  title: Text(_t('clubHub.openPayments')),
                  trailing: Text('$invoices'),
                  onTap: () => _open(
                    ClubMembershipManagementScreen(
                      initialClubId: widget.clubId,
                      initialSection: 'payments',
                    ),
                  ),
                ),
              if (pendingMembers + pendingTeams + invoices == 0)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: Text(_t('clubHub.noOpenTasksBody')),
                ),
            ],
            const Divider(height: 32),
            Text(
              _t('clubTasks.ownTasks'),
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: FilledButton.icon(
                onPressed: _saving || _loading || _error != null
                    ? null
                    : () => _edit(),
                icon: const Icon(Icons.add),
                label: Text(_t('clubTasks.add')),
              ),
            ),
            if (_error != null) ...[
              Text(_error!),
              TextButton(
                onPressed: _loading ? null : _load,
                child: Text(_t('common.retry')),
              ),
            ],
            if (_saving) const LinearProgressIndicator(),
            if (!_loading && _error == null && _tasks.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 16),
                child: Text(_t('clubTasks.empty')),
              ),
            for (final completed in [false, true]) ...[
              if (_tasks.any(
                (task) => (task['completed_at'] != null) == completed,
              ))
                Padding(
                  padding: const EdgeInsets.only(top: 16, bottom: 8),
                  child: Text(
                    _t(completed ? 'clubTasks.completed' : 'clubTasks.open'),
                  ),
                ),
              for (final task in _tasks.where(
                (task) => (task['completed_at'] != null) == completed,
              ))
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Checkbox(
                    value: completed,
                    semanticLabel: task['title'] as String,
                    onChanged: _saving || _loading
                        ? null
                        : (value) => _toggle(task, value!),
                  ),
                  title: Text(
                    task['title'] as String,
                    style: TextStyle(
                      decoration: completed ? TextDecoration.lineThrough : null,
                    ),
                  ),
                  trailing: PopupMenuButton<String>(
                    enabled: !_saving && !_loading,
                    onSelected: (value) =>
                        value == 'edit' ? _edit(task) : _delete(task),
                    itemBuilder: (_) => [
                      PopupMenuItem(
                        value: 'edit',
                        child: Text(_t('clubTasks.edit')),
                      ),
                      PopupMenuItem(
                        value: 'delete',
                        child: Text(_t('common.delete')),
                      ),
                    ],
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}
