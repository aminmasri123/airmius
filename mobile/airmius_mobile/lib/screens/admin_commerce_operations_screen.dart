import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

class AdminCommerceOperationsScreen extends StatefulWidget {
  const AdminCommerceOperationsScreen({super.key});

  @override
  State<AdminCommerceOperationsScreen> createState() =>
      _AdminCommerceOperationsScreenState();
}

class _AdminCommerceOperationsScreenState
    extends State<AdminCommerceOperationsScreen> {
  Future<_CommerceOpsData>? _future;
  String _section = 'catalog';
  final Map<String, String> _queries = {};
  final Map<String, TextEditingController> _searches = {};
  final Set<String> _invalidSearches = {};
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void dispose() {
    for (final controller in _searches.values) {
      controller.dispose();
    }
    super.dispose();
  }

  void _filter(String list, String field, String value) {
    if (field == 'search' && value.runes.length > 200) {
      setState(() => _invalidSearches.add(list));
      return;
    }
    _invalidSearches.remove(list);
    _queries['${list}_$field'] = value;
    _queries['${list}_page'] = '1';
    _reload();
  }

  void _resetFilters() {
    _queries.clear();
    _invalidSearches.clear();
    for (final controller in _searches.values) {
      controller.clear();
    }
    _reload();
  }

  Widget _listControls(String list, _CommerceOpsData data) {
    final pages = {
      ..._map(data.dashboard['pagination']),
      ..._map(data.catalog['pagination']),
    };
    final meta = _map(pages[list] ?? _map(data.dashboard[list])['meta']);
    final page = _int(meta['current_page'] ?? 1);
    final last = _int(meta['last_page'] ?? 1);
    final controller = _searches.putIfAbsent(
      list,
      () => TextEditingController(text: _queries['${list}_search'] ?? ''),
    );
    final statuses = _commerceListStatuses[list] ?? const <String>[];
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        children: [
          TextField(
            key: ValueKey('commerce-$list-search'),
            controller: controller,
            enabled: !_busy,
            maxLength: 200,
            textInputAction: TextInputAction.search,
            onSubmitted: (value) => _filter(list, 'search', value.trim()),
            onChanged: (_) {
              if (_invalidSearches.contains(list)) {
                setState(() => _invalidSearches.remove(list));
              }
            },
            decoration: InputDecoration(
              labelText: t('adminNative.search'),
              counterText: '',
              errorText: !_invalidSearches.contains(list)
                  ? null
                  : switch (AirmiusScope.of(context).language) {
                      AirmiusLanguage.de => 'Höchstens 200 Zeichen.',
                      AirmiusLanguage.en => 'Use at most 200 characters.',
                      AirmiusLanguage.fr => '200 caractères maximum.',
                      AirmiusLanguage.ar => '200 حرف كحد أقصى.',
                    },
              suffixIcon: IconButton(
                tooltip: t('adminNative.search'),
                onPressed: _busy
                    ? null
                    : () => _filter(list, 'search', controller.text.trim()),
                icon: const Icon(Icons.search),
              ),
            ),
          ),
          if (statuses.isNotEmpty)
            DropdownButtonFormField<String>(
              key: ValueKey(
                'commerce-$list-status-${_queries['${list}_status'] ?? ''}',
              ),
              initialValue: _queries['${list}_status'] ?? '',
              isExpanded: true,
              decoration: InputDecoration(labelText: t('commerceOps.status')),
              items: [
                DropdownMenuItem(value: '', child: Text(t('commerceOps.all'))),
                for (final status in statuses)
                  DropdownMenuItem(
                    value: status,
                    child: Text(
                      status == '1'
                          ? t('commerceOps.active')
                          : status == '0'
                          ? t('commerceOps.inactive')
                          : status,
                    ),
                  ),
              ],
              onChanged: _busy
                  ? null
                  : (value) => _filter(list, 'status', value ?? ''),
            ),
          Row(
            children: [
              Expanded(child: Text('$page / $last · ${_int(meta['total'])}')),
              IconButton(
                key: ValueKey('commerce-$list-previous'),
                tooltip: MaterialLocalizations.of(context).previousPageTooltip,
                onPressed: _busy || page <= 1
                    ? null
                    : () {
                        _queries['${list}_page'] = '${page - 1}';
                        _reload();
                      },
                icon: const Icon(Icons.chevron_left),
              ),
              IconButton(
                key: ValueKey('commerce-$list-next'),
                tooltip: MaterialLocalizations.of(context).nextPageTooltip,
                onPressed: _busy || page >= last
                    ? null
                    : () {
                        _queries['${list}_page'] = '${page + 1}';
                        _reload();
                      },
                icon: const Icon(Icons.chevron_right),
              ),
            ],
          ),
        ],
      ),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _startLoad();
  }

  Future<_CommerceOpsData> _startLoad() {
    final future = _load();
    // A fast failure may arrive before the next frame attaches FutureBuilder.
    future.ignore();
    return future;
  }

  Future<_CommerceOpsData> _load() async {
    final responses = await Future.wait([
      _client.adminCommerceDashboard(query: Map.of(_queries)),
      _client.adminCommerceCatalog(query: Map.of(_queries)),
    ]);
    return _CommerceOpsData(
      dashboard: _map(responses[0]['data']),
      catalog: _map(responses[1]['data']),
    );
  }

  void _reload() {
    setState(() {
      _future = _startLoad();
    });
  }

  Future<void> _run(
    Future<dynamic> Function() action, {
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

  Future<void> _exportOrders() async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final csv = await _client.adminCommerceExportCsv(
        search: _queries['orders_search'] ?? '',
        status: _queries['orders_status'] ?? '',
      );
      final date = DateFormat('yyyy-MM-dd').format(DateTime.now());
      try {
        final saved = await FilePicker.platform.saveFile(
          dialogTitle: t('commerceOps.exportOrders'),
          fileName: 'airmius-commerce-orders-$date.csv',
          type: FileType.custom,
          allowedExtensions: const ['csv'],
          bytes: Uint8List.fromList(
            utf8.encode(csv.startsWith('\uFEFF') ? csv : '\uFEFF$csv'),
          ),
        );
        if (!mounted || saved == null) return;
        _toast(t('commerceOps.exportSaved'));
      } catch (_) {
        if (!mounted) return;
        await _showExportFallback(csv);
      }
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
    } catch (error) {
      if (mounted) {
        _toast(error is StateError ? error.message : t('common.errorDetails'));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _showExportFallback(String csv) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 4, 18, 24),
          child: Column(
            children: [
              Text(
                t('commerceOps.exportReady'),
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
              Expanded(
                child: SingleChildScrollView(
                  child: SelectableText(
                    csv,
                    style: const TextStyle(
                      fontFamily: 'monospace',
                      fontSize: 12,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              AirmiusButton(
                label: t('commerceOps.copyExport'),
                icon: Icons.copy_all_outlined,
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: csv));
                  if (!context.mounted) return;
                  Navigator.pop(context);
                  _toast(t('commerceOps.exportCopied'));
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('commerceOps.title'),
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
      body: FutureBuilder<_CommerceOpsData>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final error = snapshot.error;
            return _Failure(
              message: error is AirmiusApiException
                  ? error.userMessage
                  : t('adminHub.loadFailed'),
              onRetry: _reload,
              onReset: _queries.isEmpty ? null : _resetFilters,
            );
          }
          final data =
              snapshot.data ??
              const _CommerceOpsData(dashboard: {}, catalog: {});
          return PageFrame(
            title: t('commerceOps.title'),
            subtitle: t('commerceOps.subtitle'),
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
                switch (_section) {
                  'leads' => _leads(data),
                  'fulfillment' => _fulfillment(data),
                  'payouts' => _payouts(data),
                  'campaigns' => _campaigns(data),
                  'applications' => _applications(data),
                  'audit' => _audit(data),
                  'settings' => _settings(data),
                  _ => _catalog(data),
                },
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _hero(_CommerceOpsData data) {
    final summary = _map(data.dashboard['summary']);
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('commerceOps.eyebrow')),
          const SizedBox(height: 7),
          Text(
            t('commerceOps.headline'),
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 7),
          Text(t('commerceOps.body')),
          const SizedBox(height: 14),
          Wrap(
            spacing: 9,
            runSpacing: 9,
            children: [
              _Metric(
                value: '${_int(summary['products'])}',
                label: t('adminHub.products'),
              ),
              _Metric(
                value: '${_int(summary['orders'])}',
                label: t('adminHub.orders'),
              ),
              _Metric(
                value: '${_list(data.dashboard['return_requests']).length}',
                label: t('commerceOps.returns'),
              ),
              _Metric(
                value: '${_list(data.dashboard['payout_candidates']).length}',
                label: t('commerceOps.payoutCandidates'),
              ),
              OutlinedButton.icon(
                onPressed: _busy ? null : _exportOrders,
                icon: const Icon(Icons.download_outlined),
                label: Text(t('commerceOps.exportOrders')),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _sectionPicker() {
    final sections = {
      'catalog': (Icons.inventory_2_outlined, t('commerceOps.catalog')),
      'leads': (Icons.contact_mail_outlined, t('commerceOps.leads')),
      'fulfillment': (
        Icons.local_shipping_outlined,
        t('commerceOps.fulfillment'),
      ),
      'payouts': (
        Icons.account_balance_wallet_outlined,
        t('commerceOps.payouts'),
      ),
      'campaigns': (Icons.campaign_outlined, t('commerceOps.campaigns')),
      'applications': (
        Icons.storefront_outlined,
        t('adminHub.sellerApplications'),
      ),
      'audit': (Icons.history_outlined, t('release.auditActivityTimeline')),
      'settings': (Icons.tune_outlined, t('commerceOps.settings')),
    };
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: sections.entries
            .map(
              (entry) => Padding(
                padding: const EdgeInsetsDirectional.only(end: 8),
                child: ChoiceChip(
                  avatar: Icon(entry.value.$1, size: 19),
                  selected: _section == entry.key,
                  label: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 7),
                    child: Text(entry.value.$2),
                  ),
                  onSelected: (_) => setState(() => _section = entry.key),
                ),
              ),
            )
            .toList(),
      ),
    );
  }

  Widget _catalog(_CommerceOpsData data) {
    final products = _paged(data.dashboard['products']);
    final coupons = _list(data.catalog['coupons']);
    final addons = _list(data.catalog['addons']);
    final taxRates = _list(data.catalog['tax_rates']);
    final shippingRates = _list(data.catalog['shipping_rates']);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          title: t('commerceOps.products'),
          body: t('commerceOps.productsBody'),
          icon: Icons.inventory_2_outlined,
          onAdd: () => _editProduct(),
        ),
        _listControls('products', data),
        if (products.isEmpty)
          _empty(Icons.inventory_2_outlined, t('commerceOps.noEntries')),
        ...products.map(_productCard),
        const SizedBox(height: 4),
        _entityGroup(
          listKey: 'coupons',
          data: data,
          title: t('commerceOps.coupons'),
          icon: Icons.sell_outlined,
          items: coupons,
          onAdd: () => _editCoupon(),
          itemBuilder: (item) => _simpleCard(
            title: _text(item['name'], fallback: _text(item['code'])),
            status: _bool(item['is_active'])
                ? t('commerceOps.active')
                : t('commerceOps.inactive'),
            subtitle:
                '${_text(item['code'])} · ${_text(item['type']) == 'percent' ? '${_int(item['percent_off'])}%' : _money(_int(item['value_cents']))}',
            onEdit: () => _editCoupon(item),
          ),
        ),
        _entityGroup(
          listKey: 'addons',
          data: data,
          title: t('commerceOps.addons'),
          icon: Icons.extension_outlined,
          items: addons,
          onAdd: () => _editAddon(),
          itemBuilder: (item) => _simpleCard(
            title: _text(item['name']),
            status: _bool(item['is_active'])
                ? t('commerceOps.active')
                : t('commerceOps.inactive'),
            subtitle:
                '${_money(_int(item['monthly_price_cents']))} / ${t('commerceOps.month')}',
            onEdit: () => _editAddon(item),
          ),
        ),
        _entityGroup(
          listKey: 'tax_rates',
          data: data,
          title: t('commerceOps.taxRates'),
          icon: Icons.percent_outlined,
          items: taxRates,
          onAdd: () => _editTaxRate(),
          itemBuilder: (item) => _simpleCard(
            title: _text(item['name']),
            status: _bool(item['is_active'])
                ? t('commerceOps.active')
                : t('commerceOps.inactive'),
            subtitle:
                '${_text(item['country_code'])} · ${_text(item['rate_percent'])}% ${_text(item['tax_label'])}',
            onEdit: () => _editTaxRate(item),
          ),
        ),
        _entityGroup(
          listKey: 'shipping_rates',
          data: data,
          title: t('commerceOps.shippingRates'),
          icon: Icons.local_shipping_outlined,
          items: shippingRates,
          onAdd: () => _editShippingRate(),
          itemBuilder: (item) => _simpleCard(
            title: _text(item['name']),
            status: _bool(item['is_active'])
                ? t('commerceOps.active')
                : t('commerceOps.inactive'),
            subtitle:
                '${_text(item['country_code'], fallback: t('commerceOps.allCountries'))} · ${_money(_int(item['amount_cents']))}',
            onEdit: () => _editShippingRate(item),
          ),
        ),
      ],
    );
  }

  Widget _leads(_CommerceOpsData data) {
    final leads = _list(data.catalog['public_contact_requests']);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          title: t('commerceOps.leads'),
          body: t('commerceOps.leadsBody'),
          icon: Icons.contact_mail_outlined,
        ),
        _listControls('public_contact_requests', data),
        const SizedBox(height: 12),
        if (leads.isEmpty)
          _empty(Icons.mark_email_read_outlined, t('commerceOps.noLeads'))
        else
          ...leads.map((lead) {
            final created = _text(lead['created_at']);
            final details = [
              _text(lead['email']),
              _text(lead['category']),
              _text(lead['platform']),
              if (created.length >= 10) created.substring(0, 10),
            ].where((value) => value.isNotEmpty).join(' · ');
            final message = _text(lead['message']);
            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _simpleCard(
                title: _text(
                  lead['subject'],
                  fallback: _text(
                    lead['name'],
                    fallback: t('commerceOps.lead'),
                  ),
                ),
                status: _leadStatus(_text(lead['status'], fallback: 'new')),
                subtitle: [
                  details,
                  message,
                ].where((value) => value.isNotEmpty).join('\n'),
                onEdit: () => _editLead(lead),
                actionLabel: t('commerceOps.reviewLead'),
              ),
            );
          }),
      ],
    );
  }

  String _leadStatus(String status) => switch (status) {
    'new' => t('commerceOps.leadNew'),
    'in_progress' => t('commerceOps.leadInProgress'),
    'approved' => t('commerceOps.leadApproved'),
    'completed' => t('commerceOps.leadCompleted'),
    _ => t('commerceOps.all'),
  };

  Future<void> _editLead(Map<String, dynamic> lead) async {
    final values = await _form(t('commerceOps.reviewLead'), [
      _Field.choice(
        'status',
        t('commerceOps.leadStatus'),
        initial: _text(lead['status'], fallback: 'new'),
        choices: const ['new', 'in_progress', 'approved', 'completed'],
        choiceLabels: {
          'new': t('commerceOps.leadNew'),
          'in_progress': t('commerceOps.leadInProgress'),
          'approved': t('commerceOps.leadApproved'),
          'completed': t('commerceOps.leadCompleted'),
        },
      ),
      _Field.multiline(
        'internal_notes',
        t('commerceOps.internalNotes'),
        initial: _text(lead['internal_notes']),
      ),
    ]);
    if (values == null) return;
    await _run(
      () => _client.adminUpdatePublicContactRequest(_int(lead['id']), {
        'status': values['status'],
        'internal_notes': _nullable(values['internal_notes']),
      }),
      success: t('commerceOps.leadSaved'),
    );
  }

  Widget _productCard(Map<String, dynamic> product) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _cardHeader(
              Icons.inventory_2_outlined,
              _text(product['title'], fallback: t('adminHub.product')),
              _text(product['status'], fallback: 'draft'),
            ),
            const SizedBox(height: 8),
            Text(
              '${_money(_int(product['price_cents']))} · ${_text(product['category'], fallback: '–')}',
            ),
            if (_bool(product['manages_stock'])) ...[
              const SizedBox(height: 5),
              Text(
                '${t('commerceOps.stock')}: ${_int(product['stock_quantity'])}',
                style: TextStyle(
                  color:
                      _int(product['stock_quantity']) <=
                          _int(product['low_stock_threshold'])
                      ? Theme.of(context).colorScheme.error
                      : null,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                FilledButton.tonalIcon(
                  onPressed: _busy ? null : () => _editProduct(product),
                  icon: const Icon(Icons.edit_outlined),
                  label: Text(t('common.edit')),
                ),
                if (_bool(product['manages_stock']))
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _adjustStock(product),
                    icon: const Icon(Icons.inventory_outlined),
                    label: Text(t('commerceOps.adjustStock')),
                  ),
                OutlinedButton.icon(
                  onPressed: _busy ? null : () => _deleteProduct(product),
                  icon: const Icon(Icons.delete_outline),
                  label: Text(t('common.delete')),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _fulfillment(_CommerceOpsData data) {
    final orders = _paged(data.dashboard['orders']);
    final returns = _list(data.dashboard['return_requests']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          title: t('commerceOps.orders'),
          body: t('commerceOps.ordersBody'),
          icon: Icons.receipt_long_outlined,
        ),
        _listControls('orders', data),
        if (orders.isEmpty)
          _empty(Icons.receipt_long_outlined, t('adminHub.noOrders'))
        else
          ...orders.map(_orderCard),
        const SizedBox(height: 8),
        _sectionHeader(
          title: t('commerceOps.returns'),
          body: t('commerceOps.returnsBody'),
          icon: Icons.assignment_return_outlined,
        ),
        _listControls('return_requests', data),
        if (returns.isEmpty)
          _empty(Icons.assignment_return_outlined, t('commerceOps.noReturns'))
        else
          ...returns.map(_returnCard),
      ],
    );
  }

  Widget _orderCard(Map<String, dynamic> order) {
    final issueStatus = _text(order['issue_status'], fallback: 'none');
    final refunded = _int(order['refunded_cents']);
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _cardHeader(
              Icons.receipt_long_outlined,
              _text(order['reference'], fallback: '#${_int(order['id'])}'),
              _text(order['status'], fallback: 'open'),
            ),
            const SizedBox(height: 8),
            Text(
              '${_money(_int(order['amount_cents']))} · ${t('commerceOps.shipping')}: ${_text(order['shipping_status'], fallback: 'open')}',
            ),
            if (issueStatus != 'none') ...[
              const SizedBox(height: 5),
              Text(
                '${t('commerceOps.issue')}: $issueStatus',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
            if (refunded > 0) ...[
              const SizedBox(height: 5),
              Text('${t('commerceOps.refunded')}: ${_money(refunded)}'),
            ],
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                OutlinedButton.icon(
                  onPressed: _busy ? null : () => _editShipping(order),
                  icon: const Icon(Icons.local_shipping_outlined),
                  label: Text(t('commerceOps.shipping')),
                ),
                OutlinedButton.icon(
                  onPressed: _busy ? null : () => _editIssue(order),
                  icon: const Icon(Icons.support_agent_outlined),
                  label: Text(t('commerceOps.issue')),
                ),
                if (![
                  'none',
                  'resolved',
                  'refunded',
                  'cancelled',
                ].contains(issueStatus))
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _replyIssue(order),
                    icon: const Icon(Icons.reply_outlined),
                    label: Text(t('commerceOps.reply')),
                  ),
                OutlinedButton.icon(
                  onPressed: _busy ? null : () => _refundOrder(order),
                  icon: const Icon(Icons.currency_exchange_outlined),
                  label: Text(t('commerceOps.refund')),
                ),
                OutlinedButton.icon(
                  onPressed: _busy ? null : () => _documents(order),
                  icon: const Icon(Icons.picture_as_pdf_outlined),
                  label: Text(t('commerceOps.documents')),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _returnCard(Map<String, dynamic> item) {
    final order = _map(item['order']);
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: _simpleCard(
        title:
            '${t('commerceOps.return')} #${_int(item['id'])} · ${t('commerceOps.order')} #${_int(item['commerce_order_id'] ?? order['id'])}',
        status: _text(item['status'], fallback: 'requested'),
        subtitle:
            '${_text(item['reason'], fallback: '–')} · ${_money(_int(item['requested_amount_cents']))}',
        onEdit: () => _editReturn(item),
      ),
    );
  }

  String _payoutSubtitle(Map<String, dynamic> payout) {
    final parts = <String>[
      _money(_int(payout['amount_cents']), _text(payout['currency'])),
      _text(payout['method']),
    ];
    final recovery = _int(payout['recovery_cents']);
    final adjustment = _int(payout['adjustment_cents']);
    if (recovery > 0) {
      parts.add(
        '${t('commerceOps.recoveryRequired')}: ${_money(recovery, _text(payout['currency']))}',
      );
    } else if (adjustment > 0) {
      parts.add(
        '${t('commerceOps.refundAdjustment')}: ${_money(adjustment, _text(payout['currency']))}',
      );
    }

    return parts.join(' · ');
  }

  Widget _payouts(_CommerceOpsData data) {
    final candidates = _list(data.dashboard['payout_candidates']);
    final payouts = _list(data.dashboard['payouts']);
    final profiles = _list(data.dashboard['payout_profiles']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          title: t('commerceOps.payoutCandidates'),
          body: t('commerceOps.payoutCandidatesBody'),
          icon: Icons.savings_outlined,
        ),
        if (candidates.isEmpty)
          _empty(Icons.savings_outlined, t('commerceOps.noPayoutCandidates'))
        else
          ...candidates.map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _simpleCard(
                title: _text(
                  item['name'],
                  fallback: _text(item['email'], fallback: 'User'),
                ),
                status:
                    '${_int(item['orders_count'])} ${t('commerceOps.ordersShort')}',
                subtitle: _money(
                  _int(item['amount_cents']),
                  _text(item['currency']),
                ),
                actionLabel: t('commerceOps.preparePayout'),
                onEdit: () => _preparePayout(item),
              ),
            ),
          ),
        const SizedBox(height: 8),
        _sectionHeader(
          title: t('commerceOps.payouts'),
          body: t('commerceOps.payoutsBody'),
          icon: Icons.account_balance_wallet_outlined,
        ),
        _listControls('payouts', data),
        if (payouts.isEmpty)
          _empty(
            Icons.account_balance_wallet_outlined,
            t('commerceOps.noPayouts'),
          )
        else
          ...payouts.map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _simpleCard(
                title: _text(
                  item['reference'],
                  fallback: '#${_int(item['id'])}',
                ),
                status: _text(item['status'], fallback: 'prepared'),
                subtitle: _payoutSubtitle(item),
                actionLabel:
                    ![
                          'requested',
                          'prepared',
                        ].contains(_text(item['status'])) ||
                        _int(item['recovery_cents']) > 0
                    ? null
                    : t('commerceOps.markPaid'),
                onEdit:
                    ![
                          'requested',
                          'prepared',
                        ].contains(_text(item['status'])) ||
                        _int(item['recovery_cents']) > 0
                    ? null
                    : () => _markPayoutPaid(item),
              ),
            ),
          ),
        const SizedBox(height: 8),
        _sectionHeader(
          title: t('commerceOps.payoutProfiles'),
          body: t('commerceOps.payoutProfilesBody'),
          icon: Icons.account_balance_outlined,
        ),
        _listControls('payout_profiles', data),
        if (profiles.isEmpty)
          _empty(
            Icons.account_balance_outlined,
            t('commerceOps.noPayoutProfiles'),
          )
        else
          ...profiles.map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _simpleCard(
                title: _text(
                  _map(item['user'])['name'],
                  fallback: t('commerceOps.payoutProfile'),
                ),
                status: _text(item['status'], fallback: 'draft'),
                subtitle: _text(
                  item['account_holder'],
                  fallback: t('commerceOps.accountHidden'),
                ),
                onEdit: () => _editPayoutProfile(item),
              ),
            ),
          ),
      ],
    );
  }

  Widget _campaigns(_CommerceOpsData data) {
    final campaigns = _list(data.catalog['campaigns']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _sectionHeader(
          title: t('commerceOps.campaigns'),
          body: t('commerceOps.campaignsBody'),
          icon: Icons.campaign_outlined,
          onAdd: () => _editCampaign(),
        ),
        _listControls('campaigns', data),
        if (campaigns.isEmpty)
          _empty(Icons.campaign_outlined, t('commerceOps.noCampaigns'))
        else
          ...campaigns.map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _simpleCard(
                title: _text(item['name'], fallback: t('adminHub.campaign')),
                status: _text(item['status'], fallback: 'draft'),
                subtitle:
                    '${_text(item['placement'], fallback: '–')} · ${_money(_int(item['budget_cents']))}',
                onEdit: () => _editCampaign(item),
              ),
            ),
          ),
      ],
    );
  }

  Widget _applications(_CommerceOpsData data) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final list in ['seller_applications', 'website_requests']) ...[
          _sectionHeader(
            title: t(
              list == 'seller_applications'
                  ? 'adminHub.sellerApplications'
                  : 'adminHub.websiteRequests',
            ),
            body: '',
            icon: Icons.storefront_outlined,
          ),
          _listControls(list, data),
          if (_list(data.catalog[list]).isEmpty)
            _empty(Icons.inbox_outlined, t('commerceOps.noEntries')),
          for (final item in _list(data.catalog[list]))
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _simpleCard(
                title: list == 'seller_applications'
                    ? _text(
                        item['business_name'],
                        fallback: _text(_map(item['user'])['name']),
                      )
                    : _text(
                        _map(item['club'])['name'],
                        fallback: _text(item['club_name']),
                      ),
                status: _text(item['status']),
                subtitle: [
                  _text(
                    _map(item['user'])['email'],
                    fallback: _text(item['guest_email']),
                  ),
                  _text(item['domain']),
                  _text(item['goals']),
                  _text(item['notes']),
                  _text(item['review_note']),
                  for (final check in _list(
                    _map(item['readiness'])['checklist'],
                  ))
                    if (check['done'] != true) _text(check['label']),
                ].where((value) => value.isNotEmpty).join('\n'),
                onEdit: () => _reviewApplication(list, item),
              ),
            ),
        ],
      ],
    );
  }

  Future<void> _reviewApplication(
    String list,
    Map<String, dynamic> item,
  ) async {
    final seller = list == 'seller_applications';
    final noteKey = seller ? 'review_note' : 'notes';
    final values = await _form(
      t(seller ? 'adminHub.sellerApplications' : 'adminHub.websiteRequests'),
      [
        _Field.choice(
          'status',
          t('commerceOps.status'),
          initial: _text(item['status'], fallback: seller ? 'pending' : 'new'),
          choices: _commerceListStatuses[list]!,
        ),
        _Field.multiline(
          noteKey,
          t('commerceOps.reviewNote'),
          initial: _text(item[noteKey]),
        ),
      ],
    );
    if (values == null) return;
    await _run(
      () => seller
          ? _client.adminUpdateSellerApplication(_int(item['id']), values)
          : _client.adminUpdateWebsiteRequest(_int(item['id']), values),
      success: t('commerceOps.saved'),
    );
  }

  Widget _audit(_CommerceOpsData data) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      _listControls('audit_logs', data),
      if (_list(data.catalog['audit_logs']).isEmpty)
        _empty(Icons.history_outlined, t('commerceOps.noEntries')),
      for (final item in _list(data.catalog['audit_logs']))
        ListTile(
          title: Text(_text(item['action'])),
          subtitle: Text(
            [
              _text(_map(item['user'])['email']),
              _text(item['created_at']),
              _text(item['note']),
            ].where((value) => value.isNotEmpty).join('\n'),
          ),
        ),
    ],
  );

  Widget _settings(_CommerceOpsData data) {
    final settings = _map(data.catalog['commerce_settings']);
    final visuals = _list(data.catalog['marketplace_visuals']);
    final commissions = _list(data.catalog['marketplace_commissions']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _settingsCard(
          icon: Icons.receipt_long_outlined,
          title: t('commerceOps.taxAndAds'),
          body: t('commerceOps.taxAndAdsBody'),
          value:
              '${_text(settings['company_country'], fallback: 'DE')} · ${_text(settings['company_currency'], fallback: 'EUR')}',
          onPressed: () => _editCommerceSettings(settings),
        ),
        const SizedBox(height: 10),
        _settingsCard(
          icon: Icons.percent_outlined,
          title: t('commerceOps.commissions'),
          body: t('commerceOps.commissionsBody'),
          value: '${_int(settings['marketplace_default_commission_percent'])}%',
          onPressed: () => _editCommissions(settings, commissions),
        ),
        const SizedBox(height: 10),
        _settingsCard(
          icon: Icons.image_outlined,
          title: t('commerceOps.visuals'),
          body: t('commerceOps.visualsBody'),
          value:
              '${visuals.where((item) => _text(item['source']).isNotEmpty).length}/${visuals.length}',
          onPressed: () => _editVisuals(visuals),
        ),
      ],
    );
  }

  Widget _sectionHeader({
    required String title,
    required String body,
    required IconData icon,
    VoidCallback? onAdd,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: Theme.of(context).colorScheme.primary, size: 29),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                Text(body),
              ],
            ),
          ),
          if (onAdd != null) ...[
            const SizedBox(width: 8),
            IconButton.filledTonal(
              tooltip: t('commerceOps.add'),
              onPressed: _busy ? null : onAdd,
              icon: const Icon(Icons.add),
            ),
          ],
        ],
      ),
    );
  }

  Widget _entityGroup({
    required String listKey,
    required _CommerceOpsData data,
    required String title,
    required IconData icon,
    required List<Map<String, dynamic>> items,
    required VoidCallback onAdd,
    required Widget Function(Map<String, dynamic>) itemBuilder,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AirmiusPanel(
        child: Material(
          type: MaterialType.transparency,
          child: ExpansionTile(
            key: PageStorageKey('commerce-$listKey'),
            tilePadding: EdgeInsets.zero,
            childrenPadding: const EdgeInsets.only(top: 4),
            leading: Icon(icon, color: Theme.of(context).colorScheme.primary),
            title: Text(
              '$title (${items.length})',
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            trailing: IconButton(
              tooltip: t('commerceOps.add'),
              onPressed: _busy ? null : onAdd,
              icon: const Icon(Icons.add_circle_outline),
            ),
            children: [
              _listControls(listKey, data),
              if (items.isEmpty)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(t('commerceOps.noEntries')),
                )
              else
                ...items.map(
                  (item) => Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: itemBuilder(item),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _simpleCard({
    required String title,
    required String status,
    required String subtitle,
    VoidCallback? onEdit,
    String? actionLabel,
  }) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHigh,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: Theme.of(context).dividerColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _cardHeader(Icons.circle_outlined, title, status),
          if (subtitle.isNotEmpty) ...[
            const SizedBox(height: 7),
            Text(subtitle),
          ],
          if (onEdit != null) ...[
            const SizedBox(height: 9),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: FilledButton.tonalIcon(
                onPressed: _busy ? null : onEdit,
                icon: Icon(
                  actionLabel == null
                      ? Icons.edit_outlined
                      : Icons.arrow_forward_outlined,
                ),
                label: Text(actionLabel ?? t('common.edit')),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _settingsCard({
    required IconData icon,
    required String title,
    required String body,
    required String value,
    required VoidCallback onPressed,
  }) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                icon,
                size: 30,
                color: Theme.of(context).colorScheme.primary,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              StatusPill(value),
            ],
          ),
          const SizedBox(height: 8),
          Text(body),
          const SizedBox(height: 11),
          FilledButton.tonalIcon(
            onPressed: _busy ? null : onPressed,
            icon: const Icon(Icons.edit_outlined),
            label: Text(t('commerceOps.configure')),
          ),
        ],
      ),
    );
  }

  Widget _cardHeader(IconData icon, String title, String status) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: Theme.of(context).colorScheme.primary, size: 25),
        const SizedBox(width: 9),
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

  Widget _empty(IconData icon, String title) => AirmiusPanel(
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 15),
      child: Column(
        children: [
          Icon(icon, size: 38, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 8),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
        ],
      ),
    ),
  );

  Future<Map<String, dynamic>?> _form(String title, List<_Field> fields) {
    return showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _CommerceFormDialog(title: title, fields: fields),
    );
  }

  Future<bool> _confirm(String title, String body) async {
    return await showDialog<bool>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            title: Text(title),
            content: Text(body),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext, false),
                child: Text(t('common.cancel')),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(dialogContext, true),
                child: Text(t('common.confirm')),
              ),
            ],
          ),
        ) ??
        false;
  }

  Future<void> _editProduct([Map<String, dynamic>? product]) async {
    final creating = product == null;
    final values = await _form(
      creating ? t('commerceOps.createProduct') : t('commerceOps.editProduct'),
      [
        _Field.text(
          'title',
          t('commerceOps.name'),
          initial: _text(product?['title']),
          required: true,
        ),
        _Field.multiline(
          'description',
          t('commerceOps.description'),
          initial: _text(product?['description']),
        ),
        _Field.multiline(
          'features',
          t('commerceOps.features'),
          initial: _stringList(product?['features']).join('\n'),
        ),
        _Field.text(
          'category',
          t('commerceOps.category'),
          initial: _text(product?['category'], fallback: 'product'),
          required: true,
        ),
        _Field.choice(
          'product_type',
          t('commerceOps.productType'),
          initial: _text(product?['product_type'], fallback: 'single'),
          choices: const ['single', 'variable', 'digital'],
        ),
        _Field.text('sku', 'SKU', initial: _text(product?['sku'])),
        _Field.number(
          'price',
          t('commerceOps.priceEuro'),
          initial: _decimal(_int(product?['price_cents'])),
          required: true,
        ),
        _Field.text(
          'currency',
          t('commerceOps.currency'),
          initial: _text(product?['currency'], fallback: 'EUR'),
          required: true,
        ),
        _Field.text(
          'image_url',
          t('commerceOps.imageUrl'),
          initial: _text(product?['image_url']),
        ),
        _Field.multiline(
          'gallery',
          t('commerceOps.galleryUrls'),
          initial: _stringList(product?['gallery_images']).join('\n'),
        ),
        _Field.toggle(
          'is_shippable',
          t('commerceOps.shippable'),
          initial: product == null || _bool(product['is_shippable']),
        ),
        _Field.toggle(
          'manages_stock',
          t('commerceOps.managesStock'),
          initial: _bool(product?['manages_stock']),
        ),
        _Field.number(
          'stock_quantity',
          t('commerceOps.stock'),
          initial: '${_int(product?['stock_quantity'])}',
        ),
        _Field.number(
          'low_stock_threshold',
          t('commerceOps.lowStock'),
          initial: '${_int(product?['low_stock_threshold'])}',
        ),
        _Field.text(
          'tax_class',
          t('commerceOps.taxClass'),
          initial: _text(product?['tax_class'], fallback: 'standard'),
        ),
        _Field.choice(
          'return_policy_type',
          t('commerceOps.returnPolicy'),
          initial: _text(
            _map(product?['return_policy'])['type'],
            fallback: 'standard',
          ),
          choices: const [
            'standard',
            'digital',
            'service',
            'hygiene',
            'custom',
          ],
        ),
        _Field.number(
          'return_window_days',
          t('commerceOps.returnDays'),
          initial:
              '${_int(_map(product?['return_policy'])['window_days'] ?? 14)}',
        ),
        _Field.choice(
          'status',
          t('commerceOps.status'),
          initial: _text(product?['status'], fallback: 'draft'),
          choices: const [
            'draft',
            'review',
            'published',
            'rejected',
            'archived',
          ],
        ),
        _Field.number(
          'commission_percent',
          t('commerceOps.commissionPercent'),
          initial: '${_int(product?['commission_percent'])}',
        ),
      ],
    );
    if (values == null) return;
    final payload = <String, dynamic>{
      'title': values['title'],
      'description': _nullable(values['description']),
      'features_text': values['features'],
      'attributes_text': _attributesText(product?['product_attributes']),
      'attribute_options': product?['attribute_options'] ?? const [],
      'variants': product?['variants'] ?? const [],
      'image_url': _nullable(values['image_url']),
      'image_urls_text': values['gallery'],
      'category': values['category'],
      'product_type': values['product_type'],
      'sku': _nullable(values['sku']),
      'is_shippable': values['is_shippable'],
      'manages_stock': values['manages_stock'],
      'stock_quantity': _parseInt(values['stock_quantity']),
      'low_stock_threshold': _parseInt(values['low_stock_threshold']),
      'tax_class': _nullable(values['tax_class']),
      'return_policy_type': values['return_policy_type'],
      'return_window_days': _parseInt(values['return_window_days']),
      'price_cents': _cents(values['price']),
      'currency': values['currency'].toString().toUpperCase(),
      'status': values['status'],
      'commission_percent': _parseInt(values['commission_percent']),
    };
    await _run(
      () => creating
          ? _client.adminCreateCommerceProduct(payload)
          : _client.adminEditCommerceProduct(_int(product['id']), payload),
      success: t('commerceOps.productSaved'),
    );
  }

  Future<void> _adjustStock(Map<String, dynamic> product) async {
    final values = await _form(t('commerceOps.adjustStock'), [
      _Field.number(
        'quantity_delta',
        t('commerceOps.stockChange'),
        required: true,
      ),
      _Field.multiline('note', t('commerceOps.note')),
    ]);
    if (values == null) return;
    await _run(
      () => _client.adminAdjustCommerceProductStock(_int(product['id']), {
        'quantity_delta': _parseInt(values['quantity_delta']),
        'note': _nullable(values['note']),
      }),
      success: t('commerceOps.stockSaved'),
    );
  }

  Future<void> _deleteProduct(Map<String, dynamic> product) async {
    final confirmed = await _confirm(
      t('commerceOps.deleteProduct'),
      t('commerceOps.deleteProductBody'),
    );
    if (!confirmed) return;
    await _run(
      () => _client.adminDeleteCommerceProduct(_int(product['id'])),
      success: t('commerceOps.productDeleted'),
    );
  }

  Future<void> _editCoupon([Map<String, dynamic>? item]) async {
    final creating = item == null;
    final values = await _form(t('commerceOps.coupon'), [
      _Field.text(
        'code',
        t('commerceOps.code'),
        initial: _text(item?['code']),
        required: true,
      ),
      _Field.text(
        'name',
        t('commerceOps.name'),
        initial: _text(item?['name']),
        required: true,
      ),
      _Field.choice(
        'type',
        t('commerceOps.type'),
        initial: _text(item?['type'], fallback: 'percent'),
        choices: const ['percent', 'fixed'],
      ),
      _Field.number(
        'percent_off',
        t('commerceOps.percent'),
        initial: _optionalInt(item?['percent_off']),
      ),
      _Field.number(
        'value',
        t('commerceOps.valueEuro'),
        initial: _decimal(_int(item?['value_cents'])),
      ),
      _Field.number(
        'max_redemptions',
        t('commerceOps.maxRedemptions'),
        initial: _optionalInt(item?['max_redemptions']),
      ),
      _Field.text(
        'starts_at',
        t('commerceOps.startsAt'),
        initial: _text(item?['starts_at']),
      ),
      _Field.text(
        'ends_at',
        t('commerceOps.endsAt'),
        initial: _text(item?['ends_at']),
      ),
      _Field.toggle(
        'is_active',
        t('commerceOps.active'),
        initial: item == null || _bool(item['is_active']),
      ),
    ]);
    if (values == null) return;
    final payload = {
      'code': values['code'],
      'name': values['name'],
      'type': values['type'],
      'value_cents': _optionalCents(values['value']),
      'percent_off': _optionalParsedInt(values['percent_off']),
      'max_redemptions': _optionalParsedInt(values['max_redemptions']),
      'starts_at': _nullable(values['starts_at']),
      'ends_at': _nullable(values['ends_at']),
      'is_active': values['is_active'],
    };
    await _run(
      () => creating
          ? _client.adminCreateCommerceCoupon(payload)
          : _client.adminUpdateCommerceCoupon(_int(item['id']), payload),
      success: t('commerceOps.saved'),
    );
  }

  Future<void> _editAddon([Map<String, dynamic>? item]) async {
    final creating = item == null;
    final values = await _form(t('commerceOps.addon'), [
      _Field.text(
        'slug',
        'Slug',
        initial: _text(item?['slug']),
        required: true,
      ),
      _Field.text(
        'name',
        t('commerceOps.name'),
        initial: _text(item?['name']),
        required: true,
      ),
      _Field.multiline(
        'description',
        t('commerceOps.description'),
        initial: _text(item?['description']),
      ),
      _Field.number(
        'monthly',
        t('commerceOps.monthlyEuro'),
        initial: _decimal(_int(item?['monthly_price_cents'])),
        required: true,
      ),
      _Field.number(
        'yearly',
        t('commerceOps.yearlyEuro'),
        initial: _decimal(_int(item?['yearly_price_cents'])),
        required: true,
      ),
      _Field.text(
        'target_actor',
        t('commerceOps.targetActor'),
        initial: _text(item?['target_actor'], fallback: 'user'),
        required: true,
      ),
      _Field.multiline(
        'features',
        t('commerceOps.features'),
        initial: _stringList(item?['features']).join('\n'),
      ),
      _Field.toggle(
        'is_active',
        t('commerceOps.active'),
        initial: item == null || _bool(item['is_active']),
      ),
    ]);
    if (values == null) return;
    final payload = {
      'slug': values['slug'],
      'name': values['name'],
      'description': _nullable(values['description']),
      'monthly_price_cents': _cents(values['monthly']),
      'yearly_price_cents': _cents(values['yearly']),
      'target_actor': values['target_actor'],
      'features': _lines(values['features']),
      'is_active': values['is_active'],
    };
    await _run(
      () => creating
          ? _client.adminCreateCommerceAddon(payload)
          : _client.adminUpdateCommerceAddon(_int(item['id']), payload),
      success: t('commerceOps.saved'),
    );
  }

  Future<void> _editTaxRate([Map<String, dynamic>? item]) async {
    final creating = item == null;
    final values = await _form(t('commerceOps.taxRate'), [
      _Field.text(
        'name',
        t('commerceOps.name'),
        initial: _text(item?['name']),
        required: true,
      ),
      _Field.text(
        'country_code',
        t('commerceOps.countryCode'),
        initial: _text(item?['country_code'], fallback: 'DE'),
        required: true,
      ),
      _Field.text(
        'region',
        t('commerceOps.region'),
        initial: _text(item?['region']),
      ),
      _Field.text(
        'tax_class',
        t('commerceOps.taxClass'),
        initial: _text(item?['tax_class'], fallback: 'standard'),
      ),
      _Field.text(
        'tax_label',
        t('commerceOps.taxLabel'),
        initial: _text(item?['tax_label'], fallback: 'MwSt.'),
        required: true,
      ),
      _Field.number(
        'rate_percent',
        t('commerceOps.percent'),
        initial: _text(item?['rate_percent'], fallback: '19'),
        required: true,
      ),
      _Field.text(
        'currency',
        t('commerceOps.currency'),
        initial: _text(item?['currency'], fallback: 'EUR'),
        required: true,
      ),
      _Field.toggle(
        'is_default',
        t('commerceOps.defaultRate'),
        initial: _bool(item?['is_default']),
      ),
      _Field.toggle(
        'is_active',
        t('commerceOps.active'),
        initial: item == null || _bool(item['is_active']),
      ),
      _Field.number(
        'priority',
        t('commerceOps.priority'),
        initial: '${_int(item?['priority'] ?? 100)}',
      ),
    ]);
    if (values == null) return;
    final payload = {
      'name': values['name'],
      'country_code': values['country_code'],
      'region': _nullable(values['region']),
      'tax_class': _nullable(values['tax_class']),
      'tax_label': values['tax_label'],
      'rate_percent': _parseDouble(values['rate_percent']),
      'currency': values['currency'],
      'is_default': values['is_default'],
      'is_active': values['is_active'],
      'priority': _parseInt(values['priority']),
    };
    await _run(
      () => creating
          ? _client.adminCreateCommerceTaxRate(payload)
          : _client.adminUpdateCommerceTaxRate(_int(item['id']), payload),
      success: t('commerceOps.saved'),
    );
  }

  Future<void> _editShippingRate([Map<String, dynamic>? item]) async {
    final creating = item == null;
    final values = await _form(t('commerceOps.shippingRate'), [
      _Field.text(
        'name',
        t('commerceOps.name'),
        initial: _text(item?['name']),
        required: true,
      ),
      _Field.text(
        'origin_country_code',
        t('commerceOps.originCountry'),
        initial: _text(item?['origin_country_code'], fallback: 'DE'),
      ),
      _Field.text(
        'country_code',
        t('commerceOps.destinationCountry'),
        initial: _text(item?['country_code'], fallback: 'DE'),
      ),
      _Field.text(
        'postal_code_prefix',
        t('commerceOps.postalPrefix'),
        initial: _text(item?['postal_code_prefix']),
      ),
      _Field.number(
        'amount',
        t('commerceOps.valueEuro'),
        initial: _decimal(_int(item?['amount_cents'])),
        required: true,
      ),
      _Field.text(
        'currency',
        t('commerceOps.currency'),
        initial: _text(item?['currency'], fallback: 'EUR'),
        required: true,
      ),
      _Field.number(
        'free_from',
        t('commerceOps.freeFromEuro'),
        initial: _optionalDecimal(item?['free_from_cents']),
      ),
      _Field.toggle(
        'is_active',
        t('commerceOps.active'),
        initial: item == null || _bool(item['is_active']),
      ),
      _Field.number(
        'priority',
        t('commerceOps.priority'),
        initial: '${_int(item?['priority'] ?? 100)}',
      ),
    ]);
    if (values == null) return;
    final payload = {
      'name': values['name'],
      'origin_country_code': _nullable(values['origin_country_code']),
      'country_code': _nullable(values['country_code']),
      'postal_code_prefix': _nullable(values['postal_code_prefix']),
      'amount_cents': _cents(values['amount']),
      'currency': values['currency'],
      'free_from_cents': _optionalCents(values['free_from']),
      'is_active': values['is_active'],
      'priority': _parseInt(values['priority']),
    };
    await _run(
      () => creating
          ? _client.adminCreateCommerceShippingRate(payload)
          : _client.adminUpdateCommerceShippingRate(_int(item['id']), payload),
      success: t('commerceOps.saved'),
    );
  }

  Future<void> _editShipping(Map<String, dynamic> order) async {
    final values = await _form(t('commerceOps.shipping'), [
      _Field.choice(
        'shipping_status',
        t('commerceOps.status'),
        initial: _text(order['shipping_status'], fallback: 'open'),
        choices: const ['open', 'prepared', 'shipped', 'delivered'],
      ),
      _Field.text(
        'shipping_carrier',
        t('commerceOps.carrier'),
        initial: _text(order['shipping_carrier']),
      ),
      _Field.text(
        'tracking_number',
        t('commerceOps.trackingNumber'),
        initial: _text(order['tracking_number']),
      ),
      _Field.text(
        'tracking_url',
        t('commerceOps.trackingUrl'),
        initial: _text(order['tracking_url']),
      ),
      _Field.text(
        'shipping_label_url',
        t('commerceOps.shippingLabelUrl'),
        initial: _text(order['shipping_label_url']),
      ),
    ]);
    if (values == null) return;
    await _run(
      () => _client.adminUpdateCommerceShipping(_int(order['id']), {
        'shipping_status': values['shipping_status'],
        'shipping_carrier': _nullable(values['shipping_carrier']),
        'tracking_number': _nullable(values['tracking_number']),
        'tracking_url': _nullable(values['tracking_url']),
        'shipping_label_url': _nullable(values['shipping_label_url']),
      }),
      success: t('adminHub.shippingSaved'),
    );
  }

  Future<void> _editIssue(Map<String, dynamic> order) async {
    final values = await _form(t('commerceOps.issue'), [
      _Field.choice(
        'issue_status',
        t('commerceOps.status'),
        initial: _text(order['issue_status'], fallback: 'none'),
        choices: const [
          'none',
          'reported',
          'reviewing',
          'resolved',
          'refunded',
          'cancelled',
        ],
      ),
      _Field.multiline(
        'issue_note',
        t('commerceOps.note'),
        initial: _text(order['issue_note']),
      ),
      _Field.choice(
        'order_status',
        t('commerceOps.orderStatusOptional'),
        initial: '',
        choices: const ['', 'completed', 'cancelled', 'refunded'],
      ),
    ]);
    if (values == null) return;
    await _run(
      () => _client.adminUpdateCommerceOrderIssue(_int(order['id']), {
        'issue_status': values['issue_status'],
        'issue_note': _nullable(values['issue_note']),
        'order_status': _nullable(values['order_status']),
      }),
      success: t('commerceOps.issueSaved'),
    );
  }

  Future<void> _replyIssue(Map<String, dynamic> order) async {
    final values = await _form(t('commerceOps.reply'), [
      _Field.multiline(
        'issue_response',
        t('commerceOps.replyText'),
        initial: _text(order['issue_response']),
        required: true,
      ),
      _Field.choice(
        'issue_status',
        t('commerceOps.status'),
        initial: 'reviewing',
        choices: const ['reported', 'reviewing', 'resolved'],
      ),
    ]);
    if (values == null) return;
    await _run(
      () => _client.adminReplyCommerceOrderIssue(_int(order['id']), {
        'issue_response': values['issue_response'],
        'issue_status': values['issue_status'],
      }),
      success: t('commerceOps.replySaved'),
    );
  }

  Future<void> _refundOrder(Map<String, dynamic> order) async {
    final remaining =
        _int(order['amount_cents']) - _int(order['refunded_cents']);
    final values = await _form(t('commerceOps.refund'), [
      _Field.number(
        'amount',
        t('commerceOps.refundAmountEuro'),
        initial: _decimal(remaining),
        required: true,
      ),
      _Field.multiline('reason', t('commerceOps.reason'), required: true),
    ]);
    if (values == null) return;
    await _run(
      () => _client.adminRefundCommerceOrder(_int(order['id']), {
        'amount_cents': _cents(values['amount']),
        'reason': values['reason'],
        'idempotency_key':
            'mobile-${_int(order['id'])}-${DateTime.now().microsecondsSinceEpoch}',
      }),
      success: t('commerceOps.refundSaved'),
    );
  }

  Future<void> _documents(Map<String, dynamic> order) async {
    try {
      final response = await _client.adminCommerceOrderDocuments(
        _int(order['id']),
      );
      if (!mounted) return;
      final data = _map(response['data']);
      final invoice = _map(data['invoice']);
      final credit = _map(data['credit_note']);
      await showModalBottomSheet<void>(
        context: context,
        showDragHandle: true,
        builder: (sheetContext) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  t('commerceOps.documents'),
                  style: Theme.of(
                    sheetContext,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 12),
                _documentButton(
                  sheetContext,
                  t('commerceOps.invoice'),
                  invoice,
                ),
                const SizedBox(height: 8),
                _documentButton(
                  sheetContext,
                  t('commerceOps.creditNote'),
                  credit,
                ),
              ],
            ),
          ),
        ),
      );
    } on AirmiusApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
      }
    }
  }

  Widget _documentButton(
    BuildContext sheetContext,
    String label,
    Map<String, dynamic> document,
  ) {
    final available = _bool(document['available']);
    return FilledButton.tonalIcon(
      onPressed: !available
          ? null
          : () async {
              final uri = safeExternalHttpUrl(_text(document['url']));
              if (uri != null) {
                await launchUrl(uri, mode: LaunchMode.externalApplication);
              }
            },
      icon: const Icon(Icons.picture_as_pdf_outlined),
      label: Text(
        available
            ? '$label · ${_text(document['number'])}'
            : '$label · ${t('commerceOps.notAvailable')}',
      ),
    );
  }

  Future<void> _editReturn(Map<String, dynamic> item) async {
    final values = await _form(t('commerceOps.return'), [
      _Field.choice(
        'status',
        t('commerceOps.status'),
        initial: _text(item['status'], fallback: 'requested'),
        choices: const [
          'requested',
          'approved',
          'rejected',
          'received',
          'refunded',
          'cancelled',
        ],
      ),
      _Field.multiline(
        'resolution_note',
        t('commerceOps.resolution'),
        initial: _text(item['resolution_note']),
      ),
      _Field.number(
        'approved_amount',
        t('commerceOps.approvedAmountEuro'),
        initial: _optionalDecimal(
          item['approved_amount_cents'] ?? item['requested_amount_cents'],
        ),
      ),
      _Field.toggle('restock', t('commerceOps.restock'), initial: false),
    ]);
    if (values == null) return;
    await _run(
      () => _client.adminUpdateCommerceReturn(_int(item['id']), {
        'status': values['status'],
        'resolution_note': _nullable(values['resolution_note']),
        'approved_amount_cents': _optionalCents(values['approved_amount']),
        'restock': values['restock'],
        if (values['status'] == 'refunded')
          'idempotency_key': 'return-${_int(item['id'])}-refund',
      }),
      success: t('commerceOps.returnSaved'),
    );
  }

  Future<void> _preparePayout(Map<String, dynamic> candidate) async {
    final values = await _form(t('commerceOps.preparePayout'), [
      _Field.choice(
        'method',
        t('commerceOps.method'),
        initial: 'bank_transfer',
        choices: const ['bank_transfer', 'paypal', 'manual'],
      ),
      _Field.multiline('notes', t('commerceOps.note')),
    ]);
    if (values == null) return;
    await _run(
      () => _client.adminCreateCommercePayout(_int(candidate['user_id']), {
        'method': values['method'],
        'notes': _nullable(values['notes']),
      }),
      success: t('commerceOps.payoutPrepared'),
    );
  }

  Future<void> _markPayoutPaid(Map<String, dynamic> payout) async {
    final values = await _form(t('commerceOps.markPaid'), [
      _Field.multiline('notes', t('commerceOps.note')),
    ]);
    if (values == null) return;
    await _run(
      () => _client.adminMarkCommercePayoutPaid(
        _int(payout['id']),
        notes: _nullable(values['notes'])?.toString(),
      ),
      success: t('commerceOps.payoutPaid'),
    );
  }

  Future<void> _editPayoutProfile(Map<String, dynamic> profile) async {
    final values = await _form(t('commerceOps.payoutProfile'), [
      _Field.choice(
        'status',
        t('commerceOps.status'),
        initial: _text(profile['status'], fallback: 'review'),
        choices: const ['draft', 'review', 'approved', 'blocked'],
      ),
      _Field.multiline(
        'notes',
        t('commerceOps.note'),
        initial: _text(profile['notes']),
      ),
    ]);
    if (values == null) return;
    await _run(
      () => _client.adminUpdateCommercePayoutProfile(_int(profile['id']), {
        'status': values['status'],
        'notes': _nullable(values['notes']),
      }),
      success: t('commerceOps.saved'),
    );
  }

  Future<void> _editCampaign([Map<String, dynamic>? item]) async {
    final creating = item == null;
    final audience = _map(item?['audience']);
    final values = await _form(t('commerceOps.campaign'), [
      _Field.toggle(
        'is_internal',
        t('commerceOps.internalCampaign'),
        initial: item == null || _bool(item['is_internal']),
      ),
      _Field.toggle(
        'force_priority',
        t('commerceOps.forcePriority'),
        initial: _bool(item?['force_priority']),
      ),
      _Field.text(
        'name',
        t('commerceOps.name'),
        initial: _text(item?['name']),
        required: true,
      ),
      _Field.text(
        'headline',
        t('commerceOps.headlineField'),
        initial: _text(item?['headline']),
      ),
      _Field.multiline(
        'description',
        t('commerceOps.description'),
        initial: _text(item?['description']),
      ),
      _Field.multiline(
        'primary_text',
        t('commerceOps.primaryText'),
        initial: _text(item?['primary_text']),
      ),
      _Field.text(
        'target_url',
        t('commerceOps.targetUrl'),
        initial: _text(item?['target_url']),
      ),
      _Field.text(
        'cta_label',
        t('commerceOps.cta'),
        initial: _text(item?['cta_label']),
      ),
      _Field.text(
        'creative_image_url',
        t('commerceOps.imageUrl'),
        initial: _text(item?['creative_image_url']),
      ),
      _Field.choice(
        'objective',
        t('commerceOps.objective'),
        initial: _text(item?['objective'], fallback: 'awareness'),
        choices: const ['traffic', 'awareness', 'leads', 'sales'],
      ),
      _Field.choice(
        'placement',
        t('commerceOps.placement'),
        initial: _text(item?['placement'], fallback: 'marketplace_card'),
        choices: const [
          'marketplace_card',
          'feed',
          'sidebar',
          'sponsor_section',
        ],
      ),
      _Field.choice(
        'creative_format',
        t('commerceOps.format'),
        initial: _text(item?['creative_format'], fallback: 'feed_square'),
        choices: const [
          'feed_square',
          'feed_portrait',
          'story_vertical',
          'banner_wide',
        ],
      ),
      _Field.text(
        'audience_locations',
        t('commerceOps.locations'),
        initial: _stringList(audience['locations']).join(', '),
      ),
      _Field.text(
        'audience_interests',
        t('commerceOps.interests'),
        initial: _stringList(audience['interests']).join(', '),
      ),
      _Field.number(
        'audience_age_min',
        t('commerceOps.ageMin'),
        initial: _optionalInt(audience['age_min']),
      ),
      _Field.number(
        'audience_age_max',
        t('commerceOps.ageMax'),
        initial: _optionalInt(audience['age_max']),
      ),
      _Field.number(
        'budget',
        t('commerceOps.budgetEuro'),
        initial: _decimal(_int(item?['budget_cents'])),
        required: true,
      ),
      _Field.number(
        'daily_budget',
        t('commerceOps.dailyBudgetEuro'),
        initial: _decimal(_int(item?['daily_budget_cents'])),
      ),
      _Field.choice(
        'status',
        t('commerceOps.status'),
        initial: _text(item?['status'], fallback: 'draft'),
        choices: const [
          'draft',
          'pending_payment',
          'pending_review',
          'active',
          'paused',
          'completed',
          'rejected',
        ],
      ),
      _Field.multiline(
        'review_note',
        t('commerceOps.reviewNote'),
        initial: _text(item?['review_note']),
      ),
      _Field.text(
        'starts_at',
        t('commerceOps.startsAt'),
        initial: _text(item?['starts_at']),
      ),
      _Field.text(
        'ends_at',
        t('commerceOps.endsAt'),
        initial: _text(item?['ends_at']),
      ),
    ]);
    if (values == null) return;
    final payload = {
      'is_internal': values['is_internal'],
      'force_priority': values['force_priority'],
      'name': values['name'],
      'headline': _nullable(values['headline']),
      'description': _nullable(values['description']),
      'primary_text': _nullable(values['primary_text']),
      'target_url': _nullable(values['target_url']),
      'cta_label': _nullable(values['cta_label']),
      'creative_image_url': _nullable(values['creative_image_url']),
      'creatives': item?['creatives'] ?? const [],
      'objective': values['objective'],
      'placement': values['placement'],
      'creative_format': values['creative_format'],
      'audience_locations': values['audience_locations'],
      'audience_interests': values['audience_interests'],
      'audience_excluded_locations': _stringList(
        audience['excluded_locations'],
      ).join(', '),
      'audience_excluded_interests': _stringList(
        audience['excluded_interests'],
      ).join(', '),
      'audience_devices': _stringList(audience['devices']).join(', '),
      'audience_languages': _stringList(audience['languages']).join(', '),
      'audience_hours': _stringList(audience['hours']).join(', '),
      'audience_age_min': _optionalParsedInt(values['audience_age_min']),
      'audience_age_max': _optionalParsedInt(values['audience_age_max']),
      'budget_cents': _cents(values['budget']),
      'daily_budget_cents': _cents(values['daily_budget']),
      'status': values['status'],
      'review_note': _nullable(values['review_note']),
      'starts_at': _nullable(values['starts_at']),
      'ends_at': _nullable(values['ends_at']),
    };
    await _run(
      () => creating
          ? _client.adminCreateCommerceCampaign(payload)
          : _client.adminEditCommerceCampaign(_int(item['id']), payload),
      success: t('commerceOps.saved'),
    );
  }

  Future<void> _editCommerceSettings(Map<String, dynamic> settings) async {
    final values = await _form(t('commerceOps.taxAndAds'), [
      _Field.text(
        'company_country',
        t('commerceOps.countryCode'),
        initial: _text(settings['company_country'], fallback: 'DE'),
        required: true,
      ),
      _Field.text(
        'company_currency',
        t('commerceOps.currency'),
        initial: _text(settings['company_currency'], fallback: 'EUR'),
        required: true,
      ),
      _Field.toggle(
        'enable_oss',
        'OSS',
        initial: _bool(settings['enable_oss']),
      ),
      _Field.choice(
        'export_vat_mode',
        t('commerceOps.exportVatMode'),
        initial: _text(settings['export_vat_mode'], fallback: 'zero'),
        choices: const ['zero', 'domestic'],
      ),
      _Field.toggle(
        'reverse_charge_enabled',
        t('commerceOps.reverseCharge'),
        initial: _bool(settings['reverse_charge_enabled']),
      ),
      ...[
        ('ads_cpm_cents', 'CPM'),
        ('ads_cpc_cents', 'CPC'),
        ('ads_cpl_cents', 'CPL'),
        ('ads_cpa_percent', 'CPA %'),
        ('ads_min_budget_cents', t('commerceOps.minimumBudgetCents')),
        ('ads_frequency_cap_per_day', t('commerceOps.frequencyDay')),
        ('ads_frequency_cap_feed', t('commerceOps.frequencyFeed')),
        ('ads_frequency_cap_sidebar', t('commerceOps.frequencySidebar')),
        (
          'ads_frequency_cap_marketplace_card',
          t('commerceOps.frequencyMarketplace'),
        ),
        (
          'ads_frequency_cap_sponsor_section',
          t('commerceOps.frequencySponsor'),
        ),
      ].map(
        (entry) => _Field.number(
          entry.$1,
          entry.$2,
          initial: '${_int(settings[entry.$1])}',
          required: true,
        ),
      ),
    ]);
    if (values == null) return;
    final payload = <String, dynamic>{
      'company_country': values['company_country'],
      'company_currency': values['company_currency'],
      'enable_oss': values['enable_oss'],
      'export_vat_mode': values['export_vat_mode'],
      'reverse_charge_enabled': values['reverse_charge_enabled'],
      for (final key in [
        'ads_cpm_cents',
        'ads_cpc_cents',
        'ads_cpl_cents',
        'ads_cpa_percent',
        'ads_min_budget_cents',
        'ads_frequency_cap_per_day',
        'ads_frequency_cap_feed',
        'ads_frequency_cap_sidebar',
        'ads_frequency_cap_marketplace_card',
        'ads_frequency_cap_sponsor_section',
      ])
        key: _parseInt(values[key]),
    };
    await _run(
      () => _client.adminUpdateCommerceSettings(payload),
      success: t('commerceOps.saved'),
    );
  }

  Future<void> _editCommissions(
    Map<String, dynamic> settings,
    List<Map<String, dynamic>> commissions,
  ) async {
    final values = await _form(t('commerceOps.commissions'), [
      _Field.number(
        'default',
        t('commerceOps.defaultCommission'),
        initial: '${_int(settings['marketplace_default_commission_percent'])}',
        required: true,
      ),
      _Field.multiline(
        'rows',
        t('commerceOps.commissionRows'),
        initial: commissions
            .map(
              (row) =>
                  '${_text(row['category'])}|${_text(row['label'])}|${_int(row['commission_percent'])}',
            )
            .join('\n'),
        required: true,
      ),
    ]);
    if (values == null) return;
    final rows = _lines(values['rows'])
        .map((line) => line.split('|'))
        .where((parts) => parts.length >= 3)
        .map(
          (parts) => {
            'category': parts[0].trim(),
            'label': parts[1].trim(),
            'commission_percent': int.tryParse(parts[2].trim()) ?? 0,
          },
        )
        .toList();
    await _run(
      () => _client.adminUpdateCommerceCommissions({
        'default_commission_percent': _parseInt(values['default']),
        'commissions': rows,
      }),
      success: t('commerceOps.saved'),
    );
  }

  Future<void> _editVisuals(List<Map<String, dynamic>> visuals) async {
    final fields = <_Field>[];
    for (final visual in visuals) {
      final key = _text(visual['key']);
      fields.addAll([
        _Field.text(
          '${key}_source',
          '${_text(visual['label'], fallback: key)} · URL',
          initial: _text(visual['source']),
        ),
        _Field.number(
          '${key}_width',
          '${_text(visual['label'], fallback: key)} · ${t('commerceOps.width')}',
          initial: '${_int(visual['width'])}',
        ),
        _Field.number(
          '${key}_height',
          '${_text(visual['label'], fallback: key)} · ${t('commerceOps.height')}',
          initial: '${_int(visual['height'])}',
        ),
      ]);
    }
    final values = await _form(t('commerceOps.visuals'), fields);
    if (values == null) return;
    await _run(
      () => _client.adminUpdateCommerceVisuals({
        'sources': {
          for (final visual in visuals)
            _text(visual['key']): _nullable(
              values['${_text(visual['key'])}_source'],
            ),
        },
        'dimensions': {
          for (final visual in visuals)
            _text(visual['key']): {
              'width': _parseInt(values['${_text(visual['key'])}_width']),
              'height': _parseInt(values['${_text(visual['key'])}_height']),
            },
        },
      }),
      success: t('commerceOps.saved'),
    );
  }

  String _money(int cents, [String? currency]) => NumberFormat.simpleCurrency(
    locale: Localizations.localeOf(context).toLanguageTag(),
    name: currency == null || currency.isEmpty ? 'EUR' : currency,
  ).format(cents / 100);
}

