import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class RecruitingPipelineScreen extends StatefulWidget {
  const RecruitingPipelineScreen({super.key});

  @override
  State<RecruitingPipelineScreen> createState() =>
      _RecruitingPipelineScreenState();
}

class _RecruitingPipelineScreenState
    extends State<RecruitingPipelineScreen> {
  static const _statuses = [
    'new',
    'reviewing',
    'contacted',
    'interview',
    'offered',
    'hired',
    'rejected',
  ];

  Future<Map<String, dynamic>>? _future;
  String _status = '';

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final response = await _client.recruitingPipeline(
      status: _status.isEmpty ? null : _status,
    );
    return _map(response['data']);
  }

  Future<void> _reload() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  Future<void> _edit(Map<String, dynamic> application) async {
    var status = '${application['status'] ?? 'new'}';
    final note = TextEditingController(
      text: '${application['internal_note'] ?? ''}',
    );
    var saving = false;
    String? error;

    final saved = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('recruitingPipeline.editTitle')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  '${application['name'] ?? ''}',
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: status,
                  decoration: InputDecoration(
                    labelText: t('recruitingPipeline.status'),
                  ),
                  items: _statuses
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(t('recruitingPipeline.status.$value')),
                        ),
                      )
                      .toList(growable: false),
                  onChanged: saving
                      ? null
                      : (value) => status = value ?? status,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: note,
                  enabled: !saving,
                  minLines: 3,
                  maxLines: 6,
                  maxLength: 2000,
                  decoration: InputDecoration(
                    labelText: t('recruitingPipeline.note'),
                    alignLabelWithHint: true,
                  ),
                ),
                if (error != null)
                  Text(
                    error!,
                    style: const TextStyle(
                      color: AirmiusColors.red,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: saving ? null : () => Navigator.pop(dialogContext),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: saving
                  ? null
                  : () async {
                      setDialogState(() {
                        saving = true;
                        error = null;
                      });
                      try {
                        await _client.updateRecruitingApplication(
                          _integer(application['id']),
                          {
                            'status': status,
                            'internal_note': note.text.trim().isEmpty
                                ? null
                                : note.text.trim(),
                          },
                        );
                        if (dialogContext.mounted) {
                          Navigator.pop(dialogContext, true);
                        }
                      } catch (_) {
                        if (dialogContext.mounted) {
                          setDialogState(() {
                            saving = false;
                            error = t('recruitingPipeline.saveError');
                          });
                        }
                      }
                    },
              child: Text(t('recruitingPipeline.save')),
            ),
          ],
        ),
      ),
    );
    note.dispose();
    if (saved == true && mounted) await _reload();
  }

  Future<void> _erase(Map<String, dynamic> application) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('recruitingPipeline.eraseTitle')),
        content: Text(t('recruitingPipeline.eraseBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('recruitingPipeline.erase')),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await _client.deleteRecruitingApplication(_integer(application['id']));
    if (mounted) await _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(t('recruitingPipeline.title')),
        actions: [
          IconButton(
            tooltip: t('recruitingPipeline.reload'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  EmptyPanel(t('recruitingPipeline.loadError')),
                  const SizedBox(height: 12),
                  FilledButton(
                    onPressed: _reload,
                    child: Text(t('recruitingPipeline.retry')),
                  ),
                ],
              ),
            );
          }

          final payload = snapshot.data ?? const <String, dynamic>{};
          final applications = _maps(
            _map(payload['applications'])['data'],
          );
          final stats = _map(payload['stats']);

          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                PageTitle(
                  title: t('recruitingPipeline.title'),
                  subtitle: t('recruitingPipeline.subtitle'),
                ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    Metric(
                      label: t('recruitingPipeline.total'),
                      value: '${stats['total'] ?? 0}',
                    ),
                    Metric(
                      label: t('recruitingPipeline.new'),
                      value: '${stats['new'] ?? 0}',
                    ),
                    Metric(
                      label: t('recruitingPipeline.hired'),
                      value: '${stats['hired'] ?? 0}',
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: _status,
                  decoration: InputDecoration(
                    labelText: t('recruitingPipeline.filter'),
                  ),
                  items: [
                    DropdownMenuItem(
                      value: '',
                      child: Text(t('recruitingPipeline.status.all')),
                    ),
                    ..._statuses.map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text(t('recruitingPipeline.status.$value')),
                      ),
                    ),
                  ],
                  onChanged: (value) {
                    _status = value ?? '';
                    _reload();
                  },
                ),
                const SizedBox(height: 14),
                if (applications.isEmpty)
                  EmptyPanel(t('recruitingPipeline.empty'))
                else
                  ...applications.map(
                    (application) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: AirmiusPanel(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        '${application['name'] ?? ''}',
                                        style: const TextStyle(
                                          fontSize: 17,
                                          fontWeight: FontWeight.w900,
                                        ),
                                      ),
                                      const SizedBox(height: 3),
                                      Text('${application['email'] ?? ''}'),
                                    ],
                                  ),
                                ),
                                StatusPill(
                                  t(
                                    'recruitingPipeline.status.${application['status'] ?? 'new'}',
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 10),
                            Text(_jobLabel(application)),
                            if ('${application['message'] ?? ''}'
                                .trim()
                                .isNotEmpty) ...[
                              const SizedBox(height: 8),
                              Text('${application['message']}'),
                            ],
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                Expanded(
                                  child: OutlinedButton.icon(
                                    onPressed: () => _erase(application),
                                    icon: const Icon(Icons.delete_outline),
                                    label: Text(t('recruitingPipeline.erase')),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: FilledButton.icon(
                                    onPressed: () => _edit(application),
                                    icon: const Icon(Icons.edit_outlined),
                                    label: Text(t('recruitingPipeline.edit')),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }

  String _jobLabel(Map<String, dynamic> application) {
    final job = _map(application['job']);
    final club = _map(job['club']);
    return [club['name'], job['title']]
        .where((value) => value != null && '$value'.trim().isNotEmpty)
        .join(' · ');
  }
}

Map<String, dynamic> _map(dynamic value) => value is Map
    ? value.map((key, item) => MapEntry('$key', item))
    : <String, dynamic>{};

List<Map<String, dynamic>> _maps(dynamic value) => value is List
    ? value.map(_map).toList(growable: false)
    : const <Map<String, dynamic>>[];

int _integer(dynamic value) => value is int
    ? value
    : int.tryParse('$value') ?? 0;
