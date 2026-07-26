import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

/// Shows the authenticated user's real progress and safety-related setup.
///
/// The old page contained fixed demo gates and actions that did not persist
/// anything. This view is deliberately read-only: the server calculates the
/// score from the current account and the user can follow the remaining steps
/// without being shown invented counts.
class MaturityCenterScreen extends StatefulWidget {
  const MaturityCenterScreen({super.key});

  @override
  State<MaturityCenterScreen> createState() => _MaturityCenterScreenState();
}

class _MaturityCenterScreenState extends State<MaturityCenterScreen> {
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
    final response = await _client.maturityOverview();
    return _maturityMap(response['data']);
  }

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          scope.t('maturity.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: scope.t('maturity.reload'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<JsonMap>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final message = snapshot.error is AirmiusApiException
                ? (snapshot.error as AirmiusApiException).userMessage
                : scope.t('maturity.loadFailed');
            return PageFrame(
              title: scope.t('maturity.title'),
              subtitle: scope.t('maturity.subtitle'),
              child: _MaturityError(message: message, onRetry: _reload),
            );
          }
          final data = snapshot.data ?? const <String, dynamic>{};
          return PageFrame(
            title: scope.t('maturity.title'),
            subtitle: scope.t('maturity.subtitle'),
            child: _MaturityContent(data: data),
          );
        },
      ),
    );
  }
}

class _MaturityContent extends StatelessWidget {
  const _MaturityContent({required this.data});

  final JsonMap data;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final overview = _maturityMap(data['overview']);
    final scores = _maturityMap(data['scores']);
    final actions = _maturityMaps(data['next_actions']);
    final score = _maturityNum(data['maturity_score']).clamp(0, 100).round();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              _ScoreRing(value: score),
              const SizedBox(width: 18),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow(t('maturity.score')),
                    const SizedBox(height: 6),
                    Text(
                      '$score%',
                      style: theme.textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(t('maturity.subtitle')),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        LayoutBuilder(
          builder: (context, constraints) {
            final columns = constraints.maxWidth >= 660 ? 3 : 2;
            final width = (constraints.maxWidth - (columns - 1) * 10) / columns;
            final metrics = [
              (t('maturity.weeklyTrainings'), overview['weekly_trainings']),
              (t('maturity.connections'), overview['friend_connections']),
              (t('maturity.routes'), overview['completed_routes']),
              (t('maturity.posts'), overview['approved_posts']),
              (t('maturity.xp'), overview['xp_total']),
            ];
            return Wrap(
              spacing: 10,
              runSpacing: 10,
              children: metrics
                  .map(
                    (metric) => SizedBox(
                      width: width,
                      child: MetricCard(
                        value: '${_maturityNum(metric.$2).round()}',
                        label: metric.$1,
                      ),
                    ),
                  )
                  .toList(),
            );
          },
        ),
        const SizedBox(height: 14),
        AirmiusPanel(
          title: t('maturity.dimensions'),
          child: Column(
            children: [
              for (final entry in _maturityDimensions.entries) ...[
                _ScoreBar(
                  label: t(entry.value),
                  value: _maturityNum(scores[entry.key]).clamp(0, 100) / 100,
                ),
                if (entry.key != _maturityDimensions.keys.last)
                  const SizedBox(height: 13),
              ],
            ],
          ),
        ),
        const SizedBox(height: 14),
        AirmiusPanel(
          title: t('maturity.nextActions'),
          child: actions.isEmpty
              ? Row(
                  children: [
                    Icon(
                      Icons.check_circle_outline,
                      color: theme.colorScheme.secondary,
                    ),
                    const SizedBox(width: 10),
                    Expanded(child: Text(t('maturity.empty'))),
                  ],
                )
              : Column(
                  children: actions
                      .map(
                        (action) => _ChecklistRow(
                          label: _maturityText(
                            action['label'],
                            fallback: _maturityText(action['key']),
                          ),
                          done: action['done'] == true,
                        ),
                      )
                      .toList(),
                ),
        ),
      ],
    );
  }
}

const _maturityDimensions = <String, String>{
  'onboarding': 'maturity.dimension.onboarding',
  'social': 'maturity.dimension.social',
  'training': 'maturity.dimension.training',
  'maps': 'maturity.dimension.maps',
  'content': 'maturity.dimension.content',
  'safety': 'maturity.dimension.safety',
  'xp': 'maturity.dimension.xp',
};

class _ScoreRing extends StatelessWidget {
  const _ScoreRing({required this.value});

  final int value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SizedBox(
      width: 84,
      height: 84,
      child: Stack(
        alignment: Alignment.center,
        children: [
          CircularProgressIndicator(
            value: value / 100,
            strokeWidth: 8,
            backgroundColor: theme.colorScheme.primary.withValues(alpha: .14),
          ),
          Text('$value%', style: const TextStyle(fontWeight: FontWeight.w900)),
        ],
      ),
    );
  }
}

class _ScoreBar extends StatelessWidget {
  const _ScoreBar({required this.label, required this.value});

  final String label;
  final double value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(child: Text(label)),
            Text(
              '${(value * 100).round()}%',
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ],
        ),
        const SizedBox(height: 7),
        ClipRRect(
          borderRadius: BorderRadius.circular(99),
          child: LinearProgressIndicator(
            minHeight: 9,
            value: value,
            backgroundColor: theme.colorScheme.primary.withValues(alpha: .12),
          ),
        ),
      ],
    );
  }
}

class _ChecklistRow extends StatelessWidget {
  const _ChecklistRow({required this.label, required this.done});

  final String label;
  final bool done;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Icon(
            done ? Icons.check_circle : Icons.radio_button_unchecked,
            color: done
                ? theme.colorScheme.secondary
                : theme.colorScheme.outline,
          ),
          const SizedBox(width: 10),
          Expanded(child: Text(label)),
          const SizedBox(width: 8),
          Text(
            AirmiusScope.of(
              context,
            ).t(done ? 'maturity.complete' : 'maturity.open'),
            style: TextStyle(
              color: done
                  ? theme.colorScheme.secondary
                  : theme.colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _MaturityError extends StatelessWidget {
  const _MaturityError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        children: [
          const Icon(Icons.insights_outlined, size: 42),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('common.retry'),
            icon: Icons.refresh,
            onPressed: onRetry,
          ),
        ],
      ),
    );
  }
}

JsonMap _maturityMap(Object? value) => value is Map
    ? value.map((key, value) => MapEntry('$key', value))
    : <String, dynamic>{};

List<JsonMap> _maturityMaps(Object? value) => value is List
    ? value.map(_maturityMap).where((item) => item.isNotEmpty).toList()
    : const [];

num _maturityNum(Object? value) =>
    value is num ? value : num.tryParse('$value') ?? 0;

String _maturityText(Object? value, {String fallback = ''}) {
  final text = '$value'.trim();
  return text.isEmpty || text == 'null' ? fallback : text;
}
