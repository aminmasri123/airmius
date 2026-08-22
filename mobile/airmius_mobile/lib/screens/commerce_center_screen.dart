import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class CommerceCenterScreen extends StatefulWidget {
  const CommerceCenterScreen({
    super.key,
    this.initialSection = 'overview',
    this.openCampaignComposer = false,
  });

  final String initialSection;
  final bool openCampaignComposer;

  @override
  State<CommerceCenterScreen> createState() => _CommerceCenterScreenState();
}

class _CommerceCenterScreenState extends State<CommerceCenterScreen> {
  late String _section;
  Future<Map<String, dynamic>>? _future;
  bool _busy = false;
  bool _campaignComposerScheduled = false;

  @override
  void initState() {
    super.initState();
    _section = widget.initialSection;
  }

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<Map<String, dynamic>> _load() async =>
      _map((await _client.commerceSellerDashboard())['data']);

  String _errorMessage(AirmiusApiException error) => error.statusCode == 599
      ? AirmiusScope.of(context).t('common.errorDetails')
      : error.userMessage;

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  Future<void> _run(Future<void> Function() action, {String? success}) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      if (success != null) _toast(success);
      _reload();
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(_errorMessage(error));
    } catch (error) {
      if (mounted) {
        _toast(
          error is AirmiusApiException
              ? _errorMessage(error)
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
          t('seller.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('common.refresh'),
            onPressed: _busy ? null : _reload,
            icon: Icon(Icons.refresh),
          ),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ErrorState(error: snapshot.error, onRetry: _reload);
          }
          final data = snapshot.data ?? const <String, dynamic>{};
          if (widget.openCampaignComposer && !_campaignComposerScheduled) {
            _campaignComposerScheduled = true;
            WidgetsBinding.instance.addPostFrameCallback((_) {
              if (mounted) _showCampaign(data);
            });
          }
          return RefreshIndicator(
            onRefresh: () async {
              final next = await _load();
              if (mounted) setState(() => _future = Future.value(next));
            },
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 18, 16, 36),
              children: [
                _Header(data: data),
                const SizedBox(height: 14),
                _SectionPicker(
                  selected: _section,
                  onSelected: (value) => setState(() => _section = value),
                ),
                const SizedBox(height: 14),
                if (_busy) const LinearProgressIndicator(minHeight: 3),
                if (_busy) const SizedBox(height: 10),
                _buildSection(data),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildSection(Map<String, dynamic> data) {
    return switch (_section) {
      'products' => _products(data),
      'orders' => _orders(data),
      'payouts' => _payouts(data),
      'provider' => _provider(data),
      'ads' => _campaigns(data),
      'websites' => _websiteRequests(data),
      _ => _overview(data),
    };
  }

  Widget _overview(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final products = _maps(data['products']);
    final orders = _maps(data['orders']);
    final payouts = _maps(data['payouts']);
    final canSell = data['seller_can_sell'] == true;
    final application = _map(data['seller_application']);
    final payout = _map(data['payout_summary']);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        LayoutBuilder(
          builder: (context, constraints) {
            final width = constraints.maxWidth >= 700
                ? (constraints.maxWidth - 20) / 3
                : constraints.maxWidth;
            return Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                SizedBox(
                  width: width,
                  child: MetricCard(
                    value: '${products.length}',
                    label: t('seller.products'),
                  ),
                ),
                SizedBox(
                  width: width,
                  child: MetricCard(
                    value: '${orders.length}',
                    label: t('seller.orders'),
                  ),
                ),
                SizedBox(
                  width: width,
                  child: MetricCard(
                    value: _money(payout['amount_cents']),
                    label: t('seller.payable'),
                  ),
                ),
              ],
            );
          },
        ),
        const SizedBox(height: 14),
        if (!canSell)
          _ApplicationCard(
            application: application,
            onApply: () => _showApplication(),
          )
        else
          AirmiusPanel(
            borderColor: _commerceTone(
              context,
              AirmiusColors.green,
            ).withValues(alpha: 0.5),
            child: Row(
              children: [
                Icon(
                  Icons.verified_outlined,
                  color: _commerceTone(context, AirmiusColors.green),
                  size: 30,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        t('seller.approved'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                          fontSize: 17,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        t('seller.approvedBody'),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          height: 1.4,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        const SizedBox(height: 14),
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('seller.quickActions')),
              const SizedBox(height: 12),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  AirmiusButton(
                    label: t('seller.addProduct'),
                    icon: Icons.add_box_outlined,
                    onPressed: canSell ? () => _showProduct() : null,
                  ),
                  AirmiusButton(
                    label: t('seller.providerProfile'),
                    icon: Icons.storefront_outlined,
                    secondary: true,
                    onPressed: () =>
                        _showProviderProfile(_map(data['provider_profile'])),
                  ),
                  AirmiusButton(
                    label: t('seller.payoutDetails'),
                    icon: Icons.account_balance_outlined,
                    secondary: true,
                    onPressed: () =>
                        _showPayoutProfile(_map(data['payout_profile'])),
                  ),
                ],
              ),
            ],
          ),
        ),
        if (orders.isNotEmpty) ...[
          const SizedBox(height: 14),
          _SellerOrderCard(
            order: orders.first,
            onTap: () => _showOrder(orders.first),
          ),
        ],
        if (payouts.isNotEmpty) ...[
          const SizedBox(height: 12),
          _PayoutCard(payout: payouts.first),
        ],
      ],
    );
  }

  Widget _products(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final products = _maps(data['products']);
    final canSell = data['seller_can_sell'] == true;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusButton(
          label: t('seller.addProduct'),
          icon: Icons.add_box_outlined,
          onPressed: canSell ? () => _showProduct() : null,
        ),
        const SizedBox(height: 14),
        if (!canSell)
          _ApplicationCard(
            application: _map(data['seller_application']),
            onApply: () => _showApplication(),
          )
        else if (products.isEmpty)
          _EmptyState(
            icon: Icons.inventory_2_outlined,
            title: t('seller.noProducts'),
            body: t('seller.noProductsBody'),
          )
        else
          ...products.map(
            (product) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _ProductCard(
                product: product,
                onEdit: () => _showProduct(product),
                onStatus: (status) => _run(() async {
                  await _client.updateCommerceSellerProductStatus(
                    _int(product['id']),
                    status,
                  );
                }, success: t('seller.productUpdated')),
                onDelete: () => _confirmDeleteProduct(product),
              ),
            ),
          ),
      ],
    );
  }

  Widget _orders(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final orders = _maps(data['orders']);
    if (orders.isEmpty) {
      return _EmptyState(
        icon: Icons.receipt_long_outlined,
        title: t('seller.noOrders'),
        body: t('seller.noOrdersBody'),
      );
    }
    return Column(
      children: orders
          .map(
            (order) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _SellerOrderCard(
                order: order,
                onTap: () => _showOrder(order),
              ),
            ),
          )
          .toList(),
    );
  }

  Widget _payouts(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final summary = _map(data['payout_summary']);
    final profile = _map(data['payout_profile']);
    final payouts = _maps(data['payouts']);
    final approved = profile['status'] == 'approved';
    final eligible = _int(summary['eligible_orders']) > 0;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('seller.payoutOverview')),
              const SizedBox(height: 10),
              Text(
                _money(summary['amount_cents']),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                  fontSize: 28,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                t('seller.payableAfterProtection'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 12),
              _ValueRow(
                label: t('seller.eligibleOrders'),
                value: '${_int(summary['eligible_orders'])}',
              ),
              _ValueRow(
                label: t('seller.waitingOrders'),
                value: '${_int(summary['waiting_orders'])}',
              ),
              _ValueRow(
                label: t('seller.commission'),
                value: _money(summary['commission_cents']),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  AirmiusButton(
                    label: t('seller.payoutDetails'),
                    icon: Icons.account_balance_outlined,
                    secondary: true,
                    onPressed: () => _showPayoutProfile(profile),
                  ),
                  AirmiusButton(
                    label: t('seller.requestPayout'),
                    icon: Icons.payments_outlined,
                    onPressed: approved && eligible
                        ? () => _showPayoutRequest(profile)
                        : null,
                  ),
                ],
              ),
              if (!approved) ...[
                const SizedBox(height: 10),
                Text(
                  t('seller.payoutNeedsApproval'),
                  style: TextStyle(
                    color: _commerceTone(context, AirmiusColors.amber),
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 14),
        if (payouts.isEmpty)
          _EmptyState(
            icon: Icons.account_balance_wallet_outlined,
            title: t('seller.noPayouts'),
            body: t('seller.noPayoutsBody'),
          )
        else
          ...payouts.map(
            (payout) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _PayoutCard(payout: payout),
            ),
          ),
      ],
    );
  }

  Widget _provider(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final profile = _map(data['provider_profile']);
    final locations = _maps(data['provider_locations']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.storefront_outlined,
                    color: airmiusAccentColor(context),
                    size: 32,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _text(
                            profile['display_name'],
                            fallback: t('seller.providerProfile'),
                          ),
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _text(
                            profile['public_description'],
                            fallback: t('seller.providerEmpty'),
                          ),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                  StatusPill(
                    _status(profile['status']),
                    color: profile['status'] == 'active'
                        ? _commerceTone(context, AirmiusColors.green)
                        : _commerceTone(context, AirmiusColors.amber),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              AirmiusButton(
                label: t('seller.editProvider'),
                icon: Icons.edit_outlined,
                onPressed: () => _showProviderProfile(profile),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Row(
          children: [
            Expanded(
              child: Text(
                t('seller.locations'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 19,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            IconButton.filledTonal(
              tooltip: t('seller.addLocation'),
              onPressed: () => _showLocation(),
              icon: Icon(Icons.add_location_alt_outlined),
            ),
          ],
        ),
        const SizedBox(height: 10),
        if (locations.isEmpty)
          _EmptyState(
            icon: Icons.location_off_outlined,
            title: t('seller.noLocations'),
            body: t('seller.noLocationsBody'),
          )
        else
          ...locations.map(
            (location) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _LocationCard(
                location: location,
                onEdit: () => _showLocation(location),
                onDelete: () => _confirmDeleteLocation(location),
              ),
            ),
          ),
      ],
    );
  }

  Widget _campaigns(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final campaigns = _maps(data['campaigns']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusButton(
          label: t('commerceAdv.createCampaign'),
          icon: Icons.campaign_outlined,
          onPressed: () => _showCampaign(data),
        ),
        const SizedBox(height: 14),
        if (campaigns.isEmpty)
          _EmptyState(
            icon: Icons.campaign_outlined,
            title: t('commerceAdv.noCampaigns'),
            body: t('commerceAdv.noCampaignsBody'),
          )
        else
          ...campaigns.map(
            (campaign) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _CampaignCard(
                campaign: campaign,
                onEdit: () => _showCampaign(data, campaign),
                onStatus: (status) => _run(
                  () async => _client.updateCommerceCampaignStatus(
                    _int(campaign['id']),
                    status,
                  ),
                  success: t('commerceAdv.campaignUpdated'),
                ),
                onDelete: () => _confirmDeleteCampaign(campaign),
              ),
            ),
          ),
      ],
    );
  }

  Widget _websiteRequests(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final requests = _maps(data['website_requests']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('commerceAdv.websiteEyebrow')),
              const SizedBox(height: 7),
              Text(
                t('commerceAdv.websiteHeadline'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 21,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                t('commerceAdv.websiteBody'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 12),
              AirmiusButton(
                label: t('commerceAdv.requestWebsite'),
                icon: Icons.language_outlined,
                onPressed: () => _showWebsiteRequest(data),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        if (requests.isEmpty)
          _EmptyState(
            icon: Icons.web_asset_off_outlined,
            title: t('commerceAdv.noWebsiteRequests'),
            body: t('commerceAdv.noWebsiteRequestsBody'),
          )
        else
          ...requests.map(
            (request) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _WebsiteRequestCard(request: request),
            ),
          ),
      ],
    );
  }

  Future<void> _showApplication() async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => const _SellerApplicationSheet(),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async => _client.submitCommerceSellerApplication(payload),
      success: AirmiusScope.of(context).t('seller.applicationSent'),
    );
  }

  Future<void> _showProduct([Map<String, dynamic>? product]) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _ProductSheet(product: product),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async {
        if (product == null) {
          await _client.createCommerceSellerProduct(payload);
        } else {
          await _client.updateCommerceSellerProduct(
            _int(product['id']),
            payload,
          );
        }
      },
      success: AirmiusScope.of(
        context,
      ).t(product == null ? 'seller.productCreated' : 'seller.productUpdated'),
    );
  }

  Future<void> _showProviderProfile(Map<String, dynamic> profile) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _ProviderSheet(profile: profile),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async => _client.updateCommerceProviderProfile(payload),
      success: AirmiusScope.of(context).t('seller.providerSaved'),
    );
  }

  Future<void> _showLocation([Map<String, dynamic>? location]) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _LocationSheet(location: location),
    );
    if (payload == null || !mounted) return;
    await _run(() async {
      if (location == null) {
        await _client.createCommerceProviderLocation(payload);
      } else {
        await _client.updateCommerceProviderLocation(
          _int(location['id']),
          payload,
        );
      }
    }, success: AirmiusScope.of(context).t('seller.locationSaved'));
  }

  Future<void> _showPayoutProfile(Map<String, dynamic> profile) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _PayoutProfileSheet(profile: profile),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async => _client.updateCommercePayoutProfile(payload),
      success: AirmiusScope.of(context).t('seller.payoutSaved'),
    );
  }

  Future<void> _showPayoutRequest(Map<String, dynamic> profile) async {
    final result = await showModalBottomSheet<Map<String, String>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _PayoutRequestSheet(profile: profile),
    );
    if (result == null || !mounted) return;
    await _run(
      () async => _client.requestCommercePayout(
        method: result['method']!,
        notes: result['notes'],
      ),
      success: AirmiusScope.of(context).t('seller.payoutRequested'),
    );
  }

  Future<void> _showCampaign(
    Map<String, dynamic> data, [
    Map<String, dynamic>? campaign,
  ]) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _CampaignSheet(
        campaign: campaign,
        clubs: _maps(data['clubs']),
        minimumBudgetCents: _int(data['ads_min_budget_cents']),
      ),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async {
        if (campaign == null) {
          await _client.createCommerceCampaign(payload);
        } else {
          await _client.updateCommerceCampaign(_int(campaign['id']), payload);
        }
      },
      success: AirmiusScope.of(context).t(
        campaign == null
            ? 'commerceAdv.campaignCreated'
            : 'commerceAdv.campaignUpdated',
      ),
    );
  }

  Future<void> _showWebsiteRequest(Map<String, dynamic> data) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _WebsiteRequestSheet(clubs: _maps(data['clubs'])),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async => _client.createCommerceWebsiteRequest(payload),
      success: AirmiusScope.of(context).t('commerceAdv.websiteRequested'),
    );
  }

  void _showOrder(Map<String, dynamic> order) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _OrderSheet(order: order),
    );
  }

  Future<void> _confirmDeleteProduct(Map<String, dynamic> product) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('seller.deleteProduct')),
        content: Text(t('seller.deleteProductBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('common.delete')),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await _run(
      () async => _client.deleteCommerceSellerProduct(_int(product['id'])),
      success: t('seller.productDeleted'),
    );
  }

  Future<void> _confirmDeleteLocation(Map<String, dynamic> location) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('seller.deleteLocation')),
        content: Text(t('seller.deleteLocationBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('common.delete')),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await _run(
      () async => _client.deleteCommerceProviderLocation(_int(location['id'])),
      success: t('seller.locationDeleted'),
    );
  }

  Future<void> _confirmDeleteCampaign(Map<String, dynamic> campaign) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('commerceAdv.deleteCampaign')),
        content: Text(t('commerceAdv.deleteCampaignBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('common.delete')),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await _run(
      () async => _client.deleteCommerceCampaign(_int(campaign['id'])),
      success: t('commerceAdv.campaignDeleted'),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.data});

  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final canSell = data['seller_can_sell'] == true;
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('seller.eyebrow')),
          const SizedBox(height: 8),
          Text(
            t('seller.headline'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 23,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            t('seller.subtitle'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.45),
          ),
          const SizedBox(height: 12),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: StatusPill(
              canSell ? t('seller.approvedShort') : t('seller.notApproved'),
              color: canSell
                  ? _commerceTone(context, AirmiusColors.green)
                  : _commerceTone(context, AirmiusColors.amber),
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionPicker extends StatelessWidget {
  const _SectionPicker({required this.selected, required this.onSelected});

  final String selected;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final sections = {
      'overview': t('seller.overview'),
      'products': t('seller.products'),
      'orders': t('seller.orders'),
      'payouts': t('seller.payouts'),
      'provider': t('seller.provider'),
      'ads': t('commerceAdv.ads'),
      'websites': t('commerceAdv.websites'),
    };
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: sections.entries
            .map(
              (entry) => Padding(
                padding: const EdgeInsetsDirectional.only(end: 8),
                child: ChoiceChip(
                  selected: selected == entry.key,
                  label: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 7),
                    child: Text(entry.value),
                  ),
                  onSelected: (_) => onSelected(entry.key),
                  selectedColor: airmiusAccentColor(
                    context,
                  ).withValues(alpha: 0.22),
                  backgroundColor: airmiusSurfaceSoftColor(context),
                  side: BorderSide(
                    color: selected == entry.key
                        ? airmiusAccentColor(context)
                        : airmiusBorderColor(context),
                  ),
                  labelStyle: TextStyle(
                    color: selected == entry.key
                        ? airmiusAccentColor(context)
                        : airmiusMutedColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            )
            .toList(),
      ),
    );
  }
}

