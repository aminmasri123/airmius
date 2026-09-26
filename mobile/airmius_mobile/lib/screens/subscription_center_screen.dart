import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SubscriptionCenterScreen extends StatefulWidget {
  const SubscriptionCenterScreen({super.key});

  @override
  State<SubscriptionCenterScreen> createState() =>
      _SubscriptionCenterScreenState();
}

class _SubscriptionCenterScreenState extends State<SubscriptionCenterScreen> {
  String _section = 'subscriptions';
  Future<_SubscriptionBundle>? _future;
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<_SubscriptionBundle> _load() async {
    final responses = await Future.wait([
      _client.subscriptionPlans(),
      _client.subscriptions(),
      _client.clubs(mine: true),
    ]);
    return _SubscriptionBundle.fromResponses(
      plans: responses[0],
      subscriptions: responses[1],
      clubs: responses[2],
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
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('subscriptions.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('subscriptions.reload'),
            onPressed: _busy ? null : _reload,
            icon: Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('subscriptions.title'),
        subtitle: t('subscriptions.subtitle'),
        child: FutureBuilder<_SubscriptionBundle>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const _SubscriptionLoading();
            }
            if (snapshot.hasError) {
              return _SubscriptionError(
                error: snapshot.error,
                onRetry: _reload,
              );
            }
            return _content(snapshot.data ?? const _SubscriptionBundle());
          },
        ),
      ),
    );
  }

  Widget _content(_SubscriptionBundle bundle) {
    final t = AirmiusScope.of(context).t;
    final subscriptions = [
      ...bundle.userSubscriptions.map(
        (item) => _OwnedSubscription(item: item, club: false),
      ),
      ...bundle.clubSubscriptions.map(
        (item) => _OwnedSubscription(item: item, club: true),
      ),
    ];
    final openCheckouts = bundle.checkouts.where(
      (item) => const [
        'pending',
        'awaiting_transfer',
      ].contains(_subscriptionText(item['status'])),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('subscriptions.overview')),
              const SizedBox(height: 8),
              Text(
                t('subscriptions.overviewHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: MetricCard(
                      value: '${subscriptions.length}',
                      label: t('subscriptions.active'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: MetricCard(
                      value: '${openCheckouts.length}',
                      label: t('subscriptions.openPayments'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: MetricCard(
                      value: '${bundle.invoices.length}',
                      label: t('subscriptions.invoices'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final section in const [
              'subscriptions',
              'plans',
              'payments',
              'invoices',
            ])
              ChoiceChip(
                selected: _section == section,
                label: Text(t('subscriptions.$section')),
                avatar: Icon(_sectionIcon(section), size: 18),
                onSelected: (_) => setState(() => _section = section),
              ),
          ],
        ),
        const SizedBox(height: 14),
        if (_section == 'subscriptions')
          _subscriptions(subscriptions)
        else if (_section == 'plans')
          _plans(bundle)
        else if (_section == 'payments')
          _checkouts(bundle)
        else
          _invoices(bundle),
      ],
    );
  }

  Widget _subscriptions(List<_OwnedSubscription> subscriptions) {
    final t = AirmiusScope.of(context).t;
    if (subscriptions.isEmpty) {
      return _SubscriptionEmpty(
        icon: Icons.workspace_premium_outlined,
        text: t('subscriptions.emptySubscriptions'),
      );
    }
    return Column(
      children: [
        for (final subscription in subscriptions) ...[
          _SubscriptionCard(
            subscription: subscription,
            busy: _busy,
            onCancel: () => _cancelSubscription(subscription),
            onResume: () => _resumeSubscription(subscription),
          ),
          const SizedBox(height: 10),
        ],
      ],
    );
  }

  Widget _plans(_SubscriptionBundle bundle) {
    final t = AirmiusScope.of(context).t;
    final editableClubs = bundle.clubs
        .where(
          (club) => club.containsKey('can_edit_subscriptions')
              ? _subscriptionBool(club['can_edit_subscriptions'])
              : _subscriptionBool(club['can_manage']),
        )
        .toList();
    if (bundle.plans.isEmpty) {
      return _SubscriptionEmpty(
        icon: Icons.sell_outlined,
        text: t('subscriptions.emptyPlans'),
      );
    }
    return Column(
      children: [
        for (final plan in bundle.plans) ...[
          _PlanCard(
            plan: plan,
            busy: _busy,
            onCheckout: () => _startCheckout(plan, editableClubs),
          ),
          const SizedBox(height: 10),
        ],
      ],
    );
  }

  Widget _checkouts(_SubscriptionBundle bundle) {
    final t = AirmiusScope.of(context).t;
    if (bundle.checkouts.isEmpty) {
      return _SubscriptionEmpty(
        icon: Icons.account_balance_wallet_outlined,
        text: t('subscriptions.emptyPayments'),
      );
    }
    return Column(
      children: [
        for (final checkout in bundle.checkouts) ...[
          _CheckoutCard(
            checkout: checkout,
            busy: _busy,
            onOpen: () => _openCheckout(checkout),
            onCancel: () => _cancelCheckout(checkout),
          ),
          const SizedBox(height: 10),
        ],
      ],
    );
  }

  Widget _invoices(_SubscriptionBundle bundle) {
    final t = AirmiusScope.of(context).t;
    if (bundle.invoices.isEmpty) {
      return _SubscriptionEmpty(
        icon: Icons.receipt_long_outlined,
        text: t('subscriptions.emptyInvoices'),
      );
    }
    return Column(
      children: [
        for (final invoice in bundle.invoices) ...[
          _InvoiceCard(invoice: invoice),
          const SizedBox(height: 10),
        ],
      ],
    );
  }

  Future<void> _startCheckout(JsonMap plan, List<JsonMap> clubs) async {
    final payload = await _checkoutDialog(plan, clubs);
    if (payload == null || !mounted) return;
    final t = AirmiusScope.of(context).t;
    setState(() => _busy = true);
    try {
      final response = await _client.startSubscriptionCheckout(
        _subscriptionInt(plan['id']),
        provider: _subscriptionText(payload['provider']),
        billingInterval: _subscriptionText(payload['billing_interval']),
        acceptedTerms: true,
        clubId: _subscriptionNullableInt(payload['club_id']),
      );
      final checkout = _subscriptionMap(response['data']);
      if (!mounted) return;
      _reload();
      final action = _subscriptionMap(checkout['payment_action']);
      if (_subscriptionText(action['type']) == 'redirect') {
        await _openPaymentUrl(
          _subscriptionText(action['checkout_url'] ?? checkout['checkout_url']),
        );
      } else {
        await _showBankTransfer(checkout);
      }
      if (mounted) _toast(t('subscriptions.checkoutCreated'));
    } catch (error) {
      if (mounted) _toast(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<JsonMap?> _checkoutDialog(JsonMap plan, List<JsonMap> clubs) {
    final t = AirmiusScope.of(context).t;
    final targetActor = _subscriptionText(
      plan['target_actor'],
      fallback: 'sportler',
    );
    var interval = 'monthly';
    var provider = 'bank_transfer';
    var accepted = false;
    int? clubId = clubs.isEmpty
        ? null
        : _subscriptionNullableInt(clubs.first['id']);
    return showDialog<JsonMap>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(
            _subscriptionText(plan['name'], fallback: t('subscriptions.plan')),
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  t('subscriptions.checkoutHint'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: interval,
                  decoration: InputDecoration(
                    labelText: t('subscriptions.billingInterval'),
                  ),
                  items: [
                    DropdownMenuItem(
                      value: 'monthly',
                      child: Text(t('subscriptions.monthly')),
                    ),
                    DropdownMenuItem(
                      value: 'yearly',
                      child: Text(t('subscriptions.yearly')),
                    ),
                  ],
                  onChanged: (value) =>
                      setDialogState(() => interval = value ?? interval),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: provider,
                  decoration: InputDecoration(
                    labelText: t('subscriptions.paymentMethod'),
                  ),
                  items: [
                    DropdownMenuItem(
                      value: 'bank_transfer',
                      child: Text(t('subscriptions.bankTransfer')),
                    ),
                    const DropdownMenuItem(
                      value: 'stripe',
                      child: Text('Stripe'),
                    ),
                    const DropdownMenuItem(
                      value: 'paypal',
                      child: Text('PayPal'),
                    ),
                  ],
                  onChanged: (value) =>
                      setDialogState(() => provider = value ?? provider),
                ),
                if (targetActor == 'verein') ...[
                  const SizedBox(height: 12),
                  if (clubs.isEmpty)
                    Text(
                      t('subscriptions.clubRequired'),
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    )
                  else
                    DropdownButtonFormField<int>(
                      initialValue: clubId,
                      decoration: InputDecoration(
                        labelText: t('subscriptions.club'),
                      ),
                      items: [
                        for (final club in clubs)
                          DropdownMenuItem(
                            value: _subscriptionInt(club['id']),
                            child: Text(
                              _subscriptionText(
                                club['name'],
                                fallback: t('subscriptions.club'),
                              ),
                            ),
                          ),
                      ],
                      onChanged: (value) =>
                          setDialogState(() => clubId = value),
                    ),
                ],
                const SizedBox(height: 12),
                Material(
                  color: Colors.transparent,
                  child: CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: accepted,
                    controlAffinity: ListTileControlAffinity.leading,
                    title: Text(t('subscriptions.acceptTerms')),
                    subtitle: Text(t('subscriptions.termsHint')),
                    onChanged: (value) =>
                        setDialogState(() => accepted = value == true),
                  ),
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
              onPressed:
                  !accepted || (targetActor == 'verein' && clubId == null)
                  ? null
                  : () => Navigator.pop(context, {
                      'provider': provider,
                      'billing_interval': interval,
                      'club_id': clubId,
                    }),
              child: Text(t('subscriptions.startCheckout')),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openCheckout(JsonMap checkout) async {
    final action = _subscriptionMap(checkout['payment_action']);
    if (_subscriptionText(action['type']) == 'redirect') {
      await _openPaymentUrl(
        _subscriptionText(action['checkout_url'] ?? checkout['checkout_url']),
      );
      return;
    }
    await _showBankTransfer(checkout);
  }

  Future<void> _openPaymentUrl(String rawUrl) async {
    final t = AirmiusScope.of(context).t;
    final uri = safeExternalHttpUrl(rawUrl);
    if (uri == null) {
      _toast(t('subscriptions.invalidPaymentUrl'));
      return;
    }
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && mounted) _toast(t('subscriptions.browserError'));
  }

  Future<void> _showBankTransfer(JsonMap checkout) {
    final t = AirmiusScope.of(context).t;
    final bank = _subscriptionMap(checkout['bank_transfer']);
    final reference = _subscriptionText(checkout['payment_reference']);
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(18, 4, 18, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                t('subscriptions.transferDetails'),
                style: TextStyle(fontSize: 21, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 12),
              _PaymentLine(
                label: t('subscriptions.accountHolder'),
                value: _subscriptionText(bank['account_holder']),
              ),
              _PaymentLine(
                label: t('subscriptions.iban'),
                value: _subscriptionText(bank['iban']),
                copyable: true,
              ),
              _PaymentLine(
                label: 'BIC',
                value: _subscriptionText(bank['bic']),
                copyable: true,
              ),
              _PaymentLine(
                label: t('subscriptions.reference'),
                value: reference,
                copyable: true,
              ),
              _PaymentLine(
                label: t('subscriptions.amount'),
                value: _subscriptionPrice(
                  _subscriptionInt(checkout['amount_cents']),
                  _subscriptionText(checkout['currency'], fallback: 'EUR'),
                ),
              ),
              _PaymentLine(
                label: t('subscriptions.dueAt'),
                value: _subscriptionDate(checkout['due_at']),
              ),
              const SizedBox(height: 12),
              Text(
                t('subscriptions.transferSafety'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  fontSize: 12,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _cancelCheckout(JsonMap checkout) async {
    final t = AirmiusScope.of(context).t;
    if (!await _confirm(
      title: t('subscriptions.cancelCheckoutTitle'),
      body: t('subscriptions.cancelCheckoutConfirm'),
      action: t('subscriptions.cancelCheckout'),
    )) {
      return;
    }
    await _run(
      () =>
          _client.cancelSubscriptionCheckout(_subscriptionInt(checkout['id'])),
      success: t('subscriptions.checkoutCancelled'),
    );
  }

  Future<void> _cancelSubscription(_OwnedSubscription subscription) async {
    final t = AirmiusScope.of(context).t;
    if (!await _confirm(
      title: t('subscriptions.cancelTitle'),
      body: t('subscriptions.cancelConfirm'),
      action: t('subscriptions.cancelAtPeriodEnd'),
    )) {
      return;
    }
    final id = _subscriptionInt(subscription.item['id']);
    final clubId = _subscriptionInt(subscription.item['club_id']);
    await _run(
      () => subscription.club
          ? _client.cancelClubSubscription(clubId, id)
          : _client.cancelUserSubscription(id),
      success: t('subscriptions.cancelled'),
    );
  }

  Future<void> _resumeSubscription(_OwnedSubscription subscription) async {
    final t = AirmiusScope.of(context).t;
    final id = _subscriptionInt(subscription.item['id']);
    final clubId = _subscriptionInt(subscription.item['club_id']);
    await _run(
      () => subscription.club
          ? _client.renewClubSubscription(clubId, id)
          : _client.renewUserSubscription(id),
      success: t('subscriptions.resumed'),
    );
  }

  Future<bool> _confirm({
    required String title,
    required String body,
    required String action,
  }) async {
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: Text(title),
            content: Text(body),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: Text(AirmiusScope.of(context).t('cancel')),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: Text(action),
              ),
            ],
          ),
        ) ==
        true;
  }

  Future<void> _run(
    Future<Object?> Function() action, {
    required String success,
  }) async {
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      _toast(success);
      _reload();
    } catch (error) {
      if (mounted) _toast(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _errorMessage(Object error) => error is AirmiusApiException
      ? error.userMessage
      : AirmiusScope.of(context).t('common.errorDetails');

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _SubscriptionBundle {
  const _SubscriptionBundle({
    this.plans = const [],
    this.userSubscriptions = const [],
    this.clubSubscriptions = const [],
    this.checkouts = const [],
    this.invoices = const [],
    this.clubs = const [],
  });

  factory _SubscriptionBundle.fromResponses({
    required JsonMap plans,
    required JsonMap subscriptions,
    required JsonMap clubs,
  }) {
    final subscriptionData = _subscriptionMap(subscriptions['data']);
    return _SubscriptionBundle(
      plans: _subscriptionList(plans['data']),
      userSubscriptions: _subscriptionList(
        subscriptionData['user_subscriptions'],
      ),
      clubSubscriptions: _subscriptionList(
        subscriptionData['club_subscriptions'],
      ),
      checkouts: _subscriptionList(subscriptionData['checkouts']),
      invoices: _subscriptionList(subscriptionData['invoices']),
      clubs: _subscriptionList(clubs['data']),
    );
  }

  final List<JsonMap> plans;
  final List<JsonMap> userSubscriptions;
  final List<JsonMap> clubSubscriptions;
  final List<JsonMap> checkouts;
  final List<JsonMap> invoices;
  final List<JsonMap> clubs;
}

class _OwnedSubscription {
  const _OwnedSubscription({required this.item, required this.club});

  final JsonMap item;
  final bool club;
}

class _SubscriptionCard extends StatelessWidget {
  const _SubscriptionCard({
    required this.subscription,
    required this.busy,
    required this.onCancel,
    required this.onResume,
  });

  final _OwnedSubscription subscription;
  final bool busy;
  final VoidCallback onCancel;
  final VoidCallback onResume;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final item = subscription.item;
    final plan = _subscriptionMap(item['plan']);
    final club = _subscriptionMap(item['club']);
    final scheduled = _subscriptionBool(item['cancel_at_period_end']);
    final canEdit = !subscription.club || _subscriptionBool(item['can_edit']);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                subscription.club
                    ? Icons.apartment_outlined
                    : Icons.person_outline,
                color: airmiusAccentColor(context),
                size: 28,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _subscriptionText(
                        plan['name'],
                        fallback: t('subscriptions.subscription'),
                      ),
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (_subscriptionText(club['name']).isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        _subscriptionText(club['name']),
                        style: TextStyle(color: airmiusMutedColor(context)),
                      ),
                    ],
                  ],
                ),
              ),
              StatusPill(
                _subscriptionStatus(context, item['status']),
                color: scheduled
                    ? Theme.of(context).colorScheme.tertiary
                    : Theme.of(context).colorScheme.secondary,
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            '${t('subscriptions.periodEnd')}: ${_subscriptionDate(item['current_period_ends_at'])}',
            style: TextStyle(color: airmiusMutedColor(context)),
          ),
          const SizedBox(height: 12),
          if (scheduled && canEdit)
            AirmiusButton(
              label: t('subscriptions.undoCancellation'),
              icon: Icons.restart_alt_outlined,
              secondary: true,
              onPressed: busy ? null : onResume,
            )
          else if (canEdit)
            AirmiusButton(
              label: t('subscriptions.cancelAtPeriodEnd'),
              icon: Icons.event_busy_outlined,
              danger: true,
              onPressed: busy ? null : onCancel,
            ),
        ],
      ),
    );
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({
    required this.plan,
    required this.busy,
    required this.onCheckout,
  });

  final JsonMap plan;
  final bool busy;
  final VoidCallback onCheckout;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final localized = _subscriptionMap(plan['localized_price']);
    final currency = _subscriptionText(
      localized['currency'] ?? plan['currency'],
      fallback: 'EUR',
    );
    final monthly = _subscriptionInt(
      localized['monthly_price_cents'] ?? plan['monthly_price_cents'],
    );
    final yearly = _subscriptionInt(
      localized['yearly_price_cents'] ?? plan['yearly_price_cents'],
    );
    final available =
        localized.isEmpty ||
        _subscriptionBool(localized['available'], fallback: true);
    final features = plan['features'] is List
        ? (plan['features'] as List)
              .map((value) => _subscriptionText(value))
              .where((value) => value.isNotEmpty)
              .toList()
        : const <String>[];
    return AirmiusPanel(
      borderColor: _subscriptionText(plan['badge']).isNotEmpty
          ? airmiusAccentColor(context).withValues(alpha: .6)
          : airmiusBorderColor(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  _subscriptionText(
                    plan['name'],
                    fallback: t('subscriptions.plan'),
                  ),
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
                ),
              ),
              if (_subscriptionText(plan['badge']).isNotEmpty)
                StatusPill(_subscriptionText(plan['badge'])),
            ],
          ),
          if (_subscriptionText(plan['description']).isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              _subscriptionText(plan['description']),
              style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
            ),
          ],
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              StatusPill(
                '${_subscriptionPrice(monthly, currency)} / ${t('subscriptions.monthShort')}',
                color: Theme.of(context).colorScheme.secondary,
              ),
              StatusPill(
                '${_subscriptionPrice(yearly, currency)} / ${t('subscriptions.yearShort')}',
              ),
              StatusPill(
                t(
                  _subscriptionText(plan['target_actor']) == 'verein'
                      ? 'subscriptions.forClubs'
                      : 'subscriptions.forPeople',
                ),
              ),
            ],
          ),
          if (features.isNotEmpty) ...[
            const SizedBox(height: 12),
            for (final feature in features.take(6))
              Padding(
                padding: const EdgeInsets.only(bottom: 5),
                child: Row(
                  children: [
                    Icon(
                      Icons.check_circle_outline,
                      color: Theme.of(context).colorScheme.secondary,
                      size: 18,
                    ),
                    const SizedBox(width: 7),
                    Expanded(child: Text(feature)),
                  ],
                ),
              ),
          ],
          const SizedBox(height: 12),
          AirmiusButton(
            label: monthly <= 0
                ? t('subscriptions.freePlan')
                : t('subscriptions.choosePlan'),
            icon: Icons.workspace_premium_outlined,
            onPressed: busy || !available || monthly <= 0 ? null : onCheckout,
          ),
          if (!available) ...[
            const SizedBox(height: 8),
            Text(
              t('subscriptions.unavailableCountry'),
              style: TextStyle(
                color: Theme.of(context).colorScheme.error,
                fontSize: 12,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _CheckoutCard extends StatelessWidget {
  const _CheckoutCard({
    required this.checkout,
    required this.busy,
    required this.onOpen,
    required this.onCancel,
  });

  final JsonMap checkout;
  final bool busy;
  final VoidCallback onOpen;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final plan = _subscriptionMap(checkout['plan']);
    final status = _subscriptionText(checkout['status']);
    final cancellable = const ['pending', 'awaiting_transfer'].contains(status);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                Icons.account_balance_wallet_outlined,
                color: airmiusAccentColor(context),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  _subscriptionText(
                    plan['name'],
                    fallback:
                        '${t('subscriptions.checkout')} #${_subscriptionInt(checkout['id'])}',
                  ),
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
              StatusPill(_subscriptionStatus(context, status)),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            _subscriptionPrice(
              _subscriptionInt(checkout['amount_cents']),
              _subscriptionText(checkout['currency'], fallback: 'EUR'),
            ),
            style: TextStyle(
              color: Theme.of(context).colorScheme.secondary,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          if (_subscriptionText(checkout['payment_reference']).isNotEmpty) ...[
            const SizedBox(height: 5),
            Text(
              '${t('subscriptions.reference')}: ${_subscriptionText(checkout['payment_reference'])}',
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
          ],
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: t('subscriptions.paymentDetails'),
                icon: Icons.open_in_new_outlined,
                secondary: true,
                onPressed: busy ? null : onOpen,
              ),
              if (cancellable)
                AirmiusButton(
                  label: t('subscriptions.cancelCheckout'),
                  icon: Icons.cancel_outlined,
                  danger: true,
                  onPressed: busy ? null : onCancel,
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InvoiceCard extends StatelessWidget {
  const _InvoiceCard({required this.invoice});

  final JsonMap invoice;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.receipt_long_outlined,
            color: airmiusAccentColor(context),
            size: 28,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _subscriptionText(
                    invoice['number'],
                    fallback:
                        '${t('subscriptions.invoice')} #${_subscriptionInt(invoice['id'])}',
                  ),
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 4),
                Text(
                  _subscriptionText(
                    invoice['title'],
                    fallback: t('subscriptions.invoice'),
                  ),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(
                      _subscriptionPrice(
                        _subscriptionInt(invoice['amount_cents']),
                        _subscriptionText(invoice['currency'], fallback: 'EUR'),
                      ),
                      color: Theme.of(context).colorScheme.secondary,
                    ),
                    StatusPill(
                      _subscriptionText(
                        invoice['status_label'],
                        fallback: _subscriptionStatus(
                          context,
                          invoice['status'],
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PaymentLine extends StatelessWidget {
  const _PaymentLine({
    required this.label,
    required this.value,
    this.copyable = false,
  });

  final String label;
  final String value;
  final bool copyable;

  @override
  Widget build(BuildContext context) {
    if (value.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 112,
            child: Text(
              label,
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
          ),
          Expanded(
            child: SelectableText(
              value,
              style: TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
          if (copyable)
            IconButton(
              tooltip: AirmiusScope.of(context).t('subscriptions.copy'),
              onPressed: () async {
                await Clipboard.setData(ClipboardData(text: value));
                if (!context.mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      AirmiusScope.of(context).t('subscriptions.copied'),
                    ),
                  ),
                );
              },
              icon: Icon(Icons.copy_outlined),
            ),
        ],
      ),
    );
  }
}

class _SubscriptionEmpty extends StatelessWidget {
  const _SubscriptionEmpty({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 28),
        child: Column(
          children: [
            Icon(icon, size: 42, color: airmiusMutedColor(context)),
            const SizedBox(height: 10),
            Text(
              text,
              textAlign: TextAlign.center,
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
          ],
        ),
      ),
    );
  }
}

class _SubscriptionLoading extends StatelessWidget {
  const _SubscriptionLoading();

  @override
  Widget build(BuildContext context) {
    return const AirmiusPanel(
      child: Padding(
        padding: EdgeInsets.all(30),
        child: Center(child: CircularProgressIndicator()),
      ),
    );
  }
}

class _SubscriptionError extends StatelessWidget {
  const _SubscriptionError({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final message = error is AirmiusApiException
        ? (error as AirmiusApiException).userMessage
        : t('common.errorDetails');
    return AirmiusPanel(
      borderColor: Theme.of(context).colorScheme.error.withValues(alpha: .5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            t('subscriptions.loadError'),
            style: TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          Text(message, style: TextStyle(color: airmiusMutedColor(context))),
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('subscriptions.reload'),
            icon: Icons.refresh_outlined,
            secondary: true,
            onPressed: onRetry,
          ),
        ],
      ),
    );
  }
}

IconData _sectionIcon(String section) => switch (section) {
  'plans' => Icons.sell_outlined,
  'payments' => Icons.account_balance_wallet_outlined,
  'invoices' => Icons.receipt_long_outlined,
  _ => Icons.workspace_premium_outlined,
};

String _subscriptionStatus(BuildContext context, Object? raw) {
  final t = AirmiusScope.of(context).t;
  return t(
    'subscriptions.status.${_subscriptionText(raw, fallback: 'unknown')}',
  );
}

String _subscriptionDate(Object? raw) {
  final parsed = DateTime.tryParse(_subscriptionText(raw))?.toLocal();
  if (parsed == null) return '—';
  final day = parsed.day.toString().padLeft(2, '0');
  final month = parsed.month.toString().padLeft(2, '0');
  return '$day.$month.${parsed.year}';
}

String _subscriptionPrice(int cents, String currency) {
  final value = (cents / 100).toStringAsFixed(2).replaceAll('.', ',');
  return currency.toUpperCase() == 'EUR' ? '$value €' : '$value $currency';
}

JsonMap _subscriptionMap(Object? value) {
  if (value is JsonMap) return value;
  if (value is Map) {
    return value.map((key, item) => MapEntry('$key', item));
  }
  return const {};
}

List<JsonMap> _subscriptionList(Object? value) => value is List
    ? value.map(_subscriptionMap).where((item) => item.isNotEmpty).toList()
    : const [];

String _subscriptionText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty || text == 'null' ? fallback : text;
}

int _subscriptionInt(Object? value) =>
    value is num ? value.round() : int.tryParse('$value') ?? 0;

int? _subscriptionNullableInt(Object? value) {
  final parsed = _subscriptionInt(value);
  return parsed > 0 ? parsed : null;
}

bool _subscriptionBool(Object? value, {bool fallback = false}) {
  if (value == null) return fallback;
  return value == true || value == 1 || '$value'.toLowerCase() == 'true';
}
