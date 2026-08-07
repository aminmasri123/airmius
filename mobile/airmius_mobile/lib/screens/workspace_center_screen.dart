import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_module_access.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_workspace_snapshot.dart';
import '../widgets/airmius_widgets.dart';
import 'workspace_detail_screen.dart';

class WorkspaceCenterScreen extends StatefulWidget {
  const WorkspaceCenterScreen({super.key});

  @override
  State<WorkspaceCenterScreen> createState() => _WorkspaceCenterScreenState();
}

class _WorkspaceCenterScreenState extends State<WorkspaceCenterScreen> {
  String _workspace = 'club';
  Future<AirmiusWorkspaceSnapshot>? _snapshotFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _snapshotFuture ??= _loadSnapshot();
  }

  Future<AirmiusWorkspaceSnapshot> _loadSnapshot() async {
    final services = AirmiusServicesScope.of(context);
    final clubs = await services.repositories.clubs.searchClubs(mine: true);
    final teams = await services.repositories.clubs.teams();
    final invitations = await services.repositories.clubs.teamInvitations();
    final user = services.authState.user;
    final roles = <String>{
      if (user?.role.trim().isNotEmpty == true) user!.role.trim(),
      ...?user?.roles.where((role) => role.trim().isNotEmpty),
    }.toList();
    return AirmiusWorkspaceSnapshot(
      clubs: clubs.items,
      teams: teams.items,
      invitations: invitations,
      roles: roles,
      permissions: user?.permissions ?? const [],
    );
  }

  void _reload() => setState(() => _snapshotFuture = _loadSnapshot());

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final canOpenAdmin = AirmiusModuleAccess.canOpenAdmin(
      AirmiusServicesScope.of(context).authState.user,
    );
    final text = airmiusTextColor(context);
    final muted = airmiusMutedColor(context);
    final border = Theme.of(context).dividerColor;
    final primary = Theme.of(context).colorScheme.primary;
    final options = <({String id, String label})>[
      (id: 'guest', label: t('workspace.guest')),
      (id: 'dashboard', label: t('workspace.dashboard')),
      (id: 'club', label: t('workspace.club')),
      (id: 'trainer', label: t('workspace.trainer')),
      if (canOpenAdmin) (id: 'admin', label: t('workspace.admin')),
    ];
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('workspace.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('common.retry'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<AirmiusWorkspaceSnapshot>(
        future: _snapshotFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting &&
              !snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError && !snapshot.hasData) {
            return PageFrame(
              title: t('workspace.title'),
              subtitle: t('workspace.subtitle'),
              child: AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      t('messages.error'),
                      style: TextStyle(color: muted, height: 1.4),
                    ),
                    const SizedBox(height: 12),
                    AirmiusButton(
                      label: t('common.retry'),
                      icon: Icons.refresh_outlined,
                      onPressed: _reload,
                    ),
                  ],
                ),
              ),
            );
          }
          final data = snapshot.data ?? const AirmiusWorkspaceSnapshot.empty();
          return PageFrame(
            title: t('workspace.title'),
            subtitle: t('workspace.subtitle'),
            trailing: StatusPill(t('workspace.status'), color: primary),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('workspace.context')),
                      const SizedBox(height: 8),
                      Text(
                        t('workspace.headline'),
                        style: TextStyle(
                          color: text,
                          fontSize: 23,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        t('workspace.body'),
                        style: TextStyle(color: muted, height: 1.4),
                      ),
                      const SizedBox(height: 14),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: options
                            .map(
                              (item) => ChoiceChip(
                                selected: _workspace == item.id,
                                label: Text(item.label),
                                onSelected: (_) =>
                                    setState(() => _workspace = item.id),
                                selectedColor: primary.withValues(alpha: 0.22),
                                backgroundColor: Theme.of(
                                  context,
                                ).colorScheme.surfaceContainerHighest,
                                side: BorderSide(
                                  color: _workspace == item.id
                                      ? primary
                                      : border,
                                ),
                                labelStyle: TextStyle(
                                  color: _workspace == item.id
                                      ? primary
                                      : muted,
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
                        value: '${data.activeAreas}',
                        label: t('workspace.active'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(
                        value: '${data.invitations.length}',
                        label: t('workspace.invitation'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(
                        value: '${data.roles.length}',
                        label: t('workspace.roles'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                if (data.clubs.isEmpty && data.teams.isEmpty)
                  AirmiusPanel(
                    child: Text(
                      t('workspace.body'),
                      style: TextStyle(color: muted, height: 1.4),
                    ),
                  )
                else ...[
                  _WorkspaceLine(
                    icon: Icons.open_in_new_outlined,
                    title: t('workspace.guest'),
                    body: t('workspace.guestBody'),
                    status: t('workspace.public'),
                    color: primary,
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => WorkspaceDetailScreen(
                          title: t('workspace.guest'),
                          status: t('workspace.public'),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  if (data.clubs.isNotEmpty)
                    _WorkspaceLine(
                      icon: Icons.apartment_outlined,
                      title: data.clubs.length == 1
                          ? data.clubs.single.name
                          : '${t('workspace.club')} (${data.clubs.length})',
                      body: data.clubs
                          .take(3)
                          .map((club) => club.name)
                          .join(' · '),
                      status: t('workspace.clubStatus'),
                      color: Theme.of(context).colorScheme.secondary,
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => WorkspaceDetailScreen(
                            title: t('workspace.club'),
                            status: t('workspace.clubStatus'),
                            snapshot: data,
                          ),
                        ),
                      ),
                    ),
                  const SizedBox(height: 12),
                  if (data.teams.isNotEmpty)
                    _WorkspaceLine(
                      icon: Icons.sports_score_outlined,
                      title: '${t('workspace.trainer')} (${data.teams.length})',
                      body: data.teams
                          .take(3)
                          .map((team) => team.name)
                          .join(' · '),
                      status: t('workspace.coach'),
                      color: Theme.of(context).colorScheme.tertiary,
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => WorkspaceDetailScreen(
                            title: t('workspace.trainer'),
                            status: t('workspace.coach'),
                            snapshot: data,
                          ),
                        ),
                      ),
                    ),
                ],
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('workspace.actions')),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: [
                          AirmiusButton(
                            label: t('workspace.switch'),
                            icon: Icons.swap_horiz_outlined,
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => WorkspaceDetailScreen(
                                  title: t('workspace.switch'),
                                  status: t('workspace.active'),
                                  snapshot: data,
                                ),
                              ),
                            ),
                          ),
                          AirmiusButton(
                            label: t('workspace.invitations'),
                            icon: Icons.mark_email_read_outlined,
                            secondary: true,
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => WorkspaceDetailScreen(
                                  title: t('workspace.invitations'),
                                  status: t('workspace.invitation'),
                                  snapshot: data,
                                ),
                              ),
                            ),
                          ),
                          AirmiusButton(
                            label: t('workspace.checkRoles'),
                            icon: Icons.verified_user_outlined,
                            secondary: true,
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => WorkspaceDetailScreen(
                                  title: t('workspace.checkRoles'),
                                  status: t('workspace.audit'),
                                  snapshot: data,
                                ),
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
}

class _WorkspaceLine extends StatelessWidget {
  const _WorkspaceLine({
    required this.icon,
    required this.title,
    required this.body,
    required this.status,
    required this.color,
    required this.onTap,
  });

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
          Icon(icon, color: color, size: 28),
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
          Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
        ],
      ),
    );
  }
}
