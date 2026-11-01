import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_services_scope.dart';
import 'club_access_management_screen.dart';
import 'club_profile_editor_screen.dart';

class AdminClubWorkspaceScreen extends StatefulWidget {
  const AdminClubWorkspaceScreen({
    super.key,
    required this.clubId,
    this.client,
  });
  final int clubId;
  final AirmiusApiClient? client;
  @override
  State<AdminClubWorkspaceScreen> createState() => _AdminClubWorkspaceState();
}

class _AdminClubWorkspaceState extends State<AdminClubWorkspaceScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _busy = false;
  final _pages = <String, String>{};
  final _fields = <String, TextEditingController>{};
  final _end = TextEditingController();
  final _trial = TextEditingController();
  int? _plan;
  String _status = 'active';
  String _verification = 'pending_verification';
  static const tabs = [
    'Übersicht',
    'Vereinsdaten',
    'Mitglieder & Rollen',
    'Teams',
    'Abo & Abrechnung',
    'Prüfung',
    'Änderungsverlauf',
  ];
  static const labels = {
    'active': 'Aktiv',
    'trialing': 'Testphase',
    'past_due': 'Zahlung überfällig',
    'cancelled': 'Gekündigt',
    'cancels_at_period_end': 'Kündigung zum Laufzeitende',
    'verified': 'Verifiziert',
    'rejected': 'Abgelehnt',
    'pending_verification': 'Prüfung ausstehend',
  };
  AirmiusApiClient get client {
    if (widget.client != null) return widget.client!;
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_data == null && !_busy && _error == null) _load();
  }

  Future<void> _load({bool resetForms = true}) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await client.adminClubWorkspace(
        widget.clubId,
        pages: _pages,
      );
      if (!mounted) return;
      final data = Map<String, dynamic>.from(response['data'] as Map);
      final club = data['club'] as Map;
      if (resetForms) {
        for (final key in (data['fields'] as Map).keys) {
          (_fields[key] ??= TextEditingController()).text =
              '${club[key] ?? ''}';
        }
        final subscription = data['subscription'] as Map? ?? {};
        _plan = (subscription['subscription_plan_id'] as num?)?.toInt();
        _status = '${subscription['status'] ?? 'active'}';
        _verification = '${club['verification_status']}';
        _end.text = '${subscription['current_period_ends_at'] ?? ''}'
            .split('T')
            .first;
        _trial.text = '${subscription['trial_ends_at'] ?? ''}'.split('T').first;
      }
      setState(() => _data = data);
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : 'Vereinsdaten konnten nicht geladen werden.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _save(Future<dynamic> Function() action) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Änderung gespeichert.')));
      await _load();
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : 'Speichern fehlgeschlagen. Bitte erneut versuchen.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _confirm(String title, Future<dynamic> Function() action) async {
    final result = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: Text(
          'Änderung für ${_data?['club']['name']} speichern? Der Vereinsverantwortliche wird bei Abo- und Prüfentscheidungen benachrichtigt.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Abbrechen'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Bestätigen'),
          ),
        ],
      ),
    );
    if (result == true && mounted) await _save(action);
  }

  @override
  void dispose() {
    for (final field in _fields.values) {
      field.dispose();
    }
    _end.dispose();
    _trial.dispose();
    super.dispose();
  }

  Widget list(List<Widget> children) =>
      ListView(padding: const EdgeInsets.all(20), children: children);
  Widget pagination(Map page, String key) => Row(
    mainAxisAlignment: MainAxisAlignment.center,
    children: [
      IconButton(
        onPressed: _busy || (page['current_page'] as num) <= 1
            ? null
            : () {
                _pages[key] = '${(page['current_page'] as num) - 1}';
                _load(resetForms: false);
              },
        icon: const Icon(Icons.chevron_left),
      ),
      Text('${page['current_page']} / ${page['last_page']}'),
      IconButton(
        onPressed:
            _busy || (page['current_page'] as num) >= (page['last_page'] as num)
            ? null
            : () {
                _pages[key] = '${(page['current_page'] as num) + 1}';
                _load(resetForms: false);
              },
        icon: const Icon(Icons.chevron_right),
      ),
    ],
  );
  Widget editorLink() => TextButton.icon(
    onPressed: _busy
        ? null
        : () => Navigator.push(
            context,
            MaterialPageRoute<void>(
              builder: (_) =>
                  ClubProfileEditorScreen(initialClubId: widget.clubId),
            ),
          ),
    icon: const Icon(Icons.open_in_new),
    label: const Text('Weitere Vereinsdaten und Funktionen'),
  );
  @override
  Widget build(BuildContext context) {
    final data = _data;
    final club = data?['club'] as Map? ?? {};
    final members = data?['members'] as Map? ?? {};
    final teams = data?['teams'] as Map? ?? {};
    final invoices = data?['invoices'] as Map?;
    final plans = (data?['plans'] as List? ?? []).whereType<Map>().toList();
    return DefaultTabController(
      length: tabs.length,
      child: Scaffold(
        appBar: AppBar(
          title: Text('${club['name'] ?? 'Vereinsverwaltung'}'),
          actions: [
            IconButton(
              onPressed: _busy ? null : _load,
              icon: const Icon(Icons.refresh),
              tooltip: 'Aktualisieren',
            ),
          ],
          bottom: TabBar(
            isScrollable: true,
            tabs: [for (final tab in tabs) Tab(text: tab)],
          ),
        ),
        body: Column(
          children: [
            if (_busy) const LinearProgressIndicator(),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.all(12),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            if (data == null && !_busy)
              TextButton(onPressed: _load, child: const Text('Erneut laden')),
            if (data != null)
              Expanded(
                child: TabBarView(
                  children: [
                    list([
                      ListTile(
                        title: const Text('Verantwortlich'),
                        subtitle: Text(
                          '${data['owner']?['name'] ?? 'Nicht hinterlegt'}\n${data['owner']?['email'] ?? ''}',
                        ),
                      ),
                      ListTile(
                        title: const Text('Mitglieder / Teams'),
                        subtitle: Text(
                          '${club['users_count']} App-Konten · ${club['external_members_count']} externe Mitglieder · ${club['teams_count']} Teams',
                        ),
                      ),
                      ListTile(
                        title: const Text('Abo'),
                        subtitle: Text('${data['planName'] ?? 'Kein Abo'}'),
                      ),
                      ListTile(
                        title: const Text('Prüfstatus'),
                        subtitle: Text(labels[_verification] ?? _verification),
                      ),
                      editorLink(),
                    ]),
                    Column(
                      children: [
                        Padding(
                          padding: const EdgeInsets.all(12),
                          child: FilledButton.icon(
                            onPressed: _busy
                                ? null
                                : () => _save(
                                    () =>
                                        client.adminUpdateClub(widget.clubId, {
                                          for (final entry in _fields.entries)
                                            entry.key: entry.value.text.trim(),
                                        }),
                                  ),
                            icon: const Icon(Icons.save),
                            label: const Text('Vereinsdaten speichern'),
                          ),
                        ),
                        Expanded(
                          child: list([
                            for (final entry in (data['fields'] as Map).entries)
                              Padding(
                                padding: const EdgeInsets.only(bottom: 16),
                                child: TextField(
                                  controller: _fields[entry.key],
                                  enabled: !_busy,
                                  decoration: InputDecoration(
                                    labelText: '${entry.value}',
                                    border: const OutlineInputBorder(),
                                  ),
                                ),
                              ),
                            editorLink(),
                          ]),
                        ),
                      ],
                    ),
                    list([
                      for (final member in (members['data'] as List))
                        ListTile(
                          title: Text('${member['name']}'),
                          subtitle: Text(
                            '${member['email']} · ${member['role']}',
                          ),
                          trailing: data['canManageRoles'] == true
                              ? const Icon(Icons.manage_accounts)
                              : null,
                          onTap: data['canManageRoles'] != true
                              ? null
                              : () => Navigator.push(
                                  context,
                                  MaterialPageRoute<void>(
                                    builder: (_) => ClubAccessManagementScreen(
                                      clubId: widget.clubId,
                                      clubName: '${club['name']}',
                                      memberId: (member['id'] as num).toInt(),
                                      memberName: '${member['name']}',
                                    ),
                                  ),
                                ),
                        ),
                      if ((members['data'] as List).isEmpty)
                        const Text('Keine Mitglieder vorhanden.'),
                      pagination(members, 'page'),
                    ]),
                    list([
                      for (final team in (teams['data'] as List))
                        ListTile(
                          title: Text('${team['name']}'),
                          subtitle: Text('${team['sport_type'] ?? ''}'),
                        ),
                      if ((teams['data'] as List).isEmpty)
                        const Text('Keine Teams vorhanden.'),
                      pagination(teams, 'teams_page'),
                      editorLink(),
                    ]),
                    list([
                      if (data['canManageSubscription'] != true)
                        const Text(
                          'Für die Abonnementverwaltung fehlt dir die Berechtigung.',
                        )
                      else ...[
                        DropdownButtonFormField<int>(
                          isExpanded: true,
                          initialValue: plans.any((p) => p['id'] == _plan)
                              ? _plan
                              : null,
                          decoration: const InputDecoration(labelText: 'Tarif'),
                          items: [
                            for (final plan in plans)
                              DropdownMenuItem(
                                value: (plan['id'] as num).toInt(),
                                child: Text('${plan['name']}'),
                              ),
                          ],
                          onChanged: _busy
                              ? null
                              : (value) => setState(() => _plan = value),
                        ),
                        DropdownButtonFormField<String>(
                          isExpanded: true,
                          initialValue: _status,
                          decoration: const InputDecoration(
                            labelText: 'Status',
                          ),
                          items: [
                            for (final value in [
                              'active',
                              'trialing',
                              'past_due',
                              'cancelled',
                              'cancels_at_period_end',
                            ])
                              DropdownMenuItem(
                                value: value,
                                child: Text(labels[value]!),
                              ),
                          ],
                          onChanged: _busy
                              ? null
                              : (value) => setState(() => _status = value!),
                        ),
                        TextField(
                          controller: _end,
                          decoration: const InputDecoration(
                            labelText: 'Laufzeitende (JJJJ-MM-TT)',
                          ),
                        ),
                        TextField(
                          controller: _trial,
                          decoration: const InputDecoration(
                            labelText: 'Testphase endet (JJJJ-MM-TT)',
                          ),
                        ),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: _busy || _plan == null
                              ? null
                              : () => _confirm(
                                  'Abo ändern',
                                  () => client.adminAssignClubSubscription(
                                    widget.clubId,
                                    {
                                      'subscription_plan_id': _plan,
                                      'status': _status,
                                      'current_period_ends_at':
                                          _end.text.isEmpty ? null : _end.text,
                                      'trial_ends_at': _trial.text.isEmpty
                                          ? null
                                          : _trial.text,
                                    },
                                  ),
                                ),
                          child: const Text('Abo ändern'),
                        ),
                        const SizedBox(height: 24),
                        const Text(
                          'Rechnungen',
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                        for (final invoice
                            in (invoices?['data'] as List? ?? []))
                          ListTile(
                            title: Text('${invoice['number']}'),
                            subtitle: Text(
                              '${((invoice['amount_cents'] as num) / 100).toStringAsFixed(2)} ${invoice['currency']} · ${invoice['status']}',
                            ),
                          ),
                        if ((invoices?['data'] as List? ?? []).isEmpty)
                          const Text('Keine Rechnungen vorhanden.'),
                        if (invoices != null)
                          pagination(invoices, 'invoices_page'),
                      ],
                    ]),
                    list([
                      Text(
                        'Vereinsnummer: ${club['official_club_number'] ?? 'Nicht hinterlegt'}',
                      ),
                      Text('${club['verification_notes'] ?? ''}'),
                      DropdownButtonFormField<String>(
                        isExpanded: true,
                        initialValue: _verification,
                        items: [
                          for (final value in [
                            'pending_verification',
                            'verified',
                            'rejected',
                          ])
                            DropdownMenuItem(
                              value: value,
                              child: Text(labels[value]!),
                            ),
                        ],
                        onChanged: _busy
                            ? null
                            : (value) => setState(() => _verification = value!),
                      ),
                      const SizedBox(height: 16),
                      FilledButton(
                        onPressed: _busy
                            ? null
                            : () => _confirm(
                                'Prüfstatus ändern',
                                () => client.adminUpdateClubVerification(
                                  widget.clubId,
                                  _verification,
                                ),
                              ),
                        child: const Text('Prüfstatus ändern'),
                      ),
                    ]),
                    list([
                      const Text('Die letzten 50 Vereinseinträge.'),
                      for (final event in data['history'] as List)
                        ListTile(
                          title: Text('${event['label']}'),
                          subtitle: Text(
                            '${event['actor']?['name'] ?? 'System'} · ${event['created_at']}',
                          ),
                        ),
                      if ((data['history'] as List).isEmpty)
                        const Text('Noch keine Einträge.'),
                    ]),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}
