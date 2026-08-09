import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

class SportIntegrationsScreen extends StatefulWidget {
  const SportIntegrationsScreen({super.key});

  @override
  State<SportIntegrationsScreen> createState() =>
      _SportIntegrationsScreenState();
}

class _SportIntegrationsScreenState extends State<SportIntegrationsScreen> {
  Future<AirmiusSportIntegrationBundle>? _future;
  final Set<int> _busyAccounts = {};
  final Set<String> _busyProviders = {};

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<AirmiusSportIntegrationBundle> _load() async {
    return AirmiusSportIntegrationBundle.fromJson(
      await _client.sportIntegrations(),
    );
  }

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          scope.t('fitness.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: scope.t('fitness.refresh'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: FutureBuilder<AirmiusSportIntegrationBundle>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError || snapshot.data == null) {
            return PageFrame(
              title: scope.t('fitness.title'),
              subtitle: scope.t('fitness.subtitle'),
              onRefresh: () async => _reload(),
              child: AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Icon(
                      Icons.cloud_off_outlined,
                      size: 42,
                      color: Theme.of(context).colorScheme.error,
                    ),
                    const SizedBox(height: 10),
                    Text(
                      scope.t('fitness.loadError'),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 14),
                    AirmiusButton(
                      label: scope.t('fitness.retry'),
                      icon: Icons.refresh_rounded,
                      onPressed: _reload,
                    ),
                  ],
                ),
              ),
            );
          }
          return _content(snapshot.data!);
        },
      ),
    );
  }

  Widget _content(AirmiusSportIntegrationBundle bundle) {
    final scope = AirmiusScope.of(context);
    return PageFrame(
      title: scope.t('fitness.title'),
      subtitle: scope.t('fitness.subtitle'),
      onRefresh: () async => _reload(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AirmiusPanel(
            gradient: true,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  scope.t('fitness.heroTitle'),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  scope.t('fitness.heroBody'),
                  style: Theme.of(
                    context,
                  ).textTheme.bodyMedium?.copyWith(height: 1.45),
                ),
                const SizedBox(height: 12),
                Text(
                  scope.t('fitness.safeNote'),
                  style: Theme.of(
                    context,
                  ).textTheme.bodySmall?.copyWith(height: 1.4),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            title: scope.t('fitness.providerStatus'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final provider in bundle.providers) ...[
                  _ProviderCard(
                    provider: provider,
                    busyProvider: _busyProviders.contains(provider.key),
                    busyAccount:
                        provider.account != null &&
                        _busyAccounts.contains(provider.account!.id),
                    onRequest: () => _request(provider),
                    onSync: provider.account == null
                        ? null
                        : () => _sync(provider.account!),
                    onDisconnect: provider.account == null
                        ? null
                        : () => _disconnect(provider.account!),
                  ),
                  const SizedBox(height: 10),
                ],
              ],
            ),
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: scope.t('fitness.importActivity'),
            icon: Icons.file_upload_outlined,
            onPressed: bundle.providers.isEmpty
                ? null
                : () => _openImportDialog(bundle.providers),
          ),
          const SizedBox(height: 16),
          _ActivitiesPanel(activities: bundle.activities),
        ],
      ),
    );
  }

  Future<void> _request(AirmiusSportIntegrationProvider provider) async {
    if (_busyProviders.contains(provider.key)) return;
    setState(() => _busyProviders.add(provider.key));
    await _run(
      () => _client.requestSportIntegration(provider.key),
      AirmiusScope.of(context).t('fitness.requestedSuccess'),
    );
    if (mounted) setState(() => _busyProviders.remove(provider.key));
  }

  Future<void> _sync(AirmiusSportIntegrationAccount account) async {
    if (_busyAccounts.contains(account.id)) return;
    setState(() => _busyAccounts.add(account.id));
    await _run(
      () => _client.syncSportIntegration(account.id),
      AirmiusScope.of(context).t('fitness.syncSuccess'),
    );
    if (mounted) setState(() => _busyAccounts.remove(account.id));
  }

  Future<void> _disconnect(AirmiusSportIntegrationAccount account) async {
    final scope = AirmiusScope.of(context);
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: Text(scope.t('fitness.disconnect')),
            content: Text(scope.t('fitness.safeNote')),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: Text(scope.t('fitness.cancel')),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: Text(scope.t('fitness.disconnect')),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || _busyAccounts.contains(account.id)) return;
    setState(() => _busyAccounts.add(account.id));
    await _run(
      () => _client.disconnectSportIntegration(account.id),
      scope.t('fitness.disconnectSuccess'),
    );
    if (mounted) setState(() => _busyAccounts.remove(account.id));
  }

  Future<void> _run(
    Future<AirmiusJson> Function() action,
    String successMessage,
  ) async {
    try {
      await action();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(successMessage)));
      _reload();
    } catch (error) {
      if (!mounted) return;
      final message = error is AirmiusApiException
          ? error.userMessage
          : AirmiusScope.of(context).t('common.errorDetails');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${AirmiusScope.of(context).t('fitness.actionError')} $message',
          ),
        ),
      );
    }
  }

  Future<void> _openImportDialog(
    List<AirmiusSportIntegrationProvider> providers,
  ) async {
    final scope = AirmiusScope.of(context);
    final title = TextEditingController();
    final type = TextEditingController();
    final duration = TextEditingController();
    final distance = TextEditingController();
    final calories = TextEditingController();
    var provider = providers.first.key;
    var startedAt = DateTime.now();

    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(scope.t('fitness.importActivityTitle')),
          content: SizedBox(
            width: 520,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: provider,
                    decoration: InputDecoration(
                      labelText: scope.t('fitness.provider'),
                    ),
                    items: [
                      for (final item in providers)
                        DropdownMenuItem(
                          value: item.key,
                          child: Text(item.label),
                        ),
                    ],
                    onChanged: (value) {
                      if (value != null) setDialogState(() => provider = value);
                    },
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: title,
                    autofocus: true,
                    decoration: InputDecoration(
                      labelText: scope.t('fitness.activityTitle'),
                      hintText: scope.t('fitness.activityTitleHint'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: type,
                    decoration: InputDecoration(
                      labelText: scope.t('fitness.activityType'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.schedule_outlined),
                    title: Text(scope.t('fitness.startedAt')),
                    subtitle: Text(_formatDate(context, startedAt)),
                    onTap: () async {
                      final picked = await showDatePicker(
                        context: context,
                        firstDate: DateTime(2000),
                        lastDate: DateTime.now(),
                        initialDate: startedAt,
                      );
                      if (picked != null) {
                        setDialogState(
                          () => startedAt = DateTime(
                            picked.year,
                            picked.month,
                            picked.day,
                            startedAt.hour,
                            startedAt.minute,
                          ),
                        );
                      }
                    },
                  ),
                  TextField(
                    controller: duration,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: scope.t('fitness.durationMinutes'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: distance,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: InputDecoration(
                      labelText: scope.t('fitness.distanceKm'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: calories,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: scope.t('fitness.calories'),
                    ),
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(scope.t('fitness.cancel')),
            ),
            FilledButton(
              onPressed: () {
                final value = title.text.trim();
                if (value.isEmpty) return;
                Navigator.pop(dialogContext, {
                  'provider': provider,
                  'title': value,
                  'activity_type': type.text.trim(),
                  'started_at': startedAt.toIso8601String(),
                  'duration_seconds': _minutes(duration.text),
                  'distance_meters': _meters(distance.text),
                  'calories': int.tryParse(calories.text.trim()),
                });
              },
              child: Text(scope.t('fitness.save')),
            ),
          ],
        ),
      ),
    );
    title.dispose();
    type.dispose();
    duration.dispose();
    distance.dispose();
    calories.dispose();
    if (payload == null || !mounted) return;

    try {
      await _client.importSportActivity(
        provider: payload['provider'] as String,
        externalId: 'mobile-${DateTime.now().millisecondsSinceEpoch}',
        startedAt: payload['started_at'] as String,
        title: payload['title'] as String,
        activityType: (payload['activity_type'] as String).isEmpty
            ? null
            : payload['activity_type'] as String,
        durationSeconds: payload['duration_seconds'] as int?,
        distanceMeters: payload['distance_meters'] as int?,
        calories: payload['calories'] as int?,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(scope.t('fitness.activityImported'))),
      );
      _reload();
    } catch (error) {
      if (!mounted) return;
      final message = error is AirmiusApiException
          ? error.userMessage
          : scope.t('common.errorDetails');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${scope.t('fitness.actionError')} $message')),
      );
    }
  }
}

