import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class OutfitSubscriptionCenterScreen extends StatefulWidget {
  const OutfitSubscriptionCenterScreen({super.key});

  @override
  State<OutfitSubscriptionCenterScreen> createState() =>
      _OutfitSubscriptionCenterScreenState();
}

class _OutfitSubscriptionCenterScreenState
    extends State<OutfitSubscriptionCenterScreen> {
  Future<Map<String, dynamic>>? _future;
  Map<String, dynamic> _data = const {};
  String _section = 'overview';
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

  Future<Map<String, dynamic>> _load() async {
    final data = _map((await _client.outfitSubscriptions())['data']);
    _data = data;
    return data;
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  Future<void> _run(
    Future<dynamic> Function() action, {
    required String success,
    void Function(dynamic result)? apply,
  }) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final result = await action();
      if (!mounted) return;
      apply?.call(result);
      _toast(success);
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

  void _commitData(Map<String, dynamic> data) {
    _data = data;
    setState(() => _future = Future.value(_data));
  }

  void _replaceProfile(dynamic result) {
    final profile = _map(_map(result)['data']);
    _commitData({..._data, 'styleProfile': profile});
  }

  void _replaceSubscription(dynamic result) {
    final fresh = _map(_map(result)['data']);
    if (_int(fresh['id']) == 0) return;
    final subscriptions = _maps(_data['subscriptions']);
    final index = subscriptions.indexWhere(
      (subscription) => _int(subscription['id']) == _int(fresh['id']),
    );
    if (index == -1) {
      subscriptions.insert(0, fresh);
    } else {
      subscriptions[index] = fresh;
    }
    _commitData({..._data, 'subscriptions': subscriptions});
  }

  void _replaceDelivery(dynamic result) {
    final fresh = _map(_map(result)['data']);
    if (_int(fresh['id']) == 0) return;
    final subscriptions = _maps(_data['subscriptions'])
        .map(
          (subscription) => {
            ...subscription,
            'deliveries': _maps(subscription['deliveries'])
                .map(
                  (delivery) => _int(delivery['id']) == _int(fresh['id'])
                      ? fresh
                      : delivery,
                )
                .toList(),
          },
        )
        .toList();
    _commitData({..._data, 'subscriptions': subscriptions});
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
          t('outfit.title'),
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
            final message = snapshot.error is AirmiusApiException
                ? (snapshot.error! as AirmiusApiException).userMessage
                : t('common.errorDetails');
            return _OutfitEmpty(
              icon: Icons.cloud_off_outlined,
              title: t('outfit.loadFailed'),
              body: message,
              action: FilledButton.icon(
                onPressed: _reload,
                icon: Icon(Icons.refresh),
                label: Text(t('common.retry')),
              ),
            );
          }
          final data = snapshot.data ?? const <String, dynamic>{};
          return RefreshIndicator(
            onRefresh: () async {
              final next = await _load();
              if (mounted) setState(() => _future = Future.value(next));
            },
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 18, 16, 36),
              children: [
                _hero(data),
                const SizedBox(height: 14),
                _tabs(),
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

  Widget _hero(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final subscriptions = _maps(data['subscriptions']);
    final active = subscriptions
        .where((item) => ['active', 'paused'].contains(item['status']))
        .length;
    final deliveries = subscriptions.fold<int>(
      0,
      (sum, item) => sum + _maps(item['deliveries']).length,
    );
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('outfit.eyebrow')),
          const SizedBox(height: 8),
          Text(
            t('outfit.headline'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 23,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            t('outfit.subtitle'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.45),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _MiniMetric(value: '$active', label: t('outfit.active')),
              _MiniMetric(value: '$deliveries', label: t('outfit.deliveries')),
              _MiniMetric(
                value: '${_maps(data['plans']).length}',
                label: t('outfit.plans'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _tabs() {
    final t = AirmiusScope.of(context).t;
    final entries = {
      'overview': t('outfit.overview'),
      'plans': t('outfit.plans'),
      'subscriptions': t('outfit.mySubscriptions'),
    };
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: entries.entries
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
                  selectedColor: airmiusAccentColor(
                    context,
                  ).withValues(alpha: 0.22),
                  backgroundColor: airmiusSurfaceSoftColor(context),
                  side: BorderSide(
                    color: _section == entry.key
                        ? airmiusAccentColor(context)
                        : airmiusBorderColor(context),
                  ),
                ),
              ),
            )
            .toList(),
      ),
    );
  }

  Widget _content(Map<String, dynamic> data) {
    if (_section == 'plans') return _plans(data);
    if (_section == 'subscriptions') return _subscriptions(data);
    return _overview(data);
  }

  Widget _overview(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final profile = _map(data['styleProfile']);
    final subscriptions = _maps(data['subscriptions']);
    final plans = _maps(data['plans']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Icon(
                    Icons.style_outlined,
                    color: airmiusAccentColor(context),
                    size: 30,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      t('outfit.styleProfile'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 19,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  StatusPill(
                    profile.isEmpty
                        ? t('outfit.incomplete')
                        : t('outfit.saved'),
                    color: profile.isEmpty
                        ? Theme.of(context).colorScheme.tertiary
                        : Theme.of(context).colorScheme.secondary,
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                profile.isEmpty
                    ? t('outfit.profileEmpty')
                    : [
                        _text(profile['sport_focus']),
                        _list(profile['sizes']).join(', '),
                        _text(profile['fit_preference']),
                      ].where((item) => item.isNotEmpty).join(' · '),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 12),
              AirmiusButton(
                label: t('outfit.editProfile'),
                icon: Icons.tune_outlined,
                onPressed: () => _showProfile(profile),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        if (subscriptions.isNotEmpty)
          _SubscriptionCard(
            subscription: subscriptions.first,
            onOpen: () => _showSubscription(subscriptions.first),
          )
        else if (plans.isNotEmpty)
          _PlanCard(
            plan: plans.first,
            onSubscribe: () => _showCheckout(plans.first),
          )
        else
          _OutfitEmpty(
            icon: Icons.checkroom_outlined,
            title: t('outfit.noPlans'),
            body: t('outfit.noPlansBody'),
          ),
      ],
    );
  }

  Widget _plans(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final plans = _maps(data['plans']);
    final subscriptions = _maps(data['subscriptions']);
    if (plans.isEmpty) {
      return _OutfitEmpty(
        icon: Icons.checkroom_outlined,
        title: t('outfit.noPlans'),
        body: t('outfit.noPlansBody'),
      );
    }
    return Column(
      children: plans
          .map(
            (plan) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _PlanCard(
                plan: plan,
                subscribed: subscriptions.any(
                  (subscription) =>
                      _int(_map(subscription['plan'])['id']) ==
                          _int(plan['id']) &&
                      subscription['status'] != 'cancelled',
                ),
                onSubscribe: () => _showCheckout(plan),
              ),
            ),
          )
          .toList(),
    );
  }

  Widget _subscriptions(Map<String, dynamic> data) {
    final t = AirmiusScope.of(context).t;
    final subscriptions = _maps(data['subscriptions']);
    if (subscriptions.isEmpty) {
      return _OutfitEmpty(
        icon: Icons.inventory_2_outlined,
        title: t('outfit.noSubscriptions'),
        body: t('outfit.noSubscriptionsBody'),
      );
    }
    return Column(
      children: subscriptions
          .map(
            (subscription) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _SubscriptionCard(
                subscription: subscription,
                onOpen: () => _showSubscription(subscription),
              ),
            ),
          )
          .toList(),
    );
  }

  Future<void> _showProfile(Map<String, dynamic> profile) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _StyleProfileSheet(profile: profile),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async => _client.updateOutfitStyleProfile(payload),
      success: AirmiusScope.of(context).t('outfit.profileSaved'),
      apply: _replaceProfile,
    );
  }

  Future<void> _showCheckout(Map<String, dynamic> plan) async {
    final payload = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _OutfitCheckoutSheet(plan: plan),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async {
        final result = await _client.subscribeOutfitPlan(
          _int(plan['id']),
          payload,
        );
        final action = _map(result['payment_action']);
        if (action['type'] == 'redirect') {
          final uri = safeExternalHttpUrl(_text(action['url']));
          if (uri != null) {
            await launchUrl(uri, mode: LaunchMode.externalApplication);
          } else if (mounted) {
            _toast(AirmiusScope.of(context).t('outfit.loadFailed'));
          }
        }
        return result;
      },
      success: AirmiusScope.of(context).t('outfit.subscriptionRequested'),
      apply: _replaceSubscription,
    );
  }

  void _showSubscription(Map<String, dynamic> subscription) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _SubscriptionSheet(
        subscription: subscription,
        busy: _busy,
        onPause: () {
          Navigator.pop(context);
          _changeSubscription(subscription, 'pause');
        },
        onResume: () {
          Navigator.pop(context);
          _changeSubscription(subscription, 'resume');
        },
        onCancel: () {
          Navigator.pop(context);
          _confirmCancel(subscription);
        },
        onIssue: (delivery) {
          Navigator.pop(context);
          _showIssue(delivery);
        },
      ),
    );
  }

  Future<void> _changeSubscription(
    Map<String, dynamic> subscription,
    String action,
  ) async {
    final t = AirmiusScope.of(context).t;
    await _run(
      () async {
        final id = _int(subscription['id']);
        if (action == 'pause') {
          return _client.pauseOutfitSubscription(id);
        }
        return _client.resumeOutfitSubscription(id);
      },
      success: t(
        action == 'pause'
            ? 'outfit.subscriptionPaused'
            : 'outfit.subscriptionResumed',
      ),
      apply: _replaceSubscription,
    );
  }

  Future<void> _confirmCancel(Map<String, dynamic> subscription) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('outfit.cancelTitle')),
        content: Text(t('outfit.cancelBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('outfit.cancelSubscription')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(
      () async => _client.cancelOutfitSubscription(_int(subscription['id'])),
      success: t('outfit.subscriptionCancelled'),
      apply: _replaceSubscription,
    );
  }

  Future<void> _showIssue(Map<String, dynamic> delivery) async {
    final payload = await showModalBottomSheet<Map<String, String>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => const _DeliveryIssueSheet(),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async => _client.reportOutfitDeliveryIssue(
        _int(delivery['id']),
        type: payload['type']!,
        description: payload['description']!,
        requestedResolution: payload['resolution'],
        exchangeSize: payload['size'],
      ),
      success: AirmiusScope.of(context).t('outfit.issueSent'),
      apply: _replaceDelivery,
    );
  }
}

