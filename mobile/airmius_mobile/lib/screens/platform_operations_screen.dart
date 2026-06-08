import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'api_connection_screen.dart';
import 'ui_action_result_screen.dart';

class PlatformOperationsScreen extends StatelessWidget {
  const PlatformOperationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Plattformbetrieb', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Plattformbetrieb',
        subtitle: 'API, Checkouts, Webhooks, SEO, Sprache, Wartung und Systemstatus',
        trailing: const StatusPill('Ops'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('Mobile Web-App Abgleich'),
                  SizedBox(height: 8),
                  Text('Diese Ansicht sammelt technische Web-App-Routen, die in einer nativen App nicht als normale Seite auffallen, aber fuer Betrieb, Checkout und Public-Reichweite wichtig sind.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  SizedBox(height: 12),
                  Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('API'), StatusPill('Webhooks'), StatusPill('SEO'), StatusPill('Checkout'), StatusPill('System')]),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: 'OK', label: 'API')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Webhooks')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'SEO', label: 'Public'))]),
            const SizedBox(height: 14),
            _OpsGroup(
              title: 'API & Session',
              icon: Icons.api_outlined,
              description: 'Base URL, Sanctum-Session, CSRF-Token, Sprache und Mobile-Meta-Daten.',
              actions: [
                _OpsAction(label: 'API-Status pruefen', icon: Icons.health_and_safety_outlined, body: 'Meta-Endpunkt laden, App-Version pruefen und Feature-Flags synchronisieren.'),
                _OpsAction(label: 'CSRF/Session erneuern', icon: Icons.lock_outline, body: 'Checkout-CSRF und Auth-Session aktualisieren, bevor sensible Mutationen ausgefuehrt werden.'),
                _OpsAction(label: 'Sprache synchronisieren', icon: Icons.language_outlined, body: 'Aktuelle App-Sprache in Laravel speichern und Inhalte lokalisiert neu laden.'),
              ],
            ),
            const SizedBox(height: 12),
            _OpsGroup(
              title: 'Checkout & Webhooks',
              icon: Icons.payments_outlined,
              description: 'Stripe, PayPal, Commerce, Subscription, Outfit und Gast-Checkout-Rueckkehrseiten.',
              actions: [
                _OpsAction(label: 'Webhook-Status', icon: Icons.sync_outlined, body: 'Stripe-, PayPal-, Commerce- und Outfit-Webhooks in einer Betriebsuebersicht pruefen.'),
                _OpsAction(label: 'Gast-Checkout pruefen', icon: Icons.shopping_bag_outlined, body: 'Success, Cancel, Banktransfer und Return-Links fuer Gastbestellungen darstellen.'),
                _OpsAction(label: 'Banktransfer abgleichen', icon: Icons.account_balance_outlined, body: 'Offene Ueberweisungen markieren, Belege anzeigen und Admin-Zahlstatus vorbereiten.'),
              ],
            ),
            const SizedBox(height: 12),
            _OpsGroup(
              title: 'Public, SEO & Legal',
              icon: Icons.public_outlined,
              description: 'Robots, Sitemap, RSS, Legal-Seiten, Standortformular und Public-Content.',
              actions: [
                _OpsAction(label: 'Sitemap/Robots', icon: Icons.travel_explore_outlined, body: 'Sitemap, robots.txt und Public-Seitenstatus fuer die App sichtbar machen.'),
                _OpsAction(label: 'RSS & Blog', icon: Icons.rss_feed_outlined, body: 'Blog-RSS, Kategorien und Public-Artikel fuer mobile Leser bereitstellen.'),
                _OpsAction(label: 'Legal-Status', icon: Icons.gavel_outlined, body: 'Impressum, Datenschutz, AGB, Jugendschutz, Cookies und Widerrufsversionen pruefen.'),
              ],
            ),
            const SizedBox(height: 12),
            _OpsGroup(
              title: 'Admin-System',
              icon: Icons.settings_suggest_outlined,
              description: 'Testmails, Wartung, Audit-Export, Queue-Hinweise und Upload-Speicher.',
              actions: [
                _OpsAction(label: 'Testmail senden', icon: Icons.mark_email_read_outlined, body: 'Mailzustellung testen und Fehler fuer Admins sichtbar machen.'),
                _OpsAction(label: 'Wartungsmodus', icon: Icons.construction_outlined, body: 'Maintenance-Hinweis, Login-Verhalten und Public-Kommunikation vorbereiten.'),
                _OpsAction(label: 'Audit exportieren', icon: Icons.history_outlined, body: 'Systemaktionen, Adminentscheidungen und sensible Mutationen als Audit-Export vormerken.'),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _OpsGroup extends StatelessWidget {
  const _OpsGroup({required this.title, required this.icon, required this.description, required this.actions});

  final String title;
  final IconData icon;
  final String description;
  final List<_OpsAction> actions;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, color: AirmiusColors.blue, size: 28),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                    const SizedBox(height: 5),
                    Text(description, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              for (final action in actions)
                AirmiusButton(
                  label: action.label,
                  icon: action.icon,
                  secondary: true,
                  onPressed: () => openUiAction(context, title: action.label, body: action.body, status: 'Ops', icon: action.icon),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _OpsAction {
  const _OpsAction({required this.label, required this.icon, required this.body});

  final String label;
  final IconData icon;
  final String body;
}

