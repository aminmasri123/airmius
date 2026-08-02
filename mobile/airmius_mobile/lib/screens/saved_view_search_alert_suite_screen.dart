import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SavedViewSearchAlertSuiteScreen extends StatefulWidget {
  const SavedViewSearchAlertSuiteScreen({super.key});

  @override
  State<SavedViewSearchAlertSuiteScreen> createState() =>
      _SavedViewSearchAlertSuiteScreenState();
}

class _SavedViewSearchAlertSuiteScreenState
    extends State<SavedViewSearchAlertSuiteScreen> {
  String _area = 'Mitglieder';
  bool _savedViews = true;
  bool _alerts = true;
  bool _sharedViews = true;
  bool _exports = true;

  @override
  Widget build(BuildContext context) {
    final views = _views
        .where((view) => _area == 'Alle' || view.area == _area)
        .toList();
    final accentColor = airmiusAccentColor(context);
    final secondaryColor = Theme.of(context).colorScheme.secondary;
    final tertiaryColor = Theme.of(context).colorScheme.tertiary;
    final textColor = airmiusTextColor(context);
    final mutedColor = airmiusMutedColor(context);
    final borderColor = airmiusBorderColor(context);
    final surfaceColor = airmiusSurfaceSoftColor(context);

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'Ansichten & Alarme',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Saved Views Search Alerts',
        subtitle:
            'Mobile UI für gespeicherte Filter, Suchalarme, geteilte Listenansichten, Exporte und rollenbasierte Sichtbarkeit.',
        trailing: StatusPill('Lists', color: accentColor),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('SMART LISTS'),
                  const SizedBox(height: 8),
                  Text(
                    'Wichtige Listen bleiben wiederauffindbar.',
                    style: TextStyle(
                      color: textColor,
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Die App bereitet gespeicherte Filter und Suchalarme für Mitglieder, Vereine, Events, Rechnungen, Dateien, Support und Marketplace vor.',
                    style: TextStyle(color: mutedColor, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children:
                        [
                          'Alle',
                          'Mitglieder',
                          'Vereine',
                          'Events',
                          'Rechnungen',
                          'Dateien',
                          'Support',
                          'Shop',
                        ].map((item) {
                          return ChoiceChip(
                            selected: _area == item,
                            label: Text(item),
                            onSelected: (_) => setState(() => _area = item),
                            selectedColor: accentColor.withValues(alpha: .22),
                            backgroundColor: surfaceColor,
                            side: BorderSide(
                              color: _area == item ? accentColor : borderColor,
                            ),
                            labelStyle: TextStyle(
                              color: _area == item ? textColor : mutedColor,
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
                  child: MetricCard(value: '18', label: 'Views'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '6', label: 'Alerts'),
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
                  Row(
                    children: [
                      const Expanded(child: Eyebrow('LISTENREGELN')),
                      StatusPill(_area, color: accentColor),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _ListToggle(
                    icon: Icons.bookmark_outline,
                    title: 'Gespeicherte Ansichten',
                    body:
                        'Filter, Sortierung, Spalten, Statuschips und Suchbegriff können als persönliche Ansicht gespeichert werden.',
                    enabled: _savedViews,
                    onChanged: (value) => setState(() => _savedViews = value),
                  ),
                  _ListToggle(
                    icon: Icons.notifications_active_outlined,
                    title: 'Suchalarme',
                    body:
                        'Neue Treffer für offene Anträge, überfällige Rechnungen oder passende Vereine lösen Hinweise aus.',
                    enabled: _alerts,
                    onChanged: (value) => setState(() => _alerts = value),
                  ),
                  _ListToggle(
                    icon: Icons.groups_2_outlined,
                    title: 'Geteilte Vereinsansichten',
                    body:
                        'Admins können Ansichten für Trainer, Vorstand, Kassenwart oder Support teilen.',
                    enabled: _sharedViews,
                    onChanged: (value) => setState(() => _sharedViews = value),
                  ),
                  _ListToggle(
                    icon: Icons.download_outlined,
                    title: 'Export & Verlauf',
                    body:
                        'CSV, PDF, Rechnungslisten und Audit-Exporte werden als mobile Exportjobs sichtbar.',
                    enabled: _exports,
                    onChanged: (value) => setState(() => _exports = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final view in views) ...[
              _SavedViewCard(view: view),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: secondaryColor.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('AKTIVE FILTER'),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      StatusPill('Status: offen', color: tertiaryColor),
                      StatusPill('Club: ZBB', color: accentColor),
                      StatusPill('Sort: neueste', color: secondaryColor),
                      StatusPill('Exportbereit', color: accentColor),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label: 'Ansicht speichern',
                    icon: Icons.bookmark_add_outlined,
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
                  const Eyebrow('API VIEW PAYLOAD'),
                  const SizedBox(height: 10),
                  const _PayloadLine(
                    label: 'resource',
                    value:
                        'members, clubs, events, invoices, files, support, products',
                  ),
                  const _PayloadLine(
                    label: 'filters',
                    value:
                        'status, role, date_range, club_id, team_id, payment_state',
                  ),
                  const _PayloadLine(
                    label: 'alerts',
                    value: 'new_match, count_changed, overdue, export_ready',
                  ),
                  const _PayloadLine(
                    label: 'sharing',
                    value: 'private, club_admins, trainers, finance, support',
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

class _SavedView {
  const _SavedView({
    required this.area,
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
    required this.color,
  });

  final String area;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}

const _views = [
  _SavedView(
    area: 'Mitglieder',
    title: 'Offene Mitgliedsanträge',
    body: 'Neue Anträge, Rückfragen, fehlende Dokumente und Rückzüge.',
    status: 'Alert',
    icon: Icons.assignment_ind_outlined,
    color: AirmiusColors.green,
  ),
  _SavedView(
    area: 'Mitglieder',
    title: 'Zahlstatus prüfen',
    body:
        'Mitglieder mit offenen Beiträgen, Mahnungen oder unklarer Zahlungsart.',
    status: 'Finance',
    icon: Icons.receipt_long_outlined,
    color: AirmiusColors.amber,
  ),
  _SavedView(
    area: 'Vereine',
    title: 'Vereine mit Anfragen',
    body:
        'Öffentliche Vereine, die Mitgliedsanfragen akzeptieren und passende Sportarten haben.',
    status: 'Public',
    icon: Icons.apartment_outlined,
    color: AirmiusColors.blue,
  ),
  _SavedView(
    area: 'Events',
    title: 'Heute Training',
    body: 'Trainings und Events mit RSVP, Check-in und Fahrgemeinschaften.',
    status: 'Heute',
    icon: Icons.event_available_outlined,
    color: AirmiusColors.green,
  ),
  _SavedView(
    area: 'Rechnungen',
    title: 'Überfällige Rechnungen',
    body: 'Offene Rechnungen, Beitragszyklen, Banktransfer und Mahnstufe.',
    status: 'Overdue',
    icon: Icons.payments_outlined,
    color: AirmiusColors.amber,
  ),
  _SavedView(
    area: 'Dateien',
    title: 'Consent-Dokumente',
    body: 'Datenschutz, Satzung, Beitragsordnung, SEPA und Dokumentversionen.',
    status: 'Legal',
    icon: Icons.folder_copy_outlined,
    color: AirmiusColors.blue,
  ),
  _SavedView(
    area: 'Support',
    title: 'Eskalierte Tickets',
    body:
        'Supportfälle mit Vereinsadmin-Hinweis, Plattformstatus und Dateianhängen.',
    status: 'Urgent',
    icon: Icons.support_agent_outlined,
    color: AirmiusColors.amber,
  ),
  _SavedView(
    area: 'Shop',
    title: 'Offene Bestellungen',
    body: 'Marketplace-Orders, Abholung, Versand, Rückgabe und Zahlungsstatus.',
    status: 'Orders',
    icon: Icons.storefront_outlined,
    color: AirmiusColors.blue,
  ),
];

class _SavedViewCard extends StatelessWidget {
  const _SavedViewCard({required this.view});

  final _SavedView view;

  @override
  Widget build(BuildContext context) {
    final color = airmiusSemanticColor(context, view.color);
    return AirmiusPanel(
      borderColor: color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: color.withValues(alpha: .14),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: color.withValues(alpha: .42)),
            ),
            child: Icon(view.icon, color: color),
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
                        view.title,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    StatusPill(view.status, color: color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  view.body,
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
                    StatusPill(view.area, color: color),
                    const StatusPill('Saved view'),
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

class _ListToggle extends StatelessWidget {
  const _ListToggle({
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
    final activeColor = Theme.of(context).colorScheme.secondary;
    final mutedColor = airmiusMutedColor(context);
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: enabled ? activeColor : mutedColor),
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
                Text(body, style: TextStyle(color: mutedColor, height: 1.35)),
              ],
            ),
          ),
          Switch(
            value: enabled,
            activeThumbColor: activeColor,
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
