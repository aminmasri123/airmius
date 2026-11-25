import 'dart:async';
import 'package:flutter/material.dart';
import '../widgets/airmius_form.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/marketplace_filters.dart';
import '../core/marketplace_filter_labels.dart';
import '../widgets/marketplace_filters_sheet.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'team_bulk_orders_screen.dart';

class MarketplaceScreen extends StatefulWidget {
  const MarketplaceScreen({
    super.key,
    this.initialQuery = '',
    this.initialSection = 'products',
    this.initialProductId,
    this.initialProductTitle = '',
    this.guest = false,
  });

  final String initialQuery;
  final String initialSection;
  final int? initialProductId;
  final String initialProductTitle;
  final bool guest;

  @override
  State<MarketplaceScreen> createState() => _MarketplaceScreenState();
}

class _MarketplaceScreenState extends State<MarketplaceScreen> {
  late String _section;
  String _query = '';
  MarketplaceFilters _filters = const MarketplaceFilters();
  JsonMap _taxonomy = const {};
  List<String> _categories = [];
  Timer? _searchDebounce;
  int _catalogRequest = 0;
  int _productPage = 1;
  int _lastProductPage = 1;
  bool _refreshingProducts = false;
  bool _appendRequest = false;
  String? _catalogError;
  Future<_MarketplaceBundle>? _bundleFuture;
  _MarketplaceBundle? _bundle;
  bool _busy = false;
  bool _initialProductScheduled = false;
  late final TextEditingController _searchController;
  AirmiusApiClient? _guestClient;