class _CommerceFormDialog extends StatefulWidget {
  const _CommerceFormDialog({required this.title, required this.fields});

  final String title;
  final List<_Field> fields;

  @override
  State<_CommerceFormDialog> createState() => _CommerceFormDialogState();
}

class _CommerceFormDialogState extends State<_CommerceFormDialog> {
  final _formKey = GlobalKey<FormState>();
  final Map<String, TextEditingController> _controllers = {};
  final Map<String, bool> _toggles = {};
  final Map<String, String> _choices = {};

  @override
  void initState() {
    super.initState();
    for (final field in widget.fields) {
      if (field.kind == _FieldKind.toggle) {
        _toggles[field.key] = field.toggleInitial;
      } else if (field.kind == _FieldKind.choice) {
        _choices[field.key] = field.initial;
      } else {
        _controllers[field.key] = TextEditingController(text: field.initial);
      }
    }
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 620, maxHeight: 720),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                widget.title,
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 14),
              Expanded(
                child: Form(
                  key: _formKey,
                  child: ListView.separated(
                    itemCount: widget.fields.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 12),
                    itemBuilder: (context, index) {
                      final field = widget.fields[index];
                      if (field.kind == _FieldKind.toggle) {
                        return SwitchListTile.adaptive(
                          contentPadding: EdgeInsets.zero,
                          title: Text(
                            field.label,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          value: _toggles[field.key] ?? false,
                          onChanged: (value) =>
                              setState(() => _toggles[field.key] = value),
                        );
                      }
                      if (field.kind == _FieldKind.choice) {
                        return DropdownButtonFormField<String>(
                          initialValue: _choices[field.key],
                          isExpanded: true,
                          decoration: InputDecoration(labelText: field.label),
                          items: field.choices
                              .map(
                                (value) => DropdownMenuItem(
                                  value: value,
                                  child: Text(
                                    value.isEmpty
                                        ? t('commerceOps.noChange')
                                        : field.choiceLabels[value] ??
                                              value.replaceAll('_', ' '),
                                  ),
                                ),
                              )
                              .toList(),
                          onChanged: (value) =>
                              setState(() => _choices[field.key] = value ?? ''),
                        );
                      }
                      return TextFormField(
                        controller: _controllers[field.key],
                        minLines: field.kind == _FieldKind.multiline ? 3 : 1,
                        maxLines: field.kind == _FieldKind.multiline ? 7 : 1,
                        keyboardType: field.kind == _FieldKind.number
                            ? const TextInputType.numberWithOptions(
                                decimal: true,
                              )
                            : TextInputType.text,
                        decoration: InputDecoration(labelText: field.label),
                        validator: field.required
                            ? (value) => value == null || value.trim().isEmpty
                                  ? t('commerceOps.required')
                                  : null
                            : null,
                      );
                    },
                  ),
                ),
              ),
              const SizedBox(height: 12),
              OverflowBar(
                alignment: MainAxisAlignment.end,
                spacing: 8,
                overflowSpacing: 8,
                children: [
                  TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: Text(t('common.cancel')),
                  ),
                  FilledButton.icon(
                    onPressed: () {
                      if (!(_formKey.currentState?.validate() ?? false)) return;
                      Navigator.pop(context, {
                        for (final entry in _controllers.entries)
                          entry.key: entry.value.text.trim(),
                        ..._toggles,
                        ..._choices,
                      });
                    },
                    icon: const Icon(Icons.save_outlined),
                    label: Text(t('common.save')),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

enum _FieldKind { text, multiline, number, toggle, choice }

class _Field {
  const _Field._({
    required this.key,
    required this.label,
    required this.kind,
    this.initial = '',
    this.required = false,
    this.toggleInitial = false,
    this.choices = const [],
    this.choiceLabels = const {},
  });

  factory _Field.text(
    String key,
    String label, {
    String initial = '',
    bool required = false,
  }) => _Field._(
    key: key,
    label: label,
    kind: _FieldKind.text,
    initial: initial,
    required: required,
  );

  factory _Field.multiline(
    String key,
    String label, {
    String initial = '',
    bool required = false,
  }) => _Field._(
    key: key,
    label: label,
    kind: _FieldKind.multiline,
    initial: initial,
    required: required,
  );

  factory _Field.number(
    String key,
    String label, {
    String initial = '',
    bool required = false,
  }) => _Field._(
    key: key,
    label: label,
    kind: _FieldKind.number,
    initial: initial,
    required: required,
  );

  factory _Field.toggle(String key, String label, {bool initial = false}) =>
      _Field._(
        key: key,
        label: label,
        kind: _FieldKind.toggle,
        toggleInitial: initial,
      );

  factory _Field.choice(
    String key,
    String label, {
    required String initial,
    required List<String> choices,
    Map<String, String> choiceLabels = const {},
  }) => _Field._(
    key: key,
    label: label,
    kind: _FieldKind.choice,
    initial: initial,
    choices: choices,
    choiceLabels: choiceLabels,
  );

  final String key;
  final String label;
  final _FieldKind kind;
  final String initial;
  final bool required;
  final bool toggleInitial;
  final List<String> choices;
  final Map<String, String> choiceLabels;
}

class _Metric extends StatelessWidget {
  const _Metric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minWidth: 130),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Theme.of(
        context,
      ).colorScheme.surfaceContainerHighest.withValues(alpha: 0.72),
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

class _Failure extends StatelessWidget {
  const _Failure({required this.message, required this.onRetry, this.onReset});

  final String message;
  final VoidCallback onRetry;
  final VoidCallback? onReset;

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
          if (onReset != null)
            TextButton.icon(
              key: const ValueKey('commerce-reset-filters'),
              onPressed: onReset,
              icon: const Icon(Icons.filter_alt_off_outlined),
              label: Text(AirmiusScope.of(context).t('events.resetFilters')),
            ),
        ],
      ),
    ),
  );
}

