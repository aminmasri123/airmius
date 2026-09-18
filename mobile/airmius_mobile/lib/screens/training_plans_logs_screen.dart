import 'dart:async';
import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_training_draft_store.dart';
import '../core/training_count_labels.dart';
import '../widgets/airmius_widgets.dart';
import 'exercise_library_screen.dart';
import 'free_run_screen.dart';
import 'training_progress_screen.dart';
import 'training_availability_screen.dart';
import 'training_plan_templates_screen.dart';
import 'sport_map_center_screen.dart';

class _TrainingOption {
  const _TrainingOption(this.key, this.labelKey, this.canonicalLabel);

  final String key;
  final String labelKey;
  final String canonicalLabel;

  String label(String Function(String) t) {
    final translated = t(labelKey);
    return translated == labelKey ? canonicalLabel : translated;
  }
}

const List<_TrainingOption> _trainingSessionBlocks = [
  _TrainingOption('warmup', 'trainingHub.sessionBlock.warmup', 'Warm-up'),
  _TrainingOption('main', 'trainingHub.sessionBlock.main', 'Hauptteil'),
  _TrainingOption('technique', 'trainingHub.sessionBlock.technique', 'Technik'),
  _TrainingOption('strength', 'trainingHub.sessionBlock.strength', 'Kraft'),
  _TrainingOption(
    'endurance',
    'trainingHub.sessionBlock.endurance',
    'Ausdauer',
  ),
  _TrainingOption('speed', 'trainingHub.sessionBlock.speed', 'Schnelligkeit'),
  _TrainingOption('mobility', 'trainingHub.sessionBlock.mobility', 'Mobility'),
  _TrainingOption('cooldown', 'trainingHub.sessionBlock.cooldown', 'Cool-down'),
];

const List<_TrainingOption> _trainingGoals = [
  _TrainingOption('technique', 'trainingHub.trainingGoal.technique', 'Technik'),
  _TrainingOption('strength', 'trainingHub.trainingGoal.strength', 'Kraft'),
  _TrainingOption(
    'endurance',
    'trainingHub.trainingGoal.endurance',
    'Ausdauer',
  ),
  _TrainingOption('speed', 'trainingHub.trainingGoal.speed', 'Schnelligkeit'),
  _TrainingOption(
    'mobility',
    'trainingHub.trainingGoal.mobility',
    'Beweglichkeit',
  ),
  _TrainingOption(
    'coordination',
    'trainingHub.trainingGoal.coordination',
    'Koordination',
  ),
  _TrainingOption('tactics', 'trainingHub.trainingGoal.tactics', 'Taktik'),
  _TrainingOption(
    'recovery',
    'trainingHub.trainingGoal.recovery',
    'Regeneration',
  ),
];

const List<_TrainingOption> _equipmentPresets = [
  _TrainingOption('none', 'trainingHub.equipment.none', 'kein Equipment'),
  _TrainingOption(
    'ball_cones',
    'trainingHub.equipment.ballCones',
    'Ball, Hütchen, Markierungen',
  ),
  _TrainingOption('mat', 'trainingHub.equipment.mat', 'Matte'),
  _TrainingOption(
    'bodyweight',
    'trainingHub.equipment.bodyweight',
    'Körpergewicht',
  ),
  _TrainingOption(
    'dumbbells',
    'trainingHub.equipment.dumbbells',
    'Kurzhanteln',
  ),
  _TrainingOption('gym', 'trainingHub.equipment.gym', 'Fitnessstudio'),
  _TrainingOption(
    'pool',
    'trainingHub.equipment.pool',
    'Schwimmbahn, Pull Buoy optional',
  ),
  _TrainingOption(
    'bike',
    'trainingHub.equipment.bike',
    'Fahrrad, Helm, Uhr optional',
  ),
  _TrainingOption(
    'racket',
    'trainingHub.equipment.racket',
    'Schläger, Bälle, Markierungen',
  ),
];

class TrainingPlansLogsScreen extends StatefulWidget {
  const TrainingPlansLogsScreen({
    super.key,
    this.initialTab = 0,
    this.createLogOnOpen = false,
    this.initialLogTitle,
    this.initialLogNotes,
    this.initialSportRouteId,
    this.initialSportRouteTitle,
  });

  final int initialTab;
  final bool createLogOnOpen;
  final String? initialLogTitle;
  final String? initialLogNotes;
  final int? initialSportRouteId;
  final String? initialSportRouteTitle;

  @override
  State<TrainingPlansLogsScreen> createState() =>
      _TrainingPlansLogsScreenState();
}

class _TrainingPlansLogsScreenState extends State<TrainingPlansLogsScreen> {
  Future<_TrainingData>? _future;
  int _tab = 0;
  bool _busy = false;
  bool _canManagePlans = false;
  bool _initialComposerOpened = false;

  @override
  void initState() {
    super.initState();
    _tab = widget.initialTab == 1 ? 1 : 0;
  }

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
    if (widget.createLogOnOpen && !_initialComposerOpened) {
      _initialComposerOpened = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _createLog();
      });
    }
  }

  Future<_TrainingData> _load() async {
    final responses = await Future.wait([
      _client.trainingPlans(),
      _client.trainingLogs(),
    ]);
    final capabilities = responses[0]['capabilities'];
    final canManagePlans =
        capabilities is Map &&
        capabilities['can_manage_training_plans'] == true;
    if (mounted && _canManagePlans != canManagePlans) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _canManagePlans != canManagePlans) {
          setState(() => _canManagePlans = canManagePlans);
        }
      });
    }
    return _TrainingData(
      plans: _dataList(responses[0]).map(_TrainingPlan.fromJson).toList(),
      logs: _dataList(responses[1]).map(_TrainingLog.fromJson).toList(),
      canManagePlans: canManagePlans,
    );
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  Future<void> _startFreeRun() async {
    final result = await Navigator.of(context).push<FreeRunResult>(
      MaterialPageRoute(builder: (_) => const FreeRunScreen()),
    );
    if (result == null || !mounted) return;
    setState(() {
      _tab = 1;
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(t('trainingHub.title')),
        actions: [
          IconButton(
            tooltip: t('trainingProgress.title'),
            icon: const Icon(Icons.insights_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const TrainingProgressScreen()),
            ),
          ),
          IconButton(
            tooltip: t('exerciseLibrary.title'),
            icon: const Icon(Icons.fitness_center_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const ExerciseLibraryScreen()),
            ),
          ),
          IconButton(
            tooltip: t('trainingAvailability.title'),
            icon: const Icon(Icons.health_and_safety_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => const TrainingAvailabilityScreen(),
              ),
            ),
          ),
          IconButton(
            tooltip: t('trainingHub.templates'),
            icon: const Icon(Icons.bookmarks_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => const TrainingPlanTemplatesScreen(),
              ),
            ),
          ),
        ],
      ),
      floatingActionButton: (_tab == 1 || _canManagePlans)
          ? FloatingActionButton.extended(
              onPressed: _busy ? null : (_tab == 0 ? _createPlan : _createLog),
              icon: const Icon(Icons.add),
              label: Text(
                t(_tab == 0 ? 'trainingHub.addPlan' : 'trainingHub.addLog'),
              ),
            )
          : null,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async {
            _reload();
            await _future;
          },
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
                      t('trainingHub.title'),
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      t('trainingHub.subtitle'),
                      style: Theme.of(
                        context,
                      ).textTheme.bodyMedium?.copyWith(height: 1.4),
                    ),
                    const SizedBox(height: 14),
                    SegmentedButton<int>(
                      segments: [
                        ButtonSegment(
                          value: 0,
                          icon: const Icon(Icons.calendar_month_outlined),
                          label: Text(t('workout.plannedTab')),
                        ),
                        ButtonSegment(
                          value: 1,
                          icon: const Icon(Icons.fact_check_outlined),
                          label: Text(t('workout.completedTab')),
                        ),
                      ],
                      selected: {_tab},
                      onSelectionChanged: (value) =>
                          setState(() => _tab = value.first),
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      height: 52,
                      child: FilledButton.tonalIcon(
                        onPressed: _startFreeRun,
                        icon: const Icon(Icons.directions_run),
                        label: Text(t('freeRun.startAction')),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              FutureBuilder<_TrainingData>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const AirmiusPanel(
                      child: Center(
                        child: Padding(
                          padding: EdgeInsets.all(24),
                          child: CircularProgressIndicator(),
                        ),
                      ),
                    );
                  }
                  if (snapshot.hasError) {
                    return AirmiusPanel(
                      child: Column(
                        children: [
                          const Icon(
                            Icons.cloud_off_outlined,
                            color: AirmiusColors.red,
                            size: 42,
                          ),
                          const SizedBox(height: 10),
                          Text(t('trainingHub.loadError')),
                          const SizedBox(height: 12),
                          AirmiusButton(
                            label: t('trainingHub.retry'),
                            icon: Icons.refresh,
                            onPressed: _reload,
                          ),
                        ],
                      ),
                    );
                  }

                  final data = snapshot.data ?? const _TrainingData();
                  return _tab == 0
                      ? _planList(
                          data.plans,
                          t,
                          canManagePlans: data.canManagePlans,
                        )
                      : _logList(data.logs, t);
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _planList(
    List<_TrainingPlan> plans,
    String Function(String) t, {
    required bool canManagePlans,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (canManagePlans) ...[
          AirmiusButton(
            label: t('trainingHub.aiPlan'),
            icon: Icons.auto_awesome_outlined,
            secondary: true,
            onPressed: _busy ? null : _createAiPlan,
          ),
          const SizedBox(height: 12),
        ],
        if (plans.isEmpty)
          canManagePlans
              ? _EmptyTrainingState(
                  icon: Icons.calendar_month_outlined,
                  text: t('trainingHub.emptyPlans'),
                  action: t('trainingHub.addPlan'),
                  onAction: _createPlan,
                )
              : AirmiusPanel(
                  child: Column(
                    children: [
                      const Icon(
                        Icons.lock_outline,
                        size: 48,
                        color: AirmiusColors.blue,
                      ),
                      const SizedBox(height: 12),
                      Text(
                        t('trainingHub.planPermission'),
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ),
                ),
        for (final plan in plans) ...[
          AirmiusPanel(
            child: ListTile(
              contentPadding: EdgeInsets.zero,
              leading: IconBadge(
                icon: Icons.calendar_month_outlined,
                color: plan.status == 'published'
                    ? AirmiusColors.green
                    : AirmiusColors.amber,
              ),
              title: Text(
                plan.title,
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    [
                      _translatedStatus(t, plan.status),
                      _translatedCadence(t, plan.cadence),
                      if (plan.items.isNotEmpty)
                        '${plan.items.length} ${t(plan.items.length == 1 ? 'trainingHub.itemSingular' : 'trainingHub.items')}',
                    ].join(' · '),
                  ),
                  if (plan.items.isNotEmpty &&
                      _hasTrainingStructure(plan.items.first.metrics)) ...[
                    const SizedBox(height: 7),
                    _TrainingStructurePills(metrics: plan.items.first.metrics),
                  ],
                ],
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => _openPlan(plan.id),
            ),
          ),
          const SizedBox(height: 10),
        ],
      ],
    );
  }

  Widget _logList(List<_TrainingLog> logs, String Function(String) t) {
    if (logs.isEmpty) {
      return _EmptyTrainingState(
        icon: Icons.fact_check_outlined,
        text: t('trainingHub.emptyLogs'),
        action: t('trainingHub.addLog'),
        onAction: _createLog,
      );
    }
    return Column(
      children: [
        for (final log in logs) ...[
          AirmiusPanel(
            child: ListTile(
              contentPadding: EdgeInsets.zero,
              leading: IconBadge(
                icon: Icons.directions_run_outlined,
                color: airmiusAccentColor(context),
              ),
              title: Text(
                log.title,
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
              subtitle: Text(
                [
                  _shortDate(log.performedAt),
                  if (log.durationMinutes != null)
                    '${log.durationMinutes} ${t('trainingHub.minutes')}',
                  if (log.distanceMeters != null)
                    '${(log.distanceMeters! / 1000).toStringAsFixed(1)} km',
                  if (log.entries.isNotEmpty)
                    '${_workoutExercisesFromEntries(log.entries).length} ${t(_workoutExercisesFromEntries(log.entries).length == 1 ? 'workout.exerciseSingular' : 'workout.exercises')}',
                ].join(' · '),
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => _openLog(log.id),
            ),
          ),
          const SizedBox(height: 10),
        ],
      ],
    );
  }

  Future<void> _createPlan() async {
    final payload = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(builder: (_) => const _PlanFormPage()),
    );
    if (payload == null) return;
    await _run(() => _client.createTrainingPlan(payload));
  }

  Future<void> _createAiPlan() async {
    final aiSavedMessage = AirmiusScope.of(context).t('trainingHub.aiSaved');
    final input = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => const _AiPlanFormDialog(),
    );
    if (input == null || !mounted) return;
    setState(() => _busy = true);
    try {
      final previewResponse = await _client.previewAiTrainingPlan(input);
      final plan = _singleDataValue(previewResponse['plan']);
      if (!mounted || plan.isEmpty) return;
      final save = await showDialog<bool>(
        context: context,
        builder: (_) => _AiPlanPreviewDialog(plan: plan),
      );
      if (save != true || !mounted) return;
      await _client.saveAiTrainingPlan({
        'plan': plan,
        'starts_on': _dateApi(DateTime.now()),
        'status': 'draft',
        'share_permission': 'read',
        'user_ids': <int>[],
        'accepted_ai_safety': true,
      });
      _message(aiSavedMessage);
      _reload();
    } on AirmiusApiException catch (error) {
      _message(error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _createLog() async {
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _LogFormDialog(
        prefillTitle: widget.initialLogTitle,
        prefillNotes: widget.initialLogNotes,
        prefillSportRouteId: widget.initialSportRouteId,
        prefillSportRouteTitle: widget.initialSportRouteTitle,
      ),
    );
    if (payload == null) return;
    await _run(() => _client.createTrainingLog(payload));
  }

  Future<void> _openPlan(int id) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => TrainingPlanApiDetailScreen(planId: id),
      ),
    );
    if (mounted) _reload();
  }

  Future<void> _openLog(int id) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => TrainingLogApiDetailScreen(logId: id),
      ),
    );
    if (mounted) _reload();
  }

  Future<void> _run(Future<Map<String, dynamic>> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
      _reload();
    } on AirmiusApiException catch (error) {
      _message(error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _message(String value) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(value)));
  }
}

class TrainingPlanApiDetailScreen extends StatefulWidget {
  const TrainingPlanApiDetailScreen({super.key, required this.planId});

  final int planId;

  @override
  State<TrainingPlanApiDetailScreen> createState() =>
      _TrainingPlanApiDetailScreenState();
}

