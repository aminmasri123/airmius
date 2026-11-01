import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';

class RoleAssignmentScreen extends StatefulWidget {
  const RoleAssignmentScreen({
    super.key,
    required this.client,
    this.userId,
    this.roleName,
  });

  final AirmiusApiClient client;
  final int? userId;
  final String? roleName;

  @override
  State<RoleAssignmentScreen> createState() => _RoleAssignmentScreenState();
}

class _RoleAssignmentScreenState extends State<RoleAssignmentScreen> {
  final _search = TextEditingController();
  List<Map<String, dynamic>> _users = [];
  List<Map<String, dynamic>> _roles = [];
  Map<String, dynamic>? _target;
  Set<String> _selected = {};
  bool _busy = false;
  bool _assignedOnly = false;
  String? _error;
  String? _message;
  int _page = 1;
  int _lastPage = 1;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> _maps(dynamic value) => value is List
      ? value.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
      : [];

  List<String> _strings(dynamic value) =>
      value is List ? value.map((e) => e.toString()).toList() : [];

  Future<void> _load([int page = 1]) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
      _target = null;
      _message = null;
    });
    try {
      final response = await widget.client.adminRoleAssignments({
        'q': _search.text.trim(),
        'page': '$page',
        if (widget.userId != null) 'user_id': '${widget.userId}',
        if (widget.roleName != null) 'role': widget.roleName!,
        'assigned_only': _assignedOnly ? '1' : '0',
      });
      if (!mounted) return;
      final data = response['data'] as Map;
      final users = data['users'] as Map;
      setState(() {
        _roles = _maps(data['roles']);
        _users = _maps(users['data']);
        _page = (users['current_page'] as num).toInt();
        _lastPage = (users['last_page'] as num).toInt();
        if (widget.userId != null && _users.isNotEmpty) _choose(_users.first);
      });
    } catch (error) {
      if (!mounted) return;
      setState(
        () => _error = error is AirmiusApiException
            ? error.userMessage
            : 'Nutzer konnten nicht geladen werden.',
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _choose(Map<String, dynamic> user) {
    _target = user;
    _selected = _strings(user['roles']).toSet();
    _message = null;
  }

  Future<void> _save() async {
    final target = _target;
    if (_busy || target == null) return;
    setState(() {
      _busy = true;
      _error = null;
      _message = null;
    });
    try {
      final response = await widget.client.adminAssignUserRoles(
        (target['id'] as num).toInt(),
        {
          'roles': _selected.toList(),
          'expected_roles': _strings(target['roles']),
        },
      );
      if (!mounted) return;
      final data = response['data'] as Map;
      setState(() {
        target['roles'] = _strings(data['roles']);
        _selected = _strings(data['roles']).toSet();
        _message =
            'Rollen gespeichert. Die Person wird über Änderungen benachrichtigt.';
      });
    } catch (error) {
      if (!mounted) return;
      setState(
        () => _error = error is AirmiusApiException
            ? error.userMessage
            : 'Rollen konnten nicht gespeichert werden.',
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final target = _target;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          widget.userId != null ? 'Rollen verwalten' : 'Nutzer zuweisen',
        ),
        actions: [
          IconButton(
            onPressed: _busy ? null : () => _load(_page),
            icon: const Icon(Icons.refresh),
            tooltip: 'Neu laden',
          ),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: [
            if (target != null)
              Padding(
                padding: const EdgeInsets.all(16),
                child: SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: _busy ? null : _save,
                    icon: const Icon(Icons.save_outlined),
                    label: const Text('Rollen speichern'),
                  ),
                ),
              ),
            if (_busy) const LinearProgressIndicator(),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  const Text(
                    'Änderungen gelten auf Plattformebene. Die Person erhält eine Benachrichtigung mit ihren aktuellen Berechtigungen.',
                  ),
                  const SizedBox(height: 12),
                  if (widget.userId == null && target == null) ...[
                    TextField(
                      controller: _search,
                      maxLength: 255,
                      enabled: !_busy,
                      decoration: const InputDecoration(
                        labelText: 'Name oder E-Mail suchen',
                      ),
                      onSubmitted: (_) => _load(),
                    ),
                    if (widget.roleName != null)
                      CheckboxListTile(
                        title: const Text(
                          'Nur dieser Rolle zugewiesene Personen',
                        ),
                        value: _assignedOnly,
                        onChanged: _busy
                            ? null
                            : (value) => setState(
                                () => _assignedOnly = value ?? false,
                              ),
                      ),
                    OutlinedButton.icon(
                      onPressed: _busy ? null : () => _load(),
                      icon: const Icon(Icons.search),
                      label: const Text('Suchen'),
                    ),
                    for (final user in _users)
                      Card(
                        child: ListTile(
                          title: Text('${user['name']}'),
                          subtitle: Text(
                            '${user['email']}\n${_strings(user['roles']).join(', ')}',
                          ),
                          selected: target?['id'] == user['id'],
                          trailing: const Icon(Icons.manage_accounts_outlined),
                          onTap: _busy
                              ? null
                              : () => setState(() => _choose(user)),
                        ),
                      ),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        IconButton(
                          onPressed: _busy || _page <= 1
                              ? null
                              : () => _load(_page - 1),
                          icon: const Icon(Icons.chevron_left),
                          tooltip: 'Zurück',
                        ),
                        Text('$_page / $_lastPage'),
                        IconButton(
                          onPressed: _busy || _page >= _lastPage
                              ? null
                              : () => _load(_page + 1),
                          icon: const Icon(Icons.chevron_right),
                          tooltip: 'Weiter',
                        ),
                      ],
                    ),
                  ],
                  if (_error != null)
                    Text(
                      _error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  if (_message != null) Text(_message!),
                  if (!_busy && _users.isEmpty)
                    const Text('Keine zuweisbaren Personen gefunden.'),
                  if (target != null) ...[
                    if (widget.userId == null)
                      TextButton(
                        onPressed: _busy
                            ? null
                            : () => setState(() {
                                _target = null;
                                _message = null;
                                _error = null;
                              }),
                        child: const Text('Andere Person wählen'),
                      ),
                    Text(
                      'Rollen für ${target['name']}',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    if (widget.roleName != null)
                      Text(
                        'Gewünschte Rolle: ${widget.roleName}. Bestehende Rollen bleiben ausgewählt.',
                      ),
                    const SizedBox(height: 12),
                    for (final role in _roles)
                      Card(
                        child: CheckboxListTile(
                          title: Text('${role['name']}'),
                          subtitle: Text(
                            [
                              if (role['description'] != null)
                                '${role['description']}',
                              ..._maps(
                                role['permissions'],
                              ).map((p) => '${p['description']}'),
                            ].join('\n'),
                          ),
                          value: _selected.contains(role['name']),
                          onChanged: _busy
                              ? null
                              : (value) => setState(() {
                                  if (value == true) {
                                    _selected.add('${role['name']}');
                                  } else {
                                    _selected.remove('${role['name']}');
                                  }
                                }),
                        ),
                      ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