class _ApplicationCard extends StatelessWidget {
  const _ApplicationCard({required this.application, required this.onApply});

  final Map<String, dynamic> application;
  final VoidCallback onApply;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final status = _text(application['status'], fallback: 'none');
    final statusLabel = switch (status) {
      'pending' => t('seller.status.pending'),
      'approved' => t('seller.status.approved'),
      'rejected' => t('seller.status.rejected'),
      _ => t('seller.status.none'),
    };
    return AirmiusPanel(
      borderColor: _commerceTone(
        context,
        AirmiusColors.amber,
      ).withValues(alpha: 0.5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                Icons.assignment_ind_outlined,
                color: _commerceTone(context, AirmiusColors.amber),
                size: 30,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  status == 'pending'
                      ? t('seller.applicationPending')
                      : t('seller.applicationRequired'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                    fontSize: 18,
                  ),
                ),
              ),
              StatusPill(
                statusLabel,
                color: _commerceTone(context, AirmiusColors.amber),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            status == 'pending'
                ? t('seller.applicationPendingBody')
                : t('seller.applicationRequiredBody'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
          ),
          if (status != 'pending') ...[
            const SizedBox(height: 12),
            AirmiusButton(
              label: t('seller.applyNow'),
              icon: Icons.send_outlined,
              onPressed: onApply,
            ),
          ],
        ],
      ),
    );
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({
    required this.product,
    required this.onEdit,
    required this.onStatus,
    required this.onDelete,
  });

  final Map<String, dynamic> product;
  final VoidCallback onEdit;
  final ValueChanged<String> onStatus;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final status = _text(product['status'], fallback: 'draft');
    final stock = product['manages_stock'] == true
        ? '${_int(product['stock_quantity'])}'
        : '∞';
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _ProductImage(url: _text(product['image_url'])),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _text(product['title'], fallback: t('seller.product')),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      '${_money(product['price_cents'])} · ${t('seller.stock')}: $stock',
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        StatusPill(
                          _status(status),
                          color: status == 'published'
                              ? _commerceTone(context, AirmiusColors.green)
                              : status == 'rejected'
                              ? _commerceTone(context, AirmiusColors.red)
                              : _commerceTone(context, AirmiusColors.amber),
                        ),
                        StatusPill(
                          _status(product['moderation_status']),
                          color: product['moderation_status'] == 'approved'
                              ? _commerceTone(context, AirmiusColors.green)
                              : _commerceTone(context, AirmiusColors.amber),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (_text(product['rejection_reason']).isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              _text(product['rejection_reason']),
              style: TextStyle(
                color: _commerceTone(context, AirmiusColors.red),
                height: 1.4,
              ),
            ),
          ],
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              OutlinedButton.icon(
                onPressed: onEdit,
                icon: Icon(Icons.edit_outlined),
                label: Text(t('common.edit')),
              ),
              if (status != 'archived')
                OutlinedButton.icon(
                  onPressed: () => onStatus('archived'),
                  icon: Icon(Icons.archive_outlined),
                  label: Text(t('seller.archive')),
                ),
              if (status == 'archived' || status == 'draft')
                OutlinedButton.icon(
                  onPressed: () => onStatus('review'),
                  icon: Icon(Icons.publish_outlined),
                  label: Text(t('seller.submitReview')),
                ),
              IconButton(
                tooltip: t('common.delete'),
                onPressed: onDelete,
                color: _commerceTone(context, AirmiusColors.red),
                icon: Icon(Icons.delete_outline),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ProductImage extends StatelessWidget {
  const _ProductImage({required this.url});

  final String url;

  @override
  Widget build(BuildContext context) {
    final resolvedUrl = resolveAirmiusImageUrl(url);
    return ClipRRect(
      borderRadius: BorderRadius.circular(14),
      child: Container(
        width: 82,
        height: 82,
        color: airmiusSurfaceSoftColor(context),
        child: resolvedUrl == null
            ? Icon(
                Icons.inventory_2_outlined,
                color: airmiusMutedColor(context),
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
      ),
    );
  }
}

class _SellerOrderCard extends StatelessWidget {
  const _SellerOrderCard({required this.order, required this.onTap});

  final Map<String, dynamic> order;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      onTap: onTap,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.receipt_long_outlined,
            color: airmiusAccentColor(context),
            size: 30,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _text(order['reference'], fallback: t('seller.order')),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                    fontSize: 17,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  '${_money(order['seller_gross_cents'])} · ${_maps(order['items']).length + (_map(order['product']).isEmpty ? 0 : 1)} ${t('seller.positions')}',
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    StatusPill(
                      _status(order['status']),
                      color: order['status'] == 'completed'
                          ? _commerceTone(context, AirmiusColors.green)
                          : _commerceTone(context, AirmiusColors.amber),
                    ),
                    StatusPill(
                      _status(order['shipping_status']),
                      color: order['shipping_status'] == 'delivered'
                          ? _commerceTone(context, AirmiusColors.green)
                          : airmiusAccentColor(context),
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

class _PayoutCard extends StatelessWidget {
  const _PayoutCard({required this.payout});

  final Map<String, dynamic> payout;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Row(
        children: [
          Icon(
            Icons.account_balance_wallet_outlined,
            color: _commerceTone(context, AirmiusColors.green),
            size: 30,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _text(payout['reference'], fallback: t('seller.payout')),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${_money(payout['amount_cents'])} · ${_int(payout['orders_count'])} ${t('seller.orders')}',
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
              ],
            ),
          ),
          StatusPill(
            _status(payout['status']),
            color: payout['status'] == 'paid'
                ? _commerceTone(context, AirmiusColors.green)
                : _commerceTone(context, AirmiusColors.amber),
          ),
        ],
      ),
    );
  }
}

