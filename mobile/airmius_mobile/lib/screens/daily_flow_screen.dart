import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'admin_provider_contracts_screen.dart';
import 'club_asset_inventory_checkout_suite_screen.dart';
import 'club_membership_management_screen.dart';
import 'club_profile_editor_screen.dart';
import 'club_request_inbox_screen.dart';
import 'notifications_center_screen.dart';
import 'nutrition_center_screen.dart';
import 'sport_map_center_screen.dart';
import 'training_center_screen.dart';
import 'teams_center_screen.dart';

/// A focused, API-backed view of the user's day.
///
/// The old implementation showed fixed training, calorie and water values and
/// routed write actions to a generic UI confirmation. This screen now renders
/// the same authenticated daily-flow contract as the dashboard and opens the
/// real feature centres for every step.
class DailyFlowScreen extends StatefulWidget {
  const DailyFlowScreen({super.key});

  @override
  State<DailyFlowScreen> createState() => _DailyFlowScreenState();
}

class _DailyFlowScreenState extends State<DailyFlowScreen> {
  Future<JsonMap>? _future;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<JsonMap> _load() async {
    final response = await _client.dashboardDailyFlow();
    final data = response['data'];
    return data is JsonMap ? data : <String, dynamic>{};
  }

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('dailyFlow.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('common.retry'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('dailyFlow.title'),
        subtitle: t('dailyFlow.subtitle'),
        child: FutureBuilder<JsonMap>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return _DailyFlowLoading(label: t('dailyFlow.loading'));
            }
            if (snapshot.hasError) {
              return _DailyFlowError(onRetry: _reload);
            }
            return _buildFlow(snapshot.data ?? const {});
          },
        ),
      ),
    );
  }

  Widget _buildFlow(JsonMap data) {
    final t = AirmiusScope.of(context).t;
    final score = _int(data['score']);
    final summary = _text(data['summary'], fallback: t('dailyFlow.noData'));
    final note = _text(data['coach_note']);
    final rawSteps = data['steps'];
    final steps = rawSteps is List
        ? rawSteps
              .whereType<Map>()
              .map((item) => item.cast<String, dynamic>())
              .toList()
        : <JsonMap>[];
    final attention = data['attention'];
    final rawAttentionItems = attention is Map ? attention['items'] : null;
    final attentionItems = rawAttentionItems is List
        ? rawAttentionItems
              .whereType<Map>()
              .map((item) => item.cast<String, dynamic>())
              .toList()
        : <JsonMap>[];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('dailyFlow.today')),
              const SizedBox(height: 8),
              Text(
                '$score%',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.primary,
                  fontSize: 38,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                summary,
                style: TextStyle(color: _muted(context), height: 1.4),
              ),
              if (note.isNotEmpty) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Theme.of(
                      context,
                    ).colorScheme.surface.withValues(alpha: 0.68),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Text(
                    note,
                    style: TextStyle(color: _textColor(context), height: 1.4),
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 14),
        if (attentionItems.isNotEmpty) ...[
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Eyebrow(t('dailyFlow.attentionTitle')),
                const SizedBox(height: 6),
                Text(
                  t('dailyFlow.attentionSubtitle'),
                  style: TextStyle(color: _muted(context), height: 1.35),
                ),
                const SizedBox(height: 10),
                for (final item in attentionItems)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(_attentionIcon(_text(item['key']))),
                    title: Text(
                      _attentionTitle(t, _text(item['key'])),
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    subtitle: Text(
                      '${_int(item['count'])} ${t('dailyFlow.openItems')}',
                    ),
                    trailing: const Icon(Icons.arrow_forward_outlined),
                    onTap: () => _openAttention(item),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 14),
        ],
        if (steps.isEmpty)
          AirmiusPanel(
            child: Text(
              t('dailyFlow.noData'),
              style: TextStyle(color: _muted(context)),
            ),
          )
        else
          for (final step in steps) ...[
            _DailyFlowStepCard(
              step: step,
              title: _stepTitle(t, _text(step['key'])),
              onOpen: () => _openStep(_text(step['key'])),
            ),
            const SizedBox(height: 12),
          ],
      ],
    );
  }

  void _openStep(String key) {
    final screen = switch (key) {
      'training' => const TrainingCenterScreen(),
      'route' => const SportMapCenterScreen(),
      'nutrition' || 'hydration' => const NutritionCenterScreen(),
      'reminders' => const NotificationsCenterScreen(),
      _ => null,
    };
    if (screen == null) return;
    Navigator.push(context, MaterialPageRoute(builder: (_) => screen));
  }

  void _openAttention(JsonMap item) {
    final key = _text(item['key']);
    final screen = switch (key) {
      'applications' => const ClubRequestInboxScreen(),
      'payments' => const ClubMembershipManagementScreen(
        initialSection: 'payments',
      ),
      'approvals' when _int(item['inventory_count']) > 0 =>
        const ClubAssetInventoryCheckoutSuiteScreen(),
      'approvals' => const ClubMembershipManagementScreen(),
      'documents' => const ClubRequestInboxScreen(),
      'deadlines' when _int(item['contract_count']) > 0 =>
        const AdminProviderContractsScreen(),
      'deadlines' => const ClubProfileEditorScreen(),
      'tasks' => const TeamsCenterScreen(),
      _ => null,
    };
    if (screen == null) return;
    Navigator.push(context, MaterialPageRoute(builder: (_) => screen));
  }
}