class _ProviderCard extends StatelessWidget {
  const _ProviderCard({
    required this.provider,
    required this.busyProvider,
    required this.busyAccount,
    required this.onRequest,
    required this.onSync,
    required this.onDisconnect,
  });

  final AirmiusSportIntegrationProvider provider;
  final bool busyProvider;
  final bool busyAccount;
  final VoidCallback onRequest;
  final VoidCallback? onSync;
  final VoidCallback? onDisconnect;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final status = _status(context, provider);
    final busy = busyProvider || busyAccount;
    return AirmiusPanel(
      borderColor: status.color.withValues(alpha: 0.42),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 23,
                backgroundColor: status.color.withValues(alpha: 0.14),
                child: Icon(status.icon, color: status.color),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      provider.label,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      provider.requestMessageKey != null
                          ? scope.t(provider.requestMessageKey!)
                          : provider.requestMessage ??
                                _mode(scope, provider.connectionMode),
                      style: Theme.of(
                        context,
                      ).textTheme.bodySmall?.copyWith(height: 1.35),
                    ),
                  ],
                ),
              ),
              StatusPill(status.label, color: status.color),
            ],
          ),
          const SizedBox(height: 9),
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              if (provider.direction.contains('import'))
                _InfoChip(
                  icon: Icons.download_outlined,
                  label: scope.t('fitness.importSupport'),
                ),
              if (provider.supportsGpsSamples)
                _InfoChip(
                  icon: Icons.route_outlined,
                  label: scope.t('fitness.gpsSupport'),
                ),
              if (provider.supportsBackgroundSync)
                _InfoChip(
                  icon: Icons.sync_outlined,
                  label: scope.t('fitness.backgroundSupport'),
                ),
            ],
          ),
          if (provider.account != null) ...[
            const SizedBox(height: 8),
            Text(_accountText(scope, provider.account!)),
            if (provider.account!.lastSyncedAt != null)
              Text(
                '${scope.t('fitness.lastSynced')}: ${_formatDate(context, provider.account!.lastSyncedAt!)}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
          ],
          const SizedBox(height: 9),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (provider.account == null)
                AirmiusButton(
                  label: provider.status == 'native_bridge'
                      ? scope.t('fitness.prepareImport')
                      : scope.t('fitness.connect'),
                  icon: provider.status == 'native_bridge'
                      ? Icons.file_download_outlined
                      : Icons.link_outlined,
                  onPressed: busy ? null : onRequest,
                )
              else if (provider.account?.status == 'connected' &&
                  provider.supportsDirectSync)
                AirmiusButton(
                  label: scope.t('fitness.sync'),
                  icon: Icons.sync_rounded,
                  onPressed: busy ? null : onSync,
                )
              else if (provider.account?.status == 'requested')
                AirmiusButton(
                  label: scope.t('fitness.accountRequested'),
                  icon: Icons.hourglass_top_outlined,
                  secondary: true,
                  onPressed: null,
                )
              else if (provider.account?.status == 'native_ready')
                AirmiusButton(
                  label: scope.t('fitness.importReady'),
                  icon: Icons.file_download_done_outlined,
                  secondary: true,
                  onPressed: null,
                ),
              if (onDisconnect != null)
                TextButton.icon(
                  onPressed: busy ? null : onDisconnect,
                  icon: const Icon(Icons.link_off_outlined),
                  label: Text(scope.t('fitness.disconnect')),
                ),
            ],
          ),
          if (busy) ...[
            const SizedBox(height: 8),
            const LinearProgressIndicator(minHeight: 3),
          ],
        ],
      ),
    );
  }
}