class _LocationCard extends StatelessWidget {
  const _LocationCard({
    required this.location,
    required this.onEdit,
    required this.onDelete,
  });

  final Map<String, dynamic> location;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                Icons.location_on_outlined,
                color: airmiusAccentColor(context),
                size: 28,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _text(location['name'], fallback: t('seller.location')),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _text(
                        location['address'],
                        fallback: t('seller.noAddress'),
                      ),
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              if (location['pickup_enabled'] == true)
                StatusPill(
                  t('seller.pickup'),
                  color: _commerceTone(context, AirmiusColors.green),
                ),
              if (location['returns_enabled'] == true)
                StatusPill(
                  t('seller.returns'),
                  color: airmiusAccentColor(context),
                ),
              if (location['is_public'] == true)
                StatusPill(
                  t('seller.public'),
                  color: _commerceTone(context, AirmiusColors.green),
                ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              OutlinedButton.icon(
                onPressed: onEdit,
                icon: Icon(Icons.edit_outlined),
                label: Text(t('common.edit')),
              ),
              const SizedBox(width: 8),
              IconButton(
                tooltip: t('common.delete'),
                onPressed: onDelete,
                color: _commerceTone(context, AirmiusColors.red),
                icon: Icon(Icons.delete_outline),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CampaignCard extends StatelessWidget {
  const _CampaignCard({
    required this.campaign,
    required this.onEdit,
    required this.onStatus,
    required this.onDelete,
  });

  final Map<String, dynamic> campaign;
  final VoidCallback onEdit;
  final ValueChanged<String> onStatus;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final status = _text(campaign['status'], fallback: 'draft');
    final budget = _int(campaign['budget_cents']);
    final spent = _int(campaign['spent_cents']);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.campaign_outlined,
                color: airmiusAccentColor(context),
                size: 31,
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _text(
                        campaign['name'],
                        fallback: t('commerceAdv.campaign'),
                      ),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _text(
                        campaign['headline'],
                        fallback: t('commerceAdv.noHeadline'),
                      ),
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ],
                ),
              ),
              StatusPill(
                _status(status),
                color: status == 'active'
                    ? _commerceTone(context, AirmiusColors.green)
                    : status == 'rejected'
                    ? _commerceTone(context, AirmiusColors.red)
                    : _commerceTone(context, AirmiusColors.amber),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              StatusPill(
                '${t('commerceAdv.budget')}: ${_money(budget)}',
                color: airmiusAccentColor(context),
              ),
              StatusPill(
                '${t('commerceAdv.spent')}: ${_money(spent)}',
                color: _commerceTone(context, AirmiusColors.amber),
              ),
              StatusPill(
                '${_int(campaign['impressions'])} ${t('commerceAdv.impressions')}',
              ),
              StatusPill(
                '${_int(campaign['clicks'])} ${t('commerceAdv.clicks')}',
              ),
              if (campaign['payment_completed'] == true)
                StatusPill(
                  t('commerceAdv.paid'),
                  color: _commerceTone(context, AirmiusColors.green),
                ),
              if (campaign['payment_pending'] == true)
                StatusPill(
                  t('commerceAdv.paymentPending'),
                  color: _commerceTone(context, AirmiusColors.amber),
                ),
            ],
          ),
          if (_text(campaign['review_note']).isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              _text(campaign['review_note']),
              style: TextStyle(
                color: _commerceTone(context, AirmiusColors.amber),
                height: 1.4,
              ),
            ),
          ],
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              OutlinedButton.icon(
                onPressed: onEdit,
                icon: Icon(Icons.edit_outlined),
                label: Text(t('common.edit')),
              ),
              if (status == 'active')
                OutlinedButton.icon(
                  onPressed: () => onStatus('paused'),
                  icon: Icon(Icons.pause_outlined),
                  label: Text(t('commerceAdv.pause')),
                ),
              if (status == 'paused')
                OutlinedButton.icon(
                  onPressed: () => onStatus('active'),
                  icon: Icon(Icons.play_arrow_outlined),
                  label: Text(t('commerceAdv.resume')),
                ),
              IconButton(
                tooltip: t('common.delete'),
                onPressed: onDelete,
                color: _commerceTone(context, AirmiusColors.red),
                icon: Icon(Icons.delete_outline),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _WebsiteRequestCard extends StatelessWidget {
  const _WebsiteRequestCard({required this.request});

  final Map<String, dynamic> request;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final club = _map(request['club']);
    final status = _text(request['status'], fallback: 'new');
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.language_outlined,
            color: airmiusAccentColor(context),
            size: 30,
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _text(
                    request['domain'],
                    fallback: _text(
                      club['name'],
                      fallback: t('commerceAdv.websiteProject'),
                    ),
                  ),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (_text(request['goals']).isNotEmpty) ...[
                  const SizedBox(height: 5),
                  Text(
                    _text(request['goals']),
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.4,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 8),
          StatusPill(
            t('commerceAdv.websiteStatus.$status'),
            color: status == 'done'
                ? _commerceTone(context, AirmiusColors.green)
                : _commerceTone(context, AirmiusColors.amber),
          ),
        ],
      ),
    );
  }
}