class _TrainingPlanApiDetailScreenState
    extends State<TrainingPlanApiDetailScreen> {
  Future<_TrainingPlan>? _future;
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

  Future<_TrainingPlan> _load() async => _TrainingPlan.fromJson(
    _singleData(await _client.trainingPlan(widget.planId)),
  );

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  Future<void> _showPlanActions(_TrainingPlan plan) async {
    final t = AirmiusScope.of(context).t;
    final action = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(24, 4, 24, 10),
                child: Text(
                  t('trainingHub.actions'),
                  style: Theme.of(
                    sheetContext,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
              ),
              if (plan.canWrite)
                ListTile(
                  leading: const Icon(Icons.edit_calendar_outlined),
                  title: Text(t('trainingHub.editPlan')),
                  onTap: () => Navigator.pop(sheetContext, 'edit'),
                ),
              if (plan.canWrite && !plan.isTemplate)
                ListTile(
                  leading: const Icon(Icons.send_outlined),
                  title: Text(
                    t(
                      plan.assignmentsCount > 0
                          ? 'trainingHub.manageRecipients'
                          : 'trainingHub.sendToAthletes',
                    ),
                  ),
                  onTap: () => Navigator.pop(sheetContext, 'send'),
                ),
              if (plan.canWrite && plan.status != 'published')
                ListTile(
                  leading: const Icon(Icons.publish_outlined),
                  title: Text(t('trainingHub.publish')),
                  onTap: () => Navigator.pop(sheetContext, 'publish'),
                ),
              if (plan.canWrite)
                ListTile(
                  leading: const Icon(Icons.content_copy_outlined),
                  title: Text(t('trainingHub.duplicatePlan')),
                  onTap: () => Navigator.pop(sheetContext, 'duplicate'),
                ),
              if (plan.canWrite)
                ListTile(
                  leading: const Icon(Icons.bookmark_add_outlined),
                  title: Text(t('trainingHub.saveAsTemplate')),
                  onTap: () => Navigator.pop(sheetContext, 'template'),
                ),
              if (plan.canDelete) ...[
                const Divider(),
                ListTile(
                  iconColor: AirmiusColors.red,
                  textColor: AirmiusColors.red,
                  leading: const Icon(Icons.delete_forever_outlined),
                  title: Text(t('trainingHub.deletePlan')),
                  onTap: () => Navigator.pop(sheetContext, 'delete'),
                ),
              ],
            ],
          ),
        ),
      ),
    );

    if (!mounted || action == null) return;
    switch (action) {
      case 'edit':
        await _editPlan(plan);
        return;
      case 'send':
        await _sendPlan(plan);
        return;
      case 'publish':
        await _publishPlan();
        return;
      case 'duplicate':
        await _duplicatePlan();
        return;
      case 'template':
        await _saveAsTemplate();
        return;
      case 'delete':
        await _deletePlan();
        return;
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(title: Text(t('trainingHub.planDetail'))),
      body: SafeArea(
        child: FutureBuilder<_TrainingPlan>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError || !snapshot.hasData) {
              return Center(
                child: AirmiusButton(
                  label: t('trainingHub.retry'),
                  icon: Icons.refresh,
                  onPressed: _reload,
                ),
              );
            }
            final plan = snapshot.data!;
            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(
                              plan.title,
                              style: Theme.of(context).textTheme.headlineSmall
                                  ?.copyWith(fontWeight: FontWeight.w900),
                            ),
                          ),
                          if (plan.canWrite || plan.canDelete) ...[
                            const SizedBox(width: 12),
                            OutlinedButton.icon(
                              onPressed: _busy
                                  ? null
                                  : () => _showPlanActions(plan),
                              icon: const Icon(Icons.more_horiz),
                              label: Text(t('trainingHub.actions')),
                            ),
                          ],
                        ],
                      ),
                      if (plan.description?.isNotEmpty == true) ...[
                        const SizedBox(height: 8),
                        Text(plan.description!),
                      ],
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          StatusPill(_translatedStatus(t, plan.status)),
                          StatusPill(_translatedCadence(t, plan.cadence)),
                          StatusPill(
                            '${plan.assignmentsCount} ${t('trainingHub.assignments')}',
                          ),
                        ],
                      ),
                      if (plan.assignedAudienceNames.isNotEmpty) ...[
                        const SizedBox(height: 10),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Icon(
                              Icons.people_outline,
                              size: 18,
                              color: Theme.of(context).colorScheme.primary,
                            ),
                            const SizedBox(width: 7),
                            Expanded(
                              child: Text(
                                '${t('trainingHub.recipients')}: ${plan.assignedAudienceNames.join(', ')}',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
                if (!plan.isTemplate &&
                    plan.isTemplateCopy &&
                    plan.assignmentsCount == 0) ...[
                  const SizedBox(height: 14),
                  AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            IconBadge(
                              icon: Icons.tune_outlined,
                              color: Theme.of(context).colorScheme.primary,
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    t('trainingHub.personalizeTemplateTitle'),
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium
                                        ?.copyWith(fontWeight: FontWeight.w900),
                                  ),
                                  const SizedBox(height: 5),
                                  Text(
                                    t('trainingHub.personalizeTemplateHint'),
                                    style: TextStyle(
                                      color: airmiusMutedColor(context),
                                      height: 1.35,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        _TemplateWorkflowSteps(
                          currentStep: 0,
                          labels: [
                            t('trainingHub.workflow.adjust'),
                            t('trainingHub.workflow.reviewUnits'),
                            t('trainingHub.workflow.send'),
                          ],
                        ),
                        const SizedBox(height: 14),
                        Wrap(
                          spacing: 10,
                          runSpacing: 10,
                          children: [
                            OutlinedButton.icon(
                              onPressed: _busy ? null : () => _editPlan(plan),
                              icon: const Icon(Icons.tune_outlined),
                              label: Text(t('trainingHub.adjustPlan')),
                            ),
                            FilledButton.icon(
                              onPressed: _busy ? null : () => _sendPlan(plan),
                              icon: const Icon(Icons.send_outlined),
                              label: Text(t('trainingHub.sendToAthletes')),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                AirmiusPanel(
                  title: t('trainingHub.items'),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (plan.items.isEmpty)
                        Text(t('trainingHub.emptyItems'))
                      else
                        for (final item in plan.items)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading:
                                resolveAirmiusImageUrl(item.imageUrl) != null
                                ? ClipRRect(
                                    borderRadius: BorderRadius.circular(10),
                                    child: Image.network(
                                      resolveAirmiusImageUrl(item.imageUrl)!,
                                      width: 48,
                                      height: 48,
                                      fit: BoxFit.cover,
                                      errorBuilder: (_, _, _) => const SizedBox(
                                        width: 48,
                                        height: 48,
                                        child: Icon(
                                          Icons.fitness_center_outlined,
                                        ),
                                      ),
                                    ),
                                  )
                                : const SizedBox(
                                    width: 48,
                                    child: Icon(Icons.fitness_center_outlined),
                                  ),
                            title: Text(
                              item.title,
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            subtitle: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  [
                                    if (item.durationMinutes != null)
                                      '${item.durationMinutes} ${t('trainingHub.minutes')}',
                                    if (item.distanceMeters != null)
                                      '${(item.distanceMeters! / 1000).toStringAsFixed(1)} km',
                                    if (item.intensity?.isNotEmpty == true)
                                      item.intensity!,
                                    if (item.sportRoute != null)
                                      '${t('trainingHub.route')}: ${item.sportRoute!.title}',
                                  ].join(' · '),
                                ),
                                if (_hasTrainingStructure(item.metrics)) ...[
                                  const SizedBox(height: 7),
                                  _TrainingStructurePills(
                                    metrics: item.metrics,
                                  ),
                                ],
                                if (item.plannedExercises.isNotEmpty) ...[
                                  const SizedBox(height: 7),
                                  Text(
                                    trainingPlanCountLabel(
                                      t,
                                      exercises: item.plannedExercises.length,
                                      sets: _workoutEntryCount(
                                        item.plannedExercises,
                                      ),
                                    ),
                                    style: TextStyle(
                                      color: Theme.of(
                                        context,
                                      ).colorScheme.primary,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                            onTap: plan.canWrite && !_busy
                                ? () => _editItem(item)
                                : null,
                            trailing: PopupMenuButton<String>(
                              enabled: !_busy,
                              tooltip: t('trainingHub.actions'),
                              onSelected: (action) {
                                if (action == 'route' &&
                                    item.sportRoute != null) {
                                  Navigator.of(context).push(
                                    MaterialPageRoute<void>(
                                      builder: (_) => SportMapCenterScreen(
                                        initialRouteId: item.sportRoute!.id,
                                      ),
                                    ),
                                  );
                                } else if (action == 'start') {
                                  _startItem(item);
                                } else if (action == 'edit') {
                                  _editItem(item);
                                } else if (action == 'duplicate') {
                                  _duplicateItem(item.id);
                                } else if (action == 'missed') {
                                  _markMissed(item.id);
                                } else if (action == 'delete') {
                                  _deleteItem(item.id);
                                }
                              },
                              itemBuilder: (_) => [
                                PopupMenuItem(
                                  value: 'start',
                                  child: Text(t('workout.startSession')),
                                ),
                                if (item.sportRoute != null)
                                  PopupMenuItem(
                                    value: 'route',
                                    child: Text(t('trainingHub.openRoute')),
                                  ),
                                if (plan.canWrite)
                                  PopupMenuItem(
                                    value: 'edit',
                                    child: Text(t('common.edit')),
                                  ),
                                if (plan.canWrite)
                                  PopupMenuItem(
                                    value: 'duplicate',
                                    child: Text(t('trainingHub.duplicate')),
                                  ),
                                PopupMenuItem(
                                  value: 'missed',
                                  child: Text(t('trainingHub.markMissed')),
                                ),
                                if (plan.canWrite)
                                  PopupMenuItem(
                                    value: 'delete',
                                    child: Text(t('trainingHub.delete')),
                                  ),
                              ],
                            ),
                          ),
                      if (plan.canWrite) ...[
                        const SizedBox(height: 10),
                        AirmiusButton(
                          label: t('trainingHub.addItem'),
                          icon: Icons.add,
                          onPressed: _busy ? null : _addItem,
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  Future<void> _addItem() async {
    final payload = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(builder: (_) => const _PlanItemFormPage()),
    );
    if (payload == null) return;
    final image = payload.remove('_image_file') as PlatformFile?;
    await _run(
      () => image == null
          ? _client.createTrainingPlanItem(widget.planId, payload)
          : _savePlanItemWithImage(payload, image),
    );
  }

  Future<void> _startItem(_TrainingPlanItem item) async {
    final userId = AirmiusServicesScope.of(context).authState.user?.id ?? 0;
    final payload = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (_) => _LiveWorkoutScreen(
          item: item,
          exercises: _workoutExercisesFromPlanItem(item),
          userId: userId,
        ),
      ),
    );
    if (payload == null || !mounted) return;
    setState(() => _busy = true);
    try {
      final response = await _client.createTrainingLog(payload);
      await AirmiusTrainingDraftStore().clear(
        userId: userId,
        planItemId: item.id,
      );
      final logId = _asInt(_singleData(response)['id']);
      if (!mounted) return;
      if (logId > 0) {
        await Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => TrainingLogApiDetailScreen(logId: logId),
          ),
        );
      }
      _reload();
    } on AirmiusApiException catch (error) {
      _message(error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _editPlan(_TrainingPlan plan) async {
    final payload = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(builder: (_) => _PlanFormPage(initial: plan)),
    );
    if (payload == null) return;
    await _run(() => _client.updateTrainingPlan(widget.planId, payload));
  }

  Future<void> _sendPlan(_TrainingPlan plan) async {
    final payload = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (_) =>
            _PlanFormPage(initial: plan, initialStep: 2, publishOnSave: true),
      ),
    );
    if (payload == null) return;
    final sent = await _run(
      () => _client.updateTrainingPlan(widget.planId, payload),
    );
    if (sent && mounted) {
      _message(AirmiusScope.of(context).t('trainingHub.planSent'));
    }
  }

  Future<void> _editItem(_TrainingPlanItem item) async {
    final payload = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(builder: (_) => _PlanItemFormPage(initial: item)),
    );
    if (payload == null) return;
    final image = payload.remove('_image_file') as PlatformFile?;
    await _run(
      () => image == null
          ? _client.updateTrainingPlanItem(widget.planId, item.id, payload)
          : _savePlanItemWithImage(payload, image, itemId: item.id),
    );
  }

  Uri _apiUri(String path) {
    final base = Uri.parse(
      AirmiusServicesScope.of(context).environment.apiBaseUrl,
    );
    final prefix = base.path.endsWith('/') ? base.path : '${base.path}/';
    return base.replace(
      path: '$prefix${path.startsWith('/') ? path.substring(1) : path}',
      query: null,
      fragment: null,
    );
  }

  Map<String, String> _apiHeaders() {
    final services = AirmiusServicesScope.of(context);
    return {
      'Accept': 'application/json',
      'X-Airmius-Locale': services.environment.locale,
      if (services.authState.session?.token.isNotEmpty == true)
        'Authorization': 'Bearer ${services.authState.session!.token}',
    };
  }

  Future<Map<String, dynamic>> _savePlanItemWithImage(
    Map<String, dynamic> payload,
    PlatformFile image, {
    int? itemId,
  }) async {
    final path = itemId == null
        ? '/api/v1/training/plans/${widget.planId}/items'
        : '/api/v1/training/plans/${widget.planId}/items/$itemId';
    final fallbackMessage = AirmiusScope.of(context).t('common.errorDetails');
    try {
      final request = http.MultipartRequest('POST', _apiUri(path))
        ..headers.addAll(_apiHeaders());
      if (itemId != null) request.fields['_method'] = 'PUT';
      void addField(String key, Object? value) {
        if (value == null) return;
        if (value is Map) {
          for (final nested in value.entries) {
            addField('$key[${nested.key}]', nested.value);
          }
          return;
        }
        if (value is Iterable) {
          var index = 0;
          for (final nested in value) {
            addField('$key[$index]', nested);
            index++;
          }
          return;
        }
        request.fields[key] = '$value';
      }

      for (final entry in payload.entries) {
        addField(entry.key, entry.value);
      }
      if (image.bytes != null) {
        request.files.add(
          http.MultipartFile.fromBytes(
            'image',
            image.bytes!,
            filename: image.name,
          ),
        );
      } else if (image.path != null) {
        request.files.add(
          await http.MultipartFile.fromPath(
            'image',
            image.path!,
            filename: image.name,
          ),
        );
      } else {
        throw AirmiusApiException(
          statusCode: 0,
          body: AirmiusScope.of(context).t('trainingHub.imageUnreadable'),
          path: path,
        );
      }
      final response = await http.Response.fromStream(await request.send());
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw AirmiusApiException(
          statusCode: response.statusCode,
          body: response.body,
          path: path,
        );
      }
      final decoded = jsonDecode(response.body);
      return decoded is Map<String, dynamic>
          ? decoded
          : <String, dynamic>{'data': decoded};
    } on AirmiusApiException {
      rethrow;
    } catch (error) {
      throw AirmiusApiException(
        statusCode: 0,
        body: fallbackMessage,
        path: path,
      );
    }
  }

  Future<void> _duplicateItem(int itemId) async {
    await _run(() => _client.duplicateTrainingPlanItem(widget.planId, itemId));
  }

  Future<void> _markMissed(int itemId) async {
    final payload = await showDialog<Map<String, String>>(
      context: context,
      builder: (_) => const _MissedTrainingDialog(),
    );
    if (payload == null) return;
    await _run(
      () => _client.markTrainingPlanItemMissed(
        widget.planId,
        itemId,
        reason: payload['reason']!,
        notes: payload['notes'],
      ),
    );
  }

  Future<void> _publishPlan() async {
    await _run(() => _client.publishTrainingPlan(widget.planId));
  }

  Future<void> _duplicatePlan() async {
    setState(() => _busy = true);
    try {
      final response = await _client.duplicateTrainingPlan(widget.planId);
      final copyId = _asInt(_singleData(response)['id']);
      if (!mounted) return;
      if (copyId > 0) {
        await Navigator.of(context).pushReplacement(
          MaterialPageRoute<void>(
            builder: (_) => TrainingPlanApiDetailScreen(planId: copyId),
          ),
        );
      }
    } on AirmiusApiException catch (error) {
      _message(error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _saveAsTemplate() async {
    await _run(() => _client.createTrainingTemplate(widget.planId));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(AirmiusScope.of(context).t('trainingHub.templateSaved')),
      ),
    );
  }

  Future<void> _deleteItem(int itemId) async {
    final confirmed = await _confirmDelete(context);
    if (!confirmed) return;
    await _run(() => _client.deleteTrainingPlanItem(widget.planId, itemId));
  }

  Future<void> _deletePlan() async {
    final confirmed = await _confirmDelete(context);
    if (!confirmed) return;
    setState(() => _busy = true);
    try {
      await _client.deleteTrainingPlan(widget.planId);
      if (mounted) Navigator.of(context).pop();
    } on AirmiusApiException catch (error) {
      _message(error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<bool> _run(Future<Map<String, dynamic>> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
      _reload();
      return true;
    } on AirmiusApiException catch (error) {
      _message(error.userMessage);
      return false;
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _message(String value) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(value)));
  }
}

class TrainingLogApiDetailScreen extends StatefulWidget {
  const TrainingLogApiDetailScreen({super.key, required this.logId});

  final int logId;

  @override
  State<TrainingLogApiDetailScreen> createState() =>
      _TrainingLogApiDetailScreenState();
}

class _TrainingLogApiDetailScreenState
    extends State<TrainingLogApiDetailScreen> {
  Future<_TrainingLog>? _future;
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

  Future<_TrainingLog> _load() async => _TrainingLog.fromJson(
    _singleData(await _client.trainingLog(widget.logId)),
  );

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final currentUserId = AirmiusServicesScope.of(context).authState.user?.id;
    return Scaffold(
      appBar: AppBar(title: Text(t('trainingHub.logDetail'))),
      body: SafeArea(
        child: FutureBuilder<_TrainingLog>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError || !snapshot.hasData) {
              return Center(
                child: AirmiusButton(
                  label: t('trainingHub.retry'),
                  icon: Icons.refresh,
                  onPressed: _reload,
                ),
              );
            }
            final log = snapshot.data!;
            final canEdit = log.createdBy == currentUserId;
            final actualExercises = _workoutExercisesFromEntries(log.entries);
            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        log.title,
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          StatusPill(_shortDate(log.performedAt)),
                          if (log.durationMinutes != null)
                            StatusPill(
                              '${log.durationMinutes} ${t('trainingHub.minutes')}',
                            ),
                          if (log.distanceMeters != null)
                            StatusPill(
                              '${(log.distanceMeters! / 1000).toStringAsFixed(1)} km',
                            ),
                          if (log.intensity?.isNotEmpty == true)
                            StatusPill(log.intensity!),
                        ],
                      ),
                      if (log.notes?.isNotEmpty == true) ...[
                        const SizedBox(height: 14),
                        Text(log.notes!),
                      ],
                    ],
                  ),
                ),
                if (log.sportRoute != null || log.sportRouteTrack != null) ...[
                  const SizedBox(height: 14),
                  AirmiusPanel(
                    title: t('trainingHub.routeAndTrack'),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (log.sportRoute != null)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: const Icon(Icons.route_outlined),
                            title: Text(log.sportRoute!.title),
                            subtitle: Text(
                              log.sportRoute!.distanceMeters == null
                                  ? t('trainingHub.locationMinimized')
                                  : '${(log.sportRoute!.distanceMeters! / 1000).toStringAsFixed(1)} km · ${t('trainingHub.locationMinimized')}',
                            ),
                            trailing: const Icon(Icons.chevron_right),
                            onTap: () => Navigator.of(context).push(
                              MaterialPageRoute<void>(
                                builder: (_) => SportMapCenterScreen(
                                  initialRouteId: log.sportRoute!.id,
                                ),
                              ),
                            ),
                          ),
                        if (log.sportRouteTrack != null)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: const Icon(Icons.gps_fixed_outlined),
                            title: Text(log.sportRouteTrack!.title),
                            subtitle: Text(
                              log.sportRouteTrack!.distanceMeters == null
                                  ? t('trainingHub.gpsTrack')
                                  : '${(log.sportRouteTrack!.distanceMeters! / 1000).toStringAsFixed(1)} km',
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
                if (log.entries.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  AirmiusPanel(
                    title: t('workout.title'),
                    child: Column(
                      children: [
                        for (final exercise in actualExercises)
                          _WorkoutHistoryExercise(exercise: exercise),
                      ],
                    ),
                  ),
                ],
                if (log.plannedExercises.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  AirmiusPanel(
                    title: t('workout.comparisonTitle'),
                    child: _WorkoutPlanComparison(
                      planned: log.plannedExercises,
                      actual: actualExercises,
                    ),
                  ),
                ],
                if (log.trainerFeedback?.isNotEmpty == true ||
                    log.feedbacks.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  AirmiusPanel(
                    title: t('trainingHub.feedback'),
                    borderColor: AirmiusColors.green.withValues(alpha: .5),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (log.trainerFeedback?.isNotEmpty == true)
                          Text(log.trainerFeedback!),
                        for (final feedback in log.feedbacks) ...[
                          if (log.trainerFeedback?.isNotEmpty == true ||
                              feedback != log.feedbacks.first)
                            const Divider(height: 24),
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  feedback.authorName,
                                  style: Theme.of(context).textTheme.labelLarge
                                      ?.copyWith(fontWeight: FontWeight.w800),
                                ),
                              ),
                              if (feedback.createdAt != null)
                                Text(
                                  _shortDate(feedback.createdAt),
                                  style: Theme.of(context).textTheme.labelSmall,
                                ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(feedback.body),
                        ],
                      ],
                    ),
                  ),
                ],
                if (log.status != 'draft') ...[
                  const SizedBox(height: 14),
                  AirmiusButton(
                    label: t('trainingHub.sendFeedback'),
                    icon: Icons.rate_review_outlined,
                    onPressed: _busy ? null : _sendFeedback,
                  ),
                ],
                if (canEdit) ...[
                  const SizedBox(height: 14),
                  AirmiusButton(
                    label: t('trainingHub.editLog'),
                    icon: Icons.edit_outlined,
                    onPressed: _busy ? null : () => _edit(log),
                  ),
                  const SizedBox(height: 10),
                  AirmiusButton(
                    label: t('trainingHub.deleteLog'),
                    icon: Icons.delete_forever_outlined,
                    danger: true,
                    onPressed: _busy ? null : _delete,
                  ),
                ],
              ],
            );
          },
        ),
      ),
    );
  }

  Future<void> _edit(_TrainingLog log) async {
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _LogFormDialog(initial: log),
    );
    if (payload == null) return;
    await _run(() => _client.updateTrainingLog(widget.logId, payload));
  }

  Future<void> _sendFeedback() async {
    final t = AirmiusScope.of(context).t;
    final controller = TextEditingController();
    final body = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('trainingHub.sendFeedback')),
        content: TextField(
          controller: controller,
          autofocus: true,
          minLines: 3,
          maxLines: 6,
          decoration: InputDecoration(labelText: t('trainingHub.feedback')),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(t('auth2fa.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(
              dialogContext,
              controller.text.trim().length < 2 ? null : controller.text.trim(),
            ),
            child: Text(t('trainingHub.send')),
          ),
        ],
      ),
    );
    controller.dispose();
    if (body == null) return;
    setState(() => _busy = true);
    try {
      await _client.sendTrainerFeedback(widget.logId, body);
      if (mounted) {
        _message(t('trainingHub.feedbackSent'));
        _reload();
      }
    } on AirmiusApiException catch (error) {
      _message(error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _delete() async {
    final confirmed = await _confirmDelete(context);
    if (!confirmed) return;
    setState(() => _busy = true);
    try {
      await _client.deleteTrainingLog(widget.logId);
      if (mounted) Navigator.of(context).pop();
    } on AirmiusApiException catch (error) {
      _message(error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _run(Future<Map<String, dynamic>> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
      _reload();
    } on AirmiusApiException catch (error) {
      _message(error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _message(String value) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(value)));
  }
}

class _AiPlanFormDialog extends StatefulWidget {
  const _AiPlanFormDialog();

  @override
  State<_AiPlanFormDialog> createState() => _AiPlanFormDialogState();
}

class _AiPlanFormDialogState extends State<_AiPlanFormDialog> {
  final _goal = TextEditingController();
  final _sport = TextEditingController();
  final _weeks = TextEditingController(text: '4');
  final _sessions = TextEditingController(text: '3');
  final _duration = TextEditingController(text: '45');
  String _level = 'beginner';
  String _phase = 'base';
  bool _allowEstimate = false;

  @override
  void dispose() {
    _goal.dispose();
    _sport.dispose();
    _weeks.dispose();
    _sessions.dispose();
    _duration.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final canSubmit =
        _goal.text.trim().length >= 3 && _sport.text.trim().isNotEmpty;
    return AlertDialog(
      title: Text(t('trainingHub.aiPlan')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _goal,
              autofocus: true,
              maxLines: 2,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(labelText: t('trainingHub.goal')),
            ),
            TextField(
              controller: _sport,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(labelText: t('trainingHub.sport')),
            ),
            DropdownButtonFormField<String>(
              initialValue: _level,
              decoration: InputDecoration(labelText: t('trainingHub.level')),
              items: ['beginner', 'intermediate', 'advanced', 'elite']
                  .map(
                    (value) => DropdownMenuItem(
                      value: value,
                      child: Text(t('trainingHub.level.$value')),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _level = value ?? _level),
            ),
            DropdownButtonFormField<String>(
              initialValue: _phase,
              decoration: InputDecoration(labelText: t('trainingHub.phase')),
              items: ['base', 'build', 'peak', 'recovery', 'rehab']
                  .map(
                    (value) => DropdownMenuItem(
                      value: value,
                      child: Text(t('trainingHub.phase.$value')),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _phase = value ?? _phase),
            ),
            TextField(
              controller: _weeks,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(labelText: t('trainingHub.weeks')),
            ),
            TextField(
              controller: _sessions,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: t('trainingHub.sessionsPerWeek'),
              ),
            ),
            TextField(
              controller: _duration,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(labelText: t('trainingHub.duration')),
            ),
            SwitchListTile(
              value: _allowEstimate,
              contentPadding: EdgeInsets.zero,
              title: Text(t('trainingHub.allowEstimate')),
              subtitle: Text(t('trainingHub.allowEstimateBody')),
              onChanged: (value) => setState(() => _allowEstimate = value),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('auth2fa.cancel')),
        ),
        FilledButton.icon(
          onPressed: canSubmit
              ? () => Navigator.pop(context, {
                  'goal': _goal.text.trim(),
                  'sport_type': _sport.text.trim(),
                  'level': _level,
                  'phase': _phase,
                  'weeks': int.tryParse(_weeks.text) ?? 4,
                  'sessions_per_week': int.tryParse(_sessions.text) ?? 3,
                  'duration_minutes': int.tryParse(_duration.text) ?? 45,
                  'starts_on': _dateApi(DateTime.now()),
                  'allow_profile_estimate': _allowEstimate,
                })
              : null,
          icon: const Icon(Icons.auto_awesome_outlined),
          label: Text(t('trainingHub.createPreview')),
        ),
      ],
    );
  }
}

class _AiPlanPreviewDialog extends StatefulWidget {
  const _AiPlanPreviewDialog({required this.plan});

  final Map<String, dynamic> plan;

  @override
  State<_AiPlanPreviewDialog> createState() => _AiPlanPreviewDialogState();
}

class _AiPlanPreviewDialogState extends State<_AiPlanPreviewDialog> {
  bool _accepted = false;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final plan = widget.plan;
    final items = _mapList(plan['items']);
    final warnings = (plan['warnings'] is List)
        ? List<Object?>.from(plan['warnings'] as List)
        : const <Object?>[];
    final safetyGate = _singleDataValue(plan['safety_gate']);
    final safetyBlocks = safetyGate['blocks'] is List
        ? List<Object?>.from(safetyGate['blocks'] as List)
        : const <Object?>[];
    final canSave = safetyGate['can_save'] == true;
    return AlertDialog(
      title: Text(plan['title']?.toString() ?? t('trainingHub.aiPreview')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (plan['summary']?.toString().trim().isNotEmpty == true)
              Text(plan['summary'].toString()),
            const SizedBox(height: 10),
            Text(
              '${items.length} ${t(items.length == 1 ? 'trainingHub.itemSingular' : 'trainingHub.items')}',
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 6),
            for (final item in items.take(8))
              ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.fitness_center_outlined),
                title: Text(item['title']?.toString() ?? ''),
                subtitle: Text(
                  '${_nullableInt(item['duration_minutes']) ?? 0} ${t('trainingHub.minutes')}',
                ),
              ),
            if (warnings.isNotEmpty) ...[
              const Divider(),
              Text(
                t('trainingHub.warnings'),
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
              for (final warning in warnings)
                Text('• ${warning?.toString() ?? ''}'),
            ],
            const Divider(),
            Text(
              t('trainingHub.aiSafetyTitle'),
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 6),
            Text(t('trainingHub.aiSafetyBody')),
            const SizedBox(height: 8),
            Chip(
              avatar: Icon(
                canSave ? Icons.verified_user_outlined : Icons.block_outlined,
                size: 18,
              ),
              label: Text(
                t(
                  canSave
                      ? 'trainingHub.aiSafetyReady'
                      : 'trainingHub.aiSafetyBlocked',
                ),
              ),
            ),
            for (final block in safetyBlocks)
              Text(
                '• ${t('trainingHub.aiSafetyBlock.${block?.toString() ?? 'unknown'}')}',
              ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              value: _accepted,
              onChanged: canSave
                  ? (value) => setState(() => _accepted = value == true)
                  : null,
              title: Text(t('trainingHub.aiSafetyAccept')),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context, false),
          child: Text(t('auth2fa.cancel')),
        ),
        FilledButton(
          onPressed: canSave && _accepted
              ? () => Navigator.pop(context, true)
              : null,
          child: Text(t('trainingHub.saveDraft')),
        ),
      ],
    );
  }
}

class _PlanDateButton extends StatelessWidget {
  const _PlanDateButton({
    required this.label,
    required this.value,
    required this.icon,
    required this.onPressed,
  });

