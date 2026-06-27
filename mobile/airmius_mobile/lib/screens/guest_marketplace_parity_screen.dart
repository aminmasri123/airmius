import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'checkout_screen.dart';
import 'checkout_status_screen.dart';
import 'marketplace_operations_screen.dart';
import 'product_detail_screen.dart';
import 'support_helpdesk_screen.dart';

class GuestMarketplaceParityScreen extends StatefulWidget {
  const GuestMarketplaceParityScreen({super.key});

  @override
  State<GuestMarketplaceParityScreen> createState() => _GuestMarketplaceParityScreenState();
}

class _GuestMarketplaceParityScreenState extends State<GuestMarketplaceParityScreen> {
  final TextEditingController _search = TextEditingController();
  String _category = '';
  String _segment = '';
  String _availability = '';
  String _sort = 'recommended';
  bool _filtersOpen = false;
  final Set<int> _wishlist = {2, 4};
  final List<int> _cart = [1, 4];

  @override
  void initState() {
    super.initState();
    _search.addListener(_refreshSearch);
  }

  @override
  void dispose() {
    _search.removeListener(_refreshSearch);
    _search.dispose();
    super.dispose();
  }

  void _refreshSearch() {
    if (mounted) setState(() {});
  }

  List<_Offer> get _offers {
    final query = _search.text.trim().toLowerCase();
    final items = _demoOffers.where((offer) {
      return (query.isEmpty || '${offer.title} ${offer.body} ${offer.provider}'.toLowerCase().contains(query)) &&
          (_category.isEmpty || offer.category == _category) &&
          (_segment.isEmpty || offer.segment == _segment) &&
          (_availability.isEmpty || offer.availability == _availability);
    }).toList();
    items.sort((a, b) {
      if (_sort == 'price_asc') return a.price.compareTo(b.price);
      if (_sort == 'price_desc') return b.price.compareTo(a.price);
      if (_sort == 'newest') return b.id.compareTo(a.id);
      return b.score.compareTo(a.score);
    });
    return items;
  }

  @override
  Widget build(BuildContext context) {
    final offers = _offers;
    final hero = offers.isNotEmpty ? offers.first : _demoOffers.first;
    return Scaffold(
      backgroundColor: const Color(0xFF070B12),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF07101E),
        icon: const Icon(Icons.explore_outlined),
        label: const Text(''),
        onPressed: _openSupport,
      ),
      body: SafeArea(
        child: _MarketplaceSnapshot(
          offers: offers,
          hero: hero,
          search: _search,
          cartCount: _cart.length,
          resultCount: _search.text.trim().isEmpty && _category.isEmpty && _segment.isEmpty && _availability.isEmpty ? 212 : offers.length,
          onCart: _openCart,
          onLogin: _loginHint,
          onFilters: () => setState(() => _filtersOpen = !_filtersOpen),
          onReset: _reset,
          onOpen: _openProduct,
          onWishlist: _toggleWishlist,
        ),
      ),
    );
  }

  void _changeFilter({String? category, String? segment, String? availability, String? sort}) {
    setState(() {
      if (category != null) _category = category;
      if (segment != null) _segment = segment;
      if (availability != null) _availability = availability;
      if (sort != null) _sort = sort;
    });
  }

  void _reset() {
    setState(() {
      _search.clear();
      _category = '';
      _segment = '';
      _availability = '';
      _sort = 'recommended';
      _filtersOpen = false;
    });
  }

  void _selectQuick(_Quick quick) {
    setState(() {
      _category = quick.category ?? _category;
      _segment = quick.segment ?? '';
      if (quick.query != null) _search.text = quick.query!;
      _filtersOpen = false;
    });
  }

  void _toggleWishlist(_Offer offer) {
    setState(() => _wishlist.contains(offer.id) ? _wishlist.remove(offer.id) : _wishlist.add(offer.id));
  }

  void _addToCart(_Offer offer) {
    setState(() => _cart.add(offer.id));
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${offer.title} liegt im Warenkorb.')));
  }

  void _openProduct(_Offer offer) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ProductDetailScreen(title: offer.title, provider: offer.provider, description: offer.body, category: _label(_categories, offer.category), price: _price(offer.price), icon: offer.icon)));
  void _openCart() => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const MarketplaceOperationsScreen(initialTab: 'Warenkorb')));
  void _openCheckout() => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const CheckoutScreen()));
  void _openOrder() => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const CheckoutStatusScreen(flow: 'Guest Marketplace', status: 'Banktransfer', amount: '129,00 EUR')));
  void _openSupport() => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SupportHelpdeskScreen()));
  void _loginHint() => openUiAction(context, title: 'Anmelden / Registrieren', body: 'Wie in Inertia: Login/Register mit redirect=/marketplace.', status: 'Guest Flow', icon: Icons.login_outlined);
}

class _Header extends StatelessWidget {
  const _Header({required this.cartCount, required this.onCart, required this.onLogin});
  final int cartCount;
  final VoidCallback onCart;
  final VoidCallback onLogin;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: const BoxDecoration(color: AirmiusColors.header, border: Border(bottom: BorderSide(color: AirmiusColors.border))),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1180),
          child: AirmiusPanel(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: Row(children: [
              const AirmiusLogo(size: 42),
              const Spacer(),
              InkWell(
                onTap: onCart,
                borderRadius: BorderRadius.circular(999),
                child: Container(
                  width: 40,
                  height: 40,
                  decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                  child: Stack(children: [
                    const Center(child: Icon(Icons.shopping_cart_outlined, color: Color(0xFF07101E), size: 18)),
                    if (cartCount > 0) Positioned(right: 4, top: 4, child: Container(width: 8, height: 8, decoration: const BoxDecoration(color: AirmiusColors.blue, shape: BoxShape.circle))),
                  ]),
                ),
              ),
              const SizedBox(width: 12),
              InkWell(
                onTap: onLogin,
                borderRadius: BorderRadius.circular(999),
                child: Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(color: AirmiusColors.blue.withValues(alpha: .22), shape: BoxShape.circle, border: Border.all(color: AirmiusColors.border)),
                  child: const Center(child: Text('AM', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                ),
              ),
              const SizedBox(width: 6),
              const Icon(Icons.keyboard_arrow_down, color: AirmiusColors.muted),
            ]),
          ),
        ),
      ),
    );
  }
}

