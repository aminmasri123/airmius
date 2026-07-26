import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class AvailabilityAbsencePlanningSuiteScreen extends StatefulWidget {
  const AvailabilityAbsencePlanningSuiteScreen({super.key});

  @override
  State<AvailabilityAbsencePlanningSuiteScreen> createState() =>
      _AvailabilityAbsencePlanningSuiteScreenState();
}

class _AvailabilityAbsencePlanningSuiteScreenState
    extends State<AvailabilityAbsencePlanningSuiteScreen> {
  String _scope = 'Training';
  bool _selfReport = true;
  bool _guardianReport = true;
  bool _coachOverview = true;
  bool _healthNotes = true;

  @override
  Widget build(BuildContext context) {
    final entries = _entries
        .where((entry) => _scope == 'Alle' || entry.scope == _scope)
        .toList();

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'Verfuegbarkeit',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Availability Absence Planning',
        subtitle:
            'Mobile UI für Verfuegbarkeit, Abwesenheit, Trainerübersicht, Guardian-Meldungen, Gesundheitsnotizen und Anwesenheits-Sync.',
        trailing: StatusPill(
          'Planning',
          color: Theme.of(context).colorScheme.secondary,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('TEAM AVAILABILITY'),
                  const SizedBox(height: 8),
                  Text(
                    'Trainer sehen frueh, wer wirklich kommt.',
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Die App bereitet Verfuegbarkeiten, Absagen, Guardian-Meldungen, Verletzungshinweise und Teamplanung für Training, Events und Spiele vor.',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.42,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children:
                        [
                          'Alle',
                          'Training',
                          'Event',
                          'Spiel',
                          'Team',
                          'Guardian',
                          'Gesundheit',
                        ].map((item) {
                          return ChoiceChip(
                            selected: _scope == item,
                            label: Text(item),
                            onSelected: (_) => setState(() => _scope = item),
                            selectedColor: airmiusAccentColor(
                              context,
                            ).withValues(alpha: .22),
                            backgroundColor: airmiusSurfaceSoftColor(context),
                            side: BorderSide(
                              color: _scope == item
                                  ? airmiusAccentColor(context)
                                  : airmiusBorderColor(context),
                            ),
                            labelStyle: TextStyle(
                              color: _scope == item
                                  ? airmiusTextColor(context)
                                  : airmiusMutedColor(context),
                              fontWeight: FontWeight.w900,
                            ),
                          );
                        }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(
                  child: MetricCard(value: '18', label: 'Zugesagt'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '4', label: 'Fehlen'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '2', label: 'Offen'),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      const Expanded(child: Eyebrow('PLANUNGSREGELN')),
                      StatusPill(_scope, color: airmiusAccentColor(context)),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _PlanningToggle(
                    icon: Icons.how_to_reg_outlined,
                    title: 'Selbstmeldung',
                    body:
                        'Mitglieder können Zusage, Absage, vielleicht und später antworten direkt mobil melden.',
                    enabled: _selfReport,
                    onChanged: (value) => setState(() => _selfReport = value),
                  ),
                  _PlanningToggle(
                    icon: Icons.family_restroom_outlined,
                    title: 'Guardian-Meldung',
                    body:
                        'Eltern oder Guardians können Minderjaehrige abmelden und kurze Hinweise für Trainer hinterlegen.',
                    enabled: _guardianReport,
                    onChanged: (value) =>
                        setState(() => _guardianReport = value),
                  ),
                  _PlanningToggle(
                    icon: Icons.groups_2_outlined,
                    title: 'Trainerübersicht',
                    body:
                        'Trainer sehen Teamstatus, offene Antworten, Konflikte, Mindeststaerke und Anwesenheitsprognose.',
                    enabled: _coachOverview,
                    onChanged: (value) =>
                        setState(() => _coachOverview = value),
                  ),
                  _PlanningToggle(
                    icon: Icons.health_and_safety_outlined,
                    title: 'Gesundheitsnotizen',
                    body:
                        'Verletzung, Krankheit, Schonung oder Notfallhinweis bleiben datensparsam und rollenbasiert sichtbar.',
                    enabled: _healthNotes,
                    onChanged: (value) => setState(() => _healthNotes = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final entry in entries) ...[
              _AvailabilityCard(entry: entry),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: Theme.of(
                context,
              ).colorScheme.secondary.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('TRAINER SNAPSHOT'),
                  const SizedBox(height: 8),
                  Text(
                    'U16 Training ist planbar: 18 Zusagen, 4 Absagen, 2 offen. Mindeststaerke erreicht, aber Torwart fehlt.',
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontWeight: FontWeight.w900,
                      height: 1.38,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      StatusPill(
                        'Mindeststaerke OK',
                        color: Theme.of(context).colorScheme.secondary,
                      ),
                      StatusPill(
                        'Torwart fehlt',
                        color: Theme.of(context).colorScheme.tertiary,
                      ),
                      StatusPill(
                        '2 offen',
                        color: Theme.of(context).colorScheme.primary,
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label: 'Team erinnern',
                    icon: Icons.notifications_active_outlined,
                    onPressed: () {},
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('API AVAILABILITY PAYLOAD'),
                  const SizedBox(height: 10),
                  const _PayloadLine(
                    label: 'scope',
                    value: 'training, event, match, team, guardian, health',
                  ),
                  const _PayloadLine(
                    label: 'status',
                    value: 'available, absent, maybe, late, no_response',
                  ),
                  const _PayloadLine(
                    label: 'visibility',
                    value:
                        'member, coach, guardian, club_admin, emergency_only',
                  ),
                  const _PayloadLine(
                    label: 'sync',
                    value: 'attendance, checkin, calendar, notification, audit',
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AvailabilityEntry {
  const _AvailabilityEntry({
    required this.scope,
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
    required this.color,
  });

  final String scope;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}

const _entries = [
  _AvailabilityEntry(
    scope: 'Training',
    title: 'U16 Training Dienstag',
    body: '18 Zusagen, 4 Absagen, 2 offene Antworten und ein Rollen-Konflikt.',
    status: 'Planbar',
    icon: Icons.event_available_outlined,
    color: AirmiusColors.green,
  ),
  _AvailabilityEntry(
    scope: 'Training',
    title: 'Torwart fehlt',
    body:
        'Trainerhinweis: Mindestposition nicht besetzt, Erinnerung an Ersatzspieler empfohlen.',
    status: 'Konflikt',
    icon: Icons.sports_soccer_outlined,
    color: AirmiusColors.amber,
  ),
  _AvailabilityEntry(
    scope: 'Event',
    title: 'Sommerfest Helferplan',
    body:
        'Helfer, Aufbau, Kasse und Abbau können als Verfuegbarkeits-Slots geplant werden.',
    status: 'Slots',
    icon: Icons.celebration_outlined,
    color: AirmiusColors.blue,
  ),
  _AvailabilityEntry(
    scope: 'Spiel',
    title: 'Auswaertsspiel Samstag',
    body:
        'Fahrgemeinschaft, Treffpunkt, Kader, Guardian-Freigabe und Abwesenheiten verknuepft.',
    status: 'Kader',
    icon: Icons.emoji_events_outlined,
    color: AirmiusColors.green,
  ),
  _AvailabilityEntry(
    scope: 'Team',
    title: 'Team U14',
    body:
        'Trainer sieht Wochenübersicht, offene Rückmeldungen und wiederkehrende Abwesenheiten.',
    status: 'Team',
    icon: Icons.groups_2_outlined,
    color: AirmiusColors.blue,
  ),
  _AvailabilityEntry(
    scope: 'Guardian',
    title: 'Elternmeldung Krankheit',
    body:
        'Guardian meldet Abwesenheit und optionale Rückkehrprognose für Minderjaehrigen.',
    status: 'Privat',
    icon: Icons.family_restroom_outlined,
    color: AirmiusColors.amber,
  ),
  _AvailabilityEntry(
    scope: 'Gesundheit',
    title: 'Schonung Knie',
    body:
        'Trainer sieht nur relevanten Trainingshinweis, keine sensiblen Details.',
    status: 'Limited',
    icon: Icons.health_and_safety_outlined,
    color: AirmiusColors.green,
  ),
];

class _AvailabilityCard extends StatelessWidget {
  const _AvailabilityCard({required this.entry});

  final _AvailabilityEntry entry;

  @override
  Widget build(BuildContext context) {
    final entryColor = airmiusSemanticColor(context, entry.color);
    return AirmiusPanel(
      borderColor: entryColor.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: entryColor.withValues(alpha: .14),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: entryColor.withValues(alpha: .42)),
            ),
            child: Icon(entry.icon, color: entryColor),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        entry.title,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    StatusPill(entry.status, color: entryColor),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  entry.body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 9),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(entry.scope, color: entryColor),
                    const StatusPill('Sync'),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PlanningToggle extends StatelessWidget {
  const _PlanningToggle({
    required this.icon,
    required this.title,
    required this.body,
    required this.enabled,
    required this.onChanged,
    this.last = false,
  });

  final IconData icon;
  final String title;
  final String body;
  final bool enabled;
  final ValueChanged<bool> onChanged;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            icon,
            color: enabled
                ? Theme.of(context).colorScheme.secondary
                : airmiusMutedColor(context),
          ),
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
              ],
            ),
          ),
          Switch(
            value: enabled,
            activeThumbColor: Theme.of(context).colorScheme.secondary,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }
}

class _PayloadLine extends StatelessWidget {
  const _PayloadLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: airmiusSurfaceSoftColor(context),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: airmiusBorderColor(context)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 112,
              child: Text(
                label,
                style: TextStyle(
                  color: airmiusAccentColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            Expanded(
              child: Text(
                value,
                style: TextStyle(
                  color: airmiusTextColor(context),
                  height: 1.35,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
