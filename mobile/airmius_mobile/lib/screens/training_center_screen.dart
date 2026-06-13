import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'training_event_detail_screen.dart';

enum _EventPeriod { upcoming, past, all }

enum _EventViewMode { calendar, list }

class TrainingCenterScreen extends StatefulWidget {
  const TrainingCenterScreen({super.key});

  @override
  State<TrainingCenterScreen> createState() => _TrainingCenterScreenState();
}

class _TrainingCenterScreenState extends State<TrainingCenterScreen> {
  final TextEditingController _searchController = TextEditingController();
  Future<AirmiusPage<AirmiusEvent>>? _eventsFuture;
  _EventPeriod _period = _EventPeriod.upcoming;
  _EventViewMode _viewMode = _EventViewMode.calendar;
  DateTime _calendarCursor = DateTime(DateTime.now().year, DateTime.now().month);
  DateTime _selectedDate = _dateOnly(DateTime.now());
  String _search = '';
  String _type = '';
  String _visibility = '';
  bool _filtersOpen = false;
  int? _savingEventId;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _eventsFuture ??= _loadEvents();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<AirmiusPage<AirmiusEvent>> _loadEvents() {
    final now = DateTime.now();
    final from = switch (_period) {
      _EventPeriod.upcoming => DateTime(now.year, now.month, now.day),
      _EventPeriod.past || _EventPeriod.all => null,
    };
    final to = switch (_period) {
      _EventPeriod.past => DateTime(now.year, now.month, now.day),
      _EventPeriod.upcoming || _EventPeriod.all => null,
    };
    return AirmiusServicesScope.of(context).repositories.events.events(from: from, to: to);
  }

  void _reload() {
    setState(() => _eventsFuture = _loadEvents());
  }

  void _setPeriod(_EventPeriod period) {
    setState(() {
      _period = period;
      _eventsFuture = _loadEvents();
    });
  }

  void _resetFilters() {
    setState(() {
      _search = '';
      _type = '';
      _visibility = '';
      _searchController.clear();
    });
  }

