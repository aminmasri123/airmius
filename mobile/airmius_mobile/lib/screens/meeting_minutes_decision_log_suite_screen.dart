import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_date_input.dart';
import '../core/airmius_services_scope.dart';
import '../models/club_summary.dart';
import 'club_governance_meeting_forms.dart';

class MeetingMinutesDecisionLogSuiteScreen extends StatefulWidget {
  const MeetingMinutesDecisionLogSuiteScreen({super.key, this.club});

  final ClubSummary? club;

  @override
  State<MeetingMinutesDecisionLogSuiteScreen> createState() =>
      _MeetingMinutesDecisionLogSuiteScreenState();
}

class _MeetingMinutesDecisionLogSuiteScreenState
    extends State<MeetingMinutesDecisionLogSuiteScreen> {
  List<AirmiusJson> _clubs = [];
  List<AirmiusJson> _meetings = [];
  AirmiusJson? _club;
  AirmiusJson? _meeting;
  bool _loading = true;
  bool _busy = false;
  bool _canCreate = false;
  String? _error;
  String _query = '';
  String _status = '';
  int _generation = 0;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  int get _clubId => governanceInt(_club?['id'])!;
  bool get _canManage => _meeting?['can_manage'] == true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadClubs());
  }

  Future<void> _loadClubs() async {
    if (!mounted || _busy) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final boundClub = widget.club;
      final clubs = boundClub == null
          ? governanceMaps((await _client.clubs(mine: true))['data'])
          : <AirmiusJson>[
              {'id': boundClub.id, 'name': boundClub.name},
            ];
      if (!mounted) return;
      setState(() {
        _clubs = clubs;
        _club = clubs.isEmpty ? null : clubs.first;
        _loading = false;
      });
      if (_club != null) await _loadList();
    } catch (error) {
      if (mounted) {
        setState(() {
          _error = governanceError(error);
          _loading = false;
        });
      }
    }
  }

  Future<void> _loadList() async {
    if (_club == null || !mounted) return;
    final token = ++_generation;
    setState(() {
      _loading = true;
      _error = null;
      _canCreate = false;
      _meetings = [];
    });
    try {
      final response = await _client.clubGovernanceMeetings(_clubId);
      if (!mounted || token != _generation) return;
      setState(() {
        _meetings = governanceMaps(response['data']);
        _canCreate = governanceMap(response['meta'])['can_manage'] == true;
      });
    } catch (error) {
      if (mounted && token == _generation) {
        setState(() {
          _error = governanceError(error);
          _meetings = [];
          _meeting = null;
        });
      }
    } finally {
      if (mounted && token == _generation) setState(() => _loading = false);
    }
  }

  Future<void> _openMeeting(int id) async {
    final token = ++_generation;
    setState(() {
      _loading = true;
      _error = null;
      _meeting = null;
    });
    try {
      final response = await _client.clubGovernanceMeeting(_clubId, id);
      if (mounted && token == _generation) {
        setState(() => _meeting = governanceMap(response['data']));
      }
    } catch (error) {
      if (mounted && token == _generation) {
        setState(() {
          _error = governanceError(error);
          _meetings = [];
          _canCreate = false;
        });
      }
    } finally {
      if (mounted && token == _generation) setState(() => _loading = false);
    }
  }

  Future<void> _refresh() async {
    if (_busy || _loading) return;
    if (_club == null) {
      await _loadClubs();
      return;
    }
    final id = governanceInt(_meeting?['id']);
    if (id == null) {
      await _loadList();
    } else {
      await _openMeeting(id);
    }
  }

  Future<void> _back() async {
    if (_busy) return;
    setState(() => _meeting = null);
    await _loadList();
  }

  Future<void> _editMeeting({bool create = false}) async {
    if (_busy || _loading || (create ? !_canCreate : !_canManage)) return;
    final client = _client;
    final clubId = _clubId;
    setState(() {
      _busy = true;
      _error = null;
    });
    AirmiusJson governance;
    try {
      governance = governanceMap((await client.clubGovernance(clubId))['data']);
    } catch (error) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = governanceError(error);
        });
      }
      return;
    }
    if (!mounted) return;
    setState(() => _busy = false);
    final meetingId = create ? null : governanceInt(_meeting?['id']);
    final savedId = await Navigator.push<int>(
      context,
      MaterialPageRoute(
        builder: (_) => ClubGovernanceMeetingForm(
          meeting: create ? null : _meeting,
          governance: governance,
          save: (payload) async {
            final result = await client.saveClubGovernanceMeeting(
              clubId,
              payload,
              meetingId: meetingId,
            );
            return governanceInt(governanceMap(result['data'])['id'])!;
          },
        ),
      ),
    );
    if (savedId != null && mounted) await _openMeeting(savedId);
  }

  Future<void> _newDecision() async {
    if (!_canManage || _busy || _meeting?['status'] == 'cancelled') return;
    final id = governanceInt(_meeting?['id'])!;
    final clubId = _clubId;
    final client = _client;
    final saved = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (_) => ClubGovernanceDecisionForm(
        save: (payload) =>
            client.createClubGovernanceDecision(clubId, id, payload),
      ),
    );
    if (saved == true && mounted) await _openMeeting(id);
  }

  bool _canVote(AirmiusJson decision) =>
      _meeting?['status'] != 'cancelled' &&
      _meeting?['current_recipient_id'] != null &&
      (decision.containsKey('viewer_can_vote')
          ? decision['viewer_can_vote'] == true
          : _meeting?['viewer_voting_eligible'] == true) &&
      decision['viewer_has_voted'] != true &&
      decision['status'] == 'open' &&
      decision['correction_locked_at'] == null;

  Future<bool> _confirm(String title, String message, String action) async =>
      await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(title),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Abbrechen'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text(action),
            ),
          ],
        ),
      ) ??
      false;

  Future<void> _write(
    Future<AirmiusJson> Function() action, {
    bool vote = false,
  }) async {
    if (_busy) return;
    final id = governanceInt(_meeting?['id'])!;
    setState(() {
      _busy = true;
      _error = null;
    });
    String? failure;
    try {
      await action();
    } catch (error) {
      failure = governanceError(error);
    }
    // A failed vote request can still have reached the server. Reload its state.
    if (mounted && (failure == null || vote)) await _openMeeting(id);
    if (mounted) {
      setState(() {
        _busy = false;
        _error = failure ?? _error;
      });
      if (failure != null) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(failure)));
      }
    }
  }

  Future<void> _vote(AirmiusJson decision, String choice) async {
    if (_busy || !_canVote(decision)) return;
    if (!await _confirm(
      'Stimme abgeben',
      '${decision['title']}: ${governanceLabel(choice)}. Die Stimme kann nicht geändert werden.',
      'Stimme abgeben',
    )) {
      return;
    }
    if (!mounted || !_canVote(decision)) return;
    await _write(
      () => _client.voteClubGovernanceDecision(
        _clubId,
        governanceInt(_meeting?['id'])!,
        governanceInt(decision['id'])!,
        governanceInt(_meeting?['current_recipient_id'])!,
        choice,
      ),
      vote: true,
    );
  }

  Future<void> _close(AirmiusJson decision) async {
    if (_busy || !_canManage || decision['status'] != 'open') return;
    if (!await _confirm(
      'Abstimmung abschließen',
      'Das Ergebnis wird festgeschrieben. Weitere Stimmen sind danach ausgeschlossen.',
      'Abschließen',
    )) {
      return;
    }
    if (!mounted) return;
    await _write(
      () => _client.closeClubGovernanceDecision(
        _clubId,
        governanceInt(_meeting?['id'])!,
        governanceInt(decision['id'])!,
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Sitzungen & Beschlüsse'),
      actions: [
        IconButton(
          tooltip: 'Aktualisieren',
          onPressed: _busy || _loading ? null : _refresh,
          icon: const Icon(Icons.refresh),
        ),
        if (_canCreate && _meeting == null)
          IconButton(
            tooltip: 'Sitzung anlegen',
            onPressed: _busy || _loading
                ? null
                : () => _editMeeting(create: true),
            icon: const Icon(Icons.add),
          ),
      ],
    ),
    body: RefreshIndicator(
      onRefresh: _refresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          if (_clubs.length > 1)
            DropdownButtonFormField<int>(
              initialValue: governanceInt(_club?['id']),
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Verein'),
              items: _clubs
                  .map(
                    (club) => DropdownMenuItem(
                      value: governanceInt(club['id']),
                      child: Text(
                        '${club['name']}',
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  )
                  .toList(),
              onChanged: _busy || _loading
                  ? null
                  : (value) {
                      setState(() {
                        _club = _clubs.firstWhere(
                          (club) => governanceInt(club['id']) == value,
                        );
                        _meeting = null;
                        _meetings = [];
                      });
                      _loadList();
                    },
            )
          else if (_club != null)
            Text(
              '${_club?['name']}',
              style: Theme.of(context).textTheme.titleMedium,
            ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Semantics(
                liveRegion: true,
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            ),
          if (_busy) const LinearProgressIndicator(),
          if (_loading)
            const Padding(
              padding: EdgeInsets.all(32),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_club == null)
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text('Keine Vereine verfügbar.'),
            )
          else if (_meeting != null)
            ..._detail()
          else
            ..._list(),
        ],
      ),
    ),
  );

  List<Widget> _list() {
    final visible = _meetings
        .where(
          (item) =>
              (_status.isEmpty || item['status'] == _status) &&
              '${item['title']} ${item['location_name'] ?? ''}'
                  .toLowerCase()
                  .contains(_query.toLowerCase()),
        )
        .toList();
    return [
      const SizedBox(height: 12),
      TextField(
        decoration: const InputDecoration(
          labelText: 'Sitzungen suchen',
          prefixIcon: Icon(Icons.search),
        ),
        onChanged: (value) => setState(() => _query = value),
      ),
      const SizedBox(height: 12),
      DropdownButtonFormField<String>(
        initialValue: _status,
        decoration: const InputDecoration(labelText: 'Status'),
        items: [
          const DropdownMenuItem(value: '', child: Text('Alle Status')),
          for (final value in ['draft', 'invited', 'held', 'cancelled'])
            DropdownMenuItem(value: value, child: Text(governanceLabel(value))),
        ],
        onChanged: (value) => setState(() => _status = value ?? ''),
      ),
      const SizedBox(height: 12),
      if (visible.isEmpty)
        Text(
          _meetings.isEmpty
              ? 'Keine Sitzungen verfügbar.'
              : 'Keine passenden Sitzungen.',
        ),
      for (final item in visible)
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: Text('${item['title']}'),
          subtitle: Text(
            '${governanceLabel(item['status'])} · ${governanceDate(item['scheduled_at'])}${item['location_name'] == null ? '' : '\n${item['location_name']}'}',
          ),
          trailing: const Icon(Icons.chevron_right),
          onTap: _busy ? null : () => _openMeeting(governanceInt(item['id'])!),
        ),
    ];
  }

  Widget _heading(String text) => Padding(
    padding: const EdgeInsets.only(top: 20, bottom: 8),
    child: Text(text, style: Theme.of(context).textTheme.titleMedium),
  );

  List<Widget> _detail() {
    final item = _meeting!;
    final agenda = governanceMaps(item['agenda_items']);
    final recipients = governanceMaps(item['recipients']);
    final decisions = governanceMaps(item['decisions']);
    final versions = governanceMaps(item['versions']);
    return [
      Align(
        alignment: Alignment.centerLeft,
        child: TextButton.icon(
          onPressed: _busy ? null : _back,
          icon: const Icon(Icons.arrow_back),
          label: const Text('Übersicht'),
        ),
      ),
      Text('${item['title']}', style: Theme.of(context).textTheme.titleLarge),
      Text(
        '${governanceLabel(item['type'])} · ${governanceLabel(item['status'])}',
      ),
      Text(governanceDate(item['scheduled_at'])),
      if (item['location_name'] != null) Text('${item['location_name']}'),
      if (item['governance_body'] != null)
        Text('${governanceMap(item['governance_body'])['name']}'),
      if (_canManage)
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: _busy ? null : () => _editMeeting(),
            icon: const Icon(Icons.edit_outlined),
            label: const Text('Sitzung bearbeiten'),
          ),
        ),
      _heading('Tagesordnung'),
      if (agenda.isEmpty) const Text('Keine Tagesordnung hinterlegt.'),
      for (var i = 0; i < agenda.length; i++)
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: Text('${i + 1}. ${agenda[i]['title']}'),
          subtitle: agenda[i]['description'] == null
              ? null
              : Text('${agenda[i]['description']}'),
        ),
      _heading('Protokoll / Notizen'),
      SelectableText('${item['notes'] ?? 'Noch kein Protokoll hinterlegt.'}'),
      _heading('Beschlüsse & Wahlen'),
      if (_canManage && item['status'] != 'cancelled')
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: _busy ? null : _newDecision,
            icon: const Icon(Icons.add),
            label: const Text('Beschluss anlegen'),
          ),
        ),
      if (decisions.isEmpty) const Text('Noch keine Beschlüsse.'),
      for (final decision in decisions) _decision(decision),
      _heading('Einladungen'),
      const Text('Empfänger und Stimmrechte sind festgeschrieben.'),
      for (final recipient in recipients)
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${governanceMap(recipient['person'])['name'] ?? 'Mitglied'}',
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
              Text(
                recipient['voting_eligible'] == true
                    ? 'Stimmberechtigt'
                    : 'Nicht stimmberechtigt',
              ),
              if (recipient['response_status'] != null)
                Text('${recipient['response_status']}'),
              if (_canManage)
                DropdownButtonFormField<String>(
                  key: ValueKey(
                    '${recipient['id']}:${recipient['delivery_status']}',
                  ),
                  initialValue: '${recipient['delivery_status']}',
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Zustellstatus'),
                  items: [
                    for (final value in [
                      'pending',
                      'sent',
                      'delivered',
                      'failed',
                      'bounced',
                    ])
                      DropdownMenuItem(
                        value: value,
                        child: Text(governanceLabel(value)),
                      ),
                  ],
                  onChanged: _busy
                      ? null
                      : (value) {
                          if (value == null) return;
                          _write(
                            () => _client.updateClubGovernanceMeetingDelivery(
                              _clubId,
                              governanceInt(item['id'])!,
                              governanceInt(recipient['id'])!,
                              {'delivery_status': value},
                            ),
                          );
                        },
                )
              else
                Text(governanceLabel(recipient['delivery_status'])),
            ],
          ),
        ),
      _heading('Protokollversionen'),
      if (versions.isEmpty) const Text('Keine Versionen verfügbar.'),
      for (final version in versions)
        ExpansionTile(
          tilePadding: EdgeInsets.zero,
          title: Text(
            'Version ${version['version']} · ${governanceLabel(version['status'])}',
          ),
          subtitle: Text(
            '${governanceDate(version['created_at'])}${governanceCreator(version) == null ? '' : ' · ${governanceCreator(version)}'}',
          ),
          children: [
            for (final entry in governanceMaps(version['agenda_items']))
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('${entry['title']}'),
                subtitle: entry['description'] == null
                    ? null
                    : Text('${entry['description']}'),
              ),
            Align(
              alignment: Alignment.centerLeft,
              child: SelectableText('${version['notes'] ?? 'Keine Notizen.'}'),
            ),
          ],
        ),
    ];
  }

  Widget _decision(AirmiusJson decision) {
    final result = governanceMap(decision['result']);
    final counts = governanceMap(result['counts']);
    final options = (decision['options'] as List? ?? [])
        .whereType<String>()
        .toList();
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${decision['title']}',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            Text(
              '${governanceLabel(decision['type'])} · ${governanceLabel(decision['voting_mode'])} · ${governanceLabel(decision['majority_rule'])}',
            ),
            Text(
              '${governanceLabel(decision['status'])} · Version ${decision['meeting_version']}${decision['quorum'] == null ? '' : ' · Quorum ${decision['quorum']}%'}',
            ),
            if (decision['description'] != null)
              Text('${decision['description']}'),
            const SizedBox(height: 8),
            Text(
              '${result['votes_cast'] ?? 0} / ${decision['eligible_voters']} Stimmen · Quorum ${result['quorum_reached'] == true ? 'erreicht' : 'nicht erreicht'}',
            ),
            Wrap(
              spacing: 16,
              runSpacing: 4,
              children: [
                for (final option in options)
                  Text('${governanceLabel(option)}: ${counts[option] ?? 0}'),
              ],
            ),
            if (decision['status'] == 'closed')
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  '${governanceLabel(result['outcome'])}${result['winner'] == null ? '' : ' · ${result['winner']}'}',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ),
            if (decision['viewer_has_voted'] == true)
              const Padding(
                padding: EdgeInsets.only(top: 8),
                child: Text('Ihre Stimme wurde abgegeben.'),
              ),
            if (_canVote(decision))
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Wrap(
                  spacing: 8,
                  runSpacing: 4,
                  children: [
                    for (final option in options)
                      OutlinedButton(
                        onPressed: _busy ? null : () => _vote(decision, option),
                        child: Text(governanceLabel(option)),
                      ),
                  ],
                ),
              ),
            if (_canManage &&
                decision['status'] == 'open' &&
                decision['correction_locked_at'] == null)
              TextButton.icon(
                onPressed: _busy ? null : () => _close(decision),
                icon: const Icon(Icons.lock_outline),
                label: const Text('Abstimmung abschließen'),
              ),
          ],
        ),
      ),
    );
  }
}

String governanceDate(dynamic value) {
  final date = DateTime.tryParse('$value')?.toLocal();
  if (date == null) return 'Termin offen';
  return '${formatAirmiusDate(date)} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
}

String? governanceCreator(AirmiusJson version) =>
    version['created_by'] is String
    ? version['created_by'] as String
    : governanceMap(version['created_by'])['name'] as String?;
