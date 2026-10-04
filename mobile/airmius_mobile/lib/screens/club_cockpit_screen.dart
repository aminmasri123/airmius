import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_preferences.dart';
import '../core/airmius_services_scope.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';
import 'club_membership_management_screen.dart';
import 'club_announcement_screen.dart';
import 'club_jobs_screen.dart';
import 'club_request_inbox_screen.dart';
import 'club_reports_analytics_screen.dart';
import 'club_profile_editor_screen.dart';
import 'clubs_screen.dart';
import 'conversations_center_screen.dart';
import 'event_management_screen.dart';
import 'file_manager_screen.dart';
import 'teams_center_screen.dart';
import 'club_tasks_screen.dart';
import 'club_deletion_screen.dart';
import 'member_card_screen.dart';
import 'recruiting_pipeline_screen.dart';

class ClubCockpitScreen extends StatefulWidget {
  const ClubCockpitScreen({super.key, this.initialClubId, this.initialAction});

  final int? initialClubId;
  final String? initialAction;

  @override
  State<ClubCockpitScreen> createState() => _ClubCockpitScreenState();
}

class _ClubCockpitScreenState extends State<ClubCockpitScreen> {
  Future<_ClubCockpitData>? _future;
  int? _selectedClubId;
  bool _showAllOnboardingSteps = false;
  bool _showAdvanced = false;
  bool _openedInitialAction = false;
  String _startFocus = 'members';
  List<String> _quickActionIds = const ['members', 'teams', 'events'];
  final AirmiusPreferences _preferences = AirmiusPreferences();

  @override
  void initState() {
    super.initState();
    _selectedClubId = widget.initialClubId;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<_ClubCockpitData> _load() async {
    final services = AirmiusServicesScope.of(context);
    final page = await services.repositories.clubs.searchClubs(mine: true);
    final managed = page.items
        .where((club) => club.canViewCockpit)
        .map(ClubSummary.fromAirmiusClub)
        .toList();
    if (managed.isEmpty) return const _ClubCockpitData(clubs: []);

    var selected = managed.first;
    for (final club in managed) {
      if (club.id == _selectedClubId) {
        selected = club;
        break;
      }
    }
    _selectedClubId = selected.id;
    final detail = ClubSummary.fromAirmiusClub(
      await services.repositories.clubs.club(selected.id),
    );
    if (!detail.canViewCockpit) {
      throw const AirmiusApiException(
        statusCode: 403,
        body: '{"message":"club_management_forbidden"}',
        path: '/api/v1/clubs',
      );
    }
    final userId = services.authState.user?.id;
    if (userId != null) {
      _startFocus =
          await _preferences.readClubStartFocus(userId, selected.id) ??
          'members';
      _quickActionIds =
          await _preferences.readClubQuickActions(userId, selected.id) ??
          const ['members', 'teams', 'events'];
    }
    return _ClubCockpitData(clubs: managed, selected: detail);
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  void _selectClub(int id) {
    if (_selectedClubId == id) return;
    setState(() {
      _selectedClubId = id;
      _showAllOnboardingSteps = false;
      _showAdvanced = false;
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final user = AirmiusServicesScope.of(context).authState.user;
    final canCreateClub =
        user != null &&
        (user.can('org.create') ||
            user.can('clubs.create') ||
            user.hasAnyRole(const [
              'player',
              'youth_player',
              'minor_pending_consent',
              'minor_player',
              'guest_player',
            ]));
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('clubHub.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          if (canCreateClub)
            IconButton(
              tooltip: t('clubHub.createAnotherClub'),
              onPressed: _createClub,
              icon: const Icon(Icons.add_business_outlined),
            ),
          IconButton(
            tooltip: t('common.refresh'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<_ClubCockpitData>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final error = snapshot.error;
            final message = error is AirmiusApiException
                ? error.userMessage
                : t('clubHub.loadFailed');
            return _ClubHubFailure(message: message, onRetry: _reload);
          }
          final data = snapshot.data ?? const _ClubCockpitData(clubs: []);
          if (data.selected == null) {
            return _NoManagedClub(onOpenClubs: _openClubs);
          }
          final club = data.selected!;
          _openInitialActionIfNeeded(club);
          final gettingStarted = _gettingStarted(club);
          return PageFrame(
            title: t('clubHub.title'),
            subtitle: club.name,
            showHeader: false,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _clubPicker(data),
                if (club.deletionScheduledAt != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    '${t('clubDeletion.pending')}: ${DateFormat.yMMMd().format(club.deletionScheduledAt!.toLocal())}',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
                if (club.canDelete)
                  Align(
                    alignment: AlignmentDirectional.centerEnd,
                    child: TextButton.icon(
                      onPressed: () => _openDeletion(club),
                      icon: Icon(
                        club.deletionScheduledAt == null
                            ? Icons.delete_outline
                            : Icons.undo,
                      ),
                      label: Text(
                        t(
                          club.deletionScheduledAt == null
                              ? 'clubDeletion.title'
                              : 'clubDeletion.details',
                        ),
                      ),
                    ),
                  ),
                if (gettingStarted) ...[
                  _reviewStatus(club),
                  _onboarding(club),
                  _firstAction(club),
                  _today(club),
                  TextButton.icon(
                    onPressed: () =>
                        setState(() => _showAdvanced = !_showAdvanced),
                    icon: Icon(
                      _showAdvanced ? Icons.expand_less : Icons.expand_more,
                    ),
                    label: Text(
                      t(
                        _showAdvanced
                            ? 'clubHub.hideMoreAreas'
                            : 'clubHub.showMoreAreas',
                      ),
                    ),
                  ),
                  if (_showAdvanced) ...[
                    _hero(club),
                    const SizedBox(height: 12),
                    _quickActions(club),
                    const SizedBox(height: 12),
                    _managementAreas(club),
                    const SizedBox(height: 12),
                    _finance(club),
                  ],
                ] else ...[
                  _reviewStatus(club),
                  _hero(club),
                  const SizedBox(height: 12),
                  _today(club),
                  const SizedBox(height: 12),
                  _quickActions(club),
                  const SizedBox(height: 12),
                  TextButton.icon(
                    onPressed: () =>
                        setState(() => _showAdvanced = !_showAdvanced),
                    icon: Icon(
                      _showAdvanced ? Icons.expand_less : Icons.expand_more,
                    ),
                    label: Text(
                      t(
                        _showAdvanced
                            ? 'clubHub.hideMoreAreas'
                            : 'clubHub.showMoreAreas',
                      ),
                    ),
                  ),
                  if (_showAdvanced) ...[
                    _managementAreas(club),
                    const SizedBox(height: 12),
                    _finance(club),
                    const SizedBox(height: 12),
                    _onboarding(club),
                  ],
                ],
              ],
            ),
          );
        },
      ),
    );
  }

  void _openInitialActionIfNeeded(ClubSummary club) {
    if (_openedInitialAction || widget.initialAction == null) return;
    _openedInitialAction = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      switch (widget.initialAction) {
        case 'teams':
          _openTeams(club);
        case 'todos':
          _open(ClubTasksScreen(clubId: club.id));
        case 'calendar':
          _openClubCalendar(club);
      }
    });
  }

