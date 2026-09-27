import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';

class AdminMembersScreen extends StatefulWidget {
  const AdminMembersScreen({super.key});
  @override
  State<AdminMembersScreen> createState() => _MembersState();
}

class _MembersState extends State<AdminMembersScreen> {
  final _search = TextEditingController();
  Future<AirmiusJson>? _future;
  int _page = 1;
  String _status = 'all';
  String _section = 'users';
  String _inactiveStage = 'all';
  bool _busy = false;
  String t(String key) => AirmiusScope.of(context).t(key);
  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  bool can(String permission) =>
      AirmiusServicesScope.of(context).authState.user?.can(permission) == true;
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<AirmiusJson> _load() async => Map<String, dynamic>.from(
    (await _client.adminMembers(
          page: _page,
          search: _search.text.trim(),
          status: _status,
          inactiveStage: _inactiveStage,
        ))['data']
        as Map,
  );
  void _reload() => setState(() => _future = _load());

  Future<void> _edit([int? id]) async {
    final changed = await Navigator.of(
      context,
    ).push<bool>(MaterialPageRoute(builder: (_) => _MemberEditor(id: id)));
    if (changed == true && mounted) _reload();
  }

  Future<void> _delete(Map user) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('adminNative.deleteUser')),
        content: Text('${user['name']}\n${t('adminNative.deleteUserWarning')}'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('common.delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await _client.adminDeleteMember((user['id'] as num).toInt());
      if (mounted) _reload();
    } on AirmiusApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _notice(Map user, String stage) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('adminNative.notice')),
        content: Text(
          '${user['name']}\n${user['email']}\n\n${t('adminNative.notice_$stage')}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('adminNative.send')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await _client.adminSendInactivityNotice(
        (user['id'] as num).toInt(),
        stage,
      );
      if (mounted) _reload();
    } on AirmiusApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(t('platformAdmin.users')),
      actions: [
        if (can('users.create') && can('system.manage'))
          IconButton(
            tooltip: t('adminNative.createUser'),
            onPressed: _busy ? null : () => _edit(),
            icon: const Icon(Icons.person_add_outlined),
          ),
        IconButton(
          tooltip: t('common.refresh'),
          onPressed: _busy ? null : _reload,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    for (final section in [
                      'users',
                      'warnings',
                      if (can('system.manage')) 'inactivity',
                    ])
                      Padding(
                        padding: const EdgeInsetsDirectional.only(end: 8),
                        child: ChoiceChip(
                          label: Text(t('adminNative.$section')),
                          selected: _section == section,
                          onSelected: (_) {
                            setState(() {
                              _section = section;
                              _page = 1;
                            });
                            _reload();
                          },
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _search,
                decoration: InputDecoration(
                  labelText: t('adminNative.search'),
                  suffixIcon: IconButton(
                    tooltip: t('adminNative.search'),
                    onPressed: () {
                      _page = 1;
                      _reload();
                    },
                    icon: const Icon(Icons.search),
                  ),
                ),
                onSubmitted: (_) {
                  _page = 1;
                  _reload();
                },
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _status,
                items: [
                  for (final status in ['all', 'active', 'suspended'])
                    DropdownMenuItem(
                      value: status,
                      child: Text(t('adminNative.$status')),
                    ),
                ],
                onChanged: (value) {
                  _status = value!;
                  _page = 1;
                  _reload();
                },
              ),
              if (_section == 'inactivity')
                DropdownButtonFormField<String>(
                  initialValue: _inactiveStage,
                  items: [
                    for (final value in [
                      'all',
                      '12',
                      '18',
                      '24',
                      '36',
                      'mail_failed',
                    ])
                      DropdownMenuItem(
                        value: value,
                        child: Text(t('adminNative.stage_$value')),
                      ),
                  ],
                  onChanged: (value) {
                    _inactiveStage = value!;
                    _page = 1;
                    _reload();
                  },
                ),
            ],
          ),
        ),
        if (_busy) const LinearProgressIndicator(),
        Expanded(
          child: FutureBuilder<AirmiusJson>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasError) {
                return Center(
                  child: Text(
                    snapshot.error is AirmiusApiException
                        ? (snapshot.error as AirmiusApiException).userMessage
                        : t('platformAdmin.loadFailed'),
                  ),
                );
              }
              if (_section == 'warnings') {
                final warnings = (snapshot.data?['warnings'] as List? ?? [])
                    .whereType<Map>();
                return ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (warnings.isEmpty) Text(t('adminNative.empty')),
                    for (final warning in warnings)
                      ListTile(
                        title: Text(
                          '${(warning['user'] as Map?)?['name'] ?? ''}',
                        ),
                        subtitle: Text(
                          '${warning['reason'] ?? ''}\n${warning['severity']} · ${warning['points']}',
                        ),
                        onTap:
                            can('users.edit') &&
                                (warning['user'] as Map?)?['id'] != null
                            ? () =>
                                  _edit((warning['user']['id'] as num).toInt())
                            : null,
                      ),
                  ],
                );
              }
              final page =
                  snapshot.data?[_section == 'inactivity'
                          ? 'inactiveUsers'
                          : 'users']
                      as Map? ??
                  {};
              final users = (page['data'] as List? ?? []).whereType<Map>();
              return ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (users.isEmpty) Text(t('platformAdmin.noUsers')),
                  for (final user in users) ...[
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text('${user['name']}'),
                      subtitle: Text(
                        '${user['email']}\n${t('adminNative.${user['account_status'] ?? 'active'}')}',
                      ),
                      onTap: can('users.edit')
                          ? () => _edit((user['id'] as num).toInt())
                          : null,
                      trailing:
                          can('users.delete') &&
                              user['id'] !=
                                  AirmiusServicesScope.of(
                                    context,
                                  ).authState.user?.id
                          ? IconButton(
                              tooltip: t('common.delete'),
                              onPressed: _busy ? null : () => _delete(user),
                              icon: const Icon(Icons.delete_outline),
                            )
                          : null,
                    ),
                    if (_section == 'inactivity') ...[
                      Text(
                        '${t('adminNative.inactiveDays')}: ${user['inactive_days'] ?? '—'}',
                      ),
                      if (user['deletion_scheduled_at'] != null)
                        Text(
                          '${t('adminNative.scheduledDeletion')}: ${user['deletion_scheduled_at']}',
                        ),
                      if (user['last_mail'] is Map)
                        Text(
                          '${t('adminNative.lastMail')}: ${user['last_mail']['status']}',
                        ),
                      Wrap(
                        spacing: 8,
                        children: [
                          for (final stage in ['first', 'second', 'scheduled'])
                            TextButton(
                              onPressed: _busy
                                  ? null
                                  : () => _notice(user, stage),
                              child: Text(t('adminNative.notice_$stage')),
                            ),
                        ],
                      ),
                    ],
                    const Divider(height: 1),
                  ],
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      IconButton(
                        tooltip: t('adminNative.previous'),
                        onPressed: _page <= 1
                            ? null
                            : () {
                                _page--;
                                _reload();
                              },
                        icon: const Icon(Icons.chevron_left),
                      ),
                      Text('$_page / ${page['last_page'] ?? 1}'),
                      IconButton(
                        tooltip: t('adminNative.next'),
                        onPressed: _page >= (page['last_page'] as num? ?? 1)
                            ? null
                            : () {
                                _page++;
                                _reload();
                              },
                        icon: const Icon(Icons.chevron_right),
                      ),
                    ],
                  ),
                ],
              );
            },
          ),
        ),
      ],
    ),
  );
}

