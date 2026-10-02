import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

typedef _Json = Map<String, dynamic>;

class _BackofficeLookupDialog extends StatefulWidget {
  const _BackofficeLookupDialog({required this.client, required this.kind});
  final AirmiusApiClient client;
  final String kind;

  @override
  State<_BackofficeLookupDialog> createState() =>
      _BackofficeLookupDialogState();
}

class _BackofficeLookupDialogState extends State<_BackofficeLookupDialog> {
  final _search = TextEditingController();
  late Future<_Json> _future;
  int _page = 1;
  String _query = '';

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_Json> _load() => widget.client.adminBackofficeLookup(
    widget.kind,
    query: _query,
    page: _page,
  );

  void _reload() => setState(() => _future = _load());

  void _submitSearch() {
    final query = _search.text.trim();
    if (query.runes.length > 120) return;
    _query = query;
    _page = 1;
    _reload();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(
        t(
          widget.kind == 'users'
              ? 'backoffice.user'
              : widget.kind == 'clubs'
              ? 'backoffice.club'
              : 'backoffice.invoice',
        ),
      ),
      content: SizedBox(
        width: 480,
        height: MediaQuery.sizeOf(context).height * 0.55,
        child: Column(
          children: [
            TextField(
              key: const ValueKey('lookup-search'),
              controller: _search,
              maxLength: 120,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _submitSearch(),
              decoration: InputDecoration(
                labelText: t('adminNative.search'),
                suffixIcon: IconButton(
                  tooltip: t('adminNative.search'),
                  icon: const Icon(Icons.search),
                  onPressed: _submitSearch,
                ),
              ),
            ),
            Expanded(
              child: FutureBuilder<_Json>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  if (snapshot.hasError) {
                    return Center(
                      child: TextButton.icon(
                        onPressed: _reload,
                        icon: const Icon(Icons.refresh),
                        label: Text(t('common.retry')),
                      ),
                    );
                  }
                  final records = _maps(snapshot.data?['data']);
                  final meta = _map(snapshot.data?['meta']);
                  return Column(
                    children: [
                      Text('${_integer(meta['total'])}'),
                      Expanded(
                        child: ListView.builder(
                          itemCount: records.length,
                          itemBuilder: (context, index) {
                            final record = records[index];
                            return ListTile(
                              key: ValueKey('lookup-record-${record['id']}'),
                              title: Text(
                                _text(
                                  record['name'],
                                  fallback: _text(record['number']),
                                ),
                              ),
                              subtitle: Text(
                                _text(
                                  record['email'],
                                  fallback: _text(record['title']),
                                ),
                              ),
                              onTap: () => Navigator.pop(context, record),
                            );
                          },
                        ),
                      ),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          IconButton(
                            key: const ValueKey('lookup-previous'),
                            tooltip: t('adminNative.previous'),
                            icon: const Icon(Icons.chevron_left),
                            onPressed: _page > 1
                                ? () {
                                    _page--;
                                    _reload();
                                  }
                                : null,
                          ),
                          Text(
                            '$_page / ${_integer(meta['last_page'], fallback: 1)}',
                          ),
                          IconButton(
                            key: const ValueKey('lookup-next'),
                            tooltip: t('adminNative.next'),
                            icon: const Icon(Icons.chevron_right),
                            onPressed: _page < _integer(meta['last_page'])
                                ? () {
                                    _page++;
                                    _reload();
                                  }
                                : null,
                          ),
                        ],
                      ),
                    ],
                  );
                },
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
      ],
    );
  }
}

class AdminBackofficeScreen extends StatefulWidget {
  const AdminBackofficeScreen({super.key, this.initialSection = 'overview'});

  final String initialSection;

  @override
  State<AdminBackofficeScreen> createState() => _AdminBackofficeScreenState();
}

class _AdminBackofficeScreenState extends State<AdminBackofficeScreen> {
  Future<_Json>? _future;
  _Json? _lastData;
  String _section = 'overview';
  bool _busy = false;
  int _contractsPage = 1;
  String _contractsStatus = 'all';
  String _contractsCategory = 'all';
  final _contractsSearch = TextEditingController();
  String _contractsQuery = '';
  final _appliedSearch = <String, String>{};
  final _pages = <String, int>{};
  final _subscriptionSearch = <String, TextEditingController>{
    'user_subscriptions': TextEditingController(),
    'club_subscriptions': TextEditingController(),
  };

  @override
  void dispose() {
    _contractsSearch.dispose();
    for (final controller in _subscriptionSearch.values) {
      controller.dispose();
    }
    super.dispose();
  }

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

  Future<_Json> _load() async {
    final response = await _client.adminBackofficeDashboard(
      pages: _pages,
      userSubscriptionsQuery: _appliedSearch['user_subscriptions'] ?? '',
      clubSubscriptionsQuery: _appliedSearch['club_subscriptions'] ?? '',
      contractsPage: _contractsPage,
      contractsStatus: _contractsStatus,
      contractsCategory: _contractsCategory,
      contractsQuery: _contractsQuery,
    );
    final data = _map(response['data']);
    _lastData = data;
    return data;
  }

  void _clearFilters() {
    _pages.clear();
    _appliedSearch.clear();
    for (final controller in _subscriptionSearch.values) { controller.clear(); }
    _contractsPage = 1;
    _contractsStatus = 'all';
    _contractsCategory = 'all';
    _contractsQuery = '';
    _contractsSearch.clear();
    _reload();
  }

  void _searchContracts() {
    final query = _contractsSearch.text.trim();
    if (query.runes.length > 120) return;
    _contractsQuery = query;
    _contractsPage = 1;
    _reload();
  }

