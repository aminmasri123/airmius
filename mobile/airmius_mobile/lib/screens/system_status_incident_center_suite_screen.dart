import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SystemStatusIncidentCenterSuiteScreen extends StatefulWidget {
  const SystemStatusIncidentCenterSuiteScreen({super.key});

  @override
  State<SystemStatusIncidentCenterSuiteScreen> createState() =>
      _SystemStatusIncidentCenterSuiteScreenState();
}

class _SystemStatusIncidentCenterSuiteScreenState
    extends State<SystemStatusIncidentCenterSuiteScreen> {
  String _scope = 'App';
  bool _maintenanceBanner = false;
  bool _incidentUpdates = true;
  bool _statusPage = true;
  bool _adminEscalation = true;

  @override
  Widget build(BuildContext context) {
    final services = _services
        .where((service) => _scope == 'Alle' || service.scope == _scope)
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
          'Systemstatus',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'System Status Incident Center',
        subtitle:
            'Mobile UI für Systemstatus, Wartungsfenster, Incident-Kommunikation, Service-Health und Admin-Eskalation.',
        trailing: StatusPill('Health', color: secondaryColor),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('STATUS CENTER'),
                  const SizedBox(height: 8),
                  Text(
                    'Wenn etwas hakt, bleibt Airmius ehrlich und klar.',
                    style: TextStyle(
                      color: textColor,
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Die App bereitet Statusbanner, Wartungsmodus, Incident-Verlauf, Service-Health und klare Nutzerhinweise für mobile Web-App-Paritaet vor.',
                    style: TextStyle(color: mutedColor, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children:
                        [
                          'Alle',
                          'App',
                          'API',
                          'Provider',
                          'Jobs',
                          'Store',
                        ].map((item) {
                          return ChoiceChip(
                            selected: _scope == item,
                            label: Text(item),
                            onSelected: (_) => setState(() => _scope = item),
                            selectedColor: accentColor.withValues(alpha: .22),
                            backgroundColor: surfaceColor,
                            side: BorderSide(
                              color: _scope == item ? accentColor : borderColor,
                            ),
                            labelStyle: TextStyle(
                              color: _scope == item ? textColor : mutedColor,
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
                  child: MetricCard(value: '99.9', label: 'Uptime'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '1', label: 'Incident'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '0', label: 'Critical'),
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
                      const Expanded(child: Eyebrow('KOMMUNIKATION')),
                      StatusPill(_scope, color: accentColor),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _StatusToggle(
                    icon: Icons.campaign_outlined,
                    title: 'Wartungsbanner',
                    body:
                        'Nutzer sehen im Header oder Dashboard klare Hinweise, wenn geplante Wartung aktiv ist.',
                    enabled: _maintenanceBanner,
                    onChanged: (value) =>
                        setState(() => _maintenanceBanner = value),
                  ),
                  _StatusToggle(
                    icon: Icons.update_outlined,
                    title: 'Incident Updates',
                    body:
                        'Updates erscheinen zeitlich sortiert mit Auswirkung, Ursache, Workaround und Loesungsstatus.',
                    enabled: _incidentUpdates,
                    onChanged: (value) =>
                        setState(() => _incidentUpdates = value),
                  ),
                  _StatusToggle(
                    icon: Icons.public_outlined,
                    title: 'Public Status Page',
                    body:
                        'Öffentliche Statusseite für App, API, Provider und Store-relevante Dienste.',
                    enabled: _statusPage,
                    onChanged: (value) => setState(() => _statusPage = value),
                  ),
                  _StatusToggle(
                    icon: Icons.support_agent_outlined,
                    title: 'Admin Eskalation',
                    body:
                        'Kritische Stoerungen können an Platform Admins, Provider oder Support eskaliert werden.',
                    enabled: _adminEscalation,
                    onChanged: (value) =>
                        setState(() => _adminEscalation = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final service in services) ...[
              _ServiceCard(service: service),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: tertiaryColor.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('INCIDENT TIMELINE'),
                  const SizedBox(height: 10),
                  const _IncidentLine(
                    time: '09:05',
                    title: 'Webhook-Verzoegerung erkannt',
                    body:
                        'Payment-Events kamen verspätet an; Nutzer sehen Zahlungsstatus als “wird synchronisiert”.',
                    color: AirmiusColors.amber,
                  ),
                  const _IncidentLine(
                    time: '09:18',
                    title: 'Retry Queue aktiv',
                    body:
                        'Fehlgeschlagene Webhooks werden automatisch erneut verarbeitet.',
                    color: AirmiusColors.blue,
                  ),
                  const _IncidentLine(
                    time: '09:42',
                    title: 'Service stabil',
                    body:
                        'Provider antwortet wieder normal, Queue wird abgearbeitet.',
                    color: AirmiusColors.green,
                  ),
                  const SizedBox(height: 10),
                  AirmiusButton(
                    label: 'Incident-Update veröffentlichen',
                    icon: Icons.campaign_outlined,
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
                  const Eyebrow('NUTZERHINWEIS'),
                  const SizedBox(height: 8),
                  Text(
                    'Beispiel: Einige Zahlungen werden gerade synchronisiert. Deine Daten sind sicher, und wir aktualisieren den Status automatisch.',
                    style: TextStyle(
                      color: textColor,
                      fontWeight: FontWeight.w800,
                      height: 1.38,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      StatusPill('Transparent', color: secondaryColor),
                      StatusPill('No panic copy', color: accentColor),
                      StatusPill('Retry visible', color: tertiaryColor),
                    ],
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

class _Service {
  const _Service({
    required this.scope,
    required this.name,
    required this.body,
    required this.status,
    required this.icon,
    required this.color,
  });

  final String scope;
  final String name;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}

const _services = [
  _Service(
    scope: 'App',
    name: 'Mobile App Shell',
    body:
        'Navigation, Header, Bottom Navigation, Rollenwechsel und lokale UI-Zustaende.',
    status: 'Operational',
    icon: Icons.phone_iphone_outlined,
    color: AirmiusColors.green,
  ),
  _Service(
    scope: 'App',
    name: 'Forms & Modals',
    body: 'Mitgliedsantrag, Validierung, Uploads, Rückzug und lange Formulare.',
    status: 'Operational',
    icon: Icons.dynamic_form_outlined,
    color: AirmiusColors.green,
  ),
  _Service(
    scope: 'API',
    name: 'Laravel API v1',
    body:
        'Auth, Clubs, Membership, Files, Social, Commerce, Admin und Public Endpunkte.',
    status: 'Planned',
    icon: Icons.api_outlined,
    color: AirmiusColors.blue,
  ),
  _Service(
    scope: 'API',
    name: 'Error Contract',
    body:
        'Validation, Unauthorized, Forbidden, Rate Limit, Offline und Retry-Zustaende.',
    status: 'Mapped',
    icon: Icons.sync_problem_outlined,
    color: AirmiusColors.amber,
  ),
  _Service(
    scope: 'Provider',
    name: 'Payment Provider',
    body: 'Checkout, Beiträge, Rechnungen, Webhooks und Refunds.',
    status: 'Degraded',
    icon: Icons.payments_outlined,
    color: AirmiusColors.amber,
  ),
  _Service(
    scope: 'Provider',
    name: 'Mail Provider',
    body:
        'Verifizierung, Admin-Hinweise, Support, Rechnungen und Systemmeldungen.',
    status: 'Operational',
    icon: Icons.mail_outline,
    color: AirmiusColors.green,
  ),
  _Service(
    scope: 'Jobs',
    name: 'Queue Worker',
    body:
        'Mail, Push, Upload-Scan, Import, Export, Payments und Webhook-Retry.',
    status: 'Operational',
    icon: Icons.pending_actions_outlined,
    color: AirmiusColors.green,
  ),
  _Service(
    scope: 'Jobs',
    name: 'Dead Letter Queue',
    body: 'Nicht zustellbare oder defekte Jobs mit Admin-Aktion und Audit.',
    status: 'Watching',
    icon: Icons.error_outline,
    color: AirmiusColors.amber,
  ),
  _Service(
    scope: 'Store',
    name: 'Android Release',
    body:
        'Icon, Splash, Datenschutz, Screenshots, Permissions und Review-Notizen.',
    status: 'Prepared',
    icon: Icons.store_outlined,
    color: AirmiusColors.blue,
  ),
  _Service(
    scope: 'Store',
    name: 'iOS Release',
    body: 'App Store Assets, Privacy Labels, Review-Hinweise und Device-QA.',
    status: 'Prepared',
    icon: Icons.mobile_friendly_outlined,
    color: AirmiusColors.blue,
  ),
];

class _ServiceCard extends StatelessWidget {
  const _ServiceCard({required this.service});

  final _Service service;

  @override
  Widget build(BuildContext context) {
    final color = airmiusSemanticColor(context, service.color);
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
            child: Icon(service.icon, color: color),
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
                        service.name,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    StatusPill(service.status, color: color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  service.body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 9),
                StatusPill(service.scope, color: color),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _IncidentLine extends StatelessWidget {
  const _IncidentLine({
    required this.time,
    required this.title,
    required this.body,
    required this.color,
  });

  final String time;
  final String title;
  final String body;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final color = airmiusSemanticColor(context, this.color);
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 50,
            child: Text(
              time,
              style: TextStyle(
                color: airmiusAccentColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Container(
            width: 10,
            height: 10,
            margin: const EdgeInsets.only(top: 5),
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
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
        ],
      ),
    );
  }
}

class _StatusToggle extends StatelessWidget {
  const _StatusToggle({
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