  @override
  void initState() {
    super.initState();
    _section =
        const {
          'products',
          'wishlist',
          'cart',
          'orders',
        }.contains(widget.initialSection)
        ? widget.initialSection
        : 'products';
    _query = widget.initialQuery;
    _searchController = TextEditingController(text: _query);
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _catalogRequest++;
    _searchController.dispose();
    super.dispose();
  }

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    if (widget.guest) {
      return _guestClient ??= AirmiusApiClient(
        transport: services.guestMarketplaceTransport,
        baseUrl: services.environment.apiBaseUrl,
        locale: services.environment.locale,
      );
    }
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _bundleFuture ??= _load();
    final productId = widget.initialProductId;
    if (!_initialProductScheduled && productId != null && productId > 0) {
      _initialProductScheduled = true;
      WidgetsBinding.instance.addPostFrameCallback((_) async {
        if (!mounted) return;
        await _openProduct({
          'id': productId,
          'title': widget.initialProductTitle,
        });
      });
    }
  }

  Future<_MarketplaceBundle> _load() async {
    final generation = ++_catalogRequest;
    final responses = await Future.wait([
      _requestProducts(),
      _client.commerceWishlist(),
      _client.commerceOrders(),
      _client.commerceCart(),
    ]);
    final checkout = _marketMap(responses[3]['data']);
    final productResponse = responses[0];
    final useCatalog = generation == _catalogRequest;
    if (useCatalog) {
      _readProductMeta(productResponse, 1);
      _refreshingProducts = false;
      _appendRequest = false;
      _catalogError = null;
    }
    final bundle = _MarketplaceBundle(
      products: useCatalog
          ? _marketMaps(productResponse['data'])
          : (_bundle?.products ?? []),
      productTotal: useCatalog
          ? (_marketMap(productResponse['meta'])['total'] == null
                ? _marketMaps(productResponse['data']).length
                : _marketInt(_marketMap(productResponse['meta'])['total']))
          : (_bundle?.productTotal ?? 0),
      wishlist: _marketMaps(responses[1]['data']),
      orders: _marketMaps(responses[2]['data']),
      cart: _marketMap(checkout['cart']),
      checkout: checkout,
    );
    _bundle = bundle;
    return bundle;
  }

  Future<JsonMap> _requestProducts({int page = 1}) => _client.commerceProducts(
    page: page,
    perPage: 24,
    search: _query,
    category: _filters.category,
    catalogGroup: _filters.catalogGroup,
    catalogSubcategory: _filters.catalogSubcategory,
    sport: _filters.sport,
    minPriceCents: _filters.minPriceCents,
    maxPriceCents: _filters.maxPriceCents,
    minRating: _filters.minRating,
    sort: _filters.sort,
  );

  void _readProductMeta(JsonMap response, int page) {
    final meta = _marketMap(response['meta']);
    _productPage = page;
    _lastProductPage = _marketInt(meta['last_page']).clamp(1, 1000000);
    final facets = _marketMap(meta['filters']);
    _taxonomy = _marketMap(facets['taxonomy']);
    if (facets['categories'] is List) {
      _categories =
          (facets['categories'] as List)
              .whereType<String>()
              .where((v) => v.isNotEmpty)
              .toSet()
              .toList()
            ..sort();
    } else if (_categories.isEmpty) {
      _categories =
          _marketMaps(response['data'])
              .map((p) => _marketText(p['category']))
              .where((v) => v.isNotEmpty)
              .toSet()
              .toList()
            ..sort();
    }
  }

  void _onSearchChanged(String value) {
    setState(() => _query = value);
    _searchDebounce?.cancel();
    if (_section != 'products') return;
    setState(() {
      _refreshingProducts = true;
      _appendRequest = false;
      _catalogError = null;
    });
    // Invalidate an older response as soon as the query changes, not after debounce.
    _catalogRequest++;
    _searchDebounce = Timer(const Duration(milliseconds: 350), () {
      if (mounted) _fetchProducts();
    });
  }

  Future<void> _fetchProducts({bool append = false}) async {
    _searchDebounce?.cancel();
    final generation = ++_catalogRequest;
    final page = append ? _productPage + 1 : 1;
    setState(() {
      _refreshingProducts = true;
      _appendRequest = append;
      _catalogError = null;
    });
    try {
      final response = await _requestProducts(page: page);
      if (!mounted || generation != _catalogRequest) return;
      final previous = _bundle ?? const _MarketplaceBundle();
      final items = _marketMaps(response['data']);
      _readProductMeta(response, page);
      final products = append ? [...previous.products, ...items] : items;
      final meta = _marketMap(response['meta']);
      _commitBundle(
        previous.copyWith(
          products: products,
          productTotal: meta['total'] == null
              ? products.length
              : _marketInt(meta['total']),
        ),
      );
    } catch (_) {
      if (mounted && generation == _catalogRequest) {
        setState(() => _catalogError = marketplaceFilterText(context, 'error'));
      }
    } finally {
      if (mounted && generation == _catalogRequest) {
        setState(() => _refreshingProducts = false);
      }
    }
  }

  Future<void> _showFilters() async {
    FocusScope.of(context).unfocus();
    final selected = await showModalBottomSheet<MarketplaceFilters>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      constraints: const BoxConstraints(maxWidth: 640),
      builder: (_) => MarketplaceFiltersSheet(
        initial: _filters,
        categories: _categories,
        taxonomy: _taxonomy,
      ),
    );
    if (!mounted || selected == null || selected == _filters) return;
    _setFilters(selected);
  }

  void _setFilters(MarketplaceFilters filters) {
    setState(() => _filters = filters);
    _fetchProducts();
  }

  Widget _activeFilters() {
    final chips = <Widget>[];
    void chip(String label, MarketplaceFilters next) {
      chips.add(
        InputChip(
          label: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: MediaQuery.sizeOf(context).width * .65,
            ),
            child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
          ),
          tooltip: label,
          onDeleted: () => _setFilters(next),
        ),
      );
    }

    String t(String key) => marketplaceFilterText(context, key);
    final format = NumberFormat(
      '0.##',
      AirmiusScope.of(context).language.locale.languageCode,
    );
    if (_filters.category.isNotEmpty) {
      chip(
        marketplaceCategoryLabel(context, _filters.category),
        _filters.copyWith(category: ''),
      );
    }
    if (_filters.catalogGroup.isNotEmpty || _filters.sport.isNotEmpty) {
      final group = _marketMap(
        _marketMap(_taxonomy['groups'])[_filters.catalogGroup],
      );
      final labels = [
        _marketText(group['label']),
        _marketText(_marketMap(group['children'])[_filters.catalogSubcategory]),
        _marketText(_marketMap(_taxonomy['sports'])[_filters.sport]),
      ].where((label) => label.isNotEmpty);
      if (labels.isNotEmpty) {
        chip(labels.join(' · '), _filters.copyWith(clearTaxonomy: true));
      }
    }
    if (_filters.minPriceCents != null || _filters.maxPriceCents != null) {
      final min = _filters.minPriceCents == null
          ? null
          : format.format(_filters.minPriceCents! / 100);
      final max = _filters.maxPriceCents == null
          ? null
          : format.format(_filters.maxPriceCents! / 100);
      chip(
        min != null && max != null
            ? '$min – $max'
            : min != null
            ? t('priceFrom').replaceAll('{price}', min)
            : t('priceTo').replaceAll('{price}', max!),
        _filters.copyWith(clearPrice: true),
      );
    }
    if (_filters.minRating > 0) {
      chip(
        t('stars').replaceAll('{rating}', format.format(_filters.minRating)),
        _filters.copyWith(minRating: 0),
      );
    }
    if (_filters.sort != 'newest') {
      chip(t(_filters.sort), _filters.copyWith(sort: 'newest'));
    }
    return Wrap(
      spacing: 8,
      runSpacing: 4,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        ...chips,
        TextButton(
          onPressed: () => _setFilters(const MarketplaceFilters()),
          child: Text(t('clear')),
        ),
      ],
    );
  }

  void _commitBundle(_MarketplaceBundle bundle) {
    _bundle = bundle;
    setState(() {
      _bundleFuture = Future.value(bundle);
    });
  }

  void _reload() {
    setState(() {
      _bundleFuture = _load();
    });
  }

  Future<bool> _runCart(
    Future<AirmiusJson> Function() action, {
    String? successMessage,
  }) async {
    if (_busy) return false;
    setState(() => _busy = true);
    try {
      final response = await action();
      if (!mounted) return false;
      final current = _bundle;
      if (current != null) {
        _commitBundle(current.copyWith(cart: _marketMap(response['data'])));
      }
      if (successMessage != null) _toast(successMessage);
      return true;
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
      return false;
    } catch (_) {
      if (mounted) _toast(AirmiusScope.of(context).t('common.errorDetails'));
      return false;
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
    return Scaffold(
      backgroundColor: _marketBackground,
      body: SafeArea(
        child: FutureBuilder<_MarketplaceBundle>(
          future: _bundleFuture,
          builder: (context, snapshot) {
            if (_bundle != null) return _buildContent(_bundle!);
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
      if (_section == 'products' || normalized.isEmpty) return true;
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

    if (_section == 'products') {
      final resultCount = bundle.productTotal;
      return _buildStorefront(products, cartCount, resultCount);
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 28),
      children: [
        if (_section == 'wishlist') ...[
          _MarketplaceSectionToolbar(
            onSections: () => _showSectionPicker(cartCount),
            onReload: _busy ? null : _reload,
          ),
          const SizedBox(height: 12),
          SearchBox(hint: t('market.search'), onChanged: _onSearchChanged),
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
        ] else if (_section == 'cart') ...[
          _MarketplaceSectionToolbar(
            onSections: () => _showSectionPicker(cartCount),
            onReload: _busy ? null : _reload,
          ),
          const SizedBox(height: 12),
          _CartSection(
            cart: bundle.cart,
            busy: _busy,
            onQuantityChanged: _updateCartQuantity,
            onRemove: _removeCartItem,
            onCheckout: () => _openCheckout(bundle),
          ),
        ] else if (bundle.orders.isEmpty) ...[
          _MarketplaceSectionToolbar(
            onSections: () => _showSectionPicker(cartCount),
            onReload: _busy ? null : _reload,
          ),
          const SizedBox(height: 12),
          _MarketEmpty(
            icon: Icons.receipt_long_outlined,
            text: t('market.emptyOrders'),
          ),
        ] else ...[
          _MarketplaceSectionToolbar(
            onSections: () => _showSectionPicker(cartCount),
            onReload: _busy ? null : _reload,
          ),
          const SizedBox(height: 12),
          for (final order in bundle.orders) ...[
            _OrderCard(order: order, onTap: () => _openOrder(order)),
            const SizedBox(height: 10),
          ],
        ],
      ],
    );
  }

  Widget _buildStorefront(
    List<JsonMap> products,
    int cartCount,
    int resultCount,
  ) {
    final t = AirmiusScope.of(context).t;
    return ListView(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 28),
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                t('market.title'),
                style: const TextStyle(
                  color: _marketInk,
                  fontSize: 24,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            IconButton(
              tooltip: marketplaceFilterText(context, 'sections'),
              icon: const Icon(Icons.apps_outlined, color: _marketInk),
              onPressed: () => _showSectionPicker(cartCount),
            ),
          ],
        ),
        const SizedBox(height: 10),
        _MarketplaceSearchBar(
          controller: _searchController,
          hint: t('market.search'),
          onChanged: _onSearchChanged,
          onFilter: _showFilters,
          activeFilterCount: _filters.activeCount,
          cartCount: cartCount,
          onCart: () => setState(() => _section = 'cart'),
        ),
        const SizedBox(height: 10),
        if (_catalogError == null || _appendRequest)
          Text(
            _refreshingProducts && !_appendRequest
                ? marketplaceFilterText(context, 'loading')
                : '$resultCount ${t('market.results')}',
            style: const TextStyle(
              color: _marketBlue,
              fontSize: 12,
              fontWeight: FontWeight.w900,
            ),
          ),
        if (_filters.activeCount > 0) ...[
          const SizedBox(height: 8),
          _activeFilters(),
        ],
        if (_refreshingProducts && !_appendRequest) ...[
          const SizedBox(height: 12),
          LinearProgressIndicator(
            semanticsLabel: marketplaceFilterText(context, 'loading'),
          ),
        ],
        if (_catalogError != null && !_appendRequest) _catalogFailure(),
        const SizedBox(height: 14),
        if ((!_refreshingProducts && _catalogError == null) ||
            _appendRequest) ...[
          if (products.isEmpty)
            _MarketEmpty(
              icon: Icons.storefront_outlined,
              text: t('market.emptyProducts'),
            )
          else ...[
            SizedBox(
              height: 174 + _marketExtraTextHeight(context),
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: products.length,
                separatorBuilder: (_, _) => const SizedBox(width: 12),
                itemBuilder: (context, index) => _MarketplaceHeroCard(
                  product: products[index],
                  onTap: () => _openProduct(products[index]),
                ),
              ),
            ),
            const SizedBox(height: 18),
            const _MarketplaceOfferHeader(),
            const SizedBox(height: 10),
            SizedBox(
              height: 260 + _marketExtraTextHeight(context),
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: products.length,
                separatorBuilder: (_, _) => const SizedBox(width: 10),
                itemBuilder: (context, index) => _MarketplaceProductTile(
                  product: products[index],
                  onTap: () => _openProduct(products[index]),
                  onAddToCart: () => _addToCart(products[index]),
                  onToggleWishlist: () => _toggleWishlist(products[index]),
                  busy: _busy,
                ),
              ),
            ),
            const SizedBox(height: 18),
            _MarketplaceGrid(
              products: products,
              busy: _busy,
              onOpen: (product) => _openProduct(product),
              onAddToCart: (product) => _addToCart(product),
              onToggleWishlist: _toggleWishlist,
            ),
          ],
        ],
        if (_catalogError != null && _appendRequest) _catalogFailure(),
        if (_productPage < _lastProductPage &&
            _catalogError == null &&
            (!_refreshingProducts || _appendRequest))
          Padding(
            padding: const EdgeInsets.only(top: 20),
            child: OutlinedButton.icon(
              onPressed: _refreshingProducts
                  ? null
                  : () => _fetchProducts(append: true),
              style: OutlinedButton.styleFrom(minimumSize: const Size(0, 52)),
              icon: _refreshingProducts
                  ? SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        semanticsLabel: marketplaceFilterText(
                          context,
                          'loading',
                        ),
                      ),
                    )
                  : const Icon(Icons.expand_more),
              label: Text(
                marketplaceFilterText(
                  context,
                  _refreshingProducts ? 'loading' : 'more',
                ),
              ),
            ),
          ),
      ],
    );
  }

  Widget _catalogFailure() => Semantics(
    liveRegion: true,
    child: Padding(
      padding: const EdgeInsets.only(top: 16),
      child: Column(
        children: [
          Text(
            _catalogError!,
            style: const TextStyle(color: _marketInk),
            textAlign: TextAlign.center,
          ),
          TextButton.icon(
            onPressed: () => _fetchProducts(append: _appendRequest),
            style: TextButton.styleFrom(minimumSize: const Size(0, 52)),
            icon: const Icon(Icons.refresh),
            label: Text(marketplaceFilterText(context, 'retry')),
          ),
        ],
      ),
    ),
  );

  Future<void> _showSectionPicker(int cartCount) async {
    final selected = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.white,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 14, 18, 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                AirmiusScope.of(context).t('market.title'),
                style: const TextStyle(
                  color: _marketInk,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 10),
              _MarketSectionPicker(
                value: _section,
                allowBulkOrders: !widget.guest,
                cartCount: cartCount,
                onChanged: (value) => Navigator.pop(context, value),
              ),
            ],
          ),
        ),
      ),
    );
    if (selected == null || !mounted) return;
    if (selected == 'bulk_orders') {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(builder: (_) => const TeamBulkOrdersScreen()),
      );
      if (mounted) _reload();
      return;
    }
    setState(() => _section = selected);
    if (selected == 'products') _fetchProducts();
  }

  Future<bool?> _toggleWishlist(JsonMap product) async {
    if (_busy) return null;
    final id = _marketInt(product['id']);
    if (id <= 0) return null;

    final t = AirmiusScope.of(context).t;
    final active = _isWishlisted(product);
    setState(() => _busy = true);
    try {
      final response = active
          ? await _client.removeCommerceWishlist(id)
          : await _client.addCommerceWishlist(id);
      if (!mounted) return null;

      final data = _marketMap(response['data']);
      final saved = _marketBool(data['is_wishlisted']);
      JsonMap updateProduct(JsonMap item) => {
        ...item,
        'viewer': {..._marketMap(item['viewer']), 'is_wishlisted': saved},
        'wishlist_summary': {
          ..._marketMap(item['wishlist_summary']),
          'count': _marketInt(data['wishlist_count']),
        },
      };

      final current = _bundle;
      if (current != null) {
        _commitBundle(
          current.copyWith(
            products: [
              for (final item in current.products)
                _marketInt(item['id']) == id ? updateProduct(item) : item,
            ],
            wishlist: [
              if (saved) updateProduct(product),
              for (final item in current.wishlist)
                if (_marketInt(item['id']) != id) item,
            ],
          ),
        );
      }
      _toast(t(saved ? 'market.wishlistAdded' : 'market.wishlistRemoved'));
      return saved;
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
      return null;
    } catch (_) {
      if (mounted) _toast(t('common.errorDetails'));
      return null;
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<bool> _addToCart(
    JsonMap product, {
    int quantity = 1,
    bool showSuccessMessage = true,
  }) async {
    final t = AirmiusScope.of(context).t;
    return _runCart(
      () => _client.addCommerceCartItem(
        _marketInt(product['id']),
        quantity: quantity,
      ),
      successMessage: showSuccessMessage ? t('market.cartAdded') : null,
    );
  }

  Future<void> _updateCartQuantity(int itemId, int quantity) async {
    await _runCart(
      () => _client.updateCommerceCartItem(itemId, quantity: quantity),
    );
  }

  Future<void> _removeCartItem(int itemId) async {
    final t = AirmiusScope.of(context).t;
    await _runCart(
      () => _client.removeCommerceCartItem(itemId),
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
        onAddToCart: (item, quantity) =>
            _addToCart(item, quantity: quantity, showSuccessMessage: false),
        onToggleWishlist: _toggleWishlist,
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
        guest: widget.guest,
      ),
    );
    if (order == null || !mounted) return;
    final current = _bundle;
    if (current == null) {
      setState(() => _section = 'orders');
      _reload();
    } else {
      final oldSummary = _marketMap(current.cart['summary']);
      _section = 'orders';
      _commitBundle(
        current.copyWith(
          orders: [order, ...current.orders],
          cart: {
            'items': <JsonMap>[],
            'items_count': 0,
            'summary': {
              ...oldSummary,
              'item_gross_cents': 0,
              'shipping_cents': 0,
              'tax_cents': 0,
              'amount_cents': 0,
            },
          },
        ),
      );
    }

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
    this.productTotal = 0,
    this.wishlist = const [],
    this.orders = const [],
    this.cart = const {},
    this.checkout = const {},
  });

  final List<JsonMap> products;
  final int productTotal;
  final List<JsonMap> wishlist;
  final List<JsonMap> orders;
  final JsonMap cart;
  final JsonMap checkout;

  _MarketplaceBundle copyWith({
    List<JsonMap>? products,
    int? productTotal,
    List<JsonMap>? wishlist,
    List<JsonMap>? orders,
    JsonMap? cart,
    JsonMap? checkout,
  }) => _MarketplaceBundle(
    products: products ?? this.products,
    productTotal: productTotal ?? this.productTotal,
    wishlist: wishlist ?? this.wishlist,
    orders: orders ?? this.orders,
    cart: cart ?? this.cart,
    checkout: checkout ?? this.checkout,
  );
}

