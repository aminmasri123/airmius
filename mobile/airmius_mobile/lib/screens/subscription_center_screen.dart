import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'billing_operations_screen.dart';
import 'checkout_status_screen.dart';
import 'ui_action_result_screen.dart';
import 'billing_detail_screen.dart';

class SubscriptionCenterScreen extends StatefulWidget {
  const SubscriptionCenterScreen({super.key});

  @override
  State<SubscriptionCenterScreen> createState() => _SubscriptionCenterScreenState();
}

class _SubscriptionCenterScreenState extends State<SubscriptionCenterScreen> {
  String _filter = 'Aktiv';

  @override
  Widget build(BuildContext context) {
    final items = _subscriptions.where((item) => _filter == 'Alle' || item.status == _filter).toList();
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Abos & Rechnungen', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Abos & Rechnungen',
        subtitle: 'Plaene, Checkouts, offene Zahlungen, Rechnungen und Banktransfer',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Billing'),
                  const SizedBox(height: 8),
                  const Text('User- und Vereinsabos mit Zahlungsstatus, Rechnungen, Banktransfer und Kündigung.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final filter in const ['Aktiv', 'Offen', 'Alle'])
                        ChoiceChip(
                          selected: _filter == filter,
                          label: Text(filter),
                          onSelected: (_) => setState(() => _filter = filter),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _filter == filter ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _filter == filter ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '2', label: 'Aktiv')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Offen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Invoices'))]),
            const SizedBox(height: 14),
            for (final item in items) ...[
              _SubscriptionCard(item: item),
              const SizedBox(height: 12),
            ],
            const _PlansPanel(),
            const SizedBox(height: 14),
            const _InvoicePanel(),
            const SizedBox(height: 14),
            AirmiusButton(label: 'Billing Operations', icon: Icons.account_balance_wallet_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BillingOperationsScreen()))),
          ],
        ),
      ),
    );
  }
}

class _SubscriptionCard extends StatelessWidget {
  const _SubscriptionCard({required this.item});

  final _SubscriptionItem item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BillingDetailScreen(title: item.title, body: item.body, status: item.status, amount: item.price, icon: item.icon))),
      borderColor: item.status == 'Offen' ? AirmiusColors.green.withValues(alpha: 0.45) : AirmiusColors.border,
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(item.icon, color: AirmiusColors.blue),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)), const SizedBox(height: 9), Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.status), StatusPill(item.price, color: AirmiusColors.green)])])),
        const Icon(Icons.chevron_right, color: AirmiusColors.muted),
      ]),
    );
  }
}

class _PlansPanel extends StatelessWidget {
  const _PlansPanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const Eyebrow('Plaene'),
        const SizedBox(height: 10),
        const _BillingLine(icon: Icons.workspace_premium_outlined, title: 'Player Pro', body: 'Erweiterte Funktionen für Einzeluser', trailing: '9,90'),
        const _BillingLine(icon: Icons.apartment_outlined, title: 'Club Pro', body: 'Verein, Teams, Mitglieder und Dokumente', trailing: '49,00'),
        const SizedBox(height: 12),
        AirmiusButton(label: 'Plan wechseln', icon: Icons.swap_horiz_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Plan wechseln', body: 'Abo-Plan wechseln, Checkout starten und Rechnung aktualisieren.', status: 'Abo', icon: Icons.swap_horiz_outlined)))),
      ]),
    );
  }
}

class _InvoicePanel extends StatelessWidget {
  const _InvoicePanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const Eyebrow('Rechnungen & Zahlungen'),
        const SizedBox(height: 10),
        const _BillingLine(icon: Icons.receipt_long_outlined, title: 'Rechnung #2026-004', body: 'Bezahlt per Überweisung', trailing: 'PDF'),
        const _BillingLine(icon: Icons.account_balance_outlined, title: 'Banktransfer offen', body: 'Zahlung wartet auf Zuordnung', trailing: 'Offen'),
        const _BillingLine(icon: Icons.cancel_schedule_send_outlined, title: 'Kündigung vormerken', body: 'Abo pausieren, kündigen oder erneuern', trailing: 'Aktion'),
        const SizedBox(height: 12),
        Wrap(spacing: 10, runSpacing: 10, children: [
          AirmiusButton(label: 'Success', icon: Icons.check_circle_outline, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutStatusScreen(flow: 'Subscription', status: 'Success', amount: '49,00 EUR')))),
          AirmiusButton(label: 'Banktransfer', icon: Icons.account_balance_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutStatusScreen(flow: 'Subscription', status: 'Banktransfer', amount: '49,00 EUR')))),
          AirmiusButton(label: 'Cancel', icon: Icons.cancel_outlined, danger: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutStatusScreen(flow: 'Subscription', status: 'Cancel', amount: '49,00 EUR')))),
        ]),
      ]),
    );
  }
}

class _BillingLine extends StatelessWidget {
  const _BillingLine({required this.icon, required this.title, required this.body, required this.trailing});

  final IconData icon;
  final String title;
  final String body;
  final String trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: AirmiusColors.blue),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3))])),
        StatusPill(trailing),
      ]),
    );
  }
}

class _SubscriptionItem {
  const _SubscriptionItem({required this.title, required this.body, required this.status, required this.price, required this.icon});

  final String title;
  final String body;
  final String status;
  final String price;
  final IconData icon;
}

const _subscriptions = [
  _SubscriptionItem(title: 'User Abo', body: 'Player Pro - monatlich - erneuert automatisch', status: 'Aktiv', price: '9,90', icon: Icons.person_outline),
  _SubscriptionItem(title: 'Club Abo ZBB', body: 'Club Pro - Vereinsbereich und Mitgliedschaften', status: 'Aktiv', price: '49,00', icon: Icons.apartment_outlined),
  _SubscriptionItem(title: 'Offene Beitragsrechnung', body: 'Banktransfer wartet auf Zahlung oder Zuordnung', status: 'Offen', price: '12,00', icon: Icons.account_balance_outlined),
];
