import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class GamificationBadgeAchievementSuiteScreen extends StatefulWidget {
  const GamificationBadgeAchievementSuiteScreen({super.key});

  @override
  State<GamificationBadgeAchievementSuiteScreen> createState() => _GamificationBadgeAchievementSuiteScreenState();
}

class _GamificationBadgeAchievementSuiteScreenState extends State<GamificationBadgeAchievementSuiteScreen> {
  String scope = 'Verein';
  bool showPublicBadges = true;
  bool showLearningBadges = true;
  bool showTeamAchievements = true;
  bool notifyAchievements = true;

  @override
  Widget build(BuildContext context) {
    final badges = [
      const _BadgeRow(
        title: 'Neues Mitglied',
        status: 'Aktiv',
        body: 'Badge fuer erfolgreich angenommene Mitgliedschaft und abgeschlossenes Onboarding.',
        icon: Icons.badge_outlined,
        color: AirmiusColors.green,
      ),
      const _BadgeRow(
        title: 'Training Streak',
        status: 'Level 3',
        body: 'Auszeichnung fuer regelmaessige Teilnahme an Training und Events.',
        icon: Icons.local_fire_department_outlined,
        color: AirmiusColors.amber,
      ),
      const _BadgeRow(
        title: 'Datenschutz Kurs',
        status: 'Zertifikat',
        body: 'Badge fuer abgeschlossenen Kurs mit Quiz, Zertifikat und Profilnachweis.',
        icon: Icons.workspace_premium_outlined,
        color: AirmiusColors.blue,
      ),
      const _BadgeRow(
        title: 'Team Captain',
        status: 'Rolle',
        body: 'Rollenbadge fuer Captain-Rechte, Teamverantwortung und sichtbare Teamfunktion.',
        icon: Icons.military_tech_outlined,
        color: AirmiusColors.pink,
      ),
    ];

    return PageFrame(
      title: 'Badges & Erfolge',
      subtitle: 'Level, Rollen und Motivation',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('GAMIFICATION'),
                const SizedBox(height: 8),
                const Text(
                  'Badges, Rollen, Level und Erfolge machen Mitgliedschaft, Training, Kurse, Teamarbeit und Vereinsengagement mobil sichtbar.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '4', label: 'Badges'),
                    Metric(value: 'Level', label: 'Fortschritt'),
                    Metric(value: 'Role', label: 'Rollen'),
                    Metric(value: 'Push', label: 'Erfolg'),
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
                const SectionLabel('SICHTBARKEIT'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Privat', label: Text('Privat')),
                    ButtonSegment(value: 'Verein', label: Text('Verein')),
                    ButtonSegment(value: 'Team', label: Text('Team')),
                    ButtonSegment(value: 'Public', label: Text('Public')),
                  ],
                  selected: {scope},
                  onSelectionChanged: (value) => setState(() => scope = value.first),
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
                _BadgeSwitch(title: 'Badges im Profil anzeigen', value: showPublicBadges, color: AirmiusColors.green, onChanged: (value) => setState(() => showPublicBadges = value)),
                _BadgeSwitch(title: 'Lernbadges aktivieren', value: showLearningBadges, color: AirmiusColors.blue, onChanged: (value) => setState(() => showLearningBadges = value)),
                _BadgeSwitch(title: 'Team-Erfolge anzeigen', value: showTeamAchievements, color: AirmiusColors.amber, onChanged: (value) => setState(() => showTeamAchievements = value)),
                _BadgeSwitch(title: 'Erfolge benachrichtigen', value: notifyAchievements, color: AirmiusColors.pink, onChanged: (value) => setState(() => notifyAchievements = value)),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final badge in badges) ...[
            _BadgeCard(badge: badge),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Aktuelle Sichtbarkeit: $scope. Spaeter verbindet die API Badges, Rollen, Trainingsdaten, Lernzertifikate, Teamleistungen und Benachrichtigungen.',
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Erfolg anzeigen',
                  icon: Icons.emoji_events_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Erfolg anzeigen',
                    body: 'Diese UI bereitet Badge-Vergabe, Rollen-Erfolge, Fortschritt, Sichtbarkeit und Benachrichtigung fuer die spaetere Laravel-API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.emoji_events_outlined,
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

class _BadgeRow {
  const _BadgeRow({
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

class _BadgeSwitch extends StatelessWidget {
  const _BadgeSwitch({
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
      title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
      value: value,
      activeColor: color,
      onChanged: onChanged,
    );
  }
}

class _BadgeCard extends StatelessWidget {
  const _BadgeCard({required this.badge});

  final _BadgeRow badge;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(icon: badge.icon, color: badge.color),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(badge.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                    StatusPill(badge.status, color: badge.color),
                  ],
                ),
                const SizedBox(height: 8),
                Text(badge.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
