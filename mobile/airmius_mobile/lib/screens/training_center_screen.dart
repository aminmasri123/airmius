import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'training_event_detail_screen.dart';
import 'training_plans_logs_screen.dart';

enum _EventPeriod { upcoming, past, all }

enum _EventViewMode { calendar, list }

class TrainingCenterScreen extends StatefulWidget {
  const TrainingCenterScreen({super.key, this.initialSearch = ''});

  final String initialSearch;

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
  void initState() {
    super.initState();
    _search = widget.initialSearch;
    _searchController.text = widget.initialSearch;
  }

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
    if (_savingEventId != null ||
        !event.canJoin ||
        event.status == 'cancelled') {
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
        allowsRecurring: workspace?.allowsRecurring ?? false,
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
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AirmiusScope.of(context).t('events.created'))),
      );
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
        SnackBar(
          content: Text(
            '${AirmiusScope.of(context).t('events.createError')} ${error is AirmiusApiException ? error.userMessage : AirmiusScope.of(context).t('common.errorDetails')}',
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      appBar: AppBar(
        backgroundColor: airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          scope.t('training.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: scope.t('training.title'),
        subtitle: '',
        showHeader: false,
        child: RefreshIndicator(
          color: airmiusAccentColor(context),
          backgroundColor: airmiusSurfaceColor(context),
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
                      onOpenPlansAndLogs: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const TrainingPlansLogsScreen(),
                        ),
                      ),
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
    required this.onOpenPlansAndLogs,
  });

  final AirmiusEventWorkspace workspace;
  final bool creating;
  final VoidCallback onCreate;
  final VoidCallback onOpenPlansAndLogs;

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
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          IconBadge(
                            icon: Icons.event_available_outlined,
                            color: airmiusAccentColor(context),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Eyebrow(scope.t('events.area')),
                                const SizedBox(height: 10),
                                const _EventsTitle(),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        AirmiusButton(
                          label: scope.t('trainingHub.plansAndLogs'),
                          icon: Icons.fitness_center_outlined,
                          secondary: true,
                          onPressed: onOpenPlansAndLogs,
                        ),
                        AirmiusButton(
                          label: creating
                              ? scope.t('events.creating')
                              : scope.t('events.create'),
                          icon: Icons.add,
                          onPressed: creating ? null : onCreate,
                        ),
                      ],
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
                      StatusPill(
                        scope.t('events.nextEvent'),
                        color: Theme.of(context).colorScheme.secondary,
                      ),
                      Text(
                        '${nextEvent.title} - ${_eventDateTimeLabel(context, nextEvent)}',
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
          Divider(height: 1, color: airmiusBorderColor(context)),
          LayoutBuilder(
            builder: (context, constraints) {
              final narrow = constraints.maxWidth < 420;
              final cards = [
                _StatCell(
                  label: scope.t('events.upcoming'),
                  value: '${workspace.stats.upcoming}',
                ),
                _StatCell(
                  label: scope.t('events.today'),
                  value: '${workspace.stats.today}',
                ),
                _StatCell(
                  label: scope.t('events.cancelledFilter'),
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
          right: BorderSide(
            color: airmiusBorderColor(context).withValues(alpha: 0.7),
          ),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontSize: 11,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: TextStyle(
              color: airmiusTextColor(context),
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
      style: TextStyle(
        color: airmiusTextColor(context),
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
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Column(
        children: [
          TextField(
            controller: controller,
            onChanged: onSearchChanged,
            onSubmitted: (_) => onSubmit(),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w800,
            ),
            decoration: InputDecoration(
              hintText: scope.t('events.searchHint'),
              prefixIcon: Icon(Icons.search, color: airmiusMutedColor(context)),
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _PeriodButton(
                label: scope.t('events.upcoming'),
                selected: period == _EventPeriod.upcoming,
                onTap: () => onPeriodChanged(_EventPeriod.upcoming),
              ),
              _PeriodButton(
                label: scope.t('events.past'),
                selected: period == _EventPeriod.past,
                onTap: () => onPeriodChanged(_EventPeriod.past),
              ),
              _PeriodButton(
                label: scope.t('events.all'),
                selected: period == _EventPeriod.all,
                onTap: () => onPeriodChanged(_EventPeriod.all),
              ),
              OutlinedButton.icon(
                onPressed: onToggleFilters,
                icon: Icon(Icons.tune, size: 18),
                label: Text(
                  activeFilterCount == 0
                      ? scope.t('events.filters')
                      : '${scope.t('events.filters')} $activeFilterCount',
                ),
              ),
              FilledButton.icon(
                onPressed: onSubmit,
                icon: Icon(Icons.search, size: 18),
                label: Text(scope.t('events.search')),
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
      selectedColor: airmiusAccentColor(context),
      backgroundColor: airmiusSurfaceSoftColor(context),
      side: BorderSide(
        color: selected
            ? airmiusAccentColor(context)
            : airmiusBorderColor(context),
      ),
      labelStyle: TextStyle(
        color: selected
            ? airmiusOnColor(airmiusAccentColor(context))
            : airmiusMutedColor(context),
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
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          DropdownButtonFormField<String>(
            initialValue: type,
            decoration: InputDecoration(labelText: scope.t('events.type')),
            dropdownColor: airmiusSurfaceColor(context),
            items: [
              DropdownMenuItem(
                value: '',
                child: Text(scope.t('events.allTypes')),
              ),
              for (final item in eventTypes)
                DropdownMenuItem(
                  value: item,
                  child: Text(_typeLabel(context, item)),
                ),
            ],
            onChanged: (value) => onTypeChanged(value ?? ''),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: visibility,
            decoration: InputDecoration(
              labelText: scope.t('events.visibility'),
            ),
            dropdownColor: airmiusSurfaceColor(context),
            items: [
              DropdownMenuItem(value: '', child: Text(scope.t('events.all'))),
              for (final item in visibilities)
                DropdownMenuItem(
                  value: item,
                  child: Text(_visibilityLabel(context, item)),
                ),
            ],
            onChanged: (value) => onVisibilityChanged(value ?? ''),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<int?>(
            initialValue: clubId,
            decoration: InputDecoration(labelText: scope.t('events.club')),
            dropdownColor: airmiusSurfaceColor(context),
            items: [
              DropdownMenuItem<int?>(
                value: null,
                child: Text(scope.t('events.allClubs')),
              ),
              for (final club in clubs)
                DropdownMenuItem<int?>(value: club.id, child: Text(club.name)),
            ],
            onChanged: onClubChanged,
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<int?>(
            initialValue: teamId,
            decoration: InputDecoration(labelText: scope.t('events.team')),
            dropdownColor: airmiusSurfaceColor(context),
            items: [
              DropdownMenuItem<int?>(
                value: null,
                child: Text(scope.t('events.allTeams')),
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
                label: scope.t('events.resetFilters'),
                icon: Icons.restart_alt,
                onPressed: onReset,
                secondary: true,
              ),
              AirmiusButton(
                label: scope.t('events.applyFilters'),
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
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Wrap(
              spacing: 16,
              runSpacing: 12,
              alignment: WrapAlignment.spaceBetween,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 280),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Eyebrow(scope.t('events.view')),
                      const SizedBox(height: 4),
                      Text(
                        scope.t('events.calendarAndList'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
                ),
                SegmentedButton<_EventViewMode>(
                  segments: [
                    ButtonSegment(
                      value: _EventViewMode.calendar,
                      icon: Icon(Icons.calendar_month),
                      label: Text(scope.t('events.calendar')),
                    ),
                    ButtonSegment(
                      value: _EventViewMode.list,
                      icon: Icon(Icons.list),
                      label: Text(scope.t('events.list')),
                    ),
                  ],
                  selected: {viewMode},
                  onSelectionChanged: (values) =>
                      onViewModeChanged(values.first),
                ),
              ],
            ),
          ),
          Divider(height: 1, color: airmiusBorderColor(context)),
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
    final scope = AirmiusScope.of(context);
    final localizations = MaterialLocalizations.of(context);
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
                icon: Icon(Icons.chevron_left),
              ),
              Expanded(
                child: Text(
                  _monthLabel(context, cursor),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              TextButton(
                onPressed: onToday,
                child: Text(scope.t('events.today')),
              ),
              IconButton(
                onPressed: () => onMove(1),
                icon: Icon(Icons.chevron_right),
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
            for (var offset = 0; offset < 7; offset++)
              Center(
                child: Text(
                  localizations.narrowWeekdays[(DateTime.monday + offset) % 7],
                  style: TextStyle(
                    color: airmiusMutedColor(context),
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
        Divider(height: 1, color: airmiusBorderColor(context)),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(scope.t('events.selectedDay')),
              const SizedBox(height: 4),
              Text(
                _dateLabel(context, selectedDate),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
              if (selectedEvents.isEmpty)
                AirmiusPanel(body: scope.t('events.noEventsSelectedDay'))
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
          color: airmiusSurfaceColor(context),
          border: Border.all(
            color: selected
                ? airmiusAccentColor(context)
                : airmiusBorderColor(context).withValues(alpha: 0.45),
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
                  color: today
                      ? airmiusAccentColor(context)
                      : Colors.transparent,
                  shape: BoxShape.circle,
                ),
                child: Text(
                  '${date.day}',
                  style: TextStyle(
                    color: today
                        ? airmiusOnColor(airmiusAccentColor(context))
                        : airmiusTextColor(context),
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
                                ? Theme.of(context).colorScheme.error
                                : airmiusAccentColor(context))
                            .withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(5),
                  ),
                  child: Text(
                    '${_time(event.startsAt)} ${event.title}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: event.status == 'cancelled'
                          ? Theme.of(context).colorScheme.error
                          : airmiusAccentColor(context),
                      fontSize: 9,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              if (events.length > 2)
                Text(
                  AirmiusScope.of(context)
                      .t('events.more')
                      .replaceFirst('{count}', '${events.length - 2}'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
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
    final scope = AirmiusScope.of(context);
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
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _eventDateTimeLabel(context, event),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  event.location ?? scope.t('events.locationMissing'),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ),
          StatusPill(
            _capacityLabel(context, event),
            color: airmiusMutedColor(context),
          ),
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
    final owner =
        event.clubName ?? event.teamName ?? scope.t('events.publicArea');
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
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 17,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    StatusPill(
                      _capacityLabel(context, event),
                      color: airmiusMutedColor(context),
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                Text(
                  owner,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(
                      _typeLabel(context, event.type),
                      color: airmiusAccentColor(context),
                    ),
                    StatusPill(
                      _visibilityLabel(context, event.visibility),
                      color: airmiusMutedColor(context),
                    ),
                    if (event.commentsCount > 0)
                      StatusPill(
                        '${event.commentsCount} ${scope.t('events.comments')}',
                        color: Theme.of(context).colorScheme.tertiary,
                      ),
                    if (event.status == 'cancelled')
                      StatusPill(
                        scope.t('events.cancelledFilter'),
                        color: Theme.of(context).colorScheme.error,
                      ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  _eventDateTimeLabel(context, event),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  event.location ?? scope.t('events.locationMissing'),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                const SizedBox(height: 12),
                if (saving)
                  LinearProgressIndicator(
                    color: airmiusAccentColor(context),
                    backgroundColor: airmiusSurfaceSoftColor(context),
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
                  label: scope.t('events.details'),
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
        color: airmiusSurfaceSoftColor(context),
        border: Border.all(color: airmiusBorderColor(context)),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        children: [
          Text(
            _weekdayShort(context, date),
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontSize: 10,
              fontWeight: FontWeight.w900,
            ),
          ),
          Text(
            '${date.day}',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 22,
              fontWeight: FontWeight.w900,
            ),
          ),
          Text(
            _monthShort(date),
            style: TextStyle(
              color: airmiusMutedColor(context),
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
      'yes' => Theme.of(context).colorScheme.secondary,
      'maybe' => airmiusAccentColor(context),
      _ => Theme.of(context).colorScheme.error,
    };
    return OutlinedButton(
      onPressed: disabled ? null : () => onRespond(event, value),
      style: OutlinedButton.styleFrom(
        foregroundColor: selected ? color : airmiusMutedColor(context),
        backgroundColor: selected
            ? color.withValues(alpha: 0.14)
            : Colors.transparent,
        side: BorderSide(color: selected ? color : airmiusBorderColor(context)),
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 10),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
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
    required this.allowsRecurring,
  });

  final List<AirmiusClub> clubs;
  final List<AirmiusTeam> teams;
  final List<String> eventTypes;
  final List<String> visibilities;
  final bool allowsRecurring;

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
  String? _recurring;
  DateTime? _recurrenceEndsAt;
  final Set<int> _recurrenceDays = {};
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

  Future<void> _pickRecurrenceEnd() async {
    final initial = _recurrenceEndsAt ?? _start.add(const Duration(days: 28));
    final date = await showDatePicker(
      context: context,
      initialDate: initial.isBefore(_start) ? _start : initial,
      firstDate: _start,
      lastDate: _start.add(const Duration(days: 365 * 2)),
    );
    if (date == null || !mounted) return;
    setState(
      () => _recurrenceEndsAt = DateTime(
        date.year,
        date.month,
        date.day,
        _start.hour,
        _start.minute,
      ),
    );
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
    final scope = AirmiusScope.of(context);
    if (_step == 1) {
      if (_titleController.text.trim().isEmpty) {
        return scope.t('events.titleRequired');
      }
      if (_visibility == 'organization' && _clubId == null) {
        return scope.t('events.clubRequired');
      }
      if (_visibility == 'private' && _teamId == null) {
        return scope.t('events.teamRequired');
      }
    }
    if (_step == 2) {
      if (_end != null && _end!.isBefore(_start)) {
        return scope.t('events.endError');
      }
      if (_recurring != null &&
          _recurrenceEndsAt != null &&
          !_recurrenceEndsAt!.isAfter(_start)) {
        return scope.t('events.recurrenceEndError');
      }
      if ((_recurring == 'weekly' || _recurring == 'biweekly') &&
          _recurrenceDays.isEmpty) {
        return scope.t('events.recurrenceDayRequired');
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
      if (_recurring != null) ...{
        'recurring': _recurring,
        'recurrence_ends_at': _recurrenceEndsAt!.toUtc().toIso8601String(),
        if (_recurring == 'weekly' || _recurring == 'biweekly')
          'recurrence_days': _recurrenceDays.toList()..sort(),
      },
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
              ? airmiusOnColor(airmiusAccentColor(context))
              : complete
              ? Theme.of(context).colorScheme.secondary.withValues(alpha: 0.18)
              : airmiusSurfaceSoftColor(context),
          foregroundColor: active
              ? airmiusSurfaceColor(context)
              : complete
              ? Theme.of(context).colorScheme.secondary
              : airmiusMutedColor(context),
          disabledBackgroundColor: active
              ? airmiusOnColor(airmiusAccentColor(context))
              : airmiusSurfaceSoftColor(context),
          disabledForegroundColor: active
              ? airmiusSurfaceColor(context)
              : airmiusMutedColor(context),
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(99),
          ),
        ),
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
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
    final scope = AirmiusScope.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          scope.t('events.basicData'),
          style: TextStyle(
            color: airmiusTextColor(context),
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          scope.t('events.basicQuestion'),
          style: TextStyle(
            color: airmiusMutedColor(context),
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _titleController,
          autofocus: true,
          onChanged: (_) => setState(() {}),
          decoration: InputDecoration(
            labelText: scope.t('events.fieldTitle'),
            hintText: scope.t('events.titleExample'),
          ),
          validator: (value) => value == null || value.trim().isEmpty
              ? scope.t('events.titleRequired')
              : null,
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _type,
          decoration: InputDecoration(labelText: scope.t('events.type')),
          dropdownColor: airmiusSurfaceColor(context),
          items: [
            for (final item in _eventTypeOptions)
              DropdownMenuItem(
                value: item,
                child: Text(_typeLabel(context, item)),
              ),
          ],
          onChanged: (value) => setState(() => _type = value ?? 'training'),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _visibility,
          decoration: InputDecoration(labelText: scope.t('events.visibility')),
          dropdownColor: airmiusSurfaceColor(context),
          items: [
            for (final item in _visibilityOptions)
              DropdownMenuItem(
                value: item,
                child: Text(_visibilityLabel(context, item)),
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
            decoration: InputDecoration(labelText: scope.t('events.club')),
            dropdownColor: airmiusSurfaceColor(context),
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
            decoration: InputDecoration(labelText: scope.t('events.team')),
            dropdownColor: airmiusSurfaceColor(context),
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
          Text(
            scope.t('events.privateTeamRequired'),
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ],
    );
  }

  Widget _timeStep() {
    final scope = AirmiusScope.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          scope.t('events.time'),
          style: TextStyle(
            color: airmiusTextColor(context),
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          scope.t('events.timeQuestion'),
          style: TextStyle(
            color: airmiusMutedColor(context),
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 16),
        AirmiusButton(
          label:
              '${scope.t('events.start')}: ${_dateLabel(context, _start)} ${_time(_start)}',
          icon: Icons.schedule,
          onPressed: _pickStart,
          secondary: true,
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: _end == null
              ? scope.t('events.endOptional')
              : '${scope.t('events.end')}: ${_dateLabel(context, _end!)} ${_time(_end!)}',
          icon: Icons.update,
          onPressed: _pickEnd,
          secondary: true,
        ),
        const SizedBox(height: 10),
        Text(
          scope.t('events.timezoneHint'),
          style: TextStyle(
            color: airmiusMutedColor(context),
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
        if (widget.allowsRecurring) ...[
          const SizedBox(height: 18),
          Text(
            scope.t('events.recurring'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            scope.t('events.recurringHint'),
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String?>(
            initialValue: _recurring,
            decoration: InputDecoration(labelText: scope.t('events.repeat')),
            dropdownColor: airmiusSurfaceColor(context),
            items: [
              DropdownMenuItem<String?>(
                value: null,
                child: Text(scope.t('events.repeat.none')),
              ),
              for (final value in const [
                'daily',
                'weekly',
                'biweekly',
                'monthly',
              ])
                DropdownMenuItem<String?>(
                  value: value,
                  child: Text(scope.t('events.repeat.$value')),
                ),
            ],
            onChanged: (value) => setState(() {
              _recurring = value;
              if (value == null) {
                _recurrenceEndsAt = null;
                _recurrenceDays.clear();
              } else {
                _recurrenceEndsAt ??= _start.add(const Duration(days: 28));
              }
              if (value == 'weekly' || value == 'biweekly') {
                _recurrenceDays
                  ..clear()
                  ..add(_start.weekday % 7);
              }
            }),
          ),
          if (_recurring != null) ...[
            const SizedBox(height: 10),
            AirmiusButton(
              label: _recurrenceEndsAt == null
                  ? scope.t('events.recurrenceEnd')
                  : '${scope.t('events.recurrenceEnd')}: ${_dateLabel(context, _recurrenceEndsAt!)}',
              icon: Icons.event_repeat_outlined,
              onPressed: _pickRecurrenceEnd,
              secondary: true,
            ),
          ],
          if (_recurring == 'weekly' || _recurring == 'biweekly') ...[
            const SizedBox(height: 10),
            Text(
              scope.t('events.recurrenceDays'),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 6),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final day in List.generate(7, (index) => index))
                  FilterChip(
                    label: Text(scope.t('events.day.$day')),
                    selected: _recurrenceDays.contains(day),
                    onSelected: (selected) => setState(() {
                      if (selected) {
                        _recurrenceDays.add(day);
                      } else {
                        _recurrenceDays.remove(day);
                      }
                    }),
                  ),
              ],
            ),
          ],
        ],
      ],
    );
  }

  Widget _detailsStep() {
    final scope = AirmiusScope.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          scope.t('events.details'),
          style: TextStyle(
            color: airmiusTextColor(context),
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          scope.t('events.detailsHint'),
          style: TextStyle(
            color: airmiusMutedColor(context),
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _locationController,
          decoration: InputDecoration(
            labelText: scope.t('events.location'),
            hintText: scope.t('events.locationExample'),
          ),
        ),
        const SizedBox(height: 12),
        TextFormField(
          controller: _maxParticipantsController,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(
            labelText: scope.t('events.maxParticipants'),
          ),
        ),
        const SizedBox(height: 12),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          value: _usesPenaltyCatalog,
          onChanged: _visibility == 'private' && _teamId != null
              ? (value) => setState(() => _usesPenaltyCatalog = value)
              : null,
          activeThumbColor: airmiusAccentColor(context),
          title: Text(
            scope.t('events.usePenaltyCatalog'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          subtitle: Text(
            scope.t('events.penaltyCatalogHint'),
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        const SizedBox(height: 12),
        TextFormField(
          controller: _notesController,
          maxLines: 4,
          decoration: InputDecoration(labelText: scope.t('events.notes')),
        ),
      ],
    );
  }

  Widget _reviewStep() {
    final scope = AirmiusScope.of(context);
    var clubName = '-';
    for (final club in widget.clubs) {
      if (club.id == _clubId) clubName = club.name;
    }
    var teamName = '-';
    for (final team in widget.teams) {
      if (team.id == _teamId) teamName = team.name;
    }
    final maxParticipants = _maxParticipantsController.text.trim().isEmpty
        ? scope.t('events.unlimited')
        : '${_maxParticipantsController.text.trim()} ${scope.t('events.people')}';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          scope.t('events.review'),
          style: TextStyle(
            color: airmiusTextColor(context),
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          scope.t('events.reviewHint'),
          style: TextStyle(
            color: airmiusMutedColor(context),
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
                label: scope.t('events.fieldTitle'),
                value: _titleController.text.trim().isEmpty
                    ? '-'
                    : _titleController.text.trim(),
              ),
              _ReviewLine(
                label: AirmiusScope.of(context).t('events.type'),
                value: _typeLabel(context, _type),
              ),
              _ReviewLine(
                label: scope.t('events.visibility'),
                value: _visibilityLabel(context, _visibility),
              ),
              _ReviewLine(label: scope.t('events.club'), value: clubName),
              _ReviewLine(label: scope.t('events.team'), value: teamName),
              _ReviewLine(
                label: scope.t('events.start'),
                value: '${_dateLabel(context, _start)} ${_time(_start)}',
              ),
              _ReviewLine(
                label: scope.t('events.end'),
                value: _end == null
                    ? '-'
                    : '${_dateLabel(context, _end!)} ${_time(_end!)}',
              ),
              if (_recurring != null) ...[
                _ReviewLine(
                  label: scope.t('events.repeat'),
                  value: scope.t('events.repeat.$_recurring'),
                ),
                _ReviewLine(
                  label: scope.t('events.recurrenceEnd'),
                  value: _recurrenceEndsAt == null
                      ? '-'
                      : _dateLabel(context, _recurrenceEndsAt!),
                ),
              ],
              _ReviewLine(
                label: scope.t('events.participantLimit'),
                value: maxParticipants,
              ),
              _ReviewLine(
                label: scope.t('events.penaltyCatalog'),
                value: _usesPenaltyCatalog
                    ? scope.t('events.penaltyActive')
                    : scope.t('events.inactive'),
              ),
              _ReviewLine(
                label: scope.t('events.location'),
                value: _locationController.text.trim().isEmpty
                    ? '-'
                    : _locationController.text.trim(),
              ),
              _ReviewLine(
                label: scope.t('events.notesLabel'),
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
    final scope = AirmiusScope.of(context);
    final validationMessage = _validationMessage;
    return Dialog(
      insetPadding: const EdgeInsets.all(10),
      backgroundColor: airmiusSurfaceColor(context),
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
                          Text(
                            scope.t('events.createTitle'),
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${scope.t('events.step')} $_step ${scope.t('events.of')} 4',
                            style: TextStyle(
                              color: airmiusAccentColor(context),
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: Icon(Icons.close),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 0, 18, 16),
                child: Row(
                  children: [
                    _stepButton(1, scope.t('events.stepBasic')),
                    const SizedBox(width: 8),
                    _stepButton(2, scope.t('events.time')),
                    const SizedBox(width: 8),
                    _stepButton(3, scope.t('events.details')),
                    const SizedBox(width: 8),
                    _stepButton(4, scope.t('events.review')),
                  ],
                ),
              ),
              Divider(height: 1, color: airmiusBorderColor(context)),
              Flexible(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(18),
                  child: _stepBody(),
                ),
              ),
              Divider(height: 1, color: airmiusBorderColor(context)),
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
                            color: Theme.of(
                              context,
                            ).colorScheme.tertiary.withValues(alpha: 0.7),
                          ),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          validationMessage,
                          style: TextStyle(
                            color: airmiusTextColor(context),
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
                            label: scope.t('events.keep'),
                            icon: Icons.chevron_left,
                            onPressed: _step == 1 ? null : _previousStep,
                            secondary: true,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: AirmiusButton(
                            label: _step == 4
                                ? scope.t('events.create')
                                : scope.t('events.next'),
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
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontSize: 11,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            value,
            style: TextStyle(
              color: airmiusTextColor(context),
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
    return AirmiusPanel(
      child: Padding(
        padding: EdgeInsets.symmetric(vertical: 20),
        child: Center(
          child: CircularProgressIndicator(
            strokeWidth: 2,
            color: airmiusAccentColor(context),
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
          Icon(
            Icons.error_outline,
            color: Theme.of(context).colorScheme.error,
            size: 34,
          ),
          const SizedBox(height: 10),
          Text(
            scope.t('events.error'),
            textAlign: TextAlign.center,
            style: TextStyle(
              color: airmiusTextColor(context),
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
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      borderColor: airmiusBorderColor(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Icon(
            Icons.event_busy_outlined,
            color: airmiusMutedColor(context),
            size: 40,
          ),
          const SizedBox(height: 12),
          Text(
            scope.t('events.emptyTitle'),
            textAlign: TextAlign.center,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            scope.t('events.emptyBody'),
            textAlign: TextAlign.center,
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            alignment: WrapAlignment.center,
            children: [
              AirmiusButton(
                label: scope.t('events.resetFilters'),
                icon: Icons.restart_alt,
                onPressed: onReset,
                secondary: true,
              ),
              AirmiusButton(
                label: scope.t('events.createTitle'),
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
    _eventDateTimeLabel(context, event),
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

String _capacityLabel(BuildContext context, AirmiusEvent event) {
  final scope = AirmiusScope.of(context);
  return event.maxParticipants == null
      ? '${event.yesCount} ${scope.t('events.confirmations')}'
      : '${event.yesCount}/${event.maxParticipants} ${scope.t('events.seats')}';
}

String _eventDateTimeLabel(BuildContext context, AirmiusEvent event) {
  final end = event.endsAt;
  final startLabel =
      '${_dateLabel(context, event.startsAt)} ${_time(event.startsAt)}';
  if (end == null) return startLabel;
  if (_isSameDay(event.startsAt, end)) return '$startLabel - ${_time(end)}';
  return '$startLabel - ${_dateLabel(context, end)} ${_time(end)}';
}

String _dateLabel(BuildContext context, DateTime value) =>
    MaterialLocalizations.of(context).formatShortDate(value.toLocal());

String _time(DateTime value) {
  final local = value.toLocal();
  return '${local.hour.toString().padLeft(2, '0')}:${local.minute.toString().padLeft(2, '0')}';
}

String _monthLabel(BuildContext context, DateTime value) =>
    MaterialLocalizations.of(context).formatMonthYear(value);

String _weekdayShort(BuildContext context, DateTime value) =>
    MaterialLocalizations.of(context).narrowWeekdays[value.weekday % 7];

String _monthShort(DateTime value) => value.month.toString().padLeft(2, '0');

String _typeLabel(BuildContext context, String value) {
  final translated = AirmiusScope.of(context).t('events.type.$value');
  return translated == 'events.type.$value' ? value : translated;
}

String _visibilityLabel(BuildContext context, String value) {
  final translated = AirmiusScope.of(context).t('events.visibility.$value');
  return translated == 'events.visibility.$value' ? value : translated;
}
