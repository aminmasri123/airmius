import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_module_access.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import '../widgets/admin_access_denied_screen.dart';
import 'admin_backoffice_screen.dart';
import 'admin_commerce_operations_screen.dart';
import 'outfit_operations_screen.dart';
import 'platform_admin_screen.dart';
import 'admin_workspace_screen.dart';

class AdminCenterScreen extends StatelessWidget {
  const AdminCenterScreen({super.key});

  @override
  Widget build(BuildContext context) => const AdminWorkspaceScreen();
}

class AdminCommerceOverviewScreen extends StatefulWidget {
  const AdminCommerceOverviewScreen({super.key});

  @override
  State<AdminCommerceOverviewScreen> createState() => _AdminCenterScreenState();
}

class _AdminCenterScreenState extends State<AdminCommerceOverviewScreen> {
  String _section = 'overview';
  Future<_AdminCommerceData>? _future;
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!AirmiusModuleAccess.canOpenAdmin(
      AirmiusServicesScope.of(context).authState.user,
    )) {
      return;
    }
    _future ??= _load();
  }

  Future<_AdminCommerceData> _load() async {
    final responses = await Future.wait([
      _client.adminCommerceDashboard(),
      _client.adminCommerceCatalog(),
    ]);
    return _AdminCommerceData(
      dashboard: _map(responses[0]['data']),
      catalog: _map(responses[1]['data']),
    );
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  Future<void> _run(
    Future<void> Function() action, {
    required String success,
  }) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(success)));
      _reload();
    } on AirmiusApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!AirmiusModuleAccess.canOpenAdmin(
      AirmiusServicesScope.of(context).authState.user,
    )) {
      return const AdminAccessDeniedScreen();
    }

    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('adminHub.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('common.refresh'),
            onPressed: _busy ? null : _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<_AdminCommerceData>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final error = snapshot.error;
            return _AdminFailure(
              message: error is AirmiusApiException
                  ? error.userMessage
                  : t('adminHub.loadFailed'),
              onRetry: _reload,
            );
          }
          final data =
              snapshot.data ??
              const _AdminCommerceData(dashboard: {}, catalog: {});
          return PageFrame(
            title: t('adminHub.title'),
            subtitle: t('adminHub.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _hero(data),
                const SizedBox(height: 14),
                _sectionPicker(),
                if (_busy) ...[
                  const SizedBox(height: 10),
                  const LinearProgressIndicator(minHeight: 3),
                ],
                const SizedBox(height: 14),
                _sectionContent(data),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _hero(_AdminCommerceData data) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final summary = _map(data.dashboard['summary']);
    final user = AirmiusServicesScope.of(context).authState.user;
    final canManagePlatform = user?.can('system.manage') ?? false;
    final canManageBackoffice =
        user?.can('subscriptions.manage') == true ||
        user?.can('billing.manage') == true ||
        user?.can('finance.view') == true ||
        user?.can('finance.edit') == true ||
        user?.can('system.manage') == true;
    final canManageOutfits = user?.can('outfit-subscriptions.manage') == true;
    final canManageCommerce =
        user?.can('marketplace.manage') == true ||
        user?.can('subscriptions.manage') == true ||
        user?.can('system.manage') == true;
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('adminHub.eyebrow')),
          const SizedBox(height: 7),
          Text(
            t('adminHub.headline'),
            style: theme.textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 7),
          Text(t('adminHub.body')),
          if (canManagePlatform ||
              canManageBackoffice ||
              canManageOutfits ||
              canManageCommerce) ...[
            const SizedBox(height: 14),
            Wrap(
              spacing: 9,
              runSpacing: 9,
              children: [
                if (canManageBackoffice)
                  FilledButton.tonalIcon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const AdminBackofficeScreen(),
                      ),
                    ),
                    icon: const Icon(Icons.account_balance_wallet_outlined),
                    label: Text(t('backoffice.title')),
                  ),
                if (canManageOutfits)
                  FilledButton.tonalIcon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const OutfitOperationsScreen(),
                      ),
                    ),
                    icon: const Icon(Icons.checkroom_outlined),
                    label: Text(t('outfitAdmin.title')),
                  ),
                if (canManagePlatform)
                  FilledButton.tonalIcon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const PlatformAdminScreen(),
                      ),
                    ),
                    icon: const Icon(Icons.admin_panel_settings_outlined),
                    label: Text(t('adminHub.platform')),
                  ),
                if (canManageCommerce)
                  FilledButton.tonalIcon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const AdminCommerceOperationsScreen(),
                      ),
                    ),
                    icon: const Icon(Icons.store_mall_directory_outlined),
                    label: Text(t('commerceOps.open')),
                  ),
              ],
            ),
          ],
          const SizedBox(height: 15),
          Wrap(
            spacing: 9,
            runSpacing: 9,
            children: [
              _AdminMetric(
                value: '${_int(summary['products_in_review'])}',
                label: t('adminHub.productsReview'),
              ),
              _AdminMetric(
                value: '${_int(summary['orders_awaiting_transfer'])}',
                label: t('adminHub.openTransfers'),
              ),
              _AdminMetric(
                value: '${_int(summary['seller_applications'])}',
                label: t('adminHub.sellerApplications'),
              ),
              _AdminMetric(
                value: _money(context, _int(summary['commission_cents'])),
                label: t('adminHub.commission'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _sectionPicker() {
    final t = AirmiusScope.of(context).t;
    final sections = {
      'overview': t('adminHub.overview'),
      'products': t('adminHub.products'),
      'orders': t('adminHub.orders'),
      'reviews': t('adminHub.reviews'),
    };
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: sections.entries
            .map(
              (entry) => Padding(
                padding: const EdgeInsetsDirectional.only(end: 8),
                child: ChoiceChip(
                  selected: _section == entry.key,
                  label: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 7),
                    child: Text(entry.value),
                  ),
                  onSelected: (_) => setState(() => _section = entry.key),
                ),
              ),
            )
            .toList(),
      ),
    );
  }

  Widget _sectionContent(_AdminCommerceData data) => switch (_section) {
    'products' => _products(data),
    'orders' => _orders(data),
    'reviews' => _reviews(data),
    _ => _overview(data),
  };

  Widget _overview(_AdminCommerceData data) {
    final t = AirmiusScope.of(context).t;
    final summary = _map(data.dashboard['summary']);
    final values = [
      (
        Icons.inventory_2_outlined,
        t('adminHub.allProducts'),
        '${_int(summary['products'])}',
      ),
      (
        Icons.receipt_long_outlined,
        t('adminHub.allOrders'),
        '${_int(summary['orders'])}',
      ),
      (
        Icons.language_outlined,
        t('adminHub.websiteRequests'),
        '${_int(summary['website_requests'])}',
      ),
      (
        Icons.campaign_outlined,
        t('adminHub.campaigns'),
        '${_int(summary['campaigns'])}',
      ),
      (
        Icons.account_balance_wallet_outlined,
        t('adminHub.preparedPayouts'),
        '${_int(summary['payouts_prepared'])}',
      ),
      (
        Icons.savings_outlined,
        t('adminHub.subscriptionRevenue'),
        _money(context, _int(summary['subscription_revenue_cents'])),
      ),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 680
            ? 3
            : constraints.maxWidth >= 460
            ? 2
            : 1;
        const gap = 10.0;
        final width = (constraints.maxWidth - gap * (columns - 1)) / columns;
        return Wrap(
          spacing: gap,
          runSpacing: gap,
          children: values
              .map(
                (item) => SizedBox(
                  width: width,
                  child: AirmiusPanel(
                    child: Row(
                      children: [
                        Icon(
                          item.$1,
                          color: Theme.of(context).colorScheme.primary,
                          size: 29,
                        ),
                        const SizedBox(width: 11),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                item.$3,
                                style: Theme.of(context).textTheme.titleLarge
                                    ?.copyWith(fontWeight: FontWeight.w900),
                              ),
                              Text(item.$2),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              )
              .toList(),
        );
      },
    );
  }

  Widget _products(_AdminCommerceData data) {
    final t = AirmiusScope.of(context).t;
    final products = _pagedItems(data.dashboard['products']);
    if (products.isEmpty) {
      return _AdminEmpty(
        icon: Icons.inventory_2_outlined,
        title: t('adminHub.noProducts'),
      );
    }
    return Column(
      children: products
          .map(
            (product) => Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: _AdminProductCard(
                product: product,
                onPublish: () => _run(
                  () async => _client.adminUpdateCommerceProduct(
                    _int(product['id']),
                    {'status': 'published', 'moderation_status': 'approved'},
                  ),
                  success: t('adminHub.productPublished'),
                ),
                onReject: () => _rejectProduct(product),
              ),
            ),
          )
          .toList(),
    );
  }

  Widget _orders(_AdminCommerceData data) {
    final t = AirmiusScope.of(context).t;
    final orders = _pagedItems(data.dashboard['orders']);
    if (orders.isEmpty) {
      return _AdminEmpty(
        icon: Icons.receipt_long_outlined,
        title: t('adminHub.noOrders'),
      );
    }
    return Column(
      children: orders
          .map(
            (order) => Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: _AdminOrderCard(
                order: order,
                onPaid: () => _run(
                  () async =>
                      _client.adminMarkCommerceOrderPaid(_int(order['id'])),
                  success: t('adminHub.orderPaid'),
                ),
                onShipping: () => _updateShipping(order),
              ),
            ),
          )
          .toList(),
    );
  }

  Widget _reviews(_AdminCommerceData data) {
    final t = AirmiusScope.of(context).t;
    final applications = _maps(data.catalog['seller_applications']);
    final websites = _maps(data.catalog['website_requests']);
    final campaigns = _maps(data.catalog['campaigns']);
    if (applications.isEmpty && websites.isEmpty && campaigns.isEmpty) {
      return _AdminEmpty(
        icon: Icons.fact_check_outlined,
        title: t('adminHub.noReviews'),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ...applications.map(
          (item) => _AdminReviewCard(
            icon: Icons.storefront_outlined,
            title: _text(item['business_name'], fallback: t('adminHub.seller')),
            status: _text(item['status'], fallback: 'pending'),
            onApprove: () => _run(
              () async => _client.adminUpdateSellerApplication(
                _int(item['id']),
                {'status': 'approved'},
              ),
              success: t('adminHub.reviewSaved'),
            ),
            onReject: () => _reviewWithNote(
              title: t('adminHub.rejectSeller'),
              action: (note) => _client.adminUpdateSellerApplication(
                _int(item['id']),
                {'status': 'rejected', 'review_note': note},
              ),
            ),
          ),
        ),
        ...websites.map(
          (item) => _AdminReviewCard(
            icon: Icons.language_outlined,
            title: _text(
              item['domain'],
              fallback: t('adminHub.websiteRequest'),
            ),
            status: _text(item['status'], fallback: 'new'),
            onApprove: () => _run(
              () async => _client.adminUpdateWebsiteRequest(_int(item['id']), {
                'status': 'in_progress',
                'notes': _text(item['notes']),
              }),
              success: t('adminHub.reviewSaved'),
            ),
            onReject: () => _reviewWithNote(
              title: t('adminHub.cancelWebsite'),
              action: (note) => _client.adminUpdateWebsiteRequest(
                _int(item['id']),
                {'status': 'cancelled', 'notes': note},
              ),
            ),
          ),
        ),
        ...campaigns.map(
          (item) => _AdminReviewCard(
            icon: Icons.campaign_outlined,
            title: _text(item['name'], fallback: t('adminHub.campaign')),
            status: _text(item['status'], fallback: 'draft'),
            onApprove: () => _run(
              () async => _client.adminUpdateCampaign(_int(item['id']), {
                'status': 'active',
                'review_note': t('adminHub.approvedMobile'),
              }),
              success: t('adminHub.reviewSaved'),
            ),
            onReject: () => _reviewWithNote(
              title: t('adminHub.rejectCampaign'),
              action: (note) => _client.adminUpdateCampaign(_int(item['id']), {
                'status': 'rejected',
                'review_note': note,
              }),
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _rejectProduct(Map<String, dynamic> product) async {
    final t = AirmiusScope.of(context).t;
    final note = await _askNote(t('adminHub.rejectProduct'));
    if (note == null) return;
    await _run(
      () async => _client.adminUpdateCommerceProduct(_int(product['id']), {
        'status': 'rejected',
        'moderation_status': 'rejected',
        'rejection_reason': note,
      }),
      success: t('adminHub.reviewSaved'),
    );
  }

  Future<void> _reviewWithNote({
    required String title,
    required Future<dynamic> Function(String note) action,
  }) async {
    final success = AirmiusScope.of(context).t('adminHub.reviewSaved');
    final note = await _askNote(title);
    if (note == null) return;
    await _run(() async => action(note), success: success);
  }

  Future<String?> _askNote(String title) async {
    final controller = TextEditingController();
    final result = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          minLines: 3,
          maxLines: 6,
          decoration: InputDecoration(
            labelText: AirmiusScope.of(context).t('adminHub.reason'),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(AirmiusScope.of(context).t('common.cancel')),
          ),
          FilledButton(
            onPressed: () {
              final text = controller.text.trim();
              if (text.isNotEmpty) Navigator.pop(dialogContext, text);
            },
            child: Text(AirmiusScope.of(context).t('common.save')),
          ),
        ],
      ),
    );
    controller.dispose();
    return result;
  }

  Future<void> _updateShipping(Map<String, dynamic> order) async {
    final t = AirmiusScope.of(context).t;
    final status = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: const ['open', 'prepared', 'shipped', 'delivered']
              .map(
                (value) => ListTile(
                  title: Text(t('adminHub.shipping.$value')),
                  onTap: () => Navigator.pop(sheetContext, value),
                ),
              )
              .toList(),
        ),
      ),
    );
    if (status == null) return;
    await _run(
      () async => _client.adminUpdateCommerceShipping(_int(order['id']), {
        'shipping_status': status,
      }),
      success: t('adminHub.shippingSaved'),
    );
  }
}