class _MemberEditor extends StatefulWidget {
  const _MemberEditor({this.id});
  final int? id;
  @override
  State<_MemberEditor> createState() => _EditorState();
}

class _EditorState extends State<_MemberEditor> {
  final _form = GlobalKey<FormState>();
  final _fields = {
    for (final key in [
      'name',
      'first_name',
      'last_name',
      'email',
      'birth_date',
      'bio',
      'password',
      'password_confirmation',
      'suspension_reason',
    ])
      key: TextEditingController(),
  };
  Future<void>? _future;
  List<String> _availableRoles = [];
  Set<String> _roles = {};
  bool _canManageRoles = false;
  String _visibility = 'private';
  String _suspension = '';
  bool _sendCredentials = false;
  bool _generatePassword = false;
  bool _busy = false;
  String? _error;
  String t(String key) => AirmiusScope.of(context).t(key);
  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  @override
  void dispose() {
    for (final field in _fields.values) {
      field.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    if (widget.id == null) return;
    final data = (await _client.adminMember(widget.id!))['data'] as Map;
    final user = data['user'] as Map;
    for (final entry in _fields.entries) {
      entry.value.text = '${user[entry.key] ?? ''}';
    }
    _visibility = '${user['profile_visibility'] ?? 'private'}';
    _roles = (user['roles'] as List? ?? []).cast<String>().toSet();
    _availableRoles = (data['availableRoles'] as List? ?? []).cast<String>();
    _canManageRoles = data['canManageRoles'] == true;
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate() || _busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final payload = <String, dynamic>{
        for (final entry in _fields.entries)
          if (!['password', 'password_confirmation'].contains(entry.key) ||
              (widget.id == null && !_generatePassword))
            entry.key: entry.key.startsWith('password')
                ? entry.value.text
                : entry.value.text.trim(),
        'profile_visibility': _visibility,
        if (widget.id == null) ...{
          'generate_password': _generatePassword,
          'send_credentials': _sendCredentials,
        },
        if (widget.id != null) 'suspension_action': _suspension,
        if (_canManageRoles) 'roles': _roles.toList(),
      };
      if (widget.id == null) {
        final result = await _client.adminCreatePlatformUser(payload);
        final password = (result['data'] as Map?)?['generated_password'];
        if (password is String && mounted) {
          await showDialog<void>(
            context: context,
            builder: (dialogContext) => AlertDialog(
              title: Text(t('adminNative.password')),
              content: SelectableText(password),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(dialogContext),
                  child: Text(t('common.close')),
                ),
              ],
            ),
          );
        }
      } else {
        await _client.adminUpdateMember(widget.id!, payload);
      }
      if (mounted) Navigator.pop(context, true);
    } on AirmiusApiException catch (error) {
      if (mounted) setState(() => _error = error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        t(
          widget.id == null ? 'adminNative.createUser' : 'adminNative.editUser',
        ),
      ),
    ),
    body: FutureBuilder<void>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(
            child: Text(
              snapshot.error is AirmiusApiException
                  ? (snapshot.error as AirmiusApiException).userMessage
                  : t('platformAdmin.loadFailed'),
            ),
          );
        }
        return Form(
          key: _form,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
              for (final key
                  in widget.id == null
                      ? [
                          'name',
                          'email',
                          if (!_generatePassword) ...[
                            'password',
                            'password_confirmation',
                          ],
                        ]
                      : [
                          'name',
                          'first_name',
                          'last_name',
                          'email',
                          'birth_date',
                          'bio',
                        ])
                Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: TextFormField(
                    controller: _fields[key],
                    enabled: !_busy,
                    obscureText: key.startsWith('password'),
                    autocorrect: !key.startsWith('password'),
                    enableSuggestions: !key.startsWith('password'),
                    maxLines: key == 'bio' ? 4 : 1,
                    decoration: InputDecoration(
                      labelText: t('adminNative.$key'),
                      hintText: key == 'birth_date' ? 'YYYY-MM-DD' : null,
                    ),
                    keyboardType: key == 'email'
                        ? TextInputType.emailAddress
                        : TextInputType.text,
                    validator: (value) =>
                        [
                              'name',
                              'email',
                              'password',
                              'password_confirmation',
                            ].contains(key) &&
                            (value?.trim().isEmpty ?? true)
                        ? t('adminNative.required')
                        : null,
                  ),
                ),
              if (widget.id == null)
                SwitchListTile(
                  title: Text(t('adminNative.generatePassword')),
                  value: _generatePassword,
                  onChanged: _busy
                      ? null
                      : (value) => setState(() => _generatePassword = value),
                ),
              DropdownButtonFormField<String>(
                initialValue: _visibility,
                decoration: InputDecoration(
                  labelText: t('adminNative.visibility'),
                ),
                items: [
                  for (final value in ['private', 'public'])
                    DropdownMenuItem(
                      value: value,
                      child: Text(t('adminNative.visibility_$value')),
                    ),
                ],
                onChanged: _busy
                    ? null
                    : (value) => setState(() => _visibility = value!),
              ),
              if (widget.id == null)
                SwitchListTile(
                  title: Text(t('adminNative.sendCredentials')),
                  value: _sendCredentials,
                  onChanged: _busy
                      ? null
                      : (value) => setState(() => _sendCredentials = value),
                ),
              if (widget.id != null) ...[
                const SizedBox(height: 16),
                DropdownButtonFormField<String>(
                  initialValue: _suspension,
                  decoration: InputDecoration(
                    labelText: t('adminNative.suspension'),
                  ),
                  items: [
                    DropdownMenuItem(
                      value: '',
                      child: Text(t('adminNative.unchanged')),
                    ),
                    DropdownMenuItem(
                      value: 'lift',
                      child: Text(t('adminNative.lift')),
                    ),
                    for (final days in [1, 3, 7, 10, 14, 30, 60, 90])
                      DropdownMenuItem(
                        value: '$days',
                        child: Text('$days ${t('adminNative.days')}'),
                      ),
                  ],
                  onChanged: _busy
                      ? null
                      : (value) => setState(() => _suspension = value!),
                ),
                if (_suspension != '' && _suspension != 'lift')
                  Padding(
                    padding: const EdgeInsets.only(top: 16),
                    child: TextFormField(
                      controller: _fields['suspension_reason'],
                      decoration: InputDecoration(
                        labelText: t('platformAdmin.reason'),
                      ),
                    ),
                  ),
                if (_canManageRoles) ...[
                  const SizedBox(height: 16),
                  Text(
                    t('platformAdmin.roles'),
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  for (final role in _availableRoles)
                    CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(role),
                      value: _roles.contains(role),
                      onChanged: _busy
                          ? null
                          : (value) => setState(() {
                              if (value == true) {
                                _roles.add(role);
                              } else {
                                _roles.remove(role);
                              }
                            }),
                    ),
                ],
              ],
              const SizedBox(height: 24),
              FilledButton.icon(
                onPressed: _busy ? null : _save,
                icon: const Icon(Icons.save_outlined),
                label: Text(t('common.save')),
              ),
              if (_busy) const LinearProgressIndicator(),
            ],
          ),
        );
      },
    ),
  );
}
