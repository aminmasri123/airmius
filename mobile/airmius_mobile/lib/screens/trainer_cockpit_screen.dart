import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'event_management_screen.dart';
import 'training_plans_logs_screen.dart';

class TrainerCockpitScreen extends StatefulWidget {
  const TrainerCockpitScreen({super.key});

  @override
  State<TrainerCockpitScreen> createState() => _TrainerCockpitScreenState();
}

class _TrainerCockpitScreenState extends State<TrainerCockpitScreen> {
  Future<Map<String, dynamic>>? _future;
  String _tab = 'overview';
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<Map<String, dynamic>> _load() async =>
      _coachMap((await _client.trainerCockpit())['data']);

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('coach.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('common.refresh'),
            onPressed: _busy ? null : _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _CoachFailure(error: snapshot.error, onRetry: _reload);
          }
          final data = snapshot.data ?? const <String, dynamic>{};
          return PageFrame(
            title: t('coach.title'),
            subtitle: t('coach.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _hero(data),
                const SizedBox(height: 14),
                _tabs(),
                const SizedBox(height: 14),
                if (_tab == 'teams')
                  _teams(data)
                else if (_tab == 'feedback')
                  _feedback(data)
                else if (_tab == 'planning')
                  _planning(data)
                else
                  _overview(data),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _hero(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final summary = _coachMap(data['summary']);
    final weekly = _coachMap(data['coachWeekly']);
    final score = _coachInt(summary['readiness_score']);
    final risk = _coachText(weekly['risk_level'], fallback: 'empty');
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              DecoratedBox(
                decoration: BoxDecoration(
                  color: theme.colorScheme.primary.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(13),
                  child: Icon(
                    Icons.sports_score_outlined,
                    color: theme.colorScheme.primary,
                    size: 32,
                  ),
                ),
              ),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow(t('coach.eyebrow')),
                    const SizedBox(height: 6),
                    Text(
                      t('coach.headline'),
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(t('coach.heroBody')),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: Semantics(
                  label: '${t('coach.readiness')} $score%',
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(20),
                    child: LinearProgressIndicator(
                      value: (score / 100).clamp(0, 1),
                      minHeight: 12,
                      color: _riskColor(context, risk),
                      backgroundColor:
                          theme.colorScheme.surfaceContainerHighest,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Text(
                '$score%',
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(width: 9),
              StatusPill(
                t('coach.readiness.$risk'),
                color: _riskColor(context, risk),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _CoachMetric(
                icon: Icons.groups_2_outlined,
                value: '${_coachInt(summary['teams'])}',
                label: t('coach.teams'),
              ),
              _CoachMetric(
                icon: Icons.directions_run_outlined,
                value: '${_coachInt(summary['athletes'])}',
                label: t('coach.athletes'),
              ),
              _CoachMetric(
                icon: Icons.rate_review_outlined,
                value: '${_coachInt(summary['feedback_open'])}',
                label: t('coach.openFeedback'),
              ),
              _CoachMetric(
                icon: Icons.warning_amber_outlined,
                value: '${_coachInt(summary['risk_athletes'])}',
                label: t('coach.risks'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _tabs() {
    final t = AirmiusScope.of(context).t;
    final tabs = <String, String>{
      'overview': t('coach.overview'),
      'teams': t('coach.teams'),
      'feedback': t('coach.feedback'),
      'planning': t('coach.planning'),
    };
    return Semantics(
      label: t('coach.navigation'),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: tabs.entries
            .map(
              (entry) => ChoiceChip(
                selected: _tab == entry.key,
                label: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 7),
                  child: Text(entry.value),
                ),
                onSelected: (_) => setState(() => _tab = entry.key),
              ),
            )
            .toList(),
      ),
    );
  }

  Widget _overview(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final weekly = _coachMap(data['coachWeekly']);
    final current = _coachMap(weekly['current_week']);
    final trend = _coachMap(weekly['trend']);
    final risks = _coachMaps(weekly['risk_athletes']);
    final actions = _coachMaps(weekly['actions']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          icon: Icons.monitor_heart_outlined,
          title: t('coach.weeklyControl'),
          body: t('coach.weeklyControlBody'),
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            _CoachMetric(
              icon: Icons.fitness_center_outlined,
              value: '${_coachInt(current['session_count'])}',
              label: t('coach.sessions'),
            ),
            _CoachMetric(
              icon: Icons.timer_outlined,
              value: '${_coachInt(current['duration_minutes'])}',
              label: t('coach.minutes'),
            ),
            _CoachMetric(
              icon: Icons.trending_up_outlined,
              value: '${_coachNumber(trend['sessions_percent'])}%',
              label: t('coach.trend'),
            ),
            _CoachMetric(
              icon: Icons.speed_outlined,
              value: _coachText(current['average_rpe'], fallback: '–'),
              label: t('coach.averageRpe'),
            ),
          ],
        ),
        const SizedBox(height: 16),
        _quickActions(),
        const SizedBox(height: 16),
        _sectionHeader(
          icon: Icons.health_and_safety_outlined,
          title: t('coach.riskAthletes'),
          body: t('coach.riskBody'),
        ),
        const SizedBox(height: 10),
        if (risks.isEmpty)
          _CoachEmpty(
            icon: Icons.verified_user_outlined,
            title: t('coach.noRisks'),
            body: t('coach.noRisksBody'),
          )
        else
          ...risks.map(
            (risk) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _RiskCard(risk: risk),
            ),
          ),
        if (actions.isNotEmpty) ...[
          const SizedBox(height: 6),
          _sectionHeader(
            icon: Icons.task_alt_outlined,
            title: t('coach.nextActions'),
            body: t('coach.nextActionsBody'),
          ),
          const SizedBox(height: 10),
          AirmiusPanel(
            child: Column(
              children: actions
                  .map(
                    (action) => _ActionLine(
                      action: action,
                      onTap: () => _openAction(_coachText(action['key'])),
                    ),
                  )
                  .toList(),
            ),
          ),
        ],
      ],
    );
  }

  Widget _quickActions() {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('coach.quickActions')),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: t('coach.openPlans'),
                icon: Icons.assignment_outlined,
                onPressed: _openTraining,
              ),
              AirmiusButton(
                label: t('coach.openEvents'),
                icon: Icons.event_available_outlined,
                secondary: true,
                onPressed: _openEvents,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _teams(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final teams = _coachMaps(data['teams']);
    if (teams.isEmpty) {
      return _CoachEmpty(
        icon: Icons.groups_2_outlined,
        title: t('coach.noTeams'),
        body: t('coach.noTeamsBody'),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          icon: Icons.groups_2_outlined,
          title: t('coach.myTeams'),
          body: t('coach.myTeamsBody'),
        ),
        const SizedBox(height: 10),
        ...teams.map(
          (team) => Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: _TeamCard(team: team),
          ),
        ),
      ],
    );
  }

  Widget _feedback(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final logs = _coachMaps(data['feedbackOpen']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          icon: Icons.rate_review_outlined,
          title: t('coach.openFeedback'),
          body: t('coach.feedbackBody'),
        ),
        const SizedBox(height: 10),
        if (logs.isEmpty)
          _CoachEmpty(
            icon: Icons.mark_chat_read_outlined,
            title: t('coach.noFeedback'),
            body: t('coach.noFeedbackBody'),
          )
        else
          ...logs.map(
            (log) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _FeedbackCard(
                log: log,
                busy: _busy,
                onFeedback: () => _showFeedback(log),
              ),
            ),
          ),
      ],
    );
  }

  Widget _planning(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final overdue = _coachMaps(data['overdueItems']);
    final planned = _coachMaps(data['plannedItems']);
    final events = _coachMaps(data['upcomingEvents']);
    final plans = _coachMaps(data['plans']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          icon: Icons.warning_amber_outlined,
          title: t('coach.overdue'),
          body: t('coach.overdueBody'),
        ),
        const SizedBox(height: 10),
        if (overdue.isEmpty)
          _CoachEmpty(
            icon: Icons.event_available_outlined,
            title: t('coach.nothingOverdue'),
            body: t('coach.nothingOverdueBody'),
          )
        else
          ...overdue.map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: _ScheduleCard(item: item, overdue: true),
            ),
          ),
        const SizedBox(height: 8),
        _sectionHeader(
          icon: Icons.calendar_month_outlined,
          title: t('coach.nextTrainings'),
          body: t('coach.nextTrainingsBody'),
        ),
        const SizedBox(height: 10),
        if (planned.isEmpty)
          _CoachEmpty(
            icon: Icons.edit_calendar_outlined,
            title: t('coach.noPlanned'),
            body: t('coach.noPlannedBody'),
            action: AirmiusButton(
              label: t('coach.openPlans'),
              icon: Icons.assignment_outlined,
              onPressed: _openTraining,
            ),
          )
        else
          ...planned.map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: _ScheduleCard(item: item),
            ),
          ),
        if (events.isNotEmpty || plans.isNotEmpty) ...[
          const SizedBox(height: 8),
          _sectionHeader(
            icon: Icons.dashboard_customize_outlined,
            title: t('coach.morePlanning'),
            body: t('coach.morePlanningBody'),
          ),
          const SizedBox(height: 10),
          AirmiusPanel(
            child: Column(
              children: [
                if (events.isNotEmpty)
                  _PlanningSummary(
                    icon: Icons.event_outlined,
                    title: t('coach.upcomingEvents'),
                    count: events.length,
                    onTap: _openEvents,
                  ),
                if (plans.isNotEmpty)
                  _PlanningSummary(
                    icon: Icons.assignment_outlined,
                    title: t('coach.trainingPlans'),
                    count: plans.length,
                    onTap: _openTraining,
                  ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  Widget _sectionHeader({
    required IconData icon,
    required String title,
    required String body,
  }) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: theme.colorScheme.primary, size: 26),
        const SizedBox(width: 11),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 3),
              Text(body),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _showFeedback(Map<String, dynamic> log) async {
    final t = AirmiusScope.of(context).t;
    final body = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => const _FeedbackSheet(),
    );
    if (body == null || !mounted) return;
    setState(() => _busy = true);
    try {
      await _client.sendTrainerFeedback(_coachInt(log['id']), body);
      if (!mounted) return;
      _toast(t('coach.feedbackSent'));
      _reload();
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
    } catch (error) {
      if (mounted) {
        _toast(
          error is AirmiusApiException
              ? error.userMessage
              : AirmiusScope.of(context).t('common.errorDetails'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _openAction(String key) {
    if (key == 'answerFeedback') {
      setState(() => _tab = 'feedback');
    } else if (key == 'rescheduleOverdue') {
      setState(() => _tab = 'planning');
    } else if (key == 'planFirstSession') {
      _openTraining();
    }
  }

  Future<void> _openTraining() async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const TrainingPlansLogsScreen()),
    );
    if (mounted) _reload();
  }

  Future<void> _openEvents() async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const EventManagementScreen()),
    );
    if (mounted) _reload();
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _CoachMetric extends StatelessWidget {
  const _CoachMetric({
    required this.icon,
    required this.value,
    required this.label,
  });

  final IconData icon;
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      constraints: const BoxConstraints(minWidth: 135, maxWidth: 280),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.7),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: theme.dividerColor),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.max,
        children: [
          Icon(icon, color: theme.colorScheme.primary, size: 23),
          const SizedBox(width: 10),
          Flexible(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  value,
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(label, style: theme.textTheme.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RiskCard extends StatelessWidget {
  const _RiskCard({required this.risk});

  final Map<String, dynamic> risk;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final team = _coachMap(risk['team']);
    return AirmiusPanel(
      borderColor: theme.colorScheme.error.withValues(alpha: 0.45),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AirmiusAvatar(_coachText(risk['name'], fallback: t('coach.athlete'))),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _coachText(risk['name'], fallback: t('coach.athlete')),
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(_coachText(team['name'], fallback: t('coach.noTeam'))),
                const SizedBox(height: 9),
                Wrap(
                  spacing: 7,
                  runSpacing: 7,
                  children: [
                    StatusPill(
                      '${t('coach.rpe')} ${_coachNumber(risk['rpe'])}',
                      color: theme.colorScheme.error,
                    ),
                    StatusPill(
                      '${t('coach.pain')} ${_coachNumber(risk['pain'])}',
                      color: theme.colorScheme.error,
                    ),
                    StatusPill(
                      '${t('coach.highLoad')} ${_coachInt(risk['high_load_sessions'])}',
                      color: theme.colorScheme.tertiary,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionLine extends StatelessWidget {
  const _ActionLine({required this.action, required this.onTap});

  final Map<String, dynamic> action;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final key = _coachText(action['key']);
    final count = _coachInt(action['count']);
    final tone = _coachText(action['tone']);
    final color = tone == 'danger'
        ? theme.colorScheme.error
        : tone == 'success'
        ? theme.colorScheme.secondary
        : theme.colorScheme.tertiary;
    return Material(
      type: MaterialType.transparency,
      child: ListTile(
        contentPadding: EdgeInsets.zero,
        minTileHeight: 58,
        leading: Icon(Icons.task_alt_outlined, color: color),
        title: Text(
          t('coach.action.$key'),
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
        trailing: count > 0 ? StatusPill('$count', color: color) : null,
        onTap: onTap,
      ),
    );
  }
}

class _TeamCard extends StatelessWidget {
  const _TeamCard({required this.team});

  final Map<String, dynamic> team;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final stats = _coachMap(team['stats']);
    final athletes = _coachMaps(team['athletes']);
    final club = _coachMap(team['club']);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.groups_2_outlined,
                color: theme.colorScheme.primary,
                size: 30,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _coachText(team['name']),
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      [
                        _coachText(club['name']),
                        _coachText(team['sport_type']),
                      ].where((value) => value.isNotEmpty).join(' · '),
                    ),
                  ],
                ),
              ),
              StatusPill(
                '${_coachInt(stats['athletes'])} ${t('coach.athletes')}',
              ),
            ],
          ),
          const SizedBox(height: 13),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: athletes
                .map(
                  (athlete) => Chip(
                    avatar: CircleAvatar(
                      child: Text(_coachInitial(_coachText(athlete['name']))),
                    ),
                    label: Text(_coachText(athlete['name'])),
                  ),
                )
                .toList(),
          ),
          if (athletes.isEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 3),
              child: Text(t('coach.noAthletes')),
            ),
        ],
      ),
    );
  }
}

