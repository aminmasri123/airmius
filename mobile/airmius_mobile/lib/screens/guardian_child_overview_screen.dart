import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

class GuardianChildOverviewScreen extends StatefulWidget {
  const GuardianChildOverviewScreen({required this.childId, super.key});

  final int childId;

  @override
  State<GuardianChildOverviewScreen> createState() =>
      _GuardianChildOverviewScreenState();
}

class _GuardianChildOverviewScreenState
    extends State<GuardianChildOverviewScreen> {
  Future<AirmiusGuardianChildOverview>? _future;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<AirmiusGuardianChildOverview> _load() async {
    final response = await _client.guardianChildOverview(widget.childId);
    return AirmiusGuardianChildOverview.fromJson(response);
  }

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          scope.t('guardian.childOverviewTitle'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: scope.t('guardian.reload'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: FutureBuilder<AirmiusGuardianChildOverview>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError || snapshot.data == null) {
            return PageFrame(
              title: scope.t('guardian.childOverviewTitle'),
              subtitle: scope.t('guardian.childOverviewSubtitle'),
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
                      scope.t('guardian.loadError'),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(scope.t('guardian.loadErrorBody')),
                    const SizedBox(height: 14),
                    AirmiusButton(
                      label: scope.t('guardian.retry'),
                      icon: Icons.refresh_rounded,
                      onPressed: _reload,
                    ),
                  ],
                ),
              ),
            );
          }
          return _OverviewBody(overview: snapshot.data!);
        },
      ),
    );
  }
}

class _OverviewBody extends StatelessWidget {
  const _OverviewBody({required this.overview});

  final AirmiusGuardianChildOverview overview;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final child = overview.child;
    return PageFrame(
      title: child.name,
      subtitle: scope.t('guardian.childOverviewSubtitle'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _ChildHeader(child: child),
          const SizedBox(height: 12),
          if (!overview.approved)
            AirmiusPanel(
              borderColor: Theme.of(context).colorScheme.tertiary,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.pending_actions_outlined,
                    color: Theme.of(context).colorScheme.tertiary,
                    size: 28,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      scope.t('guardian.childOverviewConsentBody'),
                      style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                        height: 1.4,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            )
          else ...[
            _SafeScopePanel(),
            const SizedBox(height: 12),
            _TrainingSummary(summary: overview.training),
            const SizedBox(height: 12),
            _UpcomingEvents(events: overview.events),
          ],
          const SizedBox(height: 12),
          _PrivacyNotice(approved: overview.approved),
        ],
      ),
    );
  }
}

class _ChildHeader extends StatelessWidget {
  const _ChildHeader({required this.child});