double _marketExtraTextHeight(BuildContext context) =>
    ((MediaQuery.textScalerOf(context).scale(14) / 14 - 1).clamp(0.0, 3.0)) *
    120;

const _marketBackground = Color(0xFFF5FAFF);
const _marketBlue = Color(0xFF0EA5E9);
const _marketInk = Color(0xFF142033);
const _marketMuted = Color(0xFF64748B);

class _MarketplaceSectionToolbar extends StatelessWidget {
  const _MarketplaceSectionToolbar({
    required this.onSections,
    required this.onReload,
  });

  final VoidCallback onSections;
  final VoidCallback? onReload;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 48,
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFD9E7F2)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x120B3555),
            blurRadius: 8,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              AirmiusScope.of(context).t('market.title'),
              style: const TextStyle(
                color: _marketInk,
                fontSize: 16,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          IconButton(
            tooltip: 'Bereiche',
            onPressed: onSections,
            color: _marketInk,
            icon: const Icon(Icons.tune_outlined),
          ),
          IconButton(
            tooltip: 'Neu laden',
            onPressed: onReload,
            color: _marketMuted,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
    );
  }
}

class _MarketplaceSearchBar extends StatelessWidget {
  const _MarketplaceSearchBar({
    required this.controller,
    required this.hint,
    required this.onChanged,
    required this.onFilter,
    this.activeFilterCount = 0,
    required this.cartCount,
    required this.onCart,
  });

