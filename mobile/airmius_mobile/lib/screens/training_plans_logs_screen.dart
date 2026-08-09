import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'exercise_library_screen.dart';
import 'training_progress_screen.dart';
import 'training_availability_screen.dart';
import 'training_plan_templates_screen.dart';
import 'sport_map_center_screen.dart';

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
    final canManagePlans = capabilities is Map &&
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
                          label: Text(t('trainingHub.plans')),
                        ),
                        ButtonSegment(
                          value: 1,
                          icon: const Icon(Icons.fact_check_outlined),
                          label: Text(t('trainingHub.logs')),
                        ),
                      ],
                      selected: {_tab},
                      onSelectionChanged: (value) =>
                          setState(() => _tab = value.first),
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
              subtitle: Text(
                [
                  _translatedStatus(t, plan.status),
                  _translatedCadence(t, plan.cadence),
                  if (plan.items.isNotEmpty)
                    '${plan.items.length} ${t('trainingHub.items')}',
                ].join(' · '),
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
      MaterialPageRoute(
        builder: (_) => const _PlanFormPage(),
      ),
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
                      Text(
                        plan.title,
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
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
                    ],
                  ),
                ),
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
                            subtitle: Text(
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
                            onTap: plan.canWrite && !_busy
                                ? () => _editItem(item)
                                : null,
                            trailing: PopupMenuButton<String>(
                              enabled: !_busy,
                              tooltip: t('trainingHub.actions'),
                              onSelected: (action) {
                                if (action == 'route' && item.sportRoute != null) {
                                  Navigator.of(context).push(
                                    MaterialPageRoute<void>(
                                      builder: (_) => SportMapCenterScreen(
                                        initialRouteId: item.sportRoute!.id,
                                      ),
                                    ),
                                  );
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
                if (plan.canWrite) ...[
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 9,
                    runSpacing: 9,
                    children: [
                      AirmiusButton(
                        label: t('trainingHub.editPlan'),
                        icon: Icons.edit_calendar_outlined,
                        secondary: true,
                        onPressed: _busy ? null : () => _editPlan(plan),
                      ),
                      if (plan.status != 'published')
                        AirmiusButton(
                          label: t('trainingHub.publish'),
                          icon: Icons.publish_outlined,
                          onPressed: _busy ? null : _publishPlan,
                        ),
                      AirmiusButton(
                        label: t('trainingHub.duplicatePlan'),
                        icon: Icons.content_copy_outlined,
                        secondary: true,
                        onPressed: _busy ? null : _duplicatePlan,
                      ),
                      AirmiusButton(
                        label: t('trainingHub.saveAsTemplate'),
                        icon: Icons.bookmark_add_outlined,
                        secondary: true,
                        onPressed: _busy ? null : _saveAsTemplate,
                      ),
                    ],
                  ),
                ],
                if (plan.canDelete) ...[
                  const SizedBox(height: 14),
                  AirmiusButton(
                    label: t('trainingHub.deletePlan'),
                    icon: Icons.delete_forever_outlined,
                    danger: true,
                    onPressed: _busy ? null : _deletePlan,
                  ),
                ],
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

  Future<void> _editPlan(_TrainingPlan plan) async {
    final payload = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (_) => _PlanFormPage(initial: plan),
      ),
    );
    if (payload == null) return;
    await _run(() => _client.updateTrainingPlan(widget.planId, payload));
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

  Future<void> _duplicateItem(int itemId) =>
      _run(() => _client.duplicateTrainingPlanItem(widget.planId, itemId));

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

  Future<void> _publishPlan() =>
      _run(() => _client.publishTrainingPlan(widget.planId));

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
                    title: t('trainingHub.entries'),
                    child: Column(
                      children: [
                        for (final entry in log.entries)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: const Icon(Icons.check_circle_outline),
                            title: Text(entry.title),
                            subtitle: Text(
                              [
                                if (entry.durationSeconds != null)
                                  '${(entry.durationSeconds! / 60).round()} ${t('trainingHub.minutes')}',
                                if (entry.distanceMeters != null)
                                  '${(entry.distanceMeters! / 1000).toStringAsFixed(1)} km',
                              ].join(' · '),
                            ),
                          ),
                      ],
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
                                  style: Theme.of(context)
                                      .textTheme
                                      .labelLarge
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

class _AiPlanPreviewDialog extends StatelessWidget {
  const _AiPlanPreviewDialog({required this.plan});

  final Map<String, dynamic> plan;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final items = _mapList(plan['items']);
    final warnings = (plan['warnings'] is List)
        ? List<Object?>.from(plan['warnings'] as List)
        : const <Object?>[];
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
              '${items.length} ${t('trainingHub.items')}',
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
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context, false),
          child: Text(t('auth2fa.cancel')),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(context, true),
          child: Text(t('trainingHub.saveDraft')),
        ),
      ],
    );
  }
}