const _commerceListStatuses = <String, List<String>>{
  'products': ['draft', 'review', 'published', 'rejected', 'archived'],
  'orders': [
    'pending',
    'awaiting_transfer',
    'completed',
    'cancelled',
    'refunded',
    'partially_refunded',
    'failed',
  ],
  'return_requests': [
    'requested',
    'approved',
    'rejected',
    'received',
    'refunded',
    'closed',
  ],
  'payouts': ['requested', 'prepared', 'paid', 'failed', 'cancelled'],
  'payout_profiles': ['draft', 'review', 'approved', 'blocked'],
  'coupons': ['1', '0'],
  'addons': ['1', '0'],
  'tax_rates': ['1', '0'],
  'shipping_rates': ['1', '0'],
  'public_contact_requests': ['new', 'in_progress', 'approved', 'completed'],
  'seller_applications': ['pending', 'approved', 'rejected'],
  'website_requests': [
    'new',
    'contacted',
    'quoted',
    'in_progress',
    'done',
    'cancelled',
  ],
  'campaigns': [
    'draft',
    'pending_payment',
    'pending_review',
    'active',
    'paused',
    'completed',
    'rejected',
  ],
};

class _CommerceOpsData {
  const _CommerceOpsData({required this.dashboard, required this.catalog});

