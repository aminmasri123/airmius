import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'recruiting_pipeline_screen.dart';

class ClubJobsScreen extends StatefulWidget {
  const ClubJobsScreen({
    super.key,
    required this.clubId,
    required this.clubName,
  });

  final int clubId;
  final String clubName;

  @override
  State<ClubJobsScreen> createState() => _ClubJobsScreenState();
}

class _ClubJobsScreenState extends State<ClubJobsScreen> {
  Future<List<Map<String, dynamic>>>? _future;

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

  Future<List<Map<String, dynamic>>> _load() async {
    final response = await _client.clubJobs(widget.clubId);
    final data = response['data'];
    if (data is! List) return [];
    return data
        .whereType<Map>()
        .map((job) => Map<String, dynamic>.from(job))
        .toList();
  }

  Future<void> _reload() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  Future<void> _openJobForm([Map<String, dynamic>? job]) async {
    final title = TextEditingController(text: '${job?['title'] ?? ''}');
    final location = TextEditingController(text: '${job?['location'] ?? ''}');
    final description = TextEditingController(
      text: '${job?['description'] ?? ''}',
    );
    final contactEmail = TextEditingController(
      text: '${job?['contact_email'] ?? ''}',
    );
    var type = '${job?['type'] ?? 'volunteer'}';
    var isPublished = job?['is_published'] == true;
    var saving = false;
    String? error;

    final saved = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(job == null ? 'Stelle erstellen' : 'Stelle bearbeiten'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: title,
                  decoration: const InputDecoration(labelText: 'Titel'),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  initialValue: type,
                  decoration: const InputDecoration(labelText: 'Typ'),
                  items: [
                    DropdownMenuItem(
                      value: 'volunteer',
                      child: Text(t('recruitingMobile.volunteer')),
                    ),
                    DropdownMenuItem(
                      value: 'professional',
                      child: Text(t('recruitingMobile.professional')),
                    ),
                  ],
                  onChanged: (value) => setDialogState(() {
                    type = value ?? 'volunteer';
                  }),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: location,
                  decoration: const InputDecoration(labelText: 'Ort'),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: contactEmail,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                    labelText: 'Kontakt-E-Mail',
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: description,
                  minLines: 4,
                  maxLines: 7,
                  decoration: const InputDecoration(labelText: 'Beschreibung'),
                ),
                SwitchListTile(
                  value: isPublished,
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Direkt veröffentlichen'),
                  onChanged: (value) => setDialogState(() {
                    isPublished = value;
                  }),
                ),
                if (error != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ],
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
                      if (title.text.trim().isEmpty ||
                          description.text.trim().isEmpty) {
                        setDialogState(() {
                          error = 'Bitte gib Titel und Beschreibung ein.';
                        });
                        return;
                      }
                      setDialogState(() {
                        saving = true;
                        error = null;
                      });
                      final body = {
                        'title': title.text.trim(),
                        'type': type,
                        'location': location.text.trim().isEmpty
                            ? null
                            : location.text.trim(),
                        'contact_email': contactEmail.text.trim().isEmpty
                            ? null
                            : contactEmail.text.trim(),
                        'description': description.text.trim(),
                        'is_published': isPublished,
                      };
                      try {
                        if (job == null) {
                          await _client.createClubJob(widget.clubId, body);
                        } else {
                          await _client.updateClubJob(
                            widget.clubId,
                            _integer(job['id']),
                            body,
                          );
                        }
                        if (dialogContext.mounted) {
                          Navigator.pop(dialogContext, true);
                        }
                      } catch (_) {
                        setDialogState(() {
                          saving = false;
                          error = 'Die Stelle konnte nicht gespeichert werden.';
                        });
                      }
                    },
              child: Text(saving ? 'Speichern ...' : t('common.save')),
            ),
          ],
        ),
      ),
    );

    if (saved == true && mounted) {
      await _reload();
    }
  }

  Future<void> _deleteJob(Map<String, dynamic> job) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Stelle löschen'),
        content: Text('"${job['title'] ?? ''}" wirklich löschen?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Löschen'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await _client.deleteClubJob(widget.clubId, _integer(job['id']));
    if (mounted) await _reload();
  }

  void _openPipeline() {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => const RecruitingPipelineScreen()));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Jobs & Bewerbungen'),
        actions: [
          IconButton(
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openJobForm(),
        icon: const Icon(Icons.add),
        label: const Text('Stelle erstellen'),
      ),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return EmptyPanel('Die Stellen konnten nicht geladen werden.');
          }
          final jobs = snapshot.data ?? const [];
          return PageFrame(
            title: 'Jobs & Bewerbungen',
            subtitle: widget.clubName,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        t('recruitingPipeline.title'),
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 8),
                      Text(t('recruitingPipeline.subtitle')),
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: _openPipeline,
                        icon: const Icon(Icons.work_history_outlined),
                        label: Text(t('recruitingPipeline.title')),
                      ),
                    ],
                  ),
                ),
                if (jobs.isEmpty)
                  const EmptyPanel('Noch keine Stellen angelegt.')
                else
                  for (final job in jobs)
                    _JobCard(
                      job: job,
                      onEdit: () => _openJobForm(job),
                      onDelete: () => _deleteJob(job),
                    ),
                const SizedBox(height: 72),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _JobCard extends StatelessWidget {
  const _JobCard({
    required this.job,
    required this.onEdit,
    required this.onDelete,
  });

  final Map<String, dynamic> job;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final published = job['is_published'] == true;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  '${job['title'] ?? ''}',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              StatusPill(published ? 'Veröffentlicht' : 'Entwurf'),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            [
              '${job['type'] ?? ''}' == 'professional'
                  ? 'Beruflich'
                  : 'Ehrenamt',
              if ('${job['location'] ?? ''}'.trim().isNotEmpty)
                '${job['location']}',
              '${job['interests_count'] ?? 0} Bewerbungen',
            ].join(' · '),
          ),
          const SizedBox(height: 10),
          Text(
            '${job['description'] ?? ''}',
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onDelete,
                  icon: const Icon(Icons.delete_outline),
                  label: const Text('Löschen'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: FilledButton.icon(
                  onPressed: onEdit,
                  icon: const Icon(Icons.edit_outlined),
                  label: const Text('Bearbeiten'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

int _integer(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('$value') ?? 0;
}
