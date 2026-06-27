import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../navigation/airmius_deep_link_navigator.dart';
import '../widgets/airmius_widgets.dart';

class DeepLinkRouteResolverSuiteScreen extends StatefulWidget {
  const DeepLinkRouteResolverSuiteScreen({super.key});

  @override
  State<DeepLinkRouteResolverSuiteScreen> createState() => _DeepLinkRouteResolverSuiteScreenState();
}

class _DeepLinkRouteResolverSuiteScreenState extends State<DeepLinkRouteResolverSuiteScreen> {
  String _source = 'Alle';
  bool _authGate = true;
  bool _workspaceResolve = true;
  bool _safeFallback = true;
  bool _auditRoute = true;

  @override
  Widget build(BuildContext context) {
    final links = _links.where((link) => _source == 'Alle' || link.source == _source).toList();

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Deep Links', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Deep Link Route Resolver',
        subtitle: 'Mobile UI für Einladung, QR, Push, E-Mail, Chat, Zahlung, Datei und Event-Routing mit Auth- und Workspace-Prüfung.',
        trailing: const StatusPill('Routing', color: AirmiusColors.blue),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('LINK ROUTER'),
                  const SizedBox(height: 8),
                  const Text(
                    'Jeder Link fuehrt an den richtigen Ort.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Die Flutter-App bereitet Deep Links für Einladungen, Mitgliedsanträge, QR-Codes, Push, Rechnungen, Chat, Dateien und Events vor.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Alle', 'Invite', 'Push', 'QR', 'Mail', 'Payment', 'Chat', 'File'].map((item) {
                      return ChoiceChip(
                        selected: _source == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _source = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _source == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _source == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '8', label: 'Sources')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '12', label: 'Targets')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: 'Auth', label: 'Gate')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(children: [const Expanded(child: Eyebrow('ROUTING REGELN')), StatusPill(_source, color: AirmiusColors.blue)]),
                  const SizedBox(height: 12),
                  _RouteToggle(
                    icon: Icons.lock_outline,
                    title: 'Auth Gate',
                    body: 'Links dürfen Login, E-Mail-Verifizierung, 2FA, Suspended und Guardian-Pending sauber abfangen.',
                    enabled: _authGate,
                    onChanged: (value) => setState(() => _authGate = value),
                  ),
                  _RouteToggle(
                    icon: Icons.switch_account_outlined,
                    title: 'Workspace Resolver',
                    body: 'Club, Team, Member, Guardian, Trainer oder Admin-Kontext wird vor dem Zielscreen bestimmt.',
                    enabled: _workspaceResolve,
                    onChanged: (value) => setState(() => _workspaceResolve = value),
                  ),
                  _RouteToggle(
                    icon: Icons.route_outlined,
                    title: 'Sicherer Fallback',
                    body: 'Abgelaufene, falsche oder nicht erlaubte Links landen in einer klaren Erklaerungsseite statt im Nirgendwo.',
                    enabled: _safeFallback,
                    onChanged: (value) => setState(() => _safeFallback = value),
                  ),
                  _RouteToggle(
                    icon: Icons.history_outlined,
                    title: 'Routing Audit',
                    body: 'Sensible Links werden mit Quelle, Ziel, Status und Fehlergrund für Support nachvollziehbar.',
                    enabled: _auditRoute,
                    onChanged: (value) => setState(() => _auditRoute = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final link in links) ...[
              _LinkCard(link: link),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('RESOLUTION PREVIEW'),
                  const SizedBox(height: 8),
                  const Text('airmius://clubs/26 -> Auth prüfen -> Club Workspace setzen -> Vereinsprofil/Vereine öffnen -> Mitgliedsantrag erreichbar machen.', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, height: 1.38)),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      StatusPill('Auth OK', color: AirmiusColors.green),
                      StatusPill('Club 26', color: AirmiusColors.blue),
                      StatusPill('Draft found', color: AirmiusColors.amber),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'Route simulieren', icon: Icons.open_in_new_outlined, onPressed: () => AirmiusDeepLinkNavigator.open(context, 'airmius://clubs/26')),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('API ROUTE PAYLOAD'),
                  const SizedBox(height: 10),
                  const _PayloadLine(label: 'source', value: 'invite, push, qr, mail, payment, chat, file'),
                  const _PayloadLine(label: 'target', value: 'club_apply, request_status, invoice, conversation, event_checkin'),
                  const _PayloadLine(label: 'guards', value: 'auth, email_verified, role, workspace, expires_at'),
                  const _PayloadLine(label: 'fallback', value: 'login, forbidden, expired, not_found, workspace_select'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DeepLink {
  const _DeepLink({required this.source, required this.title, required this.body, required this.status, required this.rawLink, required this.icon, required this.color});

  final String source;
  final String title;
  final String body;
  final String status;
  final String rawLink;
  final IconData icon;
  final Color color;
}

const _links = [
  _DeepLink(source: 'Invite', title: 'Vereinsbeitritt', body: 'Einladung fuehrt zu Club Public Preview, Mitgliedsantrag oder Konto-Verknuepfung.', status: 'Mapped', rawLink: 'airmius://clubs/26', icon: Icons.person_add_alt_1_outlined, color: AirmiusColors.blue),
  _DeepLink(source: 'QR', title: 'Member Card Check-in', body: 'QR-Code prüft Mitgliedsstatus, Rolle, Gültigkeit und Trainingskontext.', status: 'Native', rawLink: 'airmius://profile/member-card', icon: Icons.qr_code_2_outlined, color: AirmiusColors.green),
  _DeepLink(source: 'Push', title: 'Chat Nachricht', body: 'Push öffnet passende Konversation, prüft Mute, Rollen und Meldekontext.', status: 'Ready', rawLink: 'airmius://messages/1', icon: Icons.notifications_active_outlined, color: AirmiusColors.blue),
  _DeepLink(source: 'Push', title: 'Event Reminder', body: 'Erinnerung öffnet Event, RSVP, Fahrgemeinschaft und Check-in Aktion.', status: 'Ready', rawLink: 'airmius://events/1', icon: Icons.event_available_outlined, color: AirmiusColors.green),
  _DeepLink(source: 'Mail', title: 'E-Mail Verifizierung', body: 'Mail-Link verifiziert Konto und fuehrt danach zum urspruenglichen Ziel.', status: 'Auth', rawLink: 'airmius://profile/security', icon: Icons.mark_email_read_outlined, color: AirmiusColors.amber),
  _DeepLink(source: 'Payment', title: 'Rechnung bezahlen', body: 'Zahlungslink öffnet Rechnung, Zahlungsstatus, Retry oder Banktransfer-Hinweise.', status: 'Secure', rawLink: 'https://app.airmius.com/membership-applications/1001', icon: Icons.receipt_long_outlined, color: AirmiusColors.amber),
  _DeepLink(source: 'Chat', title: 'Support Ticket', body: 'Support-Link öffnet Ticketverlauf, Anhaenge und Eskalationsstatus.', status: 'Mapped', rawLink: 'airmius://messages/4', icon: Icons.support_agent_outlined, color: AirmiusColors.blue),
  _DeepLink(source: 'File', title: 'Dokument Preview', body: 'Dateilink prüft Zugriff, Zweck, Consent-Pflicht und Downloadrechte.', status: 'Guarded', rawLink: 'airmius://files/club-doc-2026-001', icon: Icons.folder_copy_outlined, color: AirmiusColors.green),
];

class _LinkCard extends StatelessWidget {
  const _LinkCard({required this.link});

  final _DeepLink link;

  @override
  Widget build(BuildContext context) {
    final target = AirmiusDeepLinkNavigator.resolve(link.rawLink);
    return AirmiusPanel(
      borderColor: link.color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: link.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: link.color.withValues(alpha: .42))),
            child: Icon(link.icon, color: link.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(link.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                    StatusPill(link.status, color: link.color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(link.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 9),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(link.source, color: link.color), StatusPill(AirmiusDeepLinkNavigator.destinationLabel(target))]),
                const SizedBox(height: 10),
                AirmiusButton(label: 'Route öffnen', icon: Icons.open_in_new_outlined, secondary: true, onPressed: () => AirmiusDeepLinkNavigator.open(context, link.rawLink)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RouteToggle extends StatelessWidget {
  const _RouteToggle({required this.icon, required this.title, required this.body, required this.enabled, required this.onChanged, this.last = false});

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
