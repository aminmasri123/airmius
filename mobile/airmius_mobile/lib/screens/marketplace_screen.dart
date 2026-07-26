import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class MarketplaceScreen extends StatefulWidget {
  const MarketplaceScreen({super.key, this.initialQuery = ''});

  final String initialQuery;

  @override
  State<MarketplaceScreen> createState() => _MarketplaceScreenState();
}

class _MarketplaceScreenState extends State<MarketplaceScreen> {
  String _section = 'products';
  String _query = '';
  Future<_MarketplaceBundle>? _bundleFuture;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _query = widget.initialQuery;
  }

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _bundleFuture ??= _load();
  }

  Future<_MarketplaceBundle> _load() async {
    final responses = await Future.wait([
      _client.commerceProducts(),
      _client.commerceWishlist(),
      _client.commerceOrders(),
      _client.commerceCart(),
    ]);
    final checkout = _marketMap(responses[3]['data']);
    return _MarketplaceBundle(
      products: _marketMaps(responses[0]['data']),
      wishlist: _marketMaps(responses[1]['data']),
      orders: _marketMaps(responses[2]['data']),
      cart: _marketMap(checkout['cart']),
      checkout: checkout,
    );
  }

  void _reload() {
    setState(() {
      _bundleFuture = _load();
    });
  }

  Future<void> _run(
    Future<void> Function() action, {
    String? successMessage,
  }) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      if (successMessage != null) _toast(successMessage);
      _reload();
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
    } catch (error) {
      if (mounted) {
        _toast(
          error is AirmiusApiException
              ? error.userMessage
              : AirmiusScope.of(context).t('common.errorDetails'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            Theme.of(context).colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('market.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('market.reload'),
            onPressed: _busy ? null : _reload,
            icon: Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('market.title'),
        subtitle: t('market.subtitle'),
        child: FutureBuilder<_MarketplaceBundle>(
          future: _bundleFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const _MarketLoading();
            }
            if (snapshot.hasError) {
              return _MarketError(error: snapshot.error, onRetry: _reload);
            }
            return _buildContent(snapshot.data ?? const _MarketplaceBundle());
          },
        ),
      ),
    );
  }

  Widget _buildContent(_MarketplaceBundle bundle) {
    final t = AirmiusScope.of(context).t;
    final normalized = _query.trim().toLowerCase();
    final source = _section == 'wishlist' ? bundle.wishlist : bundle.products;
    final products = source.where((product) {
      if (normalized.isEmpty) return true;
      return [
        product['title'],
        product['description'],
        product['category'],
        _marketMap(product['provider_profile'])['display_name'],
      ].any((value) => _marketText(value).toLowerCase().contains(normalized));
    }).toList();
    final cartCount = _marketMaps(
      bundle.cart['items'],
    ).fold<int>(0, (sum, item) => sum + _marketInt(item['quantity']));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('market.overview')),
              const SizedBox(height: 8),
              Text(
                t('market.overviewHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  _MiniMetric(
                    value: '${bundle.products.length}',
                    label: t('market.products'),
                  ),
                  _MiniMetric(
                    value: '$cartCount',
                    label: t('market.cart'),
                    color: Theme.of(context).colorScheme.secondary,
                  ),
                  _MiniMetric(
                    value: '${bundle.orders.length}',
                    label: t('market.orders'),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        _MarketSectionPicker(
          value: _section,
          cartCount: cartCount,
          onChanged: (value) => setState(() => _section = value),
        ),
        const SizedBox(height: 14),
        if (_section == 'products' || _section == 'wishlist') ...[
          SearchBox(
            hint: t('market.search'),
            onChanged: (value) => setState(() => _query = value),
          ),
          const SizedBox(height: 14),
          if (products.isEmpty)
            _MarketEmpty(
              icon: _section == 'wishlist'
                  ? Icons.favorite_border
                  : Icons.storefront_outlined,
              text: t(
                _section == 'wishlist'
                    ? 'market.emptyWishlist'
                    : 'market.emptyProducts',
              ),
            )
          else
            for (final product in products) ...[
              _ProductCard(
                product: product,
                busy: _busy,
                onOpen: () => _openProduct(product),
                onAddToCart: () => _addToCart(product),
                onToggleWishlist: () => _toggleWishlist(product),
              ),
              const SizedBox(height: 10),
            ],
        ] else if (_section == 'cart')
          _CartSection(
            cart: bundle.cart,
            busy: _busy,
            onQuantityChanged: _updateCartQuantity,
            onRemove: _removeCartItem,
            onCheckout: () => _openCheckout(bundle),
          )
        else if (bundle.orders.isEmpty)
          _MarketEmpty(
            icon: Icons.receipt_long_outlined,
            text: t('market.emptyOrders'),
          )
        else
          for (final order in bundle.orders) ...[
            _OrderCard(order: order, onTap: () => _openOrder(order)),
            const SizedBox(height: 10),
          ],
      ],
    );
  }

  Future<void> _toggleWishlist(JsonMap product) async {
    final t = AirmiusScope.of(context).t;
    final active = _marketBool(_marketMap(product['viewer'])['is_wishlisted']);
    await _run(
      () async {
        if (active) {
          await _client.removeCommerceWishlist(_marketInt(product['id']));
        } else {
          await _client.addCommerceWishlist(_marketInt(product['id']));
        }
      },
      successMessage: t(
        active ? 'market.wishlistRemoved' : 'market.wishlistAdded',
      ),
    );
  }

  Future<void> _addToCart(JsonMap product, {int quantity = 1}) async {
    final t = AirmiusScope.of(context).t;
    await _run(
      () => _client
          .addCommerceCartItem(_marketInt(product['id']), quantity: quantity)
          .then((_) {}),
      successMessage: t('market.cartAdded'),
    );
  }

  Future<void> _updateCartQuantity(int itemId, int quantity) async {
    await _run(
      () => _client
          .updateCommerceCartItem(itemId, quantity: quantity)
          .then((_) {}),
    );
  }

  Future<void> _removeCartItem(int itemId) async {
    final t = AirmiusScope.of(context).t;
    await _run(
      () => _client.removeCommerceCartItem(itemId).then((_) {}),
      successMessage: t('market.cartRemoved'),
    );
  }

  Future<void> _openProduct(JsonMap product) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: airmiusSurfaceColor(context),
      builder: (_) => _ProductDetailSheet(
        client: _client,
        initialProduct: product,
        onAddToCart: (item, quantity) => _addToCart(item, quantity: quantity),
        onChanged: _reload,
      ),
    );
  }

  Future<void> _openCheckout(_MarketplaceBundle bundle) async {
    if (_marketMaps(bundle.cart['items']).isEmpty) return;
    final order = await showModalBottomSheet<JsonMap>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: airmiusSurfaceColor(context),
      builder: (_) => _CheckoutSheet(
        client: _client,
        cart: bundle.cart,
        checkout: bundle.checkout,
      ),
    );
    if (order == null || !mounted) return;
    setState(() => _section = 'orders');
    _reload();

    final action = _marketMap(order['payment_action']);
    final redirect = _marketNullableText(action['url']);
    if (redirect != null) {
      final uri = safeExternalHttpUrl(redirect);
      if (uri == null) {
        if (mounted) _toast(AirmiusScope.of(context).t('market.openFailed'));
        return;
      }
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened && mounted) {
        _toast(AirmiusScope.of(context).t('market.openFailed'));
      }
      return;
    }
    if (_marketText(action['type']) == 'bank_transfer' && mounted) {
      await _showBankTransfer(context, order);
    }
  }

  Future<void> _openOrder(JsonMap order) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: airmiusSurfaceColor(context),
      builder: (_) => _OrderDetailSheet(
        client: _client,
        orderId: _marketInt(order['id']),
        onChanged: _reload,
      ),
    );
  }
}