class _AdminMetric extends StatelessWidget {
  const _AdminMetric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minWidth: 140),
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: Theme.of(
        context,
      ).colorScheme.surfaceContainerHighest.withValues(alpha: 0.7),
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: Theme.of(context).dividerColor),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          value,
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
        Text(label),
      ],
    ),
  );
}

class _AdminProductCard extends StatelessWidget {
  const _AdminProductCard({
    required this.product,
    required this.onPublish,
    required this.onReject,
  });

  final Map<String, dynamic> product;
  final VoidCallback onPublish;
  final VoidCallback onReject;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _AdminCardHeader(
            icon: Icons.inventory_2_outlined,
            title: _text(product['title'], fallback: t('adminHub.product')),
            status: _text(product['status'], fallback: 'draft'),
          ),
          const SizedBox(height: 8),
          Text(
            '${_money(context, _int(product['price_cents']))} · ${_text(product['category'], fallback: '–')}',
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              FilledButton.icon(
                onPressed: onPublish,
                icon: const Icon(Icons.check_outlined),
                label: Text(t('adminHub.publish')),
              ),
              OutlinedButton.icon(
                onPressed: onReject,
                icon: const Icon(Icons.close_outlined),
                label: Text(t('adminHub.reject')),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AdminOrderCard extends StatelessWidget {
  const _AdminOrderCard({
    required this.order,
    required this.onPaid,
    required this.onShipping,
  });

  final Map<String, dynamic> order;
  final VoidCallback onPaid;
  final VoidCallback onShipping;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final status = _text(order['status'], fallback: 'open');
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _AdminCardHeader(
            icon: Icons.receipt_long_outlined,
            title: _text(order['reference'], fallback: '#${_int(order['id'])}'),
            status: status,
          ),
          const SizedBox(height: 8),
          Text(_money(context, _int(order['amount_cents']))),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (status == 'awaiting_transfer' || status == 'pending')
                FilledButton.icon(
                  onPressed: onPaid,
                  icon: const Icon(Icons.payments_outlined),
                  label: Text(t('adminHub.markPaid')),
                ),
              OutlinedButton.icon(
                onPressed: onShipping,
                icon: const Icon(Icons.local_shipping_outlined),
                label: Text(t('adminHub.shipping')),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AdminReviewCard extends StatelessWidget {
  const _AdminReviewCard({
    required this.icon,
    required this.title,
    required this.status,
    required this.onApprove,
    required this.onReject,
  });

  final IconData icon;
  final String title;
  final String status;
  final VoidCallback onApprove;
  final VoidCallback onReject;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: const EdgeInsets.only(bottom: 11),
      child: AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _AdminCardHeader(icon: icon, title: title, status: status),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                FilledButton.icon(
                  onPressed: onApprove,
                  icon: const Icon(Icons.check_outlined),
                  label: Text(t('adminHub.approve')),
                ),
                OutlinedButton.icon(
                  onPressed: onReject,
                  icon: const Icon(Icons.close_outlined),
                  label: Text(t('adminHub.reject')),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _AdminCardHeader extends StatelessWidget {
  const _AdminCardHeader({
    required this.icon,
    required this.title,
    required this.status,
  });

  final IconData icon;
  final String title;
  final String status;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(icon, color: Theme.of(context).colorScheme.primary, size: 28),
      const SizedBox(width: 10),
      Expanded(
        child: Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
      ),
      const SizedBox(width: 7),
      StatusPill(status.replaceAll('_', ' ')),
    ],
  );
}

class _AdminEmpty extends StatelessWidget {
  const _AdminEmpty({required this.icon, required this.title});

  final IconData icon;
  final String title;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 18),
      child: Column(
        children: [
          Icon(icon, size: 42, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 9),
          Text(
            title,
            textAlign: TextAlign.center,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
        ],
      ),
    ),
  );
}

class _AdminFailure extends StatelessWidget {
  const _AdminFailure({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.gpp_bad_outlined, size: 45),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_outlined),
            label: Text(AirmiusScope.of(context).t('common.retry')),
          ),
        ],
      ),
    ),
  );
}

class _AdminCommerceData {
  const _AdminCommerceData({required this.dashboard, required this.catalog});

  final Map<String, dynamic> dashboard;
  final Map<String, dynamic> catalog;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _maps(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <Map<String, dynamic>>[];

List<Map<String, dynamic>> _pagedItems(Object? value) =>
    _maps(_map(value)['data']);

String _text(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

String _money(BuildContext context, int cents) => NumberFormat.simpleCurrency(
  locale: Localizations.localeOf(context).toLanguageTag(),
  name: 'EUR',
).format(cents / 100);
