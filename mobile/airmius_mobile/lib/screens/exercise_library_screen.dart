import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

class ExerciseLibraryScreen extends StatefulWidget {
  const ExerciseLibraryScreen({super.key});

  @override
  State<ExerciseLibraryScreen> createState() => _ExerciseLibraryScreenState();
}

class _ExerciseLibraryScreenState extends State<ExerciseLibraryScreen> {
  Future<_ExerciseLibraryData>? _future;
  final _search = TextEditingController();
  bool _busy = false;

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
    _search.dispose();
    super.dispose();
  }

  Future<_ExerciseLibraryData> _load() async {
    final responses = await Future.wait([
      _client.trainingExercises(),
      _client.clubs(mine: true),
      _client.teams(),
    ]);
    return _ExerciseLibraryData(
      exercises: _mapList(responses[0]['data']),
      clubs: _mapList(responses[1]['data'])
          .where(
            (club) =>
                club['can_create_training_exercises'] == true ||
                club['can_manage_training_exercises'] == true,
          )
          .toList(),
      teams: _mapList(
        responses[2]['data'],
      ).where((team) => team['can_create_training_exercises'] == true).toList(),
    );
  }

  void _reload() {
    setState(() => _future = _load());
  }

  Future<void> _create(
    List<Map<String, dynamic>> clubs,
    List<Map<String, dynamic>> teams,
  ) async {
    final payload = await _editDialog(clubs: clubs, teams: teams);
    if (payload == null || !mounted) return;
    await _run(() => _client.createTrainingExercise(payload));
  }

  Future<void> _edit(
    Map<String, dynamic> exercise,
    List<Map<String, dynamic>> clubs,
    List<Map<String, dynamic>> teams,
  ) async {
    final payload = await _editDialog(
      exercise: exercise,
      clubs: clubs,
      teams: teams,
    );
    if (payload == null || !mounted) return;
    await _run(
      () => _client.updateTrainingExercise(_asInt(exercise['id']), payload),
    );
  }

  Future<void> _delete(Map<String, dynamic> exercise) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('exerciseLibrary.deleteTitle')),
        content: Text(t('exerciseLibrary.deleteBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('exerciseLibrary.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('exerciseLibrary.delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(() => _client.deleteTrainingExercise(_asInt(exercise['id'])));
  }

  Future<void> _addToPlan(Map<String, dynamic> exercise) async {
    final t = AirmiusScope.of(context).t;
    final plans = _mapList(
      (await _client.trainingPlans(includeItems: false))['data'],
    );
    if (!mounted) return;
    if (plans.isEmpty) {
      _notice(t('exerciseLibrary.noPlans'));
      return;
    }
    final selected = await showDialog<int>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('exerciseLibrary.addToPlan')),
        content: DropdownButtonFormField<int>(
          initialValue: _asInt(plans.first['id']),
          decoration: InputDecoration(
            labelText: t('exerciseLibrary.choosePlan'),
          ),
          items: plans
              .map(
                (plan) => DropdownMenuItem<int>(
                  value: _asInt(plan['id']),
                  child: Text(
                    _text(plan['title'], t('exerciseLibrary.unnamedPlan')),
                  ),
                ),
              )
              .toList(),
          onChanged: (value) => Navigator.pop(context, value),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(t('exerciseLibrary.cancel')),
          ),
        ],
      ),
    );
    if (selected == null || !mounted) return;
    await _run(
      () => _client.addTrainingExerciseToPlan(_asInt(exercise['id']), selected),
    );
  }

  Future<void> _run(Future<AirmiusJson> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
      if (mounted) {
        _notice(AirmiusScope.of(context).t('exerciseLibrary.saved'));
        _reload();
      }
    } catch (error) {
      if (mounted) {
        _notice(
          _safeError(
            error,
            AirmiusScope.of(context).t('exerciseLibrary.error'),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _notice(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<Map<String, dynamic>?> _editDialog({
    Map<String, dynamic>? exercise,
    required List<Map<String, dynamic>> clubs,
    required List<Map<String, dynamic>> teams,
  }) async {
    final t = AirmiusScope.of(context).t;
    final name = TextEditingController(text: _text(exercise?['name']));
    final sport = TextEditingController(text: _text(exercise?['sport_type']));
    final description = TextEditingController(
      text: _text(exercise?['description']),
    );
    final instructions = TextEditingController(
      text: _text(exercise?['instructions']),
    );
    final equipment = TextEditingController(
      text: _lines(exercise?['equipment']),
    );
    final muscles = TextEditingController(
      text: _lines(exercise?['muscle_groups']),
    );
    var difficulty = _text(exercise?['difficulty'], 'all');
    var scope = _text(exercise?['scope'], 'personal');
    int? clubId = _asNullableInt(_map(exercise?['club'])['id']);
    int? teamId = _asNullableInt(_map(exercise?['team'])['id']);

    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(
            exercise == null
                ? t('exerciseLibrary.newTitle')
                : t('exerciseLibrary.editTitle'),
          ),
          content: SizedBox(
            width: 520,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: name,
                    autofocus: true,
                    decoration: InputDecoration(
                      labelText: t('exerciseLibrary.name'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: sport,
                    decoration: InputDecoration(
                      labelText: t('exerciseLibrary.sport'),
                    ),
                  ),
                  if (exercise == null) ...[
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      initialValue: scope,
                      decoration: InputDecoration(
                        labelText: t('exerciseLibrary.scope'),
                      ),
                      items: [
                        DropdownMenuItem(
                          value: 'personal',
                          child: Text(t('exerciseLibrary.personal')),
                        ),
                        ...clubs.map(
                          (club) => DropdownMenuItem(
                            value: 'club:${_asInt(club['id'])}',
                            child: Text(
                              '${t('exerciseLibrary.club')}: ${_text(club['name'])}',
                            ),
                          ),
                        ),
                        ...teams.map(
                          (team) => DropdownMenuItem(
                            value: 'team:${_asInt(team['id'])}',
                            child: Text(
                              '${t('exerciseLibrary.team')}: ${_text(team['name'])}',
                            ),
                          ),
                        ),
                      ],
                      onChanged: (value) {
                        if (value == null) return;
                        setDialogState(() {
                          scope = value.startsWith('club:')
                              ? 'club'
                              : (value.startsWith('team:') ? 'team' : value);
                          clubId = value.startsWith('club:')
                              ? int.tryParse(value.substring(5))
                              : null;
                          teamId = value.startsWith('team:')
                              ? int.tryParse(value.substring(5))
                              : null;
                        });
                      },
                    ),
                  ],
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: difficulty,
                    decoration: InputDecoration(
                      labelText: t('exerciseLibrary.difficulty'),
                    ),
                    items: ['all', 'beginner', 'intermediate', 'advanced']
                        .map(
                          (value) => DropdownMenuItem(
                            value: value,
                            child: Text(t('exerciseLibrary.difficulty.$value')),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setDialogState(() => difficulty = value ?? difficulty),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: description,
                    maxLines: 3,
                    decoration: InputDecoration(
                      labelText: t('exerciseLibrary.description'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: instructions,
                    maxLines: 4,
                    decoration: InputDecoration(
                      labelText: t('exerciseLibrary.instructions'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: equipment,
                    maxLines: 3,
                    decoration: InputDecoration(
                      labelText: t('exerciseLibrary.equipmentHint'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: muscles,
                    maxLines: 3,
                    decoration: InputDecoration(
                      labelText: t('exerciseLibrary.musclesHint'),
                    ),
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(t('exerciseLibrary.cancel')),
            ),
            FilledButton(
              onPressed: () {
                if (name.text.trim().length < 2) return;
                Navigator.pop(context, {
                  if (exercise == null) 'scope': scope,
                  if (exercise == null && scope == 'club' && clubId != null)
                    'club_id': clubId,
                  if (exercise == null && scope == 'team' && teamId != null)
                    'team_id': teamId,
                  'name': name.text.trim(),
                  'sport_type': _nullable(sport.text),
                  'description': _nullable(description.text),
                  'instructions': _nullable(instructions.text),
                  'equipment': _list(equipment.text),
                  'muscle_groups': _list(muscles.text),
                  'difficulty': difficulty,
                });
              },
              child: Text(t('exerciseLibrary.save')),
            ),
          ],
        ),
      ),
    );
    for (final controller in [
      name,
      sport,
      description,
      instructions,
      equipment,
      muscles,
    ]) {
      controller.dispose();
    }
    return result;
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(title: Text(t('exerciseLibrary.title'))),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _busy
            ? null
            : () async {
                final data = await _future;
                if (mounted) {
                  _create(data?.clubs ?? const [], data?.teams ?? const []);
                }
              },
        icon: const Icon(Icons.add),
        label: Text(t('exerciseLibrary.new')),
      ),
      body: SafeArea(
        child: FutureBuilder<_ExerciseLibraryData>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(
                child: AirmiusButton(
                  label: t('exerciseLibrary.retry'),
                  icon: Icons.refresh,
                  onPressed: _reload,
                ),
              );
            }
            final data = snapshot.data ?? const _ExerciseLibraryData();
            final query = _search.text.trim().toLowerCase();
            final exercises = data.exercises.where((exercise) {
              if (query.isEmpty) return true;
              return [
                _text(exercise['name']),
                _text(exercise['sport_type']),
                _text(exercise['description']),
              ].join(' ').toLowerCase().contains(query);
            }).toList();
            return RefreshIndicator(
              onRefresh: () async => _reload(),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
                children: [
                  AirmiusPanel(
                    gradient: true,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          t('exerciseLibrary.title'),
                          style: Theme.of(context).textTheme.headlineSmall
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          t('exerciseLibrary.subtitle'),
                          style: Theme.of(
                            context,
                          ).textTheme.bodyMedium?.copyWith(height: 1.4),
                        ),
                        const SizedBox(height: 14),
                        TextField(
                          controller: _search,
                          onChanged: (_) => setState(() {}),
                          decoration: InputDecoration(
                            prefixIcon: const Icon(Icons.search),
                            labelText: t('exerciseLibrary.search'),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  if (exercises.isEmpty)
                    AirmiusPanel(
                      child: Center(
                        child: Padding(
                          padding: const EdgeInsets.all(22),
                          child: Text(t('exerciseLibrary.empty')),
                        ),
                      ),
                    )
                  else
                    ...exercises.map(
                      (exercise) => _ExerciseCard(
                        exercise: exercise,
                        t: t,
                        onAddToPlan: () => _addToPlan(exercise),
                        onEdit: exercise['can_edit'] == true
                            ? () => _edit(exercise, data.clubs, data.teams)
                            : null,
                        onDelete: exercise['can_delete'] == true
                            ? () => _delete(exercise)
                            : null,
                      ),
                    ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class _ExerciseCard extends StatelessWidget {
  const _ExerciseCard({
    required this.exercise,
    required this.t,
    required this.onAddToPlan,
    this.onEdit,
    this.onDelete,
  });

  final Map<String, dynamic> exercise;
  final String Function(String) t;
  final VoidCallback onAddToPlan;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final scope = _text(exercise['scope'], 'personal');
    final tags = <String>[
      if (_text(exercise['sport_type']).isNotEmpty)
        _text(exercise['sport_type']),
      t('exerciseLibrary.difficulty.${_text(exercise['difficulty'], 'all')}'),
      t('exerciseLibrary.$scope'),
    ];
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    _text(exercise['name'], t('exerciseLibrary.unnamed')),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                PopupMenuButton<String>(
                  tooltip: t('exerciseLibrary.actions'),
                  onSelected: (value) {
                    if (value == 'edit') onEdit?.call();
                    if (value == 'delete') onDelete?.call();
                  },
                  itemBuilder: (context) => [
                    if (onEdit != null)
                      PopupMenuItem(
                        value: 'edit',
                        child: Text(t('exerciseLibrary.edit')),
                      ),
                    if (onDelete != null)
                      PopupMenuItem(
                        value: 'delete',
                        child: Text(t('exerciseLibrary.delete')),
                      ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: tags
                  .map(
                    (tag) => Chip(
                      label: Text(tag),
                      visualDensity: VisualDensity.compact,
                    ),
                  )
                  .toList(),
            ),
            if (_text(exercise['description']).isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                _text(exercise['description']),
                maxLines: 4,
                overflow: TextOverflow.ellipsis,
              ),
            ],
            if (_listFrom(exercise['equipment']).isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                '${t('exerciseLibrary.equipment')}: ${_listFrom(exercise['equipment']).join(', ')}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
            const SizedBox(height: 12),
            AirmiusButton(
              label: t('exerciseLibrary.addToPlan'),
              icon: Icons.playlist_add,
              onPressed: onAddToPlan,
            ),
          ],
        ),
      ),
    );
  }
}

class _ExerciseLibraryData {
  const _ExerciseLibraryData({
    this.exercises = const [],
    this.clubs = const [],
    this.teams = const [],
  });
  final List<Map<String, dynamic>> exercises;
  final List<Map<String, dynamic>> clubs;
  final List<Map<String, dynamic>> teams;
}

List<Map<String, dynamic>> _mapList(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : const [];

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

String _text(Object? value, [String fallback = '']) =>
    value?.toString() ?? fallback;

int _asInt(Object? value) =>
    value is num ? value.toInt() : int.tryParse(_text(value)) ?? 0;

int? _asNullableInt(Object? value) => value == null ? null : _asInt(value);

String _lines(Object? value) => _listFrom(value).join('\n');

List<String> _listFrom(Object? value) => value is List
    ? value
          .map((item) => item.toString())
          .where((item) => item.trim().isNotEmpty)
          .toList()
    : const [];

List<String> _list(String value) => value
    .split(RegExp(r'[,\n]'))
    .map((item) => item.trim())
    .where((item) => item.isNotEmpty)
    .toSet()
    .toList();

String? _nullable(String value) => value.trim().isEmpty ? null : value.trim();

String _safeError(Object error, String fallback) {
  if (error is AirmiusApiException) return error.userMessage;
  final message = error.toString().replaceFirst('Exception: ', '').trim();
  return message.isEmpty || message.length > 220 ? fallback : message;
}
