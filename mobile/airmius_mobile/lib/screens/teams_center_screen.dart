import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'team_detail_screen.dart';
import 'team_operations_screen.dart';

class TeamsCenterScreen extends StatefulWidget {
  const TeamsCenterScreen({super.key});

  @override
  State<TeamsCenterScreen> createState() => _TeamsCenterScreenState();
}

class _TeamsCenterScreenState extends State<TeamsCenterScreen> {
  String _tab = 'Kader';
  Future<AirmiusPage<AirmiusTeam>>? _teamsFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _teamsFuture ??= AirmiusServicesScope.of(context).repositories.clubs.teams();
  }

  void _reloadTeams() {
    setState(() {
      _teamsFuture = AirmiusServicesScope.of(context).repositories.clubs.teams();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Teams', style: TextStyle(fontWeight: FontWeight.w900))),
      body: FutureBuilder<AirmiusPage<AirmiusTeam>>(
        future: _teamsFuture,
        builder: (context, snapshot) {
          final teams = snapshot.data?.items ?? const <AirmiusTeam>[];
          final filteredTeams = _filterTeams(teams);
          return PageFrame(
            title: 'Teams',
            subtitle: 'Teamprofile, Kader, Rollen, Einladungen, Beitritte, Kalender und Teamchat',
            trailing: StatusPill('${teams.length} Teams'),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Eyebrow('Teamverwaltung'),
                const SizedBox(height: 8),
                const Text('Teams als eigene mobile Arbeitsbereiche.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
                const SizedBox(height: 8),
                const Text('Kader, Trainer, Captain, Einladungen, Teambeitritt, Trainingskalender, Dateien und Teamchat werden aus der Laravel-API als native App-Struktur angezeigt.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                const SizedBox(height: 14),
                Wrap(spacing: 8, runSpacing: 8, children: ['Kader', 'Einladungen', 'Kalender', 'Dateien', 'Chat', 'Alle'].map((item) => ChoiceChip(
                  selected: _tab == item,
                  label: Text(item),
                  onSelected: (_) => setState(() => _tab = item),
                  selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: _tab == item ? AirmiusColors.blue : AirmiusColors.border),
                  labelStyle: TextStyle(color: _tab == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                )).toList()),
              ])),
              const SizedBox(height: 14),
              Row(children: [
                Expanded(child: MetricCard(value: '${teams.length}', label: 'Teams')),
                const SizedBox(width: 10),
                Expanded(child: MetricCard(value: '${teams.where((team) => _text(team.visibility).toLowerCase().contains('public')).length}', label: 'Öffentlich')),
                const SizedBox(width: 10),
                Expanded(child: MetricCard(value: '${teams.where((team) => _text(team.ageGroup).isNotEmpty).length}', label: 'Altersgruppen')),
              ]),
              const SizedBox(height: 14),
              if (snapshot.connectionState == ConnectionState.waiting && teams.isEmpty) const AirmiusPanel(child: Center(child: Padding(padding: EdgeInsets.all(14), child: CircularProgressIndicator(color: AirmiusColors.blue)))),
              if (snapshot.hasError) AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Eyebrow('Teams konnten nicht geladen werden'),
                const SizedBox(height: 8),
                Text('${snapshot.error}', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 12),
                AirmiusButton(label: 'Neu laden', icon: Icons.refresh_outlined, onPressed: _reloadTeams),
              ])),
              if (!snapshot.hasError && snapshot.connectionState != ConnectionState.waiting && filteredTeams.isEmpty) AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Eyebrow('Keine Teams gefunden'),
                const SizedBox(height: 8),
                Text(_tab == 'Alle' ? 'In der API sind noch keine Teams für diese Ansicht vorhanden.' : 'Für "$_tab" gibt es aktuell keine passenden Teams.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 12),
                AirmiusButton(label: 'Alle Teams anzeigen', icon: Icons.groups_2_outlined, onPressed: () => setState(() => _tab = 'Alle')),
              ])),
              for (final team in filteredTeams) ...[
                _TeamLine(
                  team: team,
                  icon: _teamIcon(team),
                  title: team.name,
                  body: _teamBody(team),
                  status: _text(team.visibility, fallback: 'Team'),
                  color: _teamColor(team),
                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TeamDetailScreen(title: team.name, mode: _tab == 'Alle' ? 'Profil' : _tab, teamId: team.id, team: team))),
                ),
                const SizedBox(height: 12),
              ],
              const SizedBox(height: 14),
              AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Eyebrow('Team-Aktionen'),
                const SizedBox(height: 12),
                Wrap(spacing: 10, runSpacing: 10, children: [
                  AirmiusButton(label: 'Team erstellen', icon: Icons.group_add_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeamDetailScreen(title: 'Neues Team', mode: 'Profil')))),
                  AirmiusButton(label: 'Neu laden', icon: Icons.refresh_outlined, secondary: true, onPressed: _reloadTeams),
                  AirmiusButton(label: 'Kader verwalten', icon: Icons.badge_outlined, secondary: true, onPressed: () => setState(() => _tab = 'Kader')),
                  AirmiusButton(label: 'Einladung senden', icon: Icons.mark_email_read_outlined, secondary: true, onPressed: () => setState(() => _tab = 'Einladungen')),
                  AirmiusButton(label: 'Team Operations', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TeamOperationsScreen()))),
                ]),
              ])),
            ]),
          );
        },
      ),
    );
  }

  List<AirmiusTeam> _filterTeams(List<AirmiusTeam> teams) {
    if (_tab == 'Alle' || _tab == 'Kader') return teams;
    final needle = _tab.toLowerCase();
    return teams.where((team) {
      final haystack = '${team.name} ${team.description ?? ''} ${team.sportType ?? ''} ${team.ageGroup ?? ''} ${team.visibility ?? ''}'.toLowerCase();
      return haystack.contains(needle);
    }).toList();
  }

  String _teamBody(AirmiusTeam team) {
    final parts = [
      team.clubName,
      team.sportType,
      team.ageGroup,
      team.description,
    ].whereType<String>().where((value) => value.trim().isNotEmpty).toList();
    return parts.isEmpty ? 'Teamprofil, Kader, Rollen und Teamkommunikation.' : parts.join(' - ');
  }

  IconData _teamIcon(AirmiusTeam team) {
    final text = '${team.name} ${team.ageGroup ?? ''} ${team.description ?? ''}'.toLowerCase();
    if (text.contains('jugend') || text.contains('u1')) return Icons.family_restroom_outlined;
    if (text.contains('lauf') || text.contains('training')) return Icons.directions_run_outlined;
    return Icons.groups_2_outlined;
  }

  Color _teamColor(AirmiusTeam team) {
    final visibility = _text(team.visibility).toLowerCase();
    if (visibility.contains('public') || visibility.contains('offen')) return AirmiusColors.green;
    if (visibility.contains('private') || visibility.contains('intern')) return AirmiusColors.amber;
    return AirmiusColors.blue;
  }

  String _text(String? value, {String fallback = ''}) {
    final text = value?.trim();
    return text == null || text.isEmpty ? fallback : text;
  }
}

class _TeamLine extends StatelessWidget {
  const _TeamLine({required this.team, required this.icon, required this.title, required this.body, required this.status, required this.color, required this.onTap});

  final AirmiusTeam team;
  final IconData icon;
  final String title;
  final String body;
  final String status;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(onTap: onTap, borderColor: color.withValues(alpha: 0.45), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      AirmiusAvatar(title, imageUrl: team.logoUrl),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)), const SizedBox(height: 10), StatusPill(status, color: color)])),
      Icon(icon, color: color, size: 22),
      const SizedBox(width: 4),
      const Icon(Icons.chevron_right, color: AirmiusColors.muted),
    ]));
  }
}
