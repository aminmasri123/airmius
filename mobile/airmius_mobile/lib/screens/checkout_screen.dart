import 'package:flutter/material.dart';
import 'marketplace_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'checkout_status_screen.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  String _payment = 'Kreditkarte';
  String _delivery = 'Versand';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.storefront_outlined), label: const Text('Checkout Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => MarketplaceOperationsScreen(initialTab: 'Checkout')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Checkout', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Checkout',
        subtitle: 'Adresse, Zahlung, Zusammenfassung, Rechnung und Bestellung',
        trailing: const StatusPill('29,90', color: AirmiusColors.green),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [Eyebrow('Warenkorb'), SizedBox(height: 8), Text('Airmius Teamshirt - Groesse M - Blau', style: TextStyle(color: AirmiusColors.text, fontSize: 21, fontWeight: FontWeight.w900)), SizedBox(height: 8), Text('Checkout-UI für Laravel API: Adresse, Zahlungsart, Rechnung, Rückgabe und Orderstatus.', style: TextStyle(color: AirmiusColors.muted, height: 1.4))])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
            Eyebrow('Lieferadresse'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Name', hint: 'ZBB Konto'),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Adresse', hint: 'Straße, PLZ, Stadt'),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Zahlung & Lieferung'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(initialValue: _payment, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Zahlmethode'), items: const ['Kreditkarte', 'PayPal', 'Überweisung', 'Bar beim Verein'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: (value) => setState(() => _payment = value ?? _payment)),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(initialValue: _delivery, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Lieferart'), items: const ['Versand', 'Abholung beim Verein'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: (value) => setState(() => _delivery = value ?? _delivery)),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Zusammenfassung'),
            SizedBox(height: 10),
            _CheckoutLine(label: 'Produkt', value: '29,90 EUR'),
            _CheckoutLine(label: 'Versand', value: '0,00 EUR'),
            _CheckoutLine(label: 'Summe', value: '29,90 EUR'),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Bestellung abschließen', icon: Icons.lock_outline, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutStatusScreen(flow: 'Commerce', status: 'Success', amount: '29,90 EUR')))),
            AirmiusButton(label: 'Banktransfer', icon: Icons.account_balance_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutStatusScreen(flow: 'Commerce', status: 'Banktransfer', amount: '29,90 EUR')))),
            AirmiusButton(label: 'Abbruch simulieren', icon: Icons.cancel_outlined, danger: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutStatusScreen(flow: 'Commerce', status: 'Cancel', amount: '29,90 EUR')))),
          ]),
        ]),
      ),
    );
  }
}

class _CheckoutLine extends StatelessWidget {
  const _CheckoutLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(padding: const EdgeInsets.only(top: 10), child: Row(children: [Expanded(child: Text(label, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800))), Text(value, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))]));
  }
}

