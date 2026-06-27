import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class RoleHomeDashboardWidgetSuiteScreen extends StatefulWidget {
  const RoleHomeDashboardWidgetSuiteScreen({super.key});

  @override
  State<RoleHomeDashboardWidgetSuiteScreen> createState() => _RoleHomeDashboardWidgetSuiteScreenState();
}

class _RoleHomeDashboardWidgetSuiteScreenState extends State<RoleHomeDashboardWidgetSuiteScreen> {
  String _role = 'Mitglied';
  bool _tasks = true;
  bool _clubStatus = true;
  bool _training = true;
  bool _payments = true;

  @override
  Widget build(BuildContext context) {
    final widgets = _widgets.where((item) => _role == 'Alle' || item.role == _role).toList();

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Home Dashboard', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Role Home Dashboard Widgets',
        subtitle: 'Mobile Startseite für Rollen, Aufgaben, Statuskarten, Schnellaktionen und naechste Schritte im Web-App-Stil.',
        trailing: const StatusPill('Home', color: AirmiusColors.blue),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('APP HOME'),
                  const SizedBox(height: 8),
                  const Text(
                    'Jede Rolle landet auf einer passenden Startseite.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Die Flutter-App bereitet Home-Widgets für Mitglieder, Vereinsadmins, Trainer, Guardians und Plattformadmins vor: Aufgaben, Status, Termine, Zahlungen und Schnellaktionen.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Alle', 'Mitglied', 'Vereinsadmin', 'Trainer', 'Guardian', 'Platform'].map((item) {
                      return ChoiceChip(
                        selected: _role == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _role = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _role == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _role == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '7', label: 'Widgets')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '5', label: 'Rollen')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: 'Live', label: 'Home')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(children: [const Expanded(child: Eyebrow('HOME REGELN')), StatusPill(_role, color: AirmiusColors.blue)]),
                  const SizedBox(height: 12),
                  _HomeToggle(
                    icon: Icons.task_alt_outlined,
                    title: 'Aufgaben anzeigen',
                    body: 'Offene Antraege, Rückfragen, Formulare, Supportantworten und Admin-Entscheidungen erscheinen zuerst.',
                    enabled: _tasks,
                    onChanged: (value) => setState(() => _tasks = value),
                  ),
                  _HomeToggle(
                    icon: Icons.apartment_outlined,
                    title: 'Vereinsstatus',
                    body: 'Mitgliedschaften, offene Anfragen, Vereinsrollen, Sichtbarkeit und Dokumentstatus werden zusammengefasst.',
                    enabled: _clubStatus,
                    onChanged: (value) => setState(() => _clubStatus = value),
                  ),
                  _HomeToggle(
                    icon: Icons.event_available_outlined,
                    title: 'Training & Events',
                    body: 'Naechste Trainings, RSVP, Check-in, Fahrgemeinschaften und Erinnerungen landen direkt im Home.',
                    enabled: _training,
                    onChanged: (value) => setState(() => _training = value),
                  ),
                  _HomeToggle(
                    icon: Icons.receipt_long_outlined,
                    title: 'Zahlungen & Rechnungen',
                    body: 'Offene Beiträge, Rechnungen, Mahnungen und Zahlungsstatus werden rollenbasiert angezeigt.',
                    enabled: _payments,
                    onChanged: (value) => setState(() => _payments = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final widget in widgets) ...[
              _HomeWidgetCard(widget: widget),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('SCHNELLAKTIONEN'),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      StatusPill('Verein suchen', color: AirmiusColors.blue),
                      StatusPill('Antrag fortsetzen', color: AirmiusColors.green),
                      StatusPill('Training check-in', color: AirmiusColors.amber),
                      StatusPill('Nachricht lesen', color: AirmiusColors.blue),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'Dashboard personalisieren', icon: Icons.dashboard_customize_outlined, onPressed: () {}),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('API HOME PAYLOAD'),
                  const SizedBox(height: 10),
                  const _PayloadLine(label: 'role', value: 'member, club_admin, coach, guardian, platform_admin'),
                  const _PayloadLine(label: 'widgets', value: 'tasks, memberships, events, payments, messages, support'),
                  const _PayloadLine(label: 'priority', value: 'blocking_tasks, today, unread, overdue, recommended'),
                  const _PayloadLine(label: 'actions', value: 'open, complete, snooze, pin, hide, personalize'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HomeWidget {
  const _HomeWidget({required this.role, required this.title, required this.body, required this.status, required this.icon, required this.color});

  final String role;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}

const _widgets = [
  _HomeWidget(role: 'Mitglied', title: 'Meine Mitgliedschaften', body: 'Aktive Vereine, offene Anfragen, digitale Karte, Dokumente und naechste Schritte.', status: '2 aktiv', icon: Icons.badge_outlined, color: AirmiusColors.green),
  _HomeWidget(role: 'Mitglied', title: 'Heute für dich', body: 'Training um 18:00, offene Rechnung, neue Nachricht und Antrag-Draft.', status: '4 Tasks', icon: Icons.today_outlined, color: AirmiusColors.blue),
  _HomeWidget(role: 'Vereinsadmin', title: 'Anfrage-Eingang', body: 'Neue Mitgliedsanträge, Rückzuege, Dokumentstatus und Rückfragen.', status: '6 offen', icon: Icons.inbox_outlined, color: AirmiusColors.amber),
  _HomeWidget(role: 'Vereinsadmin', title: 'Vereins-Cockpit', body: 'Mitglieder, Teams, Beitragsstatus, Dokumente, Sichtbarkeit und Reports.', status: 'Admin', icon: Icons.apartment_outlined, color: AirmiusColors.blue),
  _HomeWidget(role: 'Trainer', title: 'Trainer-Cockpit', body: 'Training, Anwesenheit, Kader, Feedback, Teamchat und Check-in.', status: 'Live', icon: Icons.sports_outlined, color: AirmiusColors.green),
  _HomeWidget(role: 'Guardian', title: 'Guardian Aufgaben', body: 'Freigaben, Minderjaehrigenprofil, Notfallkontakt, Medienrechte und Event-Zustimmung.', status: '2 offen', icon: Icons.family_restroom_outlined, color: AirmiusColors.amber),
  _HomeWidget(role: 'Platform', title: 'Platform Admin', body: 'Moderation, Verifizierung, Incidents, Jobs, Webhooks, Reports und Audit.', status: 'Ops', icon: Icons.admin_panel_settings_outlined, color: AirmiusColors.blue),
];

class _HomeWidgetCard extends StatelessWidget {
  const _HomeWidgetCard({required this.widget});

  final _HomeWidget widget;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: widget.color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: widget.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: widget.color.withValues(alpha: .42))),
            child: Icon(widget.icon, color: widget.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(widget.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                    StatusPill(widget.status, color: widget.color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(widget.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 9),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(widget.role, color: widget.color), const StatusPill('Widget')]),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HomeToggle extends StatelessWidget {
  const _HomeToggle({required this.icon, required this.title, required this.body, required this.enabled, required this.onChanged, this.last = false});

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
          Icon(icon, color: enabled ? AirmiusColors.green : AirmiusColors.muted),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
          Switch(value: enabled, activeThumbColor: AirmiusColors.green, onChanged: onChanged),
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
        decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(width: 112, child: Text(label, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))),
            Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.text, height: 1.35))),
          ],
        ),
      ),
    );
  }
}