class _MarketplaceSnapshot extends StatelessWidget {
  const _MarketplaceSnapshot({
    required this.offers,
    required this.hero,
    required this.search,
    required this.cartCount,
    required this.resultCount,
    required this.onCart,
    required this.onLogin,
    required this.onFilters,
    required this.onReset,
    required this.onOpen,
    required this.onWishlist,
  });

  final List<_Offer> offers;
  final _Offer hero;
  final TextEditingController search;
  final int cartCount;
  final int resultCount;
  final VoidCallback onCart;
  final VoidCallback onLogin;
  final VoidCallback onFilters;
  final VoidCallback onReset;
  final ValueChanged<_Offer> onOpen;
  final ValueChanged<_Offer> onWishlist;

  @override
  Widget build(BuildContext context) {
    final visible = offers.isEmpty ? _demoOffers : offers;
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(8, 0, 8, 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _SnapshotHeader(cartCount: cartCount, onCart: onCart, onLogin: onLogin),
          const SizedBox(height: 12),
          _SnapshotSearch(search: search, onFilters: onFilters, onSearch: onReset),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              const _SnapshotChip('Kategorie: Alle'),
              const _SnapshotChip('Bereich: Alle Bereiche'),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                child: Text('$resultCount Treffer', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w800)),
              ),
            ],
          ),
          const SizedBox(height: 28),
          SizedBox(
            height: 184,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: 2,
              separatorBuilder: (_, __) => const SizedBox(width: 12),
              itemBuilder: (context, index) {
                final offer = index == 0 ? hero : visible.skip(1).first;
                return SizedBox(width: index == 0 ? 350 : 260, child: _SnapshotHeroCard(offer: offer, onTap: () => onOpen(offer)));
              },
            ),
          ),
          const SizedBox(height: 20),
          SizedBox(
            height: 256,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: visible.take(4).length,
              separatorBuilder: (_, __) => const SizedBox(width: 12),
              itemBuilder: (context, index) {
                final offer = visible[index];
                return SizedBox(width: 144, child: _SnapshotProductCard(offer: offer, onTap: () => onOpen(offer), onWishlist: () => onWishlist(offer)));
              },
            ),
          ),
          const SizedBox(height: 34),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(4)),
            child: const Row(
              children: [
                Icon(Icons.bolt_outlined, color: Color(0xFF07101E), size: 20),
                SizedBox(width: 12),
                Text('Aktuelle Angebote', style: TextStyle(color: Color(0xFF07101E), fontSize: 18, fontWeight: FontWeight.w900)),
              ],
            ),
          ),
          const SizedBox(height: 12),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: visible.length,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, mainAxisSpacing: 12, crossAxisSpacing: 10, childAspectRatio: .72),
            itemBuilder: (context, index) {
              final offer = visible[index];
              return _SnapshotProductCard(offer: offer, onTap: () => onOpen(offer), onWishlist: () => onWishlist(offer));
            },
          ),
        ],
      ),
    );
  }
}

class _SnapshotHeader extends StatelessWidget {
  const _SnapshotHeader({required this.cartCount, required this.onCart, required this.onLogin});

  final int cartCount;
  final VoidCallback onCart;
  final VoidCallback onLogin;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(12, 2, 12, 0),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(6), border: Border.all(color: const Color(0xFF26364D))),
      child: Row(
        children: [
          const AirmiusLogo(size: 42),
          const Spacer(),
          InkWell(
            onTap: onCart,
            borderRadius: BorderRadius.circular(999),
            child: Container(
              width: 40,
              height: 40,
              decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
              child: Stack(
                children: [
                  const Center(child: Icon(Icons.shopping_cart_outlined, color: Color(0xFF07101E), size: 18)),
                  if (cartCount > 0) Positioned(right: 5, top: 5, child: Container(width: 7, height: 7, decoration: const BoxDecoration(color: Color(0xFF5BA7FF), shape: BoxShape.circle))),
                ],
              ),
            ),
          ),
          const SizedBox(width: 12),
          InkWell(
            onTap: onLogin,
            borderRadius: BorderRadius.circular(999),
            child: Container(width: 40, height: 40, decoration: BoxDecoration(color: const Color(0xFF193554), shape: BoxShape.circle, border: Border.all(color: const Color(0xFF26364D))), alignment: Alignment.center, child: const Text('AM', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900))),
          ),
          const SizedBox(width: 6),
          const Icon(Icons.keyboard_arrow_down, color: Color(0xFFAFC0D8)),
        ],
      ),
    );
  }
}

class _SnapshotSearch extends StatelessWidget {
  const _SnapshotSearch({required this.search, required this.onFilters, required this.onSearch});