  Future<void> _respond(AirmiusEvent event, String status) async {
    if (_savingEventId != null || !event.canJoin || event.status == 'cancelled') return;
    if (status == 'yes' && _isFull(event)) return;

    setState(() => _savingEventId = event.id);
    try {
      final repository = AirmiusServicesScope.of(context).repositories.events;
      final next = event.myParticipationStatus == status ? await repository.leave(event.id) : await repository.respond(event.id, status);
      final currentFuture = _eventsFuture;
      final page = currentFuture == null ? null : await currentFuture;
      if (!mounted) return;
      setState(() {
        _eventsFuture = Future.value(AirmiusPage<AirmiusEvent>(
          items: page?.items.map((item) => item.id == next.id ? next : item).toList() ?? [next],
          currentPage: page?.currentPage ?? 1,
          lastPage: page?.lastPage ?? 1,
        ));
        _savingEventId = null;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _savingEventId = null);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('events.saveError'))));
    }
  }

  void _showCreateUnavailable() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Event erstellen ist in der Mobile-API noch nicht freigeschaltet.')),
    );
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
        subtitle: '',
        showHeader: false,
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
              final events = snapshot.data?.items ?? const <AirmiusEvent>[];
              final visibleEvents = _filtered(events);
              final calendarEvents = _eventsInMonth(visibleEvents, _calendarCursor);
              final selectedEvents = visibleEvents.where((event) => _isSameDay(event.startsAt, _selectedDate)).toList();

              return SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _WebParityHeader(
                      events: events,
                      onCreate: _showCreateUnavailable,
                    ),
                    const SizedBox(height: 14),
                    if (snapshot.hasError) ...[
                      _ErrorEvents(onRetry: _reload),
                      const SizedBox(height: 14),
                    ],
                    _FilterBar(
                      controller: _searchController,
                      period: _period,
                      filtersOpen: _filtersOpen,
                      activeFilterCount: [_search, _type, _visibility].where((value) => value.isNotEmpty).length,
                      onSearchChanged: (value) => setState(() => _search = value),
                      onPeriodChanged: _setPeriod,
                      onToggleFilters: () => setState(() => _filtersOpen = !_filtersOpen),
                      onSubmit: _reload,
                    ),
                    if (_filtersOpen) ...[
                      const SizedBox(height: 10),
                      _AdvancedFilters(
                        type: _type,
                        visibility: _visibility,
                        onTypeChanged: (value) => setState(() => _type = value),
                        onVisibilityChanged: (value) => setState(() => _visibility = value),
                        onReset: _resetFilters,
                        onApply: _reload,
                      ),
                    ],
                    const SizedBox(height: 14),
                    if (snapshot.connectionState == ConnectionState.waiting && events.isEmpty)
                      const _LoadingEvents()
                    else if (!snapshot.hasError && visibleEvents.isEmpty)
                      _EmptyEvents(onReset: _resetFilters, onCreate: _showCreateUnavailable)
                    else
                      _EventsSurface(
                        viewMode: _viewMode,
                        events: visibleEvents,
                        calendarEvents: calendarEvents,
                        selectedEvents: selectedEvents,
                        calendarCursor: _calendarCursor,
                        selectedDate: _selectedDate,
                        savingEventId: _savingEventId,
                        onViewModeChanged: (value) => setState(() => _viewMode = value),
                        onCalendarMove: (delta) => setState(() => _calendarCursor = DateTime(_calendarCursor.year, _calendarCursor.month + delta)),
                        onToday: () => setState(() {
                          _calendarCursor = DateTime(DateTime.now().year, DateTime.now().month);
                          _selectedDate = _dateOnly(DateTime.now());
                        }),
                        onDateSelected: (value) => setState(() => _selectedDate = value),
                        onRespond: _respond,
                      ),
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
    final now = DateTime.now();
    final needle = _search.trim().toLowerCase();
    return events.where((event) {
      if (_period == _EventPeriod.upcoming && event.startsAt.isBefore(DateTime(now.year, now.month, now.day))) return false;
      if (_period == _EventPeriod.past && !event.startsAt.isBefore(DateTime(now.year, now.month, now.day))) return false;
      if (_type.isNotEmpty && event.type != _type) return false;
      if (_visibility.isNotEmpty && event.visibility != _visibility) return false;
      if (needle.isEmpty) return true;
      final haystack = '${event.title} ${event.location ?? ''} ${event.clubName ?? ''} ${event.teamName ?? ''}'.toLowerCase();
      return haystack.contains(needle);
    }).toList();
  }
}

class _WebParityHeader extends StatelessWidget {
  const _WebParityHeader({required this.events, required this.onCreate});

  final List<AirmiusEvent> events;
  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final now = DateTime.now();
    final upcoming = events.where((event) => event.startsAt.isAfter(now) && event.status != 'cancelled').length;
    final today = events.where((event) => _isToday(event.startsAt)).length;
    final cancelled = events.where((event) => event.status == 'cancelled').length;
    final nextEvents = events.where((event) => event.startsAt.isAfter(now)).toList()..sort((a, b) => a.startsAt.compareTo(b.startsAt));
    final nextEvent = nextEvents.isEmpty ? null : nextEvents.first;

    return AirmiusPanel(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  crossAxisAlignment: WrapCrossAlignment.start,
                  alignment: WrapAlignment.spaceBetween,
                  children: [
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 460),
                      child: const Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          IconBadge(icon: Icons.event_available_outlined, color: AirmiusColors.blue),
                          SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Eyebrow('Events & Training'),
                                SizedBox(height: 10),
                                _EventsTitle(),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    AirmiusButton(label: 'Erstellen', icon: Icons.add, onPressed: onCreate),
                  ],
                ),
                if (nextEvent != null) ...[
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      const StatusPill('Naechstes Event', color: AirmiusColors.green),
                      Text('${nextEvent.title} - ${_eventDateTimeLabel(nextEvent)}', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
                    ],
                  ),
                ],
              ],
            ),
          ),
          const Divider(height: 1, color: AirmiusColors.border),
          LayoutBuilder(
            builder: (context, constraints) {
              final narrow = constraints.maxWidth < 420;
              final cards = [
                _StatCell(label: 'Kommend', value: '$upcoming'),
                _StatCell(label: scope.t('events.today'), value: '$today'),
                _StatCell(label: 'Abgesagt', value: '$cancelled'),
              ];
              if (narrow) {
                return Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(children: [for (final card in cards) Expanded(child: Padding(padding: const EdgeInsets.symmetric(horizontal: 3), child: card))]),
                );
              }
              return Row(children: [for (final card in cards) Expanded(child: card)]);
            },
          ),
        ],
      ),
    );
  }
}

