import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
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
  Future<AirmiusEventWorkspace>? _workspaceFuture;
  _EventPeriod _period = _EventPeriod.upcoming;
  _EventViewMode _viewMode = _EventViewMode.calendar;
  DateTime _calendarCursor = DateTime(
    DateTime.now().year,
    DateTime.now().month,
  );
  DateTime _selectedDate = _dateOnly(DateTime.now());
  String _search = '';
  String _type = '';
  String _visibility = '';
  int? _clubId;
  int? _teamId;
  bool _filtersOpen = false;
  int? _savingEventId;
  bool _creatingEvent = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _workspaceFuture ??= _loadWorkspace();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<AirmiusEventWorkspace> _loadWorkspace() {
    return AirmiusServicesScope.of(context).repositories.events.workspace(
      search: _search,
      type: _type,
      visibility: _visibility,
      clubId: _clubId,
      teamId: _teamId,
      period: _period.name,
      calendarMonth: _calendarMonthValue(_calendarCursor),
    );
  }

  void _reload() {
    setState(() {
      _workspaceFuture = _loadWorkspace();
    });
  }

  void _setPeriod(_EventPeriod period) {
    setState(() {
      _period = period;
      _workspaceFuture = _loadWorkspace();
    });
  }

  void _resetFilters() {
    setState(() {
      _search = '';
      _type = '';
      _visibility = '';
      _clubId = null;
      _teamId = null;
      _searchController.clear();
    });
  }

  Future<void> _respond(AirmiusEvent event, String status) async {
    if (_savingEventId != null || !event.canJoin || event.status == 'cancelled') {
      return;
    }
    if (status == 'yes' && _isFull(event)) return;

    setState(() => _savingEventId = event.id);
    try {
      final repository = AirmiusServicesScope.of(context).repositories.events;
      final next = event.myParticipationStatus == status
          ? await repository.leave(event.id)
          : await repository.respond(event.id, status);
      final currentFuture = _workspaceFuture;
      final workspace = currentFuture == null ? null : await currentFuture;
      if (!mounted) return;
      setState(() {
        _workspaceFuture = Future.value(
          (workspace ?? _emptyWorkspace()).copyWith(
            events: _replaceEvent(workspace?.events ?? const [], next),
            calendarEvents: _replaceEvent(
              workspace?.calendarEvents ?? const [],
              next,
            ),
          ),
        );
        _savingEventId = null;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _savingEventId = null);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AirmiusScope.of(context).t('events.saveError'))),
      );
    }
  }

  Future<void> _openCreateEventDialog() async {
    final currentFuture = _workspaceFuture;
    final workspace = currentFuture == null ? null : await currentFuture;
    if (!mounted) return;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (context) => _CreateEventDialog(
        clubs: workspace?.clubs ?? const [],
        teams: workspace?.teams ?? const [],
        eventTypes:
            workspace?.eventTypes ??
            const ['training', 'match', 'meeting', 'public'],
        visibilities:
            workspace?.visibilities ??
            const ['private', 'organization', 'public'],
      ),
    );
    if (payload == null || _creatingEvent) return;
    if (!mounted) return;

    setState(() => _creatingEvent = true);
    try {
      final event = await AirmiusServicesScope.of(
        context,
      ).repositories.events.create(payload);
      final latestFuture = _workspaceFuture;
      final latestWorkspace = latestFuture == null
          ? workspace
          : await latestFuture;
      if (!mounted) return;
      setState(() {
        final items = [...?latestWorkspace?.events];
        items.removeWhere((item) => item.id == event.id);
        items.insert(0, event);
        items.sort((a, b) => a.startsAt.compareTo(b.startsAt));
        final calendarItems = [...?latestWorkspace?.calendarEvents];
        calendarItems.removeWhere((item) => item.id == event.id);
        calendarItems.add(event);
        calendarItems.sort((a, b) => a.startsAt.compareTo(b.startsAt));
        _workspaceFuture = Future.value(
          (latestWorkspace ?? _emptyWorkspace()).copyWith(
            events: items,
            calendarEvents: calendarItems,
            nextEvent: event,
          ),
        );
        _calendarCursor = DateTime(event.startsAt.year, event.startsAt.month);
        _selectedDate = _dateOnly(event.startsAt);
        _creatingEvent = false;
      });
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Event erstellt.')));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _creatingEvent = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } catch (error) {
      if (!mounted) return;
      setState(() => _creatingEvent = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Event konnte nicht erstellt werden: $error')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(
          scope.t('training.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
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
            await _workspaceFuture;
          },
          child: FutureBuilder<AirmiusEventWorkspace>(
            future: _workspaceFuture,
            builder: (context, snapshot) {
              final workspace = snapshot.data ?? _emptyWorkspace();
              final events = workspace.events;
              final visibleEvents = _filtered(events);
              final calendarEvents = workspace.calendarEvents;
              final selectedEvents = calendarEvents
                  .where((event) => _isSameDay(event.startsAt, _selectedDate))
                  .toList();

              return SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _WebParityHeader(
                      workspace: workspace,
                      creating: _creatingEvent,
                      onCreate: _openCreateEventDialog,
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
                      activeFilterCount: [
                        _search,
                        _type,
                        _visibility,
                        if (_clubId != null) 'club',
                        if (_teamId != null) 'team',
                      ].where((value) => value.isNotEmpty).length,
                      onSearchChanged: (value) =>
                          setState(() => _search = value),
                      onPeriodChanged: _setPeriod,
                      onToggleFilters: () =>
                          setState(() => _filtersOpen = !_filtersOpen),
                      onSubmit: _reload,
                    ),
                    if (_filtersOpen) ...[
                      const SizedBox(height: 10),
                      _AdvancedFilters(
                        type: _type,
                        visibility: _visibility,
                        clubId: _clubId,
                        teamId: _teamId,
                        eventTypes: workspace.eventTypes,
                        visibilities: workspace.visibilities,
                        clubs: workspace.clubs,
                        teams: workspace.teams,
                        onTypeChanged: (value) => setState(() => _type = value),
                        onVisibilityChanged: (value) =>
                            setState(() => _visibility = value),
                        onClubChanged: (value) => setState(() {
                          _clubId = value;
                          if (value != null &&
                              _teamId != null &&
                              !workspace.teams.any(
                                (team) =>
                                    team.id == _teamId && team.clubId == value,
                              )) {
                            _teamId = null;
                          }
                        }),
                        onTeamChanged: (value) =>
                            setState(() => _teamId = value),
                        onReset: _resetFilters,
                        onApply: _reload,
                      ),
                    ],
                    const SizedBox(height: 14),
                    if (snapshot.connectionState == ConnectionState.waiting &&
                        events.isEmpty)
                      const _LoadingEvents()
                    else if (!snapshot.hasError && visibleEvents.isEmpty)
                      _EmptyEvents(
                        onReset: _resetFilters,
                        onCreate: _openCreateEventDialog,
                      )
                    else
                      _EventsSurface(
                        viewMode: _viewMode,
                        events: visibleEvents,
                        calendarEvents: calendarEvents,
                        selectedEvents: selectedEvents,
                        calendarCursor: _calendarCursor,
                        selectedDate: _selectedDate,
                        savingEventId: _savingEventId,
                        onViewModeChanged: (value) =>
                            setState(() => _viewMode = value),
                        onCalendarMove: (delta) => setState(() {
                          _calendarCursor = DateTime(
                            _calendarCursor.year,
                            _calendarCursor.month + delta,
                          );
                          _workspaceFuture = _loadWorkspace();
                        }),
                        onToday: () => setState(() {
                          _calendarCursor = DateTime(
                            DateTime.now().year,
                            DateTime.now().month,
                          );
                          _selectedDate = _dateOnly(DateTime.now());
                          _workspaceFuture = _loadWorkspace();
                        }),
                        onDateSelected: (value) =>
                            setState(() => _selectedDate = value),
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
      if (_period == _EventPeriod.upcoming &&
          event.startsAt.isBefore(DateTime(now.year, now.month, now.day))) {
        return false;
      }
      if (_period == _EventPeriod.past &&
          !event.startsAt.isBefore(DateTime(now.year, now.month, now.day))) {
        return false;
      }
      if (_type.isNotEmpty && event.type != _type) return false;
      if (_visibility.isNotEmpty && event.visibility != _visibility) {
        return false;
      }
      if (needle.isEmpty) return true;
      final haystack =
          '${event.title} ${event.location ?? ''} ${event.clubName ?? ''} ${event.teamName ?? ''}'
              .toLowerCase();
      return haystack.contains(needle);
    }).toList();
  }
}

class _WebParityHeader extends StatelessWidget {
  const _WebParityHeader({
    required this.workspace,
    required this.creating,
    required this.onCreate,
  });

  final AirmiusEventWorkspace workspace;
  final bool creating;
  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final nextEvent = workspace.nextEvent;

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
                          IconBadge(
                            icon: Icons.event_available_outlined,
                            color: AirmiusColors.blue,
                          ),
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
                    AirmiusButton(
                      label: creating ? 'Speichern...' : 'Erstellen',
                      icon: Icons.add,
                      onPressed: creating ? null : onCreate,
                    ),
                  ],
                ),
                if (nextEvent != null) ...[
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      const StatusPill(
                        'Naechstes Event',
                        color: AirmiusColors.green,
                      ),
                      Text(
                        '${nextEvent.title} - ${_eventDateTimeLabel(nextEvent)}',
                        style: const TextStyle(
                          color: AirmiusColors.muted,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
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
                _StatCell(
                  label: 'Kommend',
                  value: '${workspace.stats.upcoming}',
                ),
                _StatCell(
                  label: scope.t('events.today'),
                  value: '${workspace.stats.today}',
                ),
                _StatCell(
                  label: 'Abgesagt',
                  value: '${workspace.stats.cancelled}',
                ),
              ];
              if (narrow) {
                return Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      for (final card in cards)
                        Expanded(
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 3),
                            child: card,
                          ),
                        ),
                    ],
                  ),
                );
              }
              return Row(
                children: [for (final card in cards) Expanded(child: card)],
              );
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
      decoration: BoxDecoration(
        border: Border(
          right: BorderSide(color: AirmiusColors.border.withValues(alpha: 0.7)),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: const TextStyle(
              color: AirmiusColors.muted,
              fontSize: 11,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: const TextStyle(
              color: AirmiusColors.text,
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
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
      style: const TextStyle(
        color: AirmiusColors.text,
        fontSize: 28,
        height: 1.05,
        fontWeight: FontWeight.w900,
      ),
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
            style: const TextStyle(
              color: AirmiusColors.text,
              fontWeight: FontWeight.w800,
            ),
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
              _PeriodButton(
                label: 'Kommend',
                selected: period == _EventPeriod.upcoming,
                onTap: () => onPeriodChanged(_EventPeriod.upcoming),
              ),
              _PeriodButton(
                label: 'Vergangen',
                selected: period == _EventPeriod.past,
                onTap: () => onPeriodChanged(_EventPeriod.past),
              ),
              _PeriodButton(
                label: 'Alle',
                selected: period == _EventPeriod.all,
                onTap: () => onPeriodChanged(_EventPeriod.all),
              ),
              OutlinedButton.icon(
                onPressed: onToggleFilters,
                icon: const Icon(Icons.tune, size: 18),
                label: Text(
                  activeFilterCount == 0
                      ? 'Filter'
                      : 'Filter $activeFilterCount',
                ),
              ),
              FilledButton.icon(
                onPressed: onSubmit,
                icon: const Icon(Icons.search, size: 18),
                label: const Text('Suchen'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PeriodButton extends StatelessWidget {
  const _PeriodButton({
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
      selected: selected,
      label: Text(label),
      onSelected: (_) => onTap(),
      selectedColor: AirmiusColors.blue,
      backgroundColor: AirmiusColors.cardSoft,
      side: BorderSide(
        color: selected ? AirmiusColors.blue : AirmiusColors.border,
      ),
      labelStyle: TextStyle(
        color: selected ? Colors.white : AirmiusColors.muted,
        fontWeight: FontWeight.w900,
      ),
    );
  }
}

class _AdvancedFilters extends StatelessWidget {
  const _AdvancedFilters({
    required this.type,
    required this.visibility,
    required this.clubId,
    required this.teamId,
    required this.eventTypes,
    required this.visibilities,
    required this.clubs,
    required this.teams,
    required this.onTypeChanged,
    required this.onVisibilityChanged,
    required this.onClubChanged,
    required this.onTeamChanged,
    required this.onReset,
    required this.onApply,
  });

  final String type;
  final String visibility;
  final int? clubId;
  final int? teamId;
  final List<String> eventTypes;
  final List<String> visibilities;
  final List<AirmiusClub> clubs;
  final List<AirmiusTeam> teams;
  final ValueChanged<String> onTypeChanged;
  final ValueChanged<String> onVisibilityChanged;
  final ValueChanged<int?> onClubChanged;
  final ValueChanged<int?> onTeamChanged;
  final VoidCallback onReset;
  final VoidCallback onApply;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          DropdownButtonFormField<String>(
            initialValue: type,
            decoration: const InputDecoration(labelText: 'Typ'),
            dropdownColor: AirmiusColors.card,
            items: [
              const DropdownMenuItem(value: '', child: Text('Alle Typen')),
              for (final item in eventTypes)
                DropdownMenuItem(value: item, child: Text(_typeLabel(item))),
            ],
            onChanged: (value) => onTypeChanged(value ?? ''),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: visibility,
            decoration: const InputDecoration(labelText: 'Sichtbarkeit'),
            dropdownColor: AirmiusColors.card,
            items: [
              const DropdownMenuItem(value: '', child: Text('Alle')),
              for (final item in visibilities)
                DropdownMenuItem(
                  value: item,
                  child: Text(_visibilityLabel(item)),
                ),
            ],
            onChanged: (value) => onVisibilityChanged(value ?? ''),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<int?>(
            initialValue: clubId,
            decoration: const InputDecoration(labelText: 'Verein'),
            dropdownColor: AirmiusColors.card,
            items: [
              const DropdownMenuItem<int?>(
                value: null,
                child: Text('Alle Vereine'),
              ),
              for (final club in clubs)
                DropdownMenuItem<int?>(value: club.id, child: Text(club.name)),
            ],
            onChanged: onClubChanged,
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<int?>(
            initialValue: teamId,
            decoration: const InputDecoration(labelText: 'Team'),
            dropdownColor: AirmiusColors.card,
            items: [
              const DropdownMenuItem<int?>(
                value: null,
                child: Text('Alle Teams'),
              ),
              for (final team in teams.where(
                (team) => clubId == null || team.clubId == clubId,
              ))
                DropdownMenuItem<int?>(value: team.id, child: Text(team.name)),
            ],
            onChanged: onTeamChanged,
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            alignment: WrapAlignment.end,
            children: [
              AirmiusButton(
                label: 'Zurücksetzen',
                icon: Icons.restart_alt,
                onPressed: onReset,
                secondary: true,
              ),
              AirmiusButton(
                label: 'Filter anwenden',
                icon: Icons.check,
                onPressed: onApply,
              ),
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
                      Text(
                        'Kalender & Liste',
                        style: TextStyle(
                          color: AirmiusColors.text,
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
                ),
                SegmentedButton<_EventViewMode>(
                  segments: const [
                    ButtonSegment(
                      value: _EventViewMode.calendar,
                      icon: Icon(Icons.calendar_month),
                      label: Text('Kalender'),
                    ),
                    ButtonSegment(
                      value: _EventViewMode.list,
                      icon: Icon(Icons.list),
                      label: Text('Liste'),
                    ),
                  ],
                  selected: {viewMode},
                  onSelectionChanged: (values) =>
                      onViewModeChanged(values.first),
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
                    _EventCard(
                      event: event,
                      saving: savingEventId == event.id,
                      onRespond: onRespond,
                    ),
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
              IconButton(
                onPressed: () => onMove(-1),
                icon: const Icon(Icons.chevron_left),
              ),
              Expanded(
                child: Text(
                  _monthLabel(cursor),
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: AirmiusColors.text,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              TextButton(onPressed: onToday, child: const Text('Heute')),
              IconButton(
                onPressed: () => onMove(1),
                icon: const Icon(Icons.chevron_right),
              ),
            ],
          ),
        ),
        GridView.count(
          crossAxisCount: 7,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          childAspectRatio: 0.82,
          children: [
            for (final label in const [
              'Mo',
              'Di',
              'Mi',
              'Do',
              'Fr',
              'Sa',
              'So',
            ])
              Center(
                child: Text(
                  label,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
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
              const Eyebrow('Ausgewählter Tag'),
              const SizedBox(height: 4),
              Text(
                _dateLabel(selectedDate),
                style: const TextStyle(
                  color: AirmiusColors.text,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
              if (selectedEvents.isEmpty)
                const AirmiusPanel(
                  body: 'An diesem Tag sind keine Events im aktuellen Filter.',
                )
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
          border: Border.all(
            color: selected
                ? AirmiusColors.blue
                : AirmiusColors.border.withValues(alpha: 0.45),
            width: selected ? 2 : 1,
          ),
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
                decoration: BoxDecoration(
                  color: today ? AirmiusColors.blue : Colors.transparent,
                  shape: BoxShape.circle,
                ),
                child: Text(
                  '${date.day}',
                  style: TextStyle(
                    color: today ? Colors.white : AirmiusColors.text,
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(height: 4),
              for (final event in events.take(2))
                Container(
                  margin: const EdgeInsets.only(bottom: 3),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 4,
                    vertical: 2,
                  ),
                  decoration: BoxDecoration(
                    color:
                        (event.status == 'cancelled'
                                ? AirmiusColors.red
                                : AirmiusColors.blue)
                            .withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(5),
                  ),
                  child: Text(
                    '${_time(event.startsAt)} ${event.title}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: event.status == 'cancelled'
                          ? AirmiusColors.red
                          : AirmiusColors.blue,
                      fontSize: 9,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              if (events.length > 2)
                Text(
                  '+${events.length - 2} mehr',
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                  ),
                ),
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
                Text(
                  event.title,
                  style: const TextStyle(
                    color: AirmiusColors.text,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _eventDateTimeLabel(event),
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  event.location ?? 'Keine Eingabe',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    fontSize: 13,
                  ),
                ),
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
  const _EventCard({
    required this.event,
    required this.saving,
    required this.onRespond,
  });

  final AirmiusEvent event;
  final bool saving;
  final void Function(AirmiusEvent event, String status) onRespond;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final owner = event.clubName ?? event.teamName ?? 'Öffentlicher Bereich';
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
                    Expanded(
                      child: Text(
                        event.title,
                        style: const TextStyle(
                          color: AirmiusColors.text,
                          fontSize: 17,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    StatusPill(
                      _capacityLabel(event),
                      color: AirmiusColors.muted,
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                Text(
                  owner,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(
                      _typeLabel(event.type),
                      color: AirmiusColors.blue,
                    ),
                    StatusPill(
                      _visibilityLabel(event.visibility),
                      color: AirmiusColors.muted,
                    ),
                    if (event.commentsCount > 0)
                      StatusPill(
                        '${event.commentsCount} ${scope.t('events.comments')}',
                        color: AirmiusColors.amber,
                      ),
                    if (event.status == 'cancelled')
                      const StatusPill('Abgesagt', color: AirmiusColors.red),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  _eventDateTimeLabel(event),
                  style: const TextStyle(
                    color: AirmiusColors.text,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  event.location ?? 'Keine Eingabe',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: AirmiusColors.muted),
                ),
                const SizedBox(height: 12),
                if (saving)
                  const LinearProgressIndicator(
                    color: AirmiusColors.blue,
                    backgroundColor: AirmiusColors.cardSoft,
                  )
                else
                  Row(
                    children: [
                      Expanded(
                        child: _RsvpButton(
                          label: scope.t('events.yes'),
                          value: 'yes',
                          event: event,
                          onRespond: onRespond,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _RsvpButton(
                          label: scope.t('events.maybe'),
                          value: 'maybe',
                          event: event,
                          onRespond: onRespond,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _RsvpButton(
                          label: scope.t('events.no'),
                          value: 'no',
                          event: event,
                          onRespond: onRespond,
                        ),
                      ),
                    ],
                  ),
                const SizedBox(height: 10),
                AirmiusButton(
                  label: 'Details',
                  icon: Icons.visibility_outlined,
                  onPressed: () => _openEvent(context, event),
                ),
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
      decoration: BoxDecoration(
        color: AirmiusColors.cardSoft,
        border: Border.all(color: AirmiusColors.border),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        children: [
          Text(
            _weekdayShort(date),
            style: const TextStyle(
              color: AirmiusColors.muted,
              fontSize: 10,
              fontWeight: FontWeight.w900,
            ),
          ),
          Text(
            '${date.day}',
            style: const TextStyle(
              color: AirmiusColors.text,
              fontSize: 22,
              fontWeight: FontWeight.w900,
            ),
          ),
          Text(
            _monthShort(date),
            style: const TextStyle(
              color: AirmiusColors.muted,
              fontSize: 10,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _RsvpButton extends StatelessWidget {
  const _RsvpButton({
    required this.label,
    required this.value,
    required this.event,
    required this.onRespond,
  });

  final String label;
  final String value;
  final AirmiusEvent event;
  final void Function(AirmiusEvent event, String status) onRespond;

  @override
  Widget build(BuildContext context) {
    final selected = event.myParticipationStatus == value;
    final disabled =
        !event.canJoin ||
        event.status == 'cancelled' ||
        (value == 'yes' && _isFull(event));
    final color = switch (value) {
      'yes' => AirmiusColors.green,
      'maybe' => AirmiusColors.blue,
      _ => AirmiusColors.red,
    };
    return OutlinedButton(
      onPressed: disabled ? null : () => onRespond(event, value),
      style: OutlinedButton.styleFrom(
        foregroundColor: selected ? color : AirmiusColors.muted,
        backgroundColor: selected
            ? color.withValues(alpha: 0.14)
            : Colors.transparent,
        side: BorderSide(color: selected ? color : AirmiusColors.border),
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 10),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
      ),
    );
  }
}

class _CreateEventDialog extends StatefulWidget {
  const _CreateEventDialog({
    required this.clubs,
    required this.teams,
    required this.eventTypes,
    required this.visibilities,
  });

  final List<AirmiusClub> clubs;
  final List<AirmiusTeam> teams;
  final List<String> eventTypes;
  final List<String> visibilities;

  @override
  State<_CreateEventDialog> createState() => _CreateEventDialogState();
}

class _CreateEventDialogState extends State<_CreateEventDialog> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _locationController = TextEditingController();
  final _notesController = TextEditingController();
  final _maxParticipantsController = TextEditingController();
  int _step = 1;
  String _type = 'training';
  String _visibility = 'public';
  int? _clubId;
  int? _teamId;
  bool _usesPenaltyCatalog = false;
  DateTime _start = DateTime.now().add(const Duration(hours: 1));
  DateTime? _end;

  List<String> get _eventTypeOptions {
    final options = widget.eventTypes
        .where((value) => value.isNotEmpty)
        .toList();
    return options.isEmpty
        ? const ['training', 'match', 'meeting', 'public']
        : options;
  }

  List<String> get _visibilityOptions {
    final options = widget.visibilities
        .where((value) => value.isNotEmpty)
        .toList();
    if (options.isEmpty) return const ['private', 'organization', 'public'];
    const preferredOrder = ['private', 'organization', 'public'];
    options.sort(
      (a, b) => preferredOrder.indexOf(a).compareTo(preferredOrder.indexOf(b)),
    );
    return options;
  }

  @override
  void dispose() {
    _titleController.dispose();
    _locationController.dispose();
    _notesController.dispose();
    _maxParticipantsController.dispose();
    super.dispose();
  }

  Future<void> _pickStart() async {
    final next = await _pickDateTime(_start);
    if (next == null) return;
    setState(() {
      _start = next;
      if (_end != null && _end!.isBefore(_start)) _end = null;
    });
  }

  Future<void> _pickEnd() async {
    final next = await _pickDateTime(
      _end ?? _start.add(const Duration(hours: 1)),
    );
    if (next == null) return;
    setState(() => _end = next.isBefore(_start) ? _start : next);
  }

  Future<DateTime?> _pickDateTime(DateTime initial) async {
    final date = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 365 * 3)),
    );
    if (date == null || !mounted) return null;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(initial),
    );
    if (time == null) return null;
    return DateTime(date.year, date.month, date.day, time.hour, time.minute);
  }

  String? get _validationMessage {
    if (_step == 1) {
      if (_titleController.text.trim().isEmpty) {
        return 'Bitte gib einen Titel ein.';
      }
      if (_visibility == 'organization' && _clubId == null) {
        return 'Bitte wähle einen Verein aus.';
      }
      if (_visibility == 'private' && _teamId == null) {
        return 'Bitte wähle ein Team aus.';
      }
    }
    if (_step == 2) {
      if (_end != null && _end!.isBefore(_start)) {
        return 'Das Ende darf nicht vor dem Start liegen.';
      }
    }
    return null;
  }

  void _nextStep() {
    final message = _validationMessage;
    if (message != null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
      return;
    }
    setState(() {
      if (_step < 4) _step += 1;
    });
  }

  void _previousStep() {
    setState(() {
      if (_step > 1) _step -= 1;
    });
  }

  void _submit() {
    if (!_formKey.currentState!.validate()) return;
    final message = _validationMessage;
    if (message != null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
      return;
    }
    final maxParticipants = int.tryParse(
      _maxParticipantsController.text.trim(),
    );
    final payload = <String, dynamic>{
      'title': _titleController.text.trim(),
      'type': _type,
      'visibility': _visibility,
      'start_time': _start.toUtc().toIso8601String(),
      'event_timezone': 'UTC',
      if (_visibility == 'organization' && _clubId != null) 'club_id': _clubId,
      if (_visibility == 'private' && _teamId != null) 'team_id': _teamId,
      if (_visibility == 'private' && _teamId != null)
        'uses_penalty_catalog': _usesPenaltyCatalog,
      if (_end != null) 'end_time': _end!.toUtc().toIso8601String(),
      if (_locationController.text.trim().isNotEmpty)
        'location': _locationController.text.trim(),
      if (_notesController.text.trim().isNotEmpty)
        'notes': _notesController.text.trim(),
      'max_participants': ?maxParticipants,
    };
    Navigator.pop(context, payload);
  }

  Widget _stepButton(int number, String label) {
    final active = _step == number;
    final complete = _step > number;
    return Expanded(
      child: FilledButton(
        onPressed: complete ? () => setState(() => _step = number) : null,
        style: FilledButton.styleFrom(
          backgroundColor: active
              ? Colors.white
              : complete
              ? AirmiusColors.green.withValues(alpha: 0.18)
              : AirmiusColors.cardSoft,
          foregroundColor: active
              ? AirmiusColors.card
              : complete
              ? AirmiusColors.green
              : AirmiusColors.muted,
          disabledBackgroundColor: active
              ? Colors.white
              : AirmiusColors.cardSoft,
          disabledForegroundColor: active
              ? AirmiusColors.card
              : AirmiusColors.muted,
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(99),
          ),
        ),
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
        ),
      ),
    );
  }

  Widget _stepBody() {
    return switch (_step) {
      1 => _basisStep(),
      2 => _timeStep(),
      3 => _detailsStep(),
      _ => _reviewStep(),
    };
  }

  Widget _basisStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Basisdaten',
          style: TextStyle(
            color: AirmiusColors.text,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Was für ein Event moechtest du erstellen?',
          style: TextStyle(
            color: AirmiusColors.muted,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _titleController,
          autofocus: true,
          onChanged: (_) => setState(() {}),
          decoration: const InputDecoration(
            labelText: 'Titel',
            hintText: 'z. B. U17 Training',
          ),
          validator: (value) => value == null || value.trim().isEmpty
              ? 'Bitte Titel eingeben.'
              : null,
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _type,
          decoration: const InputDecoration(labelText: 'Typ'),
          dropdownColor: AirmiusColors.card,
          items: [
            for (final item in _eventTypeOptions)
              DropdownMenuItem(value: item, child: Text(_typeLabel(item))),
          ],
          onChanged: (value) => setState(() => _type = value ?? 'training'),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _visibility,
          decoration: const InputDecoration(labelText: 'Sichtbarkeit'),
          dropdownColor: AirmiusColors.card,
          items: [
            for (final item in _visibilityOptions)
              DropdownMenuItem(
                value: item,
                child: Text(_visibilityLabel(item)),
              ),
          ],
          onChanged: (value) => setState(() {
            _visibility = value ?? 'public';
            if (_visibility == 'public') {
              _clubId = null;
              _teamId = null;
              _usesPenaltyCatalog = false;
            }
            if (_visibility == 'organization') {
              _teamId = null;
              _usesPenaltyCatalog = false;
            }
            if (_visibility == 'private') _clubId = null;
          }),
        ),
        if (_visibility == 'organization') ...[
          const SizedBox(height: 12),
          DropdownButtonFormField<int>(
            initialValue: _clubId,
            decoration: const InputDecoration(labelText: 'Verein'),
            dropdownColor: AirmiusColors.card,
            items: [
              for (final club in widget.clubs)
                DropdownMenuItem(value: club.id, child: Text(club.name)),
            ],
            onChanged: (value) => setState(() => _clubId = value),
          ),
        ],
        if (_visibility == 'private') ...[
          const SizedBox(height: 12),
          DropdownButtonFormField<int>(
            initialValue: _teamId,
            decoration: const InputDecoration(labelText: 'Team'),
            dropdownColor: AirmiusColors.card,
            items: [
              for (final team in widget.teams)
                DropdownMenuItem(
                  value: team.id,
                  child: Text(
                    team.clubName == null
                        ? team.name
                        : '${team.name} - ${team.clubName}',
                  ),
                ),
            ],
            onChanged: (value) => setState(() {
              _teamId = value;
              if (value == null) _usesPenaltyCatalog = false;
            }),
          ),
          const SizedBox(height: 6),
          const Text(
            'Private Events brauchen ein Team.',
            style: TextStyle(
              color: AirmiusColors.muted,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ],
    );
  }

  Widget _timeStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Zeit',
          style: TextStyle(
            color: AirmiusColors.text,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Wann findet das Event statt?',
          style: TextStyle(
            color: AirmiusColors.muted,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 16),
        AirmiusButton(
          label: 'Start: ${_dateLabel(_start)} ${_time(_start)}',
          icon: Icons.schedule,
          onPressed: _pickStart,
          secondary: true,
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: _end == null
              ? 'Ende optional'
              : 'Ende: ${_dateLabel(_end!)} ${_time(_end!)}',
          icon: Icons.update,
          onPressed: _pickEnd,
          secondary: true,
        ),
        const SizedBox(height: 10),
        const Text(
          'Zeitzone: UTC für die API, Anzeige lokal in der App.',
          style: TextStyle(
            color: AirmiusColors.muted,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }

  Widget _detailsStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Details',
          style: TextStyle(
            color: AirmiusColors.text,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Optional: Ort, Teilnehmerlimit und Notizen.',
          style: TextStyle(
            color: AirmiusColors.muted,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _locationController,
          decoration: const InputDecoration(
            labelText: 'Ort',
            hintText: 'Sportplatz, Halle, Adresse',
          ),
        ),
        const SizedBox(height: 12),
        TextFormField(
          controller: _maxParticipantsController,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(
            labelText: 'Max. Teilnehmer optional',
          ),
        ),
        const SizedBox(height: 12),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          value: _usesPenaltyCatalog,
          onChanged: _visibility == 'private' && _teamId != null
              ? (value) => setState(() => _usesPenaltyCatalog = value)
              : null,
          activeThumbColor: AirmiusColors.blue,
          title: const Text(
            'Mit Strafkatalog arbeiten',
            style: TextStyle(
              color: AirmiusColors.text,
              fontWeight: FontWeight.w900,
            ),
          ),
          subtitle: const Text(
            'Teamkasse: Strafen können im Event an anwesende Spieler vergeben werden.',
            style: TextStyle(
              color: AirmiusColors.muted,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        const SizedBox(height: 12),
        TextFormField(
          controller: _notesController,
          maxLines: 4,
          decoration: const InputDecoration(labelText: 'Notizen optional'),
        ),
      ],
    );
  }

  Widget _reviewStep() {
    var clubName = '-';
    for (final club in widget.clubs) {
      if (club.id == _clubId) clubName = club.name;
    }
    var teamName = '-';
    for (final team in widget.teams) {
      if (team.id == _teamId) teamName = team.name;
    }
    final maxParticipants = _maxParticipantsController.text.trim().isEmpty
        ? 'Unbegrenzt'
        : '${_maxParticipantsController.text.trim()} Personen';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Prüfen',
          style: TextStyle(
            color: AirmiusColors.text,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Kontrolliere deine Angaben vor dem Speichern.',
          style: TextStyle(
            color: AirmiusColors.muted,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 16),
        AirmiusPanel(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _ReviewLine(
                label: 'Titel',
                value: _titleController.text.trim().isEmpty
                    ? '-'
                    : _titleController.text.trim(),
              ),
              _ReviewLine(label: 'Typ', value: _typeLabel(_type)),
              _ReviewLine(
                label: 'Sichtbarkeit',
                value: _visibilityLabel(_visibility),
              ),
              _ReviewLine(label: 'Verein', value: clubName),
              _ReviewLine(label: 'Team', value: teamName),
              _ReviewLine(
                label: 'Start',
                value: '${_dateLabel(_start)} ${_time(_start)}',
              ),
              _ReviewLine(
                label: 'Ende',
                value: _end == null
                    ? '-'
                    : '${_dateLabel(_end!)} ${_time(_end!)}',
              ),
              _ReviewLine(label: 'Teilnehmerlimit', value: maxParticipants),
              _ReviewLine(
                label: 'Strafkatalog',
                value: _usesPenaltyCatalog
                    ? 'Aktiv für dieses Team-Event'
                    : 'Nicht aktiv',
              ),
              _ReviewLine(
                label: 'Ort',
                value: _locationController.text.trim().isEmpty
                    ? '-'
                    : _locationController.text.trim(),
              ),
              _ReviewLine(
                label: 'Notizen',
                value: _notesController.text.trim().isEmpty
                    ? '-'
                    : _notesController.text.trim(),
              ),
            ],
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final validationMessage = _validationMessage;
    return Dialog(
      insetPadding: const EdgeInsets.all(10),
      backgroundColor: AirmiusColors.card,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 560, maxHeight: 740),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 16, 12, 12),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Event erstellen',
                            style: TextStyle(
                              color: AirmiusColors.text,
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Schritt $_step von 4',
                            style: const TextStyle(
                              color: AirmiusColors.blue,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.close),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 0, 18, 16),
                child: Row(
                  children: [
                    _stepButton(1, 'Basis'),
                    const SizedBox(width: 8),
                    _stepButton(2, 'Zeit'),
                    const SizedBox(width: 8),
                    _stepButton(3, 'Details'),
                    const SizedBox(width: 8),
                    _stepButton(4, 'Prüfen'),
                  ],
                ),
              ),
              const Divider(height: 1, color: AirmiusColors.border),
              Flexible(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(18),
                  child: _stepBody(),
                ),
              ),
              const Divider(height: 1, color: AirmiusColors.border),
              Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (validationMessage != null) ...[
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 10,
                        ),
                        decoration: BoxDecoration(
                          border: Border.all(
                            color: AirmiusColors.amber.withValues(alpha: 0.7),
                          ),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          validationMessage,
                          style: const TextStyle(
                            color: AirmiusColors.text,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                    Row(
                      children: [
                        Expanded(
                          child: AirmiusButton(
                            label: 'Zurück',
                            icon: Icons.chevron_left,
                            onPressed: _step == 1 ? null : _previousStep,
                            secondary: true,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: AirmiusButton(
                            label: _step == 4 ? 'Erstellen' : 'Weiter',
                            icon: _step == 4 ? Icons.add : Icons.chevron_right,
                            onPressed: _step == 4 ? _submit : _nextStep,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ReviewLine extends StatelessWidget {
  const _ReviewLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: const TextStyle(
              color: AirmiusColors.muted,
              fontSize: 11,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            value,
            style: const TextStyle(
              color: AirmiusColors.text,
              fontWeight: FontWeight.w800,
              height: 1.3,
            ),
          ),
        ],
      ),
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
        child: Center(
          child: CircularProgressIndicator(
            strokeWidth: 2,
            color: AirmiusColors.blue,
          ),
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
          Text(
            scope.t('events.error'),
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: AirmiusColors.text,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: scope.t('events.retry'),
            icon: Icons.refresh_outlined,
            onPressed: onRetry,
            secondary: true,
          ),
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
          const Icon(
            Icons.event_busy_outlined,
            color: AirmiusColors.muted,
            size: 40,
          ),
          const SizedBox(height: 12),
          const Text(
            'Keine Events gefunden',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: AirmiusColors.text,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Es gibt aktuell keine passenden Events. Passe die Filter an oder erstelle ein neues Event.',
            textAlign: TextAlign.center,
            style: TextStyle(color: AirmiusColors.muted, height: 1.35),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            alignment: WrapAlignment.center,
            children: [
              AirmiusButton(
                label: 'Filter zurücksetzen',
                icon: Icons.restart_alt,
                onPressed: onReset,
                secondary: true,
              ),
              AirmiusButton(
                label: 'Event erstellen',
                icon: Icons.add,
                onPressed: onCreate,
              ),
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
        fallbackBody: event.notes?.isNotEmpty == true
            ? event.notes!
            : fallbackBody,
      ),
    ),
  );
}

AirmiusEventWorkspace _emptyWorkspace() {
  return const AirmiusEventWorkspace(
    events: [],
    calendarEvents: [],
    stats: AirmiusEventStats(upcoming: 0, today: 0, cancelled: 0),
    eventTypes: ['training', 'match', 'meeting', 'public'],
    visibilities: ['private', 'organization', 'public'],
    clubs: [],
    teams: [],
    sports: [],
  );
}

List<AirmiusEvent> _replaceEvent(List<AirmiusEvent> events, AirmiusEvent next) {
  var replaced = false;
  final items = events.map((event) {
    if (event.id != next.id) return event;
    replaced = true;
    return next;
  }).toList();
  if (!replaced) items.add(next);
  items.sort((a, b) => a.startsAt.compareTo(b.startsAt));
  return items;
}

String _calendarMonthValue(DateTime value) =>
    '${value.year}-${value.month.toString().padLeft(2, '0')}';

List<DateTime> _calendarDays(DateTime cursor) {
  final first = DateTime(cursor.year, cursor.month);
  final mondayOffset = first.weekday - DateTime.monday;
  final start = first.subtract(Duration(days: mondayOffset));
  return List.generate(
    42,
    (index) => _dateOnly(start.add(Duration(days: index))),
  );
}

bool _isToday(DateTime value) => _isSameDay(value, DateTime.now());

bool _isSameDay(DateTime a, DateTime b) =>
    a.year == b.year && a.month == b.month && a.day == b.day;

DateTime _dateOnly(DateTime value) =>
    DateTime(value.year, value.month, value.day);

bool _isFull(AirmiusEvent event) =>
    event.maxParticipants != null &&
    event.yesCount >= event.maxParticipants! &&
    event.myParticipationStatus != 'yes';

String _capacityLabel(AirmiusEvent event) => event.maxParticipants == null
    ? '${event.yesCount} Zusagen'
    : '${event.yesCount}/${event.maxParticipants} Plaetze';

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
  const months = [
    'Januar',
    'Februar',
    'Maerz',
    'April',
    'Mai',
    'Juni',
    'Juli',
    'August',
    'September',
    'Oktober',
    'November',
    'Dezember',
  ];
  return '${months[value.month - 1]} ${value.year}';
}

String _weekdayShort(DateTime value) {
  const days = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
  return days[value.weekday - 1];
}

String _monthShort(DateTime value) {
  const months = [
    'Jan',
    'Feb',
    'Mrz',
    'Apr',
    'Mai',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Okt',
    'Nov',
    'Dez',
  ];
  return months[value.month - 1];
}

String _typeLabel(String value) => switch (value) {
  'training' => 'Training',
  'match' => 'Spiel',
  'meeting' => 'Meeting',
  'public' => 'Öffentlich',
  _ => value,
};

String _visibilityLabel(String value) => switch (value) {
  'private' => 'Nur Team',
  'organization' => 'Verein',
  'public' => 'Öffentlich',
  _ => value,
};
