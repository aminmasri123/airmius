import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'support_helpdesk_screen.dart';

class GuestMarketplaceBuyerScreen extends StatefulWidget {
  const GuestMarketplaceBuyerScreen({super.key});

  @override
  State<GuestMarketplaceBuyerScreen> createState() => _GuestMarketplaceBuyerScreenState();
}

class _GuestMarketplaceBuyerScreenState extends State<GuestMarketplaceBuyerScreen> {
  String _tab = 'Produkte';
  bool _showProviders = true;
  bool _showWishlist = true;
  bool _showOrders = true;
  bool _showBankTransfer = true;

  final List<_MarketItem> _items = const [
    _MarketItem(title: 'Airmius Starter Paket', area: 'Produkte', body: 'Digitales Vereinspaket mit Onboarding, Checkliste und Dokumentvorlagen.', status: 'Produkt', price: '49 EUR', icon: Icons.shopping_bag_outlined, color: AirmiusColors.blue),
    _MarketItem(title: 'Provider Profil', area: 'Provider', body: 'Anbieterprofil mit Leistungen, Kontakt, Bewertungen und Produktübersicht.', status: 'Anbieter', price: 'Verifiziert', icon: Icons.storefront_outlined, color: AirmiusColors.green),
    _MarketItem(title: 'Wishlist', area: 'Wishlist', body: 'Gespeicherte Produkte, Favoriten, Vergleich und später kaufen.', status: 'Merkliste', price: '3 Items', icon: Icons.favorite_border_outlined, color: AirmiusColors.red),
    _MarketItem(title: 'Order Status', area: 'Bestellungen', body: 'Bestellstatus, Banktransfer, Zahlungshinweis, Rechnung und Supportkontakt.', status: 'Offen', price: '129 EUR', icon: Icons.local_shipping_outlined, color: AirmiusColors.amber),
  ];

  List<_MarketItem> get _visibleItems => _items.where((item) => _tab == 'Alle' || item.area == _tab).toList();

  @override
  Widget build(BuildContext context) {
    final items = _visibleItems;

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
                        const PageTitle(title: 'Marketplace Guest', subtitle: 'Produkte, Provider, Wishlist, Order Status, Banktransfer und Support als mobile Buyer-UI.'),
                        const SizedBox(height: 16),
                        _MarketplaceHero(onOpen: () => openUiAction(context, title: 'Marketplace', body: 'Marketplace-Listenansicht vorbereitet.', status: 'UI bereit', icon: Icons.info_outline)),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Bereich', value: _tab, values: const ['Alle', 'Produkte', 'Provider', 'Wishlist', 'Bestellungen'], onChanged: (value) => setState(() => _tab = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Buyer-Optionen',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Provider anzeigen', subtitle: 'Guest MarketplaceProviderShow als mobiles Anbieterprofil vorbereiten.', value: _showProviders, onChanged: (value) => setState(() => _showProviders = value)),
                              _SwitchRow(title: 'Wishlist anzeigen', subtitle: 'Guest MarketplaceWishlist mit Favoriten und Merkliste abbilden.', value: _showWishlist, onChanged: (value) => setState(() => _showWishlist = value)),
                              _SwitchRow(title: 'Bestellstatus anzeigen', subtitle: 'Guest MarketplaceOrderStatus mit Zahlung und Rechnung vorbereiten.', value: _showOrders, onChanged: (value) => setState(() => _showOrders = value)),
                              _SwitchRow(title: 'Banktransfer erlauben', subtitle: 'MarketplaceBankTransfer mit Überweisungshinweis abbilden.', value: _showBankTransfer, onChanged: (value) => setState(() => _showBankTransfer = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in items) ...[
                          _MarketCard(item: item, onOpen: () => _toast('${item.title}: Detail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        if (items.isEmpty) const EmptyPanel('Keine Marketplace-Elemente für diesen Bereich gefunden.'),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Marketplace', icon: Icons.storefront_outlined, onPressed: () => openUiAction(context, title: 'Marketplace', body: 'Marketplace-Listenansicht vorbereitet.', status: 'UI bereit', icon: Icons.info_outline)),
                              AirmiusButton(label: 'Order Status', icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Order Status', body: 'Bestellstatus und Banktransfer vorbereitet.', status: 'UI bereit', icon: Icons.info_outline)),
                              AirmiusButton(label: 'Support', icon: Icons.support_agent_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()))),
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

class _MarketplaceHero extends StatelessWidget {
  const _MarketplaceHero({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF14243A), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('GUEST MARKETPLACE'), SizedBox(height: 4), Text('Kaufen, merken, Status verfolgen', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Öffnen', icon: Icons.storefront_outlined, onPressed: onOpen),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Die Guest-Marketplace-Webseiten werden als mobile Buyer-UI abgebildet: Produkte, Provider, Wishlist, Banktransfer und Order Status.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '4', label: 'Flows')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Items')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Order'))]),
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

class _MarketCard extends StatelessWidget {
  const _MarketCard({required this.item, required this.onOpen});

  final _MarketItem item;
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
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.price, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _MarketItem {
  const _MarketItem({required this.title, required this.area, required this.body, required this.status, required this.price, required this.icon, required this.color});

  final String title;
  final String area;
  final String body;
  final String status;
  final String price;
  final IconData icon;
  final Color color;
}
