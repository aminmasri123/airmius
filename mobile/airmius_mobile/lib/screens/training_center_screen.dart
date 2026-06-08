import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'training_event_detail_screen.dart';

class TrainingCenterScreen extends StatefulWidget {
  const TrainingCenterScreen({super.key});

  @override
  State<TrainingCenterScreen> createState() => _TrainingCenterScreenState();
}

class _TrainingCenterScreenState extends State<TrainingCenterScreen> {
  String _filter = 'all';
  late Future<AirmiusPage<AirmiusEvent>> _eventsFuture;

  @override
  void initState() {
    super.initState();
    _eventsFuture = _loadEvents();
  }

  Future<AirmiusPage<AirmiusEvent>> _loadEvents() {
    return AirmiusServicesScope.of(context).repositories.events.events();
  }

  void _reload() {
    setState(() => _eventsFuture = _loadEvents());
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(scope.t('training.title'), style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: scope.t('training.title'),
        subtitle: scope.t('training.subtitle'),
        showHeader: true,
        child: RefreshIndicator(
          color: AirmiusColors.blue,
          backgroundColor: AirmiusColors.card,
          onRefresh: () async {
            _reload();
            await _eventsFuture;
          },
          child: FutureBuilder<AirmiusPage<AirmiusEvent>>(
            future: _eventsFuture,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const _ScrollableEventBody(child: _LoadingEvents());
              }
              if (snapshot.hasError) {
                return _ScrollableEventBody(child: _ErrorEvents(onRetry: _reload));
              }

              final events = snapshot.data?.items ?? const <AirmiusEvent>[];
              final visibleEvents = _filtered(events);
              return _ScrollableEventBody(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _FilterPanel(
                      value: _filter,
                      eventsCount: events.length,
                      todayCount: events.where((event) => _isToday(event.startsAt)).length,
                      upcomingCount: events.where((event) => event.startsAt.isAfter(DateTime.now())).length,
                      onChanged: (value) => setState(() => _filter = value),
                    ),
                    const SizedBox(height: 14),
                    if (visibleEvents.isEmpty)
                      EmptyPanel(scope.t('events.empty'))
                    else
                      for (final event in visibleEvents) ...[
                        _EventCard(event: event),
                        const SizedBox(height: 12),
                      ],
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  List<AirmiusEvent> _filtered(List<AirmiusEvent> events) {
    if (_filter == 'today') return events.where((event) => _isToday(event.startsAt)).toList();
    if (_filter == 'upcoming') return events.where((event) => event.startsAt.isAfter(DateTime.now())).toList();
    return events;
  }
}

class _ScrollableEventBody extends StatelessWidget {
  const _ScrollableEventBody({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: child,
    );
  }
}

class _FilterPanel extends StatelessWidget {
  const _FilterPanel({
    required this.value,
    required this.eventsCount,
    required this.todayCount,
    required this.upcomingCount,
    required this.onChanged,
  });

  final String value;
  final int eventsCount;
  final int todayCount;
  final int upcomingCount;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final filters = {
      'all': scope.t('events.all'),
      'today': scope.t('events.today'),
      'upcoming': scope.t('events.upcoming'),
    };

    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SectionLabel(scope.t('training.title')),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: MetricCard(value: '$eventsCount', label: scope.t('training.title'))),
              const SizedBox(width: 10),
              Expanded(child: MetricCard(value: '$todayCount', label: scope.t('events.today'))),
              const SizedBox(width: 10),
              Expanded(child: MetricCard(value: '$upcomingCount', label: scope.t('events.upcoming'))),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final entry in filters.entries)
                ChoiceChip(
                  selected: value == entry.key,
                  label: Text(entry.value),
                  onSelected: (_) => onChanged(entry.key),
                  selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: value == entry.key ? AirmiusColors.blue : AirmiusColors.border),
                  labelStyle: TextStyle(color: value == entry.key ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event});

  final AirmiusEvent event;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final body = [
      _timeLabel(event.startsAt),
      event.location ?? scope.t('events.locationMissing'),
      event.clubName,
      event.teamName,
    ].whereType<String>().where((value) => value.isNotEmpty).join(' - ');

    return AirmiusPanel(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => TrainingEventDetailScreen(
            event: event,
            fallbackBody: event.notes?.isNotEmpty == true ? event.notes! : body,
          ),
        ),
      ),
      borderColor: event.startsAt.isAfter(DateTime.now()) ? AirmiusColors.green.withValues(alpha: 0.45) : AirmiusColors.border,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const IconBadge(icon: Icons.event_available_outlined, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(event.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                    StatusPill(event.status, color: event.startsAt.isAfter(DateTime.now()) ? AirmiusColors.green : AirmiusColors.blue),
                  ],
                ),
                const SizedBox(height: 6),
                Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700)),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill('${event.participantsCount} ${scope.t('events.participants')}', color: AirmiusColors.blue),
                    StatusPill('${event.commentsCount} ${scope.t('events.comments')}', color: AirmiusColors.amber),
                    StatusPill(event.visibility, color: AirmiusColors.green),
                  ],
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ],
      ),
    );
  }
}

class _LoadingEvents extends StatelessWidget {
  const _LoadingEvents();

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 20),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: AirmiusColors.blue)),
            const SizedBox(width: 12),
            Text(scope.t('status.loading'), style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }
}

class _ErrorEvents extends StatelessWidget {
  const _ErrorEvents({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(Icons.error_outline, color: AirmiusColors.red, size: 34),
          const SizedBox(height: 10),
          Text(scope.t('events.error'), textAlign: TextAlign.center, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          AirmiusButton(label: scope.t('events.retry'), icon: Icons.refresh_outlined, onPressed: onRetry, secondary: true),
        ],
      ),
    );
  }
}

bool _isToday(DateTime value) {
  final now = DateTime.now();
  return value.year == now.year && value.month == now.month && value.day == now.day;
}

String _timeLabel(DateTime value) {
  if (value.millisecondsSinceEpoch == 0) return '';
  final hour = value.hour.toString().padLeft(2, '0');
  final minute = value.minute.toString().padLeft(2, '0');
  final day = value.day.toString().padLeft(2, '0');
  final month = value.month.toString().padLeft(2, '0');
  return '$day.$month. $hour:$minute';
}
