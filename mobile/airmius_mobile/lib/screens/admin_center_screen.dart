import 'package:flutter/material.dart';
import 'trust_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'access_operations_screen.dart';
import 'admin_detail_screen.dart';
import 'billing_operations_screen.dart';
import 'commerce_operations_screen.dart';
import 'content_operations_screen.dart';
import 'outfit_operations_screen.dart';
import 'platform_operations_screen.dart';
import 'social_operations_screen.dart';
import 'ui_action_result_screen.dart';

class AdminCenterScreen extends StatefulWidget {
  const AdminCenterScreen({super.key});

  @override
  State<AdminCenterScreen> createState() => _AdminCenterScreenState();
}

class _AdminCenterScreenState extends State<AdminCenterScreen> {
  String _area = 'Übersicht';

  @override
  Widget build(BuildContext context) {
    final items = _items.where((item) => _area == 'Übersicht' || item.area == _area).toList();
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Admin', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Admin',
        subtitle: 'System, Nutzer, Moderation, Commerce, Abos und Einstellungen',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Systemverwaltung'),
                  const SizedBox(height: 8),
                  const Text('Native Admin-Oberflaeche für Rollen, Prüfungen, Zahlungen, Moderation und Commerce.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final area in const ['Übersicht', 'Nutzer', 'Moderation', 'Billing', 'Commerce', 'Content', 'Outfit', 'System'])
                        ChoiceChip(
                          selected: _area == area,
                          label: Text(area),
                          onSelected: (_) => setState(() => _area = area),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _area == area ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _area == area ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '2', label: 'Reports')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '1', label: 'Prüfung')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '4', label: 'Zahlungen')),
              ],
            ),
            const SizedBox(height: 14),
            for (final item in items) ...[
              _AdminCard(item: item),
              const SizedBox(height: 12),
            ],
            const SizedBox(height: 4),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                AirmiusButton(label: 'Admin-Aktion erstellen', icon: Icons.add_moderator_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Admin-Aktion erstellen', body: 'Moderations-, System-, Billing- oder Commerce-Aktion vorbereiten und auditieren.', status: 'Admin', icon: Icons.add_moderator_outlined)))),
                AirmiusButton(label: 'Plattformbetrieb', icon: Icons.monitor_heart_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PlatformOperationsScreen()))),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _AdminCard extends StatelessWidget {
  const _AdminCard({required this.item});

  final _AdminItem item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => item.title == 'Plattformbetrieb' ? const PlatformOperationsScreen() : item.title == 'Club-Verifizierungen' || item.title == 'Reports & Flags' ? const TrustOperationsScreen() : item.title == 'Commerce Admin' ? const CommerceOperationsScreen() : item.title == 'Abos & Rechnungen' ? const BillingOperationsScreen() : item.area == 'Content' ? const ContentOperationsScreen() : item.area == 'Outfit' ? const OutfitOperationsScreen() : item.area == 'Moderation' ? const SocialOperationsScreen() : item.area == 'Nutzer' ? const AccessOperationsScreen() : AdminDetailScreen(area: item.area, title: item.title, body: item.body, status: item.status, icon: item.icon, urgent: item.urgent))),
      borderColor: item.urgent ? AirmiusColors.red.withValues(alpha: 0.45) : AirmiusColors.border,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(item.icon, color: item.urgent ? AirmiusColors.red : AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
                const SizedBox(height: 8),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.area), StatusPill(item.status, color: item.urgent ? AirmiusColors.red : AirmiusColors.blue)]),
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ],
      ),
    );
  }
}

class _AdminItem {
  const _AdminItem({required this.area, required this.title, required this.body, required this.status, required this.icon, this.urgent = false});

  final String area;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final bool urgent;
}

const _items = [
  _AdminItem(area: 'Nutzer', title: 'Mitglieder & Users', body: 'Nutzerliste, Rollen, Inaktivitaet und Profilstatus.', status: 'Aktiv', icon: Icons.people_alt_outlined),
  _AdminItem(area: 'Nutzer', title: 'Rollen & Berechtigungen', body: 'Permissions, Rollen, Club Admins und Systemrechte.', status: 'Rollen', icon: Icons.rule_outlined),
  _AdminItem(area: 'Moderation', title: 'Content Reports', body: 'Beiträge, Kommentare und Meldungen prüfen.', status: '2 offen', icon: Icons.report_outlined, urgent: true),
  _AdminItem(area: 'Moderation', title: 'Club-Verifizierungen', body: 'Vereine prüfen, genehmigen oder ablehnen.', status: '1 offen', icon: Icons.verified_user_outlined),
  _AdminItem(area: 'Moderation', title: 'Reports & Flags', body: 'Moderationsflags, Nutzerreports, Entscheidungen und Eskalation.', status: 'Review', icon: Icons.flag_outlined, urgent: true),
  _AdminItem(area: 'Billing', title: 'Abos & Rechnungen', body: 'Subscription-Plans, Zahlstatus, Banktransfer und Rechnungen.', status: 'Billing', icon: Icons.receipt_long_outlined),
  _AdminItem(area: 'Billing', title: 'Provider Costs', body: 'Kosten, Payouts und Zahlungsanbieter im Blick.', status: 'Kosten', icon: Icons.account_balance_wallet_outlined),
  _AdminItem(area: 'Commerce', title: 'Commerce Admin', body: 'Produkte, Coupons, Bestellungen, Retouren und Payouts.', status: 'Shop', icon: Icons.storefront_outlined),
  _AdminItem(area: 'Commerce', title: 'Seller Applications', body: 'Verkaeuferbewerbungen, Providerprofile, Standorte und Marketplace-Freigabe.', status: 'Seller', icon: Icons.fact_check_outlined),
  _AdminItem(area: 'Content', title: 'Badges verwalten', body: 'Badges erstellen, Regeln prüfen, Sichtbarkeit und Achievement-Historie.', status: 'Badges', icon: Icons.workspace_premium_outlined),
  _AdminItem(area: 'Content', title: 'Sportarten verwalten', body: 'Sportarten, Disziplinen, Leistungsfelder und KI-Readiness konfigurieren.', status: 'Sports', icon: Icons.sports_outlined),
  _AdminItem(area: 'Content', title: 'Learning Quality', body: 'Kurse, Aufgaben, Zertifikate und Qualitaetsfreigabe prüfen.', status: 'Learning', icon: Icons.school_outlined),
  _AdminItem(area: 'Outfit', title: 'Outfit Admin', body: 'Lieferungen, Zahlstatus, Adresse, Reminder, Kündigung und Visuals.', status: 'Outfit', icon: Icons.checkroom_outlined),
  _AdminItem(area: 'System', title: 'Mail Center', body: 'Absender, Testmails, Zustellungen und Fehler.', status: 'System', icon: Icons.mark_email_read_outlined),
  _AdminItem(area: 'System', title: 'System Settings', body: 'Globale Einstellungen, Maintenance und Plattform-Konfiguration.', status: 'Config', icon: Icons.settings_suggest_outlined),
  _AdminItem(area: 'System', title: 'Plattformbetrieb', body: 'API, Webhooks, SEO, Gast-Checkout, Wartung und Auditstatus.', status: 'Ops', icon: Icons.monitor_heart_outlined),
];

