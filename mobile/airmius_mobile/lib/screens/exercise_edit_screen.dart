import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/exercise_content.dart';
import '../core/exercise_content_copy.dart';
import '../core/exercise_content_upload.dart';
import '../widgets/exercise_content_editor.dart';

class ExerciseEditScreen extends StatefulWidget {
  const ExerciseEditScreen({
    super.key,
    this.exercise,
    this.clubs = const [],
    this.teams = const [],
    this.client,
  });
  final Map<String, dynamic>? exercise;
  final List<Map<String, dynamic>> clubs;
  final List<Map<String, dynamic>> teams;
  final AirmiusApiClient? client;
  @override
  State<ExerciseEditScreen> createState() => _ExerciseEditScreenState();
}

class _ExerciseEditScreenState extends State<ExerciseEditScreen> {
  ExerciseContentDraft? _draft;
  late final _sport = TextEditingController(
    text: widget.exercise?['sport_type']?.toString() ?? '',
  );
  late final _equipment = TextEditingController(
    text: (widget.exercise?['equipment'] as List? ?? []).join('\n'),
  );
  late final _muscles = TextEditingController(
    text: (widget.exercise?['muscle_groups'] as List? ?? []).join('\n'),
  );
  late final _video = TextEditingController(
    text: widget.exercise?['video_url']?.toString() ?? '',
  );
  late String _difficulty = widget.exercise?['difficulty']?.toString() ?? 'all';
  String _scope = 'personal';
  bool _dirty = false, _saving = false, _allowLeave = false;
  String? _error;
  String ec(String key) => exerciseCopy(context, key);
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _draft ??= ExerciseContentDraft(
      widget.exercise ?? {},
      defaultLocale: AirmiusScope.of(context).language.locale.languageCode,
    );
  }

  @override
  void dispose() {
    for (final controller in [_sport, _equipment, _muscles, _video]) {
      controller.dispose();
    }
    super.dispose();
  }

  void _changed() {
    if (!_dirty) setState(() => _dirty = true);
  }

  Future<void> _leave() async {
    if (_saving) return;
    final leave =
        !_dirty ||
        await showDialog<bool>(
              context: context,
              builder: (context) => AlertDialog(
                title: Text(ec('leave')),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.pop(context, false),
                    child: Text(ec('cancel')),
                  ),
                  FilledButton(
                    onPressed: () => Navigator.pop(context, true),
                    child: Text(ec('remove')),
                  ),
                ],
              ),
            ) ==
            true;
    if (leave && mounted) {
      setState(() => _allowLeave = true);
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) Navigator.pop(context);
      });
    }
  }

  Future<void> _save() async {
    if (_saving) return;
    if (_draft!.originalTexts['title']!.trim().length < 2) {
      setState(() => _error = '${ec('title')} · ≥ 2');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final services = widget.client == null
          ? AirmiusServicesScope.of(context)
          : null;
      final client =
          widget.client ??
          services!.clientForSession(services.authState.session);
      final id = widget.exercise?['id'];
      final content = _draft!.toPayload();
      final payload = <String, dynamic>{
        ...content,
        'name': content['title'],
        'sport_type': _sport.text.trim(),
        'difficulty': _difficulty,
        'video_url': _video.text.trim(),
        'equipment': _equipment.text
            .split(RegExp(r'[,\n]'))
            .map((e) => e.trim())
            .where((e) => e.isNotEmpty)
            .toList(),
        'muscle_groups': _muscles.text
            .split(RegExp(r'[,\n]'))
            .map((e) => e.trim())
            .where((e) => e.isNotEmpty)
            .toList(),
        if (id == null) ...{
          'scope': _scope.split(':').first,
          if (_scope.startsWith('club:'))
            'club_id': int.parse(_scope.split(':').last),
          if (_scope.startsWith('team:'))
            'team_id': int.parse(_scope.split(':').last),
        },
      }..remove('title');
      await saveExerciseContent(
        client: client,
        path: '/api/v1/training/exercises${id == null ? '' : '/$id'}',
        payload: payload,
        updating: id != null,
      );
      if (!mounted) return;
      setState(() {
        _allowLeave = true;
        _dirty = false;
        _saving = false;
      });
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) Navigator.pop(context, true);
      });
    } catch (error) {
      if (mounted) {
        setState(() {
          _saving = false;
          _error = error is AirmiusApiException
              ? error.userMessage
              : AirmiusScope.of(context).t('exerciseLibrary.error');
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return PopScope(
      canPop: _allowLeave || (!_dirty && !_saving),
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _leave();
      },
      child: Scaffold(
        appBar: AppBar(
          leading: IconButton(
            onPressed: _saving ? null : _leave,
            icon: const Icon(Icons.arrow_back),
            tooltip: ec('cancel'),
          ),
          title: Text(ec(widget.exercise == null ? 'create' : 'edit')),
        ),
        body: AbsorbPointer(
          absorbing: _saving,
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
              ExerciseContentEditor(draft: _draft!, onChanged: _changed),
              const SizedBox(height: 20),
              TextField(
                controller: _sport,
                onChanged: (_) => _changed(),
                decoration: InputDecoration(
                  labelText: t('exerciseLibrary.sport'),
                ),
              ),
              const SizedBox(height: 16),
              if (widget.exercise == null)
                DropdownButtonFormField<String>(
                  initialValue: _scope,
                  decoration: InputDecoration(
                    labelText: t('exerciseLibrary.scope'),
                  ),
                  items: [
                    DropdownMenuItem(
                      value: 'personal',
                      child: Text(t('exerciseLibrary.personal')),
                    ),
                    ...widget.clubs.map(
                      (club) => DropdownMenuItem(
                        value: 'club:${club['id']}',
                        child: Text(
                          '${t('exerciseLibrary.club')}: ${club['name']}',
                        ),
                      ),
                    ),
                    ...widget.teams.map(
                      (team) => DropdownMenuItem(
                        value: 'team:${team['id']}',
                        child: Text(
                          '${t('exerciseLibrary.team')}: ${team['name']}',
                        ),
                      ),
                    ),
                  ],
                  onChanged: (value) {
                    if (value != null) {
                      setState(() => _scope = value);
                      _changed();
                    }
                  },
                ),
              DropdownButtonFormField<String>(
                initialValue: _difficulty,
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
                onChanged: (value) {
                  if (value != null) {
                    setState(() => _difficulty = value);
                    _changed();
                  }
                },
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _equipment,
                maxLines: 3,
                onChanged: (_) => _changed(),
                decoration: InputDecoration(
                  labelText: t('exerciseLibrary.equipmentHint'),
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _muscles,
                maxLines: 3,
                onChanged: (_) => _changed(),
                decoration: InputDecoration(
                  labelText: t('exerciseLibrary.musclesHint'),
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _video,
                keyboardType: TextInputType.url,
                onChanged: (_) => _changed(),
                decoration: InputDecoration(
                  labelText: t('trainingHub.videoUrl'),
                ),
              ),
            ],
          ),
        ),
        bottomNavigationBar: SafeArea(
          minimum: const EdgeInsets.all(16),
          child: FilledButton.icon(
            onPressed: _saving ? null : _save,
            icon: _saving
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.save_outlined),
            label: Text(ec('save')),
          ),
        ),
      ),
    );
  }
}