  final TextEditingController search;
  final VoidCallback onFilters;
  final VoidCallback onSearch;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 12),
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFF26364D))),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: search,
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
              decoration: InputDecoration(
                hintText: 'Was suchst du?',
                hintStyle: const TextStyle(color: Color(0xFFAFC0D8)),
                prefixIcon: const Icon(Icons.search, color: Colors.white),
                filled: true,
                fillColor: const Color(0xFF0B111B),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF26364D))),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF26364D))),
                contentPadding: const EdgeInsets.symmetric(vertical: 14),
              ),
            ),
          ),
          const SizedBox(width: 8),
          _SquareButton(icon: Icons.tune_outlined, onTap: onFilters),
          const SizedBox(width: 8),
          _SquareButton(icon: Icons.search, onTap: onSearch, inverted: true),
        ],
      ),
    );
  }
}

class _SquareButton extends StatelessWidget {
  const _SquareButton({required this.icon, required this.onTap, this.inverted = false});
  final IconData icon;
  final VoidCallback onTap;
  final bool inverted;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(width: 48, height: 48, decoration: BoxDecoration(color: inverted ? Colors.white : const Color(0xFF101722), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFF26364D))), child: Icon(icon, color: inverted ? const Color(0xFF07101E) : const Color(0xFFAFC0D8))),
    );
  }
}

class _SnapshotChip extends StatelessWidget {
  const _SnapshotChip(this.label);
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(margin: const EdgeInsets.only(left: 12), padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), decoration: BoxDecoration(color: const Color(0xFF1A2636), borderRadius: BorderRadius.circular(3)), child: Text(label, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)));
  }
}

class _SnapshotHeroCard extends StatelessWidget {
  const _SnapshotHeroCard({required this.offer, required this.onTap});
  final _Offer offer;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFF26364D)), image: DecorationImage(image: NetworkImage(offer.imageUrl), fit: BoxFit.cover, colorFilter: ColorFilter.mode(Colors.black.withOpacity(.34), BlendMode.darken))),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Container(padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7), decoration: BoxDecoration(color: Colors.black.withOpacity(.32), borderRadius: BorderRadius.circular(999)), child: Text(offer.id == 1 ? 'AIRMIUS MARKETPLACE' : offer.badge.toUpperCase(), style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w900))),
          const SizedBox(height: 10),
          Text(offer.id == 1 ? 'Sport Deals für Training und Team' : offer.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900, height: 1.15)),
          const SizedBox(height: 8),
          Text(offer.badge, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
          const Spacer(),
          Container(padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10), decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(999)), child: const Row(mainAxisSize: MainAxisSize.min, children: [Text('Entdecken', style: TextStyle(color: Color(0xFF07101E), fontWeight: FontWeight.w900)), SizedBox(width: 8), Icon(Icons.arrow_forward, color: Color(0xFF07101E), size: 16)])),
        ]),
      ),
    );
  }
}

class _SnapshotProductCard extends StatelessWidget {
  const _SnapshotProductCard({required this.offer, required this.onTap, required this.onWishlist});
  final _Offer offer;
  final VoidCallback onTap;
  final VoidCallback onWishlist;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFF26364D))),
        clipBehavior: Clip.antiAlias,
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Expanded(
            flex: 6,
            child: Stack(children: [
              Positioned.fill(child: Image.network(offer.imageUrl, fit: BoxFit.cover, errorBuilder: (_, __, ___) => Container(color: offer.color.withOpacity(.55), child: Icon(offer.icon, color: Colors.white, size: 44)))),
              Positioned(left: 10, top: 10, child: Text(offer.badge, style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w900))),
            ]),
          ),
          Expanded(
            flex: 5,
            child: Padding(
              padding: const EdgeInsets.all(10),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(offer.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w800, height: 1.25)),
                const Spacer(),
                Text(_price(offer.price).replaceAll('EUR', '€'), style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w900)),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}

class _Filters extends StatelessWidget {
  const _Filters({required this.search, required this.open, required this.category, required this.segment, required this.availability, required this.sort, required this.count, required this.onToggle, required this.onReset, required this.onChanged});
  final TextEditingController search;
  final bool open;
  final String category;
  final String segment;
  final String availability;
  final String sort;
  final int count;
  final VoidCallback onToggle;
  final VoidCallback onReset;
  final void Function({String? category, String? segment, String? availability, String? sort}) onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AirmiusColors.card.withValues(alpha: .72),
      padding: const EdgeInsets.all(14),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1180),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            AirmiusPanel(
              padding: const EdgeInsets.all(10),
              child: Column(children: [
                Row(children: [
                  Expanded(child: AirmiusTextField(label: 'Was suchst du?', controller: search, icon: Icons.search)),
                  const SizedBox(width: 8),
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.border)),
                    child: IconButton(onPressed: onToggle, icon: const Icon(Icons.tune_outlined, color: AirmiusColors.muted)),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    width: 56,
                    height: 48,
                    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
                    child: IconButton(onPressed: onReset, icon: const Icon(Icons.search, color: Color(0xFF07101E))),
                  ),
                ]),
                LayoutBuilder(builder: (context, constraints) {
                  if (constraints.maxWidth < 780 && !open) return const SizedBox.shrink();
                  return Padding(
                    padding: const EdgeInsets.only(top: 10),
                    child: Wrap(spacing: 8, runSpacing: 8, children: [
                      _Menu(label: 'Kategorie', value: category, items: _categories, onChanged: (value) => onChanged(category: value)),
                      _Menu(label: 'Bereich', value: segment, items: _segments, onChanged: (value) => onChanged(segment: value)),
                      _Menu(label: 'Verfuegbarkeit', value: availability, items: _availability, onChanged: (value) => onChanged(availability: value)),
                      _Menu(label: 'Sortierung', value: sort, items: _sorts, onChanged: (value) => onChanged(sort: value)),
                      const _ReadonlyCountry(),
                    ]),
                  );
                }),
              ]),
            ),
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 8, children: [
              StatusPill('Kategorie: ${_label(_categories, category)}'),
              StatusPill('Bereich: ${_label(_segments, segment)}'),
              StatusPill('Status: ${_label(_availability, availability)}'),
              StatusPill('$count Treffer', color: AirmiusColors.blue),
              StatusPill(AirmiusApiContract.publicMarketplace, color: AirmiusColors.green),
            ]),
          ]),
        ),
      ),
    );
  }
}

