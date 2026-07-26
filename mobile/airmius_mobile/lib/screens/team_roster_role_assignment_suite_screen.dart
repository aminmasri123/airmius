import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class TeamRosterRoleAssignmentSuiteScreen extends StatefulWidget {
  const TeamRosterRoleAssignmentSuiteScreen({super.key});

  @override
  State<TeamRosterRoleAssignmentSuiteScreen> createState() =>
      _TeamRosterRoleAssignmentSuiteScreenState();
}

class _TeamRosterRoleAssignmentSuiteScreenState
    extends State<TeamRosterRoleAssignmentSuiteScreen> {
  String teamFilter = 'Alle';
  bool trainerCanEdit = true;
  bool captainCanInvite = true;
  bool membersCanSeeRoster = true;
  bool joinRequestsEnabled = true;

  @override
  Widget build(BuildContext context) {
    final teams = [
      const _TeamRow(
        name: 'ZBB Herren',
        meta: '12 Mitglieder - 2 Trainer',
        status: 'Aktiv',
        body:
            'Kader, Trainer, Captain, Termine, Dateien und Chatrechte werden mobil gebuendelt.',
        color: AirmiusColors.blue,
      ),
      const _TeamRow(
        name: 'ZBB Jugend U18',
        meta: '8 Mitglieder - Guardian sichtbar',
        status: 'Jugend',
        body:
            'Jugendschutz, Elternkontakt, Trainingsfreigaben und Teamrollen sind vorbereitet.',
        color: AirmiusColors.green,
      ),
      const _TeamRow(
        name: 'Warteliste Training',
        meta: '5 Join-Requests',
        status: 'Prüfung',
        body:
            'Neue Anfragen können geprüft, angenommen, abgelehnt oder Rückfragen erhalten.',
        color: AirmiusColors.amber,
      ),
    ];

    final filtered = teams
        .where((team) => teamFilter == 'Alle' || team.status == teamFilter)
        .toList();

    return PageFrame(
      title: 'Teamverwaltung',
      subtitle: 'Kader, Rollen und Join-Requests',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('TEAM-OPS'),
                const SizedBox(height: 8),
                const Text(
                  'Vereine brauchen mobile Teamstruktur: Kader, Trainer, Captain, Join-Requests, Dateien, Termine, Chatrechte und Sichtbarkeit pro Rolle.',
                  style: TextStyle(
                    color: AirmiusColors.text,
                    height: 1.45,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '3', label: 'Teams'),
                    Metric(value: '5', label: 'Requests'),
                    Metric(value: '4', label: 'Rollen'),
                    Metric(value: 'Rights', label: 'Rechte'),
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
                const SectionLabel('FILTER'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Alle', label: Text('Alle')),
                    ButtonSegment(value: 'Aktiv', label: Text('Aktiv')),
                    ButtonSegment(value: 'Jugend', label: Text('Jugend')),
                    ButtonSegment(value: 'Prüfung', label: Text('Requests')),
                  ],
                  selected: {teamFilter},
                  onSelectionChanged: (value) =>
                      setState(() => teamFilter = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('ROLLENRECHTE'),
                const SizedBox(height: 8),
                _RoleSwitch(
                  title: 'Trainer dürfen Kader bearbeiten',
                  value: trainerCanEdit,
                  color: AirmiusColors.blue,
                  onChanged: (value) => setState(() => trainerCanEdit = value),
                ),
                _RoleSwitch(
                  title: 'Captains dürfen einladen',
                  value: captainCanInvite,
                  color: AirmiusColors.green,
                  onChanged: (value) =>
                      setState(() => captainCanInvite = value),
                ),
                _RoleSwitch(
                  title: 'Mitglieder sehen Kader',
                  value: membersCanSeeRoster,
                  color: AirmiusColors.amber,
                  onChanged: (value) =>
                      setState(() => membersCanSeeRoster = value),
                ),
                _RoleSwitch(
                  title: 'Join-Requests erlauben',
                  value: joinRequestsEnabled,
                  color: AirmiusColors.pink,
                  onChanged: (value) =>
                      setState(() => joinRequestsEnabled = value),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          if (filtered.isEmpty)
            const EmptyPanel('Keine Teams für diesen Filter.')
          else
            for (final team in filtered) ...[
              _TeamCard(team: team),
              const SizedBox(height: 12),
            ],
        ],
      ),
    );
  }
}

class _TeamRow {
  const _TeamRow({
    required this.name,
    required this.meta,
    required this.status,
    required this.body,
    required this.color,
  });

  final String name;
  final String meta;
  final String status;
  final String body;
  final Color color;
}

class _RoleSwitch extends StatelessWidget {
  const _RoleSwitch({
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
        style: const TextStyle(
          color: AirmiusColors.text,
          fontWeight: FontWeight.w900,
        ),
      ),
      value: value,
      activeThumbColor: color,
      onChanged: onChanged,
    );
  }
}

class _TeamCard extends StatelessWidget {
  const _TeamCard({required this.team});

  final _TeamRow team;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon: Icons.groups_2_outlined, color: team.color),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            team.name,
                            style: const TextStyle(
                              color: AirmiusColors.text,
                              fontSize: 17,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        StatusPill(team.status, color: team.color),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      team.meta,
                      style: const TextStyle(
                        color: AirmiusColors.blue,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      team.body,
                      style: const TextStyle(
                        color: AirmiusColors.muted,
                        height: 1.42,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(
                label: 'Kader bearbeiten',
                icon: Icons.manage_accounts_outlined,
                onPressed: () => openUiAction(
                  context,
                  title: 'Kader bearbeiten',
                  body:
                      'Diese UI bereitet Mitgliederzuweisung, Rollen, Trainer, Captains und Teamrechte für die spätere API vor.',
                  status: 'UI vorbereitet',
                  icon: Icons.manage_accounts_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Join-Request',
                icon: Icons.person_add_alt_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Join-Request prüfen',
                  body:
                      'Join-Requests können später angenommen, abgelehnt oder mit Rückfrage versehen werden.',
                  status: 'UI vorbereitet',
                  icon: Icons.person_add_alt_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Teamrechte',
                icon: Icons.verified_user_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Teamrechte',
                  body:
                      'Trainer-, Captain-, Mitglieder- und Guardian-Rechte werden als mobile Rollensteuerung vorbereitet.',
                  status: 'UI vorbereitet',
                  icon: Icons.verified_user_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
