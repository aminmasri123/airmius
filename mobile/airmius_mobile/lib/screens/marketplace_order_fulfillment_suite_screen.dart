import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class MarketplaceOrderFulfillmentSuiteScreen extends StatefulWidget {
  const MarketplaceOrderFulfillmentSuiteScreen({super.key});

  @override
  State<MarketplaceOrderFulfillmentSuiteScreen> createState() => _MarketplaceOrderFulfillmentSuiteScreenState();
}

class _MarketplaceOrderFulfillmentSuiteScreenState extends State<MarketplaceOrderFulfillmentSuiteScreen> {
  String fulfillment = 'Abholung';
  bool clubShopEnabled = true;
  bool sponsorOffers = true;
  bool allowReturns = true;
  bool notifyOrderStatus = true;

  @override
  Widget build(BuildContext context) {
    final orders = [
      const _OrderRow(
        title: 'ZBB Trainingsshirt',
        status: 'Bereit',
        price: '29 EUR',
        body: 'Clubartikel ist bezahlt und kann beim Verein abgeholt werden.',
        color: AirmiusColors.green,
      ),
      const _OrderRow(
        title: 'Airmius Hoodie',
        status: 'Versand',
        price: '49 EUR',
        body: 'Bestellung wird versendet. Tracking, Rechnung und Support sind vorbereitet.',
        color: AirmiusColors.blue,
      ),
      const _OrderRow(
        title: 'Sponsor Gutschein',
        status: 'Aktiv',
        price: 'Code',
        body: 'Sponsorangebot mit Gültigkeit, Clubbezug und Einloesehinweis.',
        color: AirmiusColors.amber,
      ),
    ];

    return PageFrame(
      title: 'Marketplace',
      subtitle: 'Clubshop, Bestellungen und Abholung',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('COMMERCE'),
                const SizedBox(height: 8),
                const Text(
                  'Die mobile App braucht eine Shop-Strecke für Clubartikel, Sponsorangebote, Warenkorb, Bestellungen, Abholung, Versand, Rückgaben und Statusmeldungen.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '3', label: 'Items'),
                    Metric(value: 'Cart', label: 'Warenkorb'),
                    Metric(value: 'Pay', label: 'Zahlung'),
                    Metric(value: 'Ship', label: 'Status'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('ERFUELLUNG'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Abholung', label: Text('Abholung')),
                    ButtonSegment(value: 'Versand', label: Text('Versand')),
                    ButtonSegment(value: 'Digital', label: Text('Digital')),
                  ],
                  selected: {fulfillment},
                  onSelectionChanged: (value) => setState(() => fulfillment = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('OPTIONEN'),
                const SizedBox(height: 8),
                _MarketplaceSwitch(title: 'Clubshop aktivieren', value: clubShopEnabled, color: AirmiusColors.green, onChanged: (value) => setState(() => clubShopEnabled = value)),
                _MarketplaceSwitch(title: 'Sponsorangebote anzeigen', value: sponsorOffers, color: AirmiusColors.blue, onChanged: (value) => setState(() => sponsorOffers = value)),
                _MarketplaceSwitch(title: 'Rückgaben erlauben', value: allowReturns, color: AirmiusColors.amber, onChanged: (value) => setState(() => allowReturns = value)),
                _MarketplaceSwitch(title: 'Statusbenachrichtigung', value: notifyOrderStatus, color: AirmiusColors.pink, onChanged: (value) => setState(() => notifyOrderStatus = value)),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final order in orders) ...[
            _OrderCard(order: order),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Aktuelle Erfuellung: $fulfillment. Später verbindet die API Produkte, Warenkorb, Zahlung, Rechnung, Bestellstatus, Abholung, Versand und Support.',
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Bestellung vorbereiten',
                  icon: Icons.shopping_bag_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Bestellung vorbereiten',
                    body: 'Diese UI bereitet Clubshop, Warenkorb, Zahlung, Abholung, Versand, Rückgabe und Bestellstatus für die spätere Laravel-API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.shopping_bag_outlined,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _OrderRow {
  const _OrderRow({
    required this.title,
    required this.status,
    required this.price,
    required this.body,
    required this.color,
  });

  final String title;
  final String status;
  final String price;
  final String body;
  final Color color;
}

class _MarketplaceSwitch extends StatelessWidget {
  const _MarketplaceSwitch({
    required this.title,
    required this.value,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final Color color;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
      value: value,
      activeColor: color,
      onChanged: onChanged,
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order});

  final _OrderRow order;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon: Icons.storefront_outlined, color: order.color),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(child: Text(order.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                        StatusPill(order.status, color: order.color),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(order.price, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 8),
                    Text(order.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(
                label: 'Details',
                icon: Icons.inventory_2_outlined,
                onPressed: () => openUiAction(
                  context,
                  title: 'Bestelldetails',
                  body: 'Bestelldetails können später Produkte, Menge, Rechnung, Zahlung, Status und Supportverlauf enthalten.',
                  status: 'UI vorbereitet',
                  icon: Icons.inventory_2_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Lieferung',
                icon: Icons.local_shipping_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Lieferstatus',
                  body: 'Abholung, Versand, Tracking, Vereinshinweise und Benachrichtigungen werden für die API vorbereitet.',
                  status: 'UI vorbereitet',
                  icon: Icons.local_shipping_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Rückgabe',
                icon: Icons.undo_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Rückgabe',
                  body: 'Rückgaben können später Grund, Status, Verein, Zahlung und Supportticket verbinden.',
                  status: 'UI vorbereitet',
                  icon: Icons.undo_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