  final TextEditingController controller;
  final String hint;
  final ValueChanged<String> onChanged;
  final VoidCallback onFilter;
  final int activeFilterCount;
  final int cartCount;
  final VoidCallback onCart;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFD9E7F2)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0F0B3555),
            blurRadius: 8,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: controller,
              onChanged: onChanged,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => FocusScope.of(context).unfocus(),
              style: const TextStyle(
                color: _marketInk,
                fontSize: 14,
                fontWeight: FontWeight.w700,
              ),
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search, color: _marketBlue),
                hintText: hint,
                suffixIcon: controller.text.isEmpty
                    ? null
                    : IconButton(
                        tooltip: marketplaceFilterText(context, 'clearSearch'),
                        onPressed: () {
                          controller.clear();
                          onChanged('');
                        },
                        icon: const Icon(Icons.close, size: 20),
                      ),

                hintStyle: const TextStyle(color: _marketMuted),
                filled: true,
                fillColor: const Color(0xFFF9FCFF),
                contentPadding: const EdgeInsets.symmetric(vertical: 12),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: Color(0xFFD9E7F2)),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: Color(0xFFD9E7F2)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: _marketBlue, width: 1.5),
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),
          Material(
            color: const Color(0xFFF9FCFF),
            borderRadius: BorderRadius.circular(11),
            child: IconButton(
              tooltip:
                  '${AirmiusScope.of(context).t('market.cart')} ($cartCount)',
              onPressed: onCart,
              color: _marketBlue,
              icon: Badge.count(
                count: cartCount,
                isLabelVisible: cartCount > 0,
                child: const Icon(Icons.shopping_cart_outlined),
              ),
            ),
          ),
          const SizedBox(width: 8),
          Material(
            color: const Color(0xFFF9FCFF),
            borderRadius: BorderRadius.circular(11),
            child: IconButton(
              tooltip: marketplaceFilterText(context, 'filter'),
              onPressed: onFilter,
              color: _marketInk,
              icon: Badge.count(
                count: activeFilterCount,
                isLabelVisible: activeFilterCount > 0,
                child: Icon(
                  Icons.tune_outlined,
                  color: activeFilterCount > 0 ? _marketBlue : _marketInk,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MarketplaceHeroCard extends StatelessWidget {
  const _MarketplaceHeroCard({required this.product, required this.onTap});

  final JsonMap product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final imageUrl = _marketNullableText(product['image_url']);
    final title = _marketText(
      product['title'],
      fallback: AirmiusScope.of(context).t('market.title'),
    );
    final category = _marketText(
      product['category'],
      fallback: AirmiusScope.of(context).t('market.product'),
    );

    return SizedBox(
      width: MediaQuery.sizeOf(context).width * .82,
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(16),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Stack(
            fit: StackFit.expand,
            children: [
              ColoredBox(
                color: const Color(0xFFB90022),
                child: imageUrl == null
                    ? const Icon(
                        Icons.shopping_bag_outlined,
                        color: Colors.white,
                        size: 54,
                      )
                    : Image.network(
                        resolveAirmiusImageUrl(imageUrl) ?? imageUrl,
                        fit: BoxFit.cover,
                        errorBuilder: (_, _, _) => const SizedBox.shrink(),
                      ),
              ),
              const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [Colors.transparent, Color(0xD9000000)],
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 13, 14, 13),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 5,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: .45),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        'AIRMIUS MARKETPLACE',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 9,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    const SizedBox(height: 7),
                    Text(
                      title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        height: 1.05,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      category,
                      style: const TextStyle(
                        color: Colors.white70,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MarketplaceOfferHeader extends StatelessWidget {
  const _MarketplaceOfferHeader();

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 48),
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
      decoration: BoxDecoration(
        color: _marketBlue,
        borderRadius: BorderRadius.circular(4),
      ),
      child: Row(
        children: [
          const Icon(Icons.bolt, color: Colors.white),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              AirmiusScope.of(context).t('market.currentOffers'),
              style: const TextStyle(
                color: Colors.white,
                fontSize: 17,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MarketplaceProductTile extends StatelessWidget {
  const _MarketplaceProductTile({
    required this.product,
    required this.onTap,
    required this.onAddToCart,
    required this.onToggleWishlist,
    required this.busy,
    this.width,
  });

  final JsonMap product;
  final VoidCallback onTap;
  final VoidCallback onAddToCart;
  final VoidCallback onToggleWishlist;
  final bool busy;
  final double? width;

  @override
  Widget build(BuildContext context) {
    final title = _marketText(
      product['title'],
      fallback: AirmiusScope.of(context).t('market.untitledProduct'),
    );
    final imageUrl = _marketNullableText(product['image_url']);
    final provider = _marketMap(product['provider_profile']);
    final category = _marketText(
      product['category'],
      fallback: AirmiusScope.of(context).t('market.product'),
    );

    return SizedBox(
      width: width ?? 142,
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Container(
            decoration: BoxDecoration(
              border: Border.all(color: const Color(0xFFD9E7F2)),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(
                  height: 124,
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      ColoredBox(
                        color: const Color(0xFFE7F1F8),
                        child: imageUrl == null
                            ? const Icon(
                                Icons.shopping_bag_outlined,
                                color: _marketBlue,
                                size: 42,
                              )
                            : Image.network(
                                resolveAirmiusImageUrl(imageUrl) ?? imageUrl,
                                fit: BoxFit.cover,
                                errorBuilder: (_, _, _) => const Icon(
                                  Icons.broken_image_outlined,
                                  color: _marketMuted,
                                ),
                              ),
                      ),
                      Positioned(
                        left: 7,
                        top: 7,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 6,
                            vertical: 3,
                          ),
                          color: Colors.white.withValues(alpha: .9),
                          child: Text(
                            category,
                            style: const TextStyle(
                              color: _marketBlue,
                              fontSize: 9,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ),
                      PositionedDirectional(
                        end: 4,
                        top: 4,
                        child: _WishlistButton(
                          saved: _isWishlisted(product),
                          onPressed: busy ? null : onToggleWishlist,
                          backgroundColor: Colors.white,
                        ),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(8, 8, 7, 6),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          provider['display_name'] == null
                              ? title
                              : '${provider['display_name']}: $title',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: _marketInk,
                            fontSize: 11,
                            height: 1.25,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const Spacer(),
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                _price(
                                  context,
                                  _marketInt(product['price_cents']),
                                  _marketText(
                                    product['currency'],
                                    fallback: 'EUR',
                                  ),
                                ),
                                style: const TextStyle(
                                  color: _marketInk,
                                  fontSize: 14,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                            IconButton(
                              visualDensity: VisualDensity.compact,
                              padding: EdgeInsets.zero,
                              tooltip: AirmiusScope.of(
                                context,
                              ).t('market.addToCart'),
                              onPressed: busy ? null : onAddToCart,
                              color: _marketBlue,
                              icon: const Icon(
                                Icons.add_shopping_cart_outlined,
                                size: 18,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _MarketplaceGrid extends StatelessWidget {
  const _MarketplaceGrid({
    required this.products,
    required this.busy,
    required this.onOpen,
    required this.onAddToCart,
    required this.onToggleWishlist,
  });

  final List<JsonMap> products;
  final bool busy;
  final ValueChanged<JsonMap> onOpen;
  final ValueChanged<JsonMap> onAddToCart;
  final ValueChanged<JsonMap> onToggleWishlist;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final scale = MediaQuery.textScalerOf(context).scale(14) / 14;
        final columns = (constraints.maxWidth / (scale > 1.3 ? 230 : 170))
            .floor()
            .clamp(1, 4);
        final width = (constraints.maxWidth - (columns - 1) * 10) / columns;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              AirmiusScope.of(context).t('market.products'),
              style: const TextStyle(
                color: _marketInk,
                fontSize: 18,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 10),
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: products.length,
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: columns,
                crossAxisSpacing: 10,
                mainAxisSpacing: 10,
                mainAxisExtent: 260 + _marketExtraTextHeight(context),
              ),
              itemBuilder: (context, index) {
                final product = products[index];
                return _MarketplaceProductTile(
                  product: product,
                  width: width,
                  busy: busy,
                  onTap: () => onOpen(product),
                  onAddToCart: () => onAddToCart(product),
                  onToggleWishlist: () => onToggleWishlist(product),
                );
              },
            ),
          ],
        );
      },
    );
  }
}

class _MarketSectionPicker extends StatelessWidget {
  const _MarketSectionPicker({
    required this.value,
    required this.cartCount,
    required this.onChanged,
    this.allowBulkOrders = true,
  });

  final String value;
  final int cartCount;
  final ValueChanged<String> onChanged;
  final bool allowBulkOrders;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final entries = [
      ('products', Icons.storefront_outlined, t('market.products'), null),
      ('cart', Icons.shopping_cart_outlined, t('market.cart'), cartCount),
      ('wishlist', Icons.favorite_outline, t('market.wishlist'), null),
      ('orders', Icons.receipt_long_outlined, t('market.orders'), null),
      if (allowBulkOrders)
        (
          'bulk_orders',
          Icons.groups_2_outlined,
          Localizations.localeOf(context).languageCode == 'de'
              ? 'Sammelbestellungen'
              : 'Bulk orders',
          null,
        ),
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

bool _isWishlisted(JsonMap product) =>
    _marketBool(_marketMap(product['viewer'])['is_wishlisted']);

class _WishlistButton extends StatelessWidget {
  const _WishlistButton({
    required this.saved,
    required this.onPressed,
    this.backgroundColor,
  });

  final bool saved;
  final VoidCallback? onPressed;
  final Color? backgroundColor;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return IconButton(
      tooltip: t(saved ? 'market.removeWishlist' : 'market.addWishlist'),
      onPressed: onPressed,
      style: IconButton.styleFrom(
        backgroundColor: backgroundColor,
        disabledBackgroundColor: backgroundColor,
      ),
      icon: Icon(
        saved ? Icons.favorite : Icons.favorite_border,
        color: saved
            ? Theme.of(context).colorScheme.error
            : backgroundColor == null
            ? airmiusMutedColor(context)
            : _marketInk,
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
    final provider = _marketMap(product['provider_profile']);
    final trust = _marketMap(product['seller_trust']);
    final availability = _marketMap(product['availability']);
    final reviews = _marketMap(product['review_summary']);
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
              _WishlistButton(
                saved: _isWishlisted(product),
                onPressed: busy ? null : onToggleWishlist,
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
    required this.onToggleWishlist,
    required this.onChanged,
  });

  final AirmiusApiClient client;
  final JsonMap initialProduct;
  final Future<bool> Function(JsonMap product, int quantity) onAddToCart;
  final Future<bool?> Function(JsonMap product) onToggleWishlist;
  final VoidCallback onChanged;

  @override
  State<_ProductDetailSheet> createState() => _ProductDetailSheetState();
}

class _ProductDetailSheetState extends State<_ProductDetailSheet> {
  Future<_ProductBundle>? _future;
  int _quantity = 1;
  bool _busy = false;
  bool _cartAdded = false;
  bool? _wishlistSaved;
  bool _reviewSaved = false;

  Future<void> _toggleWishlist(JsonMap product, bool saved) async {
    setState(() => _busy = true);
    try {
      final result = await widget.onToggleWishlist({
        ...product,
        'viewer': {..._marketMap(product['viewer']), 'is_wishlisted': saved},
      });
      if (mounted && result != null) setState(() => _wishlistSaved = result);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

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
      canReview: _marketMap(responses[0]['meta'])['can_review'] as bool?,
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
          final saved = _wishlistSaved ?? _isWishlisted(product);
          final availability = _marketMap(product['availability']);
          final returnPolicy = _marketMap(product['return_policy']);
          final fulfillment = _marketMap(product['fulfillment']);
          final provider = _marketMap(product['provider_profile']);
          final reviewSummary = _marketMap(product['review_summary']);
          final variants = _marketMaps(product['variants']);
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
                  _WishlistButton(
                    saved: saved,
                    onPressed: _busy
                        ? null
                        : () => _toggleWishlist(product, saved),
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
              if (variants.isNotEmpty) ...[
                const SizedBox(height: 18),
                Text(
                  t('market.variants'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                AirmiusPanel(
                  child: Column(
                    children: [
                      for (var index = 0; index < variants.length; index++) ...[
                        _ProductVariantRow(
                          variant: variants[index],
                          currency: _marketText(
                            product['currency'],
                            fallback: 'EUR',
                          ),
                        ),
                        if (index < variants.length - 1) const Divider(),
                      ],
                    ],
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
                                    setState(() {
                                      _busy = true;
                                      _cartAdded = false;
                                    });
                                    final added = await widget.onAddToCart(
                                      product,
                                      _quantity,
                                    );
                                    if (mounted) {
                                      setState(() {
                                        _busy = false;
                                        _cartAdded = added;
                                      });
                                    }
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
                    if (_cartAdded) ...[
                      const SizedBox(height: 12),
                      Semantics(
                        liveRegion: true,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 10,
                          ),
                          decoration: BoxDecoration(
                            color: AirmiusColors.green.withValues(alpha: .12),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: AirmiusColors.green.withValues(alpha: .45),
                            ),
                          ),
                          child: Row(
                            children: [
                              const Icon(
                                Icons.check_circle_outline,
                                color: AirmiusColors.green,
                              ),
                              const SizedBox(width: 9),
                              Expanded(
                                child: Text(
                                  t('market.cartAdded'),
                                  style: TextStyle(
                                    color: airmiusTextColor(context),
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
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
                  if (bundle.canReview != false)
                    TextButton.icon(
                      onPressed: () => _writeReview(product),
                      icon: Icon(Icons.rate_review_outlined),
                      label: Text(t('market.writeReview')),
                    ),
                ],
              ),
              if (bundle.canReview == false)
                Text(
                  t('market.reviewPurchaseRequired'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
              if (_reviewSaved)
                Semantics(
                  liveRegion: true,
                  child: Text(t('market.reviewSaved')),
                ),
              if (bundle.reviews.isEmpty && !_reviewSaved)
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
    final saved = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (_) => _ReviewDialog(
        onSave: (draft) async {
          await widget.client.createCommerceProductReview(
            _marketInt(product['id']),
            rating: draft.rating,
            title: draft.title,
            body: draft.body,
          );
        },
      ),
    );
    if (saved != true || !mounted) return;
    setState(() => _reviewSaved = true);
    widget.onChanged();
    _reload();
  }
}

class _ProductBundle {
  const _ProductBundle({
    required this.product,
    required this.reviews,
    this.canReview,
  });

  final JsonMap product;
  final List<JsonMap> reviews;
  final bool? canReview;
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
  const _ReviewDialog({required this.onSave});

  final Future<void> Function(_ReviewDraft) onSave;

  @override
  State<_ReviewDialog> createState() => _ReviewDialogState();
}

class _ReviewDialogState extends State<_ReviewDialog> {
  int _rating = 5;
  bool _saving = false;
  String? _error;
  final _title = TextEditingController();
  final _body = TextEditingController();

  Future<void> _save() async {
    if (_saving) return;
    FocusScope.of(context).unfocus();
    final genericError = AirmiusScope.of(context).t('common.errorDetails');
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.onSave(
        _ReviewDraft(rating: _rating, title: _title.text, body: _body.text),
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is AirmiusApiException
            ? error.userMessage
            : genericError;
        _saving = false;
      });
    }
  }

  @override
  void dispose() {
    _title.dispose();
    _body.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return PopScope(
      canPop: !_saving,
      child: AlertDialog(
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
                        onPressed: _saving
                            ? null
                            : () => setState(() => _rating = index),
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
                enabled: !_saving,
                maxLength: 160,
                decoration: InputDecoration(labelText: t('market.reviewTitle')),
              ),
              TextField(
                controller: _body,
                enabled: !_saving,
                minLines: 3,
                maxLines: 6,
                maxLength: 2000,
                decoration: InputDecoration(labelText: t('market.reviewBody')),
              ),
              if (_error case final message?)
                Semantics(
                  liveRegion: true,
                  child: Text(
                    message,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: _saving ? null : () => Navigator.pop(context),
            child: Text(t('cancel')),
          ),
          FilledButton(
            onPressed: _saving ? null : _save,
            child: _saving
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(t('save')),
          ),
        ],
      ),
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

class _ProductVariantRow extends StatelessWidget {
  const _ProductVariantRow({required this.variant, required this.currency});

  final JsonMap variant;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final attributes = _marketMaps(variant['attributes']);
    final stock = _marketInt(variant['stock_quantity']);
    final attributeLabel = attributes
        .map(
          (attribute) =>
              '${_marketText(attribute['name'])}: ${_marketText(attribute['value'])}',
        )
        .where((label) => !label.startsWith(':'))
        .join(' · ');

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  attributeLabel.isEmpty
                      ? t('market.variants')
                      : attributeLabel,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '${t('market.sku')}: ${_marketText(variant['sku'], fallback: '-')} · ${t('market.stock')}: $stock',
                  style: TextStyle(
                    color: stock > 0
                        ? airmiusMutedColor(context)
                        : Theme.of(context).colorScheme.error,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Text(
            _price(context, _marketInt(variant['price_cents']), currency),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
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
    this.guest = false,
  });

  final AirmiusApiClient client;
  final JsonMap cart;
  final JsonMap checkout;
  final bool guest;

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
  final _coupon = TextEditingController();
  final _guestName = TextEditingController();
  final _guestEmail = TextEditingController();
  bool get _guest => widget.guest;
  String? _appliedCoupon;
  String? _couponError;
  String _provider = 'bank_transfer';
  String _customerType = 'consumer';
  bool _acceptedTerms = false;
  bool _saveAddress = false;
  bool _busy = false;
  bool _quoteBusy = false;
  bool _termsError = false;
  bool _feedbackIsError = false;
  String? _feedbackMessage;
  late JsonMap _previewCart;

  @override
  void initState() {
    super.initState();
    _previewCart = widget.cart;
    final providers = widget.checkout['payment_providers'];
    if (providers is List && providers.isNotEmpty) {
      _provider = providers.first.toString();
    }
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
    _coupon.dispose();
    _guestName.dispose();
    _guestEmail.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final summary = _marketMap(_previewCart['summary']);
    final pricingCountries = _marketMaps(widget.checkout['pricing_countries']);
    final countryCodes = pricingCountries
        .map((country) => _marketText(country['country']))
        .where((country) => country.isNotEmpty)
        .toSet();
    final feedbackColor = _feedbackIsError
        ? Theme.of(context).colorScheme.error
        : AirmiusColors.green;
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: .94,
      minChildSize: .65,
      maxChildSize: .98,
      builder: (context, controller) => AirmiusForm(
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
                  width: 116,
                  child: countryCodes.contains(_country.text.toUpperCase())
                      ? DropdownButtonFormField<String>(
                          initialValue: _country.text.toUpperCase(),
                          isExpanded: true,
                          decoration: InputDecoration(
                            hintText: t('market.country'),
                            prefixIcon: const Icon(Icons.public, size: 20),
                            prefixIconConstraints: const BoxConstraints(
                              minWidth: 38,
                            ),
                          ),
                          items: [
                            for (final code in countryCodes)
                              DropdownMenuItem(value: code, child: Text(code)),
                          ],
                          onChanged: _quoteBusy
                              ? null
                              : (value) {
                                  if (value == null) return;
                                  _country.text = value;
                                  _refreshQuote();
                                },
                        )
                      : TextFormField(
                          controller: _country,
                          textCapitalization: TextCapitalization.characters,
                          maxLength: 2,
                          decoration: InputDecoration(
                            hintText: t('market.country'),
                            prefixIcon: const Icon(Icons.public, size: 20),
                            prefixIconConstraints: const BoxConstraints(
                              minWidth: 38,
                            ),
                            counterText: '',
                          ),
                          validator: (value) => value?.trim().length == 2
                              ? null
                              : t('market.required'),
                        ),
                ),
              ],
            ),
            if (_guest) ...[
              const SizedBox(height: 10),
              TextFormField(
                controller: _guestName,
                autofillHints: const [AutofillHints.name],
                decoration: InputDecoration(labelText: t('application.name')),
                validator: (value) => value == null || value.trim().isEmpty
                    ? t('market.required')
                    : null,
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _guestEmail,
                keyboardType: TextInputType.emailAddress,
                autofillHints: const [AutofillHints.email],
                decoration: InputDecoration(labelText: t('application.email')),
                validator: (value) =>
                    value != null &&
                        RegExp(
                          r'^[^\s@]+@[^\s@]+\.[^\s@]+$',
                        ).hasMatch(value.trim())
                    ? null
                    : t('market.required'),
              ),
              const SizedBox(height: 12),
            ],
            if (!_guest)
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
              onSelectionChanged: _quoteBusy
                  ? null
                  : (value) {
                      setState(() => _customerType = value.first);
                      _refreshQuote();
                    },
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
            const SizedBox(height: 10),
            OutlinedButton.icon(
              onPressed: _quoteBusy ? null : _refreshQuote,
              icon: _quoteBusy
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.calculate_outlined),
              label: Text('${t('market.total')} · ${t('common.refresh')}'),
            ),
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
                  for (final option
                      in const [
                        ('bank_transfer', Icons.account_balance_outlined),
                        ('stripe', Icons.credit_card_outlined),
                        ('paypal', Icons.account_balance_wallet_outlined),
                      ].where(
                        (option) =>
                            !_guest ||
                            (widget.checkout['payment_providers'] as List? ??
                                    [])
                                .contains(option.$1),
                      ))
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
            TextField(
              controller: _coupon,
              enabled: !_busy && !_quoteBusy,
              textCapitalization: TextCapitalization.characters,
              textInputAction: TextInputAction.done,
              maxLength: 80,
              onSubmitted: (_) => _applyCoupon(),
              decoration: InputDecoration(
                labelText: t('market.couponCode'),
                errorText: _couponError,
              ),
            ),
            Wrap(
              spacing: 8,
              children: [
                OutlinedButton(
                  onPressed: _busy || _quoteBusy ? null : _applyCoupon,
                  child: Text(t('market.applyCoupon')),
                ),
                if (_appliedCoupon != null)
                  TextButton(
                    onPressed: _busy || _quoteBusy
                        ? null
                        : () => _refreshQuote(couponChange: ''),
                    child: Text(t('market.removeCoupon')),
                  ),
              ],
            ),
            if (_quoteBusy) const LinearProgressIndicator(),
            if (_appliedCoupon case final code?)
              Semantics(
                liveRegion: true,
                child: Text('${t('market.couponApplied')} $code'),
              ),
            const SizedBox(height: 8),
            AirmiusPanel(
              borderColor: _termsError
                  ? Theme.of(context).colorScheme.error
                  : null,
              child: Column(
                children: [
                  _AmountRow(
                    label: t('market.itemsSubtotal'),
                    cents:
                        _marketInt(summary['item_gross_cents']) +
                        _marketInt(summary['discount_cents']),
                    currency: _marketText(summary['currency'], fallback: 'EUR'),
                  ),
                  if (_marketInt(summary['discount_cents']) > 0)
                    _AmountRow(
                      label: t('market.couponDiscount'),
                      cents: -_marketInt(summary['discount_cents']),
                      currency: _marketText(
                        summary['currency'],
                        fallback: 'EUR',
                      ),
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
                  const Divider(),
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
                    onChanged: (value) => setState(() {
                      _acceptedTerms = value ?? false;
                      if (_acceptedTerms && _termsError) {
                        _termsError = false;
                        _feedbackMessage = null;
                      }
                    }),
                    title: Text(t('market.acceptTerms')),
                    subtitle: Text(t('market.acceptTermsHint')),
                    controlAffinity: ListTileControlAffinity.leading,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            if (_feedbackMessage != null) ...[
              Semantics(
                liveRegion: true,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 11,
                  ),
                  decoration: BoxDecoration(
                    color: feedbackColor.withValues(alpha: .12),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: feedbackColor.withValues(alpha: .55),
                    ),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        _feedbackIsError
                            ? Icons.error_outline
                            : Icons.check_circle_outline,
                        color: feedbackColor,
                      ),
                      const SizedBox(width: 9),
                      Expanded(
                        child: Text(
                          _feedbackMessage!,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontWeight: FontWeight.w800,
                            height: 1.35,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 10),
            ],
            FilledButton.icon(
              onPressed: _busy || _quoteBusy ? null : _submit,
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

  void _applyCoupon() {
    final code = _coupon.text.trim();
    if (code.isEmpty) {
      setState(
        () => _couponError = AirmiusScope.of(context).t('market.required'),
      );
      return;
    }
    _refreshQuote(couponChange: code);
  }

  Future<void> _refreshQuote({String? couponChange}) async {
    if (_busy || _quoteBusy) return;
    final couponCode = couponChange ?? _appliedCoupon;
    final t = AirmiusScope.of(context).t;
    setState(() {
      _quoteBusy = true;
      _feedbackMessage = null;
      _couponError = null;
    });
    try {
      final response = await widget.client.commerceCart(
        couponCode: couponCode,
        shippingCountry: _country.text,
        shippingPostalCode: _postalCode.text,
        shippingCity: _city.text,
        customerType: _customerType,
        customerCompany: _company.text,
        customerVatId: _vatId.text,
      );
      if (!mounted) return;
      final data = _marketMap(response['data']);
      final cart = _marketMap(data['cart']);
      final applied = _marketText(_marketMap(cart['summary'])['coupon_code']);
      if (couponCode?.isNotEmpty == true && applied.isEmpty) {
        setState(() => _couponError = t('market.couponUnavailable'));
        return;
      }
      setState(() {
        _previewCart = cart;
        _appliedCoupon = applied.isEmpty ? null : applied;
        if (couponChange != null) _coupon.text = applied;
        _feedbackIsError = false;
      });
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _feedbackIsError = true;
        if (couponChange != null) {
          _couponError = error.userMessage;
        } else {
          _feedbackMessage = error.userMessage;
        }
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _feedbackIsError = true;
        if (couponChange != null) {
          _couponError = t('common.errorDetails');
        } else {
          _feedbackMessage = t('common.errorDetails');
        }
      });
    } finally {
      if (mounted) setState(() => _quoteBusy = false);
    }
  }

  Future<void> _submit() async {
    final t = AirmiusScope.of(context).t;
    final enteredCoupon = _coupon.text.trim();
    if (enteredCoupon.isNotEmpty &&
        enteredCoupon.toUpperCase() != _appliedCoupon) {
      setState(() => _couponError = t('market.applyCouponFirst'));
      return;
    }
    final fieldsValid = _formKey.currentState!.validate();
    if (!_acceptedTerms) {
      setState(() {
        _termsError = true;
        _feedbackIsError = true;
        _feedbackMessage = t('market.acceptTermsRequired');
      });
      return;
    }
    if (!fieldsValid) {
      setState(() {
        _termsError = false;
        _feedbackIsError = true;
        _feedbackMessage = t('market.checkoutFieldsRequired');
      });
      return;
    }
    setState(() {
      _busy = true;
      _termsError = false;
      _feedbackMessage = null;
    });
    try {
      final response = await widget.client.checkoutCommerceCart({
        if (_guest) 'guest_name': _guestName.text.trim(),
        if (_guest) 'guest_email': _guestEmail.text.trim(),
        if (_appliedCoupon != null) 'coupon_code': _appliedCoupon,
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
      setState(() {
        _feedbackIsError = false;
        _feedbackMessage = t('market.orderCreated');
      });
      await Future<void>.delayed(const Duration(milliseconds: 900));
      if (!mounted) return;
      Navigator.pop(context, _marketMap(response['data']));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _feedbackIsError = true;
        _feedbackMessage = error.userMessage;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _feedbackIsError = true;
        _feedbackMessage = t('common.errorDetails');
      });
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
    final currency = _marketText(order['currency'], fallback: 'EUR');
    final items = _marketMaps(order['items']);
    final support = _marketMap(order['support']);
    final documents = _marketMap(order['documents']);
    final invoice = _marketMap(documents['invoice']);
    final creditNote = _marketMap(documents['credit_note']);
    final bank = _marketMap(order['bank_transfer']);
    final returns = _marketMaps(order['return_requests']);
    final paymentAction = _marketMap(order['payment_action']);
    final checkoutUrl = _marketNullableText(paymentAction['url']);
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
                  leading: _ProductImage(
                    url: _marketNullableText(item['image_url']),
                    size: 50,
                  ),
                  title: Text(
                    _marketText(item['title']),
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  subtitle: Text(
                    "${t('market.quantity')}: ${_marketInt(item['quantity'])}\n"
                    "${t('market.unitPrice')}: ${_price(context, _marketInt(item['unit_gross_cents']), _marketText(item['currency'], fallback: currency))}",
                  ),
                ),
              const Divider(),
              _AmountRow(
                label: t('market.itemsSubtotal'),
                cents: _marketInt(order['item_gross_cents']),
                currency: currency,
              ),
              _AmountRow(
                label: t('market.shipping'),
                cents: _marketInt(order['shipping_cents']),
                currency: currency,
              ),
              _AmountRow(
                label: t('market.total'),
                cents: _marketInt(order['amount_cents']),
                currency: currency,
                strong: true,
              ),
              _AmountRow(
                label: t('market.includingTax'),
                cents: _marketInt(order['tax_cents']),
                currency: currency,
                muted: true,
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
            if (_marketNullableText(order['guest_status_url'])
                case final String guestStatusUrl)
              AirmiusButton(
                label: t('market.orderDetails'),
                icon: Icons.open_in_new,
                onPressed: () => _openUrl(guestStatusUrl),
              ),
            if (checkoutUrl != null)
              AirmiusButton(
                label: t('market.continuePayment'),
                icon: Icons.open_in_new,
                onPressed: _busy ? null : () => _openUrl(checkoutUrl),
              ),
            if (_marketBool(paymentAction['can_change_method']))
              AirmiusButton(
                label: t('market.changePaymentMethod'),
                icon: Icons.swap_horiz,
                secondary: true,
                onPressed: _busy ? null : () => _changePaymentMethod(order),
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

  Future<void> _changePaymentMethod(JsonMap order) async {
    final t = AirmiusScope.of(context).t;
    final current = _marketText(order['provider']);
    final selected = await showDialog<String>(
      context: context,
      builder: (dialogContext) => SimpleDialog(
        title: Text(t('market.changePaymentMethod')),
        children: [
          for (final provider in const ['bank_transfer', 'stripe', 'paypal'])
            if (provider != current)
              SimpleDialogOption(
                onPressed: () => Navigator.pop(dialogContext, provider),
                child: Text(t('market.payment.$provider')),
              ),
        ],
      ),
    );
    if (!mounted || selected == null || _busy) return;
    await _action(
      () => widget.client.changeCommerceOrderPaymentMethod(
        widget.orderId,
        selected,
      ),
      successMessage: t('market.paymentMethodChanged'),
    );
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
            if (_reason.text.trim().isEmpty) {
              showFormFeedback(context, formMessage(context, 'required'));
              return;
            }
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