class _MarketplaceBundle {
  const _MarketplaceBundle({
    this.products = const [],
    this.wishlist = const [],
    this.orders = const [],
    this.cart = const {},
    this.checkout = const {},
  });

  final List<JsonMap> products;
  final List<JsonMap> wishlist;
  final List<JsonMap> orders;
  final JsonMap cart;
  final JsonMap checkout;
}

class _MarketSectionPicker extends StatelessWidget {
  const _MarketSectionPicker({
    required this.value,
    required this.cartCount,
    required this.onChanged,
  });

  final String value;
  final int cartCount;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final entries = [
      ('products', Icons.storefront_outlined, t('market.products'), null),
      ('cart', Icons.shopping_cart_outlined, t('market.cart'), cartCount),
      ('wishlist', Icons.favorite_outline, t('market.wishlist'), null),
      ('orders', Icons.receipt_long_outlined, t('market.orders'), null),
    ];
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final entry in entries) ...[
            ChoiceChip(
              selected: value == entry.$1,
              onSelected: (_) => onChanged(entry.$1),
              avatar: Icon(entry.$2, size: 20),
              label: Text(
                entry.$4 == null || entry.$4 == 0
                    ? entry.$3
                    : '${entry.$3} (${entry.$4})',
              ),
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
              selectedColor: airmiusAccentColor(context).withValues(alpha: .2),
              backgroundColor: airmiusSurfaceColor(context),
              side: BorderSide(
                color: value == entry.$1
                    ? airmiusAccentColor(context)
                    : airmiusBorderColor(context),
              ),
              labelStyle: TextStyle(
                color: value == entry.$1
                    ? airmiusTextColor(context)
                    : airmiusMutedColor(context),
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _MiniMetric extends StatelessWidget {
  const _MiniMetric({required this.value, required this.label, this.color});

  final String value;
  final String label;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final metricColor = color ?? airmiusAccentColor(context);
    return Container(
      constraints: const BoxConstraints(minWidth: 96),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: metricColor.withValues(alpha: .1),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: metricColor.withValues(alpha: .35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
          Text(
            label,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({
    required this.product,
    required this.busy,
    required this.onOpen,
    required this.onAddToCart,
    required this.onToggleWishlist,
  });

  final JsonMap product;
  final bool busy;
  final VoidCallback onOpen;
  final VoidCallback onAddToCart;
  final VoidCallback onToggleWishlist;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final viewer = _marketMap(product['viewer']);
    final provider = _marketMap(product['provider_profile']);
    final trust = _marketMap(product['seller_trust']);
    final availability = _marketMap(product['availability']);
    final reviews = _marketMap(product['review_summary']);
    final wished = _marketBool(viewer['is_wishlisted']);
    final available = _marketBool(availability['is_available']);
    final title = _marketText(
      product['title'],
      fallback: t('market.untitledProduct'),
    );
    final imageUrl = _marketNullableText(product['image_url']);

    return AirmiusPanel(
      onTap: onOpen,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _ProductImage(url: imageUrl, size: 84),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            _marketText(
                              provider['display_name'],
                              fallback: t('market.provider'),
                            ),
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(color: airmiusMutedColor(context)),
                          ),
                        ),
                        if (_marketBool(trust['seller_verified'])) ...[
                          const SizedBox(width: 4),
                          Icon(
                            Icons.verified,
                            size: 17,
                            color: airmiusAccentColor(context),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 9),
                    Wrap(
                      spacing: 7,
                      runSpacing: 7,
                      children: [
                        StatusPill(
                          _price(
                            context,
                            _marketInt(product['price_cents']),
                            _marketText(product['currency'], fallback: 'EUR'),
                          ),
                          color: Theme.of(context).colorScheme.secondary,
                        ),
                        if (_marketInt(reviews['rating_count']) > 0)
                          StatusPill(
                            '★ ${reviews['rating_avg']} (${reviews['rating_count']})',
                            color: Theme.of(context).colorScheme.tertiary,
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              IconButton(
                tooltip: t(
                  wished ? 'market.removeWishlist' : 'market.addWishlist',
                ),
                onPressed: busy ? null : onToggleWishlist,
                icon: Icon(
                  wished ? Icons.favorite : Icons.favorite_border,
                  color: wished
                      ? Theme.of(context).colorScheme.error
                      : airmiusMutedColor(context),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: onOpen,
                  child: Text(t('market.details')),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                flex: 2,
                child: FilledButton.icon(
                  onPressed: busy || !available ? null : onAddToCart,
                  icon: Icon(Icons.add_shopping_cart_outlined),
                  label: Text(
                    t(available ? 'market.addToCart' : 'market.unavailable'),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ProductImage extends StatelessWidget {
  const _ProductImage({required this.url, required this.size});

  final String? url;
  final double size;

  @override
  Widget build(BuildContext context) {
    final resolvedUrl = url == null ? null : resolveAirmiusImageUrl(url);
    return Container(
      width: size,
      height: size,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: resolvedUrl == null
          ? Icon(
              Icons.inventory_2_outlined,
              color: airmiusAccentColor(context),
              size: 34,
            )
          : Image.network(
              resolvedUrl,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => Icon(
                Icons.broken_image_outlined,
                color: airmiusMutedColor(context),
              ),
            ),
    );
  }
}

class _ProductDetailSheet extends StatefulWidget {
  const _ProductDetailSheet({
    required this.client,
    required this.initialProduct,
    required this.onAddToCart,
    required this.onChanged,
  });

  final AirmiusApiClient client;
  final JsonMap initialProduct;
  final Future<void> Function(JsonMap product, int quantity) onAddToCart;
  final VoidCallback onChanged;

  @override
  State<_ProductDetailSheet> createState() => _ProductDetailSheetState();
}

class _ProductDetailSheetState extends State<_ProductDetailSheet> {
  Future<_ProductBundle>? _future;
  int _quantity = 1;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_ProductBundle> _load() async {
    final id = _marketInt(widget.initialProduct['id']);
    final responses = await Future.wait([
      widget.client.commerceProduct(id),
      widget.client.commerceProductReviews(id),
    ]);
    return _ProductBundle(
      product: _marketMap(responses[0]['data']),
      reviews: _marketMaps(responses[1]['data']),
    );
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: .92,
      minChildSize: .55,
      maxChildSize: .96,
      builder: (context, controller) => FutureBuilder<_ProductBundle>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _SheetError(error: snapshot.error, onRetry: _reload);
          }
          final bundle =
              snapshot.data ??
              _ProductBundle(product: widget.initialProduct, reviews: const []);
          final product = bundle.product;
          final availability = _marketMap(product['availability']);
          final returnPolicy = _marketMap(product['return_policy']);
          final fulfillment = _marketMap(product['fulfillment']);
          final provider = _marketMap(product['provider_profile']);
          final reviewSummary = _marketMap(product['review_summary']);
          final gallery = <String>{
            ..._marketStrings(product['gallery_images']),
            ?_marketNullableText(product['image_url']),
          }.map(resolveAirmiusImageUrl).whereType<String>().toList();
          final available = _marketBool(availability['is_available']);

          return ListView(
            controller: controller,
            padding: const EdgeInsets.fromLTRB(18, 6, 18, 28),
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      _marketText(
                        product['title'],
                        fallback: t('market.untitledProduct'),
                      ),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: t('close'),
                    onPressed: () => Navigator.pop(context),
                    icon: Icon(Icons.close),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              if (gallery.isNotEmpty)
                SizedBox(
                  height: 220,
                  child: PageView(
                    children: [
                      for (final image in gallery)
                        ClipRRect(
                          borderRadius: BorderRadius.circular(22),
                          child: Image.network(
                            image,
                            fit: BoxFit.cover,
                            errorBuilder: (_, _, _) => ColoredBox(
                              color: airmiusSurfaceSoftColor(context),
                              child: Icon(Icons.broken_image_outlined),
                            ),
                          ),
                        ),
                    ],
                  ),
                )
              else
                Center(child: _ProductImage(url: null, size: 150)),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      _price(
                        context,
                        _marketInt(product['price_cents']),
                        _marketText(product['currency'], fallback: 'EUR'),
                      ),
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.secondary,
                        fontSize: 23,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  if (_marketInt(reviewSummary['rating_count']) > 0)
                    StatusPill(
                      '★ ${reviewSummary['rating_avg']} (${reviewSummary['rating_count']})',
                      color: Theme.of(context).colorScheme.tertiary,
                    ),
                ],
              ),
              const SizedBox(height: 12),
              AirmiusPanel(
                child: Row(
                  children: [
                    Icon(Icons.storefront_outlined),
                    const SizedBox(width: 11),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _marketText(
                              provider['display_name'],
                              fallback: t('market.provider'),
                            ),
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          Text(
                            _marketText(
                              provider['provider_type'],
                              fallback: t('market.verifiedProvider'),
                            ),
                            style: TextStyle(color: airmiusMutedColor(context)),
                          ),
                        ],
                      ),
                    ),
                    if (_marketBool(provider['verified']))
                      Icon(Icons.verified, color: airmiusAccentColor(context)),
                  ],
                ),
              ),
              if (_marketText(product['description']).isNotEmpty) ...[
                const SizedBox(height: 16),
                Text(
                  t('market.description'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 7),
                Text(
                  _marketText(product['description']),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.5,
                  ),
                ),
              ],
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  StatusPill(
                    available ? t('market.available') : t('market.unavailable'),
                    color: available
                        ? Theme.of(context).colorScheme.secondary
                        : Theme.of(context).colorScheme.error,
                  ),
                  if (_marketBool(returnPolicy['returns_available']))
                    StatusPill(
                      '${_marketInt(returnPolicy['window_days'])} ${t('market.returnDays')}',
                    ),
                  if (_marketText(fulfillment['delivery_mode']).isNotEmpty)
                    StatusPill(
                      _marketText(fulfillment['delivery_mode']),
                      color: airmiusAccentColor(context),
                    ),
                  if (_marketBool(fulfillment['pickup_available']))
                    StatusPill(
                      t('market.pickup'),
                      color: Theme.of(context).colorScheme.secondary,
                    ),
                ],
              ),
              const SizedBox(height: 18),
              AirmiusPanel(
                borderColor: Theme.of(
                  context,
                ).colorScheme.secondary.withValues(alpha: .45),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      t('market.quantity'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        IconButton.filledTonal(
                          tooltip: t('market.decreaseQuantity'),
                          onPressed: _quantity > 1
                              ? () => setState(() => _quantity--)
                              : null,
                          icon: Icon(Icons.remove),
                        ),
                        SizedBox(
                          width: 58,
                          child: Text(
                            '$_quantity',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        IconButton.filledTonal(
                          tooltip: t('market.increaseQuantity'),
                          onPressed: () => setState(() => _quantity++),
                          icon: Icon(Icons.add),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: FilledButton.icon(
                            onPressed: !available || _busy
                                ? null
                                : () async {
                                    setState(() => _busy = true);
                                    await widget.onAddToCart(
                                      product,
                                      _quantity,
                                    );
                                    if (mounted) setState(() => _busy = false);
                                  },
                            icon: _busy
                                ? const SizedBox.square(
                                    dimension: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : Icon(Icons.add_shopping_cart_outlined),
                            label: Text(t('market.addToCart')),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Text(
                      t('market.checkoutSafety'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontSize: 12,
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      t('market.reviews'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  TextButton.icon(
                    onPressed: () => _writeReview(product),
                    icon: Icon(Icons.rate_review_outlined),
                    label: Text(t('market.writeReview')),
                  ),
                ],
              ),
              if (bundle.reviews.isEmpty)
                Text(
                  t('market.noReviews'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                )
              else
                for (final review in bundle.reviews) ...[
                  _ReviewCard(review: review),
                  const SizedBox(height: 9),
                ],
            ],
          );
        },
      ),
    );
  }

  Future<void> _writeReview(JsonMap product) async {
    final result = await showDialog<_ReviewDraft>(
      context: context,
      builder: (_) => const _ReviewDialog(),
    );
    if (result == null || !mounted) return;
    try {
      await widget.client.createCommerceProductReview(
        _marketInt(product['id']),
        rating: result.rating,
        title: result.title,
        body: result.body,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('market.reviewSaved')),
        ),
      );
      widget.onChanged();
      _reload();
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    }
  }
}

class _ProductBundle {
  const _ProductBundle({required this.product, required this.reviews});

  final JsonMap product;
  final List<JsonMap> reviews;
}

class _ReviewCard extends StatelessWidget {
  const _ReviewCard({required this.review});

  final JsonMap review;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final author = _marketMap(review['author']);
    final card = _marketMap(author['user_card']);
    final name = _marketText(
      card['display_name'] ?? author['name'],
      fallback: t('market.buyer'),
    );
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  name,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              Text(
                '★' * _marketInt(review['rating']).clamp(1, 5),
                style: TextStyle(color: Theme.of(context).colorScheme.tertiary),
              ),
            ],
          ),
          if (_marketText(review['title']).isNotEmpty) ...[
            const SizedBox(height: 5),
            Text(
              _marketText(review['title']),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
          if (_marketText(review['body']).isNotEmpty) ...[
            const SizedBox(height: 5),
            Text(
              _marketText(review['body']),
              style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
            ),
          ],
          if (_marketBool(review['verified_purchase'])) ...[
            const SizedBox(height: 7),
            Text(
              t('market.verifiedPurchase'),
              style: TextStyle(
                color: Theme.of(context).colorScheme.secondary,
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _ReviewDialog extends StatefulWidget {
  const _ReviewDialog();

  @override
  State<_ReviewDialog> createState() => _ReviewDialogState();
}

class _ReviewDialogState extends State<_ReviewDialog> {
  int _rating = 5;
  final _title = TextEditingController();
  final _body = TextEditingController();

  @override
  void dispose() {
    _title.dispose();
    _body.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(t('market.writeReview')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Semantics(
              label: t('market.rating'),
              child: Wrap(
                children: [
                  for (var index = 1; index <= 5; index++)
                    IconButton(
                      tooltip: '$index / 5',
                      onPressed: () => setState(() => _rating = index),
                      icon: Icon(
                        index <= _rating ? Icons.star : Icons.star_border,
                        color: Theme.of(context).colorScheme.tertiary,
                      ),
                    ),
                ],
              ),
            ),
            TextField(
              controller: _title,
              maxLength: 160,
              decoration: InputDecoration(labelText: t('market.reviewTitle')),
            ),
            TextField(
              controller: _body,
              minLines: 3,
              maxLines: 6,
              maxLength: 2000,
              decoration: InputDecoration(labelText: t('market.reviewBody')),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(
            context,
            _ReviewDraft(rating: _rating, title: _title.text, body: _body.text),
          ),
          child: Text(t('save')),
        ),
      ],
    );
  }
}

class _ReviewDraft {
  const _ReviewDraft({
    required this.rating,
    required this.title,
    required this.body,
  });

  final int rating;
  final String title;
  final String body;
}

class _CartSection extends StatelessWidget {
  const _CartSection({
    required this.cart,
    required this.busy,
    required this.onQuantityChanged,
    required this.onRemove,
    required this.onCheckout,
  });

  final JsonMap cart;
  final bool busy;
  final Future<void> Function(int itemId, int quantity) onQuantityChanged;
  final Future<void> Function(int itemId) onRemove;
  final VoidCallback onCheckout;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final items = _marketMaps(cart['items']);
    final summary = _marketMap(cart['summary']);
    if (items.isEmpty) {
      return _MarketEmpty(
        icon: Icons.shopping_cart_outlined,
        text: t('market.emptyCart'),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final item in items) ...[
          _CartItemCard(
            item: item,
            busy: busy,
            onQuantityChanged: onQuantityChanged,
            onRemove: onRemove,
          ),
          const SizedBox(height: 10),
        ],
        AirmiusPanel(
          borderColor: Theme.of(
            context,
          ).colorScheme.secondary.withValues(alpha: .5),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                t('market.summary'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
              _AmountRow(
                label: t('market.itemsSubtotal'),
                cents: _marketInt(summary['item_gross_cents']),
                currency: _marketText(summary['currency'], fallback: 'EUR'),
              ),
              _AmountRow(
                label: t('market.shipping'),
                cents: _marketInt(summary['shipping_cents']),
                currency: _marketText(summary['currency'], fallback: 'EUR'),
              ),
              _AmountRow(
                label: t('market.includingTax'),
                cents: _marketInt(summary['tax_cents']),
                currency: _marketText(summary['currency'], fallback: 'EUR'),
                muted: true,
              ),
              Divider(),
              _AmountRow(
                label: t('market.total'),
                cents: _marketInt(summary['amount_cents']),
                currency: _marketText(summary['currency'], fallback: 'EUR'),
                strong: true,
              ),
              const SizedBox(height: 14),
              FilledButton.icon(
                onPressed: busy ? null : onCheckout,
                icon: Icon(Icons.lock_outline),
                label: Text(t('market.secureCheckout')),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _CartItemCard extends StatelessWidget {
  const _CartItemCard({
    required this.item,
    required this.busy,
    required this.onQuantityChanged,
    required this.onRemove,
  });

  final JsonMap item;
  final bool busy;
  final Future<void> Function(int itemId, int quantity) onQuantityChanged;
  final Future<void> Function(int itemId) onRemove;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final product = _marketMap(item['product']);
    final itemId = _marketInt(item['id']);
    final quantity = _marketInt(item['quantity']).clamp(1, 999);
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _ProductImage(
            url: _marketNullableText(product['image_url']),
            size: 68,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _marketText(
                    product['title'],
                    fallback: t('market.untitledProduct'),
                  ),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  _price(
                    context,
                    _marketInt(item['line_total_cents']),
                    _marketText(product['currency'], fallback: 'EUR'),
                  ),
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.secondary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 9),
                Row(
                  children: [
                    IconButton.filledTonal(
                      tooltip: t('market.decreaseQuantity'),
                      onPressed: busy || quantity <= 1
                          ? null
                          : () => onQuantityChanged(itemId, quantity - 1),
                      icon: Icon(Icons.remove, size: 19),
                    ),
                    SizedBox(
                      width: 44,
                      child: Text(
                        '$quantity',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    IconButton.filledTonal(
                      tooltip: t('market.increaseQuantity'),
                      onPressed: busy
                          ? null
                          : () => onQuantityChanged(itemId, quantity + 1),
                      icon: Icon(Icons.add, size: 19),
                    ),
                  ],
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: t('market.removeCartItem'),
            onPressed: busy
                ? null
                : () async {
                    final confirmed = await _confirm(
                      context,
                      title: t('market.removeCartItem'),
                      body: t('market.removeCartQuestion'),
                    );
                    if (confirmed) await onRemove(itemId);
                  },
            icon: Icon(
              Icons.delete_outline,
              color: Theme.of(context).colorScheme.error,
            ),
          ),
        ],
      ),
    );
  }
}

class _AmountRow extends StatelessWidget {
  const _AmountRow({
    required this.label,
    required this.cents,
    required this.currency,
    this.strong = false,
    this.muted = false,
  });

  final String label;
  final int cents;
  final String currency;
  final bool strong;
  final bool muted;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              color: muted
                  ? airmiusMutedColor(context)
                  : airmiusTextColor(context),
              fontWeight: strong ? FontWeight.w900 : FontWeight.w600,
            ),
          ),
        ),
        Text(
          _price(context, cents, currency),
          style: TextStyle(
            color: strong
                ? Theme.of(context).colorScheme.secondary
                : airmiusTextColor(context),
            fontSize: strong ? 18 : null,
            fontWeight: strong ? FontWeight.w900 : FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}

class _CheckoutSheet extends StatefulWidget {
  const _CheckoutSheet({
    required this.client,
    required this.cart,
    required this.checkout,
  });

  final AirmiusApiClient client;
  final JsonMap cart;
  final JsonMap checkout;

  @override
  State<_CheckoutSheet> createState() => _CheckoutSheetState();
}

class _CheckoutSheetState extends State<_CheckoutSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _country;
  late final TextEditingController _street;
  late final TextEditingController _houseNumber;
  late final TextEditingController _postalCode;
  late final TextEditingController _city;
  final _company = TextEditingController();
  final _vatId = TextEditingController();
  String _provider = 'bank_transfer';
  String _customerType = 'consumer';
  bool _acceptedTerms = false;
  bool _saveAddress = false;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final address = _marketMap(widget.checkout['checkout_address']);
    _country = TextEditingController(
      text: _marketText(address['country'], fallback: 'DE'),
    );
    _street = TextEditingController(text: _marketText(address['street']));
    _houseNumber = TextEditingController(
      text: _marketText(address['house_number']),
    );
    _postalCode = TextEditingController(
      text: _marketText(address['postal_code']),
    );
    _city = TextEditingController(text: _marketText(address['city']));
  }

  @override
  void dispose() {
    _country.dispose();
    _street.dispose();
    _houseNumber.dispose();
    _postalCode.dispose();
    _city.dispose();
    _company.dispose();
    _vatId.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final summary = _marketMap(widget.cart['summary']);
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: .94,
      minChildSize: .65,
      maxChildSize: .98,
      builder: (context, controller) => Form(
        key: _formKey,
        child: ListView(
          controller: controller,
          padding: const EdgeInsets.fromLTRB(18, 6, 18, 30),
          children: [
            Row(
              children: [
                Icon(
                  Icons.lock_outline,
                  color: Theme.of(context).colorScheme.secondary,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    t('market.secureCheckout'),
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 23,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: t('close'),
                  onPressed: _busy ? null : () => Navigator.pop(context),
                  icon: Icon(Icons.close),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              t('market.shippingAddress'),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontSize: 18,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 10),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  flex: 3,
                  child: TextFormField(
                    controller: _street,
                    textInputAction: TextInputAction.next,
                    autofillHints: const [AutofillHints.streetAddressLine1],
                    decoration: InputDecoration(
                      labelText: t('market.street'),
                      prefixIcon: Icon(Icons.home_outlined),
                    ),
                    validator: (value) => _required(t, value),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: TextFormField(
                    controller: _houseNumber,
                    textInputAction: TextInputAction.next,
                    decoration: InputDecoration(
                      labelText: t('market.houseNumber'),
                    ),
                    validator: (value) => _required(t, value),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _postalCode,
                    keyboardType: TextInputType.text,
                    textInputAction: TextInputAction.next,
                    autofillHints: const [AutofillHints.postalCode],
                    decoration: InputDecoration(
                      labelText: t('market.postalCode'),
                    ),
                    validator: (value) => _required(t, value),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  flex: 2,
                  child: TextFormField(
                    controller: _city,
                    textInputAction: TextInputAction.next,
                    autofillHints: const [AutofillHints.addressCity],
                    decoration: InputDecoration(labelText: t('market.city')),
                    validator: (value) => _required(t, value),
                  ),
                ),
                const SizedBox(width: 10),
                SizedBox(
                  width: 76,
                  child: TextFormField(
                    controller: _country,
                    textCapitalization: TextCapitalization.characters,
                    maxLength: 2,
                    decoration: InputDecoration(
                      labelText: t('market.country'),
                      counterText: '',
                    ),
                    validator: (value) =>
                        value?.trim().length == 2 ? null : t('market.required'),
                  ),
                ),
              ],
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _saveAddress,
              onChanged: (value) =>
                  setState(() => _saveAddress = value ?? false),
              title: Text(t('market.saveAddress')),
              controlAffinity: ListTileControlAffinity.leading,
            ),
            const SizedBox(height: 12),
            Text(
              t('market.customerType'),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontSize: 18,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 9),
            SegmentedButton<String>(
              segments: [
                ButtonSegment(
                  value: 'consumer',
                  icon: Icon(Icons.person_outline),
                  label: Text(t('market.consumer')),
                ),
                ButtonSegment(
                  value: 'business',
                  icon: Icon(Icons.business_outlined),
                  label: Text(t('market.business')),
                ),
              ],
              selected: {_customerType},
              onSelectionChanged: (value) =>
                  setState(() => _customerType = value.first),
            ),
            if (_customerType == 'business') ...[
              const SizedBox(height: 10),
              TextFormField(
                controller: _company,
                decoration: InputDecoration(labelText: t('market.company')),
                validator: (value) => _required(t, value),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _vatId,
                textCapitalization: TextCapitalization.characters,
                decoration: InputDecoration(labelText: t('market.vatId')),
              ),
            ],
            const SizedBox(height: 18),
            Text(
              t('market.paymentMethod'),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontSize: 18,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 9),
            RadioGroup<String>(
              groupValue: _provider,
              onChanged: (value) {
                if (value != null) {
                  setState(() => _provider = value);
                }
              },
              child: Column(
                children: [
                  for (final option in const [
                    ('bank_transfer', Icons.account_balance_outlined),
                    ('stripe', Icons.credit_card_outlined),
                    ('paypal', Icons.account_balance_wallet_outlined),
                  ])
                    RadioListTile<String>(
                      contentPadding: EdgeInsets.zero,
                      value: option.$1,
                      title: Text(t('market.payment.${option.$1}')),
                      secondary: Icon(option.$2),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            AirmiusPanel(
              child: Column(
                children: [
                  _AmountRow(
                    label: t('market.total'),
                    cents: _marketInt(summary['amount_cents']),
                    currency: _marketText(summary['currency'], fallback: 'EUR'),
                    strong: true,
                  ),
                  const SizedBox(height: 8),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _acceptedTerms,
                    onChanged: (value) =>
                        setState(() => _acceptedTerms = value ?? false),
                    title: Text(t('market.acceptTerms')),
                    subtitle: Text(t('market.acceptTermsHint')),
                    controlAffinity: ListTileControlAffinity.leading,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: _busy ? null : _submit,
              icon: _busy
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Icon(Icons.lock_outline),
              label: Text(t('market.placeOrder')),
            ),
          ],
        ),
      ),
    );
  }

  String? _required(String Function(String) t, String? value) =>
      value == null || value.trim().isEmpty ? t('market.required') : null;

  Future<void> _submit() async {
    final t = AirmiusScope.of(context).t;
    if (!_formKey.currentState!.validate()) return;
    if (!_acceptedTerms) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('market.acceptTermsRequired'))));
      return;
    }
    setState(() => _busy = true);
    try {
      final response = await widget.client.checkoutCommerceCart({
        'provider': _provider,
        'accepted_terms': true,
        'shipping_country': _country.text.trim().toUpperCase(),
        'shipping_postal_code': _postalCode.text.trim(),
        'shipping_city': _city.text.trim(),
        'shipping_street': _street.text.trim(),
        'shipping_house_number': _houseNumber.text.trim(),
        'customer_type': _customerType,
        if (_customerType == 'business')
          'customer_company': _company.text.trim(),
        if (_customerType == 'business') 'customer_vat_id': _vatId.text.trim(),
        'save_shipping_address': _saveAddress,
        if (_saveAddress) 'shipping_address_label': t('market.savedAddress'),
      });
      if (!mounted) return;
      Navigator.pop(context, _marketMap(response['data']));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order, required this.onTap});

  final JsonMap order;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final items = _marketMaps(order['items']);
    return AirmiusPanel(
      onTap: onTap,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _MarketIcon(icon: Icons.receipt_long_outlined),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${t('market.order')} #${_marketInt(order['id'])}',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  items.isEmpty
                      ? t('market.orderDetails')
                      : items
                            .map((item) => _marketText(item['title']))
                            .where((title) => title.isNotEmpty)
                            .join(', '),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                const SizedBox(height: 9),
                Wrap(
                  spacing: 7,
                  runSpacing: 7,
                  children: [
                    StatusPill(
                      _price(
                        context,
                        _marketInt(order['amount_cents']),
                        _marketText(order['currency'], fallback: 'EUR'),
                      ),
                      color: Theme.of(context).colorScheme.secondary,
                    ),
                    StatusPill(
                      _statusLabel(t, _marketText(order['status'])),
                      color: _statusColor(
                        context,
                        _marketText(order['status']),
                      ),
                    ),
                    if (_marketText(order['shipping_status']).isNotEmpty)
                      StatusPill(
                        _statusLabel(t, _marketText(order['shipping_status'])),
                      ),
                  ],
                ),
              ],
            ),
          ),
          Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
        ],
      ),
    );
  }
}

class _OrderDetailSheet extends StatefulWidget {
  const _OrderDetailSheet({
    required this.client,
    required this.orderId,
    required this.onChanged,
  });

  final AirmiusApiClient client;
  final int orderId;
  final VoidCallback onChanged;

  @override
  State<_OrderDetailSheet> createState() => _OrderDetailSheetState();
}

class _OrderDetailSheetState extends State<_OrderDetailSheet> {
  Future<JsonMap>? _future;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _future = widget.client
        .commerceOrder(widget.orderId)
        .then((response) => _marketMap(response['data']));
  }

  void _reload() {
    widget.onChanged();
    setState(() {
      _future = widget.client
          .commerceOrder(widget.orderId)
          .then((response) => _marketMap(response['data']));
    });
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: .92,
      minChildSize: .55,
      maxChildSize: .97,
      builder: (context, controller) => FutureBuilder<JsonMap>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _SheetError(error: snapshot.error, onRetry: _reload);
          }
          return _content(controller, snapshot.data ?? const {});
        },
      ),
    );
  }

  Widget _content(ScrollController controller, JsonMap order) {
    final t = AirmiusScope.of(context).t;
    final items = _marketMaps(order['items']);
    final support = _marketMap(order['support']);
    final documents = _marketMap(order['documents']);
    final invoice = _marketMap(documents['invoice']);
    final creditNote = _marketMap(documents['credit_note']);
    final bank = _marketMap(order['bank_transfer']);
    final returns = _marketMaps(order['return_requests']);
    final checkoutUrl = _marketNullableText(
      _marketMap(order['payment_action'])['url'],
    );
    final trackingUrl = _marketNullableText(order['tracking_url']);

    return ListView(
      controller: controller,
      padding: const EdgeInsets.fromLTRB(18, 6, 18, 28),
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                '${t('market.order')} #${_marketInt(order['id'])}',
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 23,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            IconButton(
              tooltip: t('close'),
              onPressed: () => Navigator.pop(context),
              icon: Icon(Icons.close),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            StatusPill(
              _statusLabel(t, _marketText(order['status'])),
              color: _statusColor(context, _marketText(order['status'])),
            ),
            if (_marketText(order['shipping_status']).isNotEmpty)
              StatusPill(
                _statusLabel(t, _marketText(order['shipping_status'])),
              ),
            if (_marketText(order['issue_status']).isNotEmpty &&
                _marketText(order['issue_status']) != 'none')
              StatusPill(
                _statusLabel(t, _marketText(order['issue_status'])),
                color: Theme.of(context).colorScheme.tertiary,
              ),
          ],
        ),
        const SizedBox(height: 14),
        AirmiusPanel(
          child: Column(
            children: [
              for (final item in items)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(
                    _marketText(item['title']),
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  subtitle: Text(
                    '${t('market.quantity')}: ${_marketInt(item['quantity'])}',
                  ),
                  trailing: Text(
                    _price(
                      context,
                      _marketInt(item['total_cents']),
                      _marketText(item['currency'], fallback: 'EUR'),
                    ),
                  ),
                ),
              Divider(),
              _AmountRow(
                label: t('market.total'),
                cents: _marketInt(order['amount_cents']),
                currency: _marketText(order['currency'], fallback: 'EUR'),
                strong: true,
              ),
            ],
          ),
        ),
        if (bank.isNotEmpty) ...[
          const SizedBox(height: 12),
          _BankTransferCard(order: order),
        ],
        if (_marketText(order['issue_note']).isNotEmpty ||
            _marketText(order['issue_response']).isNotEmpty) ...[
          const SizedBox(height: 12),
          AirmiusPanel(
            borderColor: Theme.of(
              context,
            ).colorScheme.tertiary.withValues(alpha: .45),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  t('market.issue'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (_marketText(order['issue_note']).isNotEmpty)
                  Text(
                    _marketText(order['issue_note']),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.4,
                    ),
                  ),
                if (_marketText(order['issue_response']).isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(
                    '${t('market.response')}: ${_marketText(order['issue_response'])}',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.secondary,
                      height: 1.4,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
        if (returns.isNotEmpty) ...[
          const SizedBox(height: 12),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  t('market.returns'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                for (final item in returns)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(Icons.assignment_return_outlined),
                    title: Text(_statusLabel(t, _marketText(item['status']))),
                    subtitle: Text(_marketText(item['reason'])),
                  ),
              ],
            ),
          ),
        ],
        const SizedBox(height: 14),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            if (checkoutUrl != null)
              AirmiusButton(
                label: t('market.continuePayment'),
                icon: Icons.open_in_new,
                onPressed: () => _openUrl(checkoutUrl),
              ),
            if (trackingUrl != null)
              AirmiusButton(
                label: t('market.trackShipment'),
                icon: Icons.local_shipping_outlined,
                secondary: true,
                onPressed: () => _openUrl(trackingUrl),
              ),
            if (_marketBool(invoice['available']))
              AirmiusButton(
                label: t('market.invoice'),
                icon: Icons.picture_as_pdf_outlined,
                secondary: true,
                onPressed: () => _openUrl(_marketText(invoice['url'])),
              ),
            if (_marketBool(creditNote['available']))
              AirmiusButton(
                label: t('market.creditNote'),
                icon: Icons.picture_as_pdf_outlined,
                secondary: true,
                onPressed: () => _openUrl(_marketText(creditNote['url'])),
              ),
            if (_marketBool(support['can_cancel']))
              AirmiusButton(
                label: t('market.cancelOrder'),
                icon: Icons.cancel_outlined,
                danger: true,
                onPressed: _busy ? null : () => _cancel(order),
              ),
            if (_marketBool(support['can_report_issue']))
              AirmiusButton(
                label: t('market.reportIssue'),
                icon: Icons.report_problem_outlined,
                secondary: true,
                onPressed: _busy ? null : () => _reportIssue(order),
              ),
            if (_marketBool(support['can_request_return']))
              AirmiusButton(
                label: t('market.requestReturn'),
                icon: Icons.assignment_return_outlined,
                secondary: true,
                onPressed: _busy ? null : () => _requestReturn(order, support),
              ),
          ],
        ),
      ],
    );
  }

  Future<void> _openUrl(String value) async {
    final uri = safeExternalHttpUrl(value);
    if (uri == null ||
        !await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('market.openFailed')),
        ),
      );
    }
  }

  Future<void> _cancel(JsonMap order) async {
    final t = AirmiusScope.of(context).t;
    if (!await _confirm(
      context,
      title: t('market.cancelOrder'),
      body: t('market.cancelOrderQuestion'),
    )) {
      return;
    }
    await _action(() => widget.client.cancelCommerceOrder(widget.orderId));
  }

  Future<void> _reportIssue(JsonMap order) async {
    final t = AirmiusScope.of(context).t;
    final note = await _textInputDialog(
      context,
      title: t('market.reportIssue'),
      label: t('market.issueDescription'),
      maxLength: 2000,
    );
    if (note == null || note.trim().isEmpty) return;
    await _action(
      () => widget.client.reportCommerceOrderIssue(widget.orderId, note: note),
    );
  }

  Future<void> _requestReturn(JsonMap order, JsonMap support) async {
    final t = AirmiusScope.of(context).t;
    final returnable = _marketMaps(support['returnable_items']);
    final draft = await showDialog<_ReturnDraft>(
      context: context,
      builder: (_) => _ReturnDialog(items: returnable),
    );
    if (draft == null) return;
    await _action(
      () => widget.client.requestCommerceOrderReturn(
        widget.orderId,
        reason: draft.reason,
        itemId: draft.itemId,
        quantity: draft.quantity,
      ),
      successMessage: t('market.returnRequested'),
    );
  }

  Future<void> _action(
    Future<JsonMap> Function() action, {
    String? successMessage,
  }) async {
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      if (successMessage != null) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(successMessage)));
      }
      _reload();
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }
}