class _Menu extends StatelessWidget {
  const _Menu({required this.label, required this.value, required this.items, required this.onChanged});
  final String label;
  final String value;
  final List<_Opt> items;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 185,
      child: DropdownButtonFormField<String>(
        value: value,
        isExpanded: true,
        decoration: InputDecoration(labelText: label, filled: true, fillColor: AirmiusColors.input, border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
        dropdownColor: AirmiusColors.card,
        style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
        items: items.map((item) => DropdownMenuItem(value: item.value, child: Text(item.label, overflow: TextOverflow.ellipsis))).toList(),
        onChanged: (value) => onChanged(value ?? ''),
      ),
    );
  }
}

class _ReadonlyCountry extends StatelessWidget {
  const _ReadonlyCountry();

  @override
  Widget build(BuildContext context) {
    return const SizedBox(width: 185, child: InputDecorator(decoration: InputDecoration(labelText: 'Land', filled: true, fillColor: AirmiusColors.input, border: OutlineInputBorder()), child: Text('Land automatisch', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800))));
  }
}

class _SportRail extends StatelessWidget {
  const _SportRail({required this.onSelect});
  final ValueChanged<_Quick> onSelect;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(children: [
        for (final item in _sports)
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: ActionChip(avatar: Icon(item.icon, color: AirmiusColors.blue, size: 18), label: Text(item.label), onPressed: () => onSelect(item), backgroundColor: AirmiusColors.card, side: const BorderSide(color: AirmiusColors.border), labelStyle: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          ),
      ]),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.offer, required this.sideOffers, required this.onOpen});
  final _Offer offer;
  final List<_Offer> sideOffers;
  final ValueChanged<_Offer> onOpen;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, constraints) {
      final wide = constraints.maxWidth > 820;
      return Flex(direction: wide ? Axis.horizontal : Axis.vertical, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Expanded(
          flex: wide ? 3 : 0,
          child: InkWell(
            onTap: () => onOpen(offer),
            borderRadius: BorderRadius.circular(12),
            child: Container(
              constraints: const BoxConstraints(minHeight: 250),
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AirmiusColors.border),
                image: DecorationImage(image: NetworkImage(offer.imageUrl), fit: BoxFit.cover, colorFilter: ColorFilter.mode(Colors.black.withValues(alpha: .35), BlendMode.darken)),
              ),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                StatusPill('AIRMIUS MARKETPLACE', color: Colors.white),
                const SizedBox(height: 14),
                const Text('Sport Deals für Training und Team', style: TextStyle(color: Colors.white, fontSize: 30, fontWeight: FontWeight.w900, height: 1.05)),
                const SizedBox(height: 10),
                Text(offer.title, style: const TextStyle(color: Colors.white, fontSize: 21, fontWeight: FontWeight.w900)),
                const SizedBox(height: 8),
                Text(offer.body, maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white70, height: 1.4, fontWeight: FontWeight.w700)),
                const SizedBox(height: 16),
                AirmiusButton(label: 'Entdecken', icon: Icons.arrow_forward_outlined, onPressed: () => onOpen(offer)),
              ]),
            ),
          ),
        ),
        if (wide) const SizedBox(width: 14) else const SizedBox(height: 14),
        Expanded(flex: wide ? 1 : 0, child: Column(children: [for (final item in sideOffers) ...[_MiniOffer(offer: item, onTap: () => onOpen(item)), const SizedBox(height: 10)]])),
      ]);
    });
  }
}

class _QuickTiles extends StatelessWidget {
  const _QuickTiles({required this.onSelect});
  final ValueChanged<_Quick> onSelect;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      padding: const EdgeInsets.all(10),
      child: LayoutBuilder(builder: (context, constraints) {
        final columns = constraints.maxWidth > 840 ? 6 : 3;
        return GridView.count(
          crossAxisCount: columns,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 8,
          crossAxisSpacing: 8,
          childAspectRatio: columns == 6 ? 2.35 : 1.65,
          children: [for (final item in _quick) _QuickCard(item: item, onTap: () => onSelect(item))],
        );
      }),
    );
  }
}

class _QuickCard extends StatelessWidget {
  const _QuickCard({required this.item, required this.onTap});
  final _Quick item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(8)),
        child: Row(children: [
          Icon(item.icon, color: AirmiusColors.blue),
          const SizedBox(width: 8),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
            Text(item.label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            Text(item.hint, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w700)),
          ])),
        ]),
      ),
    );
  }
}

class _TrustGrid extends StatelessWidget {
  const _TrustGrid();

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, constraints) {
      final columns = constraints.maxWidth > 760 ? 4 : 2;
      return GridView.count(
        crossAxisCount: columns,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: columns == 4 ? 2.2 : 1.45,
        children: [for (final item in _trust) _InfoTile(item: item)],
      );
    });
  }
}