class _MiniMetric extends StatelessWidget {
  const _MiniMetric({required this.value, required this.label});
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minWidth: 105, minHeight: 68),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: airmiusSurfaceSoftColor(context).withValues(alpha: 0.8),
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: airmiusBorderColor(context)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          value,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontSize: 21,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(label, style: TextStyle(color: airmiusMutedColor(context))),
      ],
    ),
  );
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({
    required this.plan,
    required this.onSubscribe,
    this.subscribed = false,
  });
  final Map<String, dynamic> plan;
  final VoidCallback onSubscribe;
  final bool subscribed;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final discount = _int(plan['sponsor_discount_cents']);
    final sponsor = _map(plan['sponsor']);
    return AirmiusPanel(
      borderColor: airmiusAccentColor(context).withValues(alpha: 0.45),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.checkroom_outlined,
                color: airmiusAccentColor(context),
                size: 34,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _text(plan['name'], fallback: t('outfit.plan')),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 19,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      _text(plan['description']),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
              if (subscribed)
                StatusPill(
                  t('outfit.requested'),
                  color: Theme.of(context).colorScheme.secondary,
                ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            '${_money(plan['effective_monthly_price_cents'])} / ${t('outfit.month')}',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          if (discount > 0)
            Text(
              '${t('outfit.sponsorDiscount')}: ${_money(discount)}',
              style: TextStyle(
                color: Theme.of(context).colorScheme.secondary,
                fontWeight: FontWeight.w800,
              ),
            ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              StatusPill(
                '${_int(plan['items_per_box'])} ${t('outfit.itemsPerBox')}',
              ),
              ..._list(plan['sizes']).take(5).map((size) => StatusPill(size)),
              if (sponsor.isNotEmpty)
                StatusPill(
                  _text(sponsor['name']),
                  color: Theme.of(context).colorScheme.secondary,
                ),
            ],
          ),
          const SizedBox(height: 13),
          AirmiusButton(
            label: subscribed
                ? t('outfit.alreadyRequested')
                : t('outfit.selectPlan'),
            icon: Icons.shopping_bag_outlined,
            onPressed: subscribed ? null : onSubscribe,
          ),
        ],
      ),
    );
  }
}