class _CampaignSheet extends StatefulWidget {
  const _CampaignSheet({
    required this.campaign,
    required this.clubs,
    required this.minimumBudgetCents,
  });

  final Map<String, dynamic>? campaign;
  final List<Map<String, dynamic>> clubs;
  final int minimumBudgetCents;

  @override
  State<_CampaignSheet> createState() => _CampaignSheetState();
}

class _CampaignSheetState extends State<_CampaignSheet> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _headline;
  late final TextEditingController _description;
  late final TextEditingController _targetUrl;
  late final TextEditingController _imageUrl;
  late final TextEditingController _budget;
  late final TextEditingController _dailyBudget;
  late final TextEditingController _interests;
  late String _objective;
  late String _placement;
  late String _format;
  int? _clubId;
  bool _startPayment = true;
  bool _acceptedTerms = false;

  bool get _editing => widget.campaign != null;

  @override
  void initState() {
    super.initState();
    final campaign = widget.campaign ?? const <String, dynamic>{};
    final audience = _map(campaign['audience']);
    _name = TextEditingController(text: _text(campaign['name']));
    _headline = TextEditingController(text: _text(campaign['headline']));
    _description = TextEditingController(text: _text(campaign['description']));
    _targetUrl = TextEditingController(text: _text(campaign['target_url']));
    _imageUrl = TextEditingController(
      text: _text(campaign['creative_image_url']),
    );
    _budget = TextEditingController(
      text: _int(campaign['budget_cents']) > 0
          ? (_int(campaign['budget_cents']) / 100).toStringAsFixed(2)
          : '',
    );
    _dailyBudget = TextEditingController(
      text: _int(campaign['daily_budget_cents']) > 0
          ? (_int(campaign['daily_budget_cents']) / 100).toStringAsFixed(2)
          : '',
    );
    _interests = TextEditingController(
      text: audience['interests'] is List
          ? (audience['interests'] as List).join(', ')
          : '',
    );
    _objective = _text(campaign['objective'], fallback: 'traffic');
    _placement = _text(campaign['placement'], fallback: 'feed');
    _format = _text(campaign['creative_format'], fallback: 'feed_square');
    _clubId = campaign['club_id'] == null ? null : _int(campaign['club_id']);
  }

  @override
  void dispose() {
    _name.dispose();
    _headline.dispose();
    _description.dispose();
    _targetUrl.dispose();
    _imageUrl.dispose();
    _budget.dispose();
    _dailyBudget.dispose();
    _interests.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _SheetFrame(
      title: t(
        _editing ? 'commerceAdv.editCampaign' : 'commerceAdv.createCampaign',
      ),
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextFormField(
              controller: _name,
              decoration: InputDecoration(
                labelText: t('commerceAdv.campaignName'),
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? t('commerceAdv.required')
                  : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _headline,
              decoration: InputDecoration(labelText: t('commerceAdv.headline')),
              maxLength: 120,
            ),
            const SizedBox(height: 6),
            TextFormField(
              controller: _description,
              decoration: InputDecoration(
                labelText: t('commerceAdv.description'),
              ),
              minLines: 2,
              maxLines: 4,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _targetUrl,
              keyboardType: TextInputType.url,
              decoration: InputDecoration(
                labelText: t('commerceAdv.targetUrl'),
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _imageUrl,
              keyboardType: TextInputType.url,
              decoration: InputDecoration(labelText: t('commerceAdv.imageUrl')),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _objective,
              isExpanded: true,
              decoration: InputDecoration(
                labelText: t('commerceAdv.objective'),
              ),
              items: const ['traffic', 'awareness', 'leads', 'sales']
                  .map(
                    (value) => DropdownMenuItem(
                      value: value,
                      child: Text(t('commerceAdv.objective.$value')),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _objective = value!),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _placement,
              isExpanded: true,
              decoration: InputDecoration(
                labelText: t('commerceAdv.placement'),
              ),
              items:
                  const [
                        'marketplace_card',
                        'feed',
                        'sidebar',
                        'sponsor_section',
                      ]
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(t('commerceAdv.placement.$value')),
                        ),
                      )
                      .toList(),
              onChanged: (value) => setState(() => _placement = value!),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _format,
              isExpanded: true,
              decoration: InputDecoration(labelText: t('commerceAdv.format')),
              items:
                  const [
                        'feed_square',
                        'feed_portrait',
                        'story_vertical',
                        'banner_wide',
                      ]
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(t('commerceAdv.format.$value')),
                        ),
                      )
                      .toList(),
              onChanged: (value) => setState(() => _format = value!),
            ),
            if (widget.clubs.isNotEmpty) ...[
              const SizedBox(height: 12),
              DropdownButtonFormField<int?>(
                initialValue: _clubId,
                isExpanded: true,
                decoration: InputDecoration(labelText: t('commerceAdv.club')),
                items: [
                  DropdownMenuItem<int?>(
                    value: null,
                    child: Text(t('commerceAdv.noClub')),
                  ),
                  ...widget.clubs.map(
                    (club) => DropdownMenuItem<int?>(
                      value: _int(club['id']),
                      child: Text(_text(club['name'])),
                    ),
                  ),
                ],
                onChanged: _editing
                    ? null
                    : (value) => setState(() => _clubId = value),
              ),
            ],
            const SizedBox(height: 12),
            TextFormField(
              controller: _budget,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(
                labelText: t('commerceAdv.budgetEuro'),
                helperText: t('commerceAdv.minimumBudget').replaceFirst(
                  '{amount}',
                  _money(
                    widget.minimumBudgetCents > 0
                        ? widget.minimumBudgetCents
                        : 1000,
                  ),
                ),
              ),
              validator: (value) {
                final cents = _euroCents(value);
                final minimum = widget.minimumBudgetCents > 0
                    ? widget.minimumBudgetCents
                    : 1000;
                return cents < minimum ? t('commerceAdv.budgetTooLow') : null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _dailyBudget,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(
                labelText: t('commerceAdv.dailyBudgetEuro'),
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _interests,
              decoration: InputDecoration(
                labelText: t('commerceAdv.interests'),
                helperText: t('commerceAdv.interestsHint'),
              ),
            ),
            if (!_editing) ...[
              const SizedBox(height: 10),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                value: _startPayment,
                title: Text(t('commerceAdv.startPayment')),
                subtitle: Text(t('commerceAdv.startPaymentBody')),
                onChanged: (value) => setState(() {
                  _startPayment = value;
                  if (!value) _acceptedTerms = false;
                }),
              ),
              if (_startPayment)
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _acceptedTerms,
                  title: Text(t('commerceAdv.acceptTerms')),
                  controlAffinity: ListTileControlAffinity.leading,
                  onChanged: (value) =>
                      setState(() => _acceptedTerms = value == true),
                ),
            ],
            const SizedBox(height: 16),
            AirmiusButton(
              label: t('common.save'),
              icon: Icons.save_outlined,
              onPressed: _submit,
            ),
          ],
        ),
      ),
    );
  }

  void _submit() {
    final t = AirmiusScope.of(context).t;
    if (_form.currentState?.validate() != true) return;
    if (!_editing && _startPayment && !_acceptedTerms) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('commerceAdv.acceptTermsError'))),
      );
      return;
    }
    final payload = <String, dynamic>{
      'name': _name.text.trim(),
      'headline': _headline.text.trim(),
      'description': _description.text.trim(),
      'target_url': _targetUrl.text.trim(),
      'creative_image_url': _imageUrl.text.trim(),
      'objective': _objective,
      'placement': _placement,
      'creative_format': _format,
      'budget_cents': _euroCents(_budget.text),
      'daily_budget_cents': _euroCents(_dailyBudget.text),
      'audience_interests': _interests.text.trim(),
    };
    if (!_editing) {
      payload
        ..['club_id'] = _clubId
        ..['provider'] = 'bank_transfer'
        ..['start_payment'] = _startPayment
        ..['accepted_terms'] = _acceptedTerms
        ..['client_reference'] =
            'mobile-${DateTime.now().microsecondsSinceEpoch}';
    }
    Navigator.pop(context, payload);
  }
}