class _ScreenshotProductRow extends StatelessWidget {
  const _ScreenshotProductRow({required this.offers, required this.wishlist, required this.onOpen, required this.onWishlist});
  final List<_Offer> offers;
  final Set<int> wishlist;
  final ValueChanged<_Offer> onOpen;
  final ValueChanged<_Offer> onWishlist;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 256,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: offers.length,
        separatorBuilder: (_, __) => const SizedBox(width: 12),
        itemBuilder: (context, index) {
          final offer = offers[index];
          return SizedBox(
            width: 144,
            child: _ProductCard(
              offer: offer,
              compact: true,
              wishlisted: wishlist.contains(offer.id),
              onOpen: () => onOpen(offer),
              onWishlist: () => onWishlist(offer),
              onCart: () {},
            ),
          );
        },
      ),
    );
  }
}

class _OfferStrip extends StatelessWidget {
  const _OfferStrip({required this.title, required this.offers, required this.wishlist, required this.onOpen, required this.onWishlist});
  final String title;
  final List<_Offer> offers;
  final Set<int> wishlist;
  final ValueChanged<_Offer> onOpen;
  final ValueChanged<_Offer> onWishlist;

  @override
  Widget build(BuildContext context) {
    if (offers.isEmpty) return const SizedBox.shrink();
    if (title == 'Aktuelle Angebote') {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(4)),
            child: const Row(children: [
              Icon(Icons.bolt_outlined, color: Color(0xFF07101E)),
              SizedBox(width: 12),
              Text('Aktuelle Angebote', style: TextStyle(color: Color(0xFF07101E), fontSize: 18, fontWeight: FontWeight.w900)),
            ]),
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 278,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: offers.length,
              separatorBuilder: (_, __) => const SizedBox(width: 12),
              itemBuilder: (context, index) {
                final offer = offers[index];
                return SizedBox(width: 260, child: _ProductCard(offer: offer, compact: true, wishlisted: wishlist.contains(offer.id), onOpen: () => onOpen(offer), onWishlist: () => onWishlist(offer), onCart: () {}));
              },
            ),
          ),
        ],
      );
    }
    return AirmiusPanel(
      title: title,
      child: SizedBox(
        height: 278,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: offers.length,
          separatorBuilder: (_, __) => const SizedBox(width: 12),
          itemBuilder: (context, index) {
            final offer = offers[index];
            return SizedBox(width: 190, child: _ProductCard(offer: offer, compact: true, wishlisted: wishlist.contains(offer.id), onOpen: () => onOpen(offer), onWishlist: () => onWishlist(offer), onCart: () {}));
          },
        ),
      ),
    );
  }
}

class _StoresAndEssentials extends StatelessWidget {
  const _StoresAndEssentials({required this.offers, required this.onOpen});
  final List<_Offer> offers;
  final ValueChanged<_Offer> onOpen;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, constraints) {
      final wide = constraints.maxWidth > 840;
      return Flex(direction: wide ? Axis.horizontal : Axis.vertical, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Expanded(flex: wide ? 2 : 0, child: AirmiusPanel(title: 'Essentials für Vereine', child: Wrap(spacing: 10, runSpacing: 10, children: [for (final offer in offers) SizedBox(width: 165, child: _MiniOffer(offer: offer, onTap: () => onOpen(offer)))]))),
        if (wide) const SizedBox(width: 14) else const SizedBox(height: 14),
        Expanded(child: AirmiusPanel(title: 'Official Stores', child: Wrap(spacing: 10, runSpacing: 10, children: [for (final store in _stores) SizedBox(width: 150, child: _InfoTile(item: store, compact: true))]))),
      ]);
    });
  }
}

class _GroupedOffers extends StatelessWidget {
  const _GroupedOffers({required this.offers, required this.wishlist, required this.onOpen, required this.onWishlist, required this.onCart});
  final List<_Offer> offers;
  final Set<int> wishlist;
  final ValueChanged<_Offer> onOpen;
  final ValueChanged<_Offer> onWishlist;
  final ValueChanged<_Offer> onCart;