class _SubscriptionCard extends StatelessWidget {
  const _SubscriptionCard({required this.subscription, required this.onOpen});
  final Map<String, dynamic> subscription;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final plan = _map(subscription['plan']);
    final status = _text(subscription['status']);
    return AirmiusPanel(
      onTap: onOpen,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.inventory_2_outlined,
            color: airmiusAccentColor(context),
            size: 32,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _text(plan['name'], fallback: t('outfit.subscription')),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  '${_money(subscription['monthly_price_cents'])} / ${t('outfit.month')} · ${_maps(subscription['deliveries']).length} ${t('outfit.deliveries')}',
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 7,
                  runSpacing: 7,
                  children: [
                    StatusPill(
                      _status(context, status),
                      color: status == 'active'
                          ? Theme.of(context).colorScheme.secondary
                          : status == 'cancelled'
                          ? Theme.of(context).colorScheme.error
                          : Theme.of(context).colorScheme.tertiary,
                    ),
                    StatusPill(
                      _status(context, subscription['payment_status']),
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

class _StyleProfileSheet extends StatefulWidget {
  const _StyleProfileSheet({required this.profile});
  final Map<String, dynamic> profile;

  @override
  State<_StyleProfileSheet> createState() => _StyleProfileSheetState();
}

class _StyleProfileSheetState extends State<_StyleProfileSheet> {
  late final TextEditingController _sport;
  late final TextEditingController _sizes;
  late final TextEditingController _colors;
  late final TextEditingController _excluded;
  late final TextEditingController _notes;
  String _fit = 'regular';
  String _style = 'sporty';

  @override
  void initState() {
    super.initState();
    final p = widget.profile;
    _sport = TextEditingController(text: _text(p['sport_focus']));
    _sizes = TextEditingController(text: _list(p['sizes']).join(', '));
    _colors = TextEditingController(text: _list(p['colors']).join(', '));
    _excluded = TextEditingController(
      text: _list(p['excluded_colors']).join(', '),
    );
    _notes = TextEditingController(text: _text(p['notes']));
    _fit = _text(p['fit_preference'], fallback: 'regular');
    _style = _text(p['brand_style'], fallback: 'sporty');
  }

  @override
  void dispose() {
    for (final c in [_sport, _sizes, _colors, _excluded, _notes]) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _Sheet(
      title: t('outfit.editProfile'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextField(
            controller: _sport,
            decoration: InputDecoration(labelText: t('outfit.sportFocus')),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _sizes,
            decoration: InputDecoration(
              labelText: t('outfit.sizes'),
              hintText: 'S, M, L',
            ),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: _fit,
            decoration: InputDecoration(labelText: t('outfit.fit')),
            items: ['slim', 'regular', 'relaxed']
                .map(
                  (value) => DropdownMenuItem(
                    value: value,
                    child: Text(t('outfit.fit.$value')),
                  ),
                )
                .toList(),
            onChanged: (value) => setState(() => _fit = value ?? 'regular'),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: _style,
            decoration: InputDecoration(labelText: t('outfit.brandStyle')),
            items: ['sporty', 'minimal', 'performance', 'club']
                .map(
                  (value) => DropdownMenuItem(
                    value: value,
                    child: Text(t('outfit.style.$value')),
                  ),
                )
                .toList(),
            onChanged: (value) => setState(() => _style = value ?? 'sporty'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _colors,
            decoration: InputDecoration(labelText: t('outfit.colors')),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _excluded,
            decoration: InputDecoration(labelText: t('outfit.excludedColors')),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _notes,
            decoration: InputDecoration(labelText: t('outfit.notes')),
            minLines: 3,
            maxLines: 5,
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: () => Navigator.pop(context, {
              'sport_focus': _nullable(_sport.text),
              'sizes': _csv(_sizes.text),
              'fit_preference': _fit,
              'colors': _csv(_colors.text),
              'excluded_colors': _csv(_excluded.text),
              'brand_style': _style,
              'notes': _nullable(_notes.text),
            }),
            icon: Icon(Icons.save_outlined),
            label: Text(t('common.save')),
          ),
        ],
      ),
    );
  }
}

class _OutfitCheckoutSheet extends StatefulWidget {
  const _OutfitCheckoutSheet({required this.plan});
  final Map<String, dynamic> plan;

  @override
  State<_OutfitCheckoutSheet> createState() => _OutfitCheckoutSheetState();
}

class _OutfitCheckoutSheetState extends State<_OutfitCheckoutSheet> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _country = TextEditingController(text: 'DE');
  final _street = TextEditingController();
  final _house = TextEditingController();
  final _postal = TextEditingController();
  final _city = TextEditingController();
  final _note = TextEditingController();
  String _provider = 'bank_transfer';
  bool _terms = false;
  bool _contract = false;

  @override
  void dispose() {
    for (final c in [_name, _country, _street, _house, _postal, _city, _note]) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final rules = _map(widget.plan['contract_rules']);
    final compact = MediaQuery.sizeOf(context).width < 500;
    final countryField = TextFormField(
      controller: _country,
      decoration: InputDecoration(labelText: t('outfit.country')),
      maxLength: 2,
      validator: (value) =>
          value?.trim().length == 2 ? null : t('outfit.required'),
    );
    final postalField = TextFormField(
      controller: _postal,
      decoration: InputDecoration(labelText: t('outfit.postalCode')),
      validator: _required,
    );
    final streetField = TextFormField(
      controller: _street,
      decoration: InputDecoration(labelText: t('outfit.street')),
      validator: _required,
    );
    final houseField = TextFormField(
      controller: _house,
      decoration: InputDecoration(labelText: t('outfit.houseNumber')),
    );
    return _Sheet(
      title: t('outfit.checkoutTitle'),
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _Info(
              title: _text(widget.plan['name']),
              body:
                  '${_money(widget.plan['effective_monthly_price_cents'])} / ${t('outfit.month')}\n${t('outfit.minimumTerm')}: ${_int(rules['minimum_term_months'])} ${t('outfit.months')} · ${t('outfit.notice')}: ${_int(rules['cancellation_notice_days'])} ${t('outfit.days')}',
            ),
            const SizedBox(height: 14),
            SegmentedButton<String>(
              segments: [
                ButtonSegment(
                  value: 'bank_transfer',
                  icon: Icon(Icons.account_balance_outlined),
                  label: Text(t('outfit.bankTransfer')),
                ),
                const ButtonSegment(
                  value: 'paypal',
                  icon: Icon(Icons.paypal_outlined),
                  label: Text('PayPal'),
                ),
              ],
              selected: {_provider},
              onSelectionChanged: (value) =>
                  setState(() => _provider = value.first),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _name,
              decoration: InputDecoration(labelText: t('outfit.shippingName')),
              validator: _required,
            ),
            const SizedBox(height: 12),
            if (compact) ...[
              countryField,
              const SizedBox(height: 12),
              postalField,
            ] else
              Row(
                children: [
                  Expanded(child: countryField),
                  const SizedBox(width: 10),
                  Expanded(flex: 2, child: postalField),
                ],
              ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _city,
              decoration: InputDecoration(labelText: t('outfit.city')),
              validator: _required,
            ),
            const SizedBox(height: 12),
            if (compact) ...[
              streetField,
              const SizedBox(height: 12),
              houseField,
            ] else
              Row(
                children: [
                  Expanded(flex: 3, child: streetField),
                  const SizedBox(width: 10),
                  Expanded(child: houseField),
                ],
              ),
            const SizedBox(height: 12),
            TextField(
              controller: _note,
              decoration: InputDecoration(labelText: t('outfit.deliveryNote')),
              minLines: 2,
              maxLines: 4,
            ),
            CheckboxListTile(
              value: _terms,
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              title: Text(t('outfit.acceptTerms')),
              onChanged: (value) => setState(() => _terms = value ?? false),
            ),
            CheckboxListTile(
              value: _contract,
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              title: Text(t('outfit.acceptContract')),
              onChanged: (value) => setState(() => _contract = value ?? false),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: _terms && _contract
                  ? () {
                      if (!(_form.currentState?.validate() ?? false)) return;
                      Navigator.pop(context, {
                        'accepted_terms': true,
                        'accepted_contract': true,
                        'payment_provider': _provider,
                        'shipping_name': _name.text.trim(),
                        'shipping_country': _country.text.trim().toUpperCase(),
                        'shipping_street': _street.text.trim(),
                        'shipping_house_number': _nullable(_house.text),
                        'shipping_postal_code': _postal.text.trim(),
                        'shipping_city': _city.text.trim(),
                        'shipping_note': _nullable(_note.text),
                      });
                    }
                  : null,
              icon: Icon(Icons.lock_outline),
              label: Text(t('outfit.subscribeSecurely')),
            ),
          ],
        ),
      ),
    );
  }

