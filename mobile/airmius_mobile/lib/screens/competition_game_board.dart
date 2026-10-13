import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import 'planning_widgets.dart';

class CompetitionGameBoardScreen extends StatefulWidget {
  const CompetitionGameBoardScreen({
    super.key,
    required this.client,
    required this.competitionId,
  });
  final AirmiusApiClient client;
  final int competitionId;

  @override
  State<CompetitionGameBoardScreen> createState() =>
      _CompetitionGameBoardScreenState();
}

class _CompetitionGameBoardScreenState
    extends State<CompetitionGameBoardScreen> {
  AirmiusJson data = {};
  int? selectedGameId, selectedPlayerId;
  List<AirmiusJson> entries = [];
  bool busy = false;
  String? error;

  String t(String key) => planningText(context, key);
  List<AirmiusJson> get games => planningRows(data['games']);
  AirmiusJson? get game =>
      games.where((row) => row['id'] == selectedGameId).firstOrNull;
  List<AirmiusJson> get roster => planningRows(data['roster_entries']);
  bool get canEdit =>
      data['can_edit'] == true && ['planned', 'open'].contains(data['status']);
  int get maxStarters => data['sport_type'] == 'basketball' ? 5 : 11;
  int get starters => entries.where((e) => e['role'] == 'starter').length;

  @override
  void initState() {
    super.initState();
    reload();
  }

  Future<void> reload() async {
    try {
      final loaded = planningData(
        await widget.client.planningCompetition(widget.competitionId),
      );
      if (!mounted) return;
      setState(() {
        data = loaded;
        error = null;
        if (!games.any((row) => row['id'] == selectedGameId)) {
          selectedGameId = games.isEmpty ? null : games.last['id'] as int;
        }
        selectGame(selectedGameId);
      });
    } catch (e) {
      if (mounted) setState(() => error = planningError(context, e));
    }
  }

  void selectGame(int? id) {
    selectedGameId = id;
    selectedPlayerId = null;
    final row = games.where((item) => item['id'] == id).firstOrNull;
    final lineup = row?['lineup'];
    entries = planningRows(lineup is Map ? lineup['entries'] : null)
        .map(
          (e) => <String, dynamic>{
            'competition_roster_entry_id': e['competition_roster_entry_id'],
            'role': e['role'],
            'position': e['position'],
            'shirt_number': e['shirt_number'] ?? '',
            'board_x': e['board_x'],
            'board_y': e['board_y'],
          },
        )
        .toList();
  }

  Future<void> editGame([AirmiusJson? current]) async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => PlanningEditor(
          title: current == null ? t('new') : t('edit'),
          initial: {
            'opponent': current?['opponent'] ?? '',
            'scheduled_at': planningApiTime(current?['scheduled_at']),
          },
          fields: (f) => [
            f.text('opponent', required: true, max: 120),
            f.date('scheduled_at', time: true, required: false),
          ],
          save: (v) {
            final body = <String, dynamic>{
              'opponent': v['opponent'],
              'scheduled_at': planningDate(
                v['scheduled_at'],
              )?.toUtc().toIso8601String(),
            };
            return current == null
                ? widget.client.addPlanningGame(widget.competitionId, body)
                : widget.client.updatePlanningGame(
                    widget.competitionId,
                    current['id'] as int,
                    body,
                  );
          },
        ),
      ),
    );
    if (changed == true && mounted) await reload();
  }

  void addPlayer(int id) {
    if (entries.any((e) => e['competition_roster_entry_id'] == id)) return;
    setState(() {
      entries.add({
        'competition_roster_entry_id': id,
        'role': 'substitute',
        'position': null,
        'shirt_number': '',
        'board_x': null,
        'board_y': null,
      });
      selectedPlayerId = id;
    });
  }

  void place(AirmiusJson entry, double x, double y) {
    if (!canEdit || entry['role'] != 'starter' && starters >= maxStarters) {
      return;
    }
    setState(() {
      entry['role'] = 'starter';
      entry['board_x'] = x.round().clamp(5, 95);
      entry['board_y'] = y.round().clamp(7, 93);
      selectedPlayerId = null;
    });
  }

  String name(AirmiusJson entry) =>
      '${roster.where((r) => r['id'] == entry['competition_roster_entry_id']).firstOrNull?['display_name'] ?? ''}';

  Future<void> save() async {
    if (busy || !canEdit) return;
    final numbers = entries
        .map((e) => '${int.tryParse('${e['shirt_number']}') ?? -1}')
        .toList();
    if (entries.isEmpty ||
        entries.any(
          (e) => !RegExp(r'^[0-9]{1,3}$').hasMatch('${e['shirt_number']}'),
        ) ||
        numbers.toSet().length != numbers.length) {
      setState(() => error = '${t('shirt_number')}: ${t('invalidNumber')}');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.client.savePlanningGameLineup(
        widget.competitionId,
        selectedGameId!,
        {'entries': entries},
      );
      await reload();
    } catch (e) {
      if (mounted) setState(() => error = planningError(context, e));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final available = roster
        .where(
          (r) =>
              (data['eligible_roster_ids'] as List? ?? []).contains(r['id']) &&
              !entries.any((e) => e['competition_roster_entry_id'] == r['id']),
        )
        .toList();
    return Scaffold(
      appBar: AppBar(
        title: Text(t('games')),
        actions: [
          IconButton(
            tooltip: t('refresh'),
            onPressed: busy ? null : reload,
            icon: const Icon(Icons.refresh),
          ),
          if (canEdit)
            IconButton(
              tooltip: t('new'),
              onPressed: busy ? null : editGame,
              icon: const Icon(Icons.add),
            ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (error != null) PlanningError(error!),
          if (busy) const LinearProgressIndicator(),
          if (games.isEmpty) Text(t('empty')),
          if (games.isNotEmpty)
            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<int>(
                    key: ValueKey(selectedGameId),
                    initialValue: selectedGameId,
                    isExpanded: true,
                    decoration: InputDecoration(labelText: t('games')),
                    items: [
                      for (final row in games)
                        DropdownMenuItem(
                          value: row['id'] as int,
                          child: Text(
                            '${row['opponent']} · ${planningTime(row['scheduled_at'])}',
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                    ],
                    onChanged: busy
                        ? null
                        : (id) => setState(() => selectGame(id)),
                  ),
                ),
                if (canEdit && game != null)
                  IconButton(
                    tooltip: t('edit'),
                    onPressed: busy ? null : () => editGame(game),
                    icon: const Icon(Icons.edit_outlined),
                  ),
              ],
            ),
          if (game != null) ...[
            const SizedBox(height: 16),
            Text(
              '${t('field')} $starters/$maxStarters',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            if (canEdit)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: DropdownButtonFormField<int>(
                  key: ValueKey('add:${entries.length}'),
                  initialValue: null,
                  isExpanded: true,
                  decoration: InputDecoration(labelText: t('addPlayer')),
                  items: [
                    for (final r in available)
                      DropdownMenuItem(
                        value: r['id'] as int,
                        child: Text('${r['display_name']}'),
                      ),
                  ],
                  onChanged: (id) {
                    if (id != null) addPlayer(id);
                  },
                ),
              ),
            AspectRatio(
              aspectRatio: 3 / 4,
              child: LayoutBuilder(
                builder: (context, limits) {
                  final width = limits.maxWidth, height = limits.maxHeight;
                  return GestureDetector(
                    onTapUp: !canEdit || selectedPlayerId == null
                        ? null
                        : (details) {
                            final row = entries
                                .where(
                                  (e) =>
                                      e['competition_roster_entry_id'] ==
                                      selectedPlayerId,
                                )
                                .firstOrNull;
                            if (row != null) {
                              place(
                                row,
                                details.localPosition.dx / width * 100,
                                details.localPosition.dy / height * 100,
                              );
                            }
                          },
                    child: Container(
                      decoration: BoxDecoration(
                        color: data['sport_type'] == 'basketball'
                            ? const Color(0xff9c643a)
                            : const Color(0xff176143),
                        border: Border.all(color: Colors.white70, width: 2),
                      ),
                      child: Stack(
                        children: [
                          Positioned.fill(
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Container(
                                decoration: BoxDecoration(
                                  border: Border.all(
                                    color: Colors.white54,
                                    width: 2,
                                  ),
                                ),
                              ),
                            ),
                          ),
                          Positioned(
                            top: height / 2,
                            left: 12,
                            right: 12,
                            child: const Divider(
                              color: Colors.white54,
                              thickness: 2,
                            ),
                          ),
                          for (final entry in entries.where(
                            (e) =>
                                e['role'] == 'starter' &&
                                e['board_x'] != null &&
                                e['board_y'] != null,
                          ))
                            Positioned(
                              left:
                                  width * (entry['board_x'] as num) / 100 - 24,
                              top:
                                  height * (entry['board_y'] as num) / 100 - 24,
                              child: GestureDetector(
                                onPanUpdate: !canEdit
                                    ? null
                                    : (details) => place(
                                        entry,
                                        (entry['board_x'] as num) +
                                            details.delta.dx / width * 100,
                                        (entry['board_y'] as num) +
                                            details.delta.dy / height * 100,
                                      ),
                                child: Tooltip(
                                  message: name(entry),
                                  child: CircleAvatar(
                                    radius: 24,
                                    backgroundColor: Colors.blue.shade700,
                                    foregroundColor: Colors.white,
                                    child: Text(
                                      '${entry['shirt_number']}'.isEmpty
                                          ? '?'
                                          : '${entry['shirt_number']}',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
            if (canEdit)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text(t('placePlayer')),
              ),
            for (final entry in entries)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 5),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(name(entry), overflow: TextOverflow.ellipsis),
                    ),
                    SizedBox(
                      width: 64,
                      child: Semantics(
                        label: '${t('shirt_number')}: ${name(entry)}',
                        child: TextFormField(
                          key: ValueKey(
                            'number:${entry['competition_roster_entry_id']}',
                          ),
                          initialValue: '${entry['shirt_number']}',
                          enabled: canEdit && !busy,
                          keyboardType: TextInputType.number,
                          maxLength: 3,
                          decoration: const InputDecoration(
                            hintText: '#',
                            counterText: '',
                          ),
                          onChanged: (v) => entry['shirt_number'] = v,
                        ),
                      ),
                    ),
                    IconButton(
                      tooltip: entry['role'] == 'starter'
                          ? t('bench')
                          : t('field'),
                      onPressed: !canEdit || busy
                          ? null
                          : () => setState(() {
                              if (entry['role'] == 'starter') {
                                entry['role'] = 'substitute';
                                entry['board_x'] = null;
                                entry['board_y'] = null;
                              } else {
                                selectedPlayerId =
                                    entry['competition_roster_entry_id'] as int;
                              }
                            }),
                      icon: Icon(
                        entry['role'] == 'starter'
                            ? Icons.move_down
                            : Icons.touch_app_outlined,
                      ),
                    ),
                    if (canEdit)
                      IconButton(
                        tooltip: t('delete'),
                        onPressed: busy
                            ? null
                            : () => setState(() => entries.remove(entry)),
                        icon: const Icon(Icons.close),
                      ),
                  ],
                ),
              ),
            if (canEdit)
              Padding(
                padding: const EdgeInsets.only(top: 16),
                child: FilledButton.icon(
                  onPressed: busy || entries.isEmpty ? null : save,
                  icon: const Icon(Icons.save_outlined),
                  label: Text(t('save')),
                ),
              ),
          ],
        ],
      ),
    );
  }
}
