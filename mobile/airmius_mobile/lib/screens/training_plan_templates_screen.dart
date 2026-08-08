import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'training_plans_logs_screen.dart';

class TrainingPlanTemplatesScreen extends StatefulWidget {
  const TrainingPlanTemplatesScreen({super.key});

  @override
  State<TrainingPlanTemplatesScreen> createState() =>
      _TrainingPlanTemplatesScreenState();
}

class _TrainingPlanTemplatesScreenState
    extends State<TrainingPlanTemplatesScreen> {
  Future<_TemplateData>? _future;
  bool _busy = false;
  bool _canManagePlans = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<_TemplateData> _load() async {
    final response = await _client.trainingTemplates();
    final capabilities = response['capabilities'];
    final canManagePlans = capabilities is Map &&
        capabilities['can_manage_training_plans'] == true;
    if (mounted && _canManagePlans != canManagePlans) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _canManagePlans != canManagePlans) {
          setState(() => _canManagePlans = canManagePlans);
        }
      });
    }
    return _TemplateData(
      templates: _dataList(response).map(_TrainingTemplate.fromJson).toList(),
      canManagePlans: canManagePlans,
    );
  }

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(title: Text(t('trainingHub.templates'))),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async {
            _reload();
            await _future;
          },
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              AirmiusPanel(
                gradient: true,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      t('trainingHub.templates'),
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 6),
                    Text(t('trainingHub.templatesHint')),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              FutureBuilder<_TemplateData>(
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
                  final data = snapshot.data ?? const _TemplateData();
                  final templates = data.templates;
                  if (templates.isEmpty) {
                    return AirmiusPanel(
                      child: Column(
                        children: [
                          const Icon(Icons.bookmark_border_outlined, size: 42),
                          const SizedBox(height: 10),
                          Text(
                            t('trainingHub.emptyTemplates'),
                            textAlign: TextAlign.center,
                          ),
                        ],
                      ),
                    );
                  }
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      for (final template in templates) ...[
                        AirmiusPanel(
                          child: ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: const IconBadge(
                              icon: Icons.bookmark_added_outlined,
                              color: AirmiusColors.blue,
                            ),
                            title: Text(
                              template.title,
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            subtitle: Text(
                              '${template.itemsCount} ${t('trainingHub.items')}'
                              '${template.description?.isNotEmpty == true ? ' · ${template.description!}' : ''}',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                            trailing: data.canManagePlans
                                ? IconButton(
                                    tooltip: t('trainingHub.useTemplate'),
                                    icon: const Icon(Icons.add_circle_outline),
                                    onPressed: _busy
                                        ? null
                                        : () => _instantiate(template),
                                  )
                                : null,
                            onTap: data.canManagePlans && !_busy
                                ? () => _instantiate(template)
                                : null,
                          ),
                        ),
                        const SizedBox(height: 10),
                      ],
                    ],
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _instantiate(_TrainingTemplate template) async {
    final t = AirmiusScope.of(context).t;
    final input = await showDialog<_TemplateInput>(
      context: context,
      builder: (_) => const _TemplateInputDialog(),
    );
    if (input == null) return;
    setState(() => _busy = true);
    try {
      final response = await _client.instantiateTrainingTemplate(
        template.id,
        title: input.title,
        startsOn: input.startsOn,
        endsOn: input.endsOn,
      );
      final id = _asInt(_singleData(response)['id']);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('trainingHub.templateCreated'))));
      if (id > 0) {
        await Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => TrainingPlanApiDetailScreen(planId: id),
          ),
        );
      }
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
}

class _TemplateInputDialog extends StatefulWidget {
  const _TemplateInputDialog();

  @override
  State<_TemplateInputDialog> createState() => _TemplateInputDialogState();
}

class _TemplateInputDialogState extends State<_TemplateInputDialog> {
  final _title = TextEditingController();
  DateTime? _startsOn;
  DateTime? _endsOn;

  @override
  void dispose() {
    _title.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(t('trainingHub.useTemplate')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(
              controller: _title,
              autofocus: true,
              decoration: InputDecoration(
                labelText: t('trainingHub.templateTitle'),
                hintText: t('trainingHub.templateTitleHint'),
              ),
            ),
            const SizedBox(height: 12),
            _dateButton(
              context,
              label: t('trainingHub.start'),
              value: _startsOn,
              onChanged: (value) => setState(() => _startsOn = value),
            ),
            const SizedBox(height: 8),
            _dateButton(
              context,
              label: t('trainingHub.end'),
              value: _endsOn,
              onChanged: (value) => setState(() => _endsOn = value),
            ),
            const SizedBox(height: 8),
            Text(t('trainingHub.templateDateHint')),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: Text(t('common.cancel')),
        ),
        FilledButton.icon(
          onPressed: () => Navigator.of(context).pop(
            _TemplateInput(
              title: _title.text.trim(),
              startsOn: _isoDate(_startsOn),
              endsOn: _isoDate(_endsOn),
            ),
          ),
          icon: const Icon(Icons.add),
          label: Text(t('trainingHub.createFromTemplate')),
        ),
      ],
    );
  }

  Widget _dateButton(
    BuildContext context, {
    required String label,
    required DateTime? value,
    required ValueChanged<DateTime?> onChanged,
  }) {
    return OutlinedButton.icon(
      onPressed: () async {
        final picked = await showDatePicker(
          context: context,
          firstDate: DateTime.now().subtract(const Duration(days: 365)),
          lastDate: DateTime.now().add(const Duration(days: 3650)),
          initialDate: value ?? DateTime.now(),
        );
        if (picked != null) onChanged(picked);
      },
      icon: const Icon(Icons.event_outlined),
      label: Align(
        alignment: AlignmentDirectional.centerStart,
        child: Text(value == null ? label : '$label: ${_isoDate(value)}'),
      ),
    );
  }
}

class _TemplateInput {
  const _TemplateInput({
    required this.title,
    required this.startsOn,
    required this.endsOn,
  });

  final String title;
  final String? startsOn;
  final String? endsOn;
}

class _TrainingTemplate {
  const _TrainingTemplate({
    required this.id,
    required this.title,
    required this.itemsCount,
    this.description,
  });

  factory _TrainingTemplate.fromJson(Map<String, dynamic> json) =>
      _TrainingTemplate(
        id: _asInt(json['id']),
        title: json['title']?.toString() ?? '',
        description: json['description']?.toString(),
        itemsCount: _mapList(json['items']).length,
      );

  final int id;
  final String title;
  final String? description;
  final int itemsCount;
}

class _TemplateData {
  const _TemplateData({
    this.templates = const [],
    this.canManagePlans = false,
  });

  final List<_TrainingTemplate> templates;
  final bool canManagePlans;
}

String? _isoDate(DateTime? value) => value == null
    ? null
    : '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';

List<Map<String, dynamic>> _dataList(Map<String, dynamic> response) {
  final data = response['data'];
  if (data is List) {
    return data
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }
  return const [];
}

Map<String, dynamic> _singleData(Map<String, dynamic> response) {
  final data = response['data'];
  return data is Map ? Map<String, dynamic>.from(data) : response;
}

List<Map<String, dynamic>> _mapList(Object? value) {
  if (value is! List) return const [];
  return value
      .whereType<Map>()
      .map((item) => Map<String, dynamic>.from(item))
      .toList();
}

int _asInt(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
