import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'chat_detail_screen.dart';

class RecruitingPipelineScreen extends StatefulWidget {
  const RecruitingPipelineScreen({super.key});

  @override
  State<RecruitingPipelineScreen> createState() =>
      _RecruitingPipelineScreenState();
}

class _RecruitingPipelineScreenState extends State<RecruitingPipelineScreen> {
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
                  items: _allowedStatuses(application)
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

  Future<void> _openChat(Map<String, dynamic> application) async {
    try {
      var conversationId = _integer(application['conversation_id']);
      var title = _jobLabel(application);
      if (conversationId <= 0) {
        final response = await _client.openRecruitingApplicationChat(
          _integer(application['id']),
        );
        final data = _map(response['data']);
        conversationId = _integer(data['conversation_id']);
        title = '${data['name'] ?? title}';
      }
      if (!mounted || conversationId <= 0) return;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ChatDetailScreen(
            conversationId: conversationId,
            title: title,
            kind: 'group',
          ),
        ),
      );
      if (mounted) await _reload();
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('recruitingPipeline.chatError'))),
      );
    }
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
          final applications = _maps(_map(payload['applications'])['data']);
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
                            if (_map(application['profile_match']).isNotEmpty)
                              _MatchPanel(
                                match: _map(application['profile_match']),
                              )
                            else ...[
                              const SizedBox(height: 10),
                              Text(
                                t('recruitingPipeline.profileNotShared'),
                                style: const TextStyle(
                                  color: AirmiusColors.muted,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                            if (_map(
                              application['membership_handoff'],
                            ).isNotEmpty) ...[
                              const SizedBox(height: 10),
                              StatusPill(
                                t('recruitingPipeline.membershipReady'),
                                color: AirmiusColors.green,
                              ),
                            ],
                            if (application['can_edit'] == true ||
                                application['can_delete'] == true) ...[
                              const SizedBox(height: 12),
                              Row(
                                children: [
                                  if (application['can_delete'] == true)
                                    Expanded(
                                      child: OutlinedButton.icon(
                                        onPressed: () => _erase(application),
                                        icon: const Icon(Icons.delete_outline),
                                        label: Text(
                                          t('recruitingPipeline.erase'),
                                        ),
                                      ),
                                    ),
                                  if (application['can_edit'] == true &&
                                      application['can_delete'] == true)
                                    const SizedBox(width: 8),
                                  if (application['can_edit'] == true)
                                    Expanded(
                                      child: FilledButton.icon(
                                        onPressed: () => _edit(application),
                                        icon: const Icon(Icons.edit_outlined),
                                        label: Text(
                                          t('recruitingPipeline.edit'),
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                            ],
                            if (application['can_open_chat'] == true) ...[
                              const SizedBox(height: 8),
                              OutlinedButton.icon(
                                onPressed: () => _openChat(application),
                                icon: const Icon(Icons.forum_outlined),
                                label: Text(
                                  t(
                                    _integer(application['conversation_id']) > 0
                                        ? 'recruitingPipeline.openChat'
                                        : 'recruitingPipeline.startChat',
                                  ),
                                ),
                              ),
                            ],
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
    return [
      club['name'],
      job['title'],
    ].where((value) => value != null && '$value'.trim().isNotEmpty).join(' · ');
  }

  List<String> _allowedStatuses(Map<String, dynamic> application) {
    final allowed = application['allowed_statuses'];
    if (allowed is! List) return _statuses;
    final values = allowed
        .map((value) => '$value')
        .where((value) => _statuses.contains(value))
        .toList(growable: false);
    return values.isEmpty ? _statuses : values;
  }
}

class _MatchPanel extends StatelessWidget {
  const _MatchPanel({required this.match});

  final Map<String, dynamic> match;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final dimensions = _maps(match['dimensions']);
    final score = match['score'];

    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AirmiusColors.blue.withValues(alpha: .08),
        border: Border.all(color: AirmiusColors.blue.withValues(alpha: .3)),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  t('recruitingPipeline.profileMatch'),
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
              if (score != null)
                StatusPill('$score%', color: AirmiusColors.blue),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            t('recruitingPipeline.assistiveOnly'),
            style: const TextStyle(color: AirmiusColors.muted, fontSize: 12),
          ),
          if (dimensions.isNotEmpty) ...[
            const SizedBox(height: 8),
            ...dimensions.map(
              (dimension) => Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Row(
                  children: [
                    Icon(
                      dimension['matched'] == true
                          ? Icons.check_circle_outline
                          : Icons.info_outline,
                      size: 17,
                      color: dimension['matched'] == true
                          ? AirmiusColors.green
                          : AirmiusColors.amber,
                    ),
                    const SizedBox(width: 7),
                    Text(
                      t(
                        'recruitingPipeline.dimension.${dimension['key'] ?? 'sport'}',
                      ),
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

Map<String, dynamic> _map(dynamic value) => value is Map
    ? value.map((key, item) => MapEntry('$key', item))
    : <String, dynamic>{};

List<Map<String, dynamic>> _maps(dynamic value) => value is List
    ? value.map(_map).toList(growable: false)
    : const <Map<String, dynamic>>[];

int _integer(dynamic value) =>
    value is int ? value : int.tryParse('$value') ?? 0;