class _StatCell extends StatelessWidget {
  const _StatCell({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(border: Border(right: BorderSide(color: AirmiusColors.border.withValues(alpha: 0.7)))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label.toUpperCase(), style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w900)),
          const SizedBox(height: 6),
          Text(value, style: const TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900)),
        ],
      ),
    );
  }
}

class _EventsTitle extends StatelessWidget {
  const _EventsTitle();

  @override
  Widget build(BuildContext context) {
    return Text(
      AirmiusScope.of(context).t('training.title'),
      style: const TextStyle(color: AirmiusColors.text, fontSize: 28, height: 1.05, fontWeight: FontWeight.w900),
    );
  }
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({
    required this.controller,
    required this.period,
    required this.filtersOpen,
    required this.activeFilterCount,
    required this.onSearchChanged,
    required this.onPeriodChanged,
    required this.onToggleFilters,
    required this.onSubmit,
  });

  final TextEditingController controller;
  final _EventPeriod period;
  final bool filtersOpen;
  final int activeFilterCount;
  final ValueChanged<String> onSearchChanged;
  final ValueChanged<_EventPeriod> onPeriodChanged;
  final VoidCallback onToggleFilters;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        children: [
          TextField(
            controller: controller,
            onChanged: onSearchChanged,
            onSubmitted: (_) => onSubmit(),
            style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
            decoration: const InputDecoration(
              hintText: 'Suche nach Titel, Ort, Team oder Verein',
              prefixIcon: Icon(Icons.search, color: AirmiusColors.muted),
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _PeriodButton(label: 'Kommend', selected: period == _EventPeriod.upcoming, onTap: () => onPeriodChanged(_EventPeriod.upcoming)),
              _PeriodButton(label: 'Vergangen', selected: period == _EventPeriod.past, onTap: () => onPeriodChanged(_EventPeriod.past)),
              _PeriodButton(label: 'Alle', selected: period == _EventPeriod.all, onTap: () => onPeriodChanged(_EventPeriod.all)),
              OutlinedButton.icon(
                onPressed: onToggleFilters,
                icon: const Icon(Icons.tune, size: 18),
                label: Text(activeFilterCount == 0 ? 'Filter' : 'Filter $activeFilterCount'),
              ),
              FilledButton.icon(onPressed: onSubmit, icon: const Icon(Icons.search, size: 18), label: const Text('Suchen')),
            ],
          ),
        ],
      ),
    );
  }
}

class _PeriodButton extends StatelessWidget {
  const _PeriodButton({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      selected: selected,
      label: Text(label),
      onSelected: (_) => onTap(),
      selectedColor: AirmiusColors.blue,
      backgroundColor: AirmiusColors.cardSoft,
      side: BorderSide(color: selected ? AirmiusColors.blue : AirmiusColors.border),
      labelStyle: TextStyle(color: selected ? Colors.white : AirmiusColors.muted, fontWeight: FontWeight.w900),
    );
  }
}

class _AdvancedFilters extends StatelessWidget {
  const _AdvancedFilters({
    required this.type,
    required this.visibility,
    required this.onTypeChanged,
    required this.onVisibilityChanged,
    required this.onReset,
    required this.onApply,
  });

