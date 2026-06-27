import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class IntegrationWebhookProviderSuiteScreen extends StatefulWidget {
  const IntegrationWebhookProviderSuiteScreen({super.key});

  @override
  State<IntegrationWebhookProviderSuiteScreen> createState() => _IntegrationWebhookProviderSuiteScreenState();
}

class _IntegrationWebhookProviderSuiteScreenState extends State<IntegrationWebhookProviderSuiteScreen> {
  String _providerArea = 'Mail';
  bool _webhooks = true;
  bool _retryQueue = true;
  bool _secretRotation = false;
  bool _healthChecks = true;

  @override
  Widget build(BuildContext context) {
    final providers = _providers.where((item) => item.area == _providerArea).toList();

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Integrationen & Webhooks', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Integration Webhook Provider Center',
        subtitle: 'Mobile UI für Mail, Push, Payments, Storage, Maps, Providerstatus, Webhooks, Secrets und Retry-Logs.',
        trailing: const StatusPill('Ops', color: AirmiusColors.blue),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('PROVIDER OPS'),
                  const SizedBox(height: 8),
                  const Text(
                    'Alle externen Dienste bleiben kontrollierbar.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Die App bereitet Status, Konfiguration und Fehlerbehandlung für Provider vor, die später Laravel-API, Jobs und Webhooks verbinden.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Mail', 'Push', 'Payments', 'Storage', 'Maps', 'AI'].map((item) {
                      return ChoiceChip(
                        selected: _providerArea == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _providerArea = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _providerArea == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _providerArea == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '6', label: 'Provider')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '99%', label: 'Health')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '12', label: 'Hooks')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(children: [const Expanded(child: Eyebrow('BETRIEBSREGELN')), StatusPill(_providerArea, color: AirmiusColors.blue)]),
                  const SizedBox(height: 12),
                  _OpsToggle(
                    icon: Icons.webhook_outlined,
                    title: 'Webhook-Zustellung',
                    body: 'Provider-Events für Zahlung, Upload, E-Mail, Push und Moderation werden als Zustellstatus angezeigt.',
                    value: _webhooks,
                    onChanged: (value) => setState(() => _webhooks = value),
                  ),
                  _OpsToggle(
                    icon: Icons.replay_outlined,
                    title: 'Retry Queue',
                    body: 'Fehlgeschlagene Webhooks und Jobs bekommen Retry, Backoff, Dead Letter und Admin-Hinweis.',
                    value: _retryQueue,
                    onChanged: (value) => setState(() => _retryQueue = value),
                  ),
                  _OpsToggle(
                    icon: Icons.key_outlined,
                    title: 'Secret Rotation',
                    body: 'API-Keys, Signatur-Secrets und Provider-Tokens werden versioniert und auditierbar vorbereitet.',
                    value: _secretRotation,
                    onChanged: (value) => setState(() => _secretRotation = value),
                  ),
                  _OpsToggle(
                    icon: Icons.monitor_heart_outlined,
                    title: 'Health Checks',
                    body: 'Statuschecks, Latenz, Fehlerraten und letzte erfolgreiche Synchronisierung erscheinen mobil.',
                    value: _healthChecks,
                    onChanged: (value) => setState(() => _healthChecks = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final provider in providers) ...[
              _ProviderCard(provider: provider),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: AirmiusColors.amber.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('WEBHOOK LOG'),
                  const SizedBox(height: 10),
                  const _WebhookLine(method: 'POST', path: '/webhooks/payment/succeeded', status: 'Delivered', color: AirmiusColors.green),
                  const _WebhookLine(method: 'POST', path: '/webhooks/mail/bounced', status: 'Retry 2', color: AirmiusColors.amber),
                  const _WebhookLine(method: 'POST', path: '/webhooks/storage/scanned', status: 'Queued', color: AirmiusColors.blue),
                  const SizedBox(height: 10),
                  AirmiusButton(label: 'Webhook später erneut senden', icon: Icons.replay_outlined, onPressed: () {}),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('LARAVEL BINDING'),
                  const SizedBox(height: 8),
                  const Text('Später verbindet diese UI Provider-Konfigurationen mit Laravel Jobs, Queues, Events, Notifications und gesicherten Admin-Routen.', style: TextStyle(color: AirmiusColors.muted, height: 1.38)),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      StatusPill('Jobs', color: AirmiusColors.green),
                      StatusPill('Queues', color: AirmiusColors.blue),
                      StatusPill('Secrets', color: AirmiusColors.amber),
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

class _Provider {
  const _Provider({required this.area, required this.name, required this.body, required this.status, required this.icon, required this.color});

  final String area;
  final String name;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}

const _providers = [
  _Provider(area: 'Mail', name: 'Transactional Mail', body: 'Verifizierung, Passwort, Mitgliedsantrag, Admin-Info, Rechnungen und Support-Mails.', status: 'Ready', icon: Icons.mail_outline, color: AirmiusColors.blue),
  _Provider(area: 'Mail', name: 'Mail Bounce Monitor', body: 'Bounces, Beschwerden, Zustellfehler und erneute Verifikation für Userkontakte.', status: 'Retry', icon: Icons.mark_email_unread_outlined, color: AirmiusColors.amber),
  _Provider(area: 'Push', name: 'Mobile Push', body: 'Mitgliedsanfrage, Chat, Event-Erinnerung, Zahlung, Moderation und Deep Links.', status: 'Prepared', icon: Icons.notifications_active_outlined, color: AirmiusColors.green),
  _Provider(area: 'Push', name: 'Quiet Hours', body: 'Ruhezeiten, Digest, Themenkanal, Opt-in und Device Token Management.', status: 'Prepared', icon: Icons.notifications_paused_outlined, color: AirmiusColors.blue),
  _Provider(area: 'Payments', name: 'Payment Provider', body: 'Beiträge, Checkout, Rechnungen, Banktransfer, Refunds und Zahlungsstatus.', status: 'Mapped', icon: Icons.payments_outlined, color: AirmiusColors.green),
  _Provider(area: 'Payments', name: 'SEPA Mandates', body: 'Mandat, IBAN-Prüfung, Zahlungsrhythmus, Rücklastschrift und Audit.', status: 'Mapped', icon: Icons.account_balance_outlined, color: AirmiusColors.amber),
  _Provider(area: 'Storage', name: 'File Storage', body: 'Vereinsdokumente, Chat-Anhaenge, Produktbilder, Reports und Upload-Scans.', status: 'Ready', icon: Icons.folder_copy_outlined, color: AirmiusColors.blue),
  _Provider(area: 'Storage', name: 'Virus Scan', body: 'Upload-Prüfung, Quarantaene, Freigabe, Löschung und Admin-Hinweis.', status: 'Queued', icon: Icons.security_outlined, color: AirmiusColors.amber),
  _Provider(area: 'Maps', name: 'Map Provider', body: 'Sportkarte, Vereinsorte, Events, Fahrgemeinschaften, Routen und Standortfreigaben.', status: 'Prepared', icon: Icons.map_outlined, color: AirmiusColors.green),
  _Provider(area: 'Maps', name: 'Geocoding', body: 'Adresse, Trainingsort, Treffpunkt, PLZ-Suche und Radiusfilter.', status: 'Prepared', icon: Icons.location_on_outlined, color: AirmiusColors.blue),
  _Provider(area: 'AI', name: 'Coach Assist', body: 'Trainingshinweise, Content-Vorschläge, Moderationshilfe und Lernfeedback.', status: 'Optional', icon: Icons.auto_awesome_outlined, color: AirmiusColors.amber),
  _Provider(area: 'AI', name: 'Document Assist', body: 'OCR, Dokumentklassifikation, Datenschutz-Hinweise und Formularvorbefuellung.', status: 'Optional', icon: Icons.document_scanner_outlined, color: AirmiusColors.blue),
];

class _ProviderCard extends StatelessWidget {
  const _ProviderCard({required this.provider});

  final _Provider provider;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: provider.color.withValues(alpha: .45),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: provider.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: provider.color.withValues(alpha: .42))),
            child: Icon(provider.icon, color: provider.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(children: [Expanded(child: Text(provider.name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))), StatusPill(provider.status, color: provider.color)]),
                const SizedBox(height: 6),
                Text(provider.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _WebhookLine extends StatelessWidget {
  const _WebhookLine({required this.method, required this.path, required this.status, required this.color});

  final String method;
  final String path;
  final String status;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
        child: Row(
          children: [
            StatusPill(method, color: color),
            const SizedBox(width: 10),
            Expanded(child: Text(path, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800))),
            const SizedBox(width: 8),
            StatusPill(status, color: color),
          ],
        ),
      ),
    );
  }
}

class _OpsToggle extends StatelessWidget {
  const _OpsToggle({required this.icon, required this.title, required this.body, required this.value, required this.onChanged, this.last = false});

  final IconData icon;
  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: value ? AirmiusColors.green : AirmiusColors.muted),
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
          Switch(value: value, activeThumbColor: AirmiusColors.green, onChanged: onChanged),
        ],
      ),
    );
  }
}
