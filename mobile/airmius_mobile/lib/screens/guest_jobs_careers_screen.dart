import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

/// Public recruiting directory backed by the same published club jobs as web.
class GuestJobsCareersScreen extends StatefulWidget {
  const GuestJobsCareersScreen({super.key});

  @override
  State<GuestJobsCareersScreen> createState() => _GuestJobsCareersScreenState();
}

class _GuestJobsCareersScreenState extends State<GuestJobsCareersScreen> {
  final TextEditingController _queryController = TextEditingController();
  Future<_RecruitingPage>? _future;
  String _type = '';

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

  @override
  void dispose() {
    _queryController.dispose();
    super.dispose();
  }

  Future<_RecruitingPage> _load() async {
    final response = await _client.publicRecruitingJobs(
      query: _queryController.text,
      type: _type.isEmpty ? null : _type,
    );
    final jobs = _maps(response['data'])
        .map(_RecruitingJob.fromJson)
        .where((job) => job.id > 0 && job.title.isNotEmpty)
        .toList(growable: false);
    final meta = _map(response['meta']);

    return _RecruitingPage(
      jobs: jobs,
      total: _integer(meta['total'], jobs.length),
    );
  }

  Future<void> _reload() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  void _applyFilters() {
    FocusScope.of(context).unfocus();
    setState(() => _future = _load());
  }

