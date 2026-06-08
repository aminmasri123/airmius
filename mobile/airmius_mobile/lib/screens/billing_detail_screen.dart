import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'checkout_status_screen.dart';
import 'ui_action_result_screen.dart';

class BillingDetailScreen extends StatefulWidget {
  const BillingDetailScreen({super.key, required this.title, required this.body, required this.status, required this.amount, required this.icon});

  final String title;
  final String body;
  final String status;
  final String amount;
  final IconData icon;

  @override
  State<BillingDetailScreen> createState() => _BillingDetailScreenState();
}

class _BillingDetailScreenState extends State<BillingDetailScreen> {
  String _payment = 'Ueberweisung';
  bool _autoRenew = true;

  @override
  Widget build(BuildContext context) {
    final open = widget.status == 'Offen';
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.body,
        trailing: StatusPill(widget.status, color: open ? AirmiusColors.amber : AirmiusColors.green),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(widget.icon, color: AirmiusColors.blue, size: 40),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Eyebrow('Billing Detail'),
              const SizedBox(height: 6),
              Text(widget.amount, style: const TextStyle(color: AirmiusColors.text, fontSize: 28, fontWeight: FontWeight.w900)),
              const SizedBox(height: 6),
              Text(widget.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
            ])),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: 'PDF', label: 'Rechnung')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'SEPA', label: 'Option')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'API', label: 'Spaeter'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Zahlung'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(value: _payment, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Zahlmethode'), items: const ['Ueberweisung', 'SEPA-Lastschrift', 'Kreditkarte', 'PayPal'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: (value) => setState(() => _payment = value ?? _payment)),
            SwitchListTile(value: _autoRenew, onChanged: (value) => setState(() => _autoRenew = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Automatisch verlaengern', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Spaeter ueber Provider/API steuerbar.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Rechnung & Banktransfer'),
            SizedBox(height: 10),
            _BillingActionLine(icon: Icons.picture_as_pdf_outlined, title: 'Rechnung PDF', body: 'PDF anzeigen, herunterladen oder per E-Mail senden.', status: 'PDF'),
            _BillingActionLine(icon: Icons.account_balance_outlined, title: 'Banktransfer', body: 'IBAN, Verwendungszweck und automatische Zuordnung vorbereiten.', status: 'Bank'),
            _BillingActionLine(icon: Icons.history_outlined, title: 'Zahlungsverlauf', body: 'Mahnungen, Statuswechsel und Audit Trail anzeigen.', status: 'Historie'),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: open ? 'Zahlung erfassen' : 'Rechnung laden', icon: open ? Icons.payments_outlined : Icons.download_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: open ? 'Zahlung erfassen' : 'Rechnung laden', body: open ? 'Zahlung, Provider-Referenz und Rechnungsausgleich vorbereiten.' : 'Rechnungs-PDF laden, teilen und Verlauf aktualisieren.', status: open ? 'Zahlung' : 'PDF', icon: open ? Icons.payments_outlined : Icons.download_outlined)))),
            AirmiusButton(label: 'Plan wechseln', icon: Icons.swap_horiz_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Plan wechseln', body: 'Abo-Plan, Preis, Laufzeit und Checkout-Redirect vorbereiten.', status: 'Plan', icon: Icons.swap_horiz_outlined)))),
            AirmiusButton(label: 'Checkout Erfolg', icon: Icons.check_circle_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutStatusScreen(flow: 'Subscription', status: 'Success', amount: widget.amount)))),
            AirmiusButton(label: 'Banktransfer', icon: Icons.account_balance_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutStatusScreen(flow: 'Subscription', status: 'Banktransfer', amount: widget.amount)))),
            AirmiusButton(label: 'Kuendigen', icon: Icons.cancel_outlined, danger: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Abo kuendigen', body: 'Kuendigungsfrist, Warnung, Bestaetigung und Provider-API vorbereiten.', status: 'Kuendigung', icon: Icons.cancel_outlined)))),
          ]),
        ]),
      ),
    );
  }
}

class _BillingActionLine extends StatelessWidget {
  const _BillingActionLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(padding: const EdgeInsets.only(top: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])), StatusPill(status)]));
  }
}
