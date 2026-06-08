import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class AuditActivityTimelineSuiteScreen extends StatefulWidget {
  const AuditActivityTimelineSuiteScreen({super.key});

  @override
  State<AuditActivityTimelineSuiteScreen> createState() => _AuditActivityTimelineSuiteScreenState();
}

class _AuditActivityTimelineSuiteScreenState extends State<AuditActivityTimelineSuiteScreen> {
  String _filter = 'Alle';
  bool _clubEvents = true;
  bool _memberEvents = true;
  bool _financeEvents = true;
  bool _securityEvents = true;

  @override
  Widget build(BuildContext context) {
    final events = _events.where((event) => _filter == 'Alle' || event.area == _filter).toList();

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Audit Timeline', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Audit Activity Timeline',
        subtitle: 'Mobile Web-App-UI fuer Aktivitaeten, Sicherheitsereignisse, Vereinsaktionen, Exporte und Admin-Audit.',
        trailing: const StatusPill('Audit', color: AirmiusColors.amber),
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
                  const Text(
                    'Jede wichtige Aktion bleibt nachvollziehbar.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Die Flutter-App bereitet eine klare Timeline fuer Mitgliedsantraege, Vereinsdaten, Dokumente, Zahlungen, Rollen, Moderation und Sicherheitsereignisse vor.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Alle', 'Verein', 'Mitglied', 'Finanzen', 'Security', 'Admin'].map((item) {
                      return ChoiceChip(
                        selected: _filter == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _filter = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _filter == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _filter == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '24', label: 'Heute')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '6', label: 'Typen')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: 'CSV', label: 'Export')),
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
                    body: 'Profil, Sichtbarkeit, Teams, Dokumente, Regeln und Beitragskonfiguration.',
                    enabled: _clubEvents,
                    onChanged: (value) => setState(() => _clubEvents = value),
                  ),
                  _LogToggle(
                    icon: Icons.assignment_ind_outlined,
                    title: 'Mitgliedsantraege',
                    body: 'Anfrage gesendet, Rueckzug, Rueckfrage, Entscheidung, Teamzuweisung und Onboarding.',
                    enabled: _memberEvents,
                    onChanged: (value) => setState(() => _memberEvents = value),
                  ),
                  _LogToggle(
                    icon: Icons.receipt_long_outlined,
                    title: 'Finanzen',
                    body: 'Beitraege, Rechnungen, Zahlungsstatus, Mahnungen, SEPA und Rueckerstattungen.',
                    enabled: _financeEvents,
                    onChanged: (value) => setState(() => _financeEvents = value),
                  ),
                  _LogToggle(
                    icon: Icons.security_outlined,
                    title: 'Security & Admin',
                    body: 'Login, 2FA, Rollenwechsel, Sperren, Moderation, Datenschutzanfragen und Exporte.',
                    enabled: _securityEvents,
                    onChanged: (value) => setState(() => _securityEvents = value),
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
              borderColor: AirmiusColors.green.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('EXPORT & AUFBEWAHRUNG'),
                  const SizedBox(height: 8),
                  const Text('Auditdaten koennen spaeter nach Rolle exportiert, zeitlich begrenzt aufbewahrt und fuer Datenschutz- oder Vereinsnachweise gefiltert werden.', style: TextStyle(color: AirmiusColors.muted, height: 1.38)),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      StatusPill('Role scoped', color: AirmiusColors.blue),
                      StatusPill('Retention', color: AirmiusColors.amber),
                      StatusPill('Export ready', color: AirmiusColors.green),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'Audit exportieren', icon: Icons.download_outlined, onPressed: () {}),
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
  const _AuditEvent({required this.area, required this.title, required this.actor, required this.time, required this.body, required this.icon, required this.color});

  final String area;
  final String title;
  final String actor;
  final String time;
  final String body;
  final IconData icon;
  final Color color;
}

const _events = [
  _AuditEvent(area: 'Verein', title: 'Sichtbarkeit geaendert', actor: 'verein airmius', time: '09:12', body: 'Kontaktbereich und Dokumente wurden fuer das oeffentliche Vereinsprofil aktiviert.', icon: Icons.visibility_outlined, color: AirmiusColors.blue),
  _AuditEvent(area: 'Mitglied', title: 'Mitgliedsanfrage gesendet', actor: 'ZBB Konto', time: '09:28', body: 'Dynamisches Formular wurde mit Personendaten, Wohndaten und Datenschutzbestaetigung eingereicht.', icon: Icons.assignment_add, color: AirmiusColors.green),
  _AuditEvent(area: 'Mitglied', title: 'Anfrage zurueckgezogen', actor: 'ZBB Konto', time: '09:43', body: 'Der Antrag wurde vor der Admin-Entscheidung zurueckgezogen und im Vereins-Postfach markiert.', icon: Icons.undo_outlined, color: AirmiusColors.amber),
  _AuditEvent(area: 'Finanzen', title: 'Beitragsregel aktualisiert', actor: 'Club Admin', time: '10:05', body: 'Zahlungsrhythmus wurde auf monatlich gesetzt, Barzahlung und Ueberweisung bleiben erlaubt.', icon: Icons.receipt_long_outlined, color: AirmiusColors.amber),
  _AuditEvent(area: 'Security', title: '2FA bestaetigt', actor: 'ZBB Konto', time: '10:22', body: 'Sensible Kontoaktion wurde mit zweitem Faktor bestaetigt.', icon: Icons.security_outlined, color: AirmiusColors.green),
  _AuditEvent(area: 'Admin', title: 'Moderationsfall geschlossen', actor: 'Platform Admin', time: '11:01', body: 'Meldung wurde geprueft, Entscheidung dokumentiert und Audit-Hinweis gespeichert.', icon: Icons.admin_panel_settings_outlined, color: AirmiusColors.blue),
];

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event});

  final _AuditEvent event;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: event.color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: event.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: event.color.withValues(alpha: .4))),
            child: Icon(event.icon, color: event.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(event.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                    StatusPill(event.area, color: event.color),
                  ],
                ),
                const SizedBox(height: 5),
                Text(event.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 9),
                Text('${event.actor} · ${event.time}', style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w800)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _LogToggle extends StatelessWidget {
  const _LogToggle({required this.icon, required this.title, required this.body, required this.enabled, required this.onChanged, this.last = false});

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