  final String type;
  final String visibility;
  final ValueChanged<String> onTypeChanged;
  final ValueChanged<String> onVisibilityChanged;
  final VoidCallback onReset;
  final VoidCallback onApply;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          DropdownButtonFormField<String>(
            value: type,
            decoration: const InputDecoration(labelText: 'Typ'),
            dropdownColor: AirmiusColors.card,
            items: const [
              DropdownMenuItem(value: '', child: Text('Alle Typen')),
              DropdownMenuItem(value: 'training', child: Text('Training')),
              DropdownMenuItem(value: 'match', child: Text('Spiel')),
              DropdownMenuItem(value: 'meeting', child: Text('Meeting')),
              DropdownMenuItem(value: 'public', child: Text('Oeffentlich')),
            ],
            onChanged: (value) => onTypeChanged(value ?? ''),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: visibility,
            decoration: const InputDecoration(labelText: 'Sichtbarkeit'),
            dropdownColor: AirmiusColors.card,
            items: const [
              DropdownMenuItem(value: '', child: Text('Alle')),
              DropdownMenuItem(value: 'private', child: Text('Privat')),
              DropdownMenuItem(value: 'organization', child: Text('Organisation')),
              DropdownMenuItem(value: 'public', child: Text('Oeffentlich')),
            ],
            onChanged: (value) => onVisibilityChanged(value ?? ''),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            alignment: WrapAlignment.end,
            children: [
              AirmiusButton(label: 'Zuruecksetzen', icon: Icons.restart_alt, onPressed: onReset, secondary: true),
              AirmiusButton(label: 'Filter anwenden', icon: Icons.check, onPressed: onApply),
            ],
          ),
        ],
      ),
    );
  }
}

class _EventsSurface extends StatelessWidget {
  const _EventsSurface({
    required this.viewMode,
    required this.events,
    required this.calendarEvents,
    required this.selectedEvents,
    required this.calendarCursor,
    required this.selectedDate,
    required this.savingEventId,
    required this.onViewModeChanged,
    required this.onCalendarMove,
    required this.onToday,
    required this.onDateSelected,
    required this.onRespond,
  });

  final _EventViewMode viewMode;
  final List<AirmiusEvent> events;
  final List<AirmiusEvent> calendarEvents;
  final List<AirmiusEvent> selectedEvents;
  final DateTime calendarCursor;
  final DateTime selectedDate;
  final int? savingEventId;
  final ValueChanged<_EventViewMode> onViewModeChanged;
  final ValueChanged<int> onCalendarMove;
  final VoidCallback onToday;
  final ValueChanged<DateTime> onDateSelected;
  final void Function(AirmiusEvent event, String status) onRespond;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Eyebrow('Ansicht'),
                      SizedBox(height: 4),
                      Text('Kalender & Liste', style: TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
                    ],
                  ),
                ),
                SegmentedButton<_EventViewMode>(
                  segments: const [
                    ButtonSegment(value: _EventViewMode.calendar, icon: Icon(Icons.calendar_month), label: Text('Kalender')),
                    ButtonSegment(value: _EventViewMode.list, icon: Icon(Icons.list), label: Text('Liste')),
                  ],
                  selected: {viewMode},
                  onSelectionChanged: (values) => onViewModeChanged(values.first),
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: AirmiusColors.border),
          if (viewMode == _EventViewMode.calendar)
            _CalendarView(
              events: calendarEvents,
              selectedEvents: selectedEvents,
              cursor: calendarCursor,
              selectedDate: selectedDate,
              onMove: onCalendarMove,
              onToday: onToday,
              onDateSelected: onDateSelected,
            )
          else
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                children: [
                  for (final event in events) ...[
                    _EventCard(event: event, saving: savingEventId == event.id, onRespond: onRespond),
                    const SizedBox(height: 12),
                  ],
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _CalendarView extends StatelessWidget {
  const _CalendarView({
    required this.events,
    required this.selectedEvents,
    required this.cursor,
    required this.selectedDate,
    required this.onMove,
    required this.onToday,
    required this.onDateSelected,
  });

  final List<AirmiusEvent> events;
  final List<AirmiusEvent> selectedEvents;
  final DateTime cursor;
  final DateTime selectedDate;
  final ValueChanged<int> onMove;
  final VoidCallback onToday;
  final ValueChanged<DateTime> onDateSelected;

  @override
  Widget build(BuildContext context) {
    final days = _calendarDays(cursor);
    final byDate = <DateTime, List<AirmiusEvent>>{};
    for (final event in events) {
      byDate.putIfAbsent(_dateOnly(event.startsAt), () => []).add(event);
    }

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              IconButton(onPressed: () => onMove(-1), icon: const Icon(Icons.chevron_left)),
              Expanded(child: Text(_monthLabel(cursor), textAlign: TextAlign.center, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
              TextButton(onPressed: onToday, child: const Text('Heute')),
              IconButton(onPressed: () => onMove(1), icon: const Icon(Icons.chevron_right)),
            ],
          ),
        ),
        GridView.count(
          crossAxisCount: 7,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          childAspectRatio: 0.82,
          children: [
            for (final label in const ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'])
              Center(child: Text(label, style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w900))),
            for (final day in days)
              _CalendarDay(
                date: day,
                currentMonth: day.month == cursor.month,
                selected: _isSameDay(day, selectedDate),
                today: _isToday(day),
                events: byDate[_dateOnly(day)] ?? const [],
                onTap: () => onDateSelected(_dateOnly(day)),
              ),
          ],
        ),
        const Divider(height: 1, color: AirmiusColors.border),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Eyebrow('Ausgewaehlter Tag'),
              const SizedBox(height: 4),
              Text(_dateLabel(selectedDate), style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
              const SizedBox(height: 12),
              if (selectedEvents.isEmpty)
                const AirmiusPanel(body: 'An diesem Tag sind keine Events im aktuellen Filter.')
              else
                for (final event in selectedEvents) ...[
                  _SelectedDayEvent(event: event),
                  const SizedBox(height: 10),
                ],
            ],
          ),
        ),
      ],
    );
  }
}