class _ReturnDialog extends StatefulWidget {
  const _ReturnDialog({required this.items});

  final List<JsonMap> items;

  @override
  State<_ReturnDialog> createState() => _ReturnDialogState();
}

class _ReturnDialogState extends State<_ReturnDialog> {
  final _reason = TextEditingController();
  int? _itemId;
  int _quantity = 1;

  @override
  void initState() {
    super.initState();
    if (widget.items.isNotEmpty) _itemId = _marketInt(widget.items.first['id']);
  }

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final selected = widget.items
        .where((item) => _marketInt(item['id']) == _itemId)
        .firstOrNull;
    final maximum = _marketInt(selected?['quantity']).clamp(1, 999);
    if (_quantity > maximum) _quantity = maximum;
    return AlertDialog(
      title: Text(t('market.requestReturn')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (widget.items.isNotEmpty)
              DropdownButtonFormField<int>(
                initialValue: _itemId,
                decoration: InputDecoration(labelText: t('market.product')),
                items: [
                  for (final item in widget.items)
                    DropdownMenuItem(
                      value: _marketInt(item['id']),
                      child: Text(_marketText(item['title'])),
                    ),
                ],
                onChanged: (value) => setState(() {
                  _itemId = value;
                  _quantity = 1;
                }),
              ),
            const SizedBox(height: 10),
            DropdownButtonFormField<int>(
              initialValue: _quantity,
              decoration: InputDecoration(labelText: t('market.quantity')),
              items: [
                for (var value = 1; value <= maximum; value++)
                  DropdownMenuItem(value: value, child: Text('$value')),
              ],
              onChanged: (value) => setState(() => _quantity = value ?? 1),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _reason,
              minLines: 3,
              maxLines: 6,
              maxLength: 2000,
              decoration: InputDecoration(labelText: t('market.returnReason')),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton(
          onPressed: () {
            if (_reason.text.trim().isEmpty) return;
            Navigator.pop(
              context,
              _ReturnDraft(
                reason: _reason.text.trim(),
                itemId: _itemId,
                quantity: _quantity,
              ),
            );
          },
          child: Text(t('market.requestReturn')),
        ),
      ],
    );
  }
}

