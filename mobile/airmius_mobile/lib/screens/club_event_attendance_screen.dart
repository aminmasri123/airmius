import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_member_directory_screen.dart';
import 'club_team_admin_screen.dart';
import 'notification_chat_operations_screen.dart';
import 'training_operations_screen.dart';

class ClubEventAttendanceScreen extends StatefulWidget {
  const ClubEventAttendanceScreen({super.key, this.initialTab = 'Alle'});

  final String initialTab;

  @override
  State<ClubEventAttendanceScreen> createState() => _ClubEventAttendanceScreenState();
}

class _ClubEventAttendanceScreenState extends State<ClubEventAttendanceScreen> {
  late String _tab = widget.initialTab;
  bool _showWaitlist = true;
  bool _showAttendance = true;
  bool _showReminders = true;
  bool _showCancellations = true;

  static const _tabs = ['Alle', 'Events', 'Training', 'Warteliste', 'Anwesenheit', 'Abgesagt'];

  final List<_ClubEvent> _events = const [
    _ClubEvent(
      area: 'Events',
      title: 'Saisonauftakt ZBB',
      team: 'Herren Aktiv',
      body: 'Öffentliches Vereins-Event mit Anmeldung, Teilnehmerlimit, Check-in und Eventchat.',
      status: 'Anmeldung offen',
      participants: '28 / 40',
      waitlist: '0',
      attendance: 'Check-in offen',
      date: 'Sa, 15.06. - 16:00',
      location: 'Sporthalle Kleinblittersdorf',
      icon: Icons.celebration_outlined,
      color: AirmiusColors.blue,
    ),
    _ClubEvent(
      area: 'Training',
      title: 'Freitagstraining',
      team: 'Laufgruppe',
      body: 'Regeltermin mit Team-Zuordnung, Trainerhinweis, Teilnehmerstatus und Push-Erinnerung.',
      status: '18 bestätigt',
      participants: '18 / 20',
      waitlist: '2',
      attendance: '12 anwesend',
      date: 'Fr, 21.06. - 18:30',
      location: 'Airmius Running Track',
      icon: Icons.directions_run_outlined,
      color: AirmiusColors.green,
    ),
    _ClubEvent(
      area: 'Warteliste',
      title: 'Probetraining Jugend',
      team: 'U16 Jugend',
      body: 'Mitglieder und Interessenten können nachrücken, sobald ein Platz frei wird.',
      status: 'Ausgebucht',
      participants: '10 / 10',
      waitlist: '5',
      attendance: 'Vorbereitet',
      date: 'Mi, 26.06. - 17:00',
      location: 'Nebenplatz 2',
      icon: Icons.playlist_add_check_circle_outlined,
      color: AirmiusColors.amber,
    ),
    _ClubEvent(
      area: 'Anwesenheit',
      title: 'U16 Techniktraining',
      team: 'U16 Jugend',
      body: 'Anwesenheit mit Trainerfreigabe, Elternkontakt, Notfallinfo und Nachtrag.',
      status: 'Check-in läuft',
      participants: '14 / 18',
      waitlist: '1',
      attendance: '8 eingecheckt',
      date: 'Di, 02.07. - 17:30',
      location: 'Halle 1',
      icon: Icons.fact_check_outlined,
      color: AirmiusColors.blueDeep,
    ),
    _ClubEvent(
      area: 'Abgesagt',
      title: 'Hallenzeit Sonntag',
      team: 'Freies Spiel',
      body: 'Absagegrund, automatische Information, Rückzahlungshinweis und Ersatztermin vorbereitet.',
      status: 'Abgesagt',
      participants: '0 / 24',
      waitlist: '0',
      attendance: 'Geschlossen',
      date: 'So, 07.07. - 11:00',
      location: 'Halle West',
      icon: Icons.event_busy_outlined,
      color: AirmiusColors.red,
    ),
  ];

