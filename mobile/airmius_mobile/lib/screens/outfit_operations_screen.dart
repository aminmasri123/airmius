import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

class OutfitOperationsScreen extends StatefulWidget {
  const OutfitOperationsScreen({super.key});

  @override
  State<OutfitOperationsScreen> createState() => _OutfitOperationsScreenState();
}

class _OutfitOperationsScreenState extends State<OutfitOperationsScreen> {
  Future<AirmiusJson>? _future;
  String _section = 'subscriptions';
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _client.adminOutfitDashboard();
  }

  void _reload() {
    setState(() {
      _future = _client.adminOutfitDashboard();
    });
  }

  Future<void> _run(
    Future<AirmiusJson> Function() action, {
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
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('outfitAdmin.title'),
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
      body: FutureBuilder<AirmiusJson>(
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
                  : t('outfitAdmin.loadFailed'),
              label: t('common.retry'),
              onRetry: _reload,
            );
          }
          final data = _map(snapshot.data?['data']);
          return PageFrame(
            title: t('outfitAdmin.title'),
            subtitle: t('outfitAdmin.subtitle'),
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
                _content(data),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _hero(AirmiusJson data) {
    final summary = _map(data['summary']);
    final theme = Theme.of(context);
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('outfitAdmin.eyebrow')),
          const SizedBox(height: 7),
          Text(
            t('outfitAdmin.headline'),
            style: theme.textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 7),
          Text(t('outfitAdmin.body')),
          const SizedBox(height: 15),
          Wrap(
            spacing: 9,
            runSpacing: 9,
            children: [
              _Metric(
                value: '${_int(summary['active_subscriptions'])}',
                label: t('outfitAdmin.activeSubscriptions'),
              ),
              _Metric(
                value: '${_int(summary['pending_payments'])}',
                label: t('outfitAdmin.pendingPayments'),
              ),
              _Metric(
                value: '${_int(summary['deliveries'])}',
                label: t('outfitAdmin.deliveries'),
              ),
              _Metric(
                value: '${_int(summary['open_delivery_issues'])}',
                label: t('outfitAdmin.openIssues'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _sectionPicker() {
    final sections = {
      'subscriptions': t('outfitAdmin.subscriptions'),
      'deliveries': t('outfitAdmin.deliveries'),
      'plans': t('outfitAdmin.plans'),
      'visuals': t('outfitAdmin.visuals'),
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

  Widget _content(AirmiusJson data) {
    return switch (_section) {
      'deliveries' => _deliveries(_list(data['deliveries'])),
      'plans' => _plans(_list(data['plans'])),
      'visuals' => _visuals(_map(data['visuals'])),
      _ => _subscriptions(_list(data['subscriptions'])),
    };
  }

  Widget _subscriptions(List<dynamic> values) {
    if (values.isEmpty) {
      return EmptyPanel(t('outfitAdmin.noSubscriptions'));
    }
    return Column(
      children: values.map((raw) {
        final item = _map(raw);
        final user = _map(item['user']);
        final plan = _map(item['plan']);
        final address = _map(item['shipping_address']);
        final status = '${item['status'] ?? 'unknown'}';
        final paymentStatus = '${item['payment_status'] ?? 'unknown'}';
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: _AdminCard(
            icon: Icons.checkroom_outlined,
            title: '${plan['name'] ?? t('outfitAdmin.subscription')}',
            status: t('outfitAdmin.status.$status'),
            body:
                '${user['name'] ?? '—'} · ${user['email'] ?? '—'}\n'
                '${t('outfitAdmin.payment')}: ${t('outfitAdmin.status.$paymentStatus')} · '
                '${_money(item)}\n${_addressLine(address)}',
            actions: [
              _Action(
                label: t('outfitAdmin.markPaid'),
                icon: Icons.payments_outlined,
                onTap: paymentStatus == 'paid'
                    ? null
                    : () => _confirmAction(
                        title: t('outfitAdmin.markPaid'),
                        body: t('outfitAdmin.markPaidBody'),
                        action: () => _client.adminMarkOutfitSubscriptionPaid(
                          _int(item['id']),
                        ),
                        success: t('outfitAdmin.subscriptionPaid'),
                      ),
              ),
              _Action(
                label: t('outfitAdmin.markUnpaid'),
                icon: Icons.money_off_outlined,
                onTap: () => _markUnpaid(item),
              ),
              if (item['can_send_payment_reminder'] == true)
                _Action(
                  label: t('outfitAdmin.remind'),
                  icon: Icons.notification_important_outlined,
                  onTap: () => _confirmAction(
                    title: t('outfitAdmin.remind'),
                    body: t('outfitAdmin.remindBody'),
                    action: () => _client.adminSendOutfitPaymentReminder(
                      _int(item['id']),
                    ),
                    success: t('outfitAdmin.reminderSent'),
                  ),
                ),
              _Action(
                label: t('outfitAdmin.address'),
                icon: Icons.edit_location_alt_outlined,
                onTap: () => _editAddress(item),
              ),
              _Action(
                label: t('outfitAdmin.cancel'),
                icon: Icons.cancel_outlined,
                onTap: status == 'cancelled' ? null : () => _cancel(item),
                danger: true,
              ),
              _Action(
                label: t('common.delete'),
                icon: Icons.delete_outline,
                onTap: () => _deleteSubscription(item),
                danger: true,
              ),
            ],
          ),
        );
      }).toList(),
    );
  }

  Widget _deliveries(List<dynamic> values) {
    if (values.isEmpty) return EmptyPanel(t('outfitAdmin.noDeliveries'));
    return Column(
      children: values.map((raw) {
        final item = _map(raw);
        final subscription = _map(item['subscription']);
        final user = _map(subscription['user']);
        final plan = _map(subscription['plan']);
        final issue = _map(item['issue']);
        final status = '${item['status'] ?? 'planned'}';
        final issueStatus = '${issue['status'] ?? ''}';
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: _AdminCard(
            icon: Icons.local_shipping_outlined,
            title: '${plan['name'] ?? t('outfitAdmin.delivery')}',
            status: t('outfitAdmin.status.$status'),
            body:
                '${user['name'] ?? '—'} · ${_date(item['delivery_month'])}\n'
                '${t('outfitAdmin.tracking')}: ${item['tracking_number'] ?? '—'}'
                '${issueStatus.isEmpty ? '' : '\n${t('outfitAdmin.issue')}: ${t('outfitAdmin.status.$issueStatus')}'}',
            actions: [
              _Action(
                label: t('common.edit'),
                icon: Icons.edit_outlined,
                onTap: () => _editDelivery(item),
              ),
              _Action(
                label: t('outfitAdmin.shipped'),
                icon: Icons.outbox_outlined,
                onTap: status == 'shipped' || status == 'delivered'
                    ? null
                    : () => _shipDelivery(item),
              ),
              _Action(
                label: t('outfitAdmin.delivered'),
                icon: Icons.task_alt_outlined,
                onTap: status == 'delivered'
                    ? null
                    : () => _confirmAction(
                        title: t('outfitAdmin.delivered'),
                        body: t('outfitAdmin.deliveredBody'),
                        action: () => _client.adminMarkOutfitDeliveryDelivered(
                          _int(item['id']),
                        ),
                        success: t('outfitAdmin.deliverySaved'),
                      ),
              ),
              if (issueStatus.isNotEmpty)
                _Action(
                  label: t('outfitAdmin.issue'),
                  icon: Icons.report_problem_outlined,
                  onTap: () => _editIssue(item),
                ),
              _Action(
                label: t('common.delete'),
                icon: Icons.delete_outline,
                onTap: () => _deleteDelivery(item),
                danger: true,
              ),
            ],
          ),
        );
      }).toList(),
    );
  }

  Widget _plans(List<dynamic> values) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: FilledButton.icon(
            onPressed: _busy ? null : () => _editPlan(null),
            icon: const Icon(Icons.add_outlined),
            label: Text(t('outfitAdmin.addPlan')),
          ),
        ),
        const SizedBox(height: 12),
        if (values.isEmpty)
          EmptyPanel(t('outfitAdmin.noPlans'))
        else
          ...values.map((raw) {
            final item = _map(raw);
            return Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _AdminCard(
                icon: Icons.inventory_2_outlined,
                title: '${item['name'] ?? '—'}',
                status: item['is_active'] == true
                    ? t('outfitAdmin.active')
                    : t('outfitAdmin.inactive'),
                body:
                    '${_money(item, centsKey: 'effective_monthly_price_cents')} · '
                    '${_int(item['items_per_box'])} ${t('outfitAdmin.itemsPerBox')}\n'
                    '${item['description'] ?? t('outfitAdmin.noDescription')}',
                actions: [
                  _Action(
                    label: t('common.edit'),
                    icon: Icons.edit_outlined,
                    onTap: () => _editPlan(item),
                  ),
                  _Action(
                    label: t('common.delete'),
                    icon: Icons.delete_outline,
                    onTap: () => _deletePlan(item),
                    danger: true,
                  ),
                ],
              ),
            );
          }),
      ],
    );
  }

  Widget _visuals(AirmiusJson values) {
    final hero = _map(values['hero']);
    return AirmiusPanel(
      title: t('outfitAdmin.heroImage'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SelectableText('${hero['source'] ?? '—'}'),
          const SizedBox(height: 8),
          Text(
            '${hero['recommended_size'] ?? '1920 x 1080 px'} · '
            '${hero['formats'] ?? 'WebP, JPG, PNG'}',
          ),
          const SizedBox(height: 14),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: FilledButton.icon(
              onPressed: _busy ? null : () => _editVisual(hero),
              icon: const Icon(Icons.image_outlined),
              label: Text(t('outfitAdmin.changeHero')),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _markUnpaid(AirmiusJson item) async {
    final result = await _textForm(
      title: t('outfitAdmin.markUnpaid'),
      fields: [
        _Field(
          'due_at',
          t('outfitAdmin.dueDate'),
          '${item['payment_due_at'] ?? ''}',
        ),
        _Field('reason', t('outfitAdmin.reason'), ''),
      ],
      dangerous: true,
    );
    if (result == null) return;
    await _run(
      () => _client.adminMarkOutfitSubscriptionUnpaid(
        _int(item['id']),
        dueAt: _emptyToNull(result['due_at']),
        reason: _emptyToNull(result['reason']),
      ),
      success: t('outfitAdmin.subscriptionUnpaid'),
    );
  }

  Future<void> _editAddress(AirmiusJson item) async {
    final address = _map(item['shipping_address']);
    final result = await _textForm(
      title: t('outfitAdmin.address'),
      fields: [
        _Field(
          'shipping_name',
          t('outfitAdmin.recipient'),
          '${address['name'] ?? ''}',
          required: true,
        ),
        _Field(
          'shipping_country',
          t('outfitAdmin.countryCode'),
          '${address['country'] ?? ''}',
          required: true,
        ),
        _Field(
          'shipping_street',
          t('outfitAdmin.street'),
          '${address['street'] ?? ''}',
          required: true,
        ),
        _Field(
          'shipping_house_number',
          t('outfitAdmin.houseNumber'),
          '${address['house_number'] ?? ''}',
        ),
        _Field(
          'shipping_postal_code',
          t('outfitAdmin.postalCode'),
          '${address['postal_code'] ?? ''}',
          required: true,
        ),
        _Field(
          'shipping_city',
          t('outfitAdmin.city'),
          '${address['city'] ?? ''}',
          required: true,
        ),
        _Field(
          'shipping_state',
          t('outfitAdmin.state'),
          '${address['state'] ?? ''}',
        ),
        _Field(
          'shipping_note',
          t('outfitAdmin.note'),
          '${address['note'] ?? ''}',
          lines: 3,
        ),
      ],
    );
    if (result == null) return;
    await _run(
      () => _client.adminUpdateOutfitShippingAddress(_int(item['id']), result),
      success: t('outfitAdmin.addressSaved'),
    );
  }

  Future<void> _cancel(AirmiusJson item) async {
    final result = await _textForm(
      title: t('outfitAdmin.cancel'),
      fields: [_Field('reason', t('outfitAdmin.reason'), '', lines: 3)],
      dangerous: true,
    );
    if (result == null) return;
    await _run(
      () => _client.adminCancelOutfitSubscription(
        _int(item['id']),
        reason: _emptyToNull(result['reason']),
      ),
      success: t('outfitAdmin.subscriptionCancelled'),
    );
  }

  Future<void> _deleteSubscription(AirmiusJson item) => _confirmAction(
    title: t('outfitAdmin.deleteSubscription'),
    body: t('outfitAdmin.deleteSubscriptionBody'),
    action: () => _client.adminDeleteOutfitSubscription(_int(item['id'])),
    success: t('outfitAdmin.subscriptionDeleted'),
    dangerous: true,
  );

  Future<void> _editDelivery(AirmiusJson item) async {
    final result = await _deliveryForm(item);
    if (result == null) return;
    await _run(
      () => _client.adminUpdateOutfitDelivery(_int(item['id']), result),
      success: t('outfitAdmin.deliverySaved'),
    );
  }

  Future<void> _shipDelivery(AirmiusJson item) async {
    final result = await _textForm(
      title: t('outfitAdmin.shipped'),
      fields: [
        _Field(
          'tracking_number',
          t('outfitAdmin.trackingNumber'),
          '${item['tracking_number'] ?? ''}',
        ),
        _Field(
          'tracking_url',
          t('outfitAdmin.trackingUrl'),
          '${item['tracking_url'] ?? ''}',
        ),
        _Field('carrier', t('outfitAdmin.carrier'), '${item['carrier'] ?? ''}'),
      ],
    );
    if (result == null) return;
    await _run(
      () => _client.adminMarkOutfitDeliveryShipped(_int(item['id']), result),
      success: t('outfitAdmin.deliverySaved'),
    );
  }

  Future<void> _editIssue(AirmiusJson item) async {
    final issue = _map(item['issue']);
    final result = await _issueForm(issue);
    if (result == null) return;
    await _run(
      () => _client.adminUpdateOutfitDeliveryIssue(_int(item['id']), result),
      success: t('outfitAdmin.issueSaved'),
    );
  }

  Future<void> _deleteDelivery(AirmiusJson item) => _confirmAction(
    title: t('outfitAdmin.deleteDelivery'),
    body: t('outfitAdmin.deleteDeliveryBody'),
    action: () => _client.adminDeleteOutfitDelivery(_int(item['id'])),
    success: t('outfitAdmin.deliveryDeleted'),
    dangerous: true,
  );

  Future<void> _editPlan(AirmiusJson? item) async {
    final result = await _planForm(item ?? {});
    if (result == null) return;
    await _run(
      () => item == null
          ? _client.adminCreateOutfitPlan(result)
          : _client.adminUpdateOutfitPlan(_int(item['id']), result),
      success: t('outfitAdmin.planSaved'),
    );
  }

  Future<void> _deletePlan(AirmiusJson item) => _confirmAction(
    title: t('outfitAdmin.deletePlan'),
    body: t('outfitAdmin.deletePlanBody'),
    action: () => _client.adminDeleteOutfitPlan(_int(item['id'])),
    success: t('outfitAdmin.planDeleted'),
    dangerous: true,
  );

  Future<void> _editVisual(AirmiusJson hero) async {
    final result = await _textForm(
      title: t('outfitAdmin.changeHero'),
      fields: [
        _Field(
          'source',
          t('outfitAdmin.imageSource'),
          '${hero['source'] ?? ''}',
          required: true,
        ),
      ],
    );
    if (result == null) return;
    await _run(
      () => _client.adminUpdateOutfitVisuals('${result['source']}'),
      success: t('outfitAdmin.visualSaved'),
    );
  }

  Future<void> _confirmAction({
    required String title,
    required String body,
    required Future<AirmiusJson> Function() action,
    required String success,
    bool dangerous = false,
  }) async {
    final confirmed = await showDialog<bool>(
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
            style: dangerous
                ? FilledButton.styleFrom(
                    backgroundColor: Theme.of(context).colorScheme.error,
                  )
                : null,
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('common.confirm')),
          ),
        ],
      ),
    );
    if (confirmed == true) await _run(action, success: success);
  }

  Future<AirmiusJson?> _textForm({
    required String title,
    required List<_Field> fields,
    bool dangerous = false,
  }) async {
    final controllers = {
      for (final field in fields)
        field.key: TextEditingController(text: field.value),
    };
    final formKey = GlobalKey<FormState>();
    final result = await showModalBottomSheet<AirmiusJson>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.fromLTRB(
          18,
          18,
          18,
          18 + MediaQuery.viewInsetsOf(sheetContext).bottom,
        ),
        child: SingleChildScrollView(
          child: Form(
            key: formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  style: Theme.of(
                    sheetContext,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 14),
                for (final field in fields) ...[
                  TextFormField(
                    controller: controllers[field.key],
                    maxLines: field.lines,
                    decoration: InputDecoration(labelText: field.label),
                    validator: field.required
                        ? (value) => value == null || value.trim().isEmpty
                              ? t('outfitAdmin.required')
                              : null
                        : null,
                  ),
                  const SizedBox(height: 12),
                ],
                FilledButton(
                  style: dangerous
                      ? FilledButton.styleFrom(
                          backgroundColor: Theme.of(context).colorScheme.error,
                        )
                      : null,
                  onPressed: () {
                    if (formKey.currentState?.validate() != true) return;
                    Navigator.pop(sheetContext, {
                      for (final field in fields)
                        field.key: controllers[field.key]!.text.trim(),
                    });
                  },
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Text(t('common.save')),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    for (final controller in controllers.values) {
      controller.dispose();
    }
    return result;
  }

  Future<AirmiusJson?> _deliveryForm(AirmiusJson item) {
    final statuses = [
      'planned',
      'preparing',
      'shipped',
      'delivered',
      'cancelled',
    ];
    return _selectForm(
      title: t('outfitAdmin.editDelivery'),
      selectKey: 'status',
      selectLabel: t('outfitAdmin.status'),
      selectValue: '${item['status'] ?? 'planned'}',
      options: statuses,
      fields: [
        _Field(
          'delivery_month',
          t('outfitAdmin.deliveryMonth'),
          '${item['delivery_month'] ?? ''}',
        ),
        _Field(
          'tracking_number',
          t('outfitAdmin.trackingNumber'),
          '${item['tracking_number'] ?? ''}',
        ),
        _Field(
          'tracking_url',
          t('outfitAdmin.trackingUrl'),
          '${item['tracking_url'] ?? ''}',
        ),
        _Field('carrier', t('outfitAdmin.carrier'), '${item['carrier'] ?? ''}'),
        _Field(
          'items_text',
          t('outfitAdmin.items'),
          _list(item['items']).join('\n'),
          lines: 4,
        ),
        _Field(
          'notes',
          t('outfitAdmin.note'),
          '${item['notes'] ?? ''}',
          lines: 3,
        ),
      ],
    );
  }

  Future<AirmiusJson?> _issueForm(AirmiusJson issue) {
    return _selectForm(
      title: t('outfitAdmin.editIssue'),
      selectKey: 'issue_status',
      selectLabel: t('outfitAdmin.status'),
      selectValue: '${issue['status'] ?? 'open'}',
      options: const [
        'open',
        'reviewing',
        'approved',
        'return_waiting',
        'replacement_preparing',
        'resolved',
        'rejected',
      ],
      fields: [
        _Field(
          'issue_admin_note',
          t('outfitAdmin.adminNote'),
          '${issue['admin_note'] ?? ''}',
          lines: 4,
        ),
        _Field(
          'return_tracking_number',
          t('outfitAdmin.returnTrackingNumber'),
          '${issue['return_tracking_number'] ?? ''}',
        ),
        _Field(
          'return_tracking_url',
          t('outfitAdmin.returnTrackingUrl'),
          '${issue['return_tracking_url'] ?? ''}',
        ),
      ],
    );
  }

  Future<AirmiusJson?> _selectForm({
    required String title,
    required String selectKey,
    required String selectLabel,
    required String selectValue,
    required List<String> options,
    required List<_Field> fields,
  }) async {
    final controllers = {
      for (final field in fields)
        field.key: TextEditingController(text: field.value),
    };
    var selected = options.contains(selectValue) ? selectValue : options.first;
    final result = await showModalBottomSheet<AirmiusJson>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.fromLTRB(
            18,
            18,
            18,
            18 + MediaQuery.viewInsetsOf(sheetContext).bottom,
          ),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: selected,
                  decoration: InputDecoration(labelText: selectLabel),
                  items: options
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(t('outfitAdmin.status.$value')),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      setSheetState(() => selected = value ?? selected),
                ),
                const SizedBox(height: 12),
                for (final field in fields) ...[
                  TextField(
                    controller: controllers[field.key],
                    maxLines: field.lines,
                    decoration: InputDecoration(labelText: field.label),
                  ),
                  const SizedBox(height: 12),
                ],
                FilledButton(
                  onPressed: () => Navigator.pop(sheetContext, {
                    selectKey: selected,
                    for (final field in fields)
                      field.key: controllers[field.key]!.text.trim(),
                  }),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Text(t('common.save')),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    for (final controller in controllers.values) {
      controller.dispose();
    }
    return result;
  }

  Future<AirmiusJson?> _planForm(AirmiusJson item) async {
    final result = await _textForm(
      title: item.isEmpty
          ? t('outfitAdmin.addPlan')
          : t('outfitAdmin.editPlan'),
      fields: [
        _Field(
          'name',
          t('outfitAdmin.name'),
          '${item['name'] ?? ''}',
          required: true,
        ),
        _Field(
          'description',
          t('outfitAdmin.description'),
          '${item['description'] ?? ''}',
          lines: 3,
        ),
        _Field(
          'monthly_price_cents',
          t('outfitAdmin.monthlyPriceCents'),
          '${item['monthly_price_cents'] ?? 0}',
          required: true,
        ),
        _Field(
          'sponsor_discount_cents',
          t('outfitAdmin.discountCents'),
          '${item['sponsor_discount_cents'] ?? 0}',
        ),
        _Field(
          'currency',
          t('outfitAdmin.currency'),
          '${item['currency'] ?? 'EUR'}',
          required: true,
        ),
        _Field(
          'items_per_box',
          t('outfitAdmin.itemsPerBox'),
          '${item['items_per_box'] ?? 1}',
          required: true,
        ),
        _Field(
          'branding_type',
          t('outfitAdmin.branding'),
          '${item['branding_type'] ?? 'airmius'}',
          required: true,
        ),
        _Field(
          'target_gender',
          t('outfitAdmin.targetGroup'),
          '${item['target_gender'] ?? 'all'}',
        ),
        _Field(
          'sizes_text',
          t('outfitAdmin.sizes'),
          _list(item['sizes']).join(', '),
        ),
        _Field(
          'sports_text',
          t('outfitAdmin.sports'),
          _list(item['sports']).join(', '),
        ),
        _Field(
          'contract_title',
          t('outfitAdmin.contractTitle'),
          '${item['contract_title'] ?? ''}',
        ),
        _Field(
          'contract_terms_text',
          t('outfitAdmin.contractTerms'),
          _list(item['contract_terms']).join('\n'),
          lines: 4,
        ),
        _Field(
          'minimum_term_months',
          t('outfitAdmin.minimumTerm'),
          '${item['minimum_term_months'] ?? 0}',
        ),
        _Field(
          'pause_allowed_after_months',
          t('outfitAdmin.pauseAfter'),
          '${item['pause_allowed_after_months'] ?? 0}',
        ),
        _Field(
          'cancellation_notice_days',
          t('outfitAdmin.noticeDays'),
          '${item['cancellation_notice_days'] ?? 0}',
        ),
        _Field(
          'sort_order',
          t('outfitAdmin.sortOrder'),
          '${item['sort_order'] ?? 0}',
        ),
      ],
    );
    if (result == null) return null;
    return {
      'sponsor_id': item['sponsor_id'],
      'name': result['name'],
      'description': result['description'],
      'contract_title': result['contract_title'],
      'contract_terms': _lines('${result['contract_terms_text']}'),
      'minimum_term_months': _parsedInt(result['minimum_term_months']),
      'pause_allowed_after_months': _parsedInt(
        result['pause_allowed_after_months'],
      ),
      'cancellation_notice_days': _parsedInt(
        result['cancellation_notice_days'],
      ),
      'monthly_price_cents': _parsedInt(result['monthly_price_cents']),
      'sponsor_discount_cents': _parsedInt(result['sponsor_discount_cents']),
      'currency': '${result['currency']}'.toUpperCase(),
      'target_gender': result['target_gender'],
      'sizes': _csv('${result['sizes_text']}'),
      'sports': _csv('${result['sports_text']}'),
      'items_per_box': _parsedInt(result['items_per_box'], fallback: 1),
      'branding_type': result['branding_type'],
      'sort_order': _parsedInt(result['sort_order']),
      'is_public': item['is_public'] ?? true,
      'is_active': item['is_active'] ?? true,
    };
  }

  String _money(AirmiusJson item, {String centsKey = 'total_cents'}) {
    final cents = _int(item[centsKey]);
    final currency = '${item['currency'] ?? 'EUR'}';
    return NumberFormat.simpleCurrency(
      locale: Localizations.localeOf(context).toLanguageTag(),
      name: currency,
    ).format(cents / 100);
  }

  String _date(dynamic value) {
    final parsed = DateTime.tryParse('$value');
    if (parsed == null) return '—';
    return MaterialLocalizations.of(context).formatMediumDate(parsed.toLocal());
  }

  String _addressLine(AirmiusJson value) {
    final line = [
      value['street'],
      value['house_number'],
      value['postal_code'],
      value['city'],
      value['country'],
    ].where((part) => part != null && '$part'.trim().isNotEmpty).join(' ');
    return line.isEmpty ? t('outfitAdmin.noAddress') : line;
  }
}

class _AdminCard extends StatelessWidget {
  const _AdminCard({
    required this.icon,
    required this.title,
    required this.status,
    required this.body,
    required this.actions,
  });

  final IconData icon;
  final String title;
  final String status;
  final String body;
  final List<_Action> actions;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, color: Theme.of(context).colorScheme.primary),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: StatusPill(status),
          ),
          const SizedBox(height: 10),
          Text(body),
          const SizedBox(height: 13),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: actions
                .map(
                  (action) => OutlinedButton.icon(
                    style: action.danger
                        ? OutlinedButton.styleFrom(
                            foregroundColor: Theme.of(
                              context,
                            ).colorScheme.error,
                          )
                        : null,
                    onPressed: action.onTap,
                    icon: Icon(action.icon, size: 19),
                    label: Text(action.label),
                  ),
                )
                .toList(),
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(minWidth: 132),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface.withValues(alpha: 0.72),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Padding(
          padding: const EdgeInsets.all(12),
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
      ),
    );
  }
}