  @override
  Widget build(BuildContext context) {
    final groups = <String, List<_Offer>>{};
    for (final offer in offers) {
      groups.putIfAbsent(offer.segment, () => []).add(offer);
    }
    return AirmiusPanel(
      title: 'Alle Angebote',
      child: Column(children: [
        for (final entry in groups.entries) ...[
          Row(children: [Icon(_segmentIcon(entry.key), color: AirmiusColors.blue), const SizedBox(width: 8), Expanded(child: Text(_label(_segments, entry.key), style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900))), StatusPill('${entry.value.length} Angebote')]),
          const SizedBox(height: 10),
          LayoutBuilder(builder: (context, constraints) {
            final columns = constraints.maxWidth > 920 ? 4 : constraints.maxWidth > 620 ? 3 : 2;
            return GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: entry.value.length,
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: columns, mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: .64),
              itemBuilder: (context, index) {
                final offer = entry.value[index];
                return _ProductCard(offer: offer, wishlisted: wishlist.contains(offer.id), onOpen: () => onOpen(offer), onWishlist: () => onWishlist(offer), onCart: () => onCart(offer));
              },
            );
          }),
          const SizedBox(height: 16),
        ],
        if (offers.isEmpty) const EmptyPanel('Keine Treffer. Filter zurücksetzen oder eine andere Kategorie wählen.'),
      ]),
    );
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({required this.offer, required this.wishlisted, required this.onOpen, required this.onWishlist, required this.onCart, this.compact = false});
  final _Offer offer;
  final bool wishlisted;
  final VoidCallback onOpen;
  final VoidCallback onWishlist;
  final VoidCallback onCart;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onOpen,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        decoration: BoxDecoration(color: AirmiusColors.card, borderRadius: BorderRadius.circular(8), border: Border.all(color: AirmiusColors.border)),
        clipBehavior: Clip.antiAlias,
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Container(height: compact ? 104 : 128, color: AirmiusColors.input, child: Stack(children: [
            Positioned.fill(
              child: Image.network(
                offer.imageUrl,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(colors: [offer.color.withValues(alpha: .62), const Color(0xFF0B111B)])), child: Center(child: Icon(offer.icon, size: compact ? 48 : 58, color: Colors.white))),
              ),
            ),
            Positioned.fill(child: DecoratedBox(decoration: BoxDecoration(gradient: LinearGradient(colors: [Colors.black.withValues(alpha: .06), Colors.black.withValues(alpha: .2)], begin: Alignment.topCenter, end: Alignment.bottomCenter)))),
            Positioned(left: 8, top: 8, child: StatusPill(offer.badge, color: Colors.white)),
            Positioned(right: 4, top: 4, child: IconButton(onPressed: onWishlist, icon: Icon(wishlisted ? Icons.favorite : Icons.favorite_border, color: wishlisted ? AirmiusColors.red : Colors.white))),
          ])),
          Expanded(child: Padding(
            padding: const EdgeInsets.all(10),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(_label(_categories, offer.category), style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w900)),
              const SizedBox(height: 4),
              Text(offer.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, height: 1.2)),
              if (!compact) ...[const SizedBox(height: 6), Text(offer.body, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.25))],
              const Spacer(),
              Text(_price(offer.price), style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
              Text('${_net(offer.price)} netto · MwSt. ausgewiesen', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 11)),
              const SizedBox(height: 6),
              Row(children: [CircleAvatar(radius: 11, backgroundColor: AirmiusColors.blue.withValues(alpha: .2), child: Text(offer.initials, style: const TextStyle(color: AirmiusColors.blue, fontSize: 9, fontWeight: FontWeight.w900))), const SizedBox(width: 6), Expanded(child: Text(offer.provider, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w700))), if (offer.verified) const Icon(Icons.verified, color: AirmiusColors.green, size: 16)]),
              const SizedBox(height: 8),
              Row(children: [Expanded(child: OutlinedButton(onPressed: onOpen, child: const Text('Details'))), if (!compact) ...[const SizedBox(width: 6), IconButton(onPressed: onCart, icon: const Icon(Icons.add_shopping_cart_outlined, color: AirmiusColors.blue))]]),
            ]),
          )),
        ]),
      ),
    );
  }
}

class _MiniOffer extends StatelessWidget {
  const _MiniOffer({required this.offer, required this.onTap});
  final _Offer offer;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(onTap: onTap, borderRadius: BorderRadius.circular(8), child: Container(padding: const EdgeInsets.all(10), decoration: BoxDecoration(color: AirmiusColors.card, borderRadius: BorderRadius.circular(8), border: Border.all(color: AirmiusColors.border)), child: Row(children: [Container(width: 46, height: 46, decoration: BoxDecoration(color: offer.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(8)), child: Icon(offer.icon, color: offer.color)), const SizedBox(width: 10), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(offer.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(_price(offer.price), style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))]))])));
  }
}

class _InfoTile extends StatelessWidget {
  const _InfoTile({required this.item, this.compact = false});
  final _Info item;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.card, borderRadius: BorderRadius.circular(8), border: Border.all(color: AirmiusColors.border)),
      child: Row(children: [
        Icon(item.icon, color: item.color),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
          Text(item.label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          if (!compact) Text(item.body, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.25)),
        ])),
      ]),
    );
  }
}

class _ProviderLocations extends StatelessWidget {
  const _ProviderLocations({required this.onSupport});
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(title: 'Anbieter & Standorte', child: Column(children: [
      for (final location in _locations)
        ListTile(leading: Icon(location.icon, color: location.color), title: Text(location.label, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: Text(location.body, style: const TextStyle(color: AirmiusColors.muted)), trailing: StatusPill(location.status, color: location.color), onTap: onSupport),
    ]));
  }
}

class _Dependencies extends StatelessWidget {
  const _Dependencies({required this.cartCount, required this.onCheckout, required this.onOrder, required this.onSupport});
  final int cartCount;
  final VoidCallback onCheckout;
  final VoidCallback onOrder;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(title: 'Funktionen & Abhaengigkeiten wie Inertia/Laravel', child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('Cart $cartCount', color: AirmiusColors.blue), StatusPill('Wishlist', color: AirmiusColors.red), StatusPill('Guest Checkout', color: AirmiusColors.green), StatusPill('Banktransfer', color: AirmiusColors.amber), StatusPill('Provider Profile', color: AirmiusColors.blue), StatusPill('Returns', color: AirmiusColors.amber)]),
      const SizedBox(height: 12),
      Wrap(spacing: 10, runSpacing: 10, children: [AirmiusButton(label: 'Checkout', icon: Icons.payment_outlined, onPressed: onCheckout), AirmiusButton(label: 'Order Status', icon: Icons.receipt_long_outlined, secondary: true, onPressed: onOrder), AirmiusButton(label: 'Support', icon: Icons.support_agent_outlined, secondary: true, onPressed: onSupport)]),
      const SizedBox(height: 12),
      const Text('Routen: /marketplace, /marketplace/products/{id}, /marketplace/products/{id}/checkout, /marketplace/providers/{type}/{id}, /marketplace/orders/{order}/{token}/returns.', style: TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700)),
    ]));
  }
}