class _ReturnDraft {
  const _ReturnDraft({
    required this.reason,
    required this.itemId,
    required this.quantity,
  });

  final String reason;
  final int? itemId;
  final int quantity;
}

class _BankTransferCard extends StatelessWidget {
  const _BankTransferCard({required this.order});

  final JsonMap order;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final bank = _marketMap(order['bank_transfer']);
    return AirmiusPanel(
      borderColor: airmiusAccentColor(context).withValues(alpha: .5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            t('market.bankTransferTitle'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 10),
          _CopyLine(
            label: t('market.accountHolder'),
            value: _marketText(bank['account_holder']),
          ),
          _CopyLine(label: t('market.iban'), value: _marketText(bank['iban'])),
          if (_marketText(bank['bic']).isNotEmpty)
            _CopyLine(label: t('market.bic'), value: _marketText(bank['bic'])),
          _CopyLine(
            label: t('market.reference'),
            value: _marketText(bank['reference']),
          ),
          const SizedBox(height: 8),
          Text(
            t('market.bankTransferHint'),
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontSize: 12,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }
}

class _CopyLine extends StatelessWidget {
  const _CopyLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 5),
          child: Text.rich(
            TextSpan(
              text: '$label\n',
              style: TextStyle(color: airmiusMutedColor(context), fontSize: 12),
              children: [
                TextSpan(
                  text: value,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      IconButton(
        tooltip: MaterialLocalizations.of(context).copyButtonLabel,
        onPressed: value.isEmpty
            ? null
            : () async {
                await Clipboard.setData(ClipboardData(text: value));
                if (!context.mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(AirmiusScope.of(context).t('market.copied')),
                  ),
                );
              },
        icon: Icon(Icons.copy_outlined),
      ),
    ],
  );
}

Future<void> _showBankTransfer(BuildContext context, JsonMap order) {
  final t = AirmiusScope.of(context).t;
  return showModalBottomSheet<void>(
    context: context,
    useSafeArea: true,
    isScrollControlled: true,
    backgroundColor: airmiusSurfaceColor(context),
    showDragHandle: true,
    builder: (_) => Padding(
      padding: const EdgeInsets.fromLTRB(18, 4, 18, 28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            t('market.orderCreated'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 22,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          _BankTransferCard(order: order),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: () => Navigator.pop(context),
            child: Text(t('close')),
          ),
        ],
      ),
    ),
  );
}

class _MarketIcon extends StatelessWidget {
  const _MarketIcon({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    width: 50,
    height: 50,
    decoration: BoxDecoration(
      color: airmiusSurfaceSoftColor(context),
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: airmiusBorderColor(context)),
    ),
    child: Icon(icon, color: airmiusAccentColor(context)),
  );
}

class _MarketEmpty extends StatelessWidget {
  const _MarketEmpty({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 28),
      child: Column(
        children: [
          Icon(icon, size: 42, color: airmiusMutedColor(context)),
          const SizedBox(height: 10),
          Text(
            text,
            textAlign: TextAlign.center,
            style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
          ),
        ],
      ),
    ),
  );
}

