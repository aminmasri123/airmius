import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';
import 'club_membership_management_screen.dart';
import 'clubs_screen.dart';
import 'conversations_center_screen.dart';
import 'event_management_screen.dart';
import 'feed_center_screen.dart';
import 'file_manager_screen.dart';
import 'teams_center_screen.dart';

class ClubCockpitScreen extends StatefulWidget {
  const ClubCockpitScreen({super.key});

  @override
  State<ClubCockpitScreen> createState() => _ClubCockpitScreenState();
}

class _ClubCockpitScreenState extends State<ClubCockpitScreen> {
  Future<_ClubCockpitData>? _future;
  int? _selectedClubId;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<_ClubCockpitData> _load() async {
    final services = AirmiusServicesScope.of(context);
    final userId = services.authState.user?.id;
    final page = await services.repositories.clubs.searchClubs(mine: true);
    final managed = page.items
        .where((club) => club.canManage || club.ownerId == userId)
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
    if (!detail.canManage && detail.ownerId != userId) {
      throw const AirmiusApiException(
        statusCode: 403,
        body: '{"message":"club_management_forbidden"}',
        path: '/api/v1/clubs',
      );
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
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('clubHub.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
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
          return PageFrame(
            title: t('clubHub.title'),
            subtitle: t('clubHub.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _clubPicker(data),
                const SizedBox(height: 12),
                _hero(club),
                const SizedBox(height: 16),
                _onboarding(club),
                const SizedBox(height: 16),
                _today(club),
                const SizedBox(height: 16),
                _managementAreas(club),
                const SizedBox(height: 16),
                _members(club),
                const SizedBox(height: 16),
                _finance(club),
              ],
            ),
          );
        },
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
    return AirmiusPanel(
      gradient: true,
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
                    Eyebrow(t('clubHub.eyebrow')),
                    const SizedBox(height: 5),
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
                        club.sportType ?? '',
                      ].where((value) => value.trim().isNotEmpty).join(' · '),
                    ),
                  ],
                ),
              ),
              StatusPill(
                club.verified ? t('clubHub.verified') : t('clubHub.managed'),
                color: club.verified
                    ? theme.colorScheme.secondary
                    : theme.colorScheme.primary,
              ),
            ],
          ),
          const SizedBox(height: 17),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _ClubHubMetric(
                icon: Icons.people_alt_outlined,
                value: '${management?.activeMembersCount ?? club.members}',
                label: t('clubHub.members'),
              ),
              _ClubHubMetric(
                icon: Icons.groups_2_outlined,
                value: '${club.teams}',
                label: t('clubHub.teams'),
              ),
              _ClubHubMetric(
                icon: Icons.mark_email_unread_outlined,
                value: '${club.pendingMembershipRequests}',
                label: t('clubHub.requests'),
              ),
              _ClubHubMetric(
                icon: Icons.receipt_long_outlined,
                value: '${management?.openInvoicesCount ?? 0}',
                label: t('clubHub.openInvoices'),
              ),
            ],
          ),
        ],
      ),
    );
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
          action: () => _openMembership(club),
        ),
      if (club.pendingTeamJoinRequests > 0)
        _ClubAttention(
          icon: Icons.group_add_outlined,
          title: t('clubHub.pendingTeamRequests'),
          value: '${club.pendingTeamJoinRequests}',
          color: theme.colorScheme.tertiary,
          action: _openTeams,
        ),
      if ((management?.openInvoicesCount ?? 0) > 0)
        _ClubAttention(
          icon: Icons.account_balance_wallet_outlined,
          title: t('clubHub.openPayments'),
          value: _money(context, management?.openInvoiceAmount ?? 0),
          color: theme.colorScheme.error,
          action: () => _openMembership(club),
        ),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _heading(
          Icons.bolt_outlined,
          t('clubHub.today'),
          t('clubHub.todayBody'),
        ),
        const SizedBox(height: 10),
        if (items.isEmpty)
          _ClubHubEmpty(
            icon: Icons.task_alt_outlined,
            title: t('clubHub.noOpenTasks'),
            body: t('clubHub.noOpenTasksBody'),
          )
        else
          AirmiusPanel(
            child: Column(
              children: items
                  .map((item) => _AttentionLine(item: item))
                  .toList(),
            ),
          ),
      ],
    );
  }

  Widget _onboarding(ClubSummary club) {
    final onboarding = club.management?.onboarding ?? const <String, dynamic>{};
    if (onboarding.isEmpty) return const SizedBox.shrink();
    final rawSteps = onboarding['steps'];
    final steps = rawSteps is List
        ? rawSteps.whereType<Map<String, dynamic>>().toList()
        : const <Map<String, dynamic>>[];
    final percent = switch (onboarding['completion_percent']) {
      final num value => value.toDouble().clamp(0, 100),
      _ => 0.0,
    };
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
                      '${onboarding['title'] ?? ''}',
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text('${onboarding['subtitle'] ?? ''}'),
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
            label: '${onboarding['progress_label'] ?? ''}',
            value: '${percent.round()}%',
            child: LinearProgressIndicator(
              value: percent / 100,
              minHeight: 8,
              borderRadius: BorderRadius.circular(99),
            ),
          ),
          const SizedBox(height: 7),
          Text(
            '${onboarding['progress_label'] ?? ''}',
            style: theme.textTheme.bodySmall,
          ),
          const SizedBox(height: 12),
          ...steps.map((step) {
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
        ],
      ),
    );
  }

  Widget _managementAreas(ClubSummary club) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final areas = [
      _ClubArea(
        icon: Icons.people_alt_outlined,
        title: t('clubHub.membersAndFees'),
        body: t('clubHub.membersAndFeesBody'),
        color: theme.colorScheme.primary,
        onTap: () => _openMembership(club),
      ),
      _ClubArea(
        icon: Icons.groups_2_outlined,
        title: t('clubHub.teamManagement'),
        body: t('clubHub.teamManagementBody'),
        color: theme.colorScheme.secondary,
        onTap: _openTeams,
      ),
      _ClubArea(
        icon: Icons.apartment_outlined,
        title: t('clubHub.clubProfile'),
        body: t('clubHub.clubProfileBody'),
        color: theme.colorScheme.primary,
        onTap: () => _openProfile(club),
      ),
      _ClubArea(
        icon: Icons.folder_outlined,
        title: t('clubHub.documents'),
        body: t('clubHub.documentsBody'),
        color: theme.colorScheme.tertiary,
        onTap: _openFiles,
      ),
      _ClubArea(
        icon: Icons.dynamic_feed_outlined,
        title: t('clubHub.clubNews'),
        body: t('clubHub.clubNewsBody'),
        color: theme.colorScheme.primary,
        onTap: _openFeed,
      ),
      _ClubArea(
        icon: Icons.forum_outlined,
        title: t('clubHub.communication'),
        body: t('clubHub.communicationBody'),
        color: theme.colorScheme.secondary,
        onTap: _openMessages,
      ),
      _ClubArea(
        icon: Icons.event_available_outlined,
        title: t('clubHub.events'),
        body: t('clubHub.eventsBody'),
        color: theme.colorScheme.tertiary,
        onTap: _openEvents,
      ),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _heading(
          Icons.dashboard_customize_outlined,
          t('clubHub.management'),
          t('clubHub.managementBody'),
        ),
        const SizedBox(height: 10),
        LayoutBuilder(
          builder: (context, constraints) {
            final columns = constraints.maxWidth >= 700
                ? 3
                : constraints.maxWidth >= 480
                ? 2
                : 1;
            const gap = 10.0;
            final width =
                (constraints.maxWidth - gap * (columns - 1)) / columns;
            return Wrap(
              spacing: gap,
              runSpacing: gap,
              children: areas
                  .map(
                    (area) => SizedBox(
                      width: width,
                      child: _ClubAreaCard(area: area),
                    ),
                  )
                  .toList(),
            );
          },
        ),
      ],
    );
  }

  Widget _members(ClubSummary club) {
    final t = AirmiusScope.of(context).t;
    final members = club.management?.members ?? const [];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _heading(
          Icons.people_outline,
          t('clubHub.memberOverview'),
          t('clubHub.memberOverviewBody'),
        ),
        const SizedBox(height: 10),
        if (members.isEmpty)
          _ClubHubEmpty(
            icon: Icons.person_search_outlined,
            title: t('clubHub.noMembers'),
            body: t('clubHub.noMembersBody'),
          )
        else
          AirmiusPanel(
            child: Column(
              children: [
                ...members
                    .take(5)
                    .map(
                      (member) => _MemberLine(
                        name: member.name,
                        email: member.email,
                        role: member.role,
                        status: member.status,
                      ),
                    ),
                const SizedBox(height: 8),
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: TextButton.icon(
                    onPressed: () => _openMembership(club),
                    icon: const Icon(Icons.arrow_forward_outlined),
                    label: Text(t('clubHub.manageAllMembers')),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }

  Widget _finance(ClubSummary club) {
    final t = AirmiusScope.of(context).t;
    final management = club.management;
    if (management == null) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _heading(
          Icons.account_balance_outlined,
          t('clubHub.finance'),
          t('clubHub.financeBody'),
        ),
        const SizedBox(height: 10),
        AirmiusPanel(
          child: Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _ClubHubMetric(
                icon: Icons.savings_outlined,
                value: _money(context, management.totalBalance),
                label: t('clubHub.balance'),
              ),
              _ClubHubMetric(
                icon: Icons.trending_up_outlined,
                value: _money(context, management.incomePeriodTotal),
                label: t('clubHub.income'),
              ),
              _ClubHubMetric(
                icon: Icons.trending_down_outlined,
                value: _money(context, management.expensePeriodTotal),
                label: t('clubHub.expenses'),
              ),
              _ClubHubMetric(
                icon: Icons.account_balance_outlined,
                value: '${management.sepaReadyMembersCount}',
                label: t('clubHub.sepaReady'),
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

  Future<void> _openProfile(ClubSummary club) => _open(
    ClubProfileScreen(
      club: club,
      requested: false,
      onRequest: (_) {},
      onWithdraw: (_) {},
    ),
  );

  Future<void> _openTeams() => _open(const TeamsCenterScreen());
  Future<void> _openFiles() => _open(const FileManagerScreen());
  Future<void> _openFeed() => _open(const FeedCenterScreen());
  Future<void> _openMessages() => _open(const ConversationsCenterScreen());
  Future<void> _openEvents() => _open(const EventManagementScreen());

  Future<void> _openOnboardingAction(ClubSummary club, String action) =>
      switch (action) {
        'profile' || 'verification' => _openProfile(club),
        'roles' || 'memberships' || 'members' => _openMembership(club),
        'teams' => _openTeams(),
        'events' => _openEvents(),
        'feed' => _openFeed(),
        'files' => _openFiles(),
        _ => Future<void>.value(),
      };
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

class _ClubHubMetric extends StatelessWidget {
  const _ClubHubMetric({
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
  const _ClubAreaCard({required this.area});

  final _ClubArea area;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AirmiusPanel(
      onTap: area.onTap,
      borderColor: area.color.withValues(alpha: 0.4),
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 128),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(area.icon, color: area.color, size: 28),
                const Spacer(),
                const Icon(Icons.arrow_forward_outlined, size: 20),
              ],
            ),
            const SizedBox(height: 12),
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
      ),
    );
  }
}

class _MemberLine extends StatelessWidget {
  const _MemberLine({
    required this.name,
    required this.email,
    this.role,
    this.status,
  });

  final String name;
  final String email;
  final String? role;
  final String? status;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: const EdgeInsets.only(bottom: 11),
      child: Row(
        children: [
          AirmiusAvatar(name),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: const TextStyle(fontWeight: FontWeight.w800)),
                const SizedBox(height: 2),
                Text(email),
              ],
            ),
          ),
          StatusPill(
            _roleLabel(t, role),
            color: status == 'active'
                ? Theme.of(context).colorScheme.secondary
                : null,
          ),
        ],
      ),
    );
  }
}

class _ClubHubEmpty extends StatelessWidget {
  const _ClubHubEmpty({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AirmiusPanel(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 15),
        child: Column(
          children: [
            Icon(icon, color: theme.colorScheme.primary, size: 44),
            const SizedBox(height: 10),
            Text(
              title,
              textAlign: TextAlign.center,
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 5),
            Text(body, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
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

String _roleLabel(String Function(String) t, String? role) {
  final value = (role ?? 'member').toLowerCase();
  const known = {
    'owner',
    'admin',
    'manager',
    'academy_manager',
    'financial_controller',
    'trainer',
    'member',
  };
  return t(
    known.contains(value) ? 'clubHub.role.$value' : 'clubHub.role.member',
  );
}