class _PlanFormPage extends StatefulWidget {
  const _PlanFormPage({this.initial});

  final _TrainingPlan? initial;

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
  late String _itemLoad;

  @override
  void initState() {
    super.initState();
    final initial = widget.initial;
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
    _status = initial?.status ?? 'draft';
    _phase = initial?.phase ?? 'base';
    _level = initial?.level ?? 'beginner';
    _permission = initial?.sharePermission ?? 'read';
    _targetType = initial?.targetType ?? 'self';
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
    _itemLoad = 'medium';
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
    return _teamId != null &&
        (_teamMode == 'all' || _userIds.isNotEmpty);
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
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(t(widget.initial == null ? 'trainingHub.addPlan' : 'trainingHub.editPlan')),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 120),
          child: AirmiusPanel(
            gradient: true,
            child: Column(
          children: [
            SegmentedButton<int>(
              segments: [
                ButtonSegment(value: 0, label: Text(t('trainingHub.step1'))),
                ButtonSegment(value: 1, label: Text(t('trainingHub.step2'))),
                ButtonSegment(value: 2, label: Text(t('trainingHub.step3'))),
                ButtonSegment(value: 3, label: Text(t('trainingHub.step4'))),
              ],
              selected: {_step},
              onSelectionChanged: (value) {
                if (value.first >= 3 && !_targetSelectionValid) return;
                setState(() => _step = value.first);
              },
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
              decoration: InputDecoration(labelText: t('trainingHub.cadence')),
              items: ['single', 'daily', 'weekly', 'monthly']
                  .map((value) => DropdownMenuItem(
                        value: value,
                        child: Text(_translatedCadence(t, value)),
                      ))
                  .toList(),
              onChanged: (value) => setState(() => _cadence = value ?? _cadence),
            ),
            ],
            if (_step == 1) ...[
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () async {
                      final picked = await showDatePicker(
                        context: context,
                        initialDate: _startsOn,
                        firstDate: DateTime(2020),
                        lastDate: DateTime(2100),
                      );
                      if (picked != null) setState(() => _startsOn = picked);
                    },
                    icon: const Icon(Icons.event_outlined),
                    label: Text(
                      '${t('trainingHub.start')}: ${_dateApi(_startsOn)}',
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: () async {
                final picked = await showDatePicker(
                  context: context,
                  initialDate: _endsOn ?? _startsOn,
                  firstDate: _startsOn,
                  lastDate: DateTime(2100),
                );
                if (picked != null) setState(() => _endsOn = picked);
              },
              icon: const Icon(Icons.event_available_outlined),
              label: Text(
                _endsOn == null
                    ? t('trainingHub.chooseEnd')
                    : '${t('trainingHub.end')}: ${_dateApi(_endsOn!)}',
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
              controller: _goal,
              decoration: InputDecoration(labelText: t('trainingHub.goal')),
            ),
            const SizedBox(height: 16),
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
            const SizedBox(height: 16),
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
            const SizedBox(height: 16),
            TextField(
              controller: _weeks,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(labelText: t('trainingHub.weeks')),
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
                      initialDate: _competitionDate ?? _endsOn ?? _startsOn,
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
                              '${_dateApi(_competitionDate!)}',
                  ),
                ),
              ],
            ),
            ],
            if (_step == 2) ...[
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: _targetType,
                decoration: InputDecoration(labelText: t('trainingHub.target')),
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
                  decoration: InputDecoration(labelText: t('trainingHub.team')),
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
                        label: Text(athlete['name']?.toString() ?? 'Person'),
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
              if (_targetType == 'team' && _teamId != null && _teamMode == 'individual') ...[
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
                        label: Text(athlete['name']?.toString() ?? 'Athlet'),
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
                decoration: InputDecoration(labelText: t('trainingHub.status')),
                items: ['draft', 'published']
                    .map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text(_translatedStatus(t, value)),
                      ),
                    )
                    .toList(),
                onChanged: (value) => setState(() => _status = value ?? _status),
              ),
            ],
            if (_step == 3) ...[
              TextField(
                controller: _itemTitle,
                decoration: InputDecoration(labelText: t('trainingHub.itemTitle')),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _itemSport,
                decoration: InputDecoration(labelText: t('trainingHub.sport')),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _itemDuration,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(labelText: t('trainingHub.duration')),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _itemDistance,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(labelText: t('trainingHub.distance')),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: _itemLoad,
                decoration: InputDecoration(labelText: t('trainingHub.load')),
                items: ['low', 'medium', 'high', 'test']
                    .map((value) => DropdownMenuItem(value: value, child: Text(t('trainingHub.load.$value'))))
                    .toList(),
                onChanged: (value) => setState(() => _itemLoad = value ?? _itemLoad),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _itemFocus,
                decoration: InputDecoration(labelText: t('trainingHub.focus')),
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
              child: OutlinedButton(
                onPressed: () => Navigator.pop(context),
                child: Text(t('auth2fa.cancel')),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _step > 0
                  ? OutlinedButton(
                      onPressed: () => setState(() => _step -= 1),
                      child: Text(t('trainingHub.back')),
                    )
                  : const SizedBox.shrink(),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: FilledButton(
          onPressed: _step < 3
              ? ((_step == 0 && _title.text.trim().isEmpty) ||
                      (_step == 2 && !_targetSelectionValid)
                    ? null
                    : () => setState(() => _step += 1))
              : (_title.text.trim().isEmpty || _itemTitle.text.trim().isEmpty)
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
                  'status': _status,
                  'share_permission': _permission,
                  'target_type': _targetType,
                  'team_mode': _targetType == 'team' ? _teamMode : null,
                  'team_id': _teamId,
                  'user_ids': _targetType == 'self' ? <int>[] : _userIds.toList(),
                  'item_title': _itemTitle.text.trim(),
                  'item_sport_type': _itemSport.text.trim(),
                  'item_duration_minutes': int.tryParse(_itemDuration.text),
                  'item_distance_km': double.tryParse(_itemDistance.text.replaceAll(',', '.')),
                  'item_load': _itemLoad,
                  'item_focus': _itemFocus.text.trim(),
                }),
                child: Text(t(_step < 3 ? 'trainingHub.next' : 'trainingHub.save')),
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

class _PlanItemFormPageState extends State<_PlanItemFormPage> {
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
  late final TextEditingController _videoUrl;
  late final TextEditingController _todos;
  late String _intensity;
  late String _load;
  int? _sportRouteId;
  late DateTime _scheduledAt;
  PlatformFile? _image;
  List<String> _sports = const [];
  List<_TrainingRouteReference> _routes = const [];
  bool _sportsLoading = true;

  @override
  void initState() {
    super.initState();
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
    _videoUrl = TextEditingController(text: initial?.videoUrl ?? '');
    _todos = TextEditingController(text: initial?.todos.join('\n') ?? '');
    _intensity = initial?.intensity ?? 'mittel';
    _load = initial?.load ?? 'medium';
    _sportRouteId = initial?.sportRoute?.id;
    _scheduledAt = initial?.scheduledAt ?? DateTime.now();
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
                .map((item) => (item['name'] ?? item['label'] ?? item['slug'])?.toString() ?? '')
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

  @override
  void dispose() {
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
    _videoUrl.dispose();
    _todos.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final search = _sportSearch.text.trim().toLowerCase();
    final filteredSports = _sports
        .where((sport) => search.length >= 2 && sport.toLowerCase().contains(search))
        .take(12)
        .toList();
    return Scaffold(
      appBar: AppBar(
        title: Text(t(widget.initial == null ? 'trainingHub.addItem' : 'trainingHub.editItem')),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 120),
          child: AirmiusPanel(
            gradient: true,
            child: Column(
          children: [
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
                      .map((sport) => ChoiceChip(
                            label: Text(sport),
                            selected: _sportType.text == sport,
                            onSelected: (_) => setState(() => _sportType.text = sport),
                          ))
                      .toList(),
                ),
              ),
            const SizedBox(height: 16),
            TextField(
              controller: _sportType,
              readOnly: _sports.isNotEmpty,
              decoration: InputDecoration(labelText: t('trainingHub.selectedSport')),
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
            const SizedBox(height: 16),
            TextField(
              controller: _duration,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(labelText: t('trainingHub.duration')),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _distance,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(labelText: t('trainingHub.distance')),
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
                  decoration: InputDecoration(labelText: t('trainingHub.load')),
                  items: ['low', 'medium', 'high', 'test']
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(t('trainingHub.load.$value')),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => setState(() => _load = value ?? _load),
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
              decoration: InputDecoration(labelText: t('trainingHub.videoUrl')),
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
        ),
      ),
        ),
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.fromLTRB(16, 8, 16, 12),
        child: Row(
          children: [
            Expanded(
              child: OutlinedButton(
                onPressed: () => Navigator.pop(context),
                child: Text(t('auth2fa.cancel')),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: FilledButton(
          onPressed: _title.text.trim().isEmpty
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
                  'metrics': _parseMetricsText(_metrics.text),
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

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(
        t(
          widget.initial == null ? 'trainingHub.addLog' : 'trainingHub.editLog',
        ),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _title,
              autofocus: true,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(labelText: t('trainingHub.logTitle')),
            ),
            TextField(
              controller: _sport,
              decoration: InputDecoration(labelText: t('trainingHub.sport')),
            ),
            TextField(
              controller: _duration,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(labelText: t('trainingHub.duration')),
            ),
            TextField(
              controller: _distance,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(labelText: t('trainingHub.distance')),
            ),
            if (_routeChoicesLoading) const LinearProgressIndicator(),
            DropdownButtonFormField<int?>(
              initialValue: _sportRouteId,
              decoration: InputDecoration(
                labelText: t('trainingHub.route'),
                helperText: t('trainingHub.locationMinimized'),
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
                    ),
                  ),
                ..._routes.map(
                  (route) => DropdownMenuItem<int?>(
                    value: route.id,
                    child: Text(route.title, overflow: TextOverflow.ellipsis),
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
              decoration: InputDecoration(
                labelText: t('trainingHub.gpsTrack'),
                helperText: t('trainingHub.trackOwnerHint'),
              ),
              items: [
                DropdownMenuItem<int?>(
                  value: null,
                  child: Text(t('trainingHub.noTrack')),
                ),
                if (_sportRouteTrackId != null &&
                    !_tracks.any((track) => track.id == _sportRouteTrackId))
                  DropdownMenuItem<int?>(
                    value: _sportRouteTrackId,
                    child: Text(
                      widget.initial?.sportRouteTrack?.title ??
                          t('trainingHub.gpsTrack'),
                    ),
                  ),
                ..._tracks
                    .where(
                      (track) => _sportRouteId == null ||
                          track.routeId == null ||
                          track.routeId == _sportRouteId,
                    )
                    .map(
                      (track) => DropdownMenuItem<int?>(
                        value: track.id,
                        child: Text(track.title, overflow: TextOverflow.ellipsis),
                      ),
                    ),
              ],
              onChanged: (value) =>
                  setState(() => _sportRouteTrackId = value),
            ),
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
            DropdownButtonFormField<String>(
              initialValue: _privacy,
              decoration: InputDecoration(labelText: t('trainingHub.privacy')),
              items: ['private', 'trainer', 'team']
                  .map(
                    (value) => DropdownMenuItem(
                      value: value,
                      child: Text(t('trainingHub.privacy.$value')),
                    ),
                  )
                  .toList(),
              onChanged: (value) =>
                  setState(() => _privacy = value ?? _privacy),
            ),
            const SizedBox(height: 10),
            Text('${t('trainingHub.rpe')}: ${_rpe.round()}/10'),
            Slider(
              value: _rpe,
              min: 1,
              max: 10,
              divisions: 9,
              label: '${_rpe.round()}',
              onChanged: (value) => setState(() => _rpe = value),
            ),
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
          onPressed: _title.text.trim().isEmpty
              ? null
              : () => Navigator.pop(context, {
                  'title': _title.text.trim(),
                  'sport_type': _sport.text.trim(),
                  'sport_route_id': _sportRouteId,
                  'sport_route_track_id': _sportRouteTrackId,
                  'status': 'completed',
                  'performed_at':
                      widget.initial?.performedAt?.toIso8601String() ??
                      DateTime.now().toIso8601String(),
                  'duration_minutes': int.tryParse(_duration.text),
                  'distance_km': double.tryParse(
                    _distance.text.replaceAll(',', '.'),
                  ),
                  'intensity': _intensity,
                  'privacy_scope': _privacy,
                  'notes': _notes.text.trim(),
                  'wellness': {'rpe': _rpe.round()},
                }),
          child: Text(t('trainingHub.save')),
        ),
      ],
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

class _TrainingChoices {
  const _TrainingChoices({this.teams = const [], this.athletes = const []});

  final List<Map<String, dynamic>> teams;
  final List<Map<String, dynamic>> athletes;

  static Future<_TrainingChoices> load(AirmiusApiClient client) async {
    final responses = await Future.wait([client.teams(), client.friends()]);
    final teams = _dataList(responses[0])
        .where((team) => team['viewer_is_member'] == true || team['can_manage'] == true)
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
    this.canWrite = false,
    this.canDelete = false,
    this.assignmentsCount = 0,
    this.items = const [],
  });

  factory _TrainingPlan.fromJson(Map<String, dynamic> json) => _TrainingPlan(
    id: _asInt(json['id']),
    title: json['title']?.toString() ?? '',
    description: json['description']?.toString(),
    teamId: _nullableInt(json['team_id']),
    targetType: json['target_type']?.toString() ??
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
    cadence: json['cadence']?.toString() ?? 'single',
    status: json['status']?.toString() ?? 'draft',
    canWrite: json['can_write'] == true,
    canDelete: json['can_delete'] == true,
    assignmentsCount: _asInt(json['assignments_count']),
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
  final String cadence;
  final String status;
  final bool canWrite;
  final bool canDelete;
  final int assignmentsCount;
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
}

class _TrainingLog {
  const _TrainingLog({
    required this.id,
    required this.createdBy,
    required this.title,
    required this.status,
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
    return _TrainingLog(
      id: _asInt(json['id']),
      createdBy: _asInt(json['created_by']),
      title: json['title']?.toString() ?? '',
      status: json['status']?.toString() ?? 'completed',
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

class _TrainingLogEntry {
  const _TrainingLogEntry({
    required this.title,
    this.durationSeconds,
    this.distanceMeters,
  });

  factory _TrainingLogEntry.fromJson(Map<String, dynamic> json) =>
      _TrainingLogEntry(
        title: json['title']?.toString() ?? '',
        durationSeconds: _nullableInt(json['duration_seconds']),
        distanceMeters: _nullableInt(json['distance_meters']),
      );

  final String title;
  final int? durationSeconds;
  final int? distanceMeters;
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
    '${_dateApi(value)} · '
    '${value.hour.toString().padLeft(2, '0')}:'
    '${value.minute.toString().padLeft(2, '0')}';

String _metricsText(Map<String, dynamic>? metrics) {
  if (metrics == null || metrics.isEmpty) return '';
  const managed = {'Woche', 'Belastung', 'Fokus', 'week', 'load', 'focus'};
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
