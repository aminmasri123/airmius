import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';

class AdminTrainerApplicationsScreen extends StatefulWidget {
  const AdminTrainerApplicationsScreen({super.key});

  @override
  State<AdminTrainerApplicationsScreen> createState() => _ApplicationsState();
}

class _ApplicationsState extends State<AdminTrainerApplicationsScreen> {
  Future<AirmiusJson>? _future;
  int _page = 1;
  String? _status;
  bool _busy = false;

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

  Future<AirmiusJson> _load() =>
      _client.adminTrainerApplications(page: _page, status: _status);
  void _reload() => setState(() => _future = _load());

  Future<void> _review(Map<String, dynamic> item, bool approve) async {
    final notes = TextEditingController();
    final form = GlobalKey<FormState>();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          t(approve ? 'platformAdmin.approve' : 'platformAdmin.reject'),
        ),
        content: Form(
          key: form,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${(item['user'] as Map?)?['name'] ?? ''}'),
                const SizedBox(height: 16),
                TextFormField(
                  controller: notes,
                  minLines: 2,
                  maxLines: 5,
                  maxLength: 2000,
                  decoration: InputDecoration(
                    labelText: t(
                      approve
                          ? 'platformAdmin.noteOptional'
                          : 'platformAdmin.reason',
                    ),
                  ),
                  validator: (value) =>
                      !approve && (value?.trim().isEmpty ?? true)
                      ? t('platformAdmin.reason')
                      : null,
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () {
              if (form.currentState!.validate()) {
                Navigator.pop(dialogContext, true);
              }
            },
            child: Text(t('common.save')),
          ),
        ],
      ),
    );
    final text = notes.text.trim();
    notes.dispose();
    if (confirmed != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await _client.adminReviewTrainer(
        (item['id'] as num).toInt(),
        approve: approve,
        notes: text,
      );
      if (mounted) _reload();
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

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(t('adminNative.trainerApplications')),
      actions: [
        IconButton(
          tooltip: t('common.refresh'),
          onPressed: _busy ? null : _reload,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: DropdownButtonFormField<String>(
            initialValue: _status ?? 'all',
            decoration: InputDecoration(labelText: t('adminNative.status')),
            items: [
              for (final status in ['all', 'pending', 'approved', 'rejected'])
                DropdownMenuItem(
                  value: status,
                  child: Text(t('adminNative.$status')),
                ),
            ],
            onChanged: _busy
                ? null
                : (value) {
                    _status = value == 'all' ? null : value;
                    _page = 1;
                    _reload();
                  },
          ),
        ),
        if (_busy) const LinearProgressIndicator(),
        Expanded(
          child: FutureBuilder<AirmiusJson>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasError) {
                return Center(
                  child: Text(
                    snapshot.error is AirmiusApiException
                        ? (snapshot.error as AirmiusApiException).userMessage
                        : t('platformAdmin.loadFailed'),
                  ),
                );
              }
              final data = snapshot.data ?? {};
              final items = (data['data'] as List? ?? []).whereType<Map>();
              return ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (items.isEmpty)
                    Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(t('adminNative.empty')),
                    ),
                  for (final raw in items) ...[
                    Builder(
                      builder: (context) {
                        final item = Map<String, dynamic>.from(raw);
                        final user = item['user'] as Map? ?? {};
                        final details = item['application_data'] as Map? ?? {};
                        return ExpansionTile(
                          tilePadding: EdgeInsets.zero,
                          title: Text('${user['name'] ?? ''}'),
                          subtitle: Text(
                            '${user['email'] ?? ''}\n${t('adminNative.${item['status']}')}',
                          ),
                          children: [
                            if ('${item['message'] ?? ''}'.isNotEmpty)
                              ListTile(title: Text('${item['message']}')),
                            for (final key in [
                              'specialties',
                              'sports',
                              'experience',
                              'certification',
                            ])
                              if ('${details[key] ?? ''}'.isNotEmpty)
                                ListTile(
                                  title: Text(t('adminNative.$key')),
                                  subtitle: Text('${details[key]}'),
                                ),
                            if ('${item['review_notes'] ?? ''}'.isNotEmpty)
                              ListTile(
                                title: Text(t('platformAdmin.reason')),
                                subtitle: Text('${item['review_notes']}'),
                              ),
                            Padding(
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              child: Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: [
                                  FilledButton.icon(
                                    onPressed: _busy
                                        ? null
                                        : () => _review(item, true),
                                    icon: const Icon(Icons.check),
                                    label: Text(t('platformAdmin.approve')),
                                  ),
                                  OutlinedButton.icon(
                                    onPressed: _busy
                                        ? null
                                        : () => _review(item, false),
                                    icon: const Icon(Icons.close),
                                    label: Text(t('platformAdmin.reject')),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        );
                      },
                    ),
                    const Divider(),
                  ],
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      IconButton(
                        tooltip: t('adminNative.previous'),
                        onPressed: _page <= 1 || _busy
                            ? null
                            : () {
                                _page--;
                                _reload();
                              },
                        icon: const Icon(Icons.chevron_left),
                      ),
                      Text('$_page / ${data['last_page'] ?? 1}'),
                      IconButton(
                        tooltip: t('adminNative.next'),
                        onPressed:
                            _page >= (data['last_page'] as num? ?? 1) || _busy
                            ? null
                            : () {
                                _page++;
                                _reload();
                              },
                        icon: const Icon(Icons.chevron_right),
                      ),
                    ],
                  ),
                ],
              );
            },
          ),
        ),
      ],
    ),
  );
}