  bool _gettingStarted(ClubSummary club) {
    final onboarding = club.management?.onboarding;
    final steps = onboarding?['steps'];
    if (steps is List &&
        steps.any(
          (step) =>
              step is Map &&
              const {'profile', 'members', 'team'}.contains(step['key']),
        )) {
      return steps.any(
        (step) =>
            step is Map &&
            const {'profile', 'members', 'team'}.contains(step['key']) &&
            step['done'] != true,
      );
    }
    return club.members <= 1 && club.teams == 0 && club.posts == 0;
  }

  Widget _reviewStatus(ClubSummary club) {
    final status = club.verificationStatus;
    if (status == null || status == 'verified') return const SizedBox.shrink();
    final t = AirmiusScope.of(context).t;
    final rejected = status == 'rejected';
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: AirmiusPanel(
        child: ExpansionTile(
          tilePadding: EdgeInsets.zero,
          childrenPadding: EdgeInsets.zero,
          leading: Icon(
            rejected ? Icons.info_outline : Icons.hourglass_top_outlined,
          ),
          title: Text(
            t(rejected ? 'clubHub.reviewRejected' : 'clubHub.reviewPending'),
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
          subtitle: Text(t('clubHub.reviewTapForDetails')),
          children: [
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text(
                t(
                  rejected
                      ? 'clubHub.reviewRejectedBody'
                      : 'clubHub.reviewPendingBody',
                ),
              ),
            ),
            if (rejected)
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: TextButton(
                  onPressed: () => _openProfile(club),
                  child: Text(t('clubHub.clubProfile')),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _firstAction(ClubSummary club) {
    final t = AirmiusScope.of(context).t;
    final choices = <(String, String)>[
      ('members', t('clubHub.start.members')),
      ('single_team', t('clubHub.start.singleTeam')),
      ('multiple_teams', t('clubHub.start.multipleTeams')),
    ];
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              t('clubHub.start.title'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 5),
            Text(t('clubHub.start.body')),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              children: choices
                  .map(
                    (choice) => ChoiceChip(
                      label: Text(choice.$2),
                      selected: _startFocus == choice.$1,
                      onSelected: (_) {
                        setState(() => _startFocus = choice.$1);
                        final userId = AirmiusServicesScope.of(
                          context,
                        ).authState.user?.id;
                        if (userId != null) {
                          _preferences.writeClubStartFocus(
                            userId,
                            club.id,
                            choice.$1,
                          );
                        }
                      },
                    ),
                  )
                  .toList(),
            ),
            const SizedBox(height: 10),
            FilledButton.icon(
              onPressed: () => switch (_startFocus) {
                'single_team' => _open(
                  TeamsCenterScreen(
                    initialClubId: club.id,
                    openCreateOnStart: true,
                  ),
                ),
                'multiple_teams' => _open(
                  TeamsCenterScreen(initialClubId: club.id),
                ),
                _ => _open(
                  ClubMembershipManagementScreen(
                    initialClubId: club.id,
                    initialSection: 'invite',
                  ),
                ),
              },
              icon: const Icon(Icons.arrow_forward_outlined),
              label: Text(t('clubHub.start.continue')),
            ),
          ],
        ),
      ),
    );
  }