  Future<void> _openInterest(_RecruitingJob job) async {
    final services = AirmiusServicesScope.of(context);
    final user = services.authState.session?.user;
    final name = TextEditingController(text: user?.name ?? '');
    final email = TextEditingController(text: user?.email ?? '');
    final phone = TextEditingController(text: user?.phone ?? '');
    final message = TextEditingController();
    final idempotencyKey =
        'recruiting-${job.id}-${DateTime.now().microsecondsSinceEpoch}';
    var sending = false;
    var acceptedPrivacy = false;
    String? error;

    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('recruitingMobile.interestTitle')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  job.title,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: name,
                  enabled: !sending,
                  textInputAction: TextInputAction.next,
                  autofillHints: const [AutofillHints.name],
                  decoration: InputDecoration(
                    labelText: t('recruitingMobile.name'),
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: email,
                  enabled: !sending,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  autofillHints: const [AutofillHints.email],
                  decoration: InputDecoration(
                    labelText: t('recruitingMobile.email'),
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: phone,
                  enabled: !sending,
                  keyboardType: TextInputType.phone,
                  textInputAction: TextInputAction.next,
                  autofillHints: const [AutofillHints.telephoneNumber],
                  decoration: InputDecoration(
                    labelText: t('recruitingMobile.phone'),
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: message,
                  enabled: !sending,
                  minLines: 3,
                  maxLines: 6,
                  maxLength: 2000,
                  decoration: InputDecoration(
                    labelText: t('recruitingMobile.message'),
                    alignLabelWithHint: true,
                  ),
                ),
                if (error != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    error!,
                    style: const TextStyle(
                      color: AirmiusColors.red,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
                const SizedBox(height: 6),
                Text(
                  t('recruitingMobile.privacy'),
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    fontSize: 12,
                    height: 1.35,
                  ),
                ),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: acceptedPrivacy,
                  onChanged: sending
                      ? null
                      : (value) => setDialogState(
                          () => acceptedPrivacy = value ?? false,
                        ),
                  title: Text(t('recruitingMobile.privacyAccept')),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: sending ? null : () => Navigator.pop(dialogContext),
              child: Text(t('recruitingMobile.cancel')),
            ),
            FilledButton.icon(
              onPressed: sending
                  ? null
                  : () async {
                      if (name.text.trim().isEmpty ||
                          !email.text.contains('@') ||
                          !acceptedPrivacy) {
                        setDialogState(
                          () => error = t('recruitingMobile.required'),
                        );
                        return;
                      }
                      setDialogState(() {
                        sending = true;
                        error = null;
                      });
                      try {
                        await _client.submitPublicRecruitingInterest(job.id, {
                          'name': name.text.trim(),
                          'email': email.text.trim(),
                          'phone': phone.text.trim().isEmpty
                              ? null
                              : phone.text.trim(),
                          'message': message.text.trim().isEmpty
                              ? null
                              : message.text.trim(),
                          'accepted_privacy': true,
                        }, idempotencyKey: idempotencyKey);
                        if (dialogContext.mounted) {
                          Navigator.pop(dialogContext, true);
                        }
                      } catch (_) {
                        if (dialogContext.mounted) {
                          setDialogState(() {
                            sending = false;
                            error = t('recruitingMobile.submitError');
                          });
                        }
                      }
                    },
              icon: sending
                  ? const SizedBox.square(
                      dimension: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.send_outlined),
              label: Text(
                t(
                  sending
                      ? 'recruitingMobile.sending'
                      : 'recruitingMobile.send',
                ),
              ),
            ),
          ],
        ),
      ),
    );

    name.dispose();
    email.dispose();
    phone.dispose();
    message.dispose();

    if (submitted == true && mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('recruitingMobile.sent'))));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('recruitingMobile.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('recruitingMobile.reload'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<_RecruitingPage>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: AirmiusPanel(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        Icons.cloud_off_outlined,
                        color: AirmiusColors.red,
                      ),
                      const SizedBox(height: 10),
                      Text(t('recruitingMobile.error')),
                      const SizedBox(height: 14),
                      AirmiusButton(
                        label: t('recruitingMobile.retry'),
                        icon: Icons.refresh_outlined,
                        onPressed: _reload,
                      ),
                    ],
                  ),
                ),
              ),
            );
          }

          final page = snapshot.data ?? const _RecruitingPage();
          return PageFrame(
            title: t('recruitingMobile.title'),
            subtitle: t('recruitingMobile.subtitle'),
            onRefresh: _reload,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('recruitingMobile.eyebrow')),
                      const SizedBox(height: 8),
                      Text(
                        t('recruitingMobile.hero'),
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        '${page.total} ${t('recruitingMobile.results')}',
                        style: const TextStyle(
                          color: AirmiusColors.muted,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextField(
                        controller: _queryController,
                        textInputAction: TextInputAction.search,
                        onSubmitted: (_) => _applyFilters(),
                        decoration: InputDecoration(
                          labelText: t('recruitingMobile.search'),
                          prefixIcon: const Icon(Icons.search_outlined),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          _TypeChip(
                            label: t('recruitingMobile.all'),
                            selected: _type.isEmpty,
                            onTap: () {
                              _type = '';
                              _applyFilters();
                            },
                          ),
                          _TypeChip(
                            label: t('recruitingMobile.professional'),
                            selected: _type == 'professional',
                            onTap: () {
                              _type = 'professional';
                              _applyFilters();
                            },
                          ),
                          _TypeChip(
                            label: t('recruitingMobile.volunteer'),
                            selected: _type == 'volunteer',
                            onTap: () {
                              _type = 'volunteer';
                              _applyFilters();
                            },
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                if (page.jobs.isEmpty)
                  EmptyPanel(t('recruitingMobile.empty'))
                else
                  ...page.jobs.map(
                    (job) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _JobCard(
                        job: job,
                        onInterest: () => _openInterest(job),
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
}

class _TypeChip extends StatelessWidget {
  const _TypeChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onTap(),
      selectedColor: AirmiusColors.blue.withValues(alpha: .2),
      labelStyle: const TextStyle(fontWeight: FontWeight.w900),
    );
  }
}

class _JobCard extends StatelessWidget {
  const _JobCard({required this.job, required this.onInterest});

  final _RecruitingJob job;
  final VoidCallback onInterest;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final isVolunteer = job.type == 'volunteer';
    final accent = isVolunteer ? AirmiusColors.green : AirmiusColors.blue;
    final meta = [
      job.location,
      job.workload,
      job.employmentType,
    ].where((value) => value.isNotEmpty).join(' • ');

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: .15),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(
                  isVolunteer
                      ? Icons.volunteer_activism_outlined
                      : Icons.work_outline,
                  color: accent,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    StatusPill(
                      t(
                        isVolunteer
                            ? 'recruitingMobile.volunteer'
                            : 'recruitingMobile.professional',
                      ),
                      color: accent,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      job.title,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (job.clubName.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        [
                          job.clubName,
                          job.sportType,
                        ].where((value) => value.isNotEmpty).join(' • '),
                        style: TextStyle(
                          color: accent,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          if (meta.isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              meta,
              style: const TextStyle(
                color: AirmiusColors.muted,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
          const SizedBox(height: 12),
          Text(
            job.description,
            style: const TextStyle(
              color: AirmiusColors.muted,
              height: 1.45,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 14),
          AirmiusButton(
            label: t('recruitingMobile.interested'),
            icon: Icons.send_outlined,
            onPressed: onInterest,
          ),
        ],
      ),
    );
  }
}

class _RecruitingPage {
  const _RecruitingPage({this.jobs = const [], this.total = 0});

  final List<_RecruitingJob> jobs;
  final int total;
}

class _RecruitingJob {
  const _RecruitingJob({
    required this.id,
    required this.title,
    required this.type,
    required this.description,
    required this.location,
    required this.workload,
    required this.employmentType,
    required this.clubName,
    required this.sportType,
  });

  final int id;
  final String title;
  final String type;
  final String description;
  final String location;
  final String workload;
  final String employmentType;
  final String clubName;
  final String sportType;

  factory _RecruitingJob.fromJson(Map<String, dynamic> json) {
    final club = _map(json['club']);
    return _RecruitingJob(
      id: _integer(json['id']),
      title: _text(json['title']),
      type: _text(json['type']),
      description: _text(json['description']),
      location: _text(json['location']),
      workload: _text(json['workload']),
      employmentType: _text(json['employment_type']),
      clubName: _text(club['name']),
      sportType: _text(club['sport_type']),
    );
  }
}

List<Map<String, dynamic>> _maps(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : const [];

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

String _text(Object? value) => value?.toString().trim() ?? '';

int _integer(Object? value, [int fallback = 0]) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;