class _AdBanner extends StatelessWidget {
  const _AdBanner();
  @override
  Widget build(BuildContext context) => Container(padding: const EdgeInsets.all(16), decoration: BoxDecoration(color: AirmiusColors.card, borderRadius: BorderRadius.circular(8), border: Border.all(color: AirmiusColors.border)), child: const Row(children: [Icon(Icons.campaign_outlined, color: AirmiusColors.amber), SizedBox(width: 12), Expanded(child: Text('Marketplace Karte: Ads, Hero-Banner, Sale-Kachel und Kampagnenflaechen sind als native Bereiche vorgesehen.', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800)))]));
}

class _Offer {
  const _Offer(this.id, this.title, this.body, this.category, this.segment, this.availability, this.badge, this.provider, this.initials, this.price, this.icon, this.color, this.score, this.imageUrl, [this.verified = false]);
  final int id;
  final String title;
  final String body;
  final String category;
  final String segment;
  final String availability;
  final String badge;
  final String provider;
  final String initials;
  final int price;
  final IconData icon;
  final Color color;
  final int score;
  final String imageUrl;
  final bool verified;
}

class _Quick {
  const _Quick(this.label, this.hint, this.icon, {this.category, this.segment, this.query});
  final String label;
  final String hint;
  final IconData icon;
  final String? category;
  final String? segment;
  final String? query;
}

class _Opt {
  const _Opt(this.value, this.label);
  final String value;
  final String label;
}

class _Info {
  const _Info(this.label, this.body, this.icon, this.color, [this.status = '']);
  final String label;
  final String body;
  final IconData icon;
  final Color color;
  final String status;
}

String _label(List<_Opt> options, String value) => options.firstWhere((item) => item.value == value, orElse: () => options.first).label;
String _price(int cents) => '${(cents / 100).toStringAsFixed(2).replaceAll('.', ',')} EUR';
String _net(int cents) => '${((cents / 100) / 1.19).toStringAsFixed(2).replaceAll('.', ',')} EUR';

IconData _segmentIcon(String segment) => switch (segment) {
  'shoes' => Icons.directions_run_outlined,
  'apparel' => Icons.checkroom_outlined,
  'equipment' => Icons.fitness_center_outlined,
  'recovery' => Icons.favorite_border_outlined,
  'analysis' => Icons.query_stats_outlined,
  'nutrition' => Icons.local_dining_outlined,
  'plans' => Icons.school_outlined,
  'camps' => Icons.event_available_outlined,
  'team' => Icons.groups_outlined,
  _ => Icons.storefront_outlined,
};