  Widget _clubPicker(_ClubCockpitData data) {
    final t = AirmiusScope.of(context).t;
    if (data.clubs.length < 2) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Eyebrow(t('clubHub.chooseClub')),
        const SizedBox(height: 8),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: data.clubs
                .map(
                  (club) => Padding(
                    padding: const EdgeInsetsDirectional.only(end: 8),
                    child: ChoiceChip(
                      selected: club.id == data.selected?.id,
                      label: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 7),
                        child: Text(club.name),
                      ),
                      onSelected: (_) => _selectClub(club.id),
                    ),
                  ),
                )
                .toList(),
          ),
        ),
      ],
    );
  }

  Widget _hero(ClubSummary club) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final management = club.management;
    final subscription = management?.subscription ?? const <String, dynamic>{};
    final plan = subscription['plan'] is JsonMap
        ? subscription['plan'] as JsonMap
        : const <String, dynamic>{};
    final occupied = _clubInt(
      subscription['member_usage'],
      fallback: management?.activeMembersCount ?? club.members,
    );
    return AirmiusPanel(
      gradient: false,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _ClubIdentity(club: club),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      club.name,
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      [
                        club.city,
                        _sportName(t, club.sportType),
                      ].where((value) => value.trim().isNotEmpty).join(' · '),
                    ),
                  ],
                ),
              ),
              if (club.verified)
                StatusPill(
                  t('clubHub.verified'),
                  color: theme.colorScheme.secondary,
                ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              _CompactClubMetric(
                icon: Icons.people_alt_outlined,
                value: '${club.members}',
                label: t('clubHub.totalMembers'),
              ),
              _CompactClubMetric(
                icon: Icons.groups_2_outlined,
                value: '${club.teams}',
                label: t('clubHub.clubTeams'),
              ),
              _CompactClubMetric(
                icon: Icons.mark_email_unread_outlined,
                value: '${club.pendingMembershipRequests}',
                label: t('clubHub.requests'),
              ),
            ],
          ),
          ExpansionTile(
            dense: true,
            tilePadding: EdgeInsets.zero,
            childrenPadding: EdgeInsets.zero,
            title: Text(
              '${t('clubHub.plan')}: ${plan['name'] ?? t('clubHub.freePlan')}',
              style: theme.textTheme.bodySmall,
            ),
            children: [
              Text(
                '${t('clubHub.memberPlaces')}: '
                '${_clubUsage(occupied, _clubNullableInt(subscription['member_limit']), t('clubHub.unlimited'))}\n'
                '${t('clubHub.memberPlacesHelp')}\n'
                '${t('clubHub.teamPlaces')}: '
                '${_clubUsage(club.teams, _clubNullableInt(subscription['team_limit']), t('clubHub.unlimited'))}\n'
                '${t('clubHub.storage')}: '
                '${_clubStorageUsage(subscription, AirmiusScope.of(context).language.locale.toLanguageTag(), t)}',
                style: theme.textTheme.bodySmall,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _quickActions(ClubSummary club) {
    final t = AirmiusScope.of(context).t;
    final actions = <String, ({IconData icon, String label, VoidCallback run})>{
      'members': (
        icon: Icons.person_add_alt_1_outlined,
        label: t('clubHub.inviteMember'),
        run: () => _open(
          ClubMembershipManagementScreen(
            initialClubId: club.id,
            initialSection: 'invite',
          ),
        ),
      ),
      'teams': (
        icon: Icons.group_add_outlined,
        label: t('clubHub.createTeam'),
        run: () => _open(
          TeamsCenterScreen(initialClubId: club.id, openCreateOnStart: true),
        ),
      ),
      'addMember': (
        icon: Icons.person_add_outlined,
        label: t('membership.addMember'),
        run: () => _open(
          ClubMembershipManagementScreen(
            initialClubId: club.id,
            initialSection: 'invite',
          ),
        ),
      ),
      'calendar': (
        icon: Icons.calendar_month_outlined,
        label: t('clubHub.openCalendar'),
        run: () => _openClubCalendar(club),
      ),
      'todos': (
        icon: Icons.checklist_outlined,
        label: t('clubTasks.title'),
        run: () => _open(ClubTasksScreen(clubId: club.id)),
      ),
      'events': (
        icon: Icons.event_available_outlined,
        label: t('clubHub.planEvent'),
        run: () => _open(
          EventManagementScreen(
            initialClubId: club.id,
            openCreateOnStart: true,
            initialCreateType: 'meeting',
          ),
        ),
      ),
      'announcements': (
        icon: Icons.campaign_outlined,
        label: t('clubHub.clubNews'),
        run: () => _openFeed(club),
      ),
      'finance': (
        icon: Icons.account_balance_wallet_outlined,
        label: t('clubHub.finance'),
        run: () => _open(
          ClubMembershipManagementScreen(
            initialClubId: club.id,
            initialSection: 'payments',
          ),
        ),
      ),
      'documents': (
        icon: Icons.folder_outlined,
        label: t('clubHub.documents'),
        run: () => _openFiles(club),
      ),
      if (club.canViewRecruiting || club.canEditJobs)
        'recruiting': (
          icon: Icons.work_outline,
          label: club.canEditJobs
              ? 'Jobs & Bewerbungen'
              : t('recruitingPipeline.title'),
          run: () => _openRecruitingArea(club),
        ),
    };
    final ordered = [..._quickActionIds];
    if (club.pendingMembershipRequests > 0) {
      ordered.remove('members');
      ordered.insert(0, 'members');
    } else if ((club.management?.openInvoicesCount ?? 0) > 0) {
      ordered.remove('finance');
      ordered.insert(0, 'finance');
    }
    if (ordered.length > 5) ordered.removeRange(5, ordered.length);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                t('clubHub.quickActions'),
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
              ),
            ),
            TextButton.icon(
              onPressed: () => _configureQuickActions(club, actions),
              icon: const Icon(Icons.tune_outlined, size: 18),
              label: Text(t('clubHub.customizeActions')),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            if (club.canManageMembers)
              OutlinedButton.icon(
                onPressed: () => _open(
                  MemberCardScreen(initialClubId: club.id, startScanner: true),
                ),
                icon: const Icon(Icons.qr_code_scanner_outlined),
                label: Text(t('memberCard.scanTitle')),
              ),
            ...ordered.indexed.map((entry) {
              final action = actions[entry.$2]!;
              return entry.$1 == 0
                  ? FilledButton.icon(
                      onPressed: action.run,
                      icon: Icon(action.icon),
                      label: Text(action.label),
                    )
                  : OutlinedButton.icon(
                      onPressed: action.run,
                      icon: Icon(action.icon),
                      label: Text(action.label),
                    );
            }),
          ],
        ),
      ],
    );
  }

  Future<void> _configureQuickActions(
    ClubSummary club,
    Map<String, ({IconData icon, String label, VoidCallback run})> actions,
  ) async {
    final selected = [..._quickActionIds];
    final t = AirmiusScope.of(context).t;
    final result = await showModalBottomSheet<List<String>>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  t('clubHub.customizeActionsTitle'),
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                Text(t('clubHub.customizeActionsBody')),
                const SizedBox(height: 12),
                for (final entry in actions.entries)
                  CheckboxListTile(
                    value: selected.contains(entry.key),
                    secondary: Icon(entry.value.icon),
                    title: Text(entry.value.label),
                    onChanged: (checked) => setSheetState(() {
                      if (checked == true) {
                        if (selected.contains(entry.key)) return;
                        if (selected.length < 5) selected.add(entry.key);
                      } else if (checked == false && selected.length > 1) {
                        selected.remove(entry.key);
                      }
                    }),
                  ),
                const SizedBox(height: 8),
                FilledButton(
                  onPressed: selected.isNotEmpty && selected.length <= 5
                      ? () => Navigator.pop(sheetContext, selected)
                      : null,
                  child: Text(t('common.save')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    if (result == null || !mounted) return;
    setState(() => _quickActionIds = result);
    final userId = AirmiusServicesScope.of(context).authState.user?.id;
    if (userId != null) {
      await _preferences.writeClubQuickActions(userId, club.id, result);
    }
  }

  Widget _today(ClubSummary club) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final management = club.management;
    final items = <_ClubAttention>[
      if (club.pendingMembershipRequests > 0)
        _ClubAttention(
          icon: Icons.person_add_alt_outlined,
          title: t('clubHub.pendingApplications'),
          value: '${club.pendingMembershipRequests}',
          color: theme.colorScheme.tertiary,
          action: () => _open(ClubRequestInboxScreen(initialClubId: club.id)),
        ),
      if (club.pendingTeamJoinRequests > 0)
        _ClubAttention(
          icon: Icons.group_add_outlined,
          title: t('clubHub.pendingTeamRequests'),
          value: '${club.pendingTeamJoinRequests}',
          color: theme.colorScheme.tertiary,
          action: () => _openTeams(club),
        ),
      if (management?.nextEventId != null)
        _ClubAttention(
          icon: Icons.event_outlined,
          title: management?.nextEventTitle ?? t('clubHub.nextEvent'),
          value: management?.nextEventStartsAt == null
              ? t('clubHub.nextEvent')
              : DateFormat.MMMd(
                  Localizations.localeOf(context).toLanguageTag(),
                ).add_Hm().format(management!.nextEventStartsAt!.toLocal()),
          color: theme.colorScheme.primary,
          action: () => _openEvents(club),
        ),
      if ((management?.unreadAnnouncementsCount ?? 0) > 0)
        _ClubAttention(
          icon: Icons.campaign_outlined,
          title: t('clubHub.unreadAnnouncements'),
          value: '${management?.unreadAnnouncementsCount ?? 0}',
          color: theme.colorScheme.secondary,
          action: () => _openFeed(club),
        ),
      if ((management?.openInvoicesCount ?? 0) > 0)
        _ClubAttention(
          icon: Icons.account_balance_wallet_outlined,
          title: t('clubHub.openPayments'),
          value: _money(context, management?.openInvoiceAmount ?? 0),
          color: theme.colorScheme.error,
          action: () => _open(
            ClubMembershipManagementScreen(
              initialClubId: club.id,
              initialSection: 'payments',
            ),
          ),
        ),
    ];
    if (items.isEmpty) {
      return AirmiusPanel(
        onTap: () => _open(
          ClubMembershipManagementScreen(
            initialClubId: club.id,
            initialSection: 'payments',
          ),
        ),
        child: ListTile(
          contentPadding: EdgeInsets.zero,
          leading: Icon(
            Icons.task_alt_outlined,
            color: theme.colorScheme.primary,
          ),
          title: Text(
            t('clubHub.noOpenTasks'),
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
          subtitle: Text(t('clubHub.noOpenTasksBody')),
          trailing: const Icon(Icons.chevron_right),
        ),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _heading(
          Icons.bolt_outlined,
          t('clubHub.today'),
          t('clubHub.todayBody'),
        ),
        const SizedBox(height: 10),
        AirmiusPanel(
          child: Column(
            children: items.map((item) => _AttentionLine(item: item)).toList(),
          ),
        ),
      ],
    );
  }

  Widget _onboarding(ClubSummary club) {
    final onboarding = club.management?.onboarding ?? const <String, dynamic>{};
    if (onboarding.isEmpty) return const SizedBox.shrink();
    final rawSteps = onboarding['steps'];
    final allSteps = rawSteps is List
        ? rawSteps.whereType<Map<String, dynamic>>().toList()
        : const <Map<String, dynamic>>[];
    final verificationPending =
        club.verificationStatus != null &&
        club.verificationStatus != 'verified' &&
        club.verificationStatus != 'rejected';
    final steps = verificationPending
        ? allSteps
              .where(
                (step) =>
                    step['key'] != 'verification' &&
                    step['action'] != 'verification',
              )
              .toList()
        : allSteps;
    final fullPercent = switch (onboarding['completion_percent']) {
      final num value => value.toDouble().clamp(0, 100),
      _ => 0.0,
    };
    final gettingStarted = _gettingStarted(club);
    final allStarterSteps = steps
        .where(
          (step) => const {'profile', 'members', 'team'}.contains(step['key']),
        )
        .toList();
    final completedStarterSteps = allStarterSteps
        .where((step) => step['done'] == true)
        .length;
    final percent = gettingStarted && allStarterSteps.isNotEmpty
        ? completedStarterSteps * 100 / allStarterSteps.length
        : fullPercent;
    final openSteps = steps.where((step) => step['done'] != true).toList();
    final starterSteps = openSteps
        .where(
          (step) => const {'profile', 'members', 'team'}.contains(step['key']),
        )
        .toList();
    final visibleSteps = _showAllOnboardingSteps
        ? steps
        : (starterSteps.isNotEmpty ? starterSteps : openSteps)
              .take(gettingStarted ? 3 : 1)
              .toList();
    final t = AirmiusScope.of(context).t;
    final progressLabel = gettingStarted && allStarterSteps.isNotEmpty
        ? t('clubHub.start.progress')
              .replaceAll('{done}', '$completedStarterSteps')
              .replaceAll('{total}', '${allStarterSteps.length}')
        : '${onboarding['progress_label'] ?? ''}';
    final theme = Theme.of(context);

    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.rocket_launch_outlined,
                color: theme.colorScheme.primary,
                size: 28,
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      gettingStarted
                          ? t('clubHub.start.guideTitle')
                          : '${onboarding['title'] ?? ''}',
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      gettingStarted
                          ? t('clubHub.start.guideBody')
                          : '${onboarding['subtitle'] ?? ''}',
                    ),
                  ],
                ),
              ),
              StatusPill(
                '${percent.round()}%',
                color: percent >= 100
                    ? theme.colorScheme.secondary
                    : theme.colorScheme.primary,
              ),
            ],
          ),
          const SizedBox(height: 13),
          Semantics(
            label: progressLabel,
            value: '${percent.round()}%',
            child: LinearProgressIndicator(
              value: percent / 100,
              minHeight: 8,
              borderRadius: BorderRadius.circular(99),
            ),
          ),
          const SizedBox(height: 7),
          Text(progressLabel, style: theme.textTheme.bodySmall),
          const SizedBox(height: 12),
          ...visibleSteps.asMap().entries.map((entry) {
            final step = entry.value;
            final done = step['done'] == true;
            return Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: Material(
                color: theme.colorScheme.surface.withValues(alpha: 0.55),
                borderRadius: BorderRadius.circular(15),
                child: InkWell(
                  borderRadius: BorderRadius.circular(15),
                  onTap: done
                      ? null
                      : () => _openOnboardingAction(
                          club,
                          '${step['action'] ?? ''}',
                        ),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          done
                              ? Icons.check_circle_outline
                              : Icons.radio_button_unchecked,
                          color: done
                              ? theme.colorScheme.secondary
                              : theme.colorScheme.primary,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${step['title'] ?? ''}',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              if (!done && !_showAllOnboardingSteps)
                                Text(
                                  entry.key == 0
                                      ? t('clubHub.nextStep')
                                      : t('clubHub.afterThat'),
                                  style: theme.textTheme.labelSmall?.copyWith(
                                    color: theme.colorScheme.primary,
                                  ),
                                ),
                              const SizedBox(height: 3),
                              Text(
                                '${step['description'] ?? ''}',
                                style: theme.textTheme.bodySmall,
                              ),
                              if (!done) ...[
                                const SizedBox(height: 6),
                                Text(
                                  '${step['action_label'] ?? ''}',
                                  style: theme.textTheme.labelLarge?.copyWith(
                                    color: theme.colorScheme.primary,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                        if (!done) const Icon(Icons.chevron_right),
                      ],
                    ),
                  ),
                ),
              ),
            );
          }),
          if (steps.length > visibleSteps.length)
            TextButton.icon(
              onPressed: () => setState(() => _showAllOnboardingSteps = true),
              icon: const Icon(Icons.expand_more),
              label: Text(t('clubHub.showAllSteps')),
            ),
          if (_showAllOnboardingSteps && steps.length > 2)
            TextButton.icon(
              onPressed: () => setState(() => _showAllOnboardingSteps = false),
              icon: const Icon(Icons.expand_less),
              label: Text(t('clubHub.showLess')),
            ),
        ],
      ),
    );
  }

  Widget _managementAreas(ClubSummary club) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final areas = [
      _ClubArea(
        icon: Icons.apartment_outlined,
        title: t('clubHub.clubProfile'),
        body: t('clubHub.clubProfileBody'),
        color: theme.colorScheme.primary,
        onTap: () => _openProfile(club),
      ),
      _ClubArea(
        icon: Icons.people_alt_outlined,
        title: t('clubHub.membersAndFees'),
        body: t('clubHub.membersAndFeesBody'),
        color: theme.colorScheme.secondary,
        onTap: () => _openMembership(club),
      ),
      _ClubArea(
        icon: Icons.folder_outlined,
        title: t('clubHub.documents'),
        body: t('clubHub.documentsBody'),
        color: theme.colorScheme.tertiary,
        onTap: () => _openFiles(club),
      ),
      _ClubArea(
        icon: Icons.insights_outlined,
        title: t('clubReports.title'),
        body: t('clubReports.currentSnapshot'),
        color: theme.colorScheme.secondary,
        onTap: () => _open(ClubReportsAnalyticsScreen(club: club)),
      ),
      _ClubArea(
        icon: Icons.dynamic_feed_outlined,
        title: t('clubHub.clubNews'),
        body: '${club.name} · ${t('clubHub.clubNewsBody')}',
        color: theme.colorScheme.primary,
        onTap: () => _openFeed(club),
      ),
      _ClubArea(
        icon: Icons.forum_outlined,
        title: t('clubHub.communication'),
        body: t('clubHub.communicationBody'),
        color: theme.colorScheme.secondary,
        onTap: _openMessages,
      ),
      if (club.canViewRecruiting || club.canEditJobs)
        _ClubArea(
          icon: Icons.work_outline,
          title: club.canEditJobs
              ? 'Jobs & Bewerbungen'
              : t('recruitingPipeline.title'),
          body: club.canEditJobs
              ? 'Stellen erstellen, veröffentlichen und Bewerbungen prüfen.'
              : t('recruitingPipeline.subtitle'),
          color: theme.colorScheme.tertiary,
          onTap: () => _openRecruitingArea(club),
        ),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final group in <(String, List<_ClubArea>)>[
          (
            t('clubHub.group.organization'),
            [areas[0], areas[1], areas[2], areas[3]],
          ),
          (
            t('clubHub.group.communication'),
            [areas[4], areas[5], if (areas.length > 6) areas[6]],
          ),
        ]) ...[
          Padding(
            padding: const EdgeInsets.only(top: 10, bottom: 6),
            child: Eyebrow(group.$1),
          ),
          _areaGrid(group.$2),
        ],
      ],
    );
  }

  Widget _areaGrid(List<_ClubArea> areas) => Column(
    children: [
      for (var index = 0; index < areas.length; index++) ...[
        _ClubAreaCard(area: areas[index], compact: true),
        if (index < areas.length - 1) const SizedBox(height: 8),
      ],
    ],
  );

  Widget _finance(ClubSummary club) {
    final t = AirmiusScope.of(context).t;
    final management = club.management;
    if (management == null) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          onTap: () => _open(
            ClubMembershipManagementScreen(
              initialClubId: club.id,
              initialSection: 'payments',
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(
                    Icons.account_balance_outlined,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      t('clubHub.finance'),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                  ),
                  const Icon(Icons.chevron_right),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: _CompactFinanceValue(
                      value: '${management.openInvoicesCount}',
                      label: t('clubHub.openInvoices'),
                    ),
                  ),
                  Expanded(
                    child: _CompactFinanceValue(
                      value: _money(context, management.openInvoiceAmount),
                      label: t('clubReports.openAmount'),
                    ),
                  ),
                  Expanded(
                    child: _CompactFinanceValue(
                      value: _money(
                        context,
                        management.incomePeriodTotal -
                            management.expensePeriodTotal,
                      ),
                      label: t('clubReports.periodResult'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _heading(IconData icon, String title, String body) {
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

  Future<void> _openMembership(ClubSummary club) =>
      _open(ClubMembershipManagementScreen(initialClubId: club.id));

  Future<void> _openProfile(ClubSummary club) =>
      _open(ClubProfileEditorScreen(initialClubId: club.id));

  Future<void> _openTeams(ClubSummary club) =>
      _open(TeamsCenterScreen(initialClubId: club.id));
  Future<void> _openFiles(ClubSummary club) =>
      _open(FileManagerScreen(initialScope: 'club', initialClubId: club.id));
  Future<void> _openFeed(ClubSummary club) => _open(
    Scaffold(
      appBar: AppBar(
        title: Text(AirmiusScope.of(context).t('clubHub.clubNews')),
      ),
      body: PageFrame(
        title: club.name,
        subtitle: AirmiusScope.of(context).t('clubHub.clubNewsBody'),
        child: ClubAnnouncementScreen(club: club),
      ),
    ),
  );
  Future<void> _openMessages() => _open(const ConversationsCenterScreen());
  Future<void> _openClubCalendar(ClubSummary club) =>
      _open(ClubTasksScreen(clubId: club.id, initialCalendar: true));
  Future<void> _openEvents(ClubSummary club) =>
      _open(EventManagementScreen(initialClubId: club.id));

  Future<void> _openRecruitingArea(ClubSummary club) {
    if (club.canEditJobs) {
      return _open(ClubJobsScreen(clubId: club.id, clubName: club.name));
    }
    return _open(const RecruitingPipelineScreen());
  }

  Future<void> _openOnboardingAction(ClubSummary club, String action) =>
      switch (action) {
        'profile' || 'verification' => _openProfile(club),
        'roles' || 'memberships' || 'members' => _openMembership(club),
        'teams' => _openTeams(club),
        'events' => _openEvents(club),
        'feed' => _openFeed(club),
        'files' => _openFiles(club),
        _ => Future<void>.value(),
      };
  Future<void> _createClub() async {
    final created = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => const ClubCreateWizardScreen(),
      ),
    );
    if (!mounted || created != true) return;
    _reload();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(AirmiusScope.of(context).t('clubs.setupSubmitted')),
      ),
    );
  }

  Future<void> _openDeletion(ClubSummary club) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) =>
            ClubDeletionScreen(clubId: club.id, clubName: club.name),
      ),
    );
    if (mounted) _reload();
  }

  Future<void> _openClubs() => _open(
    ClubsScreen(
      requestedClubIds: const {},
      onRequestClub: (_) {},
      onWithdrawClub: (_) {},
    ),
  );

  Future<void> _open(Widget screen) async {
    await Navigator.push(context, MaterialPageRoute(builder: (_) => screen));
    if (mounted) _reload();
  }
}

