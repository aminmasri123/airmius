import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class VolunteerShiftTaskPlannerSuiteScreen extends StatefulWidget {
  const VolunteerShiftTaskPlannerSuiteScreen({super.key});

  @override
  State<VolunteerShiftTaskPlannerSuiteScreen> createState() =>
      _VolunteerShiftTaskPlannerSuiteScreenState();
}

class _VolunteerShiftTaskPlannerSuiteScreenState
    extends State<VolunteerShiftTaskPlannerSuiteScreen> {
  String _scope = 'Event';
  bool _selfSignup = true;
  bool _shiftLimits = true;
  bool _reminders = true;
  bool _proofRequired = false;

  @override
  Widget build(BuildContext context) {
    final tasks = _tasks
        .where((task) => _scope == 'Alle' || task.scope == _scope)
        .toList();

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'Helfer & Aufgaben',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Volunteer Shift Task Planner',
        subtitle:
            'Mobile UI für Helferlisten, Schichten, Aufgaben, Erinnerungen, Rollenregeln und Nachweise bei Events und Vereinsbetrieb.',
        trailing: StatusPill(
          'Volunteer',
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
                  const Eyebrow('HELPER PLANNING'),
                  const SizedBox(height: 8),
                  Text(
                    'Vereinsarbeit wird sichtbar und planbar.',
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Die App bereitet Helferschichten, Aufgaben, Zusagen, Erinnerungen und Nachweise für Events, Training, Fahrdienste und Vereinsfeste vor.',
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
                          'Event',
                          'Training',
                          'Fahrdienst',
                          'Kasse',
                          'Aufbau',
                          'Abbau',
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
                  child: MetricCard(value: '12', label: 'Aufgaben'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '9', label: 'Besetzt'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '3', label: 'Offen'),
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
                      const Expanded(child: Eyebrow('HELFERREGELN')),
                      StatusPill(_scope, color: airmiusAccentColor(context)),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _VolunteerToggle(
                    icon: Icons.person_add_alt_1_outlined,
                    title: 'Selbst eintragen',
                    body:
                        'Mitglieder können sich für passende Schichten eintragen, absagen oder Ersatz vorschlagen.',
                    enabled: _selfSignup,
                    onChanged: (value) => setState(() => _selfSignup = value),
                  ),
                  _VolunteerToggle(
                    icon: Icons.schedule_outlined,
                    title: 'Schichtgrenzen',
                    body:
                        'Maximale Personen, Altersregeln, Rollenrechte und Zeitüberschneidungen werden vor dem Speichern geprüft.',
                    enabled: _shiftLimits,
                    onChanged: (value) => setState(() => _shiftLimits = value),
                  ),
                  _VolunteerToggle(
                    icon: Icons.notifications_active_outlined,
                    title: 'Erinnerungen',
                    body:
                        'Helfer bekommen Push, E-Mail oder In-App-Hinweise vor ihrer Schicht und bei Änderungen.',
                    enabled: _reminders,
                    onChanged: (value) => setState(() => _reminders = value),
                  ),
                  _VolunteerToggle(
                    icon: Icons.fact_check_outlined,
                    title: 'Nachweis erforderlich',
                    body:
                        'Optional können erledigte Aufgaben durch Trainer, Admin oder Check-in bestätigt werden.',
                    enabled: _proofRequired,
                    onChanged: (value) =>
                        setState(() => _proofRequired = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final task in tasks) ...[
              _VolunteerTaskCard(task: task),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: Theme.of(
                context,
              ).colorScheme.tertiary.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('OFFENE SCHICHTEN'),
                  const SizedBox(height: 8),
                  Text(
                    'Sommerfest hat noch drei offene Aufgaben: Kasse 16:00, Abbau 20:00 und Fahrdienst Rückweg. Vereinsadmin kann gezielt erinnern.',
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
                        '3 offen',
                        color: Theme.of(context).colorScheme.tertiary,
                      ),
                      StatusPill(
                        'Erinnerung bereit',
                        color: Theme.of(context).colorScheme.primary,
                      ),
                      StatusPill(
                        'Keine Konflikte',
                        color: Theme.of(context).colorScheme.secondary,
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label: 'Helfer erinnern',
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
                  const Eyebrow('API VOLUNTEER PAYLOAD'),
                  const SizedBox(height: 10),
                  const _PayloadLine(
                    label: 'scope',
                    value: 'event, training, ride, cashier, setup, teardown',
                  ),
                  const _PayloadLine(
                    label: 'assignment',
                    value:
                        'self_signup, admin_assign, substitute, guardian_confirm',
                  ),
                  const _PayloadLine(
                    label: 'rules',
                    value:
                        'role_scope, age_gate, max_helpers, conflict_check, proof_required',
                  ),
                  const _PayloadLine(
                    label: 'actions',
                    value: 'join, leave, remind, approve, mark_done, export',
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

class _VolunteerTask {
  const _VolunteerTask({
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

const _tasks = [
  _VolunteerTask(
    scope: 'Event',
    title: 'Sommerfest Kasse',
    body: 'Zwei Helfer für 16:00 bis 18:00, Kassenwart darf bestätigen.',
    status: '1/2',
    icon: Icons.point_of_sale_outlined,
    color: AirmiusColors.amber,
  ),
  _VolunteerTask(
    scope: 'Event',
    title: 'Einlass & QR Check',
    body:
        'Mitgliedskarten scannen, Gästeliste prüfen und Rückfragen an Admin senden.',
    status: 'Besetzt',
    icon: Icons.qr_code_2_outlined,
    color: AirmiusColors.green,
  ),
  _VolunteerTask(
    scope: 'Training',
    title: 'Material vorbereiten',
    body:
        'Baelle, Leibchen, Timing-System und Check-in-Liste vor Training bereitstellen.',
    status: 'Offen',
    icon: Icons.inventory_2_outlined,
    color: AirmiusColors.blue,
  ),
  _VolunteerTask(
    scope: 'Fahrdienst',
    title: 'Rückfahrt Auswaertsspiel',
    body: 'Fahrgemeinschaft für drei Mitglieder mit Guardian-Freigabe.',
    status: '2 Plaetze',
    icon: Icons.directions_car_outlined,
    color: AirmiusColors.green,
  ),
  _VolunteerTask(
    scope: 'Kasse',
    title: 'Belegnachweis',
    body:
        'Kassenaufgabe erfordert kurze Bestätigung und optionalen Beleg-Upload.',
    status: 'Proof',
    icon: Icons.receipt_long_outlined,
    color: AirmiusColors.amber,
  ),
  _VolunteerTask(
    scope: 'Aufbau',
    title: 'Zelte & Tische',
    body: 'Aufbau ab 12:00, mindestens vier Helfer, keine Altersbeschraenkung.',
    status: '3/4',
    icon: Icons.handyman_outlined,
    color: AirmiusColors.blue,
  ),
  _VolunteerTask(
    scope: 'Abbau',
    title: 'Abbau nach Event',
    body: '20:00 bis 21:00, Erinnerung an alle offenen Helfer aktiv.',
    status: 'Offen',
    icon: Icons.task_alt_outlined,
    color: AirmiusColors.amber,
  ),
];

class _VolunteerTaskCard extends StatelessWidget {
  const _VolunteerTaskCard({required this.task});

  final _VolunteerTask task;

  @override
  Widget build(BuildContext context) {
    final taskColor = airmiusSemanticColor(context, task.color);
    return AirmiusPanel(
      borderColor: taskColor.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: taskColor.withValues(alpha: .14),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: taskColor.withValues(alpha: .42)),
            ),
            child: Icon(task.icon, color: taskColor),
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
                        task.title,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    StatusPill(task.status, color: taskColor),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  task.body,
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
                    StatusPill(task.scope, color: taskColor),
                    const StatusPill('Shift'),
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

class _VolunteerToggle extends StatelessWidget {
  const _VolunteerToggle({
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