  String? _required(String? value) => value == null || value.trim().isEmpty
      ? AirmiusScope.of(context).t('outfit.required')
      : null;
}

class _SubscriptionSheet extends StatelessWidget {
  const _SubscriptionSheet({
    required this.subscription,
    required this.busy,
    required this.onPause,
    required this.onResume,
    required this.onCancel,
    required this.onIssue,
  });
  final Map<String, dynamic> subscription;
  final bool busy;
  final VoidCallback onPause;
  final VoidCallback onResume;
  final VoidCallback onCancel;
  final ValueChanged<Map<String, dynamic>> onIssue;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final plan = _map(subscription['plan']);
    final bank = _map(subscription['bank_transfer']);
    final address = _map(subscription['shipping_address']);
    final deliveries = _maps(subscription['deliveries']);
    final status = _text(subscription['status']);
    return _Sheet(
      title: _text(plan['name'], fallback: t('outfit.subscription')),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              StatusPill(
                _status(context, status),
                color: Theme.of(context).colorScheme.secondary,
              ),
              StatusPill(_status(context, subscription['payment_status'])),
            ],
          ),
          const SizedBox(height: 14),
          _Row(
            label: t('outfit.monthlyPrice'),
            value: _money(subscription['monthly_price_cents']),
          ),
          _Row(
            label: t('outfit.nextDelivery'),
            value: _date(subscription['next_delivery_at']),
          ),
          _Row(
            label: t('outfit.paymentReference'),
            value: _text(subscription['payment_reference'], fallback: '–'),
          ),
          if (bank.isNotEmpty) ...[
            const SizedBox(height: 12),
            _Info(
              title: t('outfit.bankTransfer'),
              body: [
                _text(bank['account_holder']),
                'IBAN: ${_text(bank['iban'])}',
                'BIC: ${_text(bank['bic'])}',
                '${t('outfit.reference')}: ${_text(subscription['payment_reference'])}',
              ].where((item) => !item.endsWith(': ')).join('\n'),
              onCopy: () async {
                await Clipboard.setData(
                  ClipboardData(
                    text:
                        '${_text(bank['iban'])}\n${_text(subscription['payment_reference'])}',
                  ),
                );
              },
            ),
          ],
          if (address.isNotEmpty) ...[
            const SizedBox(height: 12),
            _Info(
              title: t('outfit.shippingAddress'),
              body: [
                _text(address['name']),
                '${_text(address['street'])} ${_text(address['house_number'])}',
                '${_text(address['postal_code'])} ${_text(address['city'])}',
                _text(address['country']),
              ].where((item) => item.trim().isNotEmpty).join('\n'),
            ),
          ],
          const SizedBox(height: 16),
          Text(
            t('outfit.deliveries'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          if (deliveries.isEmpty)
            Text(
              t('outfit.noDeliveries'),
              style: TextStyle(color: airmiusMutedColor(context)),
            )
          else
            ...deliveries.map(
              (delivery) => _DeliveryCard(
                delivery: delivery,
                onIssue: () => onIssue(delivery),
              ),
            ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 9,
            runSpacing: 9,
            children: [
              if (status == 'active')
                OutlinedButton.icon(
                  onPressed: busy ? null : onPause,
                  icon: Icon(Icons.pause_circle_outline),
                  label: Text(t('outfit.pause')),
                ),
              if (status == 'paused')
                FilledButton.icon(
                  onPressed: busy ? null : onResume,
                  icon: Icon(Icons.play_circle_outline),
                  label: Text(t('outfit.resume')),
                ),
              if (!['cancelled', 'cancels_at_period_end'].contains(status))
                OutlinedButton.icon(
                  onPressed: busy ? null : onCancel,
                  icon: Icon(Icons.cancel_outlined),
                  label: Text(t('outfit.cancelSubscription')),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DeliveryCard extends StatelessWidget {
  const _DeliveryCard({required this.delivery, required this.onIssue});
  final Map<String, dynamic> delivery;
  final VoidCallback onIssue;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final canIssue =
        ['shipped', 'delivered'].contains(delivery['status']) &&
        ![
          'open',
          'reviewing',
          'approved',
          'return_waiting',
          'replacement_preparing',
        ].contains(delivery['issue_status']);
    return Container(
      margin: const EdgeInsets.only(bottom: 9),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                Icons.local_shipping_outlined,
                color: airmiusAccentColor(context),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  _date(delivery['delivery_month']),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              StatusPill(_status(context, delivery['status'])),
            ],
          ),
          if (_text(delivery['tracking_number']).isNotEmpty) ...[
            const SizedBox(height: 7),
            SelectableText(
              '${t('outfit.tracking')}: ${_text(delivery['tracking_number'])}',
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
          ],
          if (_text(delivery['issue_status']).isNotEmpty) ...[
            const SizedBox(height: 7),
            Text(
              '${t('outfit.issue')}: ${_status(context, delivery['issue_status'])}',
              style: TextStyle(color: Theme.of(context).colorScheme.tertiary),
            ),
          ],
          if (canIssue) ...[
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: onIssue,
              icon: Icon(Icons.report_problem_outlined),
              label: Text(t('outfit.reportIssue')),
            ),
          ],
        ],
      ),
    );
  }
}