class _ClubIdentity extends StatelessWidget {
  const _ClubIdentity({required this.club});

  final ClubSummary club;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final url = resolveAirmiusImageUrl(club.logoUrl);
    return Container(
      width: 58,
      height: 58,
      decoration: BoxDecoration(
        color: theme.colorScheme.primary.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: theme.dividerColor),
      ),
      clipBehavior: Clip.antiAlias,
      child: url != null && url.isNotEmpty
          ? Image.network(
              url,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => _ClubInitial(name: club.name),
            )
          : _ClubInitial(name: club.name),
    );
  }
}

class _ClubInitial extends StatelessWidget {
  const _ClubInitial({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) => Center(
    child: Text(
      name.trim().isEmpty ? '?' : name.trim().substring(0, 1).toUpperCase(),
      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
        color: Theme.of(context).colorScheme.primary,
        fontWeight: FontWeight.w900,
      ),
    ),
  );
}

class _CompactClubMetric extends StatelessWidget {
  const _CompactClubMetric({
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
    return Expanded(
      child: Padding(
        padding: const EdgeInsetsDirectional.only(end: 4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 20, color: theme.colorScheme.primary),
            Text(
              value,
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w900,
              ),
            ),
            Text(
              label,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}

class _ClubAttention {
  const _ClubAttention({
    required this.icon,
    required this.title,
    required this.value,
    required this.color,
    required this.action,
  });

  final IconData icon;
  final String title;
  final String value;
  final Color color;
  final VoidCallback action;
}

class _AttentionLine extends StatelessWidget {
  const _AttentionLine({required this.item});

  final _ClubAttention item;

  @override
  Widget build(BuildContext context) => Material(
    type: MaterialType.transparency,
    child: ListTile(
      minTileHeight: 62,
      contentPadding: EdgeInsets.zero,
      leading: Icon(item.icon, color: item.color),
      title: Text(
        item.title,
        style: const TextStyle(fontWeight: FontWeight.w800),
      ),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          StatusPill(item.value, color: item.color),
          const SizedBox(width: 4),
          const Icon(Icons.chevron_right),
        ],
      ),
      onTap: item.action,
    ),
  );
}

class _ClubArea {
  const _ClubArea({
    required this.icon,
    required this.title,
    required this.body,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String body;
  final Color color;
  final VoidCallback onTap;
}

class _ClubAreaCard extends StatelessWidget {
  const _ClubAreaCard({required this.area, required this.compact});

  final _ClubArea area;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AirmiusPanel(
      onTap: area.onTap,
      borderColor: area.color.withValues(alpha: 0.4),
      child: compact
          ? Row(
              children: [
                Icon(area.icon, color: area.color, size: 24),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    area.title,
                    style: theme.textTheme.bodyLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                const Icon(Icons.chevron_right, size: 22),
              ],
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Icon(area.icon, color: area.color, size: 28),
                    const Spacer(),
                    const Icon(Icons.arrow_forward_outlined, size: 20),
                  ],
                ),
                const SizedBox(height: 7),
                Text(
                  area.title,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(area.body),
              ],
            ),
    );
  }
}

class _CompactFinanceValue extends StatelessWidget {
  const _CompactFinanceValue({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        value,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: Theme.of(
          context,
        ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
      ),
      Text(
        label,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: Theme.of(context).textTheme.bodySmall,
      ),
    ],
  );
}

class _NoManagedClub extends StatelessWidget {
  const _NoManagedClub({required this.onOpenClubs});