class _MarketLoading extends StatelessWidget {
  const _MarketLoading();

  @override
  Widget build(BuildContext context) => const AirmiusPanel(
    child: Padding(
      padding: EdgeInsets.all(28),
      child: Center(child: CircularProgressIndicator()),
    ),
  );
}

class _MarketError extends StatelessWidget {
  const _MarketError({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      borderColor: Theme.of(context).colorScheme.error.withValues(alpha: .5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            t('market.loadError'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            error is AirmiusApiException
                ? (error as AirmiusApiException).userMessage
                : t('common.errorDetails'),
            style: TextStyle(color: airmiusMutedColor(context)),
          ),
          const SizedBox(height: 14),
          AirmiusButton(
            label: t('market.reload'),
            icon: Icons.refresh_outlined,
            secondary: true,
            onPressed: onRetry,
          ),
        ],
      ),
    );
  }
}

class _SheetError extends StatelessWidget {
  const _SheetError({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(20),
    child: _MarketError(error: error, onRetry: onRetry),
  );
}

Future<bool> _confirm(
  BuildContext context, {
  required String title,
  required String body,
}) async =>
    await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(title),
        content: Text(body),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(AirmiusScope.of(context).t('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(AirmiusScope.of(context).t('confirm')),
          ),
        ],
      ),
    ) ??
    false;

