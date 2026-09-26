import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_module_access.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_workspace_snapshot.dart';
import '../widgets/airmius_widgets.dart';
import 'admin_center_screen.dart';
import 'club_cockpit_screen.dart';
import 'daily_flow_screen.dart';
import 'guest_portal_screen.dart';
import 'settings_center_screen.dart';
import 'trainer_cockpit_screen.dart';

class WorkspaceDetailScreen extends StatefulWidget {
  const WorkspaceDetailScreen({
    super.key,
    required this.title,
    required this.status,
    this.snapshot = const AirmiusWorkspaceSnapshot.empty(),
  });

  final String title;
  final String status;
  final AirmiusWorkspaceSnapshot snapshot;

  @override
  State<WorkspaceDetailScreen> createState() => _WorkspaceDetailScreenState();
}

class _WorkspaceDetailScreenState extends State<WorkspaceDetailScreen> {
  String _context = 'club';
  bool _busy = false;

  String t(String key) => AirmiusScope.of(context).t(key);

  Future<void> _activateContext() async {
    if (_context == 'admin' &&
        !AirmiusModuleAccess.canOpenAdmin(
          AirmiusServicesScope.of(context).authState.user,
        )) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('adminHub.forbiddenBody'))));
      return;
    }

    final destination = switch (_context) {
      'guest' => const GuestPortalScreen(),
      'dashboard' => const DailyFlowScreen(),
      'trainer' => const TrainerCockpitScreen(),
      'admin' => const AdminCenterScreen(),
      _ => const ClubCockpitScreen(),
    };
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => destination),
    );
  }

  Future<void> _acceptInvitation() async {
    final invitation = widget.snapshot.invitations.firstOrNull;
    if (invitation == null || _busy) return;
    setState(() => _busy = true);
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.acceptTeamInvitation(invitation.id);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('chat.invitationAccepted'))));
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(t('chat.invitationFailed'))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final canOpenAdmin = AirmiusModuleAccess.canOpenAdmin(
      AirmiusServicesScope.of(context).authState.user,
    );
    final theme = Theme.of(context);
    final primary = theme.colorScheme.primary;
    final text = theme.textTheme.bodyLarge?.color ?? AirmiusColors.text;
    final muted = theme.textTheme.bodyMedium?.color ?? AirmiusColors.muted;
    final snapshot = widget.snapshot;
    final roles = snapshot.roles.isEmpty
        ? [t('workspace.roles')]
        : snapshot.roles.map((role) {
            final key = switch (role) {
              'club_owner' => 'owner',
              'club_admin' => 'admin',
              'club_manager' => 'manager',
              'club_trainer' => 'trainer',
              _ => role,
            };
            final translated = t('clubHub.role.$key');
            return translated == 'clubHub.role.$key'
                ? role.replaceAll('_', ' ')
                : translated;
          }).toList();
    final selectedLabel = _labelFor(_context);

    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('workspace.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: widget.title,
        subtitle: t('workspace.subtitle'),
        trailing: StatusPill(widget.status, color: primary),
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
                    selectedLabel,
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
                  DropdownButtonFormField<String>(
                    initialValue: _context,
                    dropdownColor: theme.colorScheme.surface,
                    decoration: _fieldDecoration(t('workspace.context')),
                    items:
                        [
                              ('guest', t('workspace.guest')),
                              ('dashboard', t('workspace.dashboard')),
                              ('club', t('workspace.club')),
                              ('trainer', t('workspace.trainer')),
                              if (canOpenAdmin) ('admin', t('workspace.admin')),
                            ]
                            .map(
                              (item) => DropdownMenuItem(
                                value: item.$1,
                                child: Text(item.$2),
                              ),
                            )
                            .toList(),
                    onChanged: (value) =>
                        setState(() => _context = value ?? _context),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: MetricCard(
                    value: '${snapshot.roles.length}',
                    label: t('workspace.roles'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: '${snapshot.teams.length}',
                    label: t('teamsCenter.teams'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: '${snapshot.invitations.length}',
                    label: t('workspace.invitation'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('workspace.roles')),
                  const SizedBox(height: 12),
                  for (final role in roles) ...[
                    _WorkspaceRole(
                      role: role,
                      rights: snapshot.permissions.isEmpty
                          ? t('workspace.permissionsUnknown')
                          : t('workspace.permissionsCount').replaceFirst(
                              '{count}',
                              '${snapshot.permissions.length}',
                            ),
                    ),
                    if (role != roles.last) const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            if (snapshot.clubs.isNotEmpty)
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Eyebrow(t('workspace.club')),
                    const SizedBox(height: 10),
                    for (final club in snapshot.clubs.take(5))
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.apartment_outlined),
                        title: Text(
                          club.name,
                          style: const TextStyle(fontWeight: FontWeight.w900),
                        ),
                        subtitle: Text(
                          '${club.membersCount} ${t('clubs.members')} · ${club.teamsCount} ${t('teamsCenter.teams')}',
                          style: TextStyle(color: muted),
                        ),
                      ),
                  ],
                ),
              ),
            if (snapshot.invitations.isNotEmpty) ...[
              const SizedBox(height: 14),
              AirmiusPanel(
                borderColor: AirmiusColors.amber.withValues(alpha: 0.55),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Eyebrow(t('workspace.invitations')),
                    const SizedBox(height: 8),
                    Text(
                      snapshot.invitations
                          .map((item) => item.teamName)
                          .join(' · '),
                      style: TextStyle(color: muted, height: 1.35),
                    ),
                    const SizedBox(height: 10),
                    AirmiusButton(
                      label: t('teamDetail.accept'),
                      icon: Icons.mark_email_read_outlined,
                      onPressed: _busy ? null : _acceptInvitation,
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('workspace.preferences')),
                  const SizedBox(height: 8),
                  Text(
                    t('workspace.preferencesBody'),
                    style: TextStyle(color: muted, height: 1.4),
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label: t('workspace.openSettings'),
                    icon: Icons.settings_outlined,
                    secondary: true,
                    onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const SettingsCenterScreen(),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusButton(
              label: t('workspace.switch'),
              icon: Icons.swap_horiz_outlined,
              onPressed: _activateContext,
            ),
          ],
        ),
      ),
    );
  }

  String _labelFor(String value) => switch (value) {
    'guest' => t('workspace.guest'),
    'dashboard' => t('workspace.dashboard'),
    'trainer' => t('workspace.trainer'),
    'admin' => t('workspace.admin'),
    _ => t('workspace.club'),
  };

  InputDecoration _fieldDecoration(String label) => InputDecoration(
    labelText: label,
    filled: true,
    fillColor: Theme.of(context).colorScheme.surfaceContainerHighest,
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
  );
}

class _WorkspaceRole extends StatelessWidget {
  const _WorkspaceRole({required this.role, required this.rights});

  final String role;
  final String rights;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: Theme.of(context).dividerColor),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(role, style: const TextStyle(fontWeight: FontWeight.w900)),
        const SizedBox(height: 4),
        Text(rights, style: const TextStyle(height: 1.35)),
      ],
    ),
  );
}