class _FeedbackCard extends StatelessWidget {
  const _FeedbackCard({
    required this.log,
    required this.busy,
    required this.onFeedback,
  });

  final Map<String, dynamic> log;
  final bool busy;
  final VoidCallback onFeedback;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final athlete = _coachMap(log['athlete']);
    final team = _coachMap(log['team']);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AirmiusAvatar(
                _coachText(athlete['name'], fallback: t('coach.athlete')),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _coachText(log['title']),
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      [
                        _coachText(athlete['name']),
                        _coachText(team['name']),
                      ].where((value) => value.isNotEmpty).join(' · '),
                    ),
                    const SizedBox(height: 7),
                    Text(_coachDate(context, log['performed_at'])),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 13),
          FilledButton.icon(
            onPressed: busy ? null : onFeedback,
            icon: const Icon(Icons.rate_review_outlined),
            label: Text(t('coach.giveFeedback')),
          ),
        ],
      ),
    );
  }
}

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({required this.item, this.overdue = false});

  final Map<String, dynamic> item;
  final bool overdue;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final plan = _coachMap(item['plan']);
    final team = _coachMap(plan['team']);
    final color = overdue ? theme.colorScheme.error : theme.colorScheme.primary;
    return AirmiusPanel(
      borderColor: color.withValues(alpha: 0.4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            overdue
                ? Icons.event_busy_outlined
                : Icons.event_available_outlined,
            color: color,
            size: 28,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _coachText(item['title']),
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  [
                    _coachText(plan['title']),
                    _coachText(team['name']),
                  ].where((value) => value.isNotEmpty).join(' · '),
                ),
                const SizedBox(height: 7),
                Wrap(
                  spacing: 7,
                  runSpacing: 7,
                  children: [
                    StatusPill(
                      _coachDate(context, item['scheduled_at']),
                      color: color,
                    ),
                    if (_coachInt(item['duration_minutes']) > 0)
                      StatusPill(
                        '${_coachInt(item['duration_minutes'])} ${t('coach.minutesShort')}',
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PlanningSummary extends StatelessWidget {
  const _PlanningSummary({
    required this.icon,
    required this.title,
    required this.count,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final int count;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    type: MaterialType.transparency,
    child: ListTile(
      minTileHeight: 62,
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon, color: Theme.of(context).colorScheme.primary),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          StatusPill('$count'),
          const SizedBox(width: 5),
          const Icon(Icons.chevron_right),
        ],
      ),
      onTap: onTap,
    ),
  );
}

class _FeedbackSheet extends StatefulWidget {
  const _FeedbackSheet();

  @override
  State<_FeedbackSheet> createState() => _FeedbackSheetState();
}

class _FeedbackSheetState extends State<_FeedbackSheet> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        18,
        20,
        24 + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 46,
                height: 5,
                decoration: BoxDecoration(
                  color: theme.dividerColor,
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
            ),
            const SizedBox(height: 18),
            Text(
              t('coach.giveFeedback'),
              style: theme.textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 7),
            Text(t('coach.feedbackHelp')),
            const SizedBox(height: 16),
            TextField(
              controller: _controller,
              minLines: 5,
              maxLines: 10,
              autofocus: true,
              textCapitalization: TextCapitalization.sentences,
              decoration: InputDecoration(
                labelText: t('coach.feedbackText'),
                alignLabelWithHint: true,
              ),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: _controller.text.trim().length >= 2
                  ? () => Navigator.pop(context, _controller.text.trim())
                  : null,
              icon: const Icon(Icons.send_outlined),
              label: Text(t('coach.sendFeedback')),
            ),
            const SizedBox(height: 8),
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(t('common.cancel')),
            ),
          ],
        ),
      ),
    );
  }
}