class _ActivitiesPanel extends StatelessWidget {
  const _ActivitiesPanel({required this.activities});

  final List<AirmiusSportIntegrationActivity> activities;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      title: scope.t('fitness.recentActivities'),
      child: activities.isEmpty
          ? Text(scope.t('fitness.noActivities'))
          : Column(
              children: [
                for (var index = 0; index < activities.length; index++) ...[
                  _ActivityRow(activity: activities[index]),
                  if (index < activities.length - 1) const Divider(height: 20),
                ],
              ],
            ),
    );
  }
}

class _ActivityRow extends StatelessWidget {
  const _ActivityRow({required this.activity});

  final AirmiusSportIntegrationActivity activity;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final details = <String>[
      if (activity.startedAt != null) _formatDate(context, activity.startedAt!),
      if (activity.durationSeconds > 0)
        scope
            .t('fitness.minutes')
            .replaceFirst(
              '{value}',
              (activity.durationSeconds / 60).round().toString(),
            ),
      if (activity.distanceMeters > 0)
        scope
            .t('fitness.distance')
            .replaceFirst(
              '{value}',
              (activity.distanceMeters / 1000).toStringAsFixed(
                activity.distanceMeters % 1000 == 0 ? 0 : 1,
              ),
            ),
      if (activity.calories != null)
        scope
            .t('fitness.caloriesValue')
            .replaceFirst('{value}', activity.calories.toString()),
    ];
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(
          Icons.directions_run_outlined,
          color: Theme.of(context).colorScheme.primary,
          size: 26,
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                activity.title,
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 3),
              Text('${activity.activityType} · ${activity.provider}'),
              if (details.isNotEmpty) Text(details.join(' · ')),
            ],
          ),
        ),
      ],
    );
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Chip(
      avatar: Icon(
        icon,
        size: 17,
        color: Theme.of(context).colorScheme.primary,
      ),
      label: Text(label),
      visualDensity: VisualDensity.compact,
    );
  }
}

