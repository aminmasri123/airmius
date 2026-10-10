import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import 'club_tasks_screen.dart';

class MyClubTasksScreen extends StatefulWidget {
  const MyClubTasksScreen({super.key});

  @override
  State<MyClubTasksScreen> createState() => _MyClubTasksScreenState();
}

class _MyClubTasksScreenState extends State<MyClubTasksScreen> {
  List<AirmiusJson> _tasks = [];
  bool _loaded = false;
  bool _loading = true;
  String? _error;
  String _area = 'all';
  String _relationship = 'all';

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
      final response = await _client.myClubTasks();
      if (!mounted) return;
      setState(() {
        _tasks = ((response['data'] as List?) ?? const []).cast<AirmiusJson>();
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is AirmiusApiException
            ? error.userMessage
            : _t('common.errorDetails');
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Iterable<AirmiusJson> get _visibleTasks => _tasks.where((task) {
    final visibility = _text(task, 'visibility', 'personal');
    final relationship = _text(task, 'relationship', 'club');
    final areaMatches = switch (_area) {
      'personal' => const {'personal', 'shared'}.contains(visibility),
      'team' => visibility == 'team',
      'club' => visibility == 'club',
      _ => true,
    };
    return areaMatches &&
        (_relationship == 'all' || relationship == _relationship);
  });

  Future<void> _openTask(AirmiusJson task) async {
    final clubId = _integer(task['club_id']);
    final taskId = _integer(task['id']);
    if (clubId == null || taskId == null) return;
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ClubTasksScreen(clubId: clubId, initialTaskId: taskId),
      ),
    );
    if (mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final tasks = _visibleTasks.toList();
    final open = tasks.where((task) => _text(task, 'status', 'open') != 'done');
    final done = tasks.where((task) => _text(task, 'status', 'open') == 'done');
    return Scaffold(
      appBar: AppBar(title: Text(_t('myTasks.title'))),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
          children: [
            if (_loading) const LinearProgressIndicator(),
            Text(
              _t('myTasks.subtitle'),
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _area,
              decoration: InputDecoration(
                labelText: _t('clubTasks.scope'),
                prefixIcon: const Icon(Icons.layers_outlined),
              ),
              items: const ['all', 'personal', 'team', 'club']
                  .map(
                    (value) => DropdownMenuItem(
                      value: value,
                      child: Text(_t('clubTasks.scope.$value')),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _area = value ?? 'all'),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _relationship,
              decoration: InputDecoration(
                labelText: _t('myTasks.relationship'),
                prefixIcon: const Icon(Icons.person_pin_outlined),
              ),
              items:
                  const ['all', 'creator', 'assigned', 'shared', 'team', 'club']
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(_t('myTasks.relationship.$value')),
                        ),
                      )
                      .toList(),
              onChanged: (value) =>
                  setState(() => _relationship = value ?? 'all'),
            ),
            if (_error != null) ...[
              const SizedBox(height: 16),
              Text(_error!),
              TextButton(onPressed: _load, child: Text(_t('common.retry'))),
            ],
            if (!_loading && _error == null && tasks.isEmpty) ...[
              const SizedBox(height: 32),
              Center(child: Text(_t('myTasks.empty'))),
            ],
            if (open.isNotEmpty) ...[
              const SizedBox(height: 24),
              _Heading(_t('clubTasks.open')),
              for (final task in open)
                _GlobalTaskTile(
                  task: task,
                  t: _t,
                  onTap: () => _openTask(task),
                ),
            ],
            if (done.isNotEmpty) ...[
              const SizedBox(height: 24),
              _Heading(_t('clubTasks.completed')),
              for (final task in done)
                _GlobalTaskTile(
                  task: task,
                  t: _t,
                  onTap: () => _openTask(task),
                ),
            ],
          ],
        ),
      ),
    );
  }
}

class _GlobalTaskTile extends StatelessWidget {
  const _GlobalTaskTile({
    required this.task,
    required this.t,
    required this.onTap,
  });

  final AirmiusJson task;
  final String Function(String key) t;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final club = task['club'] is AirmiusJson
        ? _text(task['club'] as AirmiusJson, 'name', t('clubTasks.unknown'))
        : t('clubTasks.unknown');
    final visibility = _text(task, 'visibility', 'personal');
    final relationship = _text(task, 'relationship', 'club');
    final pending = task['requires_response'] == true;
    final done = _text(task, 'status', 'open') == 'done';
    return Card(
      margin: const EdgeInsets.only(top: 10),
      child: ListTile(
        onTap: onTap,
        leading: Icon(
          done ? Icons.check_circle_outline : Icons.radio_button_unchecked,
        ),
        title: Text(
          _text(task, 'title', t('clubTasks.task')),
          style: TextStyle(
            decoration: done ? TextDecoration.lineThrough : null,
          ),
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              _Tag(icon: Icons.domain_outlined, label: club),
              _Tag(label: t('clubTasks.visibility.$visibility')),
              _Tag(label: t('myTasks.relationship.$relationship')),
              if (pending)
                _Tag(
                  icon: Icons.mark_email_unread_outlined,
                  label: t('myTasks.pending'),
                ),
            ],
          ),
        ),
        trailing: const Icon(Icons.chevron_right),
      ),
    );
  }
}

class _Tag extends StatelessWidget {
  const _Tag({required this.label, this.icon});

  final String label;
  final IconData? icon;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(6),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (icon != null) ...[Icon(icon, size: 14), const SizedBox(width: 4)],
        Text(label, style: Theme.of(context).textTheme.labelSmall),
      ],
    ),
  );
}

class _Heading extends StatelessWidget {
  const _Heading(this.label);

  final String label;

  @override
  Widget build(BuildContext context) =>
      Text(label, style: Theme.of(context).textTheme.titleMedium);
}

String _text(AirmiusJson json, String key, [String fallback = '']) {
  final value = json[key];
  return value is String && value.trim().isNotEmpty ? value.trim() : fallback;
}

int? _integer(Object? value) {
  if (value is int) return value;
  return int.tryParse('$value');
}
