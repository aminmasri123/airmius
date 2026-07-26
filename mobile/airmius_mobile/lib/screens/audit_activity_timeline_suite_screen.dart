import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class AuditActivityTimelineSuiteScreen extends StatefulWidget {
  const AuditActivityTimelineSuiteScreen({super.key});

  @override
  State<AuditActivityTimelineSuiteScreen> createState() =>
      _AuditActivityTimelineSuiteScreenState();
}

class _AuditActivityTimelineSuiteScreenState
    extends State<AuditActivityTimelineSuiteScreen> {
  String _filter = 'Alle';
  bool _clubEvents = true;
  bool _memberEvents = true;
  bool _financeEvents = true;
  bool _securityEvents = true;

  @override
  Widget build(BuildContext context) {
    final events = _events
        .where((event) => _filter == 'Alle' || event.area == _filter)
        .toList();

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'Audit Timeline',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Audit Activity Timeline',
        subtitle:
            'Mobile Web-App-UI für Aktivitaeten, Sicherheitsereignisse, Vereinsaktionen, Exporte und Admin-Audit.',
        trailing: StatusPill(
          'Audit',
          color: Theme.of(context).colorScheme.tertiary,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('ACTIVITY LOG'),
                  const SizedBox(height: 8),
                  Text(
                    'Jede wichtige Aktion bleibt nachvollziehbar.',
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Die Flutter-App bereitet eine klare Timeline für Mitgliedsanträge, Vereinsdaten, Dokumente, Zahlungen, Rollen, Moderation und Sicherheitsereignisse vor.',
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
                          'Verein',
                          'Mitglied',
                          'Finanzen',
                          'Security',
                          'Admin',
                        ].map((item) {
                          return ChoiceChip(
                            selected: _filter == item,
                            label: Text(item),
                            onSelected: (_) => setState(() => _filter = item),
                            selectedColor: airmiusAccentColor(
                              context,
                            ).withValues(alpha: .22),
                            backgroundColor: airmiusSurfaceSoftColor(context),
                            side: BorderSide(
                              color: _filter == item
                                  ? airmiusAccentColor(context)
                                  : airmiusBorderColor(context),
                            ),
                            labelStyle: TextStyle(
                              color: _filter == item
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
                  child: MetricCard(value: '24', label: 'Heute'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '6', label: 'Typen'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: 'CSV', label: 'Export'),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('LOG-KATEGORIEN'),
                  const SizedBox(height: 12),
                  _LogToggle(
                    icon: Icons.apartment_outlined,
                    title: 'Vereinsaktionen',
                    body:
                        'Profil, Sichtbarkeit, Teams, Dokumente, Regeln und Beitragskonfiguration.',
                    enabled: _clubEvents,
                    onChanged: (value) => setState(() => _clubEvents = value),
                  ),
                  _LogToggle(
                    icon: Icons.assignment_ind_outlined,
                    title: 'Mitgliedsanträge',
                    body:
                        'Anfrage gesendet, Rückzug, Rückfrage, Entscheidung, Teamzuweisung und Onboarding.',
                    enabled: _memberEvents,
                    onChanged: (value) => setState(() => _memberEvents = value),
                  ),
                  _LogToggle(
                    icon: Icons.receipt_long_outlined,
                    title: 'Finanzen',
                    body:
                        'Beiträge, Rechnungen, Zahlungsstatus, Mahnungen, SEPA und Rückerstattungen.',
                    enabled: _financeEvents,
                    onChanged: (value) =>
                        setState(() => _financeEvents = value),
                  ),
                  _LogToggle(
                    icon: Icons.security_outlined,
                    title: 'Security & Admin',
                    body:
                        'Login, 2FA, Rollenwechsel, Sperren, Moderation, Datenschutzanfragen und Exporte.',
                    enabled: _securityEvents,
                    onChanged: (value) =>
                        setState(() => _securityEvents = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final event in events) ...[
              _EventCard(event: event),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: Theme.of(
                context,
              ).colorScheme.secondary.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('EXPORT & AUFBEWAHRUNG'),
                  const SizedBox(height: 8),
                  Text(
                    'Auditdaten können später nach Rolle exportiert, zeitlich begrenzt aufbewahrt und für Datenschutz- oder Vereinsnachweise gefiltert werden.',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.38,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      StatusPill(
                        'Role scoped',
                        color: Theme.of(context).colorScheme.primary,
                      ),
                      StatusPill(
                        'Retention',
                        color: Theme.of(context).colorScheme.tertiary,
                      ),
                      StatusPill(
                        'Export ready',
                        color: Theme.of(context).colorScheme.secondary,
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label: 'Audit exportieren',
                    icon: Icons.download_outlined,
                    onPressed: () {},
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

class _AuditEvent {
  const _AuditEvent({
    required this.area,
    required this.title,
    required this.actor,
    required this.time,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String area;
  final String title;
  final String actor;
  final String time;
  final String body;
  final IconData icon;
  final Color color;
}

const _events = [
  _AuditEvent(
    area: 'Verein',
    title: 'Sichtbarkeit geändert',
    actor: 'verein airmius',
    time: '09:12',
    body:
        'Kontaktbereich und Dokumente wurden für das öffentliche Vereinsprofil aktiviert.',
    icon: Icons.visibility_outlined,
    color: AirmiusColors.blue,
  ),
  _AuditEvent(
    area: 'Mitglied',
    title: 'Mitgliedsanfrage gesendet',
    actor: 'ZBB Konto',
    time: '09:28',
    body:
        'Dynamisches Formular wurde mit Personendaten, Wohndaten und Datenschutzbestätigung eingereicht.',
    icon: Icons.assignment_add,
    color: AirmiusColors.green,
  ),
  _AuditEvent(
    area: 'Mitglied',
    title: 'Anfrage zurückgezogen',
    actor: 'ZBB Konto',
    time: '09:43',
    body:
        'Der Antrag wurde vor der Admin-Entscheidung zurückgezogen und im Vereins-Postfach markiert.',
    icon: Icons.undo_outlined,
    color: AirmiusColors.amber,
  ),
  _AuditEvent(
    area: 'Finanzen',
    title: 'Beitragsregel aktualisiert',
    actor: 'Club Admin',
    time: '10:05',
    body:
        'Zahlungsrhythmus wurde auf monatlich gesetzt, Barzahlung und Überweisung bleiben erlaubt.',
    icon: Icons.receipt_long_outlined,
    color: AirmiusColors.amber,
  ),
  _AuditEvent(
    area: 'Security',
    title: '2FA bestätigt',
    actor: 'ZBB Konto',
    time: '10:22',
    body: 'Sensible Kontoaktion wurde mit zweitem Faktor bestätigt.',
    icon: Icons.security_outlined,
    color: AirmiusColors.green,
  ),
  _AuditEvent(
    area: 'Admin',
    title: 'Moderationsfall geschlossen',
    actor: 'Platform Admin',
    time: '11:01',
    body:
        'Meldung wurde geprüft, Entscheidung dokumentiert und Audit-Hinweis gespeichert.',
    icon: Icons.admin_panel_settings_outlined,
    color: AirmiusColors.blue,
  ),
];

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event});

  final _AuditEvent event;

  @override
  Widget build(BuildContext context) {
    final eventColor = airmiusSemanticColor(context, event.color);
    return AirmiusPanel(
      borderColor: eventColor.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: eventColor.withValues(alpha: .14),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: eventColor.withValues(alpha: .4)),
            ),
            child: Icon(event.icon, color: eventColor),
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
                        event.title,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    StatusPill(event.area, color: eventColor),
                  ],
                ),
                const SizedBox(height: 5),
                Text(
                  event.body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 9),
                Text(
                  '${event.actor} · ${event.time}',
                  style: TextStyle(
                    color: airmiusAccentColor(context),
                    fontWeight: FontWeight.w800,
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

class _LogToggle extends StatelessWidget {
  const _LogToggle({
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