class _WebsiteRequestSheet extends StatefulWidget {
  const _WebsiteRequestSheet({required this.clubs});

  final List<Map<String, dynamic>> clubs;

  @override
  State<_WebsiteRequestSheet> createState() => _WebsiteRequestSheetState();
}

class _WebsiteRequestSheetState extends State<_WebsiteRequestSheet> {
  final _form = GlobalKey<FormState>();
  final _domain = TextEditingController();
  final _goals = TextEditingController();
  final _notes = TextEditingController();
  int? _clubId;
  bool _acceptedPrivacy = false;
  bool _submitted = false;

  @override
  void dispose() {
    _domain.dispose();
    _goals.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _SheetFrame(
      title: t('commerceAdv.requestWebsite'),
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (widget.clubs.isNotEmpty) ...[
              DropdownButtonFormField<int?>(
                initialValue: _clubId,
                isExpanded: true,
                decoration: InputDecoration(labelText: t('commerceAdv.club')),
                items: [
                  DropdownMenuItem<int?>(
                    value: null,
                    child: Text(t('commerceAdv.noClub')),
                  ),
                  ...widget.clubs.map(
                    (club) => DropdownMenuItem<int?>(
                      value: _int(club['id']),
                      child: Text(_text(club['name'])),
                    ),
                  ),
                ],
                onChanged: (value) => setState(() => _clubId = value),
              ),
              const SizedBox(height: 12),
            ],
            TextFormField(
              controller: _domain,
              decoration: InputDecoration(
                labelText: t('commerceAdv.domain'),
                hintText: 'mein-verein.de',
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _goals,
              minLines: 3,
              maxLines: 6,
              autovalidateMode: AutovalidateMode.onUserInteraction,
              decoration: InputDecoration(labelText: t('commerceAdv.goals')),
              validator: (value) => value == null || value.trim().isEmpty
                  ? t('commerceAdv.required')
                  : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _notes,
              minLines: 2,
              maxLines: 5,
              decoration: InputDecoration(labelText: t('commerceAdv.notes')),
            ),
            const SizedBox(height: 16),
            CheckboxListTile(
              value: _acceptedPrivacy,
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              title: Text(t('commerceAdv.privacyAccept')),
              subtitle: Text(t('commerceAdv.privacyAcceptBody')),
              onChanged: (value) => setState(() {
                _acceptedPrivacy = value ?? false;
              }),
            ),
            if (_submitted && !_acceptedPrivacy) ...[
              const SizedBox(height: 4),
              Text(
                t('commerceAdv.privacyAcceptError'),
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ],
            const SizedBox(height: 16),
            AirmiusButton(
              label: t('commerceAdv.sendRequest'),
              icon: Icons.send_outlined,
              onPressed: () {
                setState(() => _submitted = true);
                if (_form.currentState?.validate() != true ||
                    !_acceptedPrivacy) {
                  return;
                }
                Navigator.pop(context, {
                  'club_id': _clubId,
                  'domain': _domain.text.trim(),
                  'goals': _goals.text.trim(),
                  'notes': _notes.text.trim(),
                  'accepted_privacy': true,
                });
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _SellerApplicationSheet extends StatefulWidget {
  const _SellerApplicationSheet();

  @override
  State<_SellerApplicationSheet> createState() =>
      _SellerApplicationSheetState();
}

class _SellerApplicationSheetState extends State<_SellerApplicationSheet> {
  final _form = GlobalKey<FormState>();
  final _business = TextEditingController();
  final _notes = TextEditingController();
  String _type = 'private';
  bool _accepted = false;

  @override
  void dispose() {
    _business.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _SheetFrame(
      title: t('seller.applyTitle'),
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _type,
              decoration: InputDecoration(labelText: t('seller.sellerType')),
              items: [
                DropdownMenuItem(
                  value: 'private',
                  child: Text(t('seller.private')),
                ),
                DropdownMenuItem(
                  value: 'business',
                  child: Text(t('seller.business')),
                ),
                DropdownMenuItem(value: 'club', child: Text(t('seller.club'))),
              ],
              onChanged: (value) => setState(() => _type = value ?? 'private'),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _business,
              decoration: InputDecoration(labelText: t('seller.businessName')),
              textInputAction: TextInputAction.next,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _notes,
              decoration: InputDecoration(labelText: t('seller.notes')),
              minLines: 3,
              maxLines: 5,
            ),
            const SizedBox(height: 10),
            CheckboxListTile(
              value: _accepted,
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              title: Text(t('seller.acceptRules')),
              subtitle: Text(t('seller.acceptRulesBody')),
              onChanged: (value) => setState(() => _accepted = value ?? false),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: _accepted
                  ? () {
                      if (!(_form.currentState?.validate() ?? false)) return;
                      Navigator.pop(context, {
                        'applicant_type': _type,
                        if (_business.text.trim().isNotEmpty)
                          'business_name': _business.text.trim(),
                        if (_notes.text.trim().isNotEmpty)
                          'notes': _notes.text.trim(),
                        'rule_product_truth': true,
                        'rule_rights': true,
                        'rule_shipping_returns': true,
                        'rule_commission': true,
                        'rule_data_privacy': true,
                      });
                    }
                  : null,
              icon: Icon(Icons.send_outlined),
              label: Text(t('seller.sendApplication')),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProductSheet extends StatefulWidget {
  const _ProductSheet({this.product});

  final Map<String, dynamic>? product;

  @override
  State<_ProductSheet> createState() => _ProductSheetState();
}

class _ProductSheetState extends State<_ProductSheet> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _description;
  late final TextEditingController _price;
  late final TextEditingController _stock;
  late final TextEditingController _sku;
  late final TextEditingController _image;
  String _category = 'equipment';
  String _offerType = 'physical_product';
  bool _managesStock = true;
  bool _shippable = true;

  bool get _editing => widget.product != null;

  @override
  void initState() {
    super.initState();
    final product = widget.product ?? const <String, dynamic>{};
    _title = TextEditingController(text: _text(product['title']));
    _description = TextEditingController(text: _text(product['description']));
    _price = TextEditingController(
      text: product['price_cents'] == null
          ? ''
          : (_int(product['price_cents']) / 100).toStringAsFixed(2),
    );
    _stock = TextEditingController(
      text: product['stock_quantity'] == null
          ? ''
          : '${_int(product['stock_quantity'])}',
    );
    _sku = TextEditingController(text: _text(product['sku']));
    _image = TextEditingController(text: _text(product['image_url']));
    _category = _text(product['category'], fallback: 'equipment');
    _offerType = _text(product['offer_type'], fallback: 'physical_product');
    _managesStock = product.isEmpty || product['manages_stock'] == true;
    _shippable = product.isEmpty || product['is_shippable'] == true;
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _price.dispose();
    _stock.dispose();
    _sku.dispose();
    _image.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _SheetFrame(
      title: t(_editing ? 'seller.editProduct' : 'seller.addProduct'),
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextFormField(
              controller: _title,
              decoration: InputDecoration(labelText: t('seller.productTitle')),
              textInputAction: TextInputAction.next,
              validator: (value) => value == null || value.trim().isEmpty
                  ? t('seller.required')
                  : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _description,
              decoration: InputDecoration(labelText: t('seller.description')),
              minLines: 3,
              maxLines: 6,
            ),
            const SizedBox(height: 12),
            if (!_editing) ...[
              DropdownButtonFormField<String>(
                initialValue: _offerType,
                decoration: InputDecoration(labelText: t('seller.offerType')),
                items: [
                  DropdownMenuItem(
                    value: 'physical_product',
                    child: Text(t('seller.physicalProduct')),
                  ),
                  DropdownMenuItem(
                    value: 'online_course',
                    child: Text(t('seller.onlineCourse')),
                  ),
                  DropdownMenuItem(
                    value: 'training_plan',
                    child: Text(t('seller.trainingPlan')),
                  ),
                  DropdownMenuItem(
                    value: 'camp',
                    child: Text(t('seller.camp')),
                  ),
                ],
                onChanged: (value) {
                  setState(() {
                    _offerType = value ?? 'physical_product';
                    if (_offerType != 'physical_product') {
                      _managesStock = false;
                      _shippable = false;
                    }
                  });
                },
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _category,
                decoration: InputDecoration(labelText: t('seller.category')),
                items:
                    [
                          'equipment',
                          'apparel',
                          'nutrition',
                          'accessories',
                          'digital_products',
                          'course',
                          'camp',
                        ]
                        .map(
                          (value) => DropdownMenuItem(
                            value: value,
                            child: Text(t('seller.category.$value')),
                          ),
                        )
                        .toList(),
                onChanged: (value) =>
                    setState(() => _category = value ?? 'equipment'),
              ),
              const SizedBox(height: 12),
            ],
            TextFormField(
              controller: _price,
              decoration: InputDecoration(
                labelText: t('seller.priceEuro'),
                suffixText: 'EUR',
              ),
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              validator: (value) =>
                  _parseEuro(value) == null ? t('seller.validPrice') : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _sku,
              decoration: InputDecoration(labelText: t('seller.sku')),
              textInputAction: TextInputAction.next,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _image,
              decoration: InputDecoration(labelText: t('seller.imageUrl')),
              keyboardType: TextInputType.url,
              textInputAction: TextInputAction.next,
            ),
            if (_offerType == 'physical_product') ...[
              const SizedBox(height: 8),
              SwitchListTile(
                value: _shippable,
                contentPadding: EdgeInsets.zero,
                title: Text(t('seller.shippable')),
                onChanged: (value) => setState(() => _shippable = value),
              ),
              SwitchListTile(
                value: _managesStock,
                contentPadding: EdgeInsets.zero,
                title: Text(t('seller.manageStock')),
                onChanged: (value) => setState(() => _managesStock = value),
              ),
              if (_managesStock)
                TextFormField(
                  controller: _stock,
                  decoration: InputDecoration(
                    labelText: t('seller.stockQuantity'),
                  ),
                  keyboardType: TextInputType.number,
                  validator: (value) {
                    final parsed = int.tryParse(value?.trim() ?? '');
                    if (parsed == null || parsed < (_editing ? 0 : 1)) {
                      return t('seller.validStock');
                    }
                    return null;
                  },
                ),
            ],
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: () {
                if (!(_form.currentState?.validate() ?? false)) return;
                final price = _parseEuro(_price.text)!;
                Navigator.pop(context, {
                  'title': _title.text.trim(),
                  'description': _description.text.trim(),
                  'price_cents': price,
                  'sku': _sku.text.trim().isEmpty ? null : _sku.text.trim(),
                  'image_url': _image.text.trim().isEmpty
                      ? null
                      : _image.text.trim(),
                  'manages_stock': _managesStock,
                  'stock_quantity': _managesStock
                      ? int.parse(_stock.text.trim())
                      : null,
                  if (!_editing) ...{
                    'category': _category,
                    'offer_type': _offerType,
                    'product_type': _offerType == 'physical_product'
                        ? 'single'
                        : 'digital',
                    'is_shippable': _shippable,
                  },
                });
              },
              icon: Icon(Icons.save_outlined),
              label: Text(t('common.save')),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProviderSheet extends StatefulWidget {
  const _ProviderSheet({required this.profile});

  final Map<String, dynamic> profile;

  @override
  State<_ProviderSheet> createState() => _ProviderSheetState();
}

class _ProviderSheetState extends State<_ProviderSheet> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _displayName;
  late final TextEditingController _legalName;
  late final TextEditingController _description;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  late final TextEditingController _website;
  late final TextEditingController _country;
  late final TextEditingController _postalCode;
  late final TextEditingController _city;
  late final TextEditingController _street;
  late final TextEditingController _houseNumber;
  String _type = 'private';
  bool _showAddress = false;
  bool _showEmail = true;
  bool _showPhone = false;

  @override
  void initState() {
    super.initState();
    final p = widget.profile;
    _displayName = TextEditingController(text: _text(p['display_name']));
    _legalName = TextEditingController(text: _text(p['legal_name']));
    _description = TextEditingController(text: _text(p['public_description']));
    _email = TextEditingController(text: _text(p['support_email']));
    _phone = TextEditingController(text: _text(p['phone']));
    _website = TextEditingController(text: _text(p['website']));
    _country = TextEditingController(
      text: _text(p['legal_country'], fallback: 'DE'),
    );
    _postalCode = TextEditingController(text: _text(p['legal_postal_code']));
    _city = TextEditingController(text: _text(p['legal_city']));
    _street = TextEditingController(text: _text(p['legal_street']));
    _houseNumber = TextEditingController(text: _text(p['legal_house_number']));
    _type = _text(p['provider_type'], fallback: 'private');
    _showAddress = p['show_public_address'] == true;
    _showEmail = p.isEmpty || p['show_support_email'] == true;
    _showPhone = p['show_phone'] == true;
  }

  @override
  void dispose() {
    for (final controller in [
      _displayName,
      _legalName,
      _description,
      _email,
      _phone,
      _website,
      _country,
      _postalCode,
      _city,
      _street,
      _houseNumber,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _SheetFrame(
      title: t('seller.editProvider'),
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextFormField(
              controller: _displayName,
              decoration: InputDecoration(labelText: t('seller.displayName')),
              validator: (value) => value == null || value.trim().isEmpty
                  ? t('seller.required')
                  : null,
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _type,
              decoration: InputDecoration(labelText: t('seller.sellerType')),
              items: [
                DropdownMenuItem(
                  value: 'private',
                  child: Text(t('seller.private')),
                ),
                DropdownMenuItem(
                  value: 'business',
                  child: Text(t('seller.business')),
                ),
                DropdownMenuItem(value: 'club', child: Text(t('seller.club'))),
              ],
              onChanged: (value) => setState(() => _type = value ?? 'private'),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _legalName,
              decoration: InputDecoration(labelText: t('seller.legalName')),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _description,
              decoration: InputDecoration(
                labelText: t('seller.publicDescription'),
              ),
              minLines: 3,
              maxLines: 6,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _email,
              decoration: InputDecoration(labelText: t('seller.supportEmail')),
              keyboardType: TextInputType.emailAddress,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _phone,
              decoration: InputDecoration(labelText: t('seller.phone')),
              keyboardType: TextInputType.phone,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _website,
              decoration: InputDecoration(labelText: t('seller.website')),
              keyboardType: TextInputType.url,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  flex: 2,
                  child: TextFormField(
                    controller: _country,
                    decoration: InputDecoration(
                      labelText: t('seller.countryCode'),
                    ),
                    textCapitalization: TextCapitalization.characters,
                    maxLength: 2,
                    validator: (value) => value?.trim().length == 2
                        ? null
                        : t('seller.countryCodeHint'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  flex: 3,
                  child: TextFormField(
                    controller: _postalCode,
                    decoration: InputDecoration(
                      labelText: t('seller.postalCode'),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _city,
              decoration: InputDecoration(labelText: t('seller.city')),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  flex: 3,
                  child: TextFormField(
                    controller: _street,
                    decoration: InputDecoration(labelText: t('seller.street')),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: TextFormField(
                    controller: _houseNumber,
                    decoration: InputDecoration(
                      labelText: t('seller.houseNumber'),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            SwitchListTile(
              value: _showAddress,
              contentPadding: EdgeInsets.zero,
              title: Text(t('seller.showAddress')),
              onChanged: (value) => setState(() => _showAddress = value),
            ),
            SwitchListTile(
              value: _showEmail,
              contentPadding: EdgeInsets.zero,
              title: Text(t('seller.showEmail')),
              onChanged: (value) => setState(() => _showEmail = value),
            ),
            SwitchListTile(
              value: _showPhone,
              contentPadding: EdgeInsets.zero,
              title: Text(t('seller.showPhone')),
              onChanged: (value) => setState(() => _showPhone = value),
            ),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: () {
                if (!(_form.currentState?.validate() ?? false)) return;
                Navigator.pop(context, {
                  'display_name': _displayName.text.trim(),
                  'legal_name': _nullable(_legalName.text),
                  'provider_type': _type,
                  'support_email': _nullable(_email.text),
                  'phone': _nullable(_phone.text),
                  'website': _nullable(_website.text),
                  'public_description': _nullable(_description.text),
                  'legal_country': _country.text.trim().toUpperCase(),
                  'legal_postal_code': _nullable(_postalCode.text),
                  'legal_city': _nullable(_city.text),
                  'legal_street': _nullable(_street.text),
                  'legal_house_number': _nullable(_houseNumber.text),
                  'show_public_address': _showAddress,
                  'show_support_email': _showEmail,
                  'show_phone': _showPhone,
                });
              },
              icon: Icon(Icons.save_outlined),
              label: Text(t('common.save')),
            ),
          ],
        ),
      ),
    );
  }
}

class _LocationSheet extends StatefulWidget {
  const _LocationSheet({this.location});

  final Map<String, dynamic>? location;

  @override
  State<_LocationSheet> createState() => _LocationSheetState();
}

class _LocationSheetState extends State<_LocationSheet> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _country;
  late final TextEditingController _postal;
  late final TextEditingController _city;
  late final TextEditingController _street;
  late final TextEditingController _house;
  late final TextEditingController _hours;
  String _type = 'pickup';
  bool _pickup = true;
  bool _returns = false;
  bool _public = true;

  @override
  void initState() {
    super.initState();
    final l = widget.location ?? const <String, dynamic>{};
    _name = TextEditingController(text: _text(l['name']));
    _country = TextEditingController(text: _text(l['country'], fallback: 'DE'));
    _postal = TextEditingController(text: _text(l['postal_code']));
    _city = TextEditingController(text: _text(l['city']));
    _street = TextEditingController(text: _text(l['street']));
    _house = TextEditingController(text: _text(l['house_number']));
    _hours = TextEditingController(text: _text(l['opening_hours']));
    _type = _text(l['type'], fallback: 'pickup');
    _pickup = l.isEmpty || l['pickup_enabled'] == true;
    _returns = l['returns_enabled'] == true;
    _public = l.isEmpty || l['is_public'] == true;
  }

  @override
  void dispose() {
    for (final controller in [
      _name,
      _country,
      _postal,
      _city,
      _street,
      _house,
      _hours,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _SheetFrame(
      title: t(
        widget.location == null ? 'seller.addLocation' : 'seller.editLocation',
      ),
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextFormField(
              controller: _name,
              decoration: InputDecoration(labelText: t('seller.locationName')),
              validator: (value) => value == null || value.trim().isEmpty
                  ? t('seller.required')
                  : null,
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _type,
              decoration: InputDecoration(labelText: t('seller.locationType')),
              items: ['boutique', 'branch', 'pickup', 'warehouse', 'partner']
                  .map(
                    (value) => DropdownMenuItem(
                      value: value,
                      child: Text(t('seller.locationType.$value')),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _type = value ?? 'pickup'),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  flex: 2,
                  child: TextFormField(
                    controller: _country,
                    decoration: InputDecoration(
                      labelText: t('seller.countryCode'),
                    ),
                    maxLength: 2,
                    textCapitalization: TextCapitalization.characters,
                    validator: (value) => value?.trim().length == 2
                        ? null
                        : t('seller.countryCodeHint'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  flex: 3,
                  child: TextFormField(
                    controller: _postal,
                    decoration: InputDecoration(
                      labelText: t('seller.postalCode'),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _city,
              decoration: InputDecoration(labelText: t('seller.city')),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  flex: 3,
                  child: TextFormField(
                    controller: _street,
                    decoration: InputDecoration(labelText: t('seller.street')),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: TextFormField(
                    controller: _house,
                    decoration: InputDecoration(
                      labelText: t('seller.houseNumber'),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _hours,
              decoration: InputDecoration(labelText: t('seller.openingHours')),
              minLines: 2,
              maxLines: 4,
            ),
            const SizedBox(height: 8),
            SwitchListTile(
              value: _pickup,
              contentPadding: EdgeInsets.zero,
              title: Text(t('seller.pickupEnabled')),
              onChanged: (value) => setState(() => _pickup = value),
            ),
            SwitchListTile(
              value: _returns,
              contentPadding: EdgeInsets.zero,
              title: Text(t('seller.returnsEnabled')),
              onChanged: (value) => setState(() => _returns = value),
            ),
            SwitchListTile(
              value: _public,
              contentPadding: EdgeInsets.zero,
              title: Text(t('seller.publicLocation')),
              onChanged: (value) => setState(() => _public = value),
            ),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: () {
                if (!(_form.currentState?.validate() ?? false)) return;
                Navigator.pop(context, {
                  'name': _name.text.trim(),
                  'type': _type,
                  'country': _country.text.trim().toUpperCase(),
                  'postal_code': _nullable(_postal.text),
                  'city': _nullable(_city.text),
                  'street': _nullable(_street.text),
                  'house_number': _nullable(_house.text),
                  'opening_hours': _nullable(_hours.text),
                  'pickup_enabled': _pickup,
                  'returns_enabled': _returns,
                  'is_public': _public,
                });
              },
              icon: Icon(Icons.save_outlined),
              label: Text(t('common.save')),
            ),
          ],
        ),
      ),
    );
  }
}

class _PayoutProfileSheet extends StatefulWidget {
  const _PayoutProfileSheet({required this.profile});

  final Map<String, dynamic> profile;

  @override
  State<_PayoutProfileSheet> createState() => _PayoutProfileSheetState();
}

class _PayoutProfileSheetState extends State<_PayoutProfileSheet> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _holder;
  late final TextEditingController _iban;
  late final TextEditingController _bic;
  late final TextEditingController _paypal;
  late final TextEditingController _tax;

  @override
  void initState() {
    super.initState();
    final p = widget.profile;
    _holder = TextEditingController(text: _text(p['account_holder']));
    _iban = TextEditingController(text: _text(p['iban']));
    _bic = TextEditingController(text: _text(p['bic']));
    _paypal = TextEditingController(text: _text(p['paypal_email']));
    _tax = TextEditingController(text: _text(p['tax_number']));
  }

  @override
  void dispose() {
    _holder.dispose();
    _iban.dispose();
    _bic.dispose();
    _paypal.dispose();
    _tax.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _SheetFrame(
      title: t('seller.payoutDetails'),
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextFormField(
              controller: _holder,
              decoration: InputDecoration(labelText: t('seller.accountHolder')),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _iban,
              decoration: InputDecoration(labelText: t('seller.iban')),
              textCapitalization: TextCapitalization.characters,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _bic,
              decoration: InputDecoration(labelText: t('seller.bic')),
              textCapitalization: TextCapitalization.characters,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _paypal,
              decoration: InputDecoration(labelText: t('seller.paypalEmail')),
              keyboardType: TextInputType.emailAddress,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _tax,
              decoration: InputDecoration(labelText: t('seller.taxNumber')),
            ),
            const SizedBox(height: 10),
            Text(
              t('seller.payoutSecurity'),
              style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
            ),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: () {
                if (_iban.text.trim().isEmpty && _paypal.text.trim().isEmpty) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(t('seller.ibanOrPaypal'))),
                  );
                  return;
                }
                Navigator.pop(context, {
                  'account_holder': _nullable(_holder.text),
                  'iban': _nullable(_iban.text)?.replaceAll(' ', ''),
                  'bic': _nullable(_bic.text),
                  'paypal_email': _nullable(_paypal.text),
                  'tax_number': _nullable(_tax.text),
                });
              },
              icon: Icon(Icons.lock_outline),
              label: Text(t('common.save')),
            ),
          ],
        ),
      ),
    );
  }
}

class _PayoutRequestSheet extends StatefulWidget {
  const _PayoutRequestSheet({required this.profile});

  final Map<String, dynamic> profile;

  @override
  State<_PayoutRequestSheet> createState() => _PayoutRequestSheetState();
}

class _PayoutRequestSheetState extends State<_PayoutRequestSheet> {
  final _notes = TextEditingController();
  late String _method;

  @override
  void initState() {
    super.initState();
    _method = _text(widget.profile['iban']).isNotEmpty
        ? 'bank_transfer'
        : 'paypal';
  }

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _SheetFrame(
      title: t('seller.requestPayout'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          RadioGroup<String>(
            groupValue: _method,
            onChanged: (value) {
              if (value != null) setState(() => _method = value);
            },
            child: Column(
              children: [
                RadioListTile<String>(
                  value: 'bank_transfer',
                  title: Text(t('seller.bankTransfer')),
                  enabled: _text(widget.profile['iban']).isNotEmpty,
                ),
                RadioListTile<String>(
                  value: 'paypal',
                  title: const Text('PayPal'),
                  enabled: _text(widget.profile['paypal_email']).isNotEmpty,
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _notes,
            decoration: InputDecoration(labelText: t('seller.notesOptional')),
            minLines: 2,
            maxLines: 4,
          ),
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: () => Navigator.pop(context, {
              'method': _method,
              'notes': _notes.text.trim(),
            }),
            icon: Icon(Icons.payments_outlined),
            label: Text(t('seller.requestPayout')),
          ),
        ],
      ),
    );
  }
}

class _OrderSheet extends StatelessWidget {
  const _OrderSheet({required this.order});

  final Map<String, dynamic> order;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final items = _maps(order['items']);
    final product = _map(order['product']);
    final buyer = _map(order['buyer']);
    final address = _map(order['shipping_address']);
    final issue = _map(order['issue']);
    final returns = _maps(order['return_requests']);
    return _SheetFrame(
      title: _text(order['reference'], fallback: t('seller.order')),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              StatusPill(
                _status(order['status']),
                color: _commerceTone(context, AirmiusColors.green),
              ),
              StatusPill(
                _status(order['shipping_status']),
                color: airmiusAccentColor(context),
              ),
              StatusPill(
                _status(order['payout_status']),
                color: _commerceTone(context, AirmiusColors.amber),
              ),
            ],
          ),
          const SizedBox(height: 16),
          _ValueRow(
            label: t('seller.orderValue'),
            value: _money(order['seller_gross_cents']),
          ),
          _ValueRow(
            label: t('seller.commission'),
            value: _money(order['seller_commission_cents']),
          ),
          _ValueRow(
            label: t('seller.buyer'),
            value: _text(buyer['name'], fallback: '–'),
          ),
          if (_text(buyer['email']).isNotEmpty)
            _ValueRow(label: t('seller.email'), value: _text(buyer['email'])),
          const SizedBox(height: 14),
          Text(
            t('seller.positions'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
              fontSize: 17,
            ),
          ),
          const SizedBox(height: 8),
          if (product.isNotEmpty)
            _OrderItem(
              title: _text(product['title']),
              detail: _text(product['sku']),
            ),
          ...items.map(
            (item) => _OrderItem(
              title: _text(item['title']),
              detail:
                  '${_int(item['quantity'])} × ${_money((_int(item['total_cents']) / (_int(item['quantity']).clamp(1, 999))).round())}',
            ),
          ),
          if (address.isNotEmpty) ...[
            const SizedBox(height: 14),
            Text(
              t('seller.shippingAddress'),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
                fontSize: 17,
              ),
            ),
            const SizedBox(height: 7),
            SelectableText(
              [
                _text(address['name']),
                _text(address['company']),
                '${_text(address['street'])} ${_text(address['house_number'])}'
                    .trim(),
                '${_text(address['postal_code'])} ${_text(address['city'])}'
                    .trim(),
                _text(address['country']),
              ].where((value) => value.isNotEmpty).join('\n'),
              style: TextStyle(color: airmiusMutedColor(context), height: 1.45),
            ),
          ],
          if (_text(issue['status'], fallback: 'none') != 'none') ...[
            const SizedBox(height: 14),
            _InfoBox(
              title: t('seller.issue'),
              body: _text(issue['note'], fallback: _status(issue['status'])),
              color: _commerceTone(context, AirmiusColors.red),
            ),
          ],
          if (returns.isNotEmpty) ...[
            const SizedBox(height: 12),
            _InfoBox(
              title: t('seller.returns'),
              body: returns
                  .map(
                    (item) =>
                        '${_status(item['status'])}: ${_text(item['reason'])}',
                  )
                  .join('\n'),
              color: _commerceTone(context, AirmiusColors.amber),
            ),
          ],
        ],
      ),
    );
  }
}

class _OrderItem extends StatelessWidget {
  const _OrderItem({required this.title, required this.detail});

  final String title;
  final String detail;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Icon(Icons.inventory_2_outlined, color: airmiusAccentColor(context)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          if (detail.isNotEmpty)
            Text(detail, style: TextStyle(color: airmiusMutedColor(context))),
        ],
      ),
    );
  }
}

class _InfoBox extends StatelessWidget {
  const _InfoBox({
    required this.title,
    required this.body,
    required this.color,
  });

  final String title;
  final String body;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: color.withValues(alpha: 0.4)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: TextStyle(color: color, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 5),
          Text(
            body,
            style: TextStyle(color: airmiusTextColor(context), height: 1.4),
          ),
        ],
      ),
    );
  }
}

class _ValueRow extends StatelessWidget {
  const _ValueRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(
              label,
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Column(
          children: [
            Icon(icon, color: airmiusMutedColor(context), size: 42),
            const SizedBox(height: 10),
            Text(
              title,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
                fontSize: 18,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              body,
              textAlign: TextAlign.center,
              style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final message = error is AirmiusApiException
        ? (error! as AirmiusApiException).statusCode == 599
              ? t('common.errorDetails')
              : (error! as AirmiusApiException).userMessage
        : t('common.errorDetails');
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        _EmptyState(
          icon: Icons.cloud_off_outlined,
          title: t('seller.loadFailed'),
          body: message,
        ),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: onRetry,
          icon: Icon(Icons.refresh),
          label: Text(t('common.retry')),
        ),
      ],
    );
  }
}

class _SheetFrame extends StatelessWidget {
  const _SheetFrame({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.92,
      minChildSize: 0.55,
      maxChildSize: 0.98,
      builder: (context, controller) => Material(
        color: Theme.of(context).scaffoldBackgroundColor,
        child: ListView(
          controller: controller,
          padding: EdgeInsets.fromLTRB(
            18,
            14,
            18,
            24 + MediaQuery.viewInsetsOf(context).bottom,
          ),
          children: [
            Center(
              child: Container(
                width: 46,
                height: 5,
                decoration: BoxDecoration(
                  color: airmiusBorderColor(context),
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 23,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: MaterialLocalizations.of(context).closeButtonTooltip,
                  onPressed: () => Navigator.pop(context),
                  icon: Icon(Icons.close),
                ),
              ],
            ),
            const SizedBox(height: 14),
            child,
          ],
        ),
      ),
    );
  }
}

Color _commerceTone(BuildContext context, Color semanticColor) {
  final scheme = Theme.of(context).colorScheme;
  if (semanticColor == AirmiusColors.green) return scheme.secondary;
  if (semanticColor == AirmiusColors.amber) return scheme.tertiary;
  if (semanticColor == AirmiusColors.red) return scheme.error;
  return scheme.primary;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _maps(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <Map<String, dynamic>>[];

String _text(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

String _money(Object? cents) {
  final amount = _int(cents) / 100;
  return NumberFormat.simpleCurrency(name: 'EUR').format(amount);
}

int? _parseEuro(String? value) {
  final normalized = (value ?? '').trim().replaceAll(',', '.');
  final parsed = double.tryParse(normalized);
  if (parsed == null || parsed < 0) return null;
  return (parsed * 100).round();
}

int _euroCents(String? value) => _parseEuro(value) ?? 0;

String? _nullable(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}

String _status(Object? value) {
  final status = _text(value, fallback: '–');
  return status
      .replaceAll('_', ' ')
      .split(' ')
      .map(
        (part) => part.isEmpty
            ? part
            : '${part.substring(0, 1).toUpperCase()}${part.substring(1)}',
      )
      .join(' ');
}