({Color color, IconData icon, String label}) _status(
  BuildContext context,
  AirmiusSportIntegrationProvider provider,
) {
  final scope = AirmiusScope.of(context);
  final colors = Theme.of(context).colorScheme;
  if (provider.account?.status == 'connected') {
    return (
      color: colors.secondary,
      icon: Icons.check_circle_outline,
      label: scope.t('fitness.accountConnected'),
    );
  }
  if (provider.account?.status == 'error') {
    return (
      color: colors.error,
      icon: Icons.error_outline,
      label: scope.t('fitness.accountError'),
    );
  }
  if (provider.account?.status == 'requested') {
    return (
      color: colors.tertiary,
      icon: Icons.hourglass_top_outlined,
      label: scope.t('fitness.accountRequested'),
    );
  }
  return switch (provider.status) {
    'live_oauth' => (
      color: colors.primary,
      icon: Icons.link_outlined,
      label: scope.t('fitness.statusLive'),
    ),
    'native_bridge' => (
      color: colors.primary,
      icon: Icons.phone_android_outlined,
      label: scope.t('fitness.statusNative'),
    ),
    'partner_required' => (
      color: colors.tertiary,
      icon: Icons.handshake_outlined,
      label: scope.t('fitness.statusPartner'),
    ),
    _ => (
      color: colors.onSurfaceVariant,
      icon: Icons.schedule_outlined,
      label: scope.t('fitness.statusPlanned'),
    ),
  };
}

String _mode(AirmiusScope scope, String mode) {
  if (mode.contains('native')) return scope.t('fitness.statusNative');
  if (mode.contains('oauth')) return scope.t('fitness.statusLive');
  return scope.t('fitness.statusPlanned');
}

String _accountText(
  AirmiusScope scope,
  AirmiusSportIntegrationAccount account,
) {
  final messageKey = account.syncSummary['message_key']?.toString().trim();
  if (messageKey != null && messageKey.isNotEmpty) {
    return scope.t(messageKey);
  }
  final message = account.syncSummary['message']?.toString().trim();
  if (message != null && message.isNotEmpty) return message;
  return switch (account.status) {
    'connected' => scope.t('fitness.accountConnected'),
    'requested' => scope.t('fitness.accountRequested'),
    _ => scope.t('fitness.accountError'),
  };
}

String _formatDate(BuildContext context, DateTime date) {
  final local = date.toLocal();
  final material = MaterialLocalizations.of(context);
  return '${material.formatMediumDate(local)} ${material.formatTimeOfDay(TimeOfDay.fromDateTime(local))}';
}

int? _minutes(String value) {
  final parsed = int.tryParse(value.trim());
  return parsed == null ? null : parsed * 60;
}

int? _meters(String value) {
  final parsed = double.tryParse(value.trim());
  return parsed == null ? null : (parsed * 1000).round();
}