String _attentionTitle(String Function(String) t, String key) => switch (key) {
  'applications' => t('dailyFlow.attentionApplications'),
  'payments' => t('dailyFlow.attentionPayments'),
  'approvals' => t('dailyFlow.attentionApprovals'),
  'documents' => t('dailyFlow.attentionDocuments'),
  'deadlines' => t('dailyFlow.attentionDeadlines'),
  'tasks' => t('dailyFlow.attentionTasks'),
  _ => key,
};

IconData _attentionIcon(String key) => switch (key) {
  'applications' => Icons.person_add_alt_1_outlined,
  'payments' => Icons.receipt_long_outlined,
  'approvals' => Icons.approval_outlined,
  'documents' => Icons.file_present_outlined,
  'deadlines' => Icons.hourglass_bottom_outlined,
  'tasks' => Icons.task_alt_outlined,
  _ => Icons.task_alt_outlined,
};

class _DailyFlowStepCard extends StatelessWidget {
  const _DailyFlowStepCard({
    required this.step,
    required this.title,
    required this.onOpen,
  });

  final JsonMap step;
  final String title;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final progress = _int(step['progress']).clamp(0, 100);
    final body = _text(step['body'], fallback: t('dailyFlow.noData'));
    final meta = _text(step['meta']);
    final cta = _text(step['cta'], fallback: t('dailyFlow.open'));
    return AirmiusPanel(
      onTap: onOpen,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: TextStyle(
                    color: _textColor(context),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              Text(
                '$progress%',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.primary,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(body, style: TextStyle(color: _muted(context), height: 1.35)),
          if (meta.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              meta,
              style: TextStyle(
                color: _muted(context),
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              value: progress / 100,
              minHeight: 8,
              backgroundColor: Theme.of(
                context,
              ).colorScheme.surfaceContainerHighest,
              valueColor: AlwaysStoppedAnimation<Color>(
                Theme.of(context).colorScheme.primary,
              ),
            ),
          ),
          const SizedBox(height: 10),
          Align(
            alignment: AlignmentDirectional.centerEnd,
            child: TextButton.icon(
              onPressed: onOpen,
              icon: const Icon(Icons.arrow_forward_outlined),
              label: Text(cta),
            ),
          ),
        ],
      ),
    );
  }
}

class _DailyFlowLoading extends StatelessWidget {
  const _DailyFlowLoading({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            label,
            style: TextStyle(
              color: _muted(context),
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 12),
          const LinearProgressIndicator(value: 0.35, minHeight: 4),
        ],
      ),
    );
  }
}

class _DailyFlowError extends StatelessWidget {
  const _DailyFlowError({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Row(
        children: [
          Expanded(
            child: Text(
              t('dashboard.liveDataUnavailable'),
              style: TextStyle(
                color: _muted(context),
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          TextButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_outlined),
            label: Text(t('common.retry')),
          ),
        ],
      ),
    );
  }
}

String _stepTitle(String Function(String) t, String key) => switch (key) {
  'training' => t('dashboard.training'),
  'route' => t('dashboard.sportMap'),
  'nutrition' => t('dashboard.nutrition'),
  'hydration' => t('dashboard.hydration'),
  'reminders' => t('dashboard.inbox'),
  _ => t('dashboard.importantToday'),
};

String _text(Object? value, {String fallback = ''}) {
  final text = '$value'.trim();
  return text.isEmpty || text == 'null' ? fallback : text;
}

int _int(Object? value) =>
    value is num ? value.round() : int.tryParse('$value') ?? 0;

Color _textColor(BuildContext context) => airmiusTextColor(context);

Color _muted(BuildContext context) => airmiusMutedColor(context);
