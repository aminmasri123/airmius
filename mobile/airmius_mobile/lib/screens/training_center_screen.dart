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
  int? _savingEventId;
  bool _creatingEvent = false;

  @override
  void initState() {
    super.initState();
    _search = widget.initialSearch;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _workspaceFuture ??= _loadWorkspace();
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

  void _resetFilters() {
    setState(() {
      _search = '';
      _type = '';
      _visibility = '';
      _clubId = null;
      _teamId = null;
      _period = _EventPeriod.upcoming;
      _workspaceFuture = _loadWorkspace();
    });
  }

  Future<void> _openSearchFilters() async {
    final currentFuture = _workspaceFuture;
    final workspace = currentFuture == null
        ? _emptyWorkspace()
        : await currentFuture;
    if (!mounted) return;
    final selection = await showModalBottomSheet<_EventFilterSelection>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: airmiusSurfaceColor(context),
      showDragHandle: true,
      builder: (context) => _EventSearchSheet(
        initialSearch: _search,
        initialPeriod: _period,
        initialType: _type,
        initialVisibility: _visibility,
        initialClubId: _clubId,
        initialTeamId: _teamId,
        eventTypes: workspace.eventTypes,
        visibilities: workspace.visibilities,
        clubs: workspace.clubs,
        teams: workspace.teams,
      ),
    );
    if (selection == null || !mounted) return;
    setState(() {
      _search = selection.search;
      _period = selection.period;
      _type = selection.type;
      _visibility = selection.visibility;
      _clubId = selection.clubId;
      _teamId = selection.teamId;
      _workspaceFuture = _loadWorkspace();
    });
  }

  int get _activeFilterCount => [
    _search,
    _type,
    _visibility,
    if (_clubId != null) 'club',
    if (_teamId != null) 'team',
    if (_period != _EventPeriod.upcoming) 'period',
  ].where((value) => value.isNotEmpty).length;

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
        sportRoutes: workspace?.sportRoutes ?? const [],
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
        actions: [
          _EventSearchAction(
            activeFilterCount: _activeFilterCount,
            onPressed: _openSearchFilters,
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: PageFrame(
        title: scope.t('training.title'),
        subtitle: '',
        showHeader: false,
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

            return Column(
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
                if (_activeFilterCount > 0) ...[
                  const SizedBox(height: 10),
                  _ActiveFilterSummary(
                    count: _activeFilterCount,
                    search: _search,
                    period: _period,
                    onEdit: _openSearchFilters,
                    onReset: _resetFilters,
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
            );
          },
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

class _EventSearchAction extends StatelessWidget {
  const _EventSearchAction({
    required this.activeFilterCount,
    required this.onPressed,
  });

  final int activeFilterCount;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final label = AirmiusScope.of(context).t('events.search');
    return IconButton(
      tooltip: label,
      onPressed: onPressed,
      icon: Stack(
        clipBehavior: Clip.none,
        children: [
          const Icon(Icons.search_rounded),
          if (activeFilterCount > 0)
            Positioned(
              right: -7,
              top: -7,
              child: Container(
                constraints: const BoxConstraints(minWidth: 17, minHeight: 17),
                padding: const EdgeInsets.symmetric(horizontal: 4),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.error,
                  borderRadius: BorderRadius.circular(99),
                  border: Border.all(
                    color: airmiusSurfaceColor(context),
                    width: 2,
                  ),
                ),
                alignment: Alignment.center,
                child: Text(
                  '$activeFilterCount',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 9,
                    height: 1,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _ActiveFilterSummary extends StatelessWidget {
  const _ActiveFilterSummary({
    required this.count,
    required this.search,
    required this.period,
    required this.onEdit,
    required this.onReset,
  });

  final int count;
  final String search;
  final _EventPeriod period;
  final VoidCallback onEdit;
  final VoidCallback onReset;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final periodLabel = switch (period) {
      _EventPeriod.upcoming => scope.t('events.upcoming'),
      _EventPeriod.past => scope.t('events.past'),
      _EventPeriod.all => scope.t('events.all'),
    };
    return Material(
      color: airmiusSurfaceSoftColor(context),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onEdit,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 6, 8),
          child: Row(
            children: [
              Icon(
                Icons.filter_alt_outlined,
                size: 20,
                color: airmiusAccentColor(context),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  search.trim().isEmpty
                      ? '$periodLabel · $count ${scope.t('events.filters')}'
                      : '“${search.trim()}” · $periodLabel',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              IconButton(
                tooltip: scope.t('events.resetFilters'),
                onPressed: onReset,
                icon: const Icon(Icons.close_rounded),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _EventFilterSelection {
  const _EventFilterSelection({
    required this.search,
    required this.period,
    required this.type,
    required this.visibility,
    required this.clubId,
    required this.teamId,
  });

  final String search;
  final _EventPeriod period;
  final String type;
  final String visibility;
  final int? clubId;
  final int? teamId;
}

class _EventSearchSheet extends StatefulWidget {
  const _EventSearchSheet({
    required this.initialSearch,
    required this.initialPeriod,
    required this.initialType,
    required this.initialVisibility,
    required this.initialClubId,
    required this.initialTeamId,
    required this.eventTypes,
    required this.visibilities,
    required this.clubs,
    required this.teams,
  });

  final String initialSearch;
  final _EventPeriod initialPeriod;
  final String initialType;
  final String initialVisibility;
  final int? initialClubId;
  final int? initialTeamId;
  final List<String> eventTypes;
  final List<String> visibilities;
  final List<AirmiusClub> clubs;
  final List<AirmiusTeam> teams;

  @override
  State<_EventSearchSheet> createState() => _EventSearchSheetState();
}

class _EventSearchSheetState extends State<_EventSearchSheet> {
  late final TextEditingController _searchController;
  late _EventPeriod _period;
  late String _type;
  late String _visibility;
  late int? _clubId;
  late int? _teamId;

  @override
  void initState() {
    super.initState();
    _searchController = TextEditingController(text: widget.initialSearch);
    _period = widget.initialPeriod;
    _type = widget.initialType;
    _visibility = widget.initialVisibility;
    _clubId = widget.initialClubId;
    _teamId = widget.initialTeamId;
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final filteredTeams = widget.teams
        .where((team) => _clubId == null || team.clubId == _clubId)
        .toList();
    return FractionallySizedBox(
      heightFactor: 0.9,
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          16,
          0,
          16,
          16 + MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    scope.t('events.search'),
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 22,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: scope.t('shell.closeMenu'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Expanded(
              child: ListView(
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,
                children: [
                  TextField(
                    controller: _searchController,
                    autofocus: true,
                    textInputAction: TextInputAction.search,
                    decoration: InputDecoration(
                      hintText: scope.t('events.searchHint'),
                      prefixIcon: const Icon(Icons.search),
                      suffixIcon: _searchController.text.isEmpty
                          ? null
                          : IconButton(
                              onPressed: () {
                                _searchController.clear();
                                setState(() {});
                              },
                              icon: const Icon(Icons.clear),
                            ),
                    ),
                    onChanged: (_) => setState(() {}),
                    onSubmitted: (_) => _apply(),
                  ),
                  const SizedBox(height: 18),
                  Text(
                    scope.t('events.filters'),
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  SegmentedButton<_EventPeriod>(
                    segments: [
                      ButtonSegment(
                        value: _EventPeriod.upcoming,
                        label: Text(scope.t('events.upcoming')),
                      ),
                      ButtonSegment(
                        value: _EventPeriod.past,
                        label: Text(scope.t('events.past')),
                      ),
                      ButtonSegment(
                        value: _EventPeriod.all,
                        label: Text(scope.t('events.all')),
                      ),
                    ],
                    selected: {_period},
                    onSelectionChanged: (value) =>
                        setState(() => _period = value.first),
                  ),
                  const SizedBox(height: 18),
                  DropdownButtonFormField<String>(
                    initialValue: _type,
                    decoration: InputDecoration(
                      labelText: scope.t('events.type'),
                    ),
                    items: [
                      DropdownMenuItem(
                        value: '',
                        child: Text(scope.t('events.allTypes')),
                      ),
                      for (final item in widget.eventTypes)
                        DropdownMenuItem(
                          value: item,
                          child: Text(_typeLabel(context, item)),
                        ),
                    ],
                    onChanged: (value) => setState(() => _type = value ?? ''),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: _visibility,
                    decoration: InputDecoration(
                      labelText: scope.t('events.visibility'),
                    ),
                    items: [
                      DropdownMenuItem(
                        value: '',
                        child: Text(scope.t('events.all')),
                      ),
                      for (final item in widget.visibilities)
                        DropdownMenuItem(
                          value: item,
                          child: Text(_visibilityLabel(context, item)),
                        ),
                    ],
                    onChanged: (value) =>
                        setState(() => _visibility = value ?? ''),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int?>(
                    initialValue: _clubId,
                    decoration: InputDecoration(
                      labelText: scope.t('events.club'),
                    ),
                    items: [
                      DropdownMenuItem<int?>(
                        value: null,
                        child: Text(scope.t('events.allClubs')),
                      ),
                      for (final club in widget.clubs)
                        DropdownMenuItem<int?>(
                          value: club.id,
                          child: Text(club.name),
                        ),
                    ],
                    onChanged: (value) => setState(() {
                      _clubId = value;
                      if (_teamId != null &&
                          !widget.teams.any(
                            (team) =>
                                team.id == _teamId &&
                                (_clubId == null || team.clubId == _clubId),
                          )) {
                        _teamId = null;
                      }
                    }),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int?>(
                    key: ValueKey('event-team-${_clubId ?? 'all'}-$_teamId'),
                    initialValue: _teamId,
                    decoration: InputDecoration(
                      labelText: scope.t('events.team'),
                    ),
                    items: [
                      DropdownMenuItem<int?>(
                        value: null,
                        child: Text(scope.t('events.allTeams')),
                      ),
                      for (final team in filteredTeams)
                        DropdownMenuItem<int?>(
                          value: team.id,
                          child: Text(team.name),
                        ),
                    ],
                    onChanged: (value) => setState(() => _teamId = value),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _reset,
                    icon: const Icon(Icons.restart_alt),
                    label: Text(scope.t('events.resetFilters')),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: _apply,
                    icon: const Icon(Icons.search),
                    label: Text(scope.t('events.search')),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  void _reset() {
    setState(() {
      _searchController.clear();
      _period = _EventPeriod.upcoming;
      _type = '';
      _visibility = '';
      _clubId = null;
      _teamId = null;
    });
  }

  void _apply() {
    Navigator.pop(
      context,
      _EventFilterSelection(
        search: _searchController.text.trim(),
        period: _period,
        type: _type,
        visibility: _visibility,
        clubId: _clubId,
        teamId: _teamId,
      ),
    );
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
    final color = airmiusParticipationColor(context, value);
    return OutlinedButton(
      onPressed: disabled ? null : () => onRespond(event, value),
      style: OutlinedButton.styleFrom(
        foregroundColor: color,
        backgroundColor: selected
            ? color.withValues(alpha: 0.22)
            : color.withValues(alpha: 0.08),
        side: BorderSide(
          color: selected ? color : color.withValues(alpha: 0.58),
          width: selected ? 1.6 : 1,
        ),
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 10),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          if (selected) ...[
            const Icon(Icons.check_rounded, size: 15),
            const SizedBox(width: 3),
          ],
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
            ),
          ),
        ],
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
    required this.sportRoutes,
  });

  final List<AirmiusClub> clubs;
  final List<AirmiusTeam> teams;
  final List<String> eventTypes;
  final List<String> visibilities;
  final bool allowsRecurring;
  final List<AirmiusSportRouteReference> sportRoutes;

  @override
  State<_CreateEventDialog> createState() => _CreateEventDialogState();
}

class _CreateEventDialogState extends State<_CreateEventDialog> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _locationController = TextEditingController();
  final _locationStreetController = TextEditingController();
  final _locationHouseNumberController = TextEditingController();
  final _locationPostalCodeController = TextEditingController();
  final _locationCityController = TextEditingController();
  final _locationCountryController = TextEditingController(text: 'DE');
  final _notesController = TextEditingController();
  final _maxParticipantsController = TextEditingController();
  int _step = 1;
  String _type = 'training';
  String _visibility = 'public';
  int? _clubId;
  int? _teamId;
  bool _usesPenaltyCatalog = false;
  int? _sportRouteId;
  String? _recurring;
  DateTime? _recurrenceEndsAt;
  final Set<int> _recurrenceDays = {};
  DateTime _start = DateTime.now().add(const Duration(hours: 1));
  DateTime? _end;
  DateTime? _reminderAt;

  AirmiusSportRouteReference? get _selectedSportRoute {
    for (final route in widget.sportRoutes) {
      if (route.id == _sportRouteId) return route;
    }

    return null;
  }

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
    _locationStreetController.dispose();
    _locationHouseNumberController.dispose();
    _locationPostalCodeController.dispose();
    _locationCityController.dispose();
    _locationCountryController.dispose();
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

  Future<void> _pickReminder() async {
    final next = await _pickDateTime(_reminderAt ?? _start.subtract(const Duration(hours: 1)));
    if (next == null) return;
    setState(() => _reminderAt = next);
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
      if (_reminderAt != null) 'reminder_at': _reminderAt!.toUtc().toIso8601String(),
      if (_sportRouteId != null) 'sport_route_id': _sportRouteId,
      if (_recurring != null) ...{
        'recurring': _recurring,
        'recurrence_ends_at': _recurrenceEndsAt!.toUtc().toIso8601String(),
        if (_recurring == 'weekly' || _recurring == 'biweekly')
          'recurrence_days': _recurrenceDays.toList()..sort(),
      },
      if (_locationController.text.trim().isNotEmpty)
        'location_name': _locationController.text.trim(),
      if (_locationStreetController.text.trim().isNotEmpty)
        'location_street': _locationStreetController.text.trim(),
      if (_locationHouseNumberController.text.trim().isNotEmpty)
        'location_house_number': _locationHouseNumberController.text.trim(),
      if (_locationPostalCodeController.text.trim().isNotEmpty)
        'location_postal_code': _locationPostalCodeController.text.trim(),
      if (_locationCityController.text.trim().isNotEmpty)
        'location_city': _locationCityController.text.trim(),
      if (_locationCountryController.text.trim().isNotEmpty)
        'location_country': _locationCountryController.text.trim().toUpperCase(),
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
        const SizedBox(height: 12),
        AirmiusButton(
          label: _reminderAt == null
              ? scope.t('events.reminder')
              : '${scope.t('events.reminder')}: ${_dateLabel(context, _reminderAt!)} ${_time(_reminderAt!)}',
          icon: Icons.notifications_active_outlined,
          onPressed: _pickReminder,
          secondary: true,
        ),
      ],
    );
  }

  List<Widget> _recurrenceFields() {
    final scope = AirmiusScope.of(context);
    if (!widget.allowsRecurring) return const [];
    return [
      const SizedBox(height: 18),
      Text(scope.t('events.recurring'), style: TextStyle(color: airmiusTextColor(context), fontSize: 16, fontWeight: FontWeight.w900)),
      const SizedBox(height: 4),
      Text(scope.t('events.recurringHint'), style: TextStyle(color: airmiusMutedColor(context), fontSize: 12, fontWeight: FontWeight.w700)),
      const SizedBox(height: 10),
      DropdownButtonFormField<String?>(
        initialValue: _recurring,
        decoration: InputDecoration(labelText: scope.t('events.repeat')),
        dropdownColor: airmiusSurfaceColor(context),
        items: [
          DropdownMenuItem<String?>(value: null, child: Text(scope.t('events.repeat.none'))),
          for (final value in const ['daily', 'weekly', 'biweekly', 'monthly'])
            DropdownMenuItem<String?>(value: value, child: Text(scope.t('events.repeat.$value'))),
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
            _recurrenceDays..clear()..add(_start.weekday % 7);
          }
        }),
      ),
      if (_recurring != null) ...[
        const SizedBox(height: 10),
        AirmiusButton(
          label: _recurrenceEndsAt == null ? scope.t('events.recurrenceEnd') : '${scope.t('events.recurrenceEnd')}: ${_dateLabel(context, _recurrenceEndsAt!)}',
          icon: Icons.event_repeat_outlined,
          onPressed: _pickRecurrenceEnd,
          secondary: true,
        ),
      ],
      if (_recurring == 'weekly' || _recurring == 'biweekly') ...[
        const SizedBox(height: 10),
        Text(scope.t('events.recurrenceDays'), style: TextStyle(color: airmiusTextColor(context), fontWeight: FontWeight.w900)),
        const SizedBox(height: 6),
        Wrap(
          spacing: 6,
          runSpacing: 6,
          children: [
            for (final day in List.generate(7, (index) => index))
              FilterChip(
                label: Text(scope.t('events.day.$day')),
                selected: _recurrenceDays.contains(day),
                onSelected: (selected) => setState(() => selected ? _recurrenceDays.add(day) : _recurrenceDays.remove(day)),
              ),
          ],
        ),
      ],
    ];
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
        ..._recurrenceFields(),
        const SizedBox(height: 16),
        DropdownButtonFormField<int?>(
          initialValue: _sportRouteId,
          decoration: InputDecoration(
            labelText: scope.t('events.route'),
            prefixIcon: const Icon(Icons.route_outlined),
          ),
          items: [
            DropdownMenuItem<int?>(
              value: null,
              child: Text(scope.t('events.routeNone')),
            ),
            ...widget.sportRoutes.map(
              (route) => DropdownMenuItem<int?>(
                value: route.id,
                child: Text(
                  route.distanceMeters == null
                      ? route.title
                      : '${route.title} · ${(route.distanceMeters! / 1000).toStringAsFixed(1)} km',
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ),
          ],
          onChanged: (value) => setState(() => _sportRouteId = value),
        ),
        const SizedBox(height: 6),
        Text(
          scope.t('events.routeShareHint'),
          style: TextStyle(
            color: airmiusMutedColor(context),
            fontSize: 12,
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
        Row(
          children: [
            Expanded(
              child: TextFormField(
                controller: _locationStreetController,
                decoration: InputDecoration(labelText: scope.t('events.locationStreet')),
              ),
            ),
            const SizedBox(width: 12),
            SizedBox(
              width: 92,
              child: TextFormField(
                controller: _locationHouseNumberController,
                decoration: InputDecoration(labelText: scope.t('events.locationHouseNumber')),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            SizedBox(
              width: 120,
              child: TextFormField(
                controller: _locationPostalCodeController,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(labelText: scope.t('events.locationPostalCode')),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: TextFormField(
                controller: _locationCityController,
                decoration: InputDecoration(labelText: scope.t('events.locationCity')),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        TextFormField(
          controller: _locationCountryController,
          textCapitalization: TextCapitalization.characters,
          maxLength: 2,
          decoration: InputDecoration(labelText: scope.t('events.locationCountry')),
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
              _ReviewLine(
                label: scope.t('events.reminder'),
                value: _reminderAt == null
                    ? '-'
                    : '${_dateLabel(context, _reminderAt!)} ${_time(_reminderAt!)}',
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
                label: scope.t('events.route'),
                value: _selectedSportRoute?.title ?? scope.t('events.routeNone'),
              ),
              _ReviewLine(
                label: scope.t('events.location'),
                value: _locationController.text.trim().isEmpty
                    ? '-'
                    : _locationController.text.trim(),
              ),
              _ReviewLine(
                label: scope.t('events.locationStreet'),
                value: _locationStreetController.text.trim().isEmpty
                    ? '-'
                    : '${_locationStreetController.text.trim()} ${_locationHouseNumberController.text.trim()}'.trim(),
              ),
              _ReviewLine(
                label: scope.t('events.locationCity'),
                value: _locationCityController.text.trim().isEmpty
                    ? '-'
                    : '${_locationPostalCodeController.text.trim()} ${_locationCityController.text.trim()}'.trim(),
              ),
              _ReviewLine(
                label: scope.t('events.locationCountry'),
                value: _locationCountryController.text.trim().isEmpty
                    ? '-'
                    : _locationCountryController.text.trim().toUpperCase(),
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