  void _reload() {
    setState(() {
      _future = _load();
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
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('backoffice.title'),
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
      body: FutureBuilder<_Json>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done &&
              !snapshot.hasData && _lastData == null) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError && _lastData == null) {
            final error = snapshot.error;
            return _BackofficeMessage(
              icon: Icons.cloud_off_outlined,
              title: error is AirmiusApiException
                  ? error.userMessage
                  : t('backoffice.loadFailed'),
              action: Wrap(children: [FilledButton.icon(
                onPressed: _reload,
                icon: const Icon(Icons.refresh_outlined),
                label: Text(t('common.retry')),
              ), IconButton(tooltip: t('events.resetFilters'), onPressed: _clearFilters, icon: const Icon(Icons.filter_alt_off_outlined))]),
            );
          }
          final data = snapshot.data ?? _lastData ?? const <String, dynamic>{};
          final sections = _availableSections(data);
          if (!sections.containsKey(_section)) {
            _section = sections.keys.first;
          }
          return PageFrame(
            title: t('backoffice.title'),
            subtitle: t('backoffice.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (snapshot.hasError) Wrap(spacing: 8, children: [
                  Text(t('common.errorDetails')),
                  IconButton(tooltip: t('common.retry'), onPressed: _reload, icon: const Icon(Icons.refresh)),
                  IconButton(key: const ValueKey('backoffice-clear-filters'), tooltip: t('events.resetFilters'), onPressed: _clearFilters, icon: const Icon(Icons.filter_alt_off_outlined)),
                ]),
                _hero(data),
                const SizedBox(height: 14),
                _sectionPicker(sections),
                if (_busy ||
                    snapshot.connectionState != ConnectionState.done) ...[
                  const SizedBox(height: 10),
                  const LinearProgressIndicator(minHeight: 3),
                ],
                const SizedBox(height: 14),
                _content(data),
              ],
            ),
          );
        },
      ),
    );
  }

  Map<String, String> _availableSections(_Json data) {
    final t = AirmiusScope.of(context).t;
    final abilities = _map(data['abilities']);
    return {
      'overview': t('backoffice.overview'),
      if (abilities['subscriptions_manage'] == true)
        'subscriptions': t('backoffice.subscriptions'),
      if (abilities['billing_manage'] == true ||
          abilities['subscriptions_manage'] == true)
        'billing': t('backoffice.billing'),
      if (abilities['finance_view'] == true ||
          abilities['billing_manage'] == true)
        'contracts': t('backoffice.contracts'),
    };
  }

  Widget _hero(_Json data) {
    final t = AirmiusScope.of(context).t;
    final summary = _map(data['summary']);
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('backoffice.eyebrow')),
          const SizedBox(height: 7),
          Text(
            t('backoffice.headline'),
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 7),
          Text(t('backoffice.body')),
          const SizedBox(height: 15),
          Wrap(
            spacing: 9,
            runSpacing: 9,
            children: [
              _BackofficeMetric(
                value:
                    '${_integer(summary['active_user_subscriptions']) + _integer(summary['active_club_subscriptions'])}',
                label: t('backoffice.activeSubscriptions'),
                icon: Icons.verified_outlined,
              ),
              _BackofficeMetric(
                value: '${_integer(summary['pending_transfers'])}',
                label: t('backoffice.pendingTransfers'),
                icon: Icons.account_balance_outlined,
              ),
              _BackofficeMetric(
                value:
                    '${_map(data['abilities'])['billing_manage'] == true ? _integer(summary['open_invoices']) : _integer(summary['open_subscription_invoices'])}',
                label: t('backoffice.openInvoices'),
                icon: Icons.receipt_long_outlined,
              ),
              _BackofficeMetric(
                value:
                    _map(data['billing_summary'])['revenue']?.toString() ??
                    _money(
                      _integer(summary['subscription_revenue_cents']) +
                          _integer(summary['payment_revenue_cents']),
                    ),
                label: t('backoffice.revenue'),
                icon: Icons.payments_outlined,
              ),
              _BackofficeMetric(
                value: _money(_integer(summary['monthly_contract_cost_cents'])),
                label: t('backoffice.monthlyCosts'),
                icon: Icons.description_outlined,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _sectionPicker(Map<String, String> sections) {
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

  Widget _content(_Json data) => switch (_section) {
    'subscriptions' => _subscriptions(data),
    'billing' => _billing(data),
    'contracts' => _contracts(data),
    _ => _overview(data),
  };

  Widget _overview(_Json data) {
    final t = AirmiusScope.of(context).t;
    final abilities = _map(data['abilities']);
    final rows = <({IconData icon, String title, String value})>[
      (
        icon: Icons.sell_outlined,
        title: t('backoffice.plans'),
        value: '${_maps(data['plans']).length}',
      ),
      (
        icon: Icons.people_outline,
        title: t('backoffice.userSubscriptions'),
        value: '${_listTotal(data, 'user_subscriptions')}',
      ),
      (
        icon: Icons.apartment_outlined,
        title: t('backoffice.clubSubscriptions'),
        value: '${_listTotal(data, 'club_subscriptions')}',
      ),
      (
        icon: Icons.payments_outlined,
        title: t('backoffice.payments'),
        value: '${_listTotal(data, 'payments')}',
      ),
      (
        icon: Icons.description_outlined,
        title: t('backoffice.contracts'),
        value: '${_integer(_map(data['summary'])['contracts'])}',
      ),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        LayoutBuilder(
          builder: (context, constraints) {
            final columns = constraints.maxWidth >= 640 ? 2 : 1;
            const gap = 10.0;
            final width =
                (constraints.maxWidth - gap * (columns - 1)) / columns;
            return Wrap(
              spacing: gap,
              runSpacing: gap,
              children: rows
                  .map(
                    (item) => SizedBox(
                      width: width,
                      child: _BackofficeOverviewTile(
                        icon: item.icon,
                        title: item.title,
                        value: item.value,
                      ),
                    ),
                  )
                  .toList(),
            );
          },
        ),
        const SizedBox(height: 14),
        AirmiusPanel(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.security_outlined,
                color: Theme.of(context).colorScheme.primary,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      t('backoffice.secureTitle'),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 5),
                    Text(t('backoffice.secureBody')),
                    const SizedBox(height: 8),
                    Text(
                      [
                        if (abilities['subscriptions_manage'] == true)
                          t('backoffice.permissionSubscriptions'),
                        if (abilities['billing_manage'] == true)
                          t('backoffice.permissionBilling'),
                        if (abilities['finance_view'] == true)
                          t('backoffice.permissionFinance'),
                      ].join(' · '),
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.primary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  int _listTotal(_Json data, String list) => _integer(
    _map(_map(data['pagination'])[list])['total'],
    fallback: _maps(data[list]).length,
  );

  Widget _listControls(_Json data, String list) {
    final t = AirmiusScope.of(context).t;
    final meta = _map(_map(data['pagination'])[list]);
    final page = _integer(meta['current_page'], fallback: 1);
    final last = _integer(meta['last_page'], fallback: 1);
    final search = _subscriptionSearch[list];
    void find() {
      final query = search?.text.trim() ?? '';
      if (query.runes.length > 120) return;
      _appliedSearch[list] = query;
      _pages[list] = 1;
      _reload();
    }

    return Column(
      children: [
        if (search != null)
          TextField(
            key: ValueKey('$list-search'),
            controller: search,
            maxLength: 120,
            textInputAction: TextInputAction.search,
            onSubmitted: (_) => find(),
            decoration: InputDecoration(
              labelText: t('adminNative.search'),
              suffixIcon: IconButton(
                tooltip: t('adminNative.search'),
                icon: const Icon(Icons.search),
                onPressed: find,
              ),
            ),
          ),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            IconButton(
              key: ValueKey('$list-previous'),
              tooltip: t('adminNative.previous'),
              icon: const Icon(Icons.chevron_left),
              onPressed: !_busy && page > 1
                  ? () {
                      _pages[list] = page - 1;
                      _reload();
                    }
                  : null,
            ),
            Text('$page / $last'),
            IconButton(
              key: ValueKey('$list-next'),
              tooltip: t('adminNative.next'),
              icon: const Icon(Icons.chevron_right),
              onPressed: !_busy && page < last
                  ? () {
                      _pages[list] = page + 1;
                      _reload();
                    }
                  : null,
            ),
          ],
        ),
      ],
    );
  }

  Widget _documentButton(String type, int id) => IconButton.filledTonal(
    key: ValueKey('document-$type-$id'),
    tooltip: AirmiusScope.of(context).t('application.downloadDocument'),
    icon: const Icon(Icons.download_outlined),
    onPressed: _busy ? null : () => _saveDocument(type, id),
  );

  Future<void> _saveDocument(String type, int id) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final data = _map(
        (await _client.adminBackofficeDocument(type, id))['data'],
      );
      final bytes = base64Decode(_text(data['content_base64']));
      if (data['content_type'] != 'application/pdf' ||
          bytes.length < 5 ||
          ascii.decode(bytes.take(5).toList()) != '%PDF-') {
        throw const FormatException('Invalid PDF response');
      }
      if (!mounted) return;
      await FilePicker.platform.saveFile(
        dialogTitle: AirmiusScope.of(context).t('application.downloadDocument'),
        fileName: _text(
          data['filename'],
          fallback: 'invoice.pdf',
        ).replaceAll(RegExp(r'[^A-Za-z0-9_.-]'), '_'),
        type: FileType.custom,
        allowedExtensions: const ['pdf'],
        bytes: bytes,
      );
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error is AirmiusApiException
                  ? error.userMessage
                  : AirmiusScope.of(context).t('common.errorDetails'),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<_Json?> _selectRecord(String kind) => showDialog<_Json>(
    context: context,
    builder: (_) => _BackofficeLookupDialog(client: _client, kind: kind),
  );

  Widget _lookupButton(
    String kind,
    String label,
    String value,
    ValueChanged<_Json> onSelected,
  ) => OutlinedButton.icon(
    key: ValueKey('select-$kind'),
    icon: const Icon(Icons.search),
    label: Text(value.isEmpty ? label : '$label: $value', softWrap: true),
    onPressed: () async {
      final record = await _selectRecord(kind);
      if (record != null && mounted) onSelected(record);
    },
  );

  Widget _subscriptions(_Json data) {
    final t = AirmiusScope.of(context).t;
    final plans = _maps(data['plans']);
    final userSubscriptions = _maps(data['user_subscriptions']);
    final clubSubscriptions = _maps(data['club_subscriptions']);
    final transfers = _maps(data['pending_transfers']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: FilledButton.icon(
            onPressed: _busy ? null : () => _assignSubscription(data),
            icon: const Icon(Icons.add_circle_outline),
            label: Text(t('backoffice.assignSubscription')),
          ),
        ),
        const SizedBox(height: 14),
        _BackofficeHeading(title: t('backoffice.plans'), count: plans.length),
        const SizedBox(height: 9),
        if (plans.isEmpty)
          _empty(Icons.sell_outlined, t('backoffice.noPlans'))
        else
          ...plans.map(
            (plan) => _cardSpacing(
              _BackofficeCard(
                icon: Icons.sell_outlined,
                title: _text(plan['name'], fallback: t('backoffice.plan')),
                subtitle:
                    '${_money(_integer(plan['monthly_price_cents']))} / ${t('backoffice.month')} · ${_money(_integer(plan['yearly_price_cents']))} / ${t('backoffice.year')}',
                status: plan['is_active'] == true
                    ? t('backoffice.status.active')
                    : t('backoffice.status.inactive'),
                details: [
                  _text(plan['description']),
                  '${t('backoffice.target')}: ${_text(plan['target_actor'], fallback: '–')}',
                  '${t('backoffice.usage')}: ${_integer(plan['user_subscriptions_count']) + _integer(plan['club_subscriptions_count'])}',
                ],
                actions: [
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _editPlan(plan),
                    icon: const Icon(Icons.edit_outlined),
                    label: Text(t('common.edit')),
                  ),
                ],
              ),
            ),
          ),
        const SizedBox(height: 8),
        _BackofficeHeading(
          title: t('backoffice.pendingTransfers'),
          count: _listTotal(data, 'pending_transfers'),
        ),
        _listControls(data, 'pending_transfers'),
        const SizedBox(height: 9),
        if (transfers.isEmpty)
          _empty(Icons.check_circle_outline, t('backoffice.noPendingTransfers'))
        else
          ...transfers.map(
            (transfer) => _cardSpacing(
              _BackofficeCard(
                icon: Icons.account_balance_outlined,
                title: _ownerLabel(transfer),
                subtitle:
                    '${_money(_integer(transfer['amount_cents']), currency: _text(transfer['currency'], fallback: 'EUR'))} · ${_text(transfer['payment_reference'])}',
                status: t('backoffice.status.awaiting_transfer'),
                details: [
                  _text(_map(transfer['plan'])['name']),
                  '${t('backoffice.due')}: ${_date(transfer['due_at'])}',
                ],
                actions: [
                  FilledButton.icon(
                    onPressed: _busy ? null : () => _confirmTransfer(transfer),
                    icon: const Icon(Icons.done_all_outlined),
                    label: Text(t('backoffice.markPaid')),
                  ),
                ],
              ),
            ),
          ),
        const SizedBox(height: 8),
        _BackofficeHeading(
          title: t('backoffice.userSubscriptions'),
          count: _listTotal(data, 'user_subscriptions'),
        ),
        _listControls(data, 'user_subscriptions'),
        const SizedBox(height: 9),
        if (userSubscriptions.isEmpty)
          _empty(Icons.person_outline, t('backoffice.noSubscriptions'))
        else
          ...userSubscriptions.map(
            (subscription) => _subscriptionCard(subscription, isClub: false),
          ),
        const SizedBox(height: 8),
        _BackofficeHeading(
          title: t('backoffice.clubSubscriptions'),
          count: _listTotal(data, 'club_subscriptions'),
        ),
        _listControls(data, 'club_subscriptions'),
        const SizedBox(height: 9),
        if (clubSubscriptions.isEmpty)
          _empty(Icons.apartment_outlined, t('backoffice.noSubscriptions'))
        else
          ...clubSubscriptions.map(
            (subscription) => _subscriptionCard(subscription, isClub: true),
          ),
      ],
    );
  }

  Widget _subscriptionCard(_Json subscription, {required bool isClub}) {
    final t = AirmiusScope.of(context).t;
    final status = _text(subscription['status'], fallback: 'active');
    final owner = isClub
        ? _map(subscription['club'])
        : _map(subscription['user']);
    return _cardSpacing(
      _BackofficeCard(
        icon: isClub ? Icons.apartment_outlined : Icons.person_outline,
        title: _text(owner['name'], fallback: t('backoffice.subscription')),
        subtitle: _text(
          _map(subscription['plan'])['name'],
          fallback: t('backoffice.plan'),
        ),
        status: _status(status),
        details: [
          '${t('backoffice.provider')}: ${_text(subscription['payment_provider'], fallback: '–')}',
          '${t('backoffice.periodEnds')}: ${_date(subscription['current_period_ends_at'])}',
        ],
        actions: [
          if (status != 'cancelled' && status != 'cancels_at_period_end')
            OutlinedButton.icon(
              onPressed: _busy
                  ? null
                  : () => _cancelSubscription(subscription, isClub: isClub),
              icon: const Icon(Icons.cancel_outlined),
              label: Text(t('backoffice.cancel')),
            ),
          FilledButton.tonalIcon(
            onPressed: _busy
                ? null
                : () => _renewSubscription(subscription, isClub: isClub),
            icon: const Icon(Icons.update_outlined),
            label: Text(t('backoffice.renew')),
          ),
        ],
      ),
    );
  }

  Widget _billing(_Json data) {
    final t = AirmiusScope.of(context).t;
    final abilities = _map(data['abilities']);
    final canBilling = abilities['billing_manage'] == true;
    final subscriptionInvoices = _maps(data['subscription_invoices']);
    final invoices = _maps(data['invoices']);
    final payments = _maps(data['payments']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (canBilling)
          Wrap(
            spacing: 9,
            runSpacing: 9,
            children: [
              FilledButton.icon(
                onPressed: _busy ? null : () => _createInvoice(data),
                icon: const Icon(Icons.note_add_outlined),
                label: Text(t('backoffice.newInvoice')),
              ),
              OutlinedButton.icon(
                onPressed: _busy ? null : () => _createPayment(data),
                icon: const Icon(Icons.add_card_outlined),
                label: Text(t('backoffice.newPayment')),
              ),
            ],
          ),
        if (canBilling) const SizedBox(height: 14),
        _BackofficeHeading(
          title: t('backoffice.subscriptionInvoices'),
          count: _listTotal(data, 'subscription_invoices'),
        ),
        _listControls(data, 'subscription_invoices'),
        const SizedBox(height: 9),
        if (subscriptionInvoices.isEmpty)
          _empty(Icons.receipt_outlined, t('backoffice.noInvoices'))
        else
          ...subscriptionInvoices.map(
            (invoice) => _cardSpacing(
              _BackofficeCard(
                icon: Icons.receipt_outlined,
                title: _text(
                  invoice['number'],
                  fallback: t('backoffice.invoice'),
                ),
                subtitle:
                    '${_ownerLabel(invoice)} · ${_money(_integer(invoice['amount_cents']), currency: _text(invoice['currency'], fallback: 'EUR'))}',
                status: _status(_text(invoice['status'])),
                details: [
                  _text(invoice['title']),
                  '${t('backoffice.due')}: ${_date(invoice['due_at'])}',
                ],
                actions: [
                  if (invoice['can_download'] == true)
                    _documentButton('subscription', _integer(invoice['id'])),
                  if (_text(invoice['status']) != 'paid')
                    FilledButton.icon(
                      onPressed: _busy
                          ? null
                          : () => _markSubscriptionInvoicePaid(invoice),
                      icon: const Icon(Icons.done_all_outlined),
                      label: Text(t('backoffice.markPaid')),
                    ),
                ],
              ),
            ),
          ),
        if (canBilling) ...[
          const SizedBox(height: 8),
          _BackofficeHeading(
            title: t('backoffice.billing'),
            count: _listTotal(data, 'invoices'),
          ),
          _listControls(data, 'invoices'),
          const SizedBox(height: 9),
          if (invoices.isEmpty)
            _empty(Icons.description_outlined, t('backoffice.noInvoices'))
          else
            ...invoices.map(
              (invoice) => _cardSpacing(
                _BackofficeCard(
                  icon: Icons.description_outlined,
                  title: _text(
                    invoice['number'],
                    fallback: t('backoffice.invoice'),
                  ),
                  subtitle:
                      '${_ownerLabel(invoice)} · ${_text(invoice['amount_display'], fallback: _decimalMoney(invoice['amount']))}',
                  status: _status(_text(invoice['status'])),
                  details: [
                    _text(invoice['title']),
                    _text(invoice['type_label']),
                    '${t('backoffice.paidAmount')}: ${_text(invoice['paid_amount_display'], fallback: _decimalMoney(invoice['paid_amount']))}',
                    '${t('backoffice.due')}: ${_date(invoice['due_date'])}',
                  ],
                  actions: [
                    if (invoice['can_download'] == true)
                      _documentButton(
                        _text(invoice['type']),
                        _integer(invoice['raw_id']),
                      ),
                    if (invoice['can_update_status'] == true)
                      OutlinedButton.icon(
                        onPressed: _busy
                            ? null
                            : () => _chooseInvoiceStatus(invoice),
                        icon: const Icon(Icons.sync_outlined),
                        label: Text(t('backoffice.changeStatus')),
                      ),
                    if (invoice['can_delete'] == true)
                      IconButton.filledTonal(
                        tooltip: t('common.delete'),
                        onPressed: _busy ? null : () => _deleteInvoice(invoice),
                        icon: const Icon(Icons.delete_outline),
                      ),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 8),
          _BackofficeHeading(
            title: t('backoffice.payments'),
            count: _listTotal(data, 'payments'),
          ),
          _listControls(data, 'payments'),
          const SizedBox(height: 9),
          if (payments.isEmpty)
            _empty(Icons.payments_outlined, t('backoffice.noPayments'))
          else
            ...payments.map(
              (payment) => _cardSpacing(
                _BackofficeCard(
                  icon: Icons.payments_outlined,
                  title: _ownerLabel(payment),
                  subtitle: _decimalMoney(payment['amount']),
                  status: _status(_text(payment['status'])),
                  details: [
                    '${t('backoffice.method')}: ${_text(payment['method'], fallback: '–')}',
                    '${t('backoffice.reference')}: ${_text(payment['reference'], fallback: '–')}',
                    '${t('backoffice.paidAt')}: ${_date(payment['paid_at'])}',
                  ],
                  actions: [
                    IconButton.filledTonal(
                      tooltip: t('common.delete'),
                      onPressed: _busy ? null : () => _deletePayment(payment),
                      icon: const Icon(Icons.delete_outline),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ],
    );
  }

  Widget _contracts(_Json data) {
    final t = AirmiusScope.of(context).t;
    final abilities = _map(data['abilities']);
    final canEdit = abilities['finance_edit'] == true;
    final contracts = _maps(data['contracts']);
    final meta = _map(data['contracts_meta']);
    final options = _map(data['options']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: _contractsSearch,
          maxLength: 120,
          textInputAction: TextInputAction.search,
          onSubmitted: (_) => _searchContracts(),
          decoration: InputDecoration(
            labelText: t('adminNative.search'),
            suffixIcon: IconButton(
              tooltip: t('adminNative.search'),
              icon: const Icon(Icons.search),
              onPressed: _searchContracts,
            ),
          ),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _contractsStatus,
          isExpanded: true,
          decoration: InputDecoration(labelText: t('backoffice.status')),
          items: ['all', ..._strings(options['contract_statuses'])]
              .map(
                (value) => DropdownMenuItem(
                  value: value,
                  child: Text(
                    value == 'all'
                        ? t('editorial.status.all')
                        : _translatedValue('backoffice.status', value),
                  ),
                ),
              )
              .toList(),
          onChanged: (value) {
            if (value == null) return;
            _contractsStatus = value;
            _contractsPage = 1;
            _reload();
          },
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _contractsCategory,
          isExpanded: true,
          decoration: InputDecoration(labelText: t('backoffice.category')),
          items: ['all', ..._strings(options['contract_categories'])]
              .map(
                (value) => DropdownMenuItem(
                  value: value,
                  child: Text(
                    value == 'all'
                        ? t('editorial.status.all')
                        : _translatedValue('backoffice.category', value),
                  ),
                ),
              )
              .toList(),
          onChanged: (value) {
            if (value == null) return;
            _contractsCategory = value;
            _contractsPage = 1;
            _reload();
          },
        ),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            IconButton(
              tooltip: t('adminNative.previous'),
              onPressed: _contractsPage > 1
                  ? () {
                      _contractsPage--;
                      _reload();
                    }
                  : null,
              icon: const Icon(Icons.chevron_left),
            ),
            Text(
              '${_integer(meta['current_page'], fallback: 1)} / ${_integer(meta['last_page'], fallback: 1)}',
            ),
            IconButton(
              tooltip: t('adminNative.next'),
              onPressed: _contractsPage < _integer(meta['last_page'])
                  ? () {
                      _contractsPage++;
                      _reload();
                    }
                  : null,
              icon: const Icon(Icons.chevron_right),
            ),
          ],
        ),
        if (canEdit) ...[
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: FilledButton.icon(
              onPressed: _busy ? null : () => _editContract(data),
              icon: const Icon(Icons.add_circle_outline),
              label: Text(t('backoffice.newContract')),
            ),
          ),
          const SizedBox(height: 14),
        ],
        if (contracts.isEmpty)
          _empty(Icons.description_outlined, t('backoffice.noContracts'))
        else
          ...contracts.map(
            (contract) => _cardSpacing(
              _BackofficeCard(
                icon: Icons.description_outlined,
                title: _text(
                  contract['name'],
                  fallback: t('backoffice.contract'),
                ),
                subtitle:
                    '${_decimalMoney(contract['amount'], currency: _text(contract['currency'], fallback: 'EUR'))} · ${_translatedValue('backoffice.interval', contract['billing_interval'])}',
                status: _status(_text(contract['status'])),
                details: [
                  _text(contract['vendor']),
                  '${t('backoffice.monthlyEquivalent')}: ${_decimalMoney(contract['monthly_equivalent'], currency: _text(contract['currency'], fallback: 'EUR'))}',
                  '${t('backoffice.nextDue')}: ${_date(contract['next_due_on'])}',
                  if (_text(contract['notice_until_on']).isNotEmpty)
                    '${t('backoffice.noticeUntil')}: ${_date(contract['notice_until_on'])}',
                ],
                actions: canEdit
                    ? [
                        OutlinedButton.icon(
                          onPressed: _busy
                              ? null
                              : () => _editContract(data, contract: contract),
                          icon: const Icon(Icons.edit_outlined),
                          label: Text(t('common.edit')),
                        ),
                        IconButton.filledTonal(
                          tooltip: t('common.delete'),
                          onPressed: _busy
                              ? null
                              : () => _deleteContract(contract),
                          icon: const Icon(Icons.delete_outline),
                        ),
                      ]
                    : const [],
              ),
            ),
          ),
      ],
    );
  }

  Widget _empty(IconData icon, String title) =>
      _BackofficeMessage(icon: icon, title: title);

  Widget _cardSpacing(Widget child) =>
      Padding(padding: const EdgeInsets.only(bottom: 11), child: child);

  Future<void> _editPlan(_Json plan) async {
    final t = AirmiusScope.of(context).t;
    final monthly = TextEditingController(
      text: (_integer(plan['monthly_price_cents']) / 100).toStringAsFixed(2),
    );
    final yearly = TextEditingController(
      text: (_integer(plan['yearly_price_cents']) / 100).toStringAsFixed(2),
    );
    var active = plan['is_active'] == true;
    var isPublic = plan['is_public'] == true;
    final saved = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('backoffice.editPlan')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _text(plan['name']),
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: monthly,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: InputDecoration(
                    labelText: t('backoffice.monthlyPrice'),
                    prefixIcon: const Icon(Icons.euro_outlined),
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: yearly,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: InputDecoration(
                    labelText: t('backoffice.yearlyPrice'),
                    prefixIcon: const Icon(Icons.euro_outlined),
                  ),
                ),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: Text(t('backoffice.active')),
                  value: active,
                  onChanged: (value) => setDialogState(() => active = value),
                ),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: Text(t('backoffice.public')),
                  value: isPublic,
                  onChanged: (value) => setDialogState(() => isPublic = value),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(t('common.save')),
            ),
          ],
        ),
      ),
    );
    if (saved != true) {
      monthly.dispose();
      yearly.dispose();
      return;
    }
    final monthlyCents = _amountCents(monthly.text);
    final yearlyCents = _amountCents(yearly.text);
    monthly.dispose();
    yearly.dispose();
    await _run(
      () => _client.adminUpdateSubscriptionPlan(_integer(plan['id']), {
        'target_actor': _text(plan['target_actor'], fallback: 'sportler'),
        'description': _nullableText(plan['description']),
        'monthly_price_cents': monthlyCents,
        'yearly_price_cents': yearlyCents,
        'member_limit': plan['member_limit'],
        'team_limit': plan['team_limit'],
        'storage_gb': _integer(plan['storage_gb'], fallback: 1),
        'minimum_term_months': plan['minimum_term_months'],
        'cancellation_notice_days': plan['cancellation_notice_days'],
        'cta_label': _nullableText(plan['cta_label']),
        'badge': _nullableText(plan['badge']),
        'is_public': isPublic,
        'is_active': active,
        'country_prices': _maps(plan['country_prices']),
      }),
      success: t('backoffice.planSaved'),
    );
  }

  Future<void> _assignSubscription(_Json data) async {
    final t = AirmiusScope.of(context).t;
    final plans = _maps(data['plans']);
    if (plans.isEmpty) return;
    var actor = 'user';
    _Json? owner;
    var planId = _integer(plans.first['id']);
    var status = 'active';
    final saved = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('backoffice.assignSubscription')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                SegmentedButton<String>(
                  segments: [
                    ButtonSegment(
                      value: 'user',
                      icon: const Icon(Icons.person_outline),
                      label: Text(t('backoffice.user')),
                    ),
                    ButtonSegment(
                      value: 'club',
                      icon: const Icon(Icons.apartment_outlined),
                      label: Text(t('backoffice.club')),
                    ),
                  ],
                  selected: {actor},
                  onSelectionChanged: (value) => setDialogState(() {
                    actor = value.first;
                    owner = null;
                  }),
                ),
                const SizedBox(height: 12),
                _lookupButton(
                  actor == 'user' ? 'users' : 'clubs',
                  t('backoffice.recipient'),
                  _text(owner?['name']),
                  (record) => setDialogState(() => owner = record),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: planId,
                  isExpanded: true,
                  decoration: InputDecoration(labelText: t('backoffice.plan')),
                  items: plans
                      .map(
                        (plan) => DropdownMenuItem(
                          value: _integer(plan['id']),
                          child: Text(
                            _text(plan['name']),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      setDialogState(() => planId = value ?? planId),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: status,
                  isExpanded: true,
                  decoration: InputDecoration(
                    labelText: t('backoffice.status'),
                  ),
                  items: const ['trialing', 'active', 'past_due', 'cancelled']
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(_status(value)),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      setDialogState(() => status = value ?? status),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: owner == null
                  ? null
                  : () => Navigator.pop(dialogContext, true),
              child: Text(t('common.save')),
            ),
          ],
        ),
      ),
    );
    if (saved != true || owner == null || !mounted) return;
    final body = {
      'user_subscription_id': null,
      'subscription_plan_id': planId,
      'status': status,
      'trial_ends_at': null,
      'current_period_ends_at': null,
      'payment_provider': 'manual',
    };
    await _run(
      () => actor == 'user'
          ? _client.adminAssignUserSubscription(_integer(owner!['id']), body)
          : _client.adminAssignClubSubscription(_integer(owner!['id']), body),
      success: t('backoffice.subscriptionSaved'),
    );
  }

  Future<void> _confirmTransfer(_Json transfer) async {
    final t = AirmiusScope.of(context).t;
    if (!await _confirm(
      title: t('backoffice.confirmPaymentTitle'),
      body: t('backoffice.confirmPaymentBody'),
      confirmLabel: t('backoffice.markPaid'),
    )) {
      return;
    }
    await _run(
      () => _client.adminMarkSubscriptionTransferPaid(_integer(transfer['id'])),
      success: t('backoffice.paymentConfirmed'),
    );
  }

  Future<void> _cancelSubscription(
    _Json subscription, {
    required bool isClub,
  }) async {
    final t = AirmiusScope.of(context).t;
    if (!await _confirm(
      title: t('backoffice.cancelTitle'),
      body: t('backoffice.cancelBody'),
      confirmLabel: t('backoffice.cancel'),
      destructive: true,
    )) {
      return;
    }
    await _run(
      () => isClub
          ? _client.adminCancelClubSubscription(_integer(subscription['id']))
          : _client.adminCancelUserSubscription(_integer(subscription['id'])),
      success: t('backoffice.subscriptionSaved'),
    );
  }

  Future<void> _renewSubscription(
    _Json subscription, {
    required bool isClub,
  }) async {
    final t = AirmiusScope.of(context).t;
    await _run(
      () => isClub
          ? _client.adminRenewClubSubscription(_integer(subscription['id']))
          : _client.adminRenewUserSubscription(_integer(subscription['id'])),
      success: t('backoffice.subscriptionSaved'),
    );
  }

  Future<void> _markSubscriptionInvoicePaid(_Json invoice) async {
    final t = AirmiusScope.of(context).t;
    if (!await _confirm(
      title: t('backoffice.confirmPaymentTitle'),
      body: t('backoffice.confirmPaymentBody'),
      confirmLabel: t('backoffice.markPaid'),
    )) {
      return;
    }
    await _run(
      () => _client.adminMarkSubscriptionInvoicePaid(_integer(invoice['id'])),
      success: t('backoffice.paymentConfirmed'),
    );
  }

  Future<void> _createInvoice(_Json data) async {
    final t = AirmiusScope.of(context).t;
    final sources = _strings(_map(data['options'])['invoice_sources']);
    var actor = 'user';
    int? ownerId;
    String ownerName = '';
    var source = sources.isEmpty ? 'custom' : sources.first;
    final title = TextEditingController();
    final amount = TextEditingController();
    final description = TextEditingController();
    final result = await showDialog<_Json>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            title: Text(t('backoffice.newInvoice')),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  SegmentedButton<String>(
                    segments: [
                      ButtonSegment(
                        value: 'user',
                        label: Text(t('backoffice.user')),
                        icon: const Icon(Icons.person_outline),
                      ),
                      ButtonSegment(
                        value: 'club',
                        label: Text(t('backoffice.club')),
                        icon: const Icon(Icons.apartment_outlined),
                      ),
                    ],
                    selected: {actor},
                    onSelectionChanged: (value) => setDialogState(() {
                      actor = value.first;
                      ownerId = null;
                      ownerName = '';
                    }),
                  ),
                  const SizedBox(height: 10),
                  _lookupButton(
                    actor == 'user' ? 'users' : 'clubs',
                    t('backoffice.recipient'),
                    ownerName,
                    (record) => setDialogState(() {
                      ownerId = _integer(record['id']);
                      ownerName = _text(record['name']);
                    }),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: source,
                    isExpanded: true,
                    decoration: InputDecoration(
                      labelText: t('backoffice.invoiceType'),
                    ),
                    items: sources
                        .map(
                          (value) => DropdownMenuItem(
                            value: value,
                            child: Text(
                              _translatedValue('backoffice.source', value),
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setDialogState(() => source = value ?? source),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: title,
                    textCapitalization: TextCapitalization.sentences,
                    decoration: InputDecoration(
                      labelText: t('backoffice.invoiceTitle'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: amount,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: InputDecoration(
                      labelText: t('backoffice.amount'),
                      prefixIcon: const Icon(Icons.euro_outlined),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: description,
                    minLines: 2,
                    maxLines: 4,
                    decoration: InputDecoration(
                      labelText: t('backoffice.description'),
                    ),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: Text(t('common.cancel')),
              ),
              FilledButton(
                onPressed: () {
                  if (ownerId == null ||
                      title.text.trim().isEmpty ||
                      _decimal(amount.text) <= 0) {
                    return;
                  }
                  Navigator.pop(dialogContext, {
                    'source': source,
                    'club_id': actor == 'club' ? ownerId : null,
                    'user_id': actor == 'user' ? ownerId : null,
                    'number': null,
                    'title': title.text.trim(),
                    'description': description.text.trim().isEmpty
                        ? null
                        : description.text.trim(),
                    'amount': _decimal(amount.text),
                    'status': 'open',
                    'due_date': DateTime.now()
                        .add(const Duration(days: 14))
                        .toIso8601String()
                        .split('T')
                        .first,
                    'issued_at': DateTime.now()
                        .toIso8601String()
                        .split('T')
                        .first,
                  });
                },
                child: Text(t('common.save')),
              ),
            ],
          );
        },
      ),
    );
    title.dispose();
    amount.dispose();
    description.dispose();
    if (result == null) return;
    await _run(
      () => _client.adminCreateInvoice(result),
      success: t('backoffice.invoiceSaved'),
    );
  }

  Future<void> _chooseInvoiceStatus(_Json invoice) async {
    final t = AirmiusScope.of(context).t;
    final status = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
              child: Text(
                t('backoffice.changeStatus'),
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
            ),
            ...const ['open', 'pending', 'paid', 'overdue', 'cancelled'].map(
              (value) => ListTile(
                minTileHeight: 52,
                leading: Icon(_statusIcon(value)),
                title: Text(_status(value)),
                onTap: () => Navigator.pop(sheetContext, value),
              ),
            ),
          ],
        ),
      ),
    );
    if (status == null) return;
    await _run(
      () => _client.adminUpdateInvoiceStatus(
        _integer(invoice['raw_id'] ?? invoice['id']),
        status,
      ),
      success: t('backoffice.invoiceSaved'),
    );
  }

  Future<void> _deleteInvoice(_Json invoice) async {
    final t = AirmiusScope.of(context).t;
    if (!await _confirm(
      title: t('backoffice.deleteInvoiceTitle'),
      body: t('backoffice.deleteInvoiceBody'),
      confirmLabel: t('common.delete'),
      destructive: true,
    )) {
      return;
    }
    await _run(
      () => _client.adminDeleteInvoice(
        _integer(invoice['raw_id'] ?? invoice['id']),
      ),
      success: t('backoffice.invoiceDeleted'),
    );
  }

  Future<void> _createPayment(_Json data) async {
    final t = AirmiusScope.of(context).t;
    int? userId;
    int? clubId;
    int? invoiceId;
    String userName = '';
    String clubName = '';
    String invoiceNumber = '';
    var method = 'bank_transfer';
    final amount = TextEditingController();
    final reference = TextEditingController();
    final result = await showDialog<_Json>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('backoffice.newPayment')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _lookupButton(
                  'users',
                  t('backoffice.user'),
                  userName,
                  (record) => setDialogState(() {
                    userId = _integer(record['id']);
                    userName = _text(record['name']);
                  }),
                ),
                const SizedBox(height: 10),
                _lookupButton(
                  'clubs',
                  t('backoffice.club'),
                  clubName,
                  (record) => setDialogState(() {
                    clubId = _integer(record['id']);
                    clubName = _text(record['name']);
                  }),
                ),
                const SizedBox(height: 10),
                _lookupButton(
                  'invoices',
                  t('backoffice.invoiceOptional'),
                  invoiceNumber,
                  (record) => setDialogState(() {
                    invoiceId = _integer(record['id']);
                    invoiceNumber = _text(record['number']);
                  }),
                ),
                if (invoiceId != null)
                  TextButton(
                    onPressed: () => setDialogState(() {
                      invoiceId = null;
                      invoiceNumber = '';
                    }),
                    child: Text(t('backoffice.noInvoice')),
                  ),
                const SizedBox(height: 10),
                TextField(
                  controller: amount,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: InputDecoration(
                    labelText: t('backoffice.amount'),
                    prefixIcon: const Icon(Icons.euro_outlined),
                  ),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  initialValue: method,
                  decoration: InputDecoration(
                    labelText: t('backoffice.method'),
                  ),
                  items:
                      const [
                            'bank_transfer',
                            'cash',
                            'card',
                            'paypal',
                            'stripe',
                            'manual',
                          ]
                          .map(
                            (value) => DropdownMenuItem(
                              value: value,
                              child: Text(
                                _translatedValue('backoffice.method', value),
                              ),
                            ),
                          )
                          .toList(),
                  onChanged: (value) =>
                      setDialogState(() => method = value ?? method),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: reference,
                  decoration: InputDecoration(
                    labelText: t('backoffice.reference'),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: userId == null || clubId == null
                  ? null
                  : () {
                      if (_decimal(amount.text) <= 0) return;
                      Navigator.pop(dialogContext, {
                        'club_id': clubId,
                        'user_id': userId,
                        'invoice_id': invoiceId,
                        'amount': _decimal(amount.text),
                        'status': 'paid',
                        'method': method,
                        'reference': reference.text.trim().isEmpty
                            ? null
                            : reference.text.trim(),
                        'paid_at': DateTime.now().toIso8601String(),
                        'notes': null,
                      });
                    },
              child: Text(t('common.save')),
            ),
          ],
        ),
      ),
    );
    amount.dispose();
    reference.dispose();
    if (result == null) return;
    await _run(
      () => _client.adminCreatePayment(result),
      success: t('backoffice.paymentSaved'),
    );
  }

  Future<void> _deletePayment(_Json payment) async {
    final t = AirmiusScope.of(context).t;
    if (!await _confirm(
      title: t('backoffice.deletePaymentTitle'),
      body: t('backoffice.deletePaymentBody'),
      confirmLabel: t('common.delete'),
      destructive: true,
    )) {
      return;
    }
    await _run(
      () => _client.adminDeletePayment(_integer(payment['id'])),
      success: t('backoffice.paymentDeleted'),
    );
  }

  Future<void> _editContract(_Json data, {_Json? contract}) async {
    final t = AirmiusScope.of(context).t;
    final options = _map(data['options']);
    final categories = _strings(options['contract_categories']);
    final statuses = _strings(options['contract_statuses']);
    final intervals = _strings(options['billing_intervals']);
    final methods = _strings(options['payment_methods']);
    final name = TextEditingController(text: _text(contract?['name']));
    final vendor = TextEditingController(text: _text(contract?['vendor']));
    final amount = TextEditingController(
      text: contract == null
          ? ''
          : _decimal(contract['amount']).toStringAsFixed(2),
    );
    final number = TextEditingController(
      text: _text(contract?['contract_number']),
    );
    final notes = TextEditingController(text: _text(contract?['notes']));
    var category = _validOption(
      categories,
      _text(contract?['category']),
      'other',
    );
    var status = _validOption(statuses, _text(contract?['status']), 'active');
    var interval = _validOption(
      intervals,
      _text(contract?['billing_interval']),
      'monthly',
    );
    var method = _validOption(
      methods,
      _text(contract?['payment_method']),
      'invoice',
    );
    var autoRenews = contract?['auto_renews'] != false;
    final result = await showDialog<_Json>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(
            contract == null
                ? t('backoffice.newContract')
                : t('backoffice.editContract'),
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: name,
                  textCapitalization: TextCapitalization.words,
                  decoration: InputDecoration(
                    labelText: t('backoffice.contractName'),
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: vendor,
                  textCapitalization: TextCapitalization.words,
                  decoration: InputDecoration(
                    labelText: t('backoffice.vendor'),
                  ),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  initialValue: category,
                  isExpanded: true,
                  decoration: InputDecoration(
                    labelText: t('backoffice.category'),
                  ),
                  items: categories
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(
                            _translatedValue('backoffice.category', value),
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      setDialogState(() => category = value ?? category),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  initialValue: status,
                  decoration: InputDecoration(
                    labelText: t('backoffice.status'),
                  ),
                  items: statuses
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(_status(value)),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      setDialogState(() => status = value ?? status),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: amount,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: InputDecoration(
                    labelText: t('backoffice.amount'),
                    prefixIcon: const Icon(Icons.euro_outlined),
                  ),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  initialValue: interval,
                  decoration: InputDecoration(
                    labelText: t('backoffice.billingInterval'),
                  ),
                  items: intervals
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(
                            _translatedValue('backoffice.interval', value),
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      setDialogState(() => interval = value ?? interval),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  initialValue: method,
                  decoration: InputDecoration(
                    labelText: t('backoffice.method'),
                  ),
                  items: methods
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(
                            _translatedValue('backoffice.method', value),
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      setDialogState(() => method = value ?? method),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: number,
                  decoration: InputDecoration(
                    labelText: t('backoffice.contractNumber'),
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: notes,
                  minLines: 2,
                  maxLines: 4,
                  decoration: InputDecoration(labelText: t('backoffice.notes')),
                ),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: Text(t('backoffice.autoRenews')),
                  value: autoRenews,
                  onChanged: (value) =>
                      setDialogState(() => autoRenews = value),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: () {
                if (name.text.trim().isEmpty || _decimal(amount.text) < 0) {
                  return;
                }
                Navigator.pop(dialogContext, {
                  'owner_user_id': contract?['owner'] is Map
                      ? _map(contract!['owner'])['id']
                      : null,
                  'name': name.text.trim(),
                  'vendor': vendor.text.trim().isEmpty
                      ? null
                      : vendor.text.trim(),
                  'category': category,
                  'status': status,
                  'amount': _decimal(amount.text),
                  'currency': _text(contract?['currency'], fallback: 'EUR'),
                  'billing_interval': interval,
                  'payment_method': method,
                  'next_due_on': contract?['next_due_on'],
                  'starts_on': contract?['starts_on'],
                  'ends_on': contract?['ends_on'],
                  'notice_until_on': contract?['notice_until_on'],
                  'cancellation_period_days':
                      contract?['cancellation_period_days'],
                  'auto_renews': autoRenews,
                  'contract_number': number.text.trim().isEmpty
                      ? null
                      : number.text.trim(),
                  'account_reference': contract?['account_reference'],
                  'contact_email': contract?['contact_email'],
                  'website': contract?['website'],
                  'document_url': contract?['document_url'],
                  'notes': notes.text.trim().isEmpty ? null : notes.text.trim(),
                });
              },
              child: Text(t('common.save')),
            ),
          ],
        ),
      ),
    );
    name.dispose();
    vendor.dispose();
    amount.dispose();
    number.dispose();
    notes.dispose();
    if (result == null) return;
    await _run(
      () => contract == null
          ? _client.adminCreateContract(result)
          : _client.adminUpdateContract(_integer(contract['id']), result),
      success: t('backoffice.contractSaved'),
    );
  }

  Future<void> _deleteContract(_Json contract) async {
    final t = AirmiusScope.of(context).t;
    if (!await _confirm(
      title: t('backoffice.deleteContractTitle'),
      body: t('backoffice.deleteContractBody'),
      confirmLabel: t('common.delete'),
      destructive: true,
    )) {
      return;
    }
    await _run(
      () => _client.adminDeleteContract(_integer(contract['id'])),
      success: t('backoffice.contractDeleted'),
    );
  }

  Future<bool> _confirm({
    required String title,
    required String body,
    required String confirmLabel,
    bool destructive = false,
  }) async {
    final result = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: Text(body),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(AirmiusScope.of(context).t('common.cancel')),
          ),
          FilledButton(
            style: destructive
                ? FilledButton.styleFrom(
                    backgroundColor: Theme.of(context).colorScheme.error,
                  )
                : null,
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(confirmLabel),
          ),
        ],
      ),
    );
    return result == true;
  }

  String _money(int cents, {String currency = 'EUR'}) {
    final language = AirmiusScope.of(context).language;
    return NumberFormat.simpleCurrency(
      locale: language.locale.toLanguageTag(),
      name: currency,
    ).format(cents / 100);
  }

  String _decimalMoney(dynamic value, {String currency = 'EUR'}) {
    final language = AirmiusScope.of(context).language;
    return NumberFormat.simpleCurrency(
      locale: language.locale.toLanguageTag(),
      name: currency,
    ).format(_decimal(value));
  }

  String _date(dynamic value) {
    final raw = _text(value);
    if (raw.isEmpty) return '–';
    final parsed = DateTime.tryParse(raw);
    if (parsed == null) return raw;
    return MaterialLocalizations.of(context).formatMediumDate(parsed.toLocal());
  }

  String _status(String value) {
    if (value.isEmpty) return '–';
    return AirmiusScope.of(context).t('backoffice.status.$value');
  }

  String _translatedValue(String prefix, dynamic value) {
    final raw = _text(value);
    if (raw.isEmpty) return '–';
    return AirmiusScope.of(context).t('$prefix.$raw');
  }

  String _ownerLabel(_Json item) {
    final t = AirmiusScope.of(context).t;
    final user = _map(item['user']);
    final club = _map(item['club']);
    if (club.isNotEmpty) {
      return _text(club['name'], fallback: t('backoffice.club'));
    }
    return _text(
      user['name'],
      fallback: _text(user['email'], fallback: t('backoffice.user')),
    );
  }
}