  List<_ClubEvent> get _visibleEvents {
    return _events.where((event) {
      if (_tab != 'Alle' && event.area != _tab) return false;
      if (!_showWaitlist && event.waitlist != '0') return false;
      if (!_showAttendance && event.area == 'Anwesenheit') return false;
      if (!_showCancellations && event.area == 'Abgesagt') return false;
      return true;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final visibleEvents = _visibleEvents;

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const PageTitle(
                          title: 'Events & Anwesenheit',
                          subtitle: 'Kalender, Training, Teilnehmerstatus, Warteliste, Erinnerungen und Absagen.',
                        ),
                        const SizedBox(height: 16),
                        _EventHero(onCreate: () => _toast('Event erstellen vorbereitet')),
                        const SizedBox(height: 16),
                        _TabRail(tabs: _tabs, value: _tab, onChanged: (value) => setState(() => _tab = value)),
                        const SizedBox(height: 16),
                        _ControlPanel(
                          showWaitlist: _showWaitlist,
                          showAttendance: _showAttendance,
                          showReminders: _showReminders,
                          showCancellations: _showCancellations,
                          onWaitlist: (value) => setState(() => _showWaitlist = value),
                          onAttendance: (value) => setState(() => _showAttendance = value),
                          onReminders: (value) => setState(() => _showReminders = value),
                          onCancellations: (value) => setState(() => _showCancellations = value),
                        ),
                        const SizedBox(height: 16),
                        for (final event in visibleEvents) ...[
                          _EventCard(event: event, onAction: _handleAction),
                          const SizedBox(height: 12),
                        ],
                        if (visibleEvents.isEmpty)
                          const EmptyPanel('Keine Events für diese Filter gefunden.'),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              const Eyebrow('Verknuepfte Vereinsbereiche'),
                              const SizedBox(height: 10),
                              Wrap(
                                spacing: 10,
                                runSpacing: 10,
                                children: [
                                  AirmiusButton(
                                    label: 'Teamverwaltung',
                                    icon: Icons.groups_2_outlined,
                                    secondary: true,
                                    onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubTeamAdminScreen())),
                                  ),
                                  AirmiusButton(
                                    label: 'Mitglieder',
                                    icon: Icons.badge_outlined,
                                    secondary: true,
                                    onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMemberDirectoryScreen())),
                                  ),
                                  AirmiusButton(
                                    label: 'Training',
                                    icon: Icons.fitness_center_outlined,
                                    secondary: true,
                                    onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TrainingOperationsScreen())),
                                  ),
                                  AirmiusButton(
                                    label: 'Eventchat',
                                    icon: Icons.forum_outlined,
                                    secondary: true,
                                    onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Chat'))),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _handleAction(String action, _ClubEvent event) {
    if (action == 'team') {
      Navigator.push(context, MaterialPageRoute(builder: (_) => ClubTeamAdminScreen()));
      return;
    }
    if (action == 'reminder') {
      Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Push')));
      return;
    }
    if (action == 'attendance') {
      _toast('Anwesenheit für ${event.title} vorbereitet');
      return;
    }
    _toast('${event.title}: Detailansicht vorbereitet');
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _EventHero extends StatelessWidget {
  const _EventHero({required this.onCreate});

  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF172235), Color(0xFF0B111B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AirmiusColors.borderStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusLogo(size: 42),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow('VEREINSKALENDER'),
                    SizedBox(height: 4),
                    Text('Events wie in der Web-App verwalten', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900)),
                  ],
                ),
              ),
              AirmiusButton(label: 'Neu', icon: Icons.add_circle_outline, onPressed: onCreate),
            ],
          ),
          const SizedBox(height: 14),
          const Text(
            'Admins können Termine, Trainings, Anmeldungen, Wartelisten, Erinnerungen, Absagen und Anwesenheit mobil steuern.',
            style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 16),
          const Row(
            children: [
              Expanded(child: MetricCard(value: '12', label: 'Events')),
              SizedBox(width: 10),
              Expanded(child: MetricCard(value: '34', label: 'Teilnehmer')),
              SizedBox(width: 10),
              Expanded(child: MetricCard(value: '3', label: 'Warteliste')),
            ],
          ),
        ],
      ),
    );
  }
}

class _TabRail extends StatelessWidget {
  const _TabRail({required this.tabs, required this.value, required this.onChanged});