Future<String?> _textInputDialog(
  BuildContext context, {
  required String title,
  required String label,
  required int maxLength,
}) async {
  final controller = TextEditingController();
  final result = await showDialog<String>(
    context: context,
    builder: (_) => AlertDialog(
      title: Text(title),
      content: TextField(
        controller: controller,
        minLines: 3,
        maxLines: 7,
        maxLength: maxLength,
        autofocus: true,
        decoration: InputDecoration(labelText: label),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(AirmiusScope.of(context).t('cancel')),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(context, controller.text.trim()),
          child: Text(AirmiusScope.of(context).t('send')),
        ),
      ],
    ),
  );
  controller.dispose();
  return result;
}

JsonMap _marketMap(Object? value) {
  if (value is JsonMap) return value;
  if (value is Map) {
    return value.map((key, item) => MapEntry('$key', item));
  }
  return const {};
}

List<JsonMap> _marketMaps(Object? value) => value is List
    ? value.map(_marketMap).where((item) => item.isNotEmpty).toList()
    : const [];

List<String> _marketStrings(Object? value) => value is List
    ? value
          .map(_marketText)
          .where((item) => item.isNotEmpty)
          .toList(growable: false)
    : const [];

String _marketText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty || text == 'null' ? fallback : text;
}

String? _marketNullableText(Object? value) {
  final text = _marketText(value);
  return text.isEmpty ? null : text;
}

int _marketInt(Object? value) =>
    value is num ? value.round() : int.tryParse('$value') ?? 0;

bool _marketBool(Object? value) =>
    value == true || value == 1 || '$value'.toLowerCase() == 'true';

String _price(BuildContext context, int cents, String currency) {
  try {
    return NumberFormat.simpleCurrency(
      locale: Localizations.localeOf(context).toLanguageTag(),
      name: currency.toUpperCase(),
    ).format(cents / 100);
  } catch (_) {
    return '${(cents / 100).toStringAsFixed(2)} ${currency.toUpperCase()}';
  }
}

String _statusLabel(String Function(String) t, String status) {
  final key = 'market.status.${status.trim().toLowerCase()}';
  final translated = t(key);
  return translated == key ? status : translated;
}

Color _statusColor(BuildContext context, String status) => switch (status
    .toLowerCase()) {
  'completed' ||
  'paid' ||
  'delivered' ||
  'approved' => Theme.of(context).colorScheme.secondary,
  'cancelled' || 'failed' || 'rejected' => Theme.of(context).colorScheme.error,
  'pending' ||
  'awaiting_transfer' ||
  'reported' ||
  'requested' => Theme.of(context).colorScheme.tertiary,
  _ => airmiusAccentColor(context),
};