class _DeliveryIssueSheet extends StatefulWidget {
  const _DeliveryIssueSheet();

  @override
  State<_DeliveryIssueSheet> createState() => _DeliveryIssueSheetState();
}

class _DeliveryIssueSheetState extends State<_DeliveryIssueSheet> {
  final _description = TextEditingController();
  final _resolution = TextEditingController();
  final _size = TextEditingController();
  String _type = 'exchange';

  @override
  void dispose() {
    _description.dispose();
    _resolution.dispose();
    _size.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _Sheet(
      title: t('outfit.reportIssue'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          DropdownButtonFormField<String>(
            initialValue: _type,
            decoration: InputDecoration(labelText: t('outfit.issueType')),
            items:
                [
                      'exchange',
                      'return',
                      'damaged',
                      'missing_item',
                      'wrong_item',
                      'other',
                    ]
                    .map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text(t('outfit.issue.$value')),
                      ),
                    )
                    .toList(),
            onChanged: (value) => setState(() => _type = value ?? 'exchange'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _description,
            decoration: InputDecoration(labelText: t('outfit.whatHappened')),
            minLines: 3,
            maxLines: 6,
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _resolution,
            decoration: InputDecoration(labelText: t('outfit.resolution')),
          ),
          if (_type == 'exchange') ...[
            const SizedBox(height: 12),
            TextField(
              controller: _size,
              decoration: InputDecoration(labelText: t('outfit.exchangeSize')),
            ),
          ],
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: _description.text.trim().isEmpty
                ? () {
                    setState(() {});
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text(t('outfit.descriptionRequired'))),
                    );
                  }
                : () => Navigator.pop(context, {
                    'type': _type,
                    'description': _description.text.trim(),
                    'resolution': _resolution.text.trim(),
                    'size': _size.text.trim(),
                  }),
            icon: Icon(Icons.send_outlined),
            label: Text(t('outfit.sendIssue')),
          ),
        ],
      ),
    );
  }
}

