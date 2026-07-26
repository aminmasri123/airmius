import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'team_detail_screen.dart';
import 'team_operations_screen.dart';
import 'clubs_screen.dart';

class TeamsCenterScreen extends StatefulWidget {
  const TeamsCenterScreen({super.key});

  @override
  State<TeamsCenterScreen> createState() => _TeamsCenterScreenState();
}

class _TeamsCenterScreenState extends State<TeamsCenterScreen> {
  String _tab = 'roster';
  Future<AirmiusPage<AirmiusTeam>>? _teamsFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _teamsFuture ??= AirmiusServicesScope.of(
      context,
    ).repositories.clubs.teams();
  }

  void _reloadTeams() {
    setState(() {
      _teamsFuture = AirmiusServicesScope.of(
        context,
      ).repositories.clubs.teams();
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            Theme.of(context).colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('teamsCenter.teams'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: FutureBuilder<AirmiusPage<AirmiusTeam>>(
        future: _teamsFuture,
        builder: (context, snapshot) {
          final teams = snapshot.data?.items ?? const <AirmiusTeam>[];
          final filteredTeams = _filterTeams(teams);
          return PageFrame(
            title: t('teamsCenter.teams'),
            subtitle: t('teamsCenter.subtitle'),
            trailing: StatusPill('${teams.length} ${t('teamsCenter.teams')}'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('teamsCenter.management')),
                      const SizedBox(height: 8),
                      Text(
                        t('teamsCenter.mobileWorkspaces'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 23,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        t('teamsCenter.managementBody'),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 14),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children:
                            [
                                  'roster',
                                  'invitations',
                                  'calendar',
                                  'files',
                                  'chat',
                                  'profile',
                                ]
                                .map(
                                  (item) => ChoiceChip(
                                    selected: _tab == item,
                                    label: Text(t('teamsCenter.tab.$item')),
                                    onSelected: (_) =>
                                        setState(() => _tab = item),
                                    selectedColor: airmiusAccentColor(
                                      context,
                                    ).withValues(alpha: 0.22),
                                    backgroundColor: airmiusSurfaceSoftColor(
                                      context,
                                    ),
                                    side: BorderSide(
                                      color: _tab == item
                                          ? airmiusAccentColor(context)
                                          : airmiusBorderColor(context),
                                    ),
                                    labelStyle: TextStyle(
                                      color: _tab == item
                                          ? airmiusAccentColor(context)
                                          : airmiusMutedColor(context),
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                )
                                .toList(),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: MetricCard(
                        value: '${teams.length}',
                        label: t('teamsCenter.teams'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(
                        value:
                            '${teams.where((team) => _text(team.visibility).toLowerCase().contains('public')).length}',
                        label: t('teamsCenter.public'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(
                        value:
                            '${teams.where((team) => _text(team.ageGroup).isNotEmpty).length}',
                        label: t('teamsCenter.ageGroups'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                if (snapshot.connectionState == ConnectionState.waiting &&
                    teams.isEmpty)
                  AirmiusPanel(
                    child: Center(
                      child: Padding(
                        padding: EdgeInsets.all(14),
                        child: CircularProgressIndicator(
                          color: airmiusAccentColor(context),
                        ),
                      ),
                    ),
                  ),
                if (snapshot.hasError)
                  AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Eyebrow(t('teamsCenter.loadFailed')),
                        const SizedBox(height: 8),
                        Text(
                          snapshot.error is AirmiusApiException
                              ? (snapshot.error! as AirmiusApiException)
                                    .userMessage
                              : t('common.errorDetails'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.35,
                          ),
                        ),
                        const SizedBox(height: 12),
                        AirmiusButton(
                          label: t('teamsCenter.reload'),
                          icon: Icons.refresh_outlined,
                          onPressed: _reloadTeams,
                        ),
                      ],
                    ),
                  ),
                if (!snapshot.hasError &&
                    snapshot.connectionState != ConnectionState.waiting &&
                    filteredTeams.isEmpty)
                  AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Eyebrow(t('teamsCenter.empty')),
                        const SizedBox(height: 8),
                        Text(
                          t('teamsCenter.emptyBody'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.35,
                          ),
                        ),
                        const SizedBox(height: 12),
                        AirmiusButton(
                          label: t('teamsCenter.showProfiles'),
                          icon: Icons.groups_2_outlined,
                          onPressed: () => setState(() => _tab = 'profile'),
                        ),
                      ],
                    ),
                  ),
                for (final team in filteredTeams) ...[
                  _TeamLine(
                    team: team,
                    icon: _teamIcon(team),
                    title: team.name,
                    body: _teamBody(team),
                    status: _text(
                      team.visibility,
                      fallback: t('teamDetail.team'),
                    ),
                    color: _teamColor(team),
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => TeamDetailScreen(
                          title: team.name,
                          mode: _teamMode(_tab),
                          teamId: team.id,
                          team: team,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('teamsCenter.actions')),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: [
                          AirmiusButton(
                            label: t('teamsCenter.openClubs'),
                            icon: Icons.group_add_outlined,
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => ClubsScreen(
                                  requestedClubIds: const {},
                                  onRequestClub: (_) {},
                                  onWithdrawClub: (_) {},
                                ),
                              ),
                            ),
                          ),
                          AirmiusButton(
                            label: t('teamsCenter.reload'),
                            icon: Icons.refresh_outlined,
                            secondary: true,
                            onPressed: _reloadTeams,
                          ),
                          AirmiusButton(
                            label: t('teamsCenter.manageRoster'),
                            icon: Icons.badge_outlined,
                            secondary: true,
                            onPressed: () => setState(() => _tab = 'roster'),
                          ),
                          AirmiusButton(
                            label: t('teamsCenter.sendInvitation'),
                            icon: Icons.mark_email_read_outlined,
                            secondary: true,
                            onPressed: () =>
                                setState(() => _tab = 'invitations'),
                          ),
                          AirmiusButton(
                            label: t('teamsCenter.operations'),
                            icon: Icons.tune_outlined,
                            secondary: true,
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const TeamOperationsScreen(),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  List<AirmiusTeam> _filterTeams(List<AirmiusTeam> teams) {
    return teams;
  }

  String _teamMode(String tab) => switch (tab) {
    'invitations' => 'Einladungen',
    'calendar' => 'Kalender',
    'files' => 'Dateien',
    'chat' => 'Chat',
    'profile' => 'Profil',
    _ => 'Kader',
  };

  String _teamBody(AirmiusTeam team) {
    final parts = [
      team.clubName,
      team.sportType,
      team.ageGroup,
      team.description,
    ].whereType<String>().where((value) => value.trim().isNotEmpty).toList();
    return parts.isEmpty
        ? AirmiusScope.of(context).t('teamsCenter.defaultBody')
        : parts.join(' - ');
  }

  IconData _teamIcon(AirmiusTeam team) {
    final text = '${team.name} ${team.ageGroup ?? ''} ${team.description ?? ''}'
        .toLowerCase();
    if (text.contains('jugend') || text.contains('u1')) {
      return Icons.family_restroom_outlined;
    }
    if (text.contains('lauf') || text.contains('training')) {
      return Icons.directions_run_outlined;
    }
    return Icons.groups_2_outlined;
  }

  Color _teamColor(AirmiusTeam team) {
    final visibility = _text(team.visibility).toLowerCase();
    if (visibility.contains('public') || visibility.contains('offen')) {
      return Theme.of(context).colorScheme.secondary;
    }
    if (visibility.contains('private') || visibility.contains('intern')) {
      return Theme.of(context).colorScheme.tertiary;
    }
    return airmiusAccentColor(context);
  }

  String _text(String? value, {String fallback = ''}) {
    final text = value?.trim();
    return text == null || text.isEmpty ? fallback : text;
  }
}

class _TeamLine extends StatelessWidget {
  const _TeamLine({
    required this.team,
    required this.icon,
    required this.title,
    required this.body,
    required this.status,
    required this.color,
    required this.onTap,
  });

  final AirmiusTeam team;
  final IconData icon;
  final String title;
  final String body;
  final String status;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: onTap,
      borderColor: color.withValues(alpha: 0.45),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AirmiusAvatar(title, imageUrl: team.logoUrl),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 10),
                StatusPill(status, color: color),
              ],
            ),
          ),
          Icon(icon, color: color, size: 22),
          const SizedBox(width: 4),
          Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
        ],
      ),
    );
  }
}