  final List<String> tabs;
  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final tab in tabs) ...[
            ChoiceChip(
              label: Text(tab),
              selected: value == tab,
              onSelected: (_) => onChanged(tab),
              selectedColor: AirmiusColors.blue.withValues(alpha: .24),
              backgroundColor: AirmiusColors.card,
              labelStyle: TextStyle(color: value == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
              side: BorderSide(color: value == tab ? AirmiusColors.blue : AirmiusColors.border),
            ),
            const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _ControlPanel extends StatelessWidget {
  const _ControlPanel({
    required this.showWaitlist,
    required this.showAttendance,
    required this.showReminders,
    required this.showCancellations,
    required this.onWaitlist,
    required this.onAttendance,
    required this.onReminders,
    required this.onCancellations,
  });

  final bool showWaitlist;
  final bool showAttendance;
  final bool showReminders;
  final bool showCancellations;
  final ValueChanged<bool> onWaitlist;
  final ValueChanged<bool> onAttendance;
  final ValueChanged<bool> onReminders;
  final ValueChanged<bool> onCancellations;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        children: [
          const Eyebrow('Event-Regeln'),
          const SizedBox(height: 12),
          _SwitchRow(title: 'Warteliste anzeigen', subtitle: 'Nachrücker und volle Termine bleiben sichtbar.', value: showWaitlist, onChanged: onWaitlist),
          _SwitchRow(title: 'Anwesenheit erfassen', subtitle: 'Trainer können Check-ins und Nachtraege pflegen.', value: showAttendance, onChanged: onAttendance),
          _SwitchRow(title: 'Erinnerungen aktiv', subtitle: 'Push, Chat und E-Mail sind als mobile Aktion vorbereitet.', value: showReminders, onChanged: onReminders),
          _SwitchRow(title: 'Absagen sichtbar', subtitle: 'Abgesagte Termine zeigen Grund, Ersatztermin und Hinweise.', value: showCancellations, onChanged: onCancellations),
        ],
      ),
    );
  }
}

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event, required this.onAction});

  final _ClubEvent event;
  final void Function(String action, _ClubEvent event) onAction;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('Event'),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(color: event.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: event.color.withValues(alpha: .5))),
                child: Icon(event.icon, color: event.color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(event.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text(event.team, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
                  ],
                ),
              ),
              StatusPill(event.status, color: event.color),
            ],
          ),
          const SizedBox(height: 12),
          Text(event.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _InfoChip(icon: Icons.schedule_outlined, label: event.date),
              _InfoChip(icon: Icons.place_outlined, label: event.location),
              _InfoChip(icon: Icons.people_alt_outlined, label: event.participants),
              _InfoChip(icon: Icons.playlist_add_outlined, label: '${event.waitlist} Warteliste'),
              _InfoChip(icon: Icons.fact_check_outlined, label: event.attendance),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(label: 'Details', icon: Icons.open_in_new_outlined, secondary: true, onPressed: () => onAction('details', event)),
              AirmiusButton(label: 'Anwesenheit', icon: Icons.checklist_outlined, secondary: true, onPressed: () => onAction('attendance', event)),
              AirmiusButton(label: 'Erinnern', icon: Icons.notifications_active_outlined, secondary: true, onPressed: () => onAction('reminder', event)),
              AirmiusButton(label: 'Team', icon: Icons.groups_2_outlined, secondary: true, onPressed: () => onAction('team', event)),
            ],
          ),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.title, required this.subtitle, required this.value, required this.onChanged});

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
          Switch.adaptive(value: value, onChanged: onChanged, activeColor: AirmiusColors.blue),
        ],
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(999), border: Border.all(color: AirmiusColors.border)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: AirmiusColors.blue),
          const SizedBox(width: 6),
          Text(label, style: const TextStyle(color: AirmiusColors.text, fontSize: 12, fontWeight: FontWeight.w900)),
        ],
      ),
    );
  }
}

class _ClubEvent {
  const _ClubEvent({
    required this.area,
    required this.title,
    required this.team,
    required this.body,
    required this.status,
    required this.participants,
    required this.waitlist,
    required this.attendance,
    required this.date,
    required this.location,
    required this.icon,
    required this.color,
  });

  final String area;
  final String title;
  final String team;
  final String body;
  final String status;
  final String participants;
  final String waitlist;
  final String attendance;
  final String date;
  final String location;
  final IconData icon;
  final Color color;
}