class _Info extends StatelessWidget {
  const _Info({required this.title, required this.body, this.onCopy});
  final String title;
  final String body;
  final VoidCallback? onCopy;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: airmiusSurfaceSoftColor(context),
      borderRadius: BorderRadius.circular(13),
      border: Border.all(color: airmiusBorderColor(context)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 5),
              SelectableText(
                body,
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                ),
              ),
            ],
          ),
        ),
        if (onCopy != null)
          IconButton(
            tooltip: AirmiusScope.of(context).t('shared.copy'),
            onPressed: onCopy,
            icon: Icon(Icons.copy_outlined),
          ),
      ],
    ),
  );
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: TextStyle(color: airmiusMutedColor(context)),
          ),
        ),
        const SizedBox(width: 12),
        Text(
          value,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );
}

class _OutfitEmpty extends StatelessWidget {
  const _OutfitEmpty({
    required this.icon,
    required this.title,
    required this.body,
    this.action,
  });
  final IconData icon;
  final String title;
  final String body;
  final Widget? action;

  @override
  Widget build(BuildContext context) => ListView(
    shrinkWrap: true,
    padding: const EdgeInsets.all(20),
    children: [
      Icon(icon, color: airmiusMutedColor(context), size: 46),
      const SizedBox(height: 12),
      Text(
        title,
        textAlign: TextAlign.center,
        style: TextStyle(
          color: airmiusTextColor(context),
          fontSize: 19,
          fontWeight: FontWeight.w900,
        ),
      ),
      const SizedBox(height: 7),
      Text(
        body,
        textAlign: TextAlign.center,
        style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
      ),
      if (action != null) ...[const SizedBox(height: 14), action!],
    ],
  );
}

