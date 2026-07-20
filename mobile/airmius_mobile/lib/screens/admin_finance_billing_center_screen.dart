import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'billing_operations_screen.dart';
import 'commerce_operations_screen.dart';
import 'system_admin_operations_screen.dart';

class AdminFinanceBillingCenterScreen extends StatefulWidget {
  const AdminFinanceBillingCenterScreen({super.key});

  @override
  State<AdminFinanceBillingCenterScreen> createState() => _AdminFinanceBillingCenterScreenState();
}

class _AdminFinanceBillingCenterScreenState extends State<AdminFinanceBillingCenterScreen> {
  String _section = 'Rechnungen';
  bool _showInvoices = true;
  bool _showPayments = true;
  bool _showSubscriptions = true;
  bool _showTransfers = true;

  final List<_FinanceItem> _items = const [
    _FinanceItem(title: 'Plattform-Rechnung', body: 'Abo-Rechnung für Verein, Providerkosten und Plattformgebuehren.', status: 'Offen', amount: '129 EUR', icon: Icons.receipt_long_outlined, color: AirmiusColors.blue),
    _FinanceItem(title: 'Subscription Invoice', body: 'Wiederkehrende Rechnung für Airmius-Mitgliedschaft oder Vereinsabo.', status: 'Faellig', amount: '49 EUR', icon: Icons.autorenew_outlined, color: AirmiusColors.amber),
    _FinanceItem(title: 'Payment Eingang', body: 'Zahlungseingang, Banktransfer, Überweisung oder manuelle Zuordnung.', status: 'Gebucht', amount: '89 EUR', icon: Icons.payments_outlined, color: AirmiusColors.green),
    _FinanceItem(title: 'Rückzahlung prüfen', body: 'Refund, Storno, fehlgeschlagene Zahlung oder Supportfall.', status: 'Prüfen', amount: '19 EUR', icon: Icons.undo_outlined, color: AirmiusColors.red),
    _FinanceItem(title: 'Commerce Order', body: 'Marktplatzbestellung, Outfit, Sponsorleistung oder digitale Leistung.', status: 'Commerce', amount: '239 EUR', icon: Icons.shopping_bag_outlined, color: AirmiusColors.blueDeep),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const PageTitle(title: 'Admin Rechnungen & Zahlungen', subtitle: 'Invoices, Payments, SubscriptionInvoices, Subscriptions, Banktransfer und Commerce-Abrechnung.'),
                        const SizedBox(height: 16),
                        _FinanceHero(onExport: () => _toast('Finanzexport vorbereitet')),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Bereich', value: _section, values: const ['Rechnungen', 'Payments', 'Subscriptions', 'Banktransfer', 'Commerce'], onChanged: (value) => setState(() => _section = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Finanzfilter',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Rechnungen anzeigen', subtitle: 'Admin-Invoices, SubscriptionInvoices und offene Posten.', value: _showInvoices, onChanged: (value) => setState(() => _showInvoices = value)),
                              _SwitchRow(title: 'Payments anzeigen', subtitle: 'Zahlungseingaenge, Providerstatus und manuelle Buchungen.', value: _showPayments, onChanged: (value) => setState(() => _showPayments = value)),
                              _SwitchRow(title: 'Subscriptions anzeigen', subtitle: 'Abo, Laufzeit, Verlängerung, Status und Abrechnung.', value: _showSubscriptions, onChanged: (value) => setState(() => _showSubscriptions = value)),
                              _SwitchRow(title: 'Banktransfer anzeigen', subtitle: 'Überweisungen, Referenzen, Zuordnung und Status.', value: _showTransfers, onChanged: (value) => setState(() => _showTransfers = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in _items) ...[
                          _FinanceCard(item: item, onOpen: () => _toast('${item.title}: Detail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Admin-Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Export', icon: Icons.download_outlined, onPressed: () => _toast('CSV/PDF Export vorbereitet')),
                              AirmiusButton(label: 'Billing Ops', icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BillingOperationsScreen()))),
                              AirmiusButton(label: 'Commerce', icon: Icons.shopping_bag_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CommerceOperationsScreen()))),
                              AirmiusButton(label: 'System Admin', icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SystemAdminOperationsScreen()))),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _FinanceHero extends StatelessWidget {
  const _FinanceHero({required this.onExport});

  final VoidCallback onExport;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF10243B), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AirmiusColors.borderStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusLogo(size: 42),
              const SizedBox(width: 12),
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('ADMIN FINANCE'), SizedBox(height: 4), Text('Rechnungen und Zahlungen steuern', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Export', icon: Icons.download_outlined, onPressed: onExport),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Admin-UI für Invoices, Payments, Subscriptions, SubscriptionInvoices und Commerce-Abrechnung als mobile Plattform-Finanzflaeche.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '5', label: 'Bereiche')), SizedBox(width: 10), Expanded(child: MetricCard(value: '329', label: 'EUR')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Offen'))]),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({required this.title, required this.value, required this.values, required this.onChanged});

  final String title;
  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final item in values)
            ChoiceChip(
              label: Text(item),
              selected: value == item,
              onSelected: (_) => onChanged(item),
              selectedColor: AirmiusColors.blue.withValues(alpha: .24),
              backgroundColor: AirmiusColors.card,
              labelStyle: TextStyle(color: value == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
              side: BorderSide(color: value == item ? AirmiusColors.blue : AirmiusColors.border),
            ),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.title, required this.subtitle, required this.value, required this.onChanged});

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700))])),
        Switch.adaptive(value: value, onChanged: onChanged, activeThumbColor: AirmiusColors.blue),
      ]),
    );
  }
}

class _FinanceCard extends StatelessWidget {
  const _FinanceCard({required this.item, required this.onOpen});

  final _FinanceItem item;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: item.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: item.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .5))), child: Icon(item.icon, color: item.color)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.amount, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _FinanceItem {
  const _FinanceItem({required this.title, required this.body, required this.status, required this.amount, required this.icon, required this.color});

  final String title;
  final String body;
  final String status;
  final String amount;
  final IconData icon;
  final Color color;
}