class _CalendarDay extends StatelessWidget {
  const _CalendarDay({
    required this.date,
    required this.currentMonth,
    required this.selected,
    required this.today,
    required this.events,
    required this.onTap,
  });

  final DateTime date;
  final bool currentMonth;
  final bool selected;
  final bool today;
  final List<AirmiusEvent> events;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(5),
        decoration: BoxDecoration(
          color: AirmiusColors.card,
          border: Border.all(color: selected ? AirmiusColors.blue : AirmiusColors.border.withValues(alpha: 0.45), width: selected ? 2 : 1),
        ),
        child: Opacity(
          opacity: currentMonth ? 1 : 0.45,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 24,
                height: 24,
                alignment: Alignment.center,
                decoration: BoxDecoration(color: today ? AirmiusColors.blue : Colors.transparent, shape: BoxShape.circle),
                child: Text('${date.day}', style: TextStyle(color: today ? Colors.white : AirmiusColors.text, fontSize: 11, fontWeight: FontWeight.w900)),
              ),
              const SizedBox(height: 4),
              for (final event in events.take(2))
                Container(
                  margin: const EdgeInsets.only(bottom: 3),
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                  decoration: BoxDecoration(color: (event.status == 'cancelled' ? AirmiusColors.red : AirmiusColors.blue).withValues(alpha: 0.14), borderRadius: BorderRadius.circular(5)),
                  child: Text('${_time(event.startsAt)} ${event.title}', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: event.status == 'cancelled' ? AirmiusColors.red : AirmiusColors.blue, fontSize: 9, fontWeight: FontWeight.w800)),
                ),
              if (events.length > 2) Text('+${events.length - 2} mehr', style: const TextStyle(color: AirmiusColors.muted, fontSize: 9, fontWeight: FontWeight.w800)),
            ],
          ),
        ),
      ),
    );
  }
}

class _SelectedDayEvent extends StatelessWidget {
  const _SelectedDayEvent({required this.event});

