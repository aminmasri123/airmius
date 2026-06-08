import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SystemAdminOperationsScreen extends StatefulWidget {
  const SystemAdminOperationsScreen({super.key, this.initialTab = 'Mail'});

  final String initialTab;

  @override
  State<SystemAdminOperationsScreen> createState() => _SystemAdminOperationsScreenState();
}

class _SystemAdminOperationsScreenState extends State<SystemAdminOperationsScreen> {
  late String _tab = widget.initialTab;

  @override
  Widget build(BuildContext context) {
    final operations = _operations.where((operation) => operation.area == _tab).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('System Admin', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'System Admin',
        subtitle: 'Mail-Center, Providerkosten, Systemsettings, Webhooks, Wartung und Audit',
        trailing: const StatusPill('Admin'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const AirmiusLogo(),
                  const SizedBox(height: 14),
                  const Text('Betrieb gehoert auch zur mobilen App.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  const SizedBox(height: 8),
                  const Text('Admins brauchen unterwegs Zugriff auf Mailzustellung, Providerkosten, Systemschalter, Wartung, Webhooks und Audit-Status. Diese UI bildet die Web-Admin-Routen mobil ab.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  Row(children: const [Expanded(child: MetricCard(value: 'Mail', label: 'Center')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'Kosten', label: 'Provider')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'Audit', label: 'System'))]),
                  const SizedBox(height: 14),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    for (final tab in _tabs)
                      ChoiceChip(
                        label: Text(tab),
                        selected: _tab == tab,
                        onSelected: (_) => setState(() => _tab = tab),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.cardSoft,
                        side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      ),
                  ]),
                ],
              ),
            ),
            const SizedBox(height: 16),
            for (final operation in operations) ...[
              _SystemOperationCard(operation: operation),
              const SizedBox(height: 12),
            ],
            _SystemChecklist(tab: _tab),
          ],
        ),
      ),
    );
  }
}

class _SystemOperationCard extends StatelessWidget {
  const _SystemOperationCard({required this.operation});

  final _SystemOperation operation;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: operation.color.withValues(alpha: .44),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 50,
                  height: 50,
                  decoration: BoxDecoration(color: operation.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: operation.color.withValues(alpha: .45))),
                  child: Icon(operation.icon, color: operation.color),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(operation.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                      const SizedBox(height: 5),
                      Text(operation.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                      const SizedBox(height: 10),
                      Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(operation.area, color: operation.color), StatusPill(operation.routeHint)]),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: [
              AirmiusButton(label: operation.primaryLabel, icon: operation.icon, onPressed: () => openUiAction(context, title: operation.primaryLabel, body: operation.actionBody, status: operation.area, icon: operation.icon)),
              AirmiusButton(label: 'Details', icon: Icons.chevron_right, secondary: true, onPressed: () => openUiAction(context, title: operation.title, body: '${operation.body}\n\nRoute: ${operation.routeHint}', status: 'Admin System', icon: operation.icon)),
            ]),
          ],
        ),
      );
}

class _SystemChecklist extends StatelessWidget {
  const _SystemChecklist({required this.tab});

  final String tab;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: AirmiusColors.green.withValues(alpha: .44),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Eyebrow('Betriebs-Checkliste'),
            const SizedBox(height: 8),
            Text(_checklistText[tab] ?? _checklistText.values.first, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: const [StatusPill('Mobile UI', color: AirmiusColors.green), StatusPill('API spaeter'), StatusPill('Admin')]),
          ],
        ),
      );
}

class _SystemOperation {
  const _SystemOperation({required this.area, required this.title, required this.body, required this.primaryLabel, required this.actionBody, required this.routeHint, required this.icon, required this.color});

  final String area;
  final String title;
  final String body;
  final String primaryLabel;
  final String actionBody;
  final String routeHint;
  final IconData icon;
  final Color color;
}

const _tabs = ['Mail', 'Kosten', 'Settings', 'Webhooks', 'Wartung', 'Audit'];

