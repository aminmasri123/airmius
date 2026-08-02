import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class RoleWorkspaceSwitcherSuiteScreen extends StatefulWidget {
  const RoleWorkspaceSwitcherSuiteScreen({super.key});

  @override
  State<RoleWorkspaceSwitcherSuiteScreen> createState() =>
      _RoleWorkspaceSwitcherSuiteScreenState();
}

class _RoleWorkspaceSwitcherSuiteScreenState
    extends State<RoleWorkspaceSwitcherSuiteScreen> {
  String workspace = 'Mitglied';
  bool rememberLastWorkspace = true;
  bool showRoleBadges = true;
  bool restrictAdminAreas = true;
  bool quickSwitchEnabled = true;

  @override
  Widget build(BuildContext context) {
    final roles = [
      const _WorkspaceRole(
        title: 'Mitglied',
        status: 'Aktiv',
        body:
            'Startet mit Mitgliedschaften, Karte, Beiträgen, Events, Nachrichten, Badges und Support.',
        icon: Icons.badge_outlined,
        color: AirmiusColors.green,
      ),
      const _WorkspaceRole(
        title: 'Vereinsadmin',
        status: 'ZBB',
        body:
            'Wechselt zu Anfragen, Mitgliedern, Teams, Dokumenten, Beitragsregeln, Import/Export und Analytics.',
        icon: Icons.groups_2_outlined,
        color: AirmiusColors.blue,
      ),
      const _WorkspaceRole(
        title: 'Trainer',
        status: 'Team',
        body:
            'Fokussiert Training, Kader, Anwesenheit, Vorfälle, Teamchat, Kurse und Rollenrechte.',
        icon: Icons.sports_outlined,
        color: AirmiusColors.amber,
      ),
      const _WorkspaceRole(
        title: 'Guardian',
        status: 'Safety',
        body:
            'Zeigt Minderjährige, Freigaben, Notfallkontakte, Dokumente, Events und Benachrichtigungen.',
        icon: Icons.family_restroom_outlined,
        color: AirmiusColors.pink,
      ),
      const _WorkspaceRole(
        title: 'Plattformadmin',
        status: 'Admin',
        body:
            'Öffnet Moderation, Audit, Verifizierung, Support, Analytics, Content, Ads und Systembetrieb.',
        icon: Icons.admin_panel_settings_outlined,
        color: AirmiusColors.blue,
      ),
    ];

    return PageFrame(
      title: 'Rollen & Workspaces',
      subtitle: 'Kontextwechsel für die mobile App',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('ROLE ROUTING'),
                const SizedBox(height: 8),
                Text(
                  'Die mobile App muss den richtigen Arbeitsbereich laden: Mitglied, Vereinsadmin, Trainer, Guardian oder Plattformadmin. Jede Rolle sieht andere Navigation, Aktionen und Daten.',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    height: 1.45,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '5', label: 'Rollen'),
                    Metric(value: 'Hub', label: 'Start'),
                    Metric(value: 'Rights', label: 'Zugriff'),
                    Metric(value: 'Fast', label: 'Switch'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('AKTIVER WORKSPACE'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Mitglied', label: Text('Member')),
                    ButtonSegment(value: 'Verein', label: Text('Club')),
                    ButtonSegment(value: 'Trainer', label: Text('Coach')),
                    ButtonSegment(value: 'Admin', label: Text('Admin')),
                  ],
                  selected: {workspace},
                  onSelectionChanged: (value) =>
                      setState(() => workspace = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('REGELN'),
                const SizedBox(height: 8),
                _WorkspaceSwitch(
                  title: 'Letzten Workspace merken',
                  value: rememberLastWorkspace,
                  color: Theme.of(context).colorScheme.secondary,
                  onChanged: (value) =>
                      setState(() => rememberLastWorkspace = value),
                ),
                _WorkspaceSwitch(
                  title: 'Rollenbadges anzeigen',
                  value: showRoleBadges,
                  color: airmiusAccentColor(context),
                  onChanged: (value) => setState(() => showRoleBadges = value),
                ),
                _WorkspaceSwitch(
                  title: 'Adminbereiche schützen',
                  value: restrictAdminAreas,
                  color: Theme.of(context).colorScheme.tertiary,
                  onChanged: (value) =>
                      setState(() => restrictAdminAreas = value),
                ),
                _WorkspaceSwitch(
                  title: 'Schnellwechsel aktivieren',
                  value: quickSwitchEnabled,
                  color: airmiusAccentColor(context),
                  onChanged: (value) =>
                      setState(() => quickSwitchEnabled = value),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final role in roles) ...[
            _WorkspaceRoleCard(role: role),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Aktiver Workspace: $workspace. Später verbindet die API Rollen, Berechtigungen, Vereine, Teams, Guardian-Beziehungen und Adminrechte mit der mobilen Navigation.',
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.45,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Workspace wechseln',
                  icon: Icons.swap_horiz_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Workspace wechseln',
                    body:
                        'Diese UI bereitet Rollenwechsel, Workspace-Startseite, Berechtigungen und kontextbezogene Navigation für die spätere Laravel-API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.swap_horiz_outlined,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _WorkspaceRole {
  const _WorkspaceRole({
    required this.title,
    required this.status,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String title;
  final String status;
  final String body;
  final IconData icon;
  final Color color;
}

class _WorkspaceSwitch extends StatelessWidget {
  const _WorkspaceSwitch({
    required this.title,
    required this.value,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final Color color;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(
        title,
        style: TextStyle(
          color: airmiusTextColor(context),
          fontWeight: FontWeight.w900,
        ),
      ),
      value: value,
      activeThumbColor: airmiusSemanticColor(context, color),
      onChanged: onChanged,
    );
  }
}

class _WorkspaceRoleCard extends StatelessWidget {
  const _WorkspaceRoleCard({required this.role});

  final _WorkspaceRole role;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(
            icon: role.icon,
            color: airmiusSemanticColor(context, role.color),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        role.title,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 17,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    StatusPill(
                      role.status,
                      color: airmiusSemanticColor(context, role.color),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  role.body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.42,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