const _categories = [_Opt('', 'Alle'), _Opt('product', 'Produkte'), _Opt('outfit_subscription', 'Outfit-Abos'), _Opt('course', 'Kurse'), _Opt('camp', 'Camps'), _Opt('service', 'Services')];
const _segments = [_Opt('', 'Alle Bereiche'), _Opt('shoes', 'Schuhe'), _Opt('apparel', 'Bekleidung'), _Opt('equipment', 'Equipment'), _Opt('recovery', 'Recovery'), _Opt('analysis', 'Analyse'), _Opt('nutrition', 'Ernährung'), _Opt('plans', 'Plaene & Kurse'), _Opt('camps', 'Camps'), _Opt('team', 'Team & Verein')];
const _availability = [_Opt('', 'Alle Verfuegbarkeiten'), _Opt('available', 'Sofort verfuegbar'), _Opt('shippable', 'Versandartikel'), _Opt('digital', 'Digital / Termin')];
const _sorts = [_Opt('recommended', 'Empfohlen'), _Opt('newest', 'Neueste'), _Opt('price_asc', 'Preis aufsteigend'), _Opt('price_desc', 'Preis absteigend')];
const _quick = [_Quick('Aktuell', 'Heute beliebt', Icons.bolt_outlined, category: '', query: ''), _Quick('Produkte', 'Equipment', Icons.shopping_bag_outlined, category: 'product'), _Quick('Kurse', 'Online & vor Ort', Icons.video_library_outlined, category: 'course', segment: 'plans'), _Quick('Camps', 'Events & Training', Icons.event_available_outlined, category: 'camp', segment: 'camps'), _Quick('Services', 'Analyse & Beratung', Icons.handshake_outlined, category: 'service', segment: 'analysis'), _Quick('Outfit-Abo', 'Sportkleidung', Icons.checkroom_outlined, category: 'outfit_subscription', segment: 'apparel')];
const _sports = [_Quick('Running', 'Lauf', Icons.directions_run_outlined, query: 'lauf'), _Quick('Fussball', 'Team', Icons.sports_soccer_outlined, query: 'fussball'), _Quick('Fitness', 'Gym', Icons.fitness_center_outlined, query: 'fitness'), _Quick('Teamsport', 'Verein', Icons.groups_outlined, query: 'team'), _Quick('Recovery', 'Regeneration', Icons.favorite_border_outlined, segment: 'recovery'), _Quick('Camps', 'Training', Icons.event_available_outlined, category: 'camp', segment: 'camps'), _Quick('Kurse', 'Lernen', Icons.video_library_outlined, category: 'course', segment: 'plans'), _Quick('Services', 'Analyse', Icons.handshake_outlined, category: 'service', segment: 'analysis')];
const _trust = [_Info('Gastkauf möglich', 'Direkt bestellen, Konto optional.', Icons.person_add_alt_1_outlined, AirmiusColors.blue), _Info('Preis transparent', 'Brutto, netto, Steuer und Versand.', Icons.receipt_long_outlined, AirmiusColors.green), _Info('Anbieter sichtbar', 'Verein, Trainer oder Shop klar erkennbar.', Icons.store_mall_directory_outlined, AirmiusColors.amber), _Info('Bestellstatus', 'Updates und Belege per E-Mail.', Icons.mark_email_read_outlined, AirmiusColors.red)];
const _stores = [_Info('Airmius Teamsport', 'Teamwear', Icons.checkroom_outlined, AirmiusColors.blue), _Info('RunLab', 'Running', Icons.directions_run_outlined, AirmiusColors.green), _Info('Club Gear', 'Vereine', Icons.shield_outlined, AirmiusColors.amber), _Info('Recovery Pro', 'Recovery', Icons.favorite_border_outlined, AirmiusColors.red), _Info('Coach Campus', 'Kurse', Icons.school_outlined, AirmiusColors.blue), _Info('FitMarket', 'Fitness', Icons.fitness_center_outlined, AirmiusColors.green)];
const _locations = [_Info('Airmius Teamsport Hub', 'Abholstation, Retouren und Support in DE.', Icons.location_on_outlined, AirmiusColors.green, 'Pickup'), _Info('Coach Campus Online', 'Digitale Kurse, Plaene und Zertifikate.', Icons.public_outlined, AirmiusColors.blue, 'Digital'), _Info('Club Gear Partner', 'Lokale Vereinsausstattung mit Anbieterprofil.', Icons.storefront_outlined, AirmiusColors.amber, 'Partner')];
const _demoOffers = [
  _Offer(1, 'Leichtathletik Pro Analyse Paket Track Elite', 'Umfangreiche Analyse mit Auswertung, Feedbackgespraech und priorisiertem Trainingsplan.', 'service', 'analysis', 'digital', 'Service', 'Airmius Marketplace', 'AM', 24650, Icons.query_stats_outlined, AirmiusColors.red, 98, 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1200&q=80', true),
  _Offer(2, 'Boxen Pro Analyse Paket Strike Junior', 'Umfangreiche Analyse mit Auswertung, Feedbackgespraech und priorisiertem Trainingsplan.', 'service', 'analysis', 'digital', 'Service', 'Airmius Marketplace', 'AM', 11930, Icons.sports_mma_outlined, AirmiusColors.blue, 94, 'https://images.unsplash.com/photo-1549719386-74dfcbf7dbed?auto=format&fit=crop&w=1200&q=80', true),
  _Offer(3, 'Handball Pro Analyse Paket Arena Elite', 'Umfangreiche Analyse mit Auswertung, Feedbackgespraech und priorisiertem Trainingsplan.', 'service', 'analysis', 'digital', 'Service', 'Airmius Marketplace', 'AM', 24650, Icons.sports_handball_outlined, AirmiusColors.green, 91, 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1200&q=80', true),
  _Offer(4, 'Volleyball Pro Analyse Paket Block Pro', 'Umfangreiche Analyse mit Auswertung, Feedbackgespraech und priorisiertem Trainingsplan.', 'service', 'analysis', 'digital', 'Service', 'Airmius Marketplace', 'AM', 19880, Icons.sports_volleyball_outlined, AirmiusColors.amber, 90, 'https://images.unsplash.com/photo-1612872087720-bb876e2e67d1?auto=format&fit=crop&w=1200&q=80'),
  _Offer(5, 'Running Performance Schuh Runner Pro', 'Leichter Trainingsschuh mit stabiler Daempfung für Technik, Tempo und Grundlageneinheiten.', 'product', 'shoes', 'available', 'Deal', 'RunLab', 'RL', 11240, Icons.directions_run_outlined, AirmiusColors.red, 89, 'https://images.unsplash.com/photo-1552674605-db6ffd4facb5?auto=format&fit=crop&w=1200&q=80', true),
  _Offer(6, 'Fussball Feriencamp Pitch Elite', 'Mehrtaegiges Camp mit Technik, Koordination, Spielformen und Team-Challenges.', 'camp', 'camps', 'digital', 'Camp', 'MatchDay', 'MD', 19990, Icons.sports_soccer_outlined, AirmiusColors.green, 86, 'https://images.unsplash.com/photo-1526232761682-d26e03ac148e?auto=format&fit=crop&w=1200&q=80'),
  _Offer(7, 'Basketball Skills Clinic Court Team', 'Intensiver Tagesworkshop für Grundlagen, Detailtechnik und spielnahe Anwendung.', 'camp', 'camps', 'digital', 'Camp', 'Coach Campus', 'CC', 10670, Icons.sports_basketball_outlined, AirmiusColors.amber, 84, 'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=1200&q=80', true),
  _Offer(8, 'Yoga Mobility für Sportler Flow Club', 'Digitaler Kurs mit Uebungsreihen, Korrekturpunkten und praktischen Wochenaufgaben.', 'course', 'plans', 'digital', 'Online-Kurs', 'Coach Campus', 'CC', 5900, Icons.self_improvement_outlined, AirmiusColors.green, 82, 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=1200&q=80'),
  _Offer(9, 'Fitness Recovery Bundle Power Pro', 'Regenerationspaket für Muskelpflege, Mobility und aktive Erholung nach dem Training.', 'product', 'recovery', 'available', 'Neu', 'Recovery Pro', 'RP', 4360, Icons.fitness_center_outlined, AirmiusColors.blue, 80, 'https://images.unsplash.com/photo-1599058917765-a780eda07a3e?auto=format&fit=crop&w=1200&q=80', true),
  _Offer(10, 'Tennis Trainer Fortbildung Ace Digital', 'Fortbildung für Trainer mit Methodik, Belastungssteuerung und praktischen Uebungsformaten.', 'course', 'plans', 'digital', 'Kurs', 'Coach Campus', 'CC', 8390, Icons.sports_tennis_outlined, AirmiusColors.green, 78, 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=1200&q=80', true),
];