const _operations = <_SystemOperation>[
  _SystemOperation(area: 'Mail', title: 'Mail-Center Uebersicht', body: 'Zustellstatus, Kategorien, Sender, Bounce-Hinweise, Retry und fehlerhafte Mail-Deliveries mobil sichtbar machen.', primaryLabel: 'Mail-Center oeffnen', actionBody: 'Mail-Center laden, Zustellstatus gruppieren, fehlerhafte Sendungen markieren und Admin-Filter anwenden.', routeHint: '/admin/mail-center', icon: Icons.mark_email_read_outlined, color: AirmiusColors.blue),
  _SystemOperation(area: 'Mail', title: 'Absender & Testmail', body: 'Kategorie-Absender konfigurieren und Testmail fuer Vereins-, Billing-, Guardian- oder Commerce-Mails ausloesen.', primaryLabel: 'Testmail senden', actionBody: 'Testmail-Dialog mit Kategorie, Empfaenger, Sprache, Preview und Versandstatus vorbereiten.', routeHint: '/admin/mail-center/senders/{category}/test', icon: Icons.outgoing_mail, color: AirmiusColors.green),
  _SystemOperation(area: 'Mail', title: 'Mail erneut senden', body: 'Fehlerhafte oder kritische Systemmails koennen erneut versendet und anschliessend als geloest markiert werden.', primaryLabel: 'Resend vormerken', actionBody: 'MailDelivery neu senden, Status aktualisieren, Audit-Eintrag und Admin-Benachrichtigung vormerken.', routeHint: '/admin/mail-center/{mailDelivery}/resend', icon: Icons.refresh_outlined, color: AirmiusColors.amber),
  _SystemOperation(area: 'Kosten', title: 'Providerkosten', body: 'Externe Dienstleisterkosten fuer Payments, Mail, Hosting, KI, Maps und Plattformbetrieb mobil kontrollieren.', primaryLabel: 'Kosten erfassen', actionBody: 'Providerkosten-Formular mit Zeitraum, Anbieter, Betrag, Kategorie, Beleg und Sichtbarkeit vorbereiten.', routeHint: '/admin/provider-costs', icon: Icons.account_balance_wallet_outlined, color: AirmiusColors.amber),
  _SystemOperation(area: 'Kosten', title: 'Kostenstatus & Abgleich', body: 'Offene, geplante und bezahlte Providerkosten mit Adminstatus, Notiz und Export-Hinweis darstellen.', primaryLabel: 'Status aktualisieren', actionBody: 'Kostenstatus setzen, Zahlungsnotiz speichern und spaeteren Export/API-Abgleich vormerken.', routeHint: '/admin/provider-costs/{providerCost}', icon: Icons.receipt_long_outlined, color: AirmiusColors.green),
  _SystemOperation(area: 'Settings', title: 'Systemsettings', body: 'Globale Plattformschalter fuer Registrierung, Public-Seiten, Uploads, Wartung, Feature-Flags und Sprache mobil pflegen.', primaryLabel: 'Settings bearbeiten', actionBody: 'Systemsettings als gruppierte Mobile-Sections bearbeiten und sichere Mutationen fuer Laravel vorbereiten.', routeHint: '/admin/settings', icon: Icons.settings_suggest_outlined, color: AirmiusColors.blue),
  _SystemOperation(area: 'Settings', title: 'Feature Flags', body: 'Module wie Marketplace, Outfit, Learning, Maturity, Sponsoring oder Public Leads pro Umgebung aktivieren oder sperren.', primaryLabel: 'Flags pruefen', actionBody: 'Feature-Flag-Uebersicht mit Umgebung, Zielgruppe, Risiko und Rollenzugriff vorbereiten.', routeHint: '/api/v1/meta', icon: Icons.flag_outlined, color: AirmiusColors.green),
  _SystemOperation(area: 'Webhooks', title: 'Stripe & PayPal Webhooks', body: 'Subscription, Commerce und Outfit-Webhooks mit letztem Status, Signaturhinweis und Retry-Aktion anzeigen.', primaryLabel: 'Webhooks pruefen', actionBody: 'Webhook-Status, Provider, letzte Events, Fehler und Retry-Hinweise mobil zusammenfassen.', routeHint: '/webhooks/stripe /webhooks/paypal', icon: Icons.sync_outlined, color: AirmiusColors.blue),
  _SystemOperation(area: 'Webhooks', title: 'Checkout Rueckkehrseiten', body: 'Success, Cancel und Banktransfer-Rueckkehrseiten fuer Gast-, Commerce-, Subscription- und Outfit-Checkouts abbilden.', primaryLabel: 'Checkouts pruefen', actionBody: 'Checkout-Return-Flows als mobile Statuskarten mit API-Hinweis und Nutzerkommunikation vorbereiten.', routeHint: '/checkout/*/success', icon: Icons.payments_outlined, color: AirmiusColors.amber),
  _SystemOperation(area: 'Wartung', title: 'Wartungsmodus', body: 'Maintenance-Hinweis, Public-Kommunikation, Login-Verhalten und Admin-Ausnahmen in einer mobilen Betriebsansicht steuern.', primaryLabel: 'Wartung planen', actionBody: 'Wartungsfenster, Nachricht, Start/Ende, betroffene Module und Admin-Freigabe vorbereiten.', routeHint: '/admin/settings', icon: Icons.construction_outlined, color: AirmiusColors.red),
  _SystemOperation(area: 'Wartung', title: 'SEO & Public Betrieb', body: 'Robots, Sitemap, RSS, Legal-Seiten und Public-Indexierbarkeit fuer App/Public-Betrieb sichtbar machen.', primaryLabel: 'SEO pruefen', actionBody: 'Sitemap, robots.txt, RSS, Public-Legal und Landingpage-Status als mobile Checkliste darstellen.', routeHint: '/robots.txt /sitemap.xml /rss', icon: Icons.travel_explore_outlined, color: AirmiusColors.green),
  _SystemOperation(area: 'Audit', title: 'Admin Audit Export', body: 'Sensible Adminaktionen, Moderation, Billing, Rollen, Userstatus und Systemschalter fuer Audit und Compliance sammeln.', primaryLabel: 'Audit exportieren', actionBody: 'Audit-Export mit Zeitraum, Modul, Admin, Aktion, Risiko und Exportformat als UI-Flow vorbereiten.', routeHint: '/admin/settings', icon: Icons.history_outlined, color: AirmiusColors.blue),
  _SystemOperation(area: 'Audit', title: 'Betriebsnotizen', body: 'Interne Hinweise zu Incidents, Mailproblemen, Paymentproblemen und API-Risiken fuer Admins dokumentieren.', primaryLabel: 'Notiz anlegen', actionBody: 'Betriebsnotiz mit Prioritaet, Modul, Verantwortlichem, Faelligkeit und Loesungsstatus vormerken.', routeHint: '/admin/operating-contracts', icon: Icons.sticky_note_2_outlined, color: AirmiusColors.amber),
];

const _checklistText = {
  'Mail': 'Vor Store-Release muessen Testmail, Absender, Zustellstatus, Resend und Fehleraufloesung als API-Flows angebunden werden.',
  'Kosten': 'Providerkosten brauchen Kategorien, Belege, Zahlungsstatus, Export und Adminrechte. Die mobile UI bereitet diese Struktur vor.',
  'Settings': 'Systemsettings sollten spaeter capability flags liefern, damit Flutter Aktionen rollenbasiert anzeigen oder sperren kann.',
  'Webhooks': 'Webhooks laufen serverseitig, aber die App braucht Status, letzte Events und klare Admin-Hinweise bei Checkout-Problemen.',
  'Wartung': 'Wartungsmodus und Public-Betrieb brauchen mobile Hinweise, damit Nutzer und Admins nicht in leeren Zustanden landen.',
  'Audit': 'Audit ist fuer Rollen, Zahlungen, Moderation, Datenschutz und Systemschalter Pflicht. Die UI sammelt diese Adminsicht.',
};