  final VoidCallback onOpenClubs;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: AirmiusPanel(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.admin_panel_settings_outlined,
                color: Theme.of(context).colorScheme.primary,
                size: 48,
              ),
              const SizedBox(height: 12),
              Text(
                t('clubHub.noManagedClub'),
                textAlign: TextAlign.center,
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 7),
              Text(t('clubHub.noManagedClubBody'), textAlign: TextAlign.center),
              const SizedBox(height: 15),
              AirmiusButton(
                label: t('clubHub.openClubs'),
                icon: Icons.search_outlined,
                onPressed: onOpenClubs,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ClubHubFailure extends StatelessWidget {
  const _ClubHubFailure({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: AirmiusPanel(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.lock_person_outlined,
                color: Theme.of(context).colorScheme.error,
                size: 46,
              ),
              const SizedBox(height: 11),
              Text(
                t('clubHub.loadFailed'),
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 7),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 14),
              AirmiusButton(
                label: t('common.retry'),
                icon: Icons.refresh_outlined,
                onPressed: onRetry,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ClubCockpitData {
  const _ClubCockpitData({required this.clubs, this.selected});

  final List<ClubSummary> clubs;
  final ClubSummary? selected;
}

String _money(BuildContext context, double value) {
  final locale = Localizations.localeOf(context).toLanguageTag();
  return NumberFormat.simpleCurrency(locale: locale, name: 'EUR').format(value);
}

int _clubInt(Object? value, {int fallback = 0}) => switch (value) {
  int number => number,
  num number => number.toInt(),
  String text => int.tryParse(text) ?? fallback,
  _ => fallback,
};

int? _clubNullableInt(Object? value) {
  if (value == null) return null;
  final parsed = _clubInt(value, fallback: -1);
  return parsed < 0 ? null : parsed;
}

String _clubUsage(int used, int? limit, String unlimited) =>
    limit == null || limit <= 0 ? '$used / $unlimited' : '$used / $limit';

String _clubStorageUsage(
  Map<String, dynamic> subscription,
  String locale,
  String Function(String) t,
) {
  final used = _clubNullableInt(subscription['storage_bytes']);
  final limit = _clubNullableInt(subscription['storage_gb']);
  final number = NumberFormat('#,##0.##', locale);
  String usedLabel = t('clubHub.unavailable');
  if (used != null) {
    // The server enforces each storage_gb as 1024³ bytes.
    const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    var value = used.toDouble();
    var unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
      value /= 1024;
      unit++;
    }
    usedLabel = '${number.format(value)} ${units[unit]}';
  }
  final unlimited =
      subscription.containsKey('storage_gb') &&
      (subscription['storage_gb'] == null || limit == 0);
  final limitLabel = unlimited
      ? t('clubHub.unlimited')
      : limit == null
      ? t('clubHub.unavailable')
      : '${number.format(limit)} GiB';
  return '$usedLabel / $limitLabel';
}

String _sportName(String Function(String) t, String? sport) {
  final value = (sport ?? '').trim();
  if (value.isEmpty) return '';
  if (value.toLowerCase() == 'strassenlauf') {
    return t('clubHub.sport.strassenlauf');
  }
  final words = value.replaceAll('_', ' ').replaceAll('-', ' ').split(' ');
  return words
      .where((word) => word.isNotEmpty)
      .map((word) => '${word[0].toUpperCase()}${word.substring(1)}')
      .join(' ');
}
