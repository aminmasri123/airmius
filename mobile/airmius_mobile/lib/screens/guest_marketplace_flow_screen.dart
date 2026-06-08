import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'support_helpdesk_screen.dart';

class GuestMarketplaceFlowScreen extends StatefulWidget {
  const GuestMarketplaceFlowScreen({super.key});

  @override
  State<GuestMarketplaceFlowScreen> createState() => _GuestMarketplaceFlowScreenState();
}

class _GuestMarketplaceFlowScreenState extends State<GuestMarketplaceFlowScreen> {
  String _filter = 'Alle';
  bool _showProducts = true;
  bool _showCheckout = true;
  bool _showProvider = true;

  final List<_MarketFlow> _flows = const [
    _MarketFlow(
      title: 'Marketplace Start',
      area: 'Shop',
      status: 'Guest',
      body: 'Oeffentliche Produktuebersicht mit Kategorien, Suche, Badges, Preisen und schnellen Produktkarten.',
      icon: Icons.storefront_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _MarketFlow(
      title: 'Produkt ansehen',
      area: 'Shop',
      status: 'Detail',
      body: 'Mobile Produktdetailseite mit Galerie, Anbieter, Varianten, Beschreibung und Kaufaktion.',
      icon: Icons.inventory_2_outlined,
      color: Color(0xFF2EE59D),
    ),
    _MarketFlow(
      title: 'Anbieterprofil',
      area: 'Provider',
      status: 'Partner',
      body: 'Profil fuer Marketplace-Anbieter mit Sortiment, Standort, Bewertung, Kontakt und Vertrauen.',
      icon: Icons.verified_outlined,
      color: Color(0xFFB084FF),
    ),
    _MarketFlow(
      title: 'Wishlist',
      area: 'Shop',
      status: 'Merken',
      body: 'Merkliste fuer Gaeste und User mit gespeicherten Produkten und spaeterer Account-Verknuepfung.',
      icon: Icons.favorite_border_outlined,
      color: Color(0xFFFF6B6B),
    ),
    _MarketFlow(
      title: 'Warenkorb',
      area: 'Checkout',
      status: 'Cart',
      body: 'Warenkorb mit Mengen, Zwischensumme, Versandhinweisen, Gutscheinen und sicherer Weiterleitung.',
      icon: Icons.shopping_cart_outlined,
      color: Color(0xFFF8B84E),
    ),
    _MarketFlow(
      title: 'Bankueberweisung',
      area: 'Checkout',
      status: 'Transfer',
      body: 'Gastfreundliche Zahlungsseite fuer Banktransfer, Referenznummer und Zahlungsstatus.',
      icon: Icons.account_balance_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _MarketFlow(
      title: 'Bestellstatus',
      area: 'Checkout',
      status: 'Order',
      body: 'Statusseite fuer Bestellung, Zahlung, Versand, Abholung, Rechnung und Supportkontakt.',
      icon: Icons.receipt_long_outlined,
      color: Color(0xFF2EE59D),
    ),
  ];

  List<_MarketFlow> get _visibleFlows {
    if (_filter == 'Alle') return _flows;
    return _flows.where((flow) => flow.area == _filter).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF070B12),
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              sliver: SliverToBoxAdapter(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _TopBar(onSupport: () => _openSupport(context)),
                    const SizedBox(height: 18),
                    const _IntroPanel(),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(child: _MetricCard(value: '7', label: 'Flows')),
                        SizedBox(width: 10),
                        Expanded(child: _MetricCard(value: '3', label: 'Shop')),
                        SizedBox(width: 10),
                        Expanded(child: _MetricCard(value: '3', label: 'Checkout')),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _FilterTabs(
                      value: _filter,
                      values: const ['Alle', 'Shop', 'Provider', 'Checkout'],
                      onChanged: (value) => setState(() => _filter = value),
                    ),
                    const SizedBox(height: 14),
                    _VisibilityPanel(
                      showProducts: _showProducts,
                      showCheckout: _showCheckout,
                      showProvider: _showProvider,
                      onProducts: (value) => setState(() => _showProducts = value),
                      onCheckout: (value) => setState(() => _showCheckout = value),
                      onProvider: (value) => setState(() => _showProvider = value),
                    ),
                    const SizedBox(height: 14),
                    for (final flow in _visibleFlows.where(_isVisible)) ...[
                      _MarketFlowCard(flow: flow),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      onCart: () => openUiAction(
                        context,
                        title: 'Warenkorb',
                        message: 'Die mobile Warenkorb-UI ist vorbereitet; Produktdaten kommen spaeter ueber die Laravel-API.',
                      ),
                      onOrder: () => openUiAction(
                        context,
                        title: 'Bestellstatus',
                        message: 'Hier wird spaeter Order-Status, Zahlung und Versand aus der API geladen.',
                      ),
                      onSupport: () => _openSupport(context),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  bool _isVisible(_MarketFlow flow) {
    if (flow.area == 'Checkout') return _showCheckout;
    if (flow.area == 'Provider') return _showProvider;
    return _showProducts;
  }

  void _openSupport(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _MarketFlow {
  const _MarketFlow({
    required this.title,
    required this.area,
    required this.status,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String title;
  final String area;
  final String status;
  final String body;
  final IconData icon;
  final Color color;
}

class _TopBar extends StatelessWidget {
  const _TopBar({required this.onSupport});

  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const AirmiusLogo(markOnly: true, size: 34),
        const SizedBox(width: 10),
        const Expanded(child: Text('Airmius', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900))),
        IconButton(onPressed: onSupport, icon: const Icon(Icons.support_agent_outlined, color: Color(0xFFAFC0D8))),
      ],
    );
  }
}

class _IntroPanel extends StatelessWidget {
  const _IntroPanel();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFF26364D)),
        gradient: const LinearGradient(colors: [Color(0xFF121A27), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          Text('GUEST MARKETPLACE', style: TextStyle(color: Color(0xFF5BA7FF), fontSize: 12, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text('Shop, Wishlist & Checkout', style: TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text(
            'Native Mobile-UI fuer Gast-Marketplace, Produkte, Anbieter, Wishlist, Warenkorb, Banktransfer und Bestellstatus.',
            style: TextStyle(color: Color(0xFFAFC0D8), height: 1.45, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(18), border: Border.all(color: const Color(0xFF26364D))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(value, style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(color: Color(0xFFAFC0D8), fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

class _FilterTabs extends StatelessWidget {
  const _FilterTabs({required this.value, required this.values, required this.onChanged});

  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: values.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final item = values[index];
          final active = item == value;
          return ChoiceChip(
            label: Text(item),
            selected: active,
            onSelected: (_) => onChanged(item),
            labelStyle: TextStyle(color: active ? Colors.white : const Color(0xFFAFC0D8), fontWeight: FontWeight.w900),
            selectedColor: const Color(0xFF173D68),
            backgroundColor: const Color(0xFF101722),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999), side: const BorderSide(color: Color(0xFF26364D))),
          );
        },
      ),
    );
  }
}

class _VisibilityPanel extends StatelessWidget {
  const _VisibilityPanel({
    required this.showProducts,
    required this.showCheckout,
    required this.showProvider,
    required this.onProducts,
    required this.onCheckout,
    required this.onProvider,
  });

  final bool showProducts;
  final bool showCheckout;
  final bool showProvider;
  final ValueChanged<bool> onProducts;
  final ValueChanged<bool> onCheckout;
  final ValueChanged<bool> onProvider;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Mobile Kaufstrecke',
      child: Column(
        children: [
          _SwitchRow(label: 'Produkte anzeigen', value: showProducts, onChanged: onProducts),
          _SwitchRow(label: 'Checkout anzeigen', value: showCheckout, onChanged: onCheckout),
          _SwitchRow(label: 'Anbieter anzeigen', value: showProvider, onChanged: onProvider),
        ],
      ),
    );
  }
}

class _MarketFlowCard extends StatelessWidget {
  const _MarketFlowCard({required this.flow});

  final _MarketFlow flow;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(24), border: Border.all(color: const Color(0xFF26364D))),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(color: flow.color.withOpacity(.14), borderRadius: BorderRadius.circular(16), border: Border.all(color: flow.color.withOpacity(.45))),
            child: Icon(flow.icon, color: flow.color, size: 28),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(flow.title, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900))),
                    _Pill(label: flow.status, color: flow.color),
                  ],
                ),
                const SizedBox(height: 8),
                Text(flow.body, style: const TextStyle(color: Color(0xFFDDE7F5), height: 1.45, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({required this.onCart, required this.onOrder, required this.onSupport});

  final VoidCallback onCart;
  final VoidCallback onOrder;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Schnellaktionen',
      child: Column(
        children: [
          _ActionButton(icon: Icons.shopping_cart_outlined, label: 'Warenkorb Vorschau', onTap: onCart),
          const SizedBox(height: 10),
          _ActionButton(icon: Icons.receipt_long_outlined, label: 'Bestellstatus pruefen', onTap: onOrder),
          const SizedBox(height: 10),
          _ActionButton(icon: Icons.support_agent_outlined, label: 'Support kontaktieren', onTap: onSupport),
        ],
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: const Color(0xFF0D131D), borderRadius: BorderRadius.circular(22), border: Border.all(color: const Color(0xFF26364D))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.label, required this.value, required this.onChanged});

  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      value: value,
      onChanged: onChanged,
      dense: true,
      contentPadding: EdgeInsets.zero,
      activeThumbColor: const Color(0xFF5BA7FF),
      title: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: const Color(0xFF111A27), borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFF26364D))),
        child: Row(
          children: [
            Icon(icon, color: AirmiusColors.blue),
            const SizedBox(width: 12),
            Expanded(child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900))),
            const Icon(Icons.chevron_right, color: Color(0xFFAFC0D8)),
          ],
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(color: color.withOpacity(.12), borderRadius: BorderRadius.circular(999), border: Border.all(color: color.withOpacity(.55))),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
    );
  }
}