class _CoachEmpty extends StatelessWidget {
  const _CoachEmpty({
    required this.icon,
    required this.title,
    required this.body,
    this.action,
  });

  final IconData icon;
  final String title;
  final String body;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AirmiusPanel(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 16),
        child: Column(
          children: [
            Icon(icon, color: theme.colorScheme.primary, size: 46),
            const SizedBox(height: 11),
            Text(
              title,
              textAlign: TextAlign.center,
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 6),
            Text(body, textAlign: TextAlign.center),
            if (action != null) ...[const SizedBox(height: 14), action!],
          ],
        ),
      ),
    );
  }
}

class _CoachFailure extends StatelessWidget {
  const _CoachFailure({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final message = error is AirmiusApiException
        ? (error as AirmiusApiException).userMessage
        : t('coach.loadFailed');
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: _CoachEmpty(
          icon: Icons.lock_person_outlined,
          title: t('coach.loadFailed'),
          body: message,
          action: AirmiusButton(
            label: t('common.retry'),
            icon: Icons.refresh_outlined,
            onPressed: onRetry,
          ),
        ),
      ),
    );
  }
}

Color _riskColor(BuildContext context, String level) {
  final colors = Theme.of(context).colorScheme;
  return switch (level) {
    'good' => colors.secondary,
    'risk' => colors.error,
    'watch' => colors.tertiary,
    _ => colors.outline,
  };
}

Map<String, dynamic> _coachMap(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _coachMaps(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <Map<String, dynamic>>[];

String _coachText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _coachInt(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

String _coachNumber(Object? value) {
  if (value is num) {
    return value == value.roundToDouble()
        ? value.toInt().toString()
        : value.toStringAsFixed(1);
  }
  return _coachText(value, fallback: '0');
}

String _coachInitial(String value) =>
    value.trim().isEmpty ? '?' : value.trim().substring(0, 1).toUpperCase();

String _coachDate(BuildContext context, Object? value) {
  final raw = _coachText(value);
  final date = DateTime.tryParse(raw)?.toLocal();
  if (date == null) return '–';
  final locale = Localizations.localeOf(context).toLanguageTag();
  return DateFormat.yMMMd(locale).add_Hm().format(date);
}