class _BackofficeMetric extends StatelessWidget {
  const _BackofficeMetric({
    required this.value,
    required this.label,
    required this.icon,
  });

  final String value;
  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minWidth: 145),
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: Theme.of(
        context,
      ).colorScheme.surfaceContainerHighest.withValues(alpha: 0.72),
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: Theme.of(context).dividerColor),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 22, color: Theme.of(context).colorScheme.primary),
        const SizedBox(width: 9),
        Flexible(
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
        ),
      ],
    ),
  );
}

class _BackofficeOverviewTile extends StatelessWidget {
  const _BackofficeOverviewTile({
    required this.icon,
    required this.title,
    required this.value,
  });

  final IconData icon;
  final String title;
  final String value;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
    child: Row(
      children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            color: Theme.of(
              context,
            ).colorScheme.primary.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(15),
          ),
          child: Icon(icon, color: Theme.of(context).colorScheme.primary),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ),
        Text(
          value,
          style: Theme.of(
            context,
          ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
        ),
      ],
    ),
  );
}

class _BackofficeHeading extends StatelessWidget {
  const _BackofficeHeading({required this.title, required this.count});

  final String title;
  final int count;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
      ),
      Badge(label: Text('$count')),
    ],
  );
}

class _BackofficeCard extends StatelessWidget {
  const _BackofficeCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.status,
    required this.details,
    required this.actions,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final String status;
  final List<String> details;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    final cleanDetails = details.where((detail) => detail.trim().isNotEmpty);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: Theme.of(
                    context,
                  ).colorScheme.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(15),
                ),
                child: Icon(icon, color: Theme.of(context).colorScheme.primary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (subtitle.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(subtitle),
                    ],
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: StatusPill(status),
          ),
          if (cleanDetails.isNotEmpty) ...[
            const SizedBox(height: 12),
            ...cleanDetails.map(
              (detail) => Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Text(
                  detail,
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
            ),
          ],
          if (actions.isNotEmpty) ...[
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: actions),
          ],
        ],
      ),
    );
  }
}