class _Failure extends StatelessWidget {
  const _Failure({
    required this.message,
    required this.label,
    required this.onRetry,
  });

  final String message;
  final String label;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off_outlined, size: 42),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_outlined),
              label: Text(label),
            ),
          ],
        ),
      ),
    );
  }
}

class _Action {
  const _Action({
    required this.label,
    required this.icon,
    required this.onTap,
    this.danger = false,
  });

  final String label;
  final IconData icon;
  final VoidCallback? onTap;
  final bool danger;
}

class _Field {
  const _Field(
    this.key,
    this.label,
    this.value, {
    this.required = false,
    this.lines = 1,
  });

  final String key;
  final String label;
  final String value;
  final bool required;
  final int lines;
}

AirmiusJson _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<dynamic> _list(dynamic value) => value is List ? value : const [];

int _int(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

int _parsedInt(dynamic value, {int fallback = 0}) =>
    int.tryParse('$value'.trim()) ?? fallback;

String? _emptyToNull(dynamic value) {
  final text = '$value'.trim();
  return text.isEmpty ? null : text;
}

List<String> _csv(String value) => value
    .split(',')
    .map((item) => item.trim())
    .where((item) => item.isNotEmpty)
    .toList();

List<String> _lines(String value) => value
    .split(RegExp(r'\r?\n'))
    .map((item) => item.trim())
    .where((item) => item.isNotEmpty)
    .toList();
