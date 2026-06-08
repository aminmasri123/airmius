import 'package:flutter/material.dart';
import 'marketplace_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'checkout_screen.dart';
import 'ui_action_result_screen.dart';
import 'marketplace_operations_screen.dart';

class ProductDetailScreen extends StatefulWidget {
  const ProductDetailScreen({super.key, required this.title, required this.provider, required this.description, required this.category, required this.price, required this.icon});

  final String title;
  final String provider;
  final String description;
  final String category;
  final String price;
  final IconData icon;

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  String _size = 'M';
  String _variant = 'Blau';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.storefront_outlined), label: const Text('Product Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => MarketplaceOperationsScreen(initialTab: 'Shop')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: '${widget.provider} - Produktdetail, Varianten, Anbieter und Bewertungen',
        trailing: StatusPill(widget.price, color: AirmiusColors.green),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Container(height: 190, decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.border)), child: Icon(widget.icon, color: AirmiusColors.blue, size: 72)),
            const SizedBox(height: 14),
            Text(widget.description, style: const TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(widget.category), const StatusPill('Verifizierter Anbieter'), const StatusPill('Bestand ok', color: AirmiusColors.green)]),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '4.8', label: 'Bewertung')), SizedBox(width: 10), Expanded(child: MetricCard(value: '12', label: 'Bestand')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2-4', label: 'Tage'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Varianten'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(value: _size, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Groesse'), items: const ['S', 'M', 'L', 'XL'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: (value) => setState(() => _size = value ?? _size)),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(value: _variant, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Variante'), items: const ['Blau', 'Schwarz', 'Weiss'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: (value) => setState(() => _variant = value ?? _variant)),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Anbieter & Fulfillment'),
            SizedBox(height: 10),
            _InfoLine(icon: Icons.storefront_outlined, title: 'Airmius Shop', body: 'Verifizierter Anbieter mit Rueckgabeprozess und Rechnung.'),
            _InfoLine(icon: Icons.local_shipping_outlined, title: 'Versand', body: 'Standardversand, Abholung beim Verein spaeter per API moeglich.'),
            _InfoLine(icon: Icons.assignment_return_outlined, title: 'Rueckgabe', body: 'Retourenstatus, Frist und Supportfall als native UI vorbereitet.'),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'In Warenkorb', icon: Icons.add_shopping_cart_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'In Warenkorb', body: '${widget.title} mit Groesse $_size und Variante $_variant zum Warenkorb hinzufuegen.', status: 'Cart', icon: Icons.add_shopping_cart_outlined)))),
            AirmiusButton(label: 'Checkout', icon: Icons.shopping_cart_checkout_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutScreen()))),
          ]),
        ]),
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  const _InfoLine({required this.icon, required this.title, required this.body});

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Padding(padding: const EdgeInsets.only(top: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))]))]));
  }
}