class _Sheet extends StatelessWidget {
  const _Sheet({required this.title, required this.child});
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => DraggableScrollableSheet(
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
                tooltip: AirmiusScope.of(context).t('common.close'),
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

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _maps(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <Map<String, dynamic>>[];

List<String> _list(Object? value) =>
    value is List ? value.map((item) => '$item').toList() : <String>[];

String _text(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

String _money(Object? cents) =>
    NumberFormat.simpleCurrency(name: 'EUR').format(_int(cents) / 100);

String _date(Object? value) {
  final date = DateTime.tryParse(_text(value));
  return date == null ? '–' : DateFormat.yMMMd().format(date.toLocal());
}

String _status(BuildContext context, Object? value) {
  final status = _text(value);
  if (status.isEmpty) return '–';
  final key = 'outfit.status.$status';
  final translated = AirmiusScope.of(context).t(key);
  return translated == key
      ? status
            .replaceAll('_', ' ')
            .split(' ')
            .map(
              (part) => part.isEmpty
                  ? part
                  : '${part.substring(0, 1).toUpperCase()}${part.substring(1)}',
            )
            .join(' ')
      : translated;
}

List<String> _csv(String value) => value
    .split(',')
    .map((item) => item.trim())
    .where((item) => item.isNotEmpty)
    .toList();

String? _nullable(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}
