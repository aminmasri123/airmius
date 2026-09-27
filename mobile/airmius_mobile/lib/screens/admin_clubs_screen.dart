import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';

class AdminClubsScreen extends StatefulWidget {
  const AdminClubsScreen({super.key, this.verification});
  final String? verification;

  @override
  State<AdminClubsScreen> createState() => _AdminClubsState();
}

class _AdminClubsState extends State<AdminClubsScreen> {
  final _search = TextEditingController();
  Future<AirmiusJson>? _future;
  String? _verification;
  int _page = 1;
  bool _busy = false;
  static const _statuses = ['pending_verification', 'verified', 'rejected'];

  String t(String key) => AirmiusScope.of(context).t(key);
  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void initState() {
    super.initState();
    _verification = widget.verification;
  }

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

  Future<AirmiusJson> _load() async {
    final response = await _client.adminClubs(
      page: _page,
      query: _search.text.trim(),
      verification: _verification,
    );
    return Map<String, dynamic>.from(response['data'] as Map);
  }

  void _reload() => setState(() => _future = _load());

  Future<void> _run(Future<AirmiusJson> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
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

  String _status(Object? value) => t(
    'platformAdmin.status.${value == 'pending' || value == null ? 'pending_verification' : value}',
  );

  Future<void> _change(Map club, String status) async {
    if (_busy || club['verification_status'] == status) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('platformAdmin.changeClubStatus')),
        content: Text(
          '${club['name']}\n\n${_status(club['verification_status'])} → ${_status(status)}\n\n${t('platformAdmin.confirmClubStatus')}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('common.save')),
          ),
        ],
      ),
    );
    if (confirmed == true && mounted) {
      await _run(
        () => _client.adminUpdateClubVerification(
          (club['id'] as num).toInt(),
          status,
        ),
      );
    }
  }

  Future<void> _delete(Map club) async {
    final name = TextEditingController();
    final form = GlobalKey<FormState>();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('adminNative.deleteClub')),
        content: SingleChildScrollView(
          child: Form(
            key: form,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(t('adminNative.deleteClubWarning')),
                const SizedBox(height: 16),
                TextFormField(
                  controller: name,
                  decoration: InputDecoration(labelText: '${club['name']}'),
                  validator: (value) => value?.trim() == club['name']
                      ? null
                      : t('adminNative.nameMismatch'),
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () {
              if (form.currentState!.validate()) {
                Navigator.pop(dialogContext, true);
              }
            },
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
            ),
            child: Text(t('common.delete')),
          ),
        ],
      ),
    );
    final confirmation = name.text.trim();
    name.dispose();
    if (confirmed == true && mounted) {
      await _run(
        () =>
            _client.adminDeleteClub((club['id'] as num).toInt(), confirmation),
      );
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(t('platformAdmin.club')),
      actions: [
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
              TextField(
                controller: _search,
                maxLength: 120,
                decoration: InputDecoration(
                  labelText: t('adminNative.search'),
                  counterText: '',
                  suffixIcon: IconButton(
                    tooltip: t('adminNative.search'),
                    onPressed: _busy
                        ? null
                        : () {
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
                initialValue: _verification ?? 'all',
                isExpanded: true,
                items: [
                  DropdownMenuItem(
                    value: 'all',
                    child: Text(t('adminNative.all')),
                  ),
                  for (final status in _statuses)
                    DropdownMenuItem(
                      value: status,
                      child: Text(_status(status)),
                    ),
                ],
                onChanged: _busy
                    ? null
                    : (value) {
                        _verification = value == 'all' ? null : value;
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
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(
                      snapshot.error is AirmiusApiException
                          ? (snapshot.error as AirmiusApiException).userMessage
                          : t('platformAdmin.loadFailed'),
                    ),
                  ),
                );
              }
              final data = snapshot.data ?? {};
              final page = data['clubs'] as Map? ?? {};
              final clubs = (page['data'] as List? ?? []).whereType<Map>();
              return ListView(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                children: [
                  if (clubs.isEmpty) Text(t('platformAdmin.noClubs')),
                  for (final club in clubs) ...[
                    ExpansionTile(
                      tilePadding: EdgeInsets.zero,
                      title: Text('${club['name']}'),
                      subtitle: Text(
                        [club['city'], club['country']]
                            .whereType<String>()
                            .where((s) => s.isNotEmpty)
                            .join(' · '),
                      ),
                      children: [
                        ListTile(
                          title: Text(t('platformAdmin.owner')),
                          subtitle: Text(
                            '${(club['owner'] as Map?)?['name'] ?? ''}\n${(club['owner'] as Map?)?['email'] ?? ''}',
                          ),
                        ),
                        ListTile(
                          title: Text(t('adminNative.membersTeams')),
                          subtitle: Text(
                            '${club['members_count']} / ${club['teams_count']}',
                          ),
                        ),
                        if (club['plan'] is Map)
                          ListTile(title: Text('${club['plan']['name']}')),
                        if (club['official_club_number'] != null)
                          ListTile(
                            title: Text(t('platformAdmin.officialNumber')),
                            subtitle: Text('${club['official_club_number']}'),
                          ),
                        if (data['canDeleteClubs'] == true)
                          TextButton.icon(
                            onPressed: _busy ? null : () => _delete(club),
                            icon: const Icon(Icons.delete_outline),
                            label: Text(t('adminNative.deleteClub')),
                          ),
                      ],
                    ),
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: PopupMenuButton<String>(
                        enabled: !_busy,
                        tooltip: t('platformAdmin.changeClubStatus'),
                        onSelected: (value) => _change(club, value),
                        itemBuilder: (_) => [
                          for (final status in _statuses)
                            PopupMenuItem(
                              value: status,
                              child: Text(_status(status)),
                            ),
                        ],
                        child: Padding(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(_status(club['verification_status'])),
                              const Icon(Icons.arrow_drop_down),
                            ],
                          ),
                        ),
                      ),
                    ),
                    const Divider(),
                  ],
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      IconButton(
                        tooltip: t('adminNative.previous'),
                        onPressed: _page <= 1 || _busy
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
                        onPressed:
                            _page >= (page['last_page'] as num? ?? 1) || _busy
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