  final Map<String, dynamic> dashboard;
  final Map<String, dynamic> catalog;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _list(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <Map<String, dynamic>>[];

List<Map<String, dynamic>> _paged(Object? value) => _list(_map(value)['data']);

String _text(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

bool _bool(Object? value) =>
    value == true || value == 1 || value?.toString() == '1';

String _decimal(int cents) => (cents / 100).toStringAsFixed(2);

String _optionalDecimal(Object? cents) =>
    cents == null ? '' : _decimal(_int(cents));

String _optionalInt(Object? value) => value == null ? '' : '${_int(value)}';

int _cents(Object? value) {
  final normalized = _text(value).replaceAll(',', '.');
  return ((double.tryParse(normalized) ?? 0) * 100).round();
}

int? _optionalCents(Object? value) =>
    _text(value).isEmpty ? null : _cents(value);

int _parseInt(Object? value) =>
    int.tryParse(_text(value).replaceAll(RegExp(r'[^0-9-]'), '')) ?? 0;

int? _optionalParsedInt(Object? value) =>
    _text(value).isEmpty ? null : _parseInt(value);

double _parseDouble(Object? value) =>
    double.tryParse(_text(value).replaceAll(',', '.')) ?? 0;

Object? _nullable(Object? value) => _text(value).isEmpty ? null : value;

List<String> _lines(Object? value) => _text(value)
    .split(RegExp(r'\r?\n'))
    .map((line) => line.trim())
    .where((line) => line.isNotEmpty)
    .toList();

List<String> _stringList(Object? value) =>
    value is List ? value.map((item) => '$item').toList() : const [];

String _attributesText(Object? value) {
  if (value is! Map) return '';
  return value.entries
      .map((entry) {
        final raw = entry.value;
        final text = raw is List ? raw.join(', ') : '$raw';
        return '${entry.key}: $text';
      })
      .join('\n');
}