  final String label;
  final String value;
  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final accent = Theme.of(context).colorScheme.primary;
    return Semantics(
      button: true,
      label: '$label $value',
      child: OutlinedButton(
        onPressed: onPressed,
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(68),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
        ),
        child: Row(
          children: [
            Icon(icon, size: 20, color: accent),
            const SizedBox(width: 7),
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelMedium,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    value,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: accent,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TemplateWorkflowSteps extends StatelessWidget {
  const _TemplateWorkflowSteps({
    required this.currentStep,
    required this.labels,
  });

  final int currentStep;
  final List<String> labels;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var index = 0; index < labels.length; index++) ...[
          Expanded(
            child: Column(
              children: [
                Container(
                  width: 30,
                  height: 30,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: index <= currentStep
                        ? colors.primary
                        : colors.surfaceContainerHighest,
                  ),
                  child: index < currentStep
                      ? Icon(Icons.check, size: 18, color: colors.onPrimary)
                      : Text(
                          '${index + 1}',
                          style: TextStyle(
                            color: index == currentStep
                                ? colors.onPrimary
                                : colors.onSurfaceVariant,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                ),
                const SizedBox(height: 6),
                Text(
                  labels[index],
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: index == currentStep
                        ? colors.primary
                        : colors.onSurfaceVariant,
                    fontWeight: index == currentStep
                        ? FontWeight.w900
                        : FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          if (index < labels.length - 1)
            Expanded(
              child: Padding(
                padding: const EdgeInsets.only(top: 14),
                child: Divider(
                  height: 2,
                  thickness: 2,
                  color: index < currentStep
                      ? colors.primary
                      : colors.outlineVariant,
                ),
              ),
            ),
        ],
      ],
    );
  }
}

class _PlanFormPage extends StatefulWidget {
  const _PlanFormPage({
    this.initial,
    this.initialStep = 0,
    this.publishOnSave = false,
  });

  final _TrainingPlan? initial;
  final int initialStep;
  final bool publishOnSave;

  @override
  State<_PlanFormPage> createState() => _PlanFormPageState();
}

class _PlanFormPageState extends State<_PlanFormPage> {
  _TrainingChoices _choices = const _TrainingChoices();
  bool _choicesLoading = true;
  String? _choicesError;
  int _step = 0;
  late final TextEditingController _title;
  late final TextEditingController _description;
  late final TextEditingController _goal;
  late final TextEditingController _weeks;
  late final TextEditingController _weeklySessions;
  late final TextEditingController _macrocycle;
  late final TextEditingController _mesocycle;
  late final TextEditingController _deloadWeek;
  late String _cadence;
  late String _status;
  late String _phase;
  late String _level;
  late String _permission;
  late String _targetType;
  late String _teamMode;
  int? _teamId;
  late Set<int> _userIds;
  late DateTime _startsOn;
  DateTime? _endsOn;
  DateTime? _competitionDate;
  late final TextEditingController _itemTitle;
  late final TextEditingController _itemSport;
  late final TextEditingController _itemDuration;
  late final TextEditingController _itemDistance;
  late final TextEditingController _itemFocus;
  late final TextEditingController _itemEquipment;
  late String _itemLoad;
  late String _itemGoal;
  late String _itemLevel;
  final List<_WorkoutExerciseDraft> _itemExercises = [];

  @override
  void initState() {
    super.initState();
    final initial = widget.initial;
    final lastStep = initial == null ? 5 : 2;
    _step = widget.initialStep < 0
        ? 0
        : widget.initialStep > lastStep
        ? lastStep
        : widget.initialStep;
    _title = TextEditingController(text: initial?.title ?? '');
    _description = TextEditingController(text: initial?.description ?? '');
    _goal = TextEditingController(text: initial?.goal ?? '');
    _weeks = TextEditingController(text: initial?.weeks?.toString() ?? '');
    _weeklySessions = TextEditingController(
      text: initial?.weeklySessions?.toString() ?? '',
    );
    _macrocycle = TextEditingController(text: initial?.macrocycle ?? '');
    _mesocycle = TextEditingController(text: initial?.mesocycle ?? '');
    _deloadWeek = TextEditingController(
      text: initial?.deloadWeek?.toString() ?? '',
    );
    _cadence = initial?.cadence ?? 'weekly';
    _status = widget.publishOnSave ? 'published' : initial?.status ?? 'draft';
    _phase = initial?.phase ?? 'base';
    _level = initial?.level ?? 'beginner';
    _permission = initial?.sharePermission ?? 'read';
    _targetType = widget.publishOnSave && (initial?.assignmentsCount ?? 0) == 0
        ? 'private'
        : initial?.targetType ?? 'self';
    _teamMode = initial?.teamMode ?? 'all';
    _teamId = initial?.teamId;
    _userIds = {...(initial?.assignedUserIds ?? const <int>[])};
    _startsOn = initial?.startsOn ?? DateTime.now();
    _endsOn = initial?.endsOn;
    _competitionDate = initial?.competitionDate;
    _itemTitle = TextEditingController();
    _itemSport = TextEditingController(text: 'laufen');
    _itemDuration = TextEditingController();
    _itemDistance = TextEditingController();
    _itemFocus = TextEditingController();
    _itemEquipment = TextEditingController();
    _itemLoad = 'medium';
    _itemGoal = 'technique';
    _itemLevel = _level;
    _loadChoices();
  }

  Future<void> _loadChoices() async {
    try {
      final services = AirmiusServicesScope.of(context);
      final choices = await _TrainingChoices.load(
        services.clientForSession(services.authState.session),
      );
      if (!mounted) return;
      setState(() {
        _choices = choices;
        _choicesLoading = false;
      });
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _choicesLoading = false;
        _choicesError = error.userMessage;
      });
    }
  }

  List<Map<String, dynamic>> get _selectedTeamMembers {
    if (_teamId == null) return const [];
    Map<String, dynamic>? team;
    for (final item in _choices.teams) {
      if (_asInt(item['id']) == _teamId) {
        team = item;
        break;
      }
    }
    final users = team?['users'];
    if (users is! List) return const [];

    return users
        .whereType<Map>()
        .map((user) => Map<String, dynamic>.from(user))
        .toList();
  }

  bool get _targetSelectionValid {
    if (_targetType == 'self') return true;
    if (_targetType == 'private') return _userIds.isNotEmpty;
    return _teamId != null && (_teamMode == 'all' || _userIds.isNotEmpty);
  }

  Future<void> _editItemExercise({int? index}) async {
    final result = await showDialog<_WorkoutExerciseDraft>(
      context: context,
      builder: (_) => _WorkoutExerciseDialog(
        initial: index == null ? null : _itemExercises[index],
        isPlanning: true,
      ),
    );
    if (result == null || !mounted) return;
    setState(() {
      if (index == null) {
        _itemExercises.add(result);
      } else {
        _itemExercises[index] = result;
      }
    });
  }

  Future<void> _addItemExerciseFromLibrary() async {
    final exercise = await _chooseWorkoutExercise(context);
    if (exercise == null || !mounted) return;
    final result = await showDialog<_WorkoutExerciseDraft>(
      context: context,
      builder: (_) =>
          _WorkoutExerciseDialog(initial: exercise, isPlanning: true),
    );
    if (result != null && mounted) {
      setState(() => _itemExercises.add(result));
    }
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _goal.dispose();
    _weeks.dispose();
    _weeklySessions.dispose();
    _macrocycle.dispose();
    _mesocycle.dispose();
    _deloadWeek.dispose();
    _itemTitle.dispose();
    _itemSport.dispose();
    _itemDuration.dispose();
    _itemDistance.dispose();
    _itemFocus.dispose();
    _itemEquipment.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final itemSetCount = _workoutEntryCount(_itemExercises);
    final totalSteps = widget.initial == null ? 6 : 3;
    final lastStep = totalSteps - 1;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t(
            widget.initial == null
                ? 'trainingHub.addPlan'
                : widget.publishOnSave
                ? 'trainingHub.sendToAthletes'
                : 'trainingHub.editPlan',
          ),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 120),
          child: AirmiusPanel(
            gradient: true,
            child: Column(
              children: [
                Semantics(
                  label: t('trainingHub.stepProgress')
                      .replaceFirst('{current}', '${_step + 1}')
                      .replaceFirst('{total}', '$totalSteps'),
                  value: t('trainingHub.step${_step + 1}'),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: List.generate(totalSteps * 2 - 1, (index) {
                          if (index.isOdd) {
                            final connectorStep = index ~/ 2;
                            return Expanded(
                              child: Container(
                                height: 2,
                                color: connectorStep < _step
                                    ? Theme.of(context).colorScheme.primary
                                    : Theme.of(context).dividerColor,
                              ),
                            );
                          }
                          final step = index ~/ 2;
                          final completed = step < _step;
                          final active = step == _step;
                          return AnimatedContainer(
                            duration: const Duration(milliseconds: 180),
                            width: active ? 30 : 26,
                            height: active ? 30 : 26,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: completed || active
                                  ? Theme.of(context).colorScheme.primary
                                  : Theme.of(context).colorScheme.surface,
                              border: Border.all(
                                width: 2,
                                color: completed || active
                                    ? Theme.of(context).colorScheme.primary
                                    : Theme.of(context).dividerColor,
                              ),
                            ),
                            alignment: Alignment.center,
                            child: completed
                                ? Icon(
                                    Icons.check,
                                    size: 17,
                                    color: Theme.of(
                                      context,
                                    ).colorScheme.onPrimary,
                                  )
                                : Text(
                                    '${step + 1}',
                                    style: TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w900,
                                      color: active
                                          ? Theme.of(
                                              context,
                                            ).colorScheme.onPrimary
                                          : Theme.of(
                                              context,
                                            ).colorScheme.onSurfaceVariant,
                                    ),
                                  ),
                          );
                        }),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        '${t('trainingHub.stepProgress').replaceFirst('{current}', '${_step + 1}').replaceFirst('{total}', '$totalSteps')} · ${t('trainingHub.step${_step + 1}')}',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                if (_choicesLoading) ...[
                  const LinearProgressIndicator(),
                  const SizedBox(height: 12),
                ],
                if (_choicesError != null) ...[
                  Text(
                    _choicesError!,
                    style: const TextStyle(color: AirmiusColors.red),
                  ),
                  const SizedBox(height: 12),
                ],
                if (_step == 0) ...[
                  TextField(
                    controller: _title,
                    autofocus: true,
                    onChanged: (_) => setState(() {}),
                    decoration: InputDecoration(
                      labelText: t('trainingHub.planTitle'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _cadence,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.cadence'),
                    ),
                    items: ['single', 'daily', 'weekly', 'monthly']
                        .map(
                          (value) => DropdownMenuItem(
                            value: value,
                            child: Text(_translatedCadence(t, value)),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setState(() => _cadence = value ?? _cadence),
                  ),
                ],
                if (_step == 1) ...[
                  Row(
                    children: [
                      Expanded(
                        child: _PlanDateButton(
                          label: t('trainingHub.start'),
                          value: _shortDate(_startsOn),
                          icon: Icons.event_outlined,
                          onPressed: () async {
                            final picked = await showDatePicker(
                              context: context,
                              initialDate: _startsOn,
                              firstDate: DateTime(2020),
                              lastDate: DateTime(2100),
                            );
                            if (picked != null) {
                              setState(() => _startsOn = picked);
                            }
                          },
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _PlanDateButton(
                          label: t('trainingHub.end'),
                          value: _endsOn == null
                              ? t('trainingHub.selectDate')
                              : _shortDate(_endsOn),
                          icon: Icons.event_available_outlined,
                          onPressed: () async {
                            final picked = await showDatePicker(
                              context: context,
                              initialDate: _endsOn ?? _startsOn,
                              firstDate: _startsOn,
                              lastDate: DateTime(2100),
                            );
                            if (picked != null) {
                              setState(() => _endsOn = picked);
                            }
                          },
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _description,
                    maxLines: 3,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.description'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _goal,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.goal'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _phase,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.phase'),
                    ),
                    items: ['base', 'build', 'peak', 'recovery', 'rehab']
                        .map(
                          (value) => DropdownMenuItem(
                            value: value,
                            child: Text(t('trainingHub.phase.$value')),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setState(() => _phase = value ?? _phase),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _level,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.level'),
                    ),
                    items: ['beginner', 'intermediate', 'advanced', 'elite']
                        .map(
                          (value) => DropdownMenuItem(
                            value: value,
                            child: Text(t('trainingHub.level.$value')),
                          ),
                        )
                        .toList(),
                    onChanged: (value) => setState(() {
                      final next = value ?? _level;
                      if (_itemLevel == _level) _itemLevel = next;
                      _level = next;
                    }),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _weeks,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.weeks'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _weeklySessions,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.sessionsPerWeek'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  ExpansionTile(
                    tilePadding: EdgeInsets.zero,
                    childrenPadding: const EdgeInsets.only(bottom: 8),
                    leading: const Icon(Icons.timeline_outlined),
                    title: Text(
                      t('trainingHub.periodization'),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    subtitle: Text(t('trainingHub.periodizationHint')),
                    children: [
                      TextField(
                        controller: _macrocycle,
                        decoration: InputDecoration(
                          labelText: t('trainingHub.macrocycle'),
                        ),
                      ),
                      TextField(
                        controller: _mesocycle,
                        decoration: InputDecoration(
                          labelText: t('trainingHub.mesocycle'),
                        ),
                      ),
                      TextField(
                        controller: _deloadWeek,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          labelText: t('trainingHub.deloadWeek'),
                        ),
                      ),
                      const SizedBox(height: 8),
                      OutlinedButton.icon(
                        onPressed: () async {
                          final picked = await showDatePicker(
                            context: context,
                            initialDate:
                                _competitionDate ?? _endsOn ?? _startsOn,
                            firstDate: _startsOn,
                            lastDate: DateTime(2100),
                          );
                          if (picked != null) {
                            setState(() => _competitionDate = picked);
                          }
                        },
                        icon: const Icon(Icons.emoji_events_outlined),
                        label: Text(
                          _competitionDate == null
                              ? t('trainingHub.chooseCompetitionDate')
                              : '${t('trainingHub.competitionDate')}: '
                                    '${_shortDate(_competitionDate)}',
                        ),
                      ),
                    ],
                  ),
                ],
                if (_step == 2) ...[
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _targetType,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.target'),
                    ),
                    items: [
                      DropdownMenuItem(
                        value: 'self',
                        child: Text(t('trainingHub.target.self')),
                      ),
                      DropdownMenuItem(
                        value: 'private',
                        child: Text(t('trainingHub.target.private')),
                      ),
                      DropdownMenuItem(
                        value: 'team',
                        child: Text(t('trainingHub.target.team')),
                      ),
                    ],
                    onChanged: (value) => setState(() {
                      _targetType = value ?? _targetType;
                      _teamId = null;
                      _teamMode = 'all';
                      _userIds.clear();
                    }),
                  ),
                  if (_targetType == 'team') ...[
                    const SizedBox(height: 16),
                    DropdownButtonFormField<int?>(
                      initialValue: _teamId,
                      decoration: InputDecoration(
                        labelText: t('trainingHub.team'),
                      ),
                      items: [
                        DropdownMenuItem<int?>(
                          value: null,
                          child: Text(t('trainingHub.chooseTeam')),
                        ),
                        ..._choices.teams.map(
                          (team) => DropdownMenuItem<int?>(
                            value: _asInt(team['id']),
                            child: Text(team['name']?.toString() ?? 'Team'),
                          ),
                        ),
                      ],
                      onChanged: (value) => setState(() {
                        _teamId = value;
                        _teamMode = 'all';
                        _userIds.clear();
                      }),
                    ),
                    if (_teamId != null) ...[
                      const SizedBox(height: 16),
                      SegmentedButton<String>(
                        segments: [
                          ButtonSegment(
                            value: 'all',
                            icon: const Icon(Icons.groups_outlined),
                            label: Text(t('trainingHub.target.fullTeam')),
                          ),
                          ButtonSegment(
                            value: 'individual',
                            icon: const Icon(Icons.person_outline),
                            label: Text(t('trainingHub.target.individual')),
                          ),
                        ],
                        selected: {_teamMode},
                        onSelectionChanged: (value) => setState(() {
                          _teamMode = value.first;
                          _userIds.clear();
                        }),
                      ),
                    ],
                  ],
                  if (_targetType == 'private') ...[
                    const SizedBox(height: 16),
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: Text(
                        t('trainingHub.target.privatePeople'),
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                    ),
                    const SizedBox(height: 7),
                    if (_choices.athletes.isEmpty)
                      Text(t('trainingHub.target.noPrivatePeople'))
                    else
                      Wrap(
                        spacing: 7,
                        runSpacing: 7,
                        children: _choices.athletes.map((athlete) {
                          final id = _asInt(athlete['id']);
                          return FilterChip(
                            selected: _userIds.contains(id),
                            label: Text(
                              athlete['name']?.toString() ?? 'Person',
                            ),
                            onSelected: (selected) => setState(() {
                              if (selected) {
                                _userIds.add(id);
                              } else {
                                _userIds.remove(id);
                              }
                            }),
                          );
                        }).toList(),
                      ),
                  ],
                  if (_targetType == 'team' &&
                      _teamId != null &&
                      _teamMode == 'individual') ...[
                    const SizedBox(height: 16),
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: Text(
                        t('trainingHub.assignAthletes'),
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                    ),
                    const SizedBox(height: 7),
                    if (_selectedTeamMembers.isEmpty)
                      Text(t('trainingHub.target.noTeamMembers'))
                    else
                      Wrap(
                        spacing: 7,
                        runSpacing: 7,
                        children: _selectedTeamMembers.map((athlete) {
                          final id = _asInt(athlete['id']);
                          return FilterChip(
                            selected: _userIds.contains(id),
                            label: Text(
                              athlete['name']?.toString() ?? 'Athlet',
                            ),
                            onSelected: (selected) => setState(() {
                              if (selected) {
                                _userIds.add(id);
                              } else {
                                _userIds.remove(id);
                              }
                            }),
                          );
                        }).toList(),
                      ),
                  ],
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _permission,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.permission'),
                    ),
                    items: ['read', 'write']
                        .map(
                          (value) => DropdownMenuItem(
                            value: value,
                            child: Text(t('trainingHub.permission.$value')),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setState(() => _permission = value ?? _permission),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _status,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.status'),
                    ),
                    items: ['draft', 'published']
                        .map(
                          (value) => DropdownMenuItem(
                            value: value,
                            child: Text(_translatedStatus(t, value)),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setState(() => _status = value ?? _status),
                  ),
                ],
                if (_step == 3) ...[
                  TextField(
                    controller: _itemTitle,
                    onChanged: (_) => setState(() {}),
                    decoration: InputDecoration(
                      labelText: t('trainingHub.itemTitle'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _itemSport,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.sport'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _itemDuration,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.duration'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _itemDistance,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: InputDecoration(
                      labelText: t('trainingHub.distance'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _itemLoad,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.load'),
                    ),
                    items: ['low', 'medium', 'high', 'test']
                        .map(
                          (value) => DropdownMenuItem(
                            value: value,
                            child: Text(t('trainingHub.load.$value')),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setState(() => _itemLoad = value ?? _itemLoad),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _itemFocus,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.focus'),
                    ),
                  ),
                ],
                if (_step == 4) ...[
                  _PlannedWorkoutComposer(
                    exercises: _itemExercises,
                    onAdd: () => _editItemExercise(),
                    onLibrary: _addItemExerciseFromLibrary,
                    onEdit: (index) => _editItemExercise(index: index),
                    onDelete: (index) =>
                        setState(() => _itemExercises.removeAt(index)),
                    onReorder: (oldIndex, newIndex) => setState(() {
                      final exercise = _itemExercises.removeAt(oldIndex);
                      _itemExercises.insert(newIndex, exercise);
                    }),
                  ),
                ],
                if (_step == 5) ...[
                  _TrainingStructureFields(
                    goal: _itemGoal,
                    level: _itemLevel,
                    equipmentController: _itemEquipment,
                    initiallyExpanded: true,
                    onGoalChanged: (value) => setState(() => _itemGoal = value),
                    onLevelChanged: (value) =>
                        setState(() => _itemLevel = value),
                    onEquipmentChanged: (_) => setState(() {}),
                    onEquipmentPreset: (value) =>
                        setState(() => _itemEquipment.text = value),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.fromLTRB(16, 8, 16, 12),
        child: Row(
          children: [
            SizedBox(
              width: 48,
              child: IconButton.outlined(
                tooltip: t('auth2fa.cancel'),
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close),
              ),
            ),
            if (_step > 0) ...[
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton(
                  onPressed: () => setState(() => _step -= 1),
                  child: Text(t('trainingHub.back')),
                ),
              ),
            ],
            const SizedBox(width: 10),
            Expanded(
              child: FilledButton(
                onPressed: _step < lastStep
                    ? ((_step == 0 && _title.text.trim().isEmpty) ||
                              (_step == 2 && !_targetSelectionValid) ||
                              (widget.initial == null &&
                                  _step == 3 &&
                                  _itemTitle.text.trim().isEmpty) ||
                              (_step == 4 && itemSetCount > 40)
                          ? null
                          : () => setState(() => _step += 1))
                    : (_title.text.trim().isEmpty ||
                          (widget.initial == null &&
                              _itemTitle.text.trim().isEmpty) ||
                          itemSetCount > 40)
                    ? null
                    : () => Navigator.pop(context, {
                        'title': _title.text.trim(),
                        'description': _description.text.trim(),
                        'cadence': _cadence,
                        'starts_on': _dateApi(_startsOn),
                        'ends_on': _endsOn == null ? null : _dateApi(_endsOn!),
                        'goal': _goal.text.trim(),
                        'phase': _phase,
                        'level': _level,
                        'weeks': int.tryParse(_weeks.text),
                        'weekly_sessions': int.tryParse(_weeklySessions.text),
                        'macrocycle': _macrocycle.text.trim(),
                        'mesocycle': _mesocycle.text.trim(),
                        'deload_week': int.tryParse(_deloadWeek.text),
                        'competition_date': _competitionDate == null
                            ? null
                            : _dateApi(_competitionDate!),
                        'status': widget.publishOnSave ? 'published' : _status,
                        'share_permission': _permission,
                        'target_type': _targetType,
                        'team_mode': _targetType == 'team' ? _teamMode : null,
                        'team_id': _teamId,
                        'user_ids': _targetType == 'self'
                            ? <int>[]
                            : _userIds.toList(),
                        'item_title': _itemTitle.text.trim(),
                        'item_sport_type': _itemSport.text.trim(),
                        'item_duration_minutes': int.tryParse(
                          _itemDuration.text,
                        ),
                        'item_distance_km': double.tryParse(
                          _itemDistance.text.replaceAll(',', '.'),
                        ),
                        'item_load': _itemLoad,
                        'item_focus': _itemFocus.text.trim(),
                        'item_metrics': _structuredTrainingMetrics(
                          goal: _itemGoal,
                          level: _itemLevel,
                          equipment: _itemEquipment.text,
                        ),
                        'item_exercises': _itemExercises
                            .map((exercise) => exercise.toPlannedPayload())
                            .toList(),
                      }),
                child: Text(
                  t(
                    _step < lastStep
                        ? 'trainingHub.next'
                        : widget.publishOnSave
                        ? 'trainingHub.sendPlan'
                        : 'trainingHub.save',
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PlanItemFormPage extends StatefulWidget {
  const _PlanItemFormPage({this.initial});

  final _TrainingPlanItem? initial;

  @override
  State<_PlanItemFormPage> createState() => _PlanItemFormPageState();
}

class _PlanItemFormPageState extends State<_PlanItemFormPage>
    with SingleTickerProviderStateMixin {
  late final TextEditingController _title;
  late final TextEditingController _description;
  late final TextEditingController _sportType;
  late final TextEditingController _sportSearch;
  late final TextEditingController _duration;
  late final TextEditingController _distance;
  late final TextEditingController _week;
  late final TextEditingController _calories;
  late final TextEditingController _focus;
  late final TextEditingController _metrics;
  late final TextEditingController _equipment;
  late final TextEditingController _videoUrl;
  late final TextEditingController _todos;
  late String _intensity;
  late String _load;
  late String _trainingGoal;
  late String _sessionLevel;
  int? _sportRouteId;
  late DateTime _scheduledAt;
  PlatformFile? _image;
  List<String> _sports = const [];
  List<_TrainingRouteReference> _routes = const [];
  late List<_WorkoutExerciseDraft> _exercises;
  bool _sportsLoading = true;
  late final TabController _tabController;
  int _tab = 0;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    final initial = widget.initial;
    _title = TextEditingController(text: initial?.title ?? '');
    _description = TextEditingController(text: initial?.description ?? '');
    _sportType = TextEditingController(text: initial?.sportType ?? '');
    _sportSearch = TextEditingController();
    _duration = TextEditingController(
      text: initial?.durationMinutes?.toString() ?? '',
    );
    _distance = TextEditingController(
      text: initial?.distanceMeters == null
          ? ''
          : (initial!.distanceMeters! / 1000).toStringAsFixed(1),
    );
    _week = TextEditingController(text: initial?.week?.toString() ?? '');
    _calories = TextEditingController(
      text: initial?.calories?.toString() ?? '',
    );
    _focus = TextEditingController(text: initial?.focus ?? '');
    _metrics = TextEditingController(text: _metricsText(initial?.metrics));
    _equipment = TextEditingController(
      text: _metricValue(initial?.metrics, 'Equipment') ?? '',
    );
    _videoUrl = TextEditingController(text: initial?.videoUrl ?? '');
    _todos = TextEditingController(text: initial?.todos.join('\n') ?? '');
    _intensity = initial?.intensity ?? 'mittel';
    _load = initial?.load ?? 'medium';
    _trainingGoal = _optionKeyFromMetric(
      _trainingGoals,
      _metricValue(initial?.metrics, 'Trainingsziel') ?? initial?.focus,
      'technique',
    );
    _sessionLevel = _levelKeyOrDefault(
      _metricValue(initial?.metrics, 'Niveau'),
    );
    _sportRouteId = initial?.sportRoute?.id;
    _scheduledAt = initial?.scheduledAt ?? DateTime.now();
    _exercises =
        initial?.plannedExercises.map((exercise) => exercise.copy()).toList() ??
        [];
    _loadSports();
  }

  Future<void> _loadSports() async {
    try {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      final responses = await Future.wait([
        client.sports(),
        client.trainingRouteOptions(),
      ]);
      final data = responses[0]['data'];
      final sports = data is List
          ? data
                .whereType<Map>()
                .map(
                  (item) =>
                      (item['name'] ?? item['label'] ?? item['slug'])
                          ?.toString() ??
                      '',
                )
                .where((value) => value.isNotEmpty)
                .toSet()
                .toList()
          : <String>[];
      if (!mounted) return;
      setState(() {
        _sports = sports;
        final routeOptions = _singleData(responses[1]);
        _routes = _mapList(routeOptions['routes'])
            .map(_TrainingRouteReference.fromJson)
            .where((route) => route.id > 0)
            .toList();
        _sportsLoading = false;
      });
    } catch (_) {
      if (mounted) setState(() => _sportsLoading = false);
    }
  }

  Future<void> _editExercise({int? index}) async {
    final result = await showDialog<_WorkoutExerciseDraft>(
      context: context,
      builder: (_) => _WorkoutExerciseDialog(
        initial: index == null ? null : _exercises[index],
        isPlanning: true,
      ),
    );
    if (result == null || !mounted) return;
    setState(() {
      if (index == null) {
        _exercises.add(result);
      } else {
        _exercises[index] = result;
      }
    });
  }

  Future<void> _addExerciseFromLibrary() async {
    final exercise = await _chooseWorkoutExercise(context);
    if (exercise == null || !mounted) return;
    final result = await showDialog<_WorkoutExerciseDraft>(
      context: context,
      builder: (_) =>
          _WorkoutExerciseDialog(initial: exercise, isPlanning: true),
    );
    if (result != null && mounted) {
      setState(() => _exercises.add(result));
    }
  }

  @override
  void dispose() {
    _tabController.dispose();
    _title.dispose();
    _description.dispose();
    _sportType.dispose();
    _sportSearch.dispose();
    _duration.dispose();
    _distance.dispose();
    _week.dispose();
    _calories.dispose();
    _focus.dispose();
    _metrics.dispose();
    _equipment.dispose();
    _videoUrl.dispose();
    _todos.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final workoutEntryCount = _workoutEntryCount(_exercises);
    final search = _sportSearch.text.trim().toLowerCase();
    final filteredSports = _sports
        .where(
          (sport) => search.length >= 2 && sport.toLowerCase().contains(search),
        )
        .take(12)
        .toList();
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t(
            widget.initial == null
                ? 'trainingHub.addItem'
                : 'trainingHub.editItem',
          ),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 120),
          child: AirmiusPanel(
            gradient: true,
            child: Column(
              children: [
                TabBar(
                  controller: _tabController,
                  onTap: (value) => setState(() => _tab = value),
                  tabs: [
                    Tab(
                      icon: const Icon(Icons.edit_note_outlined),
                      text: t('trainingHub.itemTabBasics'),
                    ),
                    Tab(
                      icon: const Icon(Icons.fitness_center_outlined),
                      text: t('trainingHub.itemTabTraining'),
                    ),
                    Tab(
                      icon: const Icon(Icons.tune_outlined),
                      text: t('trainingHub.itemTabDetails'),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                if (_tab == 0) ...[
                  TextField(
                    controller: _title,
                    autofocus: true,
                    onChanged: (_) => setState(() {}),
                    decoration: InputDecoration(
                      labelText: t('trainingHub.itemTitle'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _description,
                    maxLines: 3,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.description'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _sportSearch,
                    onChanged: (_) => setState(() {}),
                    decoration: InputDecoration(
                      labelText: t('trainingHub.sport'),
                      hintText: t('trainingHub.sportSearch'),
                      prefixIcon: const Icon(Icons.search),
                    ),
                  ),
                  if (search.length < 2)
                    Padding(
                      padding: const EdgeInsets.only(top: 10),
                      child: Align(
                        alignment: AlignmentDirectional.centerStart,
                        child: Text(
                          t('trainingHub.sportSearchHint'),
                          style: TextStyle(color: airmiusMutedColor(context)),
                        ),
                      ),
                    ),
                  if (_sportsLoading) const LinearProgressIndicator(),
                  if (filteredSports.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 10),
                      child: Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: filteredSports
                            .map(
                              (sport) => ChoiceChip(
                                label: Text(sport),
                                selected: _sportType.text == sport,
                                onSelected: (_) =>
                                    setState(() => _sportType.text = sport),
                              ),
                            )
                            .toList(),
                      ),
                    ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _sportType,
                    readOnly: _sports.isNotEmpty,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.selectedSport'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<int?>(
                    initialValue: _sportRouteId,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.route'),
                      helperText: t('trainingHub.routeShareHint'),
                    ),
                    items: [
                      DropdownMenuItem<int?>(
                        value: null,
                        child: Text(t('trainingHub.noRoute')),
                      ),
                      if (_sportRouteId != null &&
                          !_routes.any((route) => route.id == _sportRouteId))
                        DropdownMenuItem<int?>(
                          value: _sportRouteId,
                          child: Text(
                            widget.initial?.sportRoute?.title ??
                                t('trainingHub.route'),
                          ),
                        ),
                      ..._routes.map(
                        (route) => DropdownMenuItem<int?>(
                          value: route.id,
                          child: Text(
                            route.distanceMeters == null
                                ? route.title
                                : '${route.title} · ${(route.distanceMeters! / 1000).toStringAsFixed(1)} km',
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ),
                    ],
                    onChanged: (value) => setState(() => _sportRouteId = value),
                  ),
                ],
                if (_tab == 1) ...[
                  const SizedBox(height: 16),
                  TextField(
                    controller: _duration,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.duration'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _distance,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: InputDecoration(
                      labelText: t('trainingHub.distance'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _intensity,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.intensity'),
                    ),
                    items: ['locker', 'mittel', 'hart', 'recovery']
                        .map(
                          (value) => DropdownMenuItem(
                            value: value,
                            child: Text(t('trainingHub.intensity.$value')),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setState(() => _intensity = value ?? _intensity),
                  ),
                  const SizedBox(height: 16),
                  _PlannedWorkoutComposer(
                    exercises: _exercises,
                    onAdd: () => _editExercise(),
                    onLibrary: _addExerciseFromLibrary,
                    onEdit: (index) => _editExercise(index: index),
                    onDelete: (index) =>
                        setState(() => _exercises.removeAt(index)),
                    onReorder: (oldIndex, newIndex) => setState(() {
                      final exercise = _exercises.removeAt(oldIndex);
                      _exercises.insert(newIndex, exercise);
                    }),
                  ),
                  const SizedBox(height: 8),
                  _TrainingStructureFields(
                    goal: _trainingGoal,
                    level: _sessionLevel,
                    equipmentController: _equipment,
                    onGoalChanged: (value) =>
                        setState(() => _trainingGoal = value),
                    onLevelChanged: (value) =>
                        setState(() => _sessionLevel = value),
                    onEquipmentChanged: (_) => setState(() {}),
                    onEquipmentPreset: (value) =>
                        setState(() => _equipment.text = value),
                  ),
                  const SizedBox(height: 8),
                  ExpansionTile(
                    tilePadding: EdgeInsets.zero,
                    childrenPadding: const EdgeInsets.only(bottom: 8),
                    leading: const Icon(Icons.calendar_month_outlined),
                    title: Text(
                      t('trainingHub.sessionPlanning'),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    subtitle: Text(t('trainingHub.sessionPlanningHint')),
                    children: [
                      OutlinedButton.icon(
                        onPressed: () async {
                          final date = await showDatePicker(
                            context: context,
                            initialDate: _scheduledAt,
                            firstDate: DateTime(2020),
                            lastDate: DateTime(2100),
                          );
                          if (date == null || !context.mounted) return;
                          final time = await showTimePicker(
                            context: context,
                            initialTime: TimeOfDay.fromDateTime(_scheduledAt),
                          );
                          if (time == null || !context.mounted) return;
                          setState(
                            () => _scheduledAt = DateTime(
                              date.year,
                              date.month,
                              date.day,
                              time.hour,
                              time.minute,
                            ),
                          );
                        },
                        icon: const Icon(Icons.schedule_outlined),
                        label: Text(
                          '${t('trainingHub.scheduledAt')}: '
                          '${_dateTimeLabel(_scheduledAt)}',
                        ),
                      ),
                      TextField(
                        controller: _week,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          labelText: t('trainingHub.planWeek'),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _calories,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          labelText: t('trainingHub.calories'),
                        ),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        initialValue: _load,
                        decoration: InputDecoration(
                          labelText: t('trainingHub.load'),
                        ),
                        items: ['low', 'medium', 'high', 'test']
                            .map(
                              (value) => DropdownMenuItem(
                                value: value,
                                child: Text(t('trainingHub.load.$value')),
                              ),
                            )
                            .toList(),
                        onChanged: (value) =>
                            setState(() => _load = value ?? _load),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _focus,
                        decoration: InputDecoration(
                          labelText: t('trainingHub.focus'),
                        ),
                      ),
                      TextField(
                        controller: _metrics,
                        minLines: 2,
                        maxLines: 6,
                        decoration: InputDecoration(
                          labelText: t('trainingHub.customMetrics'),
                          helperText: t('trainingHub.customMetricsHint'),
                        ),
                      ),
                    ],
                  ),
                ],
                if (_tab == 2) ...[
                  const SizedBox(height: 16),
                  TextField(
                    controller: _todos,
                    minLines: 3,
                    maxLines: 6,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.todos'),
                      helperText: t('trainingHub.todosHint'),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _videoUrl,
                    keyboardType: TextInputType.url,
                    decoration: InputDecoration(
                      labelText: t('trainingHub.videoUrl'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: OutlinedButton.icon(
                      onPressed: () async {
                        final result = await FilePicker.platform.pickFiles(
                          type: FileType.image,
                          withData: true,
                        );
                        final picked = result?.files.single;
                        if (picked != null && mounted) {
                          setState(() => _image = picked);
                        }
                      },
                      icon: const Icon(Icons.add_photo_alternate_outlined),
                      label: Text(
                        _image?.name ??
                            (widget.initial?.imageUrl?.isNotEmpty == true
                                ? t('trainingHub.replaceImage')
                                : t('trainingHub.chooseImage')),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.fromLTRB(16, 8, 16, 12),
        child: Row(
          children: [
            Expanded(
              child: _tab > 0
                  ? OutlinedButton(
                      onPressed: () {
                        _tabController.animateTo(_tab - 1);
                        setState(() => _tab -= 1);
                      },
                      child: Text(t('trainingHub.back')),
                    )
                  : OutlinedButton(
                      onPressed: () => Navigator.pop(context),
                      child: Text(t('auth2fa.cancel')),
                    ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _tab < 2
                  ? FilledButton(
                      onPressed:
                          _tab == 0 && _title.text.trim().isEmpty ||
                              (_tab == 1 && workoutEntryCount > 40)
                          ? null
                          : () {
                              _tabController.animateTo(_tab + 1);
                              setState(() => _tab += 1);
                            },
                      child: Text(t('trainingHub.next')),
                    )
                  : FilledButton(
                      onPressed:
                          _title.text.trim().isEmpty || workoutEntryCount > 40
                          ? null
                          : () => Navigator.pop(context, {
                              'title': _title.text.trim(),
                              'description': _description.text.trim(),
                              'sport_type': _sportType.text.trim(),
                              'sport_route_id': _sportRouteId,
                              'scheduled_at': _scheduledAt.toIso8601String(),
                              'week': int.tryParse(_week.text),
                              'duration_minutes': int.tryParse(_duration.text),
                              'distance_km': double.tryParse(
                                _distance.text.replaceAll(',', '.'),
                              ),
                              'calories': int.tryParse(_calories.text),
                              'intensity': _intensity,
                              'load': _load,
                              'focus': _focus.text.trim(),
                              'metrics': {
                                ..._parseMetricsText(_metrics.text),
                                ..._structuredTrainingMetrics(
                                  goal: _trainingGoal,
                                  level: _sessionLevel,
                                  equipment: _equipment.text,
                                ),
                              },
                              'exercises': _exercises
                                  .map(
                                    (exercise) => exercise.toPlannedPayload(),
                                  )
                                  .toList(),
                              'todos_text': _todos.text.trim(),
                              'video_url': _videoUrl.text.trim(),
                              '_image_file': _image,
                            }),
                      child: Text(t('trainingHub.save')),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

@visibleForTesting
Widget buildTrainingLiveWorkoutPreview() {
  final item = _TrainingPlanItem.fromJson({
    'id': -731,
    'title': 'Ganzkörper Kraft',
    'sport_type': 'krafttraining',
    'duration_minutes': 45,
    'intensity': 'mittel',
    'metrics': {
      'planned_exercises': [
        {
          'exercise_key': 'squat',
          'title': 'Kniebeuge',
          'tracking_mode': 'reps',
          'notes': 'Rumpf stabil halten und kontrolliert absenken.',
          'sets': [
            {'set_index': 1, 'reps': 10, 'weight_kg': 40, 'rest_seconds': 90},
            {'set_index': 2, 'reps': 8, 'weight_kg': 45, 'rest_seconds': 90},
            {'set_index': 3, 'reps': 8, 'weight_kg': 45, 'rest_seconds': 90},
          ],
        },
        {
          'exercise_key': 'plank',
          'title': 'Unterarmstütz',
          'tracking_mode': 'time',
          'sets': [
            {'set_index': 1, 'duration_minutes': 1, 'rest_seconds': 45},
            {'set_index': 2, 'duration_minutes': 1, 'rest_seconds': 45},
          ],
        },
      ],
    },
  });
  return _LiveWorkoutScreen(
    item: item,
    exercises: _workoutExercisesFromPlanItem(item),
    userId: -731,
    enablePersistence: false,
  );
}

class _LiveSetPosition {
  const _LiveSetPosition(this.exerciseIndex, this.setIndex);

  final int exerciseIndex;
  final int setIndex;
}

class _LiveWorkoutScreen extends StatefulWidget {
  const _LiveWorkoutScreen({
    required this.item,
    required this.exercises,
    required this.userId,
    this.enablePersistence = true,
  });

  final _TrainingPlanItem item;
  final List<_WorkoutExerciseDraft> exercises;
  final int userId;
  final bool enablePersistence;

  @override
  State<_LiveWorkoutScreen> createState() => _LiveWorkoutScreenState();
}

class _LiveWorkoutScreenState extends State<_LiveWorkoutScreen>
    with WidgetsBindingObserver {
  late final List<_WorkoutExerciseDraft> _planned;
  late final List<_WorkoutExerciseDraft> _actual;
  late final AirmiusTrainingDraftStore _draftStore;
  late DateTime _startedAt;
  Timer? _ticker;
  Timer? _autosaveTicker;
  Timer? _autosaveDebounce;
  int _cursor = 0;
  int _restRemaining = 0;
  bool _restActive = false;
  bool _readyToFinish = false;
  bool _restoringDraft = true;
  bool _savingDraft = false;
  bool _draftSavePending = false;
  DateTime? _lastSavedAt;

  List<_LiveSetPosition> get _positions => [
    for (var exerciseIndex = 0; exerciseIndex < _actual.length; exerciseIndex++)
      for (
        var setIndex = 0;
        setIndex < _actual[exerciseIndex].sets.length;
        setIndex++
      )
        _LiveSetPosition(exerciseIndex, setIndex),
  ];

  int get _visitedCount => _actual.fold(
    0,
    (total, exercise) =>
        total +
        exercise.sets
            .where((set) => set.completed || set.skipReason.isNotEmpty)
            .length,
  );

  int get _completedCount => _actual.fold(
    0,
    (total, exercise) =>
        total + exercise.sets.where((set) => set.completed).length,
  );

  int get _elapsedSeconds => DateTime.now().difference(_startedAt).inSeconds;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _planned = widget.exercises.map((exercise) => exercise.copy()).toList();
    _actual = widget.exercises
        .map((exercise) => exercise.forExecution())
        .toList();
    _draftStore = AirmiusTrainingDraftStore();
    _startedAt = DateTime.now();
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(() {
        if (_restActive && _restRemaining > 0) _restRemaining -= 1;
        if (_restActive && _restRemaining <= 0) _restActive = false;
      });
    });
    if (widget.enablePersistence) {
      _autosaveTicker = Timer.periodic(
        const Duration(seconds: 15),
        (_) => unawaited(_persistDraft()),
      );
      unawaited(_restoreDraft());
    } else {
      _restoringDraft = false;
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _ticker?.cancel();
    _autosaveTicker?.cancel();
    _autosaveDebounce?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused ||
        state == AppLifecycleState.detached) {
      unawaited(_persistDraft());
    }
  }

  Future<void> _restoreDraft() async {
    final draft = await _draftStore.read(
      userId: widget.userId,
      planItemId: widget.item.id,
    );
    if (!mounted) return;

    final restoredExercises = _mapList(draft?['actual_exercises'])
        .map(_WorkoutExerciseDraft.fromLiveDraftJson)
        .where((exercise) {
          return exercise.exerciseKey.isNotEmpty && exercise.title.isNotEmpty;
        })
        .toList();
    setState(() {
      if (restoredExercises.isNotEmpty) {
        _actual
          ..clear()
          ..addAll(restoredExercises);
        _startedAt =
            DateTime.tryParse('${draft?['started_at'] ?? ''}') ?? _startedAt;
        _cursor = _nullableInt(draft?['cursor']) ?? 0;
        _lastSavedAt = DateTime.tryParse('${draft?['saved_at'] ?? ''}');
        _readyToFinish = _actual.every(
          (exercise) => exercise.sets.every(
            (set) => set.completed || set.skipReason.isNotEmpty,
          ),
        );
      }
      _restoringDraft = false;
    });
    if (restoredExercises.isNotEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('liveWorkout.restored')),
        ),
      );
    }
  }

  void _scheduleDraftSave() {
    if (_restoringDraft) return;
    _autosaveDebounce?.cancel();
    _autosaveDebounce = Timer(
      const Duration(milliseconds: 350),
      () => unawaited(_persistDraft()),
    );
  }

  Future<void> _persistDraft() async {
    if (!widget.enablePersistence || _restoringDraft || _actual.isEmpty) return;
    if (_savingDraft) {
      _draftSavePending = true;
      return;
    }
    _savingDraft = true;
    try {
      await _draftStore.write(
        userId: widget.userId,
        planItemId: widget.item.id,
        payload: {
          'started_at': _startedAt.toIso8601String(),
          'cursor': _cursor,
          'actual_exercises': [
            for (final exercise in _actual) exercise.toLiveDraftPayload(),
          ],
        },
      );
      if (mounted) setState(() => _lastSavedAt = DateTime.now());
    } finally {
      _savingDraft = false;
      if (_draftSavePending) {
        _draftSavePending = false;
        unawaited(_persistDraft());
      }
    }
  }

  Future<void> _confirmExit() async {
    final t = AirmiusScope.of(context).t;
    final choice = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('liveWorkout.exitTitle')),
        content: Text(t('liveWorkout.exitBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, 'continue'),
            child: Text(t('liveWorkout.continue')),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, 'discard'),
            child: Text(t('liveWorkout.discard')),
          ),
          FilledButton.icon(
            onPressed: () => Navigator.pop(dialogContext, 'save'),
            icon: const Icon(Icons.save_outlined),
            label: Text(t('liveWorkout.saveExit')),
          ),
        ],
      ),
    );
    if (!mounted || choice == null || choice == 'continue') return;
    if (choice == 'discard' && widget.enablePersistence) {
      await _draftStore.clear(
        userId: widget.userId,
        planItemId: widget.item.id,
      );
    } else {
      await _persistDraft();
    }
    if (mounted) Navigator.pop(context);
  }

  _WorkoutSetDraft? _plannedSet(_LiveSetPosition position) {
    if (position.exerciseIndex >= _planned.length) return null;
    final sets = _planned[position.exerciseIndex].sets;
    return position.setIndex < sets.length ? sets[position.setIndex] : null;
  }

  void _moveTo(int exerciseIndex, int setIndex) {
    final positions = _positions;
    final index = positions.indexWhere(
      (position) =>
          position.exerciseIndex == exerciseIndex &&
          position.setIndex == setIndex,
    );
    if (index >= 0) {
      setState(() => _cursor = index);
      _scheduleDraftSave();
    }
  }

  void _advance({int restSeconds = 0}) {
    final positions = _positions;
    var next = -1;
    for (var index = _cursor + 1; index < positions.length; index++) {
      final position = positions[index];
      final set = _actual[position.exerciseIndex].sets[position.setIndex];
      if (!set.completed && set.skipReason.isEmpty) {
        next = index;
        break;
      }
    }
    if (next < 0) {
      for (var index = 0; index < _cursor; index++) {
        final position = positions[index];
        final set = _actual[position.exerciseIndex].sets[position.setIndex];
        if (!set.completed && set.skipReason.isEmpty) {
          next = index;
          break;
        }
      }
    }

    setState(() {
      _readyToFinish = next < 0;
      if (next >= 0) _cursor = next;
      _restRemaining = restSeconds;
      _restActive = next >= 0 && restSeconds > 0;
    });
  }

  void _completeSet() {
    final positions = _positions;
    if (positions.isEmpty) return;
    final position = positions[_cursor.clamp(0, positions.length - 1)];
    final set = _actual[position.exerciseIndex].sets[position.setIndex];
    final rest = int.tryParse(set.restSeconds.trim()) ?? 0;
    set.completed = true;
    set.skipReason = '';
    _advance(restSeconds: rest);
    _scheduleDraftSave();
  }

  Future<void> _skipSet() async {
    final t = AirmiusScope.of(context).t;
    final reason = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final value in ['pain', 'equipment', 'fatigue', 'other'])
              ListTile(
                leading: Icon(
                  value == 'pain'
                      ? Icons.healing_outlined
                      : Icons.skip_next_outlined,
                ),
                title: Text(t('liveWorkout.skip.$value')),
                onTap: () => Navigator.pop(sheetContext, value),
              ),
          ],
        ),
      ),
    );
    if (reason == null || !mounted) return;
    final positions = _positions;
    final position = positions[_cursor.clamp(0, positions.length - 1)];
    final set = _actual[position.exerciseIndex].sets[position.setIndex];
    set.completed = false;
    set.skipReason = reason;
    _advance();
    _scheduleDraftSave();
  }

  Future<void> _editActualSet() async {
    final positions = _positions;
    if (positions.isEmpty) return;
    final position = positions[_cursor.clamp(0, positions.length - 1)];
    final exercise = _actual[position.exerciseIndex];
    final result = await showModalBottomSheet<_WorkoutSetDraft>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _LiveSetEditorSheet(
        mode: exercise.mode,
        initial: exercise.sets[position.setIndex].copy(),
      ),
    );
    if (result != null && mounted) {
      setState(() => exercise.sets[position.setIndex] = result);
      _scheduleDraftSave();
    }
  }

  Future<void> _replaceExercise() async {
    final t = AirmiusScope.of(context).t;
    final positions = _positions;
    if (positions.isEmpty) return;
    final position = positions[_cursor.clamp(0, positions.length - 1)];
    final exerciseIndex = position.exerciseIndex;
    final selection = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) =>
          _WorkoutReplacementSheet(templates: _builtInWorkoutTemplates(t)),
    );
    if (selection == null || !mounted) return;

    _WorkoutExerciseDraft? replacement;
    if (selection['custom'] == true) {
      replacement = await showDialog<_WorkoutExerciseDraft>(
        context: context,
        builder: (_) => const _WorkoutExerciseDialog(),
      );
    } else {
      replacement = _WorkoutExerciseDraft.fromTemplate(selection);
    }
    if (replacement == null || !mounted) return;

    final source = _actual[exerciseIndex];
    final execution = replacement.forExecution();
    final substituted = _WorkoutExerciseDraft(
      exerciseKey: execution.exerciseKey,
      title: execution.title,
      mode: execution.mode,
      notes: execution.notes,
      substitutedFor: source.substitutedFor ?? source.exerciseKey,
      sets: execution.sets,
    );
    setState(() {
      _actual[exerciseIndex] = substituted;
      _readyToFinish = false;
    });
    _moveTo(exerciseIndex, 0);
    _scheduleDraftSave();
  }

  void _addSet() {
    final t = AirmiusScope.of(context).t;
    if (_workoutEntryCount(_actual) >= 40) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('workout.entryLimit'))));
      return;
    }
    final positions = _positions;
    if (positions.isEmpty) return;
    final position = positions[_cursor.clamp(0, positions.length - 1)];
    final exercise = _actual[position.exerciseIndex];
    final added = exercise.sets[position.setIndex].copy(completed: false);
    added.skipReason = '';
    setState(() {
      exercise.sets.add(added);
      _readyToFinish = false;
    });
    _moveTo(position.exerciseIndex, exercise.sets.length - 1);
    _scheduleDraftSave();
  }

  Future<void> _finish() async {
    final result = await showModalBottomSheet<_WorkoutCompletionResult>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => _WorkoutCompletionSheet(
        completedSets: _completedCount,
        totalSets: _workoutEntryCount(_actual),
      ),
    );
    if (result == null || !mounted) return;
    await _persistDraft();
    if (!mounted) return;
    final elapsedMinutes = (_elapsedSeconds + 59) ~/ 60;
    final allCompleted = _actual.every(
      (exercise) => exercise.sets.every((set) => set.completed),
    );
    Navigator.pop(context, <String, dynamic>{
      'title': widget.item.title,
      'training_plan_item_id': widget.item.id,
      'sport_type': widget.item.sportType,
      'sport_route_id': widget.item.sportRoute?.id,
      'status': allCompleted ? 'completed' : 'partial',
      'performed_at': _startedAt.toIso8601String(),
      'duration_minutes': elapsedMinutes < 1 ? 1 : elapsedMinutes,
      'distance_km': widget.item.distanceMeters == null
          ? null
          : widget.item.distanceMeters! / 1000,
      'intensity': result.rpe <= 3
          ? 'locker'
          : (result.rpe >= 8 ? 'hart' : 'mittel'),
      'privacy_scope': 'trainer',
      'notes': result.notes,
      'wellness': {'rpe': result.rpe, 'pain': result.pain},
      'entries': [
        for (final exercise in _actual) ...exercise.toPayloadEntries(),
      ],
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    if (_restoringDraft) {
      return Scaffold(
        appBar: AppBar(title: Text(widget.item.title)),
        body: const Center(child: CircularProgressIndicator()),
      );
    }
    final positions = _positions;
    final total = positions.length;
    final progress = total == 0 ? 0.0 : _visitedCount / total;
    if (positions.isEmpty) {
      return Scaffold(
        appBar: AppBar(title: Text(widget.item.title)),
        body: Center(child: Text(t('workout.empty'))),
      );
    }
    if (_cursor >= positions.length) _cursor = positions.length - 1;
    final position = positions[_cursor];
    final exercise = _actual[position.exerciseIndex];
    final actualSet = exercise.sets[position.setIndex];
    final plannedSet = _plannedSet(position);
    final accent = Theme.of(context).colorScheme.primary;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) unawaited(_confirmExit());
      },
      child: Scaffold(
        appBar: AppBar(
          leading: IconButton(
            tooltip: t('auth2fa.cancel'),
            onPressed: _confirmExit,
            icon: const Icon(Icons.close),
          ),
          title: Text(widget.item.title, overflow: TextOverflow.ellipsis),
          actions: [
            IconButton(
              tooltip: t('liveWorkout.finish'),
              onPressed: _finish,
              icon: const Icon(Icons.flag_outlined),
            ),
          ],
        ),
        body: SafeArea(
          bottom: false,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 120),
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      t('liveWorkout.progress')
                          .replaceFirst('{done}', '$_visitedCount')
                          .replaceFirst('{total}', '$total'),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                  ),
                  Text(
                    _formatWorkoutClock(_elapsedSeconds),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              LinearProgressIndicator(value: progress, minHeight: 8),
              const SizedBox(height: 7),
              Row(
                children: [
                  Icon(
                    Icons.save_outlined,
                    size: 16,
                    color: airmiusMutedColor(context),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    t(
                      _lastSavedAt == null
                          ? 'liveWorkout.autosaveActive'
                          : 'liveWorkout.autosaved',
                    ),
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: airmiusMutedColor(context),
                    ),
                  ),
                ],
              ),
              if (_restActive) ...[
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: accent.withValues(alpha: .10),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: accent.withValues(alpha: .28)),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.hourglass_bottom_outlined, color: accent),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              t('liveWorkout.rest'),
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            Text(
                              _formatWorkoutClock(_restRemaining),
                              style: Theme.of(context).textTheme.headlineSmall
                                  ?.copyWith(fontWeight: FontWeight.w900),
                            ),
                          ],
                        ),
                      ),
                      IconButton.filledTonal(
                        tooltip: t('liveWorkout.add30'),
                        onPressed: () => setState(() => _restRemaining += 30),
                        icon: const Icon(Icons.add_alarm_outlined),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 20),
              Text(
                t('liveWorkout.exerciseProgress')
                    .replaceFirst('{current}', '${position.exerciseIndex + 1}')
                    .replaceFirst('{total}', '${_actual.length}'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 5),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      exercise.title,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filledTonal(
                    tooltip: t('liveWorkout.replaceExercise'),
                    onPressed: _replaceExercise,
                    icon: const Icon(Icons.swap_horiz),
                  ),
                ],
              ),
              if (exercise.substitutedFor != null) ...[
                const SizedBox(height: 4),
                Text(
                  t('liveWorkout.replacementActive'),
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.primary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
              if (exercise.notes.isNotEmpty) ...[
                const SizedBox(height: 6),
                Text(exercise.notes),
              ],
              const SizedBox(height: 14),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    for (
                      var setIndex = 0;
                      setIndex < exercise.sets.length;
                      setIndex++
                    ) ...[
                      if (setIndex > 0) const SizedBox(width: 7),
                      ChoiceChip(
                        selected: position.setIndex == setIndex,
                        avatar: Icon(
                          exercise.sets[setIndex].completed
                              ? Icons.check_circle
                              : (exercise.sets[setIndex].skipReason.isNotEmpty
                                    ? Icons.skip_next
                                    : Icons.radio_button_unchecked),
                          size: 18,
                        ),
                        label: Text('${t('workout.set')} ${setIndex + 1}'),
                        onSelected: (_) =>
                            _moveTo(position.exerciseIndex, setIndex),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 20),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Theme.of(
                    context,
                  ).colorScheme.surfaceContainerHighest.withValues(alpha: .35),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Theme.of(context).dividerColor),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _WorkoutComparisonLine(
                      label: t('workout.planned'),
                      value: plannedSet == null
                          ? t('liveWorkout.extraSet')
                          : _workoutSetSummary(plannedSet, exercise.mode, t),
                    ),
                    const Divider(height: 24),
                    _WorkoutComparisonLine(
                      label: t('workout.actual'),
                      value: _workoutSetSummary(actualSet, exercise.mode, t),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: _editActualSet,
                      icon: const Icon(Icons.edit_outlined),
                      label: Text(t('liveWorkout.editActual')),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextButton.icon(
                      onPressed: _skipSet,
                      icon: const Icon(Icons.skip_next_outlined),
                      label: Text(t('liveWorkout.skipSet')),
                    ),
                  ),
                  Expanded(
                    child: TextButton.icon(
                      onPressed: _addSet,
                      icon: const Icon(Icons.add),
                      label: Text(t('liveWorkout.addSet')),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        bottomNavigationBar: SafeArea(
          minimum: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: FilledButton.icon(
            onPressed: _restActive
                ? () => setState(() => _restActive = false)
                : (_readyToFinish ? _finish : _completeSet),
            icon: Icon(
              _restActive
                  ? Icons.skip_next
                  : (_readyToFinish ? Icons.flag : Icons.check),
            ),
            label: Text(
              t(
                _restActive
                    ? 'liveWorkout.endRest'
                    : (_readyToFinish
                          ? 'liveWorkout.finish'
                          : 'liveWorkout.completeSet'),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _WorkoutReplacementSheet extends StatefulWidget {
  const _WorkoutReplacementSheet({required this.templates});

  final List<Map<String, dynamic>> templates;

  @override
  State<_WorkoutReplacementSheet> createState() =>
      _WorkoutReplacementSheetState();
}

class _WorkoutReplacementSheetState extends State<_WorkoutReplacementSheet> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final normalized = _query.trim().toLowerCase();
    final templates = widget.templates.where((template) {
      if (normalized.isEmpty) return true;
      return '${template['name']} ${template['sport_type']}'
          .toLowerCase()
          .contains(normalized);
    }).toList();

    return SizedBox(
      height: MediaQuery.sizeOf(context).height * .78,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            child: Text(
              t('liveWorkout.selectReplacement'),
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: TextField(
              autofocus: true,
              decoration: InputDecoration(
                labelText: t('liveWorkout.searchReplacement'),
                prefixIcon: const Icon(Icons.search),
              ),
              onChanged: (value) => setState(() => _query = value),
            ),
          ),
          const SizedBox(height: 8),
          ListTile(
            leading: const Icon(Icons.add_circle_outline),
            title: Text(
              t('liveWorkout.customReplacement'),
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            onTap: () =>
                Navigator.pop(context, <String, dynamic>{'custom': true}),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView.separated(
              itemCount: templates.length,
              separatorBuilder: (_, _) => const Divider(height: 1),
              itemBuilder: (context, index) {
                final template = templates[index];
                final mode = template['suggested_mode']?.toString() ?? 'reps';
                return ListTile(
                  leading: Icon(_workoutModeIcon(mode)),
                  title: Text(
                    template['name']?.toString() ?? '',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  subtitle: Text(
                    [
                      template['sport_type']?.toString() ?? '',
                      t('workout.mode.$mode'),
                    ].where((value) => value.isNotEmpty).join(' · '),
                  ),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.pop(context, template),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _LiveSetEditorSheet extends StatefulWidget {
  const _LiveSetEditorSheet({required this.mode, required this.initial});

  final String mode;
  final _WorkoutSetDraft initial;

  @override
  State<_LiveSetEditorSheet> createState() => _LiveSetEditorSheetState();
}

class _LiveSetEditorSheetState extends State<_LiveSetEditorSheet> {
  late final _WorkoutSetDraft _set;

  @override
  void initState() {
    super.initState();
    _set = widget.initial.copy();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    InputDecoration decoration(String label, String suffix) =>
        InputDecoration(labelText: label, suffixText: suffix);
    return Padding(
      padding: EdgeInsets.fromLTRB(
        16,
        0,
        16,
        MediaQuery.viewInsetsOf(context).bottom + 16,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              t('liveWorkout.editActual'),
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 14),
            if (widget.mode == 'reps') ...[
              TextFormField(
                initialValue: _set.reps,
                keyboardType: TextInputType.number,
                decoration: decoration(
                  t('workout.repetitions'),
                  t('workout.repsShort'),
                ),
                onChanged: (value) => _set.reps = value,
              ),
              const SizedBox(height: 10),
              TextFormField(
                initialValue: _set.weightKg,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: decoration(t('workout.weight'), 'kg'),
                onChanged: (value) => _set.weightKg = value,
              ),
            ],
            if (widget.mode == 'time')
              TextFormField(
                initialValue: _set.durationMinutes,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: decoration(t('workout.duration'), 'min'),
                onChanged: (value) => _set.durationMinutes = value,
              ),
            if (widget.mode == 'distance') ...[
              TextFormField(
                initialValue: _set.distanceKm,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: decoration(t('workout.distance'), 'km'),
                onChanged: (value) => _set.distanceKm = value,
              ),
              const SizedBox(height: 10),
              TextFormField(
                initialValue: _set.durationMinutes,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: decoration(t('workout.duration'), 'min'),
                onChanged: (value) => _set.durationMinutes = value,
              ),
            ],
            if (widget.mode == 'rounds') ...[
              TextFormField(
                initialValue: _set.rounds,
                keyboardType: TextInputType.number,
                decoration: decoration(t('workout.rounds'), ''),
                onChanged: (value) => _set.rounds = value,
              ),
              const SizedBox(height: 10),
              TextFormField(
                initialValue: _set.durationMinutes,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: decoration(t('workout.duration'), 'min'),
                onChanged: (value) => _set.durationMinutes = value,
              ),
            ],
            const SizedBox(height: 10),
            TextFormField(
              initialValue: _set.restSeconds,
              keyboardType: TextInputType.number,
              decoration: decoration(
                t('workout.rest'),
                t('workout.secondsShort'),
              ),
              onChanged: (value) => _set.restSeconds = value,
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: () => Navigator.pop(context, _set),
              icon: const Icon(Icons.check),
              label: Text(t('workout.saveExercise')),
            ),
          ],
        ),
      ),
    );
  }
}

class _WorkoutCompletionResult {
  const _WorkoutCompletionResult({
    required this.rpe,
    required this.pain,
    required this.notes,
  });

  final int rpe;
  final int pain;
  final String notes;
}

class _WorkoutCompletionSheet extends StatefulWidget {
  const _WorkoutCompletionSheet({
    required this.completedSets,
    required this.totalSets,
  });

  final int completedSets;
  final int totalSets;

  @override
  State<_WorkoutCompletionSheet> createState() =>
      _WorkoutCompletionSheetState();
}

class _WorkoutCompletionSheetState extends State<_WorkoutCompletionSheet> {
  final _notes = TextEditingController();
  double _rpe = 5;
  double _pain = 0;

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: EdgeInsets.fromLTRB(
        16,
        0,
        16,
        MediaQuery.viewInsetsOf(context).bottom + 16,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              t('liveWorkout.completionTitle'),
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 6),
            Text(
              t('liveWorkout.completionSets')
                  .replaceFirst('{done}', '${widget.completedSets}')
                  .replaceFirst('{total}', '${widget.totalSets}'),
            ),
            const SizedBox(height: 16),
            Text('${t('trainingHub.rpe')}: ${_rpe.round()}/10'),
            Slider(
              value: _rpe,
              min: 1,
              max: 10,
              divisions: 9,
              label: '${_rpe.round()}',
              onChanged: (value) => setState(() => _rpe = value),
            ),
            Text('${t('liveWorkout.pain')}: ${_pain.round()}/10'),
            Slider(
              value: _pain,
              min: 0,
              max: 10,
              divisions: 10,
              label: '${_pain.round()}',
              onChanged: (value) => setState(() => _pain = value),
            ),
            if (_pain >= 4)
              Text(
                t('liveWorkout.painHint'),
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontWeight: FontWeight.w800,
                ),
              ),
            const SizedBox(height: 10),
            TextField(
              controller: _notes,
              minLines: 2,
              maxLines: 4,
              decoration: InputDecoration(labelText: t('liveWorkout.notes')),
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: () => Navigator.pop(
                context,
                _WorkoutCompletionResult(
                  rpe: _rpe.round(),
                  pain: _pain.round(),
                  notes: _notes.text.trim(),
                ),
              ),
              icon: const Icon(Icons.flag),
              label: Text(t('liveWorkout.saveCompletion')),
            ),
          ],
        ),
      ),
    );
  }
}

String _formatWorkoutClock(int seconds) {
  final safe = seconds < 0 ? 0 : seconds;
  final minutes = safe ~/ 60;
  final remainder = safe % 60;
  return '${minutes.toString().padLeft(2, '0')}:'
      '${remainder.toString().padLeft(2, '0')}';
}

class _LogFormDialog extends StatefulWidget {
  const _LogFormDialog({
    this.initial,
    this.prefillTitle,
    this.prefillNotes,
    this.prefillSportRouteId,
    this.prefillSportRouteTitle,
  });

  final _TrainingLog? initial;
  final String? prefillTitle;
  final String? prefillNotes;
  final int? prefillSportRouteId;
  final String? prefillSportRouteTitle;

  @override
  State<_LogFormDialog> createState() => _LogFormDialogState();
}

class _LogFormDialogState extends State<_LogFormDialog> {
  late final TextEditingController _title;
  late final TextEditingController _sport;
  late final TextEditingController _duration;
  late final TextEditingController _distance;
  late final TextEditingController _notes;
  late String _intensity;
  late String _privacy;
  late List<_WorkoutExerciseDraft> _exercises;
  double _rpe = 5;
  int? _sportRouteId;
  int? _sportRouteTrackId;
  List<_TrainingRouteReference> _routes = const [];
  List<_TrainingTrackReference> _tracks = const [];
  bool _routeChoicesLoading = false;
  bool _routeChoicesLoaded = false;

  @override
  void initState() {
    super.initState();
    final initial = widget.initial;
    _title = TextEditingController(
      text: initial?.title ?? widget.prefillTitle ?? '',
    );
    _sport = TextEditingController(text: initial?.sportType ?? '');
    _duration = TextEditingController(
      text: initial?.durationMinutes?.toString() ?? '',
    );
    _distance = TextEditingController(
      text: initial?.distanceMeters == null
          ? ''
          : (initial!.distanceMeters! / 1000).toStringAsFixed(1),
    );
    _notes = TextEditingController(
      text: initial?.notes ?? widget.prefillNotes ?? '',
    );
    _intensity = initial?.intensity ?? 'mittel';
    _privacy = initial?.privacyScope ?? 'trainer';
    _exercises = initial == null
        ? []
        : _workoutExercisesFromEntries(initial.entries);
    _rpe = initial?.rpe?.toDouble() ?? 5;
    _sportRouteId = initial?.sportRoute?.id ?? widget.prefillSportRouteId;
    _sportRouteTrackId = initial?.sportRouteTrack?.id;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_routeChoicesLoaded && !_routeChoicesLoading) {
      _loadRouteChoices();
    }
  }

  Future<void> _loadRouteChoices() async {
    setState(() => _routeChoicesLoading = true);
    try {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      final routeOptions = _singleData(await client.trainingRouteOptions());
      if (!mounted) return;
      setState(() {
        _routes = _mapList(routeOptions['routes'])
            .map(_TrainingRouteReference.fromJson)
            .where((route) => route.id > 0)
            .toList();
        _tracks = _mapList(routeOptions['tracks'])
            .map(_TrainingTrackReference.fromJson)
            .where((track) => track.id > 0)
            .toList();
        _routeChoicesLoaded = true;
        _routeChoicesLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _routeChoicesLoaded = true;
        _routeChoicesLoading = false;
      });
    }
  }

  @override
  void dispose() {
    _title.dispose();
    _sport.dispose();
    _duration.dispose();
    _distance.dispose();
    _notes.dispose();
    super.dispose();
  }

  InputDecoration _inputDecoration(
    BuildContext context,
    String label, {
    IconData? icon,
    String? helperText,
  }) {
    return InputDecoration(
      labelText: label,
      helperText: helperText,
      prefixIcon: icon == null ? null : Icon(icon, size: 20),
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
    );
  }

  Map<String, dynamic> _payload() => {
    'title': _title.text.trim(),
    'training_plan_item_id': widget.initial?.trainingPlanItemId,
    'sport_type': _sport.text.trim(),
    'sport_route_id': _sportRouteId,
    'sport_route_track_id': _sportRouteTrackId,
    'status': 'completed',
    'performed_at':
        widget.initial?.performedAt?.toIso8601String() ??
        DateTime.now().toIso8601String(),
    'duration_minutes': int.tryParse(_duration.text),
    'distance_km': double.tryParse(_distance.text.replaceAll(',', '.')),
    'intensity': _intensity,
    'privacy_scope': _privacy,
    'notes': _notes.text.trim(),
    'wellness': {'rpe': _rpe.round()},
    'entries': [
      for (final exercise in _exercises) ...exercise.toPayloadEntries(),
    ],
  };

  Future<void> _editExercise({
    _WorkoutExerciseDraft? exercise,
    int? index,
  }) async {
    final result = await showDialog<_WorkoutExerciseDraft>(
      context: context,
      builder: (_) => _WorkoutExerciseDialog(initial: exercise),
    );
    if (result == null || !mounted) return;
    setState(() {
      if (index == null) {
        _exercises.add(result);
      } else {
        _exercises[index] = result;
      }
    });
  }

  Future<void> _addExerciseFromLibrary() async {
    final t = AirmiusScope.of(context).t;
    try {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      List<Map<String, dynamic>> savedExercises = const [];
      try {
        savedExercises = _dataList(await client.trainingExercises());
      } on AirmiusApiException {
        // Starter templates remain available when the personal library is offline.
      }
      final exercises = [..._builtInWorkoutTemplates(t), ...savedExercises];
      if (!mounted) return;
      if (exercises.isEmpty) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(t('workout.libraryEmpty'))));
        return;
      }
      final selected = await showModalBottomSheet<Map<String, dynamic>>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        builder: (_) => _WorkoutExercisePickerSheet(exercises: exercises),
      );
      if (selected == null || !mounted) return;
      await _editExercise(
        exercise: _WorkoutExerciseDraft.fromTemplate(selected),
      );
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    }
  }

  void _submit() {
    if (_title.text.trim().isEmpty) return;
    Navigator.pop(context, _payload());
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final title = t(
      widget.initial == null ? 'trainingHub.addLog' : 'trainingHub.editLog',
    );
    final workoutEntryCount = _workoutEntryCount(_exercises);
    final canSave = _title.text.trim().isNotEmpty && workoutEntryCount <= 40;
    final accent = Theme.of(context).colorScheme.primary;
    return Dialog.fullscreen(
      child: Scaffold(
        appBar: AppBar(
          leading: IconButton(
            tooltip: t('auth2fa.cancel'),
            icon: const Icon(Icons.close),
            onPressed: () => Navigator.pop(context),
          ),
          title: Text(title),
        ),
        body: SafeArea(
          bottom: false,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            children: [
              _LogFormSummary(
                title: title,
                intensity: t('trainingHub.intensity.$_intensity'),
                rpe: _rpe.round(),
                exerciseCount: _exercises.length,
              ),
              const SizedBox(height: 12),
              _LogFormSection(
                icon: Icons.edit_note_outlined,
                title: t('trainingHub.logBasics'),
                subtitle: t('trainingHub.logBasicsHint'),
                children: [
                  TextField(
                    controller: _title,
                    autofocus: widget.initial == null,
                    textInputAction: TextInputAction.next,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                    onChanged: (_) => setState(() {}),
                    decoration: _inputDecoration(
                      context,
                      t('trainingHub.logTitle'),
                      icon: Icons.title_outlined,
                    ),
                  ),
                  TextField(
                    controller: _sport,
                    textInputAction: TextInputAction.next,
                    style: const TextStyle(fontSize: 16),
                    decoration: _inputDecoration(
                      context,
                      t('trainingHub.sport'),
                      icon: Icons.sports_outlined,
                    ),
                  ),
                  TextField(
                    controller: _duration,
                    keyboardType: TextInputType.number,
                    textInputAction: TextInputAction.next,
                    style: const TextStyle(fontSize: 16),
                    decoration: _inputDecoration(
                      context,
                      t('trainingHub.duration'),
                      icon: Icons.timer_outlined,
                    ),
                  ),
                  TextField(
                    controller: _distance,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    textInputAction: TextInputAction.next,
                    style: const TextStyle(fontSize: 16),
                    decoration: _inputDecoration(
                      context,
                      t('trainingHub.distance'),
                      icon: Icons.straighten_outlined,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              _LogFormSection(
                icon: Icons.fitness_center_outlined,
                title: t('workout.title'),
                subtitle: t('workout.subtitle'),
                children: [
                  if (_exercises.isEmpty)
                    _WorkoutEmptyState(
                      onAdd: () => _editExercise(),
                      onLibrary: _addExerciseFromLibrary,
                    )
                  else ...[
                    for (var index = 0; index < _exercises.length; index++) ...[
                      if (index > 0) const Divider(height: 1),
                      _WorkoutExerciseRow(
                        exercise: _exercises[index],
                        onEdit: () => _editExercise(
                          exercise: _exercises[index],
                          index: index,
                        ),
                        onDelete: () =>
                            setState(() => _exercises.removeAt(index)),
                      ),
                    ],
                    if (workoutEntryCount > 40)
                      Text(
                        t('workout.entryLimit'),
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    Row(
                      children: [
                        Expanded(
                          child: FilledButton.tonalIcon(
                            onPressed: () => _editExercise(),
                            icon: const Icon(Icons.add),
                            label: Text(t('workout.addExercise')),
                          ),
                        ),
                        const SizedBox(width: 8),
                        IconButton.filledTonal(
                          tooltip: t('workout.fromLibrary'),
                          onPressed: _addExerciseFromLibrary,
                          icon: const Icon(Icons.menu_book_outlined),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 12),
              _LogFormSection(
                icon: Icons.route_outlined,
                title: t('trainingHub.routeAndTrack'),
                subtitle: t('trainingHub.locationMinimized'),
                children: [
                  if (_routeChoicesLoading) const LinearProgressIndicator(),
                  DropdownButtonFormField<int?>(
                    initialValue: _sportRouteId,
                    isExpanded: true,
                    decoration: _inputDecoration(
                      context,
                      t('trainingHub.route'),
                      icon: Icons.route_outlined,
                    ),
                    items: [
                      DropdownMenuItem<int?>(
                        value: null,
                        child: Text(t('trainingHub.noRoute')),
                      ),
                      if (_sportRouteId != null &&
                          !_routes.any((route) => route.id == _sportRouteId))
                        DropdownMenuItem<int?>(
                          value: _sportRouteId,
                          child: Text(
                            widget.initial?.sportRoute?.title ??
                                widget.prefillSportRouteTitle ??
                                t('trainingHub.route'),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ..._routes.map(
                        (route) => DropdownMenuItem<int?>(
                          value: route.id,
                          child: Text(
                            route.title,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ),
                    ],
                    onChanged: (value) {
                      final matchingTracks = _tracks.where(
                        (track) => track.id == _sportRouteTrackId,
                      );
                      final selectedTrack = matchingTracks.isEmpty
                          ? null
                          : matchingTracks.first;
                      setState(() {
                        _sportRouteId = value;
                        if (selectedTrack?.routeId != null &&
                            value != null &&
                            selectedTrack!.routeId != value) {
                          _sportRouteTrackId = null;
                        }
                      });
                    },
                  ),
                  DropdownButtonFormField<int?>(
                    initialValue: _sportRouteTrackId,
                    isExpanded: true,
                    decoration: _inputDecoration(
                      context,
                      t('trainingHub.gpsTrack'),
                      icon: Icons.gps_fixed_outlined,
                      helperText: t('trainingHub.trackOwnerHint'),
                    ),
                    items: [
                      DropdownMenuItem<int?>(
                        value: null,
                        child: Text(t('trainingHub.noTrack')),
                      ),
                      if (_sportRouteTrackId != null &&
                          !_tracks.any(
                            (track) => track.id == _sportRouteTrackId,
                          ))
                        DropdownMenuItem<int?>(
                          value: _sportRouteTrackId,
                          child: Text(
                            widget.initial?.sportRouteTrack?.title ??
                                t('trainingHub.gpsTrack'),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ..._tracks
                          .where(
                            (track) =>
                                _sportRouteId == null ||
                                track.routeId == null ||
                                track.routeId == _sportRouteId,
                          )
                          .map(
                            (track) => DropdownMenuItem<int?>(
                              value: track.id,
                              child: Text(
                                track.title,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ),
                    ],
                    onChanged: (value) =>
                        setState(() => _sportRouteTrackId = value),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              _LogFormSection(
                icon: Icons.monitor_heart_outlined,
                title: t('trainingHub.logLoad'),
                subtitle: t('trainingHub.logLoadHint'),
                children: [
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['locker', 'mittel', 'hart', 'recovery']
                        .map(
                          (value) => ChoiceChip(
                            selected: _intensity == value,
                            label: Text(t('trainingHub.intensity.$value')),
                            onSelected: (_) =>
                                setState(() => _intensity = value),
                          ),
                        )
                        .toList(),
                  ),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['private', 'trainer', 'team']
                        .map(
                          (value) => ChoiceChip(
                            selected: _privacy == value,
                            label: Text(t('trainingHub.privacy.$value')),
                            onSelected: (_) => setState(() => _privacy = value),
                          ),
                        )
                        .toList(),
                  ),
                  Container(
                    padding: const EdgeInsets.fromLTRB(12, 10, 12, 4),
                    decoration: BoxDecoration(
                      color: accent.withValues(alpha: .08),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: accent.withValues(alpha: .2)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                t('trainingHub.rpe'),
                                style: const TextStyle(
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                            StatusPill('${_rpe.round()}/10', color: accent),
                          ],
                        ),
                        Slider(
                          value: _rpe,
                          min: 1,
                          max: 10,
                          divisions: 9,
                          label: '${_rpe.round()}',
                          onChanged: (value) => setState(() => _rpe = value),
                        ),
                      ],
                    ),
                  ),
                  TextField(
                    controller: _notes,
                    minLines: 3,
                    maxLines: 5,
                    style: const TextStyle(fontSize: 16),
                    decoration: _inputDecoration(
                      context,
                      t('trainingHub.notes'),
                      icon: Icons.notes_outlined,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        bottomNavigationBar: SafeArea(
          minimum: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close),
                  label: Text(t('auth2fa.cancel')),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton.icon(
                  onPressed: canSave ? _submit : null,
                  icon: const Icon(Icons.check),
                  label: Text(t('trainingHub.save')),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _WorkoutExerciseDialog extends StatefulWidget {
  const _WorkoutExerciseDialog({this.initial, this.isPlanning = false});

  final _WorkoutExerciseDraft? initial;
  final bool isPlanning;

  @override
  State<_WorkoutExerciseDialog> createState() => _WorkoutExerciseDialogState();
}

class _WorkoutExerciseDialogState extends State<_WorkoutExerciseDialog> {
  late final TextEditingController _title;
  late final TextEditingController _notes;
  late String _exerciseKey;
  late String _mode;
  late List<_WorkoutSetDraft> _sets;
  int _activeSet = 0;
  bool _showNameError = false;

  static const _modes = ['reps', 'time', 'distance', 'rounds'];

  @override
  void initState() {
    super.initState();
    final initial = widget.initial ?? _WorkoutExerciseDraft.create();
    _title = TextEditingController(text: initial.title);
    _notes = TextEditingController(text: initial.notes);
    _exerciseKey = initial.exerciseKey;
    _mode = _modes.contains(initial.mode) ? initial.mode : 'reps';
    _sets = initial.sets
        .map(
          (set) =>
              set.copy(completed: widget.isPlanning ? false : set.completed),
        )
        .toList();
    if (_sets.isEmpty) _sets.add(_WorkoutSetDraft.defaults());
  }

  @override
  void dispose() {
    _title.dispose();
    _notes.dispose();
    super.dispose();
  }

  InputDecoration _decoration(String label, IconData icon, {String? suffix}) {
    return InputDecoration(
      labelText: label,
      prefixIcon: Icon(icon, size: 20),
      suffixText: suffix,
      isDense: true,
    );
  }

  Widget _metricPair(Widget first, Widget second) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 390) {
          return Column(children: [first, const SizedBox(height: 10), second]);
        }
        return Row(
          children: [
            Expanded(child: first),
            const SizedBox(width: 10),
            Expanded(child: second),
          ],
        );
      },
    );
  }

  void _addSet() {
    if (_sets.length >= 20) return;
    setState(() {
      _sets.add(_sets.last.copy(completed: false));
      _activeSet = _sets.length - 1;
    });
  }

  void _removeActiveSet() {
    if (_sets.length == 1) return;
    setState(() {
      _sets.removeAt(_activeSet);
      if (_activeSet >= _sets.length) _activeSet = _sets.length - 1;
    });
  }

  void _save() {
    if (_title.text.trim().isEmpty) {
      setState(() => _showNameError = true);
      return;
    }
    Navigator.pop(
      context,
      _WorkoutExerciseDraft(
        exerciseKey: _exerciseKey,
        title: _title.text.trim(),
        mode: _mode,
        notes: _notes.text.trim(),
        sets: _sets.map((set) => set.copy()).toList(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final accent = Theme.of(context).colorScheme.primary;
    final active = _sets[_activeSet];
    final modeIndex = _modes.indexOf(_mode);
    return Dialog.fullscreen(
      child: Scaffold(
        appBar: AppBar(
          leading: IconButton(
            tooltip: t('auth2fa.cancel'),
            onPressed: () => Navigator.pop(context),
            icon: const Icon(Icons.close),
          ),
          title: Text(
            t(
              widget.initial == null
                  ? 'workout.newExercise'
                  : 'workout.editExercise',
            ),
          ),
          actions: [
            IconButton(
              tooltip: t('workout.saveExercise'),
              onPressed: _save,
              icon: const Icon(Icons.check),
            ),
            const SizedBox(width: 8),
          ],
        ),
        body: SafeArea(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              TextField(
                controller: _title,
                autofocus: widget.initial == null,
                textCapitalization: TextCapitalization.sentences,
                decoration:
                    _decoration(
                      t('workout.exerciseName'),
                      Icons.fitness_center_outlined,
                    ).copyWith(
                      hintText: t('workout.exerciseHint'),
                      errorText: _showNameError
                          ? t('workout.exerciseRequired')
                          : null,
                    ),
                onChanged: (_) {
                  if (_showNameError) setState(() => _showNameError = false);
                },
              ),
              const SizedBox(height: 20),
              Text(
                t('workout.trackingMode'),
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 8),
              Container(
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.surfaceContainerHighest,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: DefaultTabController(
                  key: ValueKey('mode-$_mode'),
                  length: _modes.length,
                  initialIndex: modeIndex < 0 ? 0 : modeIndex,
                  child: TabBar(
                    isScrollable: true,
                    dividerColor: Colors.transparent,
                    onTap: (index) => setState(() => _mode = _modes[index]),
                    tabs: [
                      for (final mode in _modes)
                        Tab(
                          icon: Icon(_workoutModeIcon(mode), size: 19),
                          text: t('workout.mode.$mode'),
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 22),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      t('workout.sets'),
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  IconButton.filledTonal(
                    tooltip: t('workout.addSet'),
                    onPressed: _sets.length >= 20 ? null : _addSet,
                    icon: const Icon(Icons.add),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Container(
                decoration: BoxDecoration(
                  border: Border.all(color: Theme.of(context).dividerColor),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: DefaultTabController(
                  key: ValueKey('sets-${_sets.length}-$_activeSet'),
                  length: _sets.length,
                  initialIndex: _activeSet,
                  child: TabBar(
                    isScrollable: true,
                    dividerColor: Colors.transparent,
                    onTap: (index) => setState(() => _activeSet = index),
                    tabs: [
                      for (var index = 0; index < _sets.length; index++)
                        Tab(
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                !widget.isPlanning && _sets[index].completed
                                    ? Icons.check_circle
                                    : Icons.radio_button_unchecked,
                                size: 17,
                                color:
                                    !widget.isPlanning && _sets[index].completed
                                    ? accent
                                    : null,
                              ),
                              const SizedBox(width: 6),
                              Text('${t('workout.set')} ${index + 1}'),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: .06),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: accent.withValues(alpha: .18)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_mode == 'reps')
                      _metricPair(
                        TextFormField(
                          key: ValueKey('reps-$_activeSet'),
                          initialValue: active.reps,
                          keyboardType: TextInputType.number,
                          decoration: _decoration(
                            t('workout.repetitions'),
                            Icons.repeat_rounded,
                            suffix: t('workout.repsShort'),
                          ),
                          onChanged: (value) => active.reps = value,
                        ),
                        TextFormField(
                          key: ValueKey('weight-$_activeSet'),
                          initialValue: active.weightKg,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          decoration: _decoration(
                            t('workout.weight'),
                            Icons.monitor_weight_outlined,
                            suffix: 'kg',
                          ),
                          onChanged: (value) => active.weightKg = value,
                        ),
                      ),
                    if (_mode == 'time')
                      TextFormField(
                        key: ValueKey('time-$_activeSet'),
                        initialValue: active.durationMinutes,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: _decoration(
                          t('workout.duration'),
                          Icons.timer_outlined,
                          suffix: 'min',
                        ),
                        onChanged: (value) => active.durationMinutes = value,
                      ),
                    if (_mode == 'distance')
                      _metricPair(
                        TextFormField(
                          key: ValueKey('distance-$_activeSet'),
                          initialValue: active.distanceKm,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          decoration: _decoration(
                            t('workout.distance'),
                            Icons.straighten_outlined,
                            suffix: 'km',
                          ),
                          onChanged: (value) => active.distanceKm = value,
                        ),
                        TextFormField(
                          key: ValueKey('distance-time-$_activeSet'),
                          initialValue: active.durationMinutes,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          decoration: _decoration(
                            t('workout.duration'),
                            Icons.timer_outlined,
                            suffix: 'min',
                          ),
                          onChanged: (value) => active.durationMinutes = value,
                        ),
                      ),
                    if (_mode == 'rounds')
                      _metricPair(
                        TextFormField(
                          key: ValueKey('rounds-$_activeSet'),
                          initialValue: active.rounds,
                          keyboardType: TextInputType.number,
                          decoration: _decoration(
                            t('workout.rounds'),
                            Icons.autorenew,
                          ),
                          onChanged: (value) => active.rounds = value,
                        ),
                        TextFormField(
                          key: ValueKey('rounds-time-$_activeSet'),
                          initialValue: active.durationMinutes,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          decoration: _decoration(
                            t('workout.duration'),
                            Icons.timer_outlined,
                            suffix: 'min',
                          ),
                          onChanged: (value) => active.durationMinutes = value,
                        ),
                      ),
                    const SizedBox(height: 12),
                    TextFormField(
                      key: ValueKey('rest-$_activeSet'),
                      initialValue: active.restSeconds,
                      keyboardType: TextInputType.number,
                      decoration: _decoration(
                        t('workout.rest'),
                        Icons.hourglass_bottom_outlined,
                        suffix: 's',
                      ),
                      onChanged: (value) => active.restSeconds = value,
                    ),
                    const SizedBox(height: 8),
                    if (!widget.isPlanning)
                      SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(
                          t('workout.completed'),
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        subtitle: Text(t('workout.completedHint')),
                        value: active.completed,
                        onChanged: (value) =>
                            setState(() => active.completed = value),
                      ),
                    if (_sets.length > 1)
                      Align(
                        alignment: AlignmentDirectional.centerEnd,
                        child: TextButton.icon(
                          onPressed: _removeActiveSet,
                          icon: const Icon(Icons.delete_outline),
                          label: Text(t('workout.removeSet')),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _notes,
                minLines: 2,
                maxLines: 5,
                decoration: _decoration(
                  t('workout.exerciseNotes'),
                  Icons.notes_outlined,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

Future<_WorkoutExerciseDraft?> _chooseWorkoutExercise(
  BuildContext context,
) async {
  final t = AirmiusScope.of(context).t;
  List<Map<String, dynamic>> savedExercises = const [];
  try {
    final services = AirmiusServicesScope.of(context);
    final client = services.clientForSession(services.authState.session);
    savedExercises = _dataList(await client.trainingExercises());
  } on AirmiusApiException {
    // The built-in sport templates remain available while offline.
  }
  if (!context.mounted) return null;

  final selected = await showModalBottomSheet<Map<String, dynamic>>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => _WorkoutExercisePickerSheet(
      exercises: [..._builtInWorkoutTemplates(t), ...savedExercises],
    ),
  );
  return selected == null ? null : _WorkoutExerciseDraft.fromTemplate(selected);
}

class _WorkoutExercisePickerSheet extends StatefulWidget {
  const _WorkoutExercisePickerSheet({required this.exercises});

  final List<Map<String, dynamic>> exercises;

  @override
  State<_WorkoutExercisePickerSheet> createState() =>
      _WorkoutExercisePickerSheetState();
}

class _WorkoutExercisePickerSheetState
    extends State<_WorkoutExercisePickerSheet> {
  final _search = TextEditingController();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final query = _search.text.trim().toLowerCase();
    final filtered = widget.exercises.where((exercise) {
      return [
        exercise['name'],
        exercise['sport_type'],
        exercise['description'],
      ].join(' ').toLowerCase().contains(query);
    }).toList();
    return SafeArea(
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * .78,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
              child: Text(
                t('workout.selectExercise'),
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: TextField(
                controller: _search,
                autofocus: true,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  labelText: t('workout.searchExercise'),
                  prefixIcon: const Icon(Icons.search),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Expanded(
              child: filtered.isEmpty
                  ? Center(child: Text(t('workout.libraryEmpty')))
                  : ListView.separated(
                      padding: const EdgeInsets.fromLTRB(8, 4, 8, 20),
                      itemCount: filtered.length,
                      separatorBuilder: (_, _) => const Divider(height: 1),
                      itemBuilder: (context, index) {
                        final exercise = filtered[index];
                        final sport = exercise['sport_type']?.toString() ?? '';
                        final starter = exercise['is_starter'] == true;
                        final subtitle = [
                          if (sport.isNotEmpty) sport,
                          if (starter) t('workout.starter'),
                        ].join(' · ');
                        return ListTile(
                          leading: const Icon(Icons.fitness_center_outlined),
                          title: Text(
                            exercise['name']?.toString() ?? '',
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                          subtitle: subtitle.isEmpty ? null : Text(subtitle),
                          trailing: const Icon(Icons.add_circle_outline),
                          onTap: () => Navigator.pop(context, exercise),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _WorkoutEmptyState extends StatelessWidget {
  const _WorkoutEmptyState({required this.onAdd, required this.onLibrary});

  final VoidCallback onAdd;
  final VoidCallback onLibrary;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Column(
      children: [
        Icon(
          Icons.fitness_center_outlined,
          size: 38,
          color: airmiusMutedColor(context),
        ),
        const SizedBox(height: 8),
        Text(t('workout.empty'), textAlign: TextAlign.center),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: FilledButton.tonalIcon(
                onPressed: onAdd,
                icon: const Icon(Icons.add),
                label: Text(t('workout.addExercise')),
              ),
            ),
            const SizedBox(width: 8),
            IconButton.filledTonal(
              tooltip: t('workout.fromLibrary'),
              onPressed: onLibrary,
              icon: const Icon(Icons.menu_book_outlined),
            ),
          ],
        ),
      ],
    );
  }
}

class _PlannedWorkoutComposer extends StatelessWidget {
  const _PlannedWorkoutComposer({
    required this.exercises,
    required this.onAdd,
    required this.onLibrary,
    required this.onEdit,
    required this.onDelete,
    required this.onReorder,
  });

  final List<_WorkoutExerciseDraft> exercises;
  final VoidCallback onAdd;
  final VoidCallback onLibrary;
  final ValueChanged<int> onEdit;
  final ValueChanged<int> onDelete;
  final ReorderCallback onReorder;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final setCount = _workoutEntryCount(exercises);
    final accent = Theme.of(context).colorScheme.primary;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Icon(Icons.view_week_outlined, size: 20, color: accent),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                t('workout.planTitle'),
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          t('workout.planSubtitle'),
          style: TextStyle(color: airmiusMutedColor(context), height: 1.3),
        ),
        const SizedBox(height: 14),
        if (exercises.isEmpty)
          _WorkoutEmptyState(onAdd: onAdd, onLibrary: onLibrary)
        else ...[
          Semantics(
            label: t('workout.reorderHint'),
            child: ReorderableListView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              buildDefaultDragHandles: false,
              itemCount: exercises.length,
              onReorderItem: onReorder,
              proxyDecorator: (child, _, animation) => AnimatedBuilder(
                animation: animation,
                builder: (context, child) => Material(
                  color: Theme.of(context).colorScheme.surfaceContainerHigh,
                  elevation: 2 + (animation.value * 4),
                  borderRadius: BorderRadius.circular(8),
                  child: child,
                ),
                child: child,
              ),
              itemBuilder: (context, index) => Column(
                key: ValueKey(
                  '${exercises[index].exerciseKey}-${identityHashCode(exercises[index])}',
                ),
                children: [
                  if (index > 0) const Divider(height: 1),
                  _WorkoutExerciseRow(
                    index: index,
                    exercise: exercises[index],
                    onEdit: () => onEdit(index),
                    onDelete: () => onDelete(index),
                  ),
                ],
              ),
            ),
          ),
          if (setCount > 40)
            Text(
              t('workout.entryLimit'),
              style: TextStyle(
                color: Theme.of(context).colorScheme.error,
                fontWeight: FontWeight.w800,
              ),
            ),
          Row(
            children: [
              Expanded(
                child: FilledButton.tonalIcon(
                  onPressed: onAdd,
                  icon: const Icon(Icons.add),
                  label: Text(t('workout.addExercise')),
                ),
              ),
              const SizedBox(width: 8),
              IconButton.filledTonal(
                tooltip: t('workout.fromLibrary'),
                onPressed: onLibrary,
                icon: const Icon(Icons.menu_book_outlined),
              ),
            ],
          ),
        ],
      ],
    );
  }
}

@visibleForTesting
Widget buildPlannedWorkoutReorderPreview() {
  return const _PlannedWorkoutReorderPreview();
}

class _PlannedWorkoutReorderPreview extends StatefulWidget {
  const _PlannedWorkoutReorderPreview();

  @override
  State<_PlannedWorkoutReorderPreview> createState() =>
      _PlannedWorkoutReorderPreviewState();
}

class _PlannedWorkoutReorderPreviewState
    extends State<_PlannedWorkoutReorderPreview> {
  late final List<_WorkoutExerciseDraft> _exercises = [
    _WorkoutExerciseDraft.create(title: 'Brust'),
    _WorkoutExerciseDraft.create(title: 'Kniebeuge'),
    _WorkoutExerciseDraft.create(title: 'Warm-up Fahrrad'),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Einheit bearbeiten')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _PlannedWorkoutComposer(
            exercises: _exercises,
            onAdd: () {},
            onLibrary: () {},
            onEdit: (_) {},
            onDelete: (index) => setState(() => _exercises.removeAt(index)),
            onReorder: (oldIndex, newIndex) => setState(() {
              final exercise = _exercises.removeAt(oldIndex);
              _exercises.insert(newIndex, exercise);
            }),
          ),
        ],
      ),
    );
  }
}

class _WorkoutExerciseRow extends StatelessWidget {
  const _WorkoutExerciseRow({
    this.index,
    required this.exercise,
    required this.onEdit,
    required this.onDelete,
  });

  final int? index;
  final _WorkoutExerciseDraft exercise;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (index == null)
            IconBadge(
              icon: _workoutModeIcon(exercise.mode),
              color: Theme.of(context).colorScheme.primary,
            )
          else
            Semantics(
              button: true,
              label: t(
                'workout.reorderExercise',
              ).replaceFirst('{exercise}', exercise.title),
              child: ReorderableDragStartListener(
                index: index!,
                child: Tooltip(
                  message: t('workout.reorder'),
                  child: SizedBox(
                    width: 48,
                    height: 48,
                    child: Stack(
                      alignment: Alignment.center,
                      children: [
                        IconBadge(
                          icon: _workoutModeIcon(exercise.mode),
                          color: Theme.of(context).colorScheme.primary,
                        ),
                        PositionedDirectional(
                          end: 0,
                          bottom: 0,
                          child: Icon(
                            Icons.drag_indicator,
                            size: 18,
                            color: airmiusMutedColor(context),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  exercise.title,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 4),
                Text(
                  _workoutExerciseSummary(exercise, t),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: t('exerciseLibrary.edit'),
            onPressed: onEdit,
            icon: const Icon(Icons.edit_outlined),
          ),
          IconButton(
            tooltip: t('exerciseLibrary.delete'),
            onPressed: onDelete,
            icon: const Icon(Icons.delete_outline),
          ),
        ],
      ),
    );
  }
}

class _WorkoutHistoryExercise extends StatelessWidget {
  const _WorkoutHistoryExercise({required this.exercise});

  final _WorkoutExerciseDraft exercise;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                _workoutModeIcon(exercise.mode),
                size: 20,
                color: Theme.of(context).colorScheme.primary,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  exercise.title,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
              StatusPill(t('workout.mode.${exercise.mode}')),
            ],
          ),
          const SizedBox(height: 8),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                for (var index = 0; index < exercise.sets.length; index++) ...[
                  if (index > 0) const SizedBox(width: 7),
                  _WorkoutSetResult(
                    setNumber: index + 1,
                    mode: exercise.mode,
                    set: exercise.sets[index],
                  ),
                ],
              ],
            ),
          ),
          if (exercise.notes.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              exercise.notes,
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
          ],
          const Divider(height: 17),
        ],
      ),
    );
  }
}

class _WorkoutPlanComparison extends StatelessWidget {
  const _WorkoutPlanComparison({required this.planned, required this.actual});

  final List<_WorkoutExerciseDraft> planned;
  final List<_WorkoutExerciseDraft> actual;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: planned.asMap().entries.map((entry) {
        final exercise = entry.value;
        _WorkoutExerciseDraft? performed;
        for (final candidate in actual) {
          if (candidate.exerciseKey == exercise.exerciseKey ||
              candidate.substitutedFor == exercise.exerciseKey ||
              candidate.title.toLowerCase() == exercise.title.toLowerCase()) {
            performed = candidate;
            break;
          }
        }
        final completedSets =
            performed?.sets.where((set) => set.completed).length ?? 0;
        return Padding(
          padding: EdgeInsets.only(top: entry.key == 0 ? 0 : 12, bottom: 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Icon(
                    _workoutModeIcon(exercise.mode),
                    size: 19,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      exercise.title,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                  ),
                  if (performed != null)
                    StatusPill(
                      '$completedSets/${exercise.sets.length}',
                      color: completedSets >= exercise.sets.length
                          ? AirmiusColors.green
                          : Theme.of(context).colorScheme.primary,
                    ),
                ],
              ),
              if (performed?.substitutedFor != null) ...[
                const SizedBox(height: 7),
                Row(
                  children: [
                    Icon(
                      Icons.swap_horiz,
                      size: 18,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                    const SizedBox(width: 7),
                    Expanded(
                      child: Text(
                        t(
                          'workout.substitutedWith',
                        ).replaceFirst('{exercise}', performed!.title),
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.primary,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
              const SizedBox(height: 9),
              _WorkoutComparisonLine(
                label: t('workout.planned'),
                value: _workoutExerciseSummary(exercise, t),
              ),
              const SizedBox(height: 5),
              _WorkoutComparisonLine(
                label: t('workout.actual'),
                value: performed == null
                    ? t('workout.noActual')
                    : _workoutExerciseSummary(performed, t),
                muted: performed == null,
              ),
              const SizedBox(height: 10),
              for (
                var setIndex = 0;
                setIndex < exercise.sets.length;
                setIndex++
              )
                _WorkoutSetComparisonRow(
                  setNumber: setIndex + 1,
                  mode: exercise.mode,
                  planned: exercise.sets[setIndex],
                  actual: performed != null && setIndex < performed.sets.length
                      ? performed.sets[setIndex]
                      : null,
                ),
              if (performed != null &&
                  performed.sets.length > exercise.sets.length)
                for (
                  var setIndex = exercise.sets.length;
                  setIndex < performed.sets.length;
                  setIndex++
                )
                  _WorkoutSetComparisonRow(
                    setNumber: setIndex + 1,
                    mode: performed.mode,
                    actual: performed.sets[setIndex],
                  ),
              if (entry.key < planned.length - 1) const Divider(height: 24),
            ],
          ),
        );
      }).toList(),
    );
  }
}

class _WorkoutSetComparisonRow extends StatelessWidget {
  const _WorkoutSetComparisonRow({
    required this.setNumber,
    required this.mode,
    this.planned,
    this.actual,
  });

  final int setNumber;
  final String mode;
  final _WorkoutSetDraft? planned;
  final _WorkoutSetDraft? actual;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final skipped = actual?.skipReason.trim().isNotEmpty == true;
    final completed = actual?.completed == true;
    final status = actual == null
        ? t('workout.noActual')
        : completed
        ? t('workout.completed')
        : skipped
        ? t('liveWorkout.skip.${actual!.skipReason}')
        : t('workout.notCompleted');
    final statusColor = completed
        ? AirmiusColors.green
        : (skipped
              ? Theme.of(context).colorScheme.error
              : airmiusMutedColor(context));
    final statusIcon = completed
        ? Icons.check_circle_outline
        : (skipped ? Icons.report_outlined : Icons.pending_outlined);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(statusIcon, size: 18, color: statusColor),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  planned == null
                      ? '${t('liveWorkout.extraSet')} $setNumber'
                      : '${t('workout.set')} $setNumber',
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
              Flexible(
                child: Text(
                  status,
                  textAlign: TextAlign.end,
                  style: TextStyle(
                    color: statusColor,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 5),
          _WorkoutComparisonLine(
            label: t('workout.planned'),
            value: planned == null
                ? t('liveWorkout.extraSet')
                : _workoutSetSummary(planned!, mode, t),
            muted: planned == null,
          ),
          const SizedBox(height: 3),
          _WorkoutComparisonLine(
            label: t('workout.actual'),
            value: actual == null
                ? t('workout.noActual')
                : _workoutSetSummary(actual!, mode, t),
            muted: actual == null,
          ),
        ],
      ),
    );
  }
}

class _WorkoutComparisonLine extends StatelessWidget {
  const _WorkoutComparisonLine({
    required this.label,
    required this.value,
    this.muted = false,
  });

  final String label;
  final String value;
  final bool muted;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 88,
          child: Text(
            label,
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: TextStyle(color: muted ? airmiusMutedColor(context) : null),
          ),
        ),
      ],
    );
  }
}

class _WorkoutSetResult extends StatelessWidget {
  const _WorkoutSetResult({
    required this.setNumber,
    required this.mode,
    required this.set,
  });

  final int setNumber;
  final String mode;
  final _WorkoutSetDraft set;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Container(
      constraints: const BoxConstraints(minWidth: 104),
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9),
      decoration: BoxDecoration(
        color: set.completed
            ? AirmiusColors.green.withValues(alpha: .10)
            : Theme.of(context).colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: set.completed
              ? AirmiusColors.green.withValues(alpha: .45)
              : Theme.of(context).dividerColor,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${t('workout.set')} $setNumber',
            style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 12),
          ),
          const SizedBox(height: 3),
          Text(_workoutSetSummary(set, mode, t)),
        ],
      ),
    );
  }
}

class _MissedTrainingDialog extends StatefulWidget {
  const _MissedTrainingDialog();

  @override
  State<_MissedTrainingDialog> createState() => _MissedTrainingDialogState();
}

class _MissedTrainingDialogState extends State<_MissedTrainingDialog> {
  final _notes = TextEditingController();
  String _reason = 'krank';

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    const reasons = [
      'krank',
      'verletzt',
      'keine_zeit',
      'verschoben',
      'bewusst_ausgelassen',
      'anderes',
    ];
    return AlertDialog(
      title: Text(t('trainingHub.markMissed')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _reason,
              decoration: InputDecoration(
                labelText: t('trainingHub.missedReason'),
              ),
              items: reasons
                  .map(
                    (reason) => DropdownMenuItem(
                      value: reason,
                      child: Text(t('trainingHub.missed.$reason')),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _reason = value ?? _reason),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _notes,
              maxLines: 3,
              decoration: InputDecoration(labelText: t('trainingHub.notes')),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('auth2fa.cancel')),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(context, {
            'reason': _reason,
            'notes': _notes.text.trim(),
          }),
          child: Text(t('trainingHub.save')),
        ),
      ],
    );
  }
}

class _EmptyTrainingState extends StatelessWidget {
  const _EmptyTrainingState({
    required this.icon,
    required this.text,
    required this.action,
    required this.onAction,
  });

  final IconData icon;
  final String text;
  final String action;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        children: [
          Icon(icon, size: 48, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 12),
          Text(text, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          AirmiusButton(label: action, icon: Icons.add, onPressed: onAction),
        ],
      ),
    );
  }
}

class _LogFormSummary extends StatelessWidget {
  const _LogFormSummary({
    required this.title,
    required this.intensity,
    required this.rpe,
    required this.exerciseCount,
  });

  final String title;
  final String intensity;
  final int rpe;
  final int exerciseCount;

  @override
  Widget build(BuildContext context) {
    final accent = Theme.of(context).colorScheme.primary;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: .10),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: accent.withValues(alpha: .22)),
      ),
      child: Row(
        children: [
          IconBadge(icon: Icons.fact_check_outlined, color: accent),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 7),
                Wrap(
                  spacing: 7,
                  runSpacing: 7,
                  children: [
                    StatusPill(intensity, color: accent),
                    StatusPill('$rpe/10', color: AirmiusColors.green),
                    if (exerciseCount > 0)
                      StatusPill(
                        '$exerciseCount ${AirmiusScope.of(context).t(exerciseCount == 1 ? 'workout.exerciseSingular' : 'workout.exercises')}',
                        color: AirmiusColors.blue,
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _LogFormSection extends StatelessWidget {
  const _LogFormSection({
    required this.icon,
    required this.title,
    required this.children,
    this.subtitle,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final separated = <Widget>[];
    for (final child in children) {
      if (separated.isNotEmpty) separated.add(const SizedBox(height: 12));
      separated.add(child);
    }
    final accent = Theme.of(context).colorScheme.primary;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(
          context,
        ).colorScheme.surfaceContainerHighest.withValues(alpha: .35),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Theme.of(context).dividerColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(icon, size: 20, color: accent),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ],
          ),
          if (subtitle?.trim().isNotEmpty == true) ...[
            const SizedBox(height: 4),
            Text(
              subtitle!,
              style: TextStyle(color: airmiusMutedColor(context), height: 1.3),
            ),
          ],
          const SizedBox(height: 14),
          ...separated,
        ],
      ),
    );
  }
}

class _TrainingStructureFields extends StatelessWidget {
  const _TrainingStructureFields({
    required this.goal,
    required this.level,
    required this.equipmentController,
    required this.onGoalChanged,
    required this.onLevelChanged,
    required this.onEquipmentChanged,
    required this.onEquipmentPreset,
    this.initiallyExpanded = false,
  });

  final String goal;
  final String level;
  final TextEditingController equipmentController;
  final ValueChanged<String> onGoalChanged;
  final ValueChanged<String> onLevelChanged;
  final ValueChanged<String> onEquipmentChanged;
  final ValueChanged<String> onEquipmentPreset;
  final bool initiallyExpanded;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final colorScheme = Theme.of(context).colorScheme;
    final goalKey = _optionKeyOrDefault(_trainingGoals, goal, 'technique');
    final goalLabel = _trainingGoals
        .firstWhere((option) => option.key == goalKey)
        .label(t);
    final levelKey = _levelKeyOrDefault(level);
    final equipment = equipmentController.text.trim();
    final summary = [
      goalLabel,
      t('trainingHub.level.$levelKey'),
      equipment.isEmpty ? t('trainingHub.equipment.notSpecified') : equipment,
    ].join(' · ');

    return Theme(
      data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
      child: ExpansionTile(
        initiallyExpanded: initiallyExpanded,
        tilePadding: EdgeInsets.zero,
        childrenPadding: const EdgeInsets.fromLTRB(0, 4, 0, 8),
        leading: Icon(
          Icons.tune_outlined,
          color: colorScheme.primary,
          size: 21,
        ),
        title: Text(
          t('trainingHub.sessionStructure'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        subtitle: Text(
          summary,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(color: airmiusMutedColor(context)),
        ),
        children: [
          DropdownButtonFormField<String>(
            initialValue: goalKey,
            decoration: InputDecoration(
              labelText: t('trainingHub.trainingGoal'),
              isDense: true,
            ),
            items: _trainingGoals
                .map(
                  (option) => DropdownMenuItem(
                    value: option.key,
                    child: Text(option.label(t)),
                  ),
                )
                .toList(),
            onChanged: (value) {
              if (value != null) onGoalChanged(value);
            },
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: levelKey,
            decoration: InputDecoration(
              labelText: t('trainingHub.level'),
              isDense: true,
            ),
            items: ['beginner', 'intermediate', 'advanced', 'elite']
                .map(
                  (value) => DropdownMenuItem(
                    value: value,
                    child: Text(t('trainingHub.level.$value')),
                  ),
                )
                .toList(),
            onChanged: (value) {
              if (value != null) onLevelChanged(value);
            },
          ),
          const SizedBox(height: 10),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: TextField(
                  controller: equipmentController,
                  onChanged: onEquipmentChanged,
                  decoration: InputDecoration(
                    labelText: t('trainingHub.equipment'),
                    hintText: t('trainingHub.equipmentHint'),
                    isDense: true,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              IconButton.filledTonal(
                tooltip: t('trainingHub.equipment.choosePreset'),
                icon: const Icon(Icons.playlist_add_outlined),
                onPressed: () async {
                  final selected = await showModalBottomSheet<String>(
                    context: context,
                    showDragHandle: true,
                    builder: (sheetContext) => SafeArea(
                      child: ListView(
                        shrinkWrap: true,
                        padding: const EdgeInsets.only(bottom: 12),
                        children: [
                          Padding(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                            child: Text(
                              t('trainingHub.equipment.choosePreset'),
                              style: Theme.of(sheetContext)
                                  .textTheme
                                  .titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w900),
                            ),
                          ),
                          for (final option in _equipmentPresets)
                            ListTile(
                              leading: const Icon(
                                Icons.fitness_center_outlined,
                              ),
                              title: Text(option.label(t)),
                              onTap: () =>
                                  Navigator.pop(sheetContext, option.label(t)),
                            ),
                        ],
                      ),
                    ),
                  );
                  if (selected != null && context.mounted) {
                    onEquipmentPreset(selected);
                  }
                },
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TrainingStructurePills extends StatelessWidget {
  const _TrainingStructurePills({required this.metrics});

  final Map<String, dynamic> metrics;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final labels = _trainingStructureLabels(t, metrics);
    if (labels.isEmpty) return const SizedBox.shrink();
    return Wrap(
      spacing: 7,
      runSpacing: 7,
      children: labels.map((label) => StatusPill(label)).toList(),
    );
  }
}

class _TrainingChoices {
  const _TrainingChoices({this.teams = const [], this.athletes = const []});

  final List<Map<String, dynamic>> teams;
  final List<Map<String, dynamic>> athletes;

  static Future<_TrainingChoices> load(AirmiusApiClient client) async {
    final responses = await Future.wait([client.teams(), client.friends()]);
    final teams = _dataList(responses[0])
        .where(
          (team) =>
              team['viewer_is_member'] == true || team['can_manage'] == true,
        )
        .toList();
    final friendData = responses[1]['data'];
    final athletes = friendData is Map && friendData['friends'] is List
        ? (friendData['friends'] as List)
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList()
        : const <Map<String, dynamic>>[];
    return _TrainingChoices(teams: teams, athletes: athletes);
  }
}

class _TrainingData {
  const _TrainingData({
    this.plans = const [],
    this.logs = const [],
    this.canManagePlans = false,
  });

  final List<_TrainingPlan> plans;
  final List<_TrainingLog> logs;
  final bool canManagePlans;
}

class _TrainingPlan {
  const _TrainingPlan({
    required this.id,
    required this.title,
    required this.cadence,
    required this.status,
    this.description,
    this.teamId,
    this.targetType = 'self',
    this.teamMode,
    this.startsOn,
    this.endsOn,
    this.goal,
    this.phase,
    this.level,
    this.weeks,
    this.weeklySessions,
    this.macrocycle,
    this.mesocycle,
    this.deloadWeek,
    this.competitionDate,
    this.sharePermission = 'read',
    this.assignedUserIds = const [],
    this.assignedAudienceNames = const [],
    this.canWrite = false,
    this.canDelete = false,
    this.assignmentsCount = 0,
    this.isTemplate = false,
    this.isTemplateCopy = false,
    this.items = const [],
  });

  factory _TrainingPlan.fromJson(Map<String, dynamic> json) => _TrainingPlan(
    id: _asInt(json['id']),
    title: json['title']?.toString() ?? '',
    description: json['description']?.toString(),
    teamId: _nullableInt(json['team_id']),
    targetType:
        json['target_type']?.toString() ??
        (_nullableInt(json['team_id']) != null ? 'team' : 'self'),
    teamMode: json['team_mode']?.toString(),
    startsOn: DateTime.tryParse(json['starts_on']?.toString() ?? ''),
    endsOn: DateTime.tryParse(json['ends_on']?.toString() ?? ''),
    goal: _planSetting(json, 'goal')?.toString(),
    phase: _planSetting(json, 'phase')?.toString(),
    level: _planSetting(json, 'level')?.toString(),
    weeks: _nullableInt(_planSetting(json, 'weeks')),
    weeklySessions: _nullableInt(_planSetting(json, 'weekly_sessions')),
    macrocycle: _planSetting(json, 'macrocycle')?.toString(),
    mesocycle: _planSetting(json, 'mesocycle')?.toString(),
    deloadWeek: _nullableInt(_planSetting(json, 'deload_week')),
    competitionDate: DateTime.tryParse(
      _planSetting(json, 'competition_date')?.toString() ?? '',
    ),
    sharePermission: json['share_permission']?.toString() ?? 'read',
    assignedUserIds: _mapList(json['assignments'])
        .map((assignment) => assignment['user'])
        .whereType<Map>()
        .map((user) => _asInt(user['id']))
        .where((id) => id > 0)
        .toList(),
    assignedAudienceNames: _mapList(json['assignments'])
        .map((assignment) => assignment['user'] ?? assignment['team'])
        .whereType<Map>()
        .map((audience) => audience['name']?.toString().trim() ?? '')
        .where((name) => name.isNotEmpty)
        .toSet()
        .toList(),
    cadence: json['cadence']?.toString() ?? 'single',
    status: json['status']?.toString() ?? 'draft',
    canWrite: json['can_write'] == true,
    canDelete: json['can_delete'] == true,
    assignmentsCount: _asInt(json['assignments_count']),
    isTemplate: json['is_template'] == true,
    isTemplateCopy: json['is_template_copy'] == true,
    items: _mapList(json['items']).map(_TrainingPlanItem.fromJson).toList(),
  );

  final int id;
  final String title;
  final String? description;
  final int? teamId;
  final String targetType;
  final String? teamMode;
  final DateTime? startsOn;
  final DateTime? endsOn;
  final String? goal;
  final String? phase;
  final String? level;
  final int? weeks;
  final int? weeklySessions;
  final String? macrocycle;
  final String? mesocycle;
  final int? deloadWeek;
  final DateTime? competitionDate;
  final String sharePermission;
  final List<int> assignedUserIds;
  final List<String> assignedAudienceNames;
  final String cadence;
  final String status;
  final bool canWrite;
  final bool canDelete;
  final int assignmentsCount;
  final bool isTemplate;
  final bool isTemplateCopy;
  final List<_TrainingPlanItem> items;
}

class _TrainingPlanItem {
  const _TrainingPlanItem({
    required this.id,
    required this.title,
    this.description,
    this.sportType,
    this.scheduledAt,
    this.week,
    this.durationMinutes,
    this.distanceMeters,
    this.calories,
    this.intensity,
    this.load,
    this.focus,
    this.videoUrl,
    this.imageUrl,
    this.todos = const [],
    this.metrics = const {},
    this.sportRoute,
  });

  factory _TrainingPlanItem.fromJson(Map<String, dynamic> json) =>
      _TrainingPlanItem(
        id: _asInt(json['id']),
        title: json['title']?.toString() ?? '',
        description: json['description']?.toString(),
        sportType: json['sport_type']?.toString(),
        scheduledAt: DateTime.tryParse(json['scheduled_at']?.toString() ?? ''),
        week: _nullableInt(
          json['metrics'] is Map ? (json['metrics'] as Map)['Woche'] : null,
        ),
        durationMinutes: _nullableInt(json['duration_minutes']),
        distanceMeters: _nullableInt(json['distance_meters']),
        calories: _nullableInt(json['calories']),
        intensity: json['intensity']?.toString(),
        load: json['metrics'] is Map
            ? (json['metrics'] as Map)['Belastung']?.toString()
            : null,
        focus: json['metrics'] is Map
            ? (json['metrics'] as Map)['Fokus']?.toString()
            : null,
        videoUrl: json['video_url']?.toString(),
        imageUrl: json['image_url']?.toString(),
        todos: json['todos'] is List
            ? (json['todos'] as List).map((item) => '$item').toList()
            : const [],
        metrics: json['metrics'] is Map
            ? Map<String, dynamic>.from(json['metrics'] as Map)
            : const {},
        sportRoute: json['sport_route'] is Map
            ? _TrainingRouteReference.fromJson(
                Map<String, dynamic>.from(json['sport_route'] as Map),
              )
            : null,
      );

  final int id;
  final String title;
  final String? description;
  final String? sportType;
  final DateTime? scheduledAt;
  final int? week;
  final int? durationMinutes;
  final int? distanceMeters;
  final int? calories;
  final String? intensity;
  final String? load;
  final String? focus;
  final String? videoUrl;
  final String? imageUrl;
  final List<String> todos;
  final Map<String, dynamic> metrics;
  final _TrainingRouteReference? sportRoute;

  List<_WorkoutExerciseDraft> get plannedExercises =>
      _plannedExercisesFromMetrics(metrics);
}

class _TrainingLog {
  const _TrainingLog({
    required this.id,
    required this.createdBy,
    required this.title,
    required this.status,
    this.trainingPlanItemId,
    this.sportType,
    this.performedAt,
    this.durationMinutes,
    this.distanceMeters,
    this.intensity,
    this.notes,
    this.trainerFeedback,
    this.feedbacks = const [],
    this.privacyScope,
    this.rpe,
    this.entries = const [],
    this.plannedExercises = const [],
    this.sportRoute,
    this.sportRouteTrack,
  });

  factory _TrainingLog.fromJson(Map<String, dynamic> json) {
    final metrics = json['metrics'] is Map
        ? Map<String, dynamic>.from(json['metrics'] as Map)
        : const <String, dynamic>{};
    final wellness = metrics['wellness'] is Map
        ? Map<String, dynamic>.from(metrics['wellness'] as Map)
        : const <String, dynamic>{};
    final snapshot = metrics['plan_snapshot'] is Map
        ? Map<String, dynamic>.from(metrics['plan_snapshot'] as Map)
        : const <String, dynamic>{};
    final planItem = json['plan_item'] is Map
        ? Map<String, dynamic>.from(json['plan_item'] as Map)
        : const <String, dynamic>{};
    final plannedMetrics = snapshot['metrics'] is Map
        ? Map<String, dynamic>.from(snapshot['metrics'] as Map)
        : (planItem['metrics'] is Map
              ? Map<String, dynamic>.from(planItem['metrics'] as Map)
              : const <String, dynamic>{});
    return _TrainingLog(
      id: _asInt(json['id']),
      createdBy: _asInt(json['created_by']),
      title: json['title']?.toString() ?? '',
      status: json['status']?.toString() ?? 'completed',
      trainingPlanItemId: _nullableInt(json['training_plan_item_id']),
      sportType: json['sport_type']?.toString(),
      performedAt: DateTime.tryParse(json['performed_at']?.toString() ?? ''),
      durationMinutes: _nullableInt(json['duration_minutes']),
      distanceMeters: _nullableInt(json['distance_meters']),
      intensity: json['intensity']?.toString(),
      notes: json['notes']?.toString(),
      trainerFeedback: json['trainer_feedback']?.toString(),
      feedbacks: _mapList(
        json['feedbacks'],
      ).map(_TrainingFeedback.fromJson).toList(),
      privacyScope: metrics['privacy_scope']?.toString(),
      rpe: _nullableInt(wellness['rpe']),
      entries: _mapList(
        json['entries'],
      ).map(_TrainingLogEntry.fromJson).toList(),
      plannedExercises: _plannedExercisesFromMetrics(plannedMetrics),
      sportRoute: json['sport_route'] is Map
          ? _TrainingRouteReference.fromJson(
              Map<String, dynamic>.from(json['sport_route'] as Map),
            )
          : null,
      sportRouteTrack: json['sport_route_track'] is Map
          ? _TrainingTrackReference.fromJson(
              Map<String, dynamic>.from(json['sport_route_track'] as Map),
            )
          : null,
    );
  }

  final int id;
  final int createdBy;
  final String title;
  final String status;
  final int? trainingPlanItemId;
  final String? sportType;
  final DateTime? performedAt;
  final int? durationMinutes;
  final int? distanceMeters;
  final String? intensity;
  final String? notes;
  final String? trainerFeedback;
  final List<_TrainingFeedback> feedbacks;
  final String? privacyScope;
  final int? rpe;
  final List<_TrainingLogEntry> entries;
  final List<_WorkoutExerciseDraft> plannedExercises;
  final _TrainingRouteReference? sportRoute;
  final _TrainingTrackReference? sportRouteTrack;
}

class _TrainingRouteReference {
  const _TrainingRouteReference({
    required this.id,
    required this.title,
    this.distanceMeters,
    this.durationSeconds,
    this.elevationGainMeters,
  });

  factory _TrainingRouteReference.fromJson(Map<String, dynamic> json) =>
      _TrainingRouteReference(
        id: _asInt(json['id']),
        title: json['title']?.toString() ?? '',
        distanceMeters: _nullableInt(json['distance_meters']),
        durationSeconds: _nullableInt(json['estimated_duration_seconds']),
        elevationGainMeters: _nullableInt(json['elevation_gain_meters']),
      );

  final int id;
  final String title;
  final int? distanceMeters;
  final int? durationSeconds;
  final int? elevationGainMeters;
}

class _TrainingTrackReference {
  const _TrainingTrackReference({
    required this.id,
    required this.title,
    this.routeId,
    this.distanceMeters,
    this.durationSeconds,
  });

  factory _TrainingTrackReference.fromJson(Map<String, dynamic> json) =>
      _TrainingTrackReference(
        id: _asInt(json['id']),
        title: json['title']?.toString() ?? '',
        routeId: _nullableInt(json['sport_route_id']),
        distanceMeters: _nullableInt(json['distance_meters']),
        durationSeconds: _nullableInt(json['duration_seconds']),
      );

  final int id;
  final String title;
  final int? routeId;
  final int? distanceMeters;
  final int? durationSeconds;
}

class _TrainingFeedback {
  const _TrainingFeedback({
    required this.body,
    required this.authorName,
    this.createdAt,
  });

  factory _TrainingFeedback.fromJson(Map<String, dynamic> json) {
    final author = json['author'] is Map
        ? Map<String, dynamic>.from(json['author'] as Map)
        : const <String, dynamic>{};

    return _TrainingFeedback(
      body: json['body']?.toString() ?? '',
      authorName: author['name']?.toString() ?? '',
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
    );
  }

  final String body;
  final String authorName;
  final DateTime? createdAt;
}

int _workoutDraftSequence = 0;

class _WorkoutExerciseDraft {
  const _WorkoutExerciseDraft({
    required this.exerciseKey,
    required this.title,
    required this.mode,
    required this.sets,
    this.notes = '',
    this.substitutedFor,
  });

  factory _WorkoutExerciseDraft.create({String title = '', String notes = ''}) {
    _workoutDraftSequence += 1;
    return _WorkoutExerciseDraft(
      exerciseKey:
          'exercise-${DateTime.now().microsecondsSinceEpoch}-$_workoutDraftSequence',
      title: title,
      mode: 'reps',
      notes: notes,
      sets: [_WorkoutSetDraft.defaults()],
    );
  }

  factory _WorkoutExerciseDraft.fromTemplate(Map<String, dynamic> template) {
    _workoutDraftSequence += 1;
    final mode =
        const {
          'reps',
          'time',
          'distance',
          'rounds',
        }.contains(template['suggested_mode'])
        ? template['suggested_mode'].toString()
        : 'reps';
    var count = _asInt(template['suggested_sets']);
    if (count < 1) count = 1;
    if (count > 20) count = 20;
    final prototype = _WorkoutSetDraft(
      reps: template['suggested_reps']?.toString() ?? '',
      durationMinutes: template['suggested_duration_minutes']?.toString() ?? '',
      distanceKm: template['suggested_distance_km']?.toString() ?? '',
      rounds: template['suggested_rounds']?.toString() ?? '',
      restSeconds: template['suggested_rest_seconds']?.toString() ?? '60',
    );
    return _WorkoutExerciseDraft(
      exerciseKey:
          'template-${DateTime.now().microsecondsSinceEpoch}-$_workoutDraftSequence',
      title: template['name']?.toString() ?? '',
      mode: mode,
      notes: template['instructions']?.toString() ?? '',
      sets: List.generate(count, (_) => prototype.copy()),
    );
  }

  factory _WorkoutExerciseDraft.fromPlannedJson(Map<String, dynamic> json) {
    final mode =
        const {
          'reps',
          'time',
          'distance',
          'rounds',
        }.contains(json['tracking_mode'])
        ? json['tracking_mode'].toString()
        : 'reps';
    final sets = _mapList(
      json['sets'],
    ).map(_WorkoutSetDraft.fromPlannedJson).toList();
    return _WorkoutExerciseDraft(
      exerciseKey: json['exercise_key']?.toString() ?? '',
      title: json['title']?.toString() ?? '',
      mode: mode,
      notes: json['notes']?.toString() ?? '',
      substitutedFor: json['substituted_for']?.toString(),
      sets: sets.isEmpty ? [_WorkoutSetDraft.defaults()] : sets,
    );
  }

  factory _WorkoutExerciseDraft.fromLiveDraftJson(Map<String, dynamic> json) {
    final mode =
        const {
          'reps',
          'time',
          'distance',
          'rounds',
        }.contains(json['tracking_mode'])
        ? json['tracking_mode'].toString()
        : 'reps';
    final sets = _mapList(
      json['sets'],
    ).map(_WorkoutSetDraft.fromLiveDraftJson).toList();
    return _WorkoutExerciseDraft(
      exerciseKey: json['exercise_key']?.toString() ?? '',
      title: json['title']?.toString() ?? '',
      mode: mode,
      notes: json['notes']?.toString() ?? '',
      substitutedFor: json['substituted_for']?.toString(),
      sets: sets.isEmpty ? [_WorkoutSetDraft.defaults()] : sets,
    );
  }

  _WorkoutExerciseDraft copy() => _WorkoutExerciseDraft(
    exerciseKey: exerciseKey,
    title: title,
    mode: mode,
    notes: notes,
    substitutedFor: substitutedFor,
    sets: sets.map((set) => set.copy()).toList(),
  );

  final String exerciseKey;
  final String title;
  final String mode;
  final String notes;
  final String? substitutedFor;
  final List<_WorkoutSetDraft> sets;

  List<Map<String, dynamic>> toPayloadEntries() {
    return [
      for (var index = 0; index < sets.length; index++)
        sets[index].toPayload(
          title: title,
          exerciseKey: exerciseKey,
          mode: mode,
          setIndex: index + 1,
          notes: index == 0 ? notes : '',
          substitutedFor: substitutedFor,
        ),
    ];
  }

  Map<String, dynamic> toPlannedPayload() => {
    'exercise_key': exerciseKey,
    'title': title,
    'tracking_mode': mode,
    'notes': notes.trim().isEmpty ? null : notes.trim(),
    'sets': [
      for (var index = 0; index < sets.length; index++)
        sets[index].toPlannedPayload(mode: mode, setIndex: index + 1),
    ],
  };

  Map<String, dynamic> toLiveDraftPayload() => {
    'exercise_key': exerciseKey,
    'title': title,
    'tracking_mode': mode,
    'notes': notes,
    'substituted_for': substitutedFor,
    'sets': [for (final set in sets) set.toLiveDraftPayload()],
  };

  _WorkoutExerciseDraft forExecution() => _WorkoutExerciseDraft(
    exerciseKey: exerciseKey,
    title: title,
    mode: mode,
    notes: notes,
    substitutedFor: substitutedFor,
    sets: sets.map((set) => set.copy(completed: false)).toList(),
  );
}

List<_WorkoutExerciseDraft> _plannedExercisesFromMetrics(
  Map<String, dynamic> metrics,
) {
  return _mapList(metrics['planned_exercises'])
      .map(_WorkoutExerciseDraft.fromPlannedJson)
      .where(
        (exercise) =>
            exercise.exerciseKey.isNotEmpty && exercise.title.isNotEmpty,
      )
      .toList();
}

List<_WorkoutExerciseDraft> _workoutExercisesFromPlanItem(
  _TrainingPlanItem item,
) {
  if (item.plannedExercises.isNotEmpty) {
    return item.plannedExercises
        .map((exercise) => exercise.forExecution())
        .toList();
  }

  _workoutDraftSequence += 1;
  final mode = item.distanceMeters != null
      ? 'distance'
      : (item.durationMinutes != null ? 'time' : 'reps');
  final set = _WorkoutSetDraft(
    reps: mode == 'reps' ? '10' : '',
    durationMinutes: item.durationMinutes?.toString() ?? '',
    distanceKm: item.distanceMeters == null
        ? ''
        : _compactNumber(item.distanceMeters! / 1000),
    restSeconds: '60',
    completed: false,
  );
  return [
    _WorkoutExerciseDraft(
      exerciseKey:
          'plan-${item.id}-${DateTime.now().microsecondsSinceEpoch}-$_workoutDraftSequence',
      title: item.title,
      mode: mode,
      notes: item.todos.join('\n'),
      sets: [set],
    ),
  ];
}

class _WorkoutSetDraft {
  _WorkoutSetDraft({
    this.reps = '',
    this.weightKg = '',
    this.durationMinutes = '',
    this.distanceKm = '',
    this.rounds = '',
    this.restSeconds = '60',
    this.completed = true,
    this.skipReason = '',
  });

  factory _WorkoutSetDraft.defaults() => _WorkoutSetDraft(
    reps: '10',
    durationMinutes: '1',
    distanceKm: '1',
    rounds: '1',
  );

  factory _WorkoutSetDraft.fromEntry(_TrainingLogEntry entry) {
    return _WorkoutSetDraft(
      reps: entry.reps?.toString() ?? '',
      weightKg: _compactNumber(entry.weightKg),
      durationMinutes: entry.durationSeconds == null
          ? ''
          : _compactNumber(entry.durationSeconds! / 60),
      distanceKm: entry.distanceMeters == null
          ? ''
          : _compactNumber(entry.distanceMeters! / 1000),
      rounds: entry.rounds?.toString() ?? '',
      restSeconds: entry.restSeconds?.toString() ?? '',
      completed: entry.completed,
      skipReason: entry.skipReason ?? '',
    );
  }

  factory _WorkoutSetDraft.fromPlannedJson(Map<String, dynamic> json) {
    return _WorkoutSetDraft(
      reps: json['reps']?.toString() ?? '',
      weightKg: _compactNumber(_nullableDouble(json['weight_kg'])),
      durationMinutes: _compactNumber(
        _nullableDouble(json['duration_minutes']),
      ),
      distanceKm: _compactNumber(_nullableDouble(json['distance_km'])),
      rounds: json['rounds']?.toString() ?? '',
      restSeconds: json['rest_seconds']?.toString() ?? '',
      completed: false,
    );
  }

  factory _WorkoutSetDraft.fromLiveDraftJson(Map<String, dynamic> json) {
    return _WorkoutSetDraft(
      reps: json['reps']?.toString() ?? '',
      weightKg: json['weight_kg']?.toString() ?? '',
      durationMinutes: json['duration_minutes']?.toString() ?? '',
      distanceKm: json['distance_km']?.toString() ?? '',
      rounds: json['rounds']?.toString() ?? '',
      restSeconds: json['rest_seconds']?.toString() ?? '',
      completed: json['completed'] == true,
      skipReason: json['skip_reason']?.toString() ?? '',
    );
  }

  String reps;
  String weightKg;
  String durationMinutes;
  String distanceKm;
  String rounds;
  String restSeconds;
  bool completed;
  String skipReason;

  _WorkoutSetDraft copy({bool? completed}) => _WorkoutSetDraft(
    reps: reps,
    weightKg: weightKg,
    durationMinutes: durationMinutes,
    distanceKm: distanceKm,
    rounds: rounds,
    restSeconds: restSeconds,
    completed: completed ?? this.completed,
    skipReason: skipReason,
  );

  Map<String, dynamic> toPayload({
    required String title,
    required String exerciseKey,
    required String mode,
    required int setIndex,
    required String notes,
    String? substitutedFor,
  }) {
    return {
      'title': title,
      'sets': 1,
      'reps': mode == 'reps' ? int.tryParse(reps.trim()) : null,
      'weight_kg': mode == 'reps'
          ? double.tryParse(weightKg.replaceAll(',', '.'))
          : null,
      'duration_minutes':
          mode == 'time' || mode == 'distance' || mode == 'rounds'
          ? double.tryParse(durationMinutes.replaceAll(',', '.'))
          : null,
      'distance_km': mode == 'distance'
          ? double.tryParse(distanceKm.replaceAll(',', '.'))
          : null,
      'notes': notes.trim().isEmpty ? null : notes.trim(),
      'exercise_key': exerciseKey,
      'substituted_for': substitutedFor,
      'set_index': setIndex,
      'tracking_mode': mode,
      'rest_seconds': int.tryParse(restSeconds.trim()),
      'rounds': mode == 'rounds' ? int.tryParse(rounds.trim()) : null,
      'completed': completed,
      'skip_reason': skipReason.trim().isEmpty ? null : skipReason.trim(),
    };
  }

  Map<String, dynamic> toPlannedPayload({
    required String mode,
    required int setIndex,
  }) {
    return {
      'set_index': setIndex,
      'reps': mode == 'reps' ? int.tryParse(reps.trim()) : null,
      'weight_kg': mode == 'reps'
          ? double.tryParse(weightKg.replaceAll(',', '.'))
          : null,
      'duration_minutes':
          mode == 'time' || mode == 'distance' || mode == 'rounds'
          ? double.tryParse(durationMinutes.replaceAll(',', '.'))
          : null,
      'distance_km': mode == 'distance'
          ? double.tryParse(distanceKm.replaceAll(',', '.'))
          : null,
      'rounds': mode == 'rounds' ? int.tryParse(rounds.trim()) : null,
      'rest_seconds': int.tryParse(restSeconds.trim()),
    };
  }

  Map<String, dynamic> toLiveDraftPayload() => {
    'reps': reps,
    'weight_kg': weightKg,
    'duration_minutes': durationMinutes,
    'distance_km': distanceKm,
    'rounds': rounds,
    'rest_seconds': restSeconds,
    'completed': completed,
    'skip_reason': skipReason,
  };
}

List<_WorkoutExerciseDraft> _workoutExercisesFromEntries(
  List<_TrainingLogEntry> entries,
) {
  final groups = <String, List<_TrainingLogEntry>>{};
  for (var index = 0; index < entries.length; index++) {
    final entry = entries[index];
    final key = entry.exerciseKey?.trim().isNotEmpty == true
        ? entry.exerciseKey!.trim()
        : 'legacy-$index';
    groups.putIfAbsent(key, () => []).add(entry);
  }

  return groups.entries.map((group) {
    final sourceEntries = [...group.value]
      ..sort(
        (left, right) => (left.setIndex ?? 0).compareTo(right.setIndex ?? 0),
      );
    final first = sourceEntries.first;
    final sets = <_WorkoutSetDraft>[];
    for (final entry in sourceEntries) {
      var count = entry.exerciseKey == null ? (entry.sets ?? 1) : 1;
      if (count < 1) count = 1;
      if (count > 20) count = 20;
      for (var index = 0; index < count; index++) {
        sets.add(_WorkoutSetDraft.fromEntry(entry));
      }
    }
    return _WorkoutExerciseDraft(
      exerciseKey: group.key,
      substitutedFor: first.substitutedFor,
      title: first.title,
      mode: _inferWorkoutMode(first),
      notes: first.notes ?? '',
      sets: sets.isEmpty ? [_WorkoutSetDraft.defaults()] : sets,
    );
  }).toList();
}

String _inferWorkoutMode(_TrainingLogEntry entry) {
  const modes = {'reps', 'time', 'distance', 'rounds'};
  if (modes.contains(entry.trackingMode)) return entry.trackingMode!;
  if (entry.rounds != null) return 'rounds';
  if (entry.reps != null || entry.weightKg != null || entry.sets != null) {
    return 'reps';
  }
  if (entry.distanceMeters != null) return 'distance';
  if (entry.durationSeconds != null) return 'time';
  return 'reps';
}

IconData _workoutModeIcon(String mode) => switch (mode) {
  'time' => Icons.timer_outlined,
  'distance' => Icons.straighten_outlined,
  'rounds' => Icons.autorenew,
  _ => Icons.repeat_rounded,
};

String _workoutExerciseSummary(
  _WorkoutExerciseDraft exercise,
  String Function(String) t,
) {
  final values = exercise.sets
      .map((set) => _workoutSetPrimaryValue(set, exercise.mode, t))
      .where((value) => value.isNotEmpty)
      .toList();
  return [
    '${exercise.sets.length} ${t('workout.sets')}',
    if (values.isNotEmpty) values.join(' / '),
  ].join(' · ');
}

String _workoutSetSummary(
  _WorkoutSetDraft set,
  String mode,
  String Function(String) t,
) {
  return [
    _workoutSetPrimaryValue(set, mode, t),
    if (set.restSeconds.trim().isNotEmpty)
      '${set.restSeconds.trim()} ${t('workout.secondsShort')} ${t('workout.restShort')}',
  ].where((value) => value.isNotEmpty).join(' · ');
}

String _workoutSetPrimaryValue(
  _WorkoutSetDraft set,
  String mode,
  String Function(String) t,
) {
  return switch (mode) {
    'time' =>
      set.durationMinutes.trim().isEmpty
          ? ''
          : '${set.durationMinutes.trim()} min',
    'distance' => [
      if (set.distanceKm.trim().isNotEmpty) '${set.distanceKm.trim()} km',
      if (set.durationMinutes.trim().isNotEmpty)
        '${set.durationMinutes.trim()} min',
    ].join(' / '),
    'rounds' => [
      if (set.rounds.trim().isNotEmpty)
        '${set.rounds.trim()} ${t('workout.roundsShort')}',
      if (set.durationMinutes.trim().isNotEmpty)
        '${set.durationMinutes.trim()} min',
    ].join(' / '),
    _ => [
      if (set.reps.trim().isNotEmpty)
        '${set.reps.trim()} ${t('workout.repsShort')}',
      if (set.weightKg.trim().isNotEmpty) '${set.weightKg.trim()} kg',
    ].join(' × '),
  };
}

String _compactNumber(num? value) {
  if (value == null) return '';
  return value % 1 == 0 ? value.toInt().toString() : value.toStringAsFixed(2);
}

int _workoutEntryCount(List<_WorkoutExerciseDraft> exercises) =>
    exercises.fold(0, (total, exercise) => total + exercise.sets.length);

List<Map<String, dynamic>> _builtInWorkoutTemplates(String Function(String) t) {
  Map<String, dynamic> template(
    String key,
    String mode, {
    int sets = 3,
    int? reps,
    double? duration,
    double? distance,
    int? rounds,
    int rest = 60,
  }) {
    return {
      'name': t('workout.template.$key.name'),
      'sport_type': t('workout.template.$key.sport'),
      'is_starter': true,
      'suggested_mode': mode,
      'suggested_sets': sets,
      'suggested_reps': reps,
      'suggested_duration_minutes': duration,
      'suggested_distance_km': distance,
      'suggested_rounds': rounds,
      'suggested_rest_seconds': rest,
    };
  }

  return [
    template('warmup', 'time', sets: 1, duration: 8, rest: 0),
    template('squat', 'reps', sets: 3, reps: 10, rest: 90),
    template('runIntervals', 'distance', sets: 6, distance: .4, rest: 90),
    template('swimTechnique', 'distance', sets: 8, distance: .05, rest: 30),
    template('bikeTempo', 'time', sets: 4, duration: 5, rest: 120),
    template('footballPassing', 'rounds', sets: 4, rounds: 1, duration: 4),
    template('basketballDribble', 'time', sets: 4, duration: 2),
    template('handballThrows', 'reps', sets: 4, reps: 8),
    template('volleyballReception', 'reps', sets: 4, reps: 12),
    template('tennisRally', 'reps', sets: 3, reps: 20),
    template('badmintonFootwork', 'time', sets: 6, duration: 1, rest: 45),
    template('tableTennisServe', 'reps', sets: 4, reps: 15, rest: 30),
    template('shadowBoxing', 'time', sets: 5, duration: 3),
    template('skiBalance', 'time', sets: 3, duration: 1),
    template('gymnasticsCore', 'time', sets: 3, duration: .5, rest: 45),
    template('sprint', 'distance', sets: 8, distance: .03, rest: 90),
    template('mobilityFlow', 'time', sets: 2, duration: 8, rest: 30),
  ];
}

class _TrainingLogEntry {
  const _TrainingLogEntry({
    required this.title,
    this.sets,
    this.reps,
    this.weightKg,
    this.durationSeconds,
    this.distanceMeters,
    this.intensity,
    this.notes,
    this.exerciseKey,
    this.substitutedFor,
    this.setIndex,
    this.trackingMode,
    this.restSeconds,
    this.rounds,
    this.completed = true,
    this.skipReason,
  });

  factory _TrainingLogEntry.fromJson(Map<String, dynamic> json) {
    final metrics = json['metrics'] is Map
        ? Map<String, dynamic>.from(json['metrics'] as Map)
        : const <String, dynamic>{};
    return _TrainingLogEntry(
      title: json['title']?.toString() ?? '',
      sets: _nullableInt(json['sets']),
      reps: _nullableInt(json['reps']),
      weightKg: _nullableDouble(json['weight_kg']),
      durationSeconds: _nullableInt(json['duration_seconds']),
      distanceMeters: _nullableInt(json['distance_meters']),
      intensity: json['intensity']?.toString(),
      notes: json['notes']?.toString(),
      exerciseKey: metrics['exercise_key']?.toString(),
      substitutedFor: metrics['substituted_for']?.toString(),
      setIndex: _nullableInt(metrics['set_index']),
      trackingMode: metrics['tracking_mode']?.toString(),
      restSeconds: _nullableInt(metrics['rest_seconds']),
      rounds: _nullableInt(metrics['rounds']),
      completed: metrics['completed'] != false,
      skipReason: metrics['skip_reason']?.toString(),
    );
  }

  final String title;
  final int? sets;
  final int? reps;
  final double? weightKg;
  final int? durationSeconds;
  final int? distanceMeters;
  final String? intensity;
  final String? notes;
  final String? exerciseKey;
  final String? substitutedFor;
  final int? setIndex;
  final String? trackingMode;
  final int? restSeconds;
  final int? rounds;
  final bool completed;
  final String? skipReason;
}

List<Map<String, dynamic>> _dataList(Map<String, dynamic> response) =>
    _mapList(response['data']);

Map<String, dynamic> _singleData(Map<String, dynamic> response) {
  final data = response['data'];
  return data is Map ? Map<String, dynamic>.from(data) : response;
}

Map<String, dynamic> _singleDataValue(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _mapList(Object? value) {
  if (value is! List) return const [];
  return value
      .whereType<Map>()
      .map((item) => Map<String, dynamic>.from(item))
      .toList();
}

int _asInt(Object? value) =>
    value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;

int? _nullableInt(Object? value) => value == null ? null : _asInt(value);

double? _nullableDouble(Object? value) => value is num
    ? value.toDouble()
    : double.tryParse(value?.toString().replaceAll(',', '.') ?? '');

Object? _planSetting(Map<String, dynamic> json, String key) {
  final settings = json['settings'];
  return settings is Map ? settings[key] : null;
}

String _shortDate(DateTime? value) {
  if (value == null) return '–';
  final local = value.toLocal();
  return '${local.day.toString().padLeft(2, '0')}.'
      '${local.month.toString().padLeft(2, '0')}.${local.year}';
}

String _dateApi(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-'
    '${value.month.toString().padLeft(2, '0')}-'
    '${value.day.toString().padLeft(2, '0')}';

String _dateTimeLabel(DateTime value) =>
    '${_shortDate(value)} · '
    '${value.hour.toString().padLeft(2, '0')}:'
    '${value.minute.toString().padLeft(2, '0')}';

String _normalizedMetricText(Object? value) => (value?.toString() ?? '')
    .trim()
    .toLowerCase()
    .replaceAll('ä', 'ae')
    .replaceAll('ö', 'oe')
    .replaceAll('ü', 'ue')
    .replaceAll('ß', 'ss')
    .replaceAll(RegExp(r'\s+'), ' ');

String? _metricValue(Map<String, dynamic>? metrics, String key) {
  final value = metrics?[key];
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? null : text;
}

String _optionKeyOrDefault(
  List<_TrainingOption> options,
  String? value,
  String fallback,
) {
  final normalized = _normalizedMetricText(value);
  return options.any((option) => option.key == normalized)
      ? normalized
      : fallback;
}

String _optionKeyFromMetric(
  List<_TrainingOption> options,
  Object? value,
  String fallback,
) {
  final normalized = _normalizedMetricText(value);
  if (normalized.isEmpty) return fallback;
  for (final option in options) {
    if (normalized == _normalizedMetricText(option.key) ||
        normalized == _normalizedMetricText(option.canonicalLabel)) {
      return option.key;
    }
  }
  const aliases = {
    'warm up': 'warmup',
    'warm-up': 'warmup',
    'activation': 'warmup',
    'aktivierung': 'warmup',
    'hauptteil': 'main',
    'main part': 'main',
    'technik': 'technique',
    'technique': 'technique',
    'kraft': 'strength',
    'strength': 'strength',
    'ausdauer': 'endurance',
    'endurance': 'endurance',
    'schnelligkeit': 'speed',
    'tempo': 'speed',
    'speed': 'speed',
    'beweglichkeit': 'mobility',
    'mobilitaet': 'mobility',
    'mobility': 'mobility',
    'cool down': 'cooldown',
    'cool-down': 'cooldown',
    'regeneration': 'recovery',
    'recovery': 'recovery',
    'koordination': 'coordination',
    'coordination': 'coordination',
    'taktik': 'tactics',
    'tactics': 'tactics',
  };
  final alias = aliases[normalized];
  return alias != null && options.any((option) => option.key == alias)
      ? alias
      : fallback;
}

String _levelKeyOrDefault(Object? value) {
  final normalized = _normalizedMetricText(value);
  const levels = {'beginner', 'intermediate', 'advanced', 'elite'};
  if (levels.contains(normalized)) return normalized;
  const aliases = {
    'anfaenger': 'beginner',
    'beginner': 'beginner',
    'mittelstufe': 'intermediate',
    'intermediate': 'intermediate',
    'fortgeschritten': 'advanced',
    'advanced': 'advanced',
    'elite': 'elite',
  };
  return aliases[normalized] ?? 'intermediate';
}

String _canonicalOptionLabel(List<_TrainingOption> options, String key) {
  for (final option in options) {
    if (option.key == key) return option.canonicalLabel;
  }
  return key;
}

Map<String, String> _structuredTrainingMetrics({
  required String goal,
  required String level,
  required String equipment,
}) {
  final metrics = <String, String>{
    'Trainingsziel': _canonicalOptionLabel(
      _trainingGoals,
      _optionKeyOrDefault(_trainingGoals, goal, 'technique'),
    ),
    'Niveau': _levelKeyOrDefault(level),
  };
  final equipmentText = equipment.trim();
  if (equipmentText.isNotEmpty) metrics['Equipment'] = equipmentText;
  return metrics;
}

String? _optionDisplayLabel(
  String Function(String) t,
  List<_TrainingOption> options,
  Object? value,
) {
  final normalized = _normalizedMetricText(value);
  if (normalized.isEmpty) return null;
  final key = _optionKeyFromMetric(options, value, '');
  for (final option in options) {
    if (option.key == key ||
        normalized == _normalizedMetricText(option.canonicalLabel)) {
      return option.label(t);
    }
  }
  return value?.toString();
}

String? _levelDisplayLabel(String Function(String) t, Object? value) {
  final level = _levelKeyOrDefault(value);
  return value == null || value.toString().trim().isEmpty
      ? null
      : t('trainingHub.level.$level');
}

bool _hasTrainingStructure(Map<String, dynamic> metrics) =>
    _metricValue(metrics, 'Abschnitt') != null ||
    _metricValue(metrics, 'Trainingsziel') != null ||
    _metricValue(metrics, 'Niveau') != null ||
    _metricValue(metrics, 'Equipment') != null;

List<String> _trainingStructureLabels(
  String Function(String) t,
  Map<String, dynamic> metrics,
) {
  final labels = <String>[];
  final block = _optionDisplayLabel(
    t,
    _trainingSessionBlocks,
    metrics['Abschnitt'],
  );
  final goal = _optionDisplayLabel(t, _trainingGoals, metrics['Trainingsziel']);
  final level = _levelDisplayLabel(t, metrics['Niveau']);
  final equipment = _metricValue(metrics, 'Equipment');
  if (block?.isNotEmpty == true) labels.add(block!);
  if (goal?.isNotEmpty == true) labels.add(goal!);
  if (level?.isNotEmpty == true) labels.add(level!);
  if (equipment?.isNotEmpty == true) labels.add(equipment!);
  return labels;
}

String _metricsText(Map<String, dynamic>? metrics) {
  if (metrics == null || metrics.isEmpty) return '';
  const managed = {
    'Woche',
    'Belastung',
    'Fokus',
    'week',
    'load',
    'focus',
    'Abschnitt',
    'Trainingsziel',
    'Niveau',
    'Equipment',
    '_training_type',
    'training_type',
    'Trainingstyp',
    'planned_exercises',
  };
  return metrics.entries
      .where((entry) => !managed.contains(entry.key))
      .map((entry) => '${entry.key}: ${entry.value}')
      .join('\n');
}

Map<String, String> _parseMetricsText(String value) {
  final result = <String, String>{};
  for (final rawLine in value.split(RegExp(r'\r?\n'))) {
    final line = rawLine.trim();
    if (line.isEmpty) continue;
    final colon = line.indexOf(':');
    final equals = line.indexOf('=');
    final separator = colon >= 0 ? colon : equals;
    if (separator <= 0) continue;
    final key = line.substring(0, separator).trim();
    final metric = line.substring(separator + 1).trim();
    if (key.isNotEmpty && metric.isNotEmpty) result[key] = metric;
  }
  return result;
}

String _translatedStatus(String Function(String) t, String status) =>
    t('trainingHub.status.$status');

String _translatedCadence(String Function(String) t, String cadence) =>
    t('trainingHub.cadence.$cadence');

Future<bool> _confirmDelete(BuildContext context) async {
  final t = AirmiusScope.of(context).t;
  return await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(t('trainingHub.deleteConfirmTitle')),
          content: Text(t('trainingHub.deleteConfirmBody')),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: Text(t('auth2fa.cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text(t('trainingHub.delete')),
            ),
          ],
        ),
      ) ??
      false;
}