class _BackofficeMessage extends StatelessWidget {
  const _BackofficeMessage({
    required this.icon,
    required this.title,
    this.action,
  });

  final IconData icon;
  final String title;
  final Widget? action;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 52, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 12),
          Text(
            title,
            textAlign: TextAlign.center,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
          ),
          if (action != null) ...[const SizedBox(height: 14), action!],
        ],
      ),
    ),
  );
}

_Json _map(dynamic value) => value is Map
    ? value.map((key, entry) => MapEntry(key.toString(), entry))
    : <String, dynamic>{};

List<_Json> _maps(dynamic value) => value is List
    ? value.map(_map).where((item) => item.isNotEmpty).toList()
    : [];

List<String> _strings(dynamic value) =>
    value is List ? value.map((item) => item.toString()).toList() : [];

String _text(dynamic value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

String? _nullableText(dynamic value) {
  final text = _text(value);
  return text.isEmpty ? null : text;
}

int _integer(dynamic value, {int fallback = 0}) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;

double _decimal(dynamic value) {
  if (value is num) return value.toDouble();
  return double.tryParse(
        '$value'.trim().replaceAll(RegExp(r'\s'), '').replaceAll(',', '.'),
      ) ??
      0;
}

int _amountCents(String value) => (_decimal(value) * 100).round();

String _validOption(List<String> values, String current, String fallback) {
  if (values.contains(current)) return current;
  if (values.contains(fallback)) return fallback;
  return values.isEmpty ? fallback : values.first;
}

IconData _statusIcon(String status) => switch (status) {
  'paid' => Icons.check_circle_outline,
  'overdue' => Icons.warning_amber_outlined,
  'cancelled' => Icons.cancel_outlined,
  'pending' => Icons.schedule_outlined,
  _ => Icons.radio_button_unchecked,
};
