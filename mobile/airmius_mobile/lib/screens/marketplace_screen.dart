import 'package:flutter/material.dart';
import 'marketplace_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'checkout_screen.dart';
import 'product_detail_screen.dart';

class MarketplaceScreen extends StatefulWidget {
  const MarketplaceScreen({super.key});

  @override
  State<MarketplaceScreen> createState() => _MarketplaceScreenState();
}

class _MarketplaceScreenState extends State<MarketplaceScreen> {
  String _query = '';
  String _category = 'Alle';

  @override
  Widget build(BuildContext context) {
    final normalized = _query.trim().toLowerCase();
    final products = _products.where((product) {
      final matchesQuery = normalized.isEmpty || product.title.toLowerCase().contains(normalized) || product.provider.toLowerCase().contains(normalized);
      final matchesCategory = _category == 'Alle' || product.category == _category;
      return matchesQuery && matchesCategory;
    }).toList();

    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.storefront_outlined), label: const Text('Shop Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => MarketplaceOperationsScreen(initialTab: 'Shop')))),
        
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Marketplace', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Marketplace',
        subtitle: 'Produkte, Anbieter, Warenkorb und Bestellungen',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Shop'),
                  const SizedBox(height: 8),
                  const Text('Sportprodukte, Vereinsservices und Anbieterprofile im nativen Airmius-Stil.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  SearchBox(hint: 'Produkt oder Anbieter suchen', onChanged: (value) => setState(() => _query = value)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final category in const ['Alle', 'Ausrüstung', 'Kleidung', 'Service'])
                  ChoiceChip(
                    selected: _category == category,
                    label: Text(category),
                    onSelected: (_) => setState(() => _category = category),
                    selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                    backgroundColor: AirmiusColors.cardSoft,
                    side: BorderSide(color: _category == category ? AirmiusColors.blue : AirmiusColors.border),
                    labelStyle: TextStyle(color: _category == category ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '3', label: 'Produkte')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '2', label: 'Orders')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '1', label: 'Cart')),
              ],
            ),
            const SizedBox(height: 14),
            for (final product in products) ...[
              _ProductCard(product: product),
              const SizedBox(height: 12),
            ],
            if (products.isEmpty) const AirmiusPanel(child: Padding(padding: EdgeInsets.all(18), child: Center(child: Text('Keine Produkte gefunden.', style: TextStyle(color: AirmiusColors.muted))))),
            const SizedBox(height: 14),
            const _CartPanel(),
            const SizedBox(height: 14),
            const _ProviderPanel(),
            const SizedBox(height: 14),
            const _OrdersPanel(),
          ],
        ),
      ),
    );
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({required this.product});

  final _Product product;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ProductDetailScreen(title: product.title, provider: product.provider, description: product.description, category: product.category, price: product.price, icon: product.icon))),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 68,
            height: 68,
            decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.border)),
            child: Icon(product.icon, color: AirmiusColors.blue, size: 32),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(product.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 17)),
                const SizedBox(height: 4),
                Text(product.description, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
                const SizedBox(height: 9),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(product.category), StatusPill(product.price, color: AirmiusColors.green)]),
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ],
      ),
    );
  }
}

class _CartPanel extends StatelessWidget {
  const _CartPanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: AirmiusColors.green.withValues(alpha: 0.50),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Warenkorb'),
          const SizedBox(height: 10),
          const _ShopLine(icon: Icons.shopping_cart_outlined, title: 'Airmius Teamshirt', body: '1x Groesse M - Blau', trailing: '29,90'),
          const SizedBox(height: 12),
          AirmiusButton(label: 'Checkout starten', icon: Icons.shopping_cart_checkout_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutScreen()))),
        ],
      ),
    );
  }
}

class _OrdersPanel extends StatelessWidget {
  const _OrdersPanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Bestellungen & Rückgaben'),
          const SizedBox(height: 10),
          const _ShopLine(icon: Icons.receipt_long_outlined, title: 'Bestellung #A-1024', body: 'Bezahlt - Versand wird vorbereitet', trailing: 'Aktiv'),
          const _ShopLine(icon: Icons.assignment_return_outlined, title: 'Rückgabe #R-88', body: 'Rückgabeanfrage wird geprüft', trailing: 'Offen'),
          const SizedBox(height: 12),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Rückgabe anfragen', icon: Icons.assignment_return_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Rückgabe anfragen', body: 'Return-Request, Grund, Fotos, Frist und Supportstatus vorbereiten.', status: 'Retour', icon: Icons.assignment_return_outlined)),
            AirmiusButton(label: 'Problem melden', icon: Icons.report_outlined, danger: true, onPressed: () => openUiAction(context, title: 'Bestellproblem melden', body: 'Order-Issue, Nachricht, Anhang und Anbieterantwort vorbereiten.', status: 'Support', icon: Icons.report_outlined)),
          ]),
        ],
      ),
    );
  }
}

class _ProviderPanel extends StatelessWidget {
  const _ProviderPanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: AirmiusColors.blue.withValues(alpha: 0.50),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Anbieter werden'),
          const SizedBox(height: 8),
          const Text('Die Web-App erlaubt Seller-Bewerbung, Anbieterprofil, Standorte und Marketplace-Sichtbarkeit. Diese Mobile-UI bereitet den Einstieg vor.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          const SizedBox(height: 12),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Verkaeuferbewerbung', icon: Icons.storefront_outlined, onPressed: () => openUiAction(context, title: 'Verkaeuferbewerbung', body: 'Seller-Application, Nachweise, Anbieterrolle und Freigabeprozess vorbereiten.', status: 'Seller', icon: Icons.storefront_outlined)),
            AirmiusButton(label: 'Anbieterprofil', icon: Icons.badge_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Anbieterprofil', body: 'Providerprofil, Anzeigename, Kontakt, Standorte und Sichtbarkeit vorbereiten.', status: 'Provider', icon: Icons.badge_outlined)),
          ]),
        ],
      ),
    );
  }
}

class _ShopLine extends StatelessWidget {
  const _ShopLine({required this.icon, required this.title, required this.body, required this.trailing});

  final IconData icon;
  final String title;
  final String body;
  final String trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3))])),
          StatusPill(trailing),
        ],
      ),
    );
  }
}

class _Product {
  const _Product({required this.title, required this.provider, required this.description, required this.category, required this.price, required this.icon});

  final String title;
  final String provider;
  final String description;
  final String category;
  final String price;
  final IconData icon;
}

const _products = [
  _Product(title: 'Airmius Teamshirt', provider: 'Airmius Shop', description: 'Vereinskleidung mit Varianten und Bestand.', category: 'Kleidung', price: '29,90', icon: Icons.checkroom_outlined),
  _Product(title: 'Trainingspaket Starter', provider: 'Airmius Running Club', description: 'Plan, Video und Coach-Feedback.', category: 'Service', price: '49,00', icon: Icons.fitness_center),
  _Product(title: 'Vereinsball Set', provider: 'Tennis Zentrum West', description: '12 Trainingsbaelle für Teams.', category: 'Ausrüstung', price: '39,90', icon: Icons.sports_tennis),
];