  final AirmiusGuardianChild child;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final status = _overviewStatus(context, child.status);
    return AirmiusPanel(
      gradient: true,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            radius: 29,
            backgroundColor: status.color.withValues(alpha: 0.16),
            child: Icon(status.icon, color: status.color, size: 29),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  child.name,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  scope.t('guardian.age').replaceFirst('{age}', '${child.age}'),
                ),
                const SizedBox(height: 8),
                StatusPill(status.label, color: status.color),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SafeScopePanel extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final color = Theme.of(context).colorScheme.primary;
    return AirmiusPanel(
      borderColor: color.withValues(alpha: 0.45),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.shield_outlined, color: color, size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  scope.t('guardian.safeScopeTitle'),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  scope.t('guardian.safeScopeBody'),
                  style: Theme.of(
                    context,
                  ).textTheme.bodyMedium?.copyWith(height: 1.4),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TrainingSummary extends StatelessWidget {
  const _TrainingSummary({required this.summary});

  final AirmiusGuardianTrainingSummary summary;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final distance = (summary.distanceMeters / 1000).toStringAsFixed(
      summary.distanceMeters % 1000 == 0 ? 0 : 1,
    );
    return AirmiusPanel(
      title: scope.t('guardian.trainingSummary'),
      subtitle: scope
          .t('guardian.trainingPeriod')
          .replaceFirst('{days}', '${summary.periodDays}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          LayoutBuilder(
            builder: (context, constraints) {
              final width = constraints.maxWidth >= 560
                  ? (constraints.maxWidth - 18) / 4
                  : (constraints.maxWidth - 10) / 2;
              return Wrap(
                spacing: 6,
                runSpacing: 8,
                children: [
                  SizedBox(
                    width: width,
                    child: MetricCard(
                      value: '${summary.sessions}',
                      label: scope.t('guardian.trainingSessions'),
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: MetricCard(
                      value: '${summary.durationMinutes}',
                      label: scope.t('guardian.trainingMinutes'),
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: MetricCard(
                      value: '$distance km',
                      label: scope.t('guardian.trainingDistance'),
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: MetricCard(
                      value: '${summary.calories}',
                      label: scope.t('guardian.trainingCalories'),
                    ),
                  ),
                ],
              );
            },
          ),
          const SizedBox(height: 10),
          Text(
            summary.lastPerformedAt == null
                ? scope.t('guardian.noTraining')
                : '${scope.t('guardian.lastTraining')}: ${_guardianOverviewDate(context, summary.lastPerformedAt!)}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

class _UpcomingEvents extends StatelessWidget {
  const _UpcomingEvents({required this.events});

  final List<AirmiusGuardianEventPreview> events;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      title: scope.t('guardian.upcomingEvents'),
      child: events.isEmpty
          ? Text(scope.t('guardian.noUpcomingEvents'))
          : Column(
              children: [
                for (var index = 0; index < events.length; index++) ...[
                  _EventRow(event: events[index]),
                  if (index < events.length - 1)
                    const Divider(height: 20, thickness: 0.7),
                ],
              ],
            ),
    );
  }
}

class _EventRow extends StatelessWidget {
  const _EventRow({required this.event});

  final AirmiusGuardianEventPreview event;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final location = [
      event.locationName,
      event.locationCity,
    ].whereType<String>().where((value) => value.trim().isNotEmpty).join(', ');
    final date = event.startTime == null
        ? '–'
        : _guardianOverviewDate(context, event.startTime!);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(
          Icons.event_outlined,
          color: Theme.of(context).colorScheme.primary,
          size: 25,
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                event.title,
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 3),
              Text(date),
              if (event.teamName != null && event.teamName!.isNotEmpty)
                Text(event.teamName!),
              if (location.isNotEmpty) ...[
                const SizedBox(height: 3),
                Text(
                  '${scope.t('guardian.eventLocation')}: $location',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
              if (event.participationStatus != null)
                Text(
                  '${scope.t('guardian.participation')}: ${_participationLabel(scope, event.participationStatus!)}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _PrivacyNotice extends StatelessWidget {
  const _PrivacyNotice({required this.approved});

  final bool approved;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      borderColor: Theme.of(context).colorScheme.outlineVariant,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.visibility_off_outlined,
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              approved
                  ? scope.t('guardian.safeScopeBody')
                  : scope.t('guardian.childOverviewConsentBody'),
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(height: 1.4),
            ),
          ),
        ],
      ),
    );
  }
}

({Color color, IconData icon, String label}) _overviewStatus(
  BuildContext context,
  String value,
) {
  final scope = AirmiusScope.of(context);
  final colorScheme = Theme.of(context).colorScheme;
  return switch (value) {
    'approved' => (
      color: Colors.green.shade700,
      icon: Icons.verified_outlined,
      label: scope.t('guardian.statusApproved'),
    ),
    'rejected' => (
      color: colorScheme.error,
      icon: Icons.block_outlined,
      label: scope.t('guardian.statusRejected'),
    ),
    'revoked' => (
      color: colorScheme.error,
      icon: Icons.gpp_bad_outlined,
      label: scope.t('guardian.statusRevoked'),
    ),
    _ => (
      color: colorScheme.tertiary,
      icon: Icons.pending_actions_outlined,
      label: scope.t('guardian.statusPending'),
    ),
  };
}

String _participationLabel(AirmiusScope scope, String value) => switch (value) {
  'yes' || 'attending' || 'confirmed' => scope.t('guardian.participationYes'),
  'late' => scope.t('guardian.participationLate'),
  'maybe' => scope.t('guardian.participationMaybe'),
  'no' || 'declined' => scope.t('guardian.participationNo'),
  _ => scope.t('guardian.participationUnknown'),
};

String _guardianOverviewDate(BuildContext context, DateTime date) {
  final local = date.toLocal();
  final material = MaterialLocalizations.of(context);
  return '${material.formatMediumDate(local)} ${material.formatTimeOfDay(TimeOfDay.fromDateTime(local))}';
}