  final AirmiusEvent event;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      padding: const EdgeInsets.all(12),
      onTap: () => _openEvent(context, event),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(event.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(_eventDateTimeLabel(event), style: const TextStyle(color: AirmiusColors.muted, fontSize: 13)),
                const SizedBox(height: 4),
                Text(event.location ?? 'Keine Eingabe', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 13)),
              ],
            ),
          ),
          StatusPill(_capacityLabel(event), color: AirmiusColors.muted),
        ],
      ),
    );
  }
}

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event, required this.saving, required this.onRespond});

  final AirmiusEvent event;
  final bool saving;
  final void Function(AirmiusEvent event, String status) onRespond;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final owner = event.clubName ?? event.teamName ?? 'Oeffentlicher Bereich';
    return AirmiusPanel(
      onTap: () => _openEvent(context, event),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _DateTile(date: event.startsAt),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(child: Text(event.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                    StatusPill(_capacityLabel(event), color: AirmiusColors.muted),
                  ],
                ),
                const SizedBox(height: 5),
                Text(owner, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(_typeLabel(event.type), color: AirmiusColors.blue),
                    StatusPill(_visibilityLabel(event.visibility), color: AirmiusColors.muted),
                    if (event.commentsCount > 0) StatusPill('${event.commentsCount} ${scope.t('events.comments')}', color: AirmiusColors.amber),
                    if (event.status == 'cancelled') const StatusPill('Abgesagt', color: AirmiusColors.red),
                  ],
                ),
                const SizedBox(height: 12),
                Text(_eventDateTimeLabel(event), style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800)),
                const SizedBox(height: 5),
                Text(event.location ?? 'Keine Eingabe', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted)),
                const SizedBox(height: 12),
                if (saving)
                  const LinearProgressIndicator(color: AirmiusColors.blue, backgroundColor: AirmiusColors.cardSoft)
                else
                  Row(
                    children: [
                      Expanded(child: _RsvpButton(label: scope.t('events.yes'), value: 'yes', event: event, onRespond: onRespond)),
                      const SizedBox(width: 8),
                      Expanded(child: _RsvpButton(label: scope.t('events.maybe'), value: 'maybe', event: event, onRespond: onRespond)),
                      const SizedBox(width: 8),
                      Expanded(child: _RsvpButton(label: scope.t('events.no'), value: 'no', event: event, onRespond: onRespond)),
                    ],
                  ),
                const SizedBox(height: 10),
                AirmiusButton(label: 'Details', icon: Icons.visibility_outlined, onPressed: () => _openEvent(context, event)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DateTile extends StatelessWidget {
  const _DateTile({required this.date});

  final DateTime date;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 58,
      padding: const EdgeInsets.symmetric(vertical: 9),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, border: Border.all(color: AirmiusColors.border), borderRadius: BorderRadius.circular(10)),
      child: Column(
        children: [
          Text(_weekdayShort(date), style: const TextStyle(color: AirmiusColors.muted, fontSize: 10, fontWeight: FontWeight.w900)),
          Text('${date.day}', style: const TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900)),
          Text(_monthShort(date), style: const TextStyle(color: AirmiusColors.muted, fontSize: 10, fontWeight: FontWeight.w900)),
        ],
      ),
    );
  }
}

class _RsvpButton extends StatelessWidget {
  const _RsvpButton({required this.label, required this.value, required this.event, required this.onRespond});

  final String label;
  final String value;
  final AirmiusEvent event;
  final void Function(AirmiusEvent event, String status) onRespond;

  @override
  Widget build(BuildContext context) {
    final selected = event.myParticipationStatus == value;
    final disabled = !event.canJoin || event.status == 'cancelled' || (value == 'yes' && _isFull(event));
    final color = switch (value) {
      'yes' => AirmiusColors.green,
      'maybe' => AirmiusColors.blue,
      _ => AirmiusColors.red,
    };
    return OutlinedButton(
      onPressed: disabled ? null : () => onRespond(event, value),
      style: OutlinedButton.styleFrom(
        foregroundColor: selected ? color : AirmiusColors.muted,
        backgroundColor: selected ? color.withValues(alpha: 0.14) : Colors.transparent,
        side: BorderSide(color: selected ? color : AirmiusColors.border),
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 10),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
      child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900)),
    );
  }
}

class _LoadingEvents extends StatelessWidget {
  const _LoadingEvents();

  @override
  Widget build(BuildContext context) {
    return const AirmiusPanel(
      child: Padding(
        padding: EdgeInsets.symmetric(vertical: 20),
        child: Center(child: CircularProgressIndicator(strokeWidth: 2, color: AirmiusColors.blue)),
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

class _EmptyEvents extends StatelessWidget {
  const _EmptyEvents({required this.onReset, required this.onCreate});

  final VoidCallback onReset;
  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: AirmiusColors.border,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(Icons.event_busy_outlined, color: AirmiusColors.muted, size: 40),
          const SizedBox(height: 12),
          const Text('Keine Events gefunden', textAlign: TextAlign.center, style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 6),
          const Text('Es gibt aktuell keine passenden Events. Passe die Filter an oder erstelle ein neues Event.', textAlign: TextAlign.center, style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            alignment: WrapAlignment.center,
            children: [
              AirmiusButton(label: 'Filter zuruecksetzen', icon: Icons.restart_alt, onPressed: onReset, secondary: true),
              AirmiusButton(label: 'Event erstellen', icon: Icons.add, onPressed: onCreate),
            ],
          ),
        ],
      ),
    );
  }
}

void _openEvent(BuildContext context, AirmiusEvent event) {
  final fallbackBody = [
    _eventDateTimeLabel(event),
    event.location,
    event.clubName,
    event.teamName,
  ].whereType<String>().where((value) => value.isNotEmpty).join(' - ');
  Navigator.push(
    context,
    MaterialPageRoute(
      builder: (_) => TrainingEventDetailScreen(
        event: event,
        fallbackBody: event.notes?.isNotEmpty == true ? event.notes! : fallbackBody,
      ),
    ),
  );
}

List<AirmiusEvent> _eventsInMonth(List<AirmiusEvent> events, DateTime month) {
  return events.where((event) => event.startsAt.year == month.year && event.startsAt.month == month.month).toList();
}

List<DateTime> _calendarDays(DateTime cursor) {
  final first = DateTime(cursor.year, cursor.month);
  final mondayOffset = first.weekday - DateTime.monday;
  final start = first.subtract(Duration(days: mondayOffset));
  return List.generate(42, (index) => _dateOnly(start.add(Duration(days: index))));
}

bool _isToday(DateTime value) => _isSameDay(value, DateTime.now());

bool _isSameDay(DateTime a, DateTime b) => a.year == b.year && a.month == b.month && a.day == b.day;

DateTime _dateOnly(DateTime value) => DateTime(value.year, value.month, value.day);

bool _isFull(AirmiusEvent event) => event.maxParticipants != null && event.yesCount >= event.maxParticipants! && event.myParticipationStatus != 'yes';

String _capacityLabel(AirmiusEvent event) => event.maxParticipants == null ? '${event.participantsCount}' : '${event.participantsCount}/${event.maxParticipants}';

String _eventDateTimeLabel(AirmiusEvent event) {
  final end = event.endsAt;
  final startLabel = '${_dateLabel(event.startsAt)} ${_time(event.startsAt)}';
  if (end == null) return startLabel;
  if (_isSameDay(event.startsAt, end)) return '$startLabel - ${_time(end)}';
  return '$startLabel - ${_dateLabel(end)} ${_time(end)}';
}

String _dateLabel(DateTime value) {
  final local = value.toLocal();
  return '${local.day.toString().padLeft(2, '0')}.${local.month.toString().padLeft(2, '0')}.${local.year}';
}

String _time(DateTime value) {
  final local = value.toLocal();
  return '${local.hour.toString().padLeft(2, '0')}:${local.minute.toString().padLeft(2, '0')}';
}

String _monthLabel(DateTime value) {
  const months = ['Januar', 'Februar', 'Maerz', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
  return '${months[value.month - 1]} ${value.year}';
}

String _weekdayShort(DateTime value) {
  const days = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
  return days[value.weekday - 1];
}

String _monthShort(DateTime value) {
  const months = ['Jan', 'Feb', 'Mrz', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
  return months[value.month - 1];
}

String _typeLabel(String value) => switch (value) {
      'training' => 'Training',
      'match' => 'Spiel',
      'meeting' => 'Meeting',
      'public' => 'Oeffentlich',
      _ => value,
    };

String _visibilityLabel(String value) => switch (value) {
      'private' => 'Privat',
      'organization' => 'Organisation',
      'public' => 'Oeffentlich',
      _ => value,
    };
