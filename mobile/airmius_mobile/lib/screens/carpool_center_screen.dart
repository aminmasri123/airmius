import 'package:flutter/material.dart';
import '../widgets/airmius_form.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import '../widgets/ride_information_panel.dart';
import '../widgets/ride_pricing.dart';
import '../core/ride_pricing_copy.dart';
import '../core/ride_currency_catalog.dart';
import '../widgets/country_field.dart';
import '../widgets/ride_location_field.dart';
import '../core/ride_location_copy.dart';

class CarpoolCenterScreen extends StatefulWidget {
  const CarpoolCenterScreen({super.key});

  @override
  State<CarpoolCenterScreen> createState() => _CarpoolCenterScreenState();
}

class _CarpoolCenterScreenState extends State<CarpoolCenterScreen> {
  Future<JsonMap>? _future;
  final _fromSearch = TextEditingController();
  final _toSearch = TextEditingController();
  JsonMap? _fromLocation, _toLocation;
  int _radius = 0;
  String _filter = 'upcoming';
  int _eventFilterId = 0;
  bool _busy = false;
  JsonMap _pricingCatalog = {};

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _client.rides();
  }

  @override
  void dispose() {
    _fromSearch.dispose();
    _toSearch.dispose();
    super.dispose();
  }

  String _areaQuery(JsonMap? place, String text) => place == null
      ? text.trim()
      : [
          place['postal_code'],
          place['city'],
          place['country'],
        ].where((v) => v != null && v.toString().isNotEmpty).join(' ');

  void _reload() {
    if (_radius != 0 &&
        ((_fromSearch.text.trim().isNotEmpty && _fromLocation == null) ||
            (_toSearch.text.trim().isNotEmpty && _toLocation == null) ||
            (_fromLocation == null && _toLocation == null))) {
      _toast(rideLocationText(context, 'choose'));
      return;
    }
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _future = _client.rides(
        fromQuery: _areaQuery(_fromLocation, _fromSearch.text),
        toQuery: _areaQuery(_toLocation, _toSearch.text),
        radiusKm: _radius,
        fromLocation: _fromLocation,
        toLocation: _toLocation,
      );
    });
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _run(
    Future<AirmiusJson> Function() action,
    String success,
  ) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      _toast(success);
      _reload();
    } catch (error) {
      if (mounted) {
        _toast(
          '${t('rides.actionError')} ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _editRide(
    List<JsonMap> clubs,
    List<JsonMap> teams,
    List<JsonMap> events, {
    JsonMap? ride,
  }) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      isDismissible: false,
      enableDrag: false,
      builder: (context) => _RideEditorSheet(
        catalog: _pricingCatalog,
        clubs: clubs,
        teams: teams,
        events: events,
        ride: ride,
        client: _client,
        onSave: (payload) async {
          if (ride == null) {
            await _client.createRide(payload);
          } else {
            await _client.updateRide(_rideInt(ride['id']), payload);
          }
        },
      ),
    );
    if (saved == true && mounted) {
      _toast(t(ride == null ? 'rides.created' : 'rides.updated'));
      _reload();
    }
  }

  Future<void> _requestRide(JsonMap ride) async {
    final controller = TextEditingController();
    final send = await showRidePricingReview(
      context,
      ride,
      message: controller,
    );
    final message = controller.text;
    controller.dispose();
    if (send != 'confirm' || !mounted) return;
    await _run(
      () => _client.requestRide(
        _rideInt(ride['id']),
        message: message,
        priceVersion: _rideInt(ridePricing(ride)['version']),
        priceAcknowledged: true,
      ),
      t('rides.requestSent'),
    );
  }

  Future<void> _reviewRidePrice(JsonMap ride) async {
    final decision = await showRidePricingReview(context, ride, changed: true);
    if (!mounted || decision == null) return;
    await _run(
      () => decision == 'decline'
          ? _client.leaveRide(_rideInt(ride['id']))
          : _client.confirmRidePrice(
              _rideInt(ride['id']),
              _rideInt(ridePricing(ride)['version']),
            ),
      decision == 'decline'
          ? t('rides.left')
          : ridePricingText(context, 'confirmed'),
    );
  }

  Future<void> _leaveRide(JsonMap ride) async {
    final pending = ride['has_pending_request'] == true;
    final confirmed = await _confirm(
      pending ? 'rides.withdrawTitle' : 'rides.leaveTitle',
      pending ? 'rides.withdrawQuestion' : 'rides.leaveQuestion',
      danger: !pending,
    );
    if (!confirmed || !mounted) return;
    await _run(
      () => _client.leaveRide(_rideInt(ride['id'])),
      t(pending ? 'rides.requestWithdrawn' : 'rides.left'),
    );
  }

  Future<void> _deleteRide(JsonMap ride) async {
    final confirmed = await _confirm(
      'rides.deleteTitle',
      'rides.deleteQuestion',
      danger: true,
    );
    if (!confirmed || !mounted) return;
    await _run(
      () => _client.deleteRide(_rideInt(ride['id'])),
      t('rides.deleted'),
    );
  }

  Future<bool> _confirm(
    String titleKey,
    String bodyKey, {
    bool danger = false,
  }) async {
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: Text(t(titleKey)),
            content: Text(t(bodyKey)),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: Text(t('cancel')),
              ),
              FilledButton(
                style: danger
                    ? FilledButton.styleFrom(
                        backgroundColor: Theme.of(context).colorScheme.error,
                      )
                    : null,
                onPressed: () => Navigator.pop(context, true),
                child: Text(t('confirm')),
              ),
            ],
          ),
        ) ??
        false;
  }

  Future<void> _showDetails(
    JsonMap ride,
    List<JsonMap> clubs,
    List<JsonMap> teams,
    List<JsonMap> events,
  ) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => _RideDetailsSheet(
        ride: ride,
        busy: _busy,
        onReviewPrice: () {
          Navigator.pop(sheetContext);
          _reviewRidePrice(ride);
        },
        onEdit: () {
          Navigator.pop(sheetContext);
          _editRide(clubs, teams, events, ride: ride);
        },
        onJoin: () {
          Navigator.pop(sheetContext);
          _requestRide(ride);
        },
        onLeave: () {
          Navigator.pop(sheetContext);
          _leaveRide(ride);
        },
        onDelete: () {
          Navigator.pop(sheetContext);
          _deleteRide(ride);
        },
        onApprove: (userId) async {
          Navigator.pop(sheetContext);
          await _run(
            () => _client.approveRideRequest(_rideInt(ride['id']), userId),
            t('rides.requestApproved'),
          );
        },
        onReject: (userId) async {
          Navigator.pop(sheetContext);
          await _run(
            () => _client.rejectRideRequest(_rideInt(ride['id']), userId),
            t('rides.requestRejected'),
          );
        },
        onRemove: (userId) async {
          Navigator.pop(sheetContext);
          final confirmed = await _confirm(
            'rides.removeTitle',
            'rides.removeQuestion',
            danger: true,
          );
          if (!confirmed || !mounted) return;
          await _run(
            () => _client.removeRideMember(_rideInt(ride['id']), userId),
            t('rides.memberRemoved'),
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            Theme.of(context).colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('rides.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('rides.reload'),
            onPressed: _busy ? null : _reload,
            icon: Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<JsonMap>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final error = snapshot.error;
            return _RideError(
              message: error is AirmiusApiException ? error.userMessage : null,
              onRetry: _reload,
            );
          }

          final root = _rideMap(snapshot.data?['data']);
          _pricingCatalog = rideCurrencyCatalog(
            _rideMap(root['pricing_catalog']),
            country: AirmiusServicesScope.of(context).authState.user?.country,
          );
          final rides = _rideList(root['rides']);
          final clubs = _rideList(root['clubs']);
          final teams = _rideList(root['teams']);
          final events = _rideList(root['events']);
          final filtered = _filtered(rides);
          final upcoming = rides.where((ride) => !_isPast(ride)).length;
          final mine = rides
              .where(
                (ride) =>
                    ride['is_driver'] == true ||
                    ride['is_joined'] == true ||
                    ride['has_pending_request'] == true,
              )
              .length;
          final freeSeats = rides
              .where((ride) => !_isPast(ride))
              .fold<int>(
                0,
                (sum, ride) => sum + _rideInt(ride['available_seats']),
              );

          return PageFrame(
            title: t('rides.title'),
            subtitle: t('rides.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('rides.safeMobility')),
                      const SizedBox(height: 8),
                      Text(
                        t('rides.hero'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          height: 1.15,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        t('rides.privacyHint'),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 14),
                      AirmiusButton(
                        label: t('rides.offer'),
                        icon: Icons.add_road_outlined,
                        onPressed: _busy
                            ? null
                            : () => _editRide(clubs, teams, events),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: MetricCard(
                        value: '$upcoming',
                        label: t('rides.upcoming'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(
                        value: '$freeSeats',
                        label: t('rides.freeSeats'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(value: '$mine', label: t('rides.mine')),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children:
                        [
                          ('upcoming', 'rides.upcoming'),
                          ('mine', 'rides.mine'),
                          ('all', 'rides.all'),
                          ('past', 'rides.past'),
                        ].map((option) {
                          final selected = _filter == option.$1;
                          return ChoiceChip(
                            selected: selected,
                            label: Text(t(option.$2)),
                            onSelected: (_) =>
                                setState(() => _filter = option.$1),
                            selectedColor: airmiusAccentColor(
                              context,
                            ).withValues(alpha: 0.22),
                            backgroundColor: airmiusSurfaceSoftColor(context),
                            side: BorderSide(
                              color: selected
                                  ? airmiusAccentColor(context)
                                  : airmiusBorderColor(context),
                            ),
                            labelStyle: TextStyle(
                              color: selected
                                  ? airmiusAccentColor(context)
                                  : airmiusMutedColor(context),
                              fontWeight: FontWeight.w900,
                            ),
                          );
                        }).toList(),
                  ),
                ),
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      RideLocationField(
                        client: _client,
                        controller: _fromSearch,
                        label: t('rides.searchFrom'),
                        area: true,
                        onChanged: () => _fromLocation = null,
                        onSelected: (place) =>
                            setState(() => _fromLocation = place),
                      ),
                      const SizedBox(height: 12),
                      RideLocationField(
                        client: _client,
                        controller: _toSearch,
                        label: t('rides.searchTo'),
                        area: true,
                        onChanged: () => _toLocation = null,
                        onSelected: (place) =>
                            setState(() => _toLocation = place),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<int>(
                        initialValue: _radius,
                        isExpanded: true,
                        decoration: InputDecoration(
                          labelText: rideLocationText(context, 'radius'),
                        ),
                        items: [
                          DropdownMenuItem(
                            value: 0,
                            child: Text(rideLocationText(context, 'exact')),
                          ),
                          for (final km in [5, 10, 25])
                            DropdownMenuItem(value: km, child: Text('$km km')),
                        ],
                        onChanged: (value) =>
                            setState(() => _radius = value ?? 0),
                      ),
                      if (_radius > 0)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          child: Text(rideLocationText(context, 'hint')),
                        ),
                      const SizedBox(height: 12),
                      AirmiusButton(
                        label: t('rides.search'),
                        icon: Icons.search,
                        onPressed: _reload,
                      ),
                      TextButton(
                        onPressed: () {
                          _fromLocation = null;
                          _toLocation = null;
                          _radius = 0;
                          _fromSearch.clear();
                          _toSearch.clear();
                          _reload();
                        },
                        child: Text(t('rides.resetSearch')),
                      ),
                      Text(
                        filtered.length.toString() + ' ' + t('rides.results'),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                if (events.isNotEmpty) ...[
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    initialValue: _eventFilterId,
                    decoration: InputDecoration(
                      labelText: t('rides.eventFilter'),
                      prefixIcon: Icon(Icons.event_outlined),
                    ),
                    items: [
                      DropdownMenuItem<int>(
                        value: 0,
                        child: Text(t('rides.allEvents')),
                      ),
                      ...events.map(
                        (event) => DropdownMenuItem<int>(
                          value: _rideInt(event['id']),
                          child: Text(_rideText(event['title'])),
                        ),
                      ),
                    ],
                    onChanged: (value) =>
                        setState(() => _eventFilterId = value ?? 0),
                  ),
                  const SizedBox(height: 14),
                ],
                if (filtered.isEmpty)
                  AirmiusPanel(
                    child: Column(
                      children: [
                        Icon(
                          Icons.directions_car_outlined,
                          color: airmiusMutedColor(context),
                          size: 42,
                        ),
                        const SizedBox(height: 10),
                        Text(
                          t('rides.empty'),
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          t('rides.emptyHint'),
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  )
                else
                  ...filtered.map(
                    (ride) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _RideCard(
                        ride: ride,
                        onTap: () => _showDetails(ride, clubs, teams, events),
                      ),
                    ),
                  ),
                const SizedBox(height: 2),
                AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('rides.safetyTitle')),
                      const SizedBox(height: 10),
                      _SafetyLine(
                        icon: Icons.location_on_outlined,
                        title: t('rides.privatePickup'),
                        body: t('rides.privatePickupHint'),
                      ),
                      _SafetyLine(
                        icon: Icons.family_restroom_outlined,
                        title: t('rides.youthSafety'),
                        body: t('rides.youthSafetyHint'),
                      ),
                      _SafetyLine(
                        icon: Icons.verified_user_outlined,
                        title: t('rides.driverControl'),
                        body: t('rides.driverControlHint'),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  List<JsonMap> _filtered(List<JsonMap> rides) {
    return rides.where((ride) {
      if (_eventFilterId != 0 && _rideInt(ride['event_id']) != _eventFilterId) {
        return false;
      }
      final past = _isPast(ride);
      return switch (_filter) {
        'upcoming' => !past,
        'past' => past,
        'mine' =>
          ride['is_driver'] == true ||
              ride['is_joined'] == true ||
              ride['has_pending_request'] == true,
        _ => true,
      };
    }).toList();
  }
}

class _RideCard extends StatelessWidget {
  const _RideCard({required this.ride, required this.onTap});

  final JsonMap ride;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final departure = _rideDate(ride['departure_time']);
    final participants = _rideInt(ride['participants_count']);
    final seats = _rideInt(ride['seats']);
    final status = ride['is_driver'] == true
        ? t('rides.driver')
        : ride['is_joined'] == true
        ? t('rides.joined')
        : ride['has_pending_request'] == true
        ? t('rides.pending')
        : t('rides.available');
    final statusColor = ride['is_driver'] == true
        ? airmiusAccentColor(context)
        : ride['is_joined'] == true
        ? Theme.of(context).colorScheme.secondary
        : ride['has_pending_request'] == true
        ? Theme.of(context).colorScheme.tertiary
        : airmiusAccentColor(context);

    return AirmiusPanel(
      onTap: onTap,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.directions_car_filled_outlined,
            color: airmiusAccentColor(context),
            size: 30,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${_rideText(ride['from'])} → ${_rideText(ride['to'])}',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                  ),
                ),
                Text(
                  [
                            ride['pickup_postal_code'],
                            ride['pickup_city'],
                            ride['pickup_country'],
                          ]
                          .where((v) => v != null && v.toString().isNotEmpty)
                          .join(' ') +
                      ' → ' +
                      [
                            ride['destination_postal_code'],
                            ride['destination_city'],
                            ride['destination_country'],
                          ]
                          .where((v) => v != null && v.toString().isNotEmpty)
                          .join(' '),
                ),
                if (ride['is_driver'] == true &&
                    ride['area_search_ready'] != true)
                  Text(
                    rideLocationText(context, 'unmapped'),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                const SizedBox(height: 5),
                Text(
                  _rideText(
                    _rideMap(ride['driver'])['name'],
                    fallback: t('rides.driver'),
                  ),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.3,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  ridePriceLabel(context, ridePricing(ride)),
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(_formatRideDate(context, departure)),
                    if (_rideMap(ride['event']).isNotEmpty)
                      StatusPill(
                        _rideText(_rideMap(ride['event'])['title']),
                        color: airmiusAccentColor(context),
                      ),
                    StatusPill(
                      t('rides.seatCount')
                          .replaceFirst('{used}', '$participants')
                          .replaceFirst('{total}', '$seats'),
                      color: participants >= seats
                          ? Theme.of(context).colorScheme.error
                          : Theme.of(context).colorScheme.secondary,
                    ),
                    StatusPill(
                      t(
                        'rides.visibility.${_rideText(ride['visibility'], fallback: 'public')}',
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          StatusPill(status, color: statusColor),
        ],
      ),
    );
  }
}

class _RideDetailsSheet extends StatelessWidget {
  const _RideDetailsSheet({
    required this.ride,
    required this.busy,
    required this.onReviewPrice,
    required this.onEdit,
    required this.onJoin,
    required this.onLeave,
    required this.onDelete,
    required this.onApprove,
    required this.onReject,
    required this.onRemove,
  });

  final JsonMap ride;
  final bool busy;
  final VoidCallback onReviewPrice;
  final VoidCallback onEdit;
  final VoidCallback onJoin;
  final VoidCallback onLeave;
  final VoidCallback onDelete;
  final ValueChanged<int> onApprove;
  final ValueChanged<int> onReject;
  final ValueChanged<int> onRemove;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final members = _rideList(ride['users']);
    final requests = _rideList(ride['pending_requests']);
    final reason = _rideText(ride['join_block_reason']);

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.88,
      minChildSize: 0.55,
      maxChildSize: 0.96,
      builder: (context, scrollController) => SingleChildScrollView(
        controller: scrollController,
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 30),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 48,
                height: 5,
                decoration: BoxDecoration(
                  color: airmiusBorderColor(context),
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
            ),
            const SizedBox(height: 18),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.directions_car_filled_outlined,
                  color: airmiusAccentColor(context),
                  size: 34,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${_rideText(ride['from'])} → ${_rideText(ride['to'])}',
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          height: 1.15,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        t('rides.byDriver').replaceFirst(
                          '{name}',
                          _rideText(
                            _rideMap(ride['driver'])['name'],
                            fallback: t('rides.driver'),
                          ),
                        ),
                        style: TextStyle(color: airmiusMutedColor(context)),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: t('close'),
                  onPressed: () => Navigator.pop(context),
                  icon: Icon(Icons.close),
                ),
              ],
            ),
            const SizedBox(height: 18),
            RideInformationPanel(ride: ride, showHeader: false),
            if (ridePricing(ride)['requires_confirmation'] == true)
              AirmiusButton(
                label: ridePricingText(context, 'confirm'),
                icon: Icons.price_check,
                onPressed: busy ? null : onReviewPrice,
              ),
            if (members.isNotEmpty) ...[
              const SizedBox(height: 14),
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Eyebrow(t('rides.participants')),
                    const SizedBox(height: 8),
                    ...members.map(
                      (member) => ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: AirmiusAvatar(
                          _rideText(member['name'], fallback: '?'),
                        ),
                        title: Text(
                          _rideText(
                            member['name'],
                            fallback: t('rides.member'),
                          ),
                          style: TextStyle(fontWeight: FontWeight.w800),
                        ),
                        trailing: member['can_remove'] == true
                            ? IconButton(
                                tooltip: t('rides.remove'),
                                onPressed: busy
                                    ? null
                                    : () => onRemove(_rideInt(member['id'])),
                                icon: Icon(
                                  Icons.person_remove_outlined,
                                  color: Theme.of(context).colorScheme.error,
                                ),
                              )
                            : null,
                      ),
                    ),
                  ],
                ),
              ),
            ],
            if (requests.isNotEmpty) ...[
              const SizedBox(height: 14),
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Eyebrow(t('rides.pendingRequests')),
                    const SizedBox(height: 8),
                    ...requests.map(
                      (request) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(
                              _rideText(
                                request['name'],
                                fallback: t('rides.member'),
                              ),
                              style: TextStyle(
                                color: airmiusTextColor(context),
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            if (request['price_confirmation_required'] == true)
                              Text(
                                ridePricingText(
                                  context,
                                  'awaiting_confirmation',
                                ),
                              ),
                            if (_rideText(request['message']).isNotEmpty) ...[
                              const SizedBox(height: 4),
                              Text(
                                _rideText(request['message']),
                                style: TextStyle(
                                  color: airmiusMutedColor(context),
                                  height: 1.35,
                                ),
                              ),
                            ],
                            const SizedBox(height: 8),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                AirmiusButton(
                                  label: t('rides.approve'),
                                  icon: Icons.check_outlined,
                                  onPressed:
                                      busy ||
                                          request['price_confirmation_required'] ==
                                              true
                                      ? null
                                      : () =>
                                            onApprove(_rideInt(request['id'])),
                                ),
                                AirmiusButton(
                                  label: t('rides.reject'),
                                  icon: Icons.close_outlined,
                                  danger: true,
                                  onPressed: busy
                                      ? null
                                      : () => onReject(_rideInt(request['id'])),
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
            ],
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (ride['can_join'] == true)
                  AirmiusButton(
                    label: t('rides.requestSeat'),
                    icon: Icons.person_add_alt_1_outlined,
                    onPressed: busy ? null : onJoin,
                  ),
                if (ride['has_pending_request'] == true)
                  AirmiusButton(
                    label: t('rides.withdraw'),
                    icon: Icons.undo_outlined,
                    secondary: true,
                    onPressed: busy ? null : onLeave,
                  ),
                if (ride['is_joined'] == true && ride['is_driver'] != true)
                  AirmiusButton(
                    label: t('rides.leave'),
                    icon: Icons.logout_outlined,
                    secondary: true,
                    onPressed: busy ? null : onLeave,
                  ),
                if (ride['can_update'] == true)
                  AirmiusButton(
                    label: t('edit'),
                    icon: Icons.edit_outlined,
                    secondary: true,
                    onPressed: busy ? null : onEdit,
                  ),
                if (ride['can_delete'] == true)
                  AirmiusButton(
                    label: t('delete'),
                    icon: Icons.delete_outline,
                    danger: true,
                    onPressed: busy ? null : onDelete,
                  ),
              ],
            ),
            if (ride['can_join'] != true &&
                ride['is_driver'] != true &&
                ride['is_joined'] != true &&
                ride['has_pending_request'] != true &&
                reason.isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(
                t('rides.block.$reason'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.35,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _RideEditorSheet extends StatefulWidget {
  const _RideEditorSheet({
    required this.clubs,
    required this.teams,
    required this.events,
    required this.onSave,
    required this.client,
    required this.catalog,
    this.ride,
  });

  final List<JsonMap> clubs;
  final List<JsonMap> teams;
  final List<JsonMap> events;
  final JsonMap? ride;
  final Future<void> Function(JsonMap) onSave;
  final AirmiusApiClient client;
  final JsonMap catalog;

  @override
  State<_RideEditorSheet> createState() => _RideEditorSheetState();
}

class _RideEditorSheetState extends State<_RideEditorSheet> {
  final _formKey = GlobalKey<FormState>();
  bool _saving = false;
  String? _saveError;
  late final TextEditingController _from;
  late final TextEditingController _to;
  late final TextEditingController _pickupName;
  late final TextEditingController _street;
  late final TextEditingController _house;
  late final TextEditingController _postal;
  late final TextEditingController _city;
  late final TextEditingController _country;
  late final TextEditingController _note;
  late final TextEditingController _contact;
  late final TextEditingController _seats;
  final _destination = <String, TextEditingController>{};
  final _costAmount = TextEditingController();
  String? _costMode, _costCurrency;
  late String _visibility;
  int? _clubId;
  int? _teamId;
  int? _eventId;
  late DateTime _departure;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    final ride = widget.ride ?? const <String, dynamic>{};
    final pricing = ridePricing(ride);
    _costMode = pricing['mode'] as String?;
    _costCurrency =
        (pricing['currency'] ?? widget.catalog['default_currency']) as String?;
    _costAmount.text = '${pricing['amount'] ?? ''}';
    for (final field in [
      'street',
      'house_number',
      'postal_code',
      'city',
      'country',
    ]) {
      _destination[field] = TextEditingController(
        text: _rideText(
          ride['destination_' + field],
          fallback: field == 'country' ? 'DE' : '',
        ),
      );
    }
    _from = TextEditingController(text: _rideText(ride['from']));
    _to = TextEditingController(text: _rideText(ride['to']));
    _pickupName = TextEditingController(text: _rideText(ride['pickup_name']));
    _street = TextEditingController(text: _rideText(ride['pickup_street']));
    _house = TextEditingController(
      text: _rideText(ride['pickup_house_number']),
    );
    _postal = TextEditingController(
      text: _rideText(ride['pickup_postal_code']),
    );
    _city = TextEditingController(text: _rideText(ride['pickup_city']));
    _country = TextEditingController(
      text: _rideText(ride['pickup_country'], fallback: 'DE'),
    );
    _note = TextEditingController(text: _rideText(ride['pickup_note']));
    _contact = TextEditingController(text: _rideText(ride['contact_details']));
    _seats = TextEditingController(
      text: '${_rideInt(ride['seats'], fallback: 3)}',
    );
    _visibility = _rideText(ride['visibility'], fallback: 'friends');
    _clubId = _rideNullableInt(ride['club_id']);
    _teamId = _rideNullableInt(ride['team_id']);
    _eventId = _rideNullableInt(ride['event_id']);
    _departure =
        _rideDate(ride['departure_time']) ??
        DateTime.now().add(const Duration(hours: 1));
  }

  @override
  void dispose() {
    for (final controller in [
      _from,
      _to,
      _pickupName,
      _street,
      _house,
      _postal,
      _city,
      _country,
      _note,
      _costAmount,
      _contact,
      _seats,
      ..._destination.values,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  List<JsonMap> get _visibleTeams {
    if (_clubId == null) return widget.teams;
    return widget.teams
        .where((team) => _rideInt(team['club_id']) == _clubId)
        .toList();
  }

  List<JsonMap> get _visibleEvents {
    if (_visibility == 'team') {
      return widget.events
          .where((event) => _rideNullableInt(event['team_id']) == _teamId)
          .toList();
    }
    if (_visibility == 'club') {
      return widget.events
          .where((event) => _rideNullableInt(event['club_id']) == _clubId)
          .toList();
    }
    return widget.events;
  }

  Future<void> _pickDeparture() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _departure,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_departure),
    );
    if (time == null || !mounted) return;
    setState(() {
      _departure = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      );
    });
  }

  void _chooseAddress(bool start, JsonMap place) {
    (start ? _from : _to).text = (place['city'] ?? place['name'] ?? '')
        .toString();
    final fields = start
        ? {
            'street': _street,
            'house_number': _house,
            'postal_code': _postal,
            'city': _city,
            'country': _country,
          }
        : _destination;
    for (final entry in fields.entries) {
      entry.value.text = (place[entry.key] ?? '').toString();
    }
    if (start)
      _pickupName.text = (place['street'] ?? '').toString().isEmpty
          ? (place['name'] ?? '').toString()
          : '';
    setState(() {});
  }

  Future<void> _submit() async {
    if (_saving) return;
    if (!_formKey.currentState!.validate()) return;
    if (_departure.isBefore(DateTime.now().add(const Duration(minutes: 9)))) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('rides.departureTooSoon'))));
      return;
    }
    if (_visibility == 'club' && _clubId == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('rides.clubRequired'))));
      return;
    }
    if (_visibility == 'team' && _teamId == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('rides.teamRequired'))));
      return;
    }

    final payload = <String, dynamic>{
      'pricing_details': true,
      'cost_mode': _costMode,
      'cost_amount': _costAmount.text,
      'cost_currency': _costCurrency,
      'address_details': true,
      for (final entry in _destination.entries)
        'destination_' + entry.key: _nullable(entry.value.text),
      'visibility': _visibility,
      'club_id': _visibility == 'club' || _visibility == 'team'
          ? _clubId
          : null,
      'team_id': _visibility == 'team' ? _teamId : null,
      'event_id': _eventId,
      'from': _from.text.trim(),
      'to': _to.text.trim(),
      'pickup_name': _nullable(_pickupName.text),
      'pickup_street': _nullable(_street.text),
      'pickup_house_number': _nullable(_house.text),
      'pickup_postal_code': _nullable(_postal.text),
      'pickup_city': _nullable(_city.text),
      'pickup_country': _nullable(_country.text)?.toUpperCase(),
      'pickup_note': _nullable(_note.text),
      'departure_time': _departure.toUtc().toIso8601String(),
      'seats': int.parse(_seats.text),
      'contact_details': _nullable(_contact.text),
    };
    setState(() {
      _saving = true;
      _saveError = null;
    });
    try {
      await widget.onSave(payload);
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted)
        setState(
          () => _saveError = error is AirmiusApiException
              ? error.userMessage
              : t('rides.actionError'),
        );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.94,
      minChildSize: 0.7,
      maxChildSize: 0.98,
      builder: (context, scrollController) => AirmiusForm(
        key: _formKey,
        child: SingleChildScrollView(
          controller: scrollController,
          padding: EdgeInsets.fromLTRB(
            20,
            12,
            20,
            24 + MediaQuery.viewInsetsOf(context).bottom,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      t(
                        widget.ride == null
                            ? 'rides.createTitle'
                            : 'rides.editTitle',
                      ),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: t('close'),
                    onPressed: _saving ? null : () => Navigator.pop(context),
                    icon: Icon(Icons.close),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                isExpanded: true,
                initialValue: _visibility,
                decoration: InputDecoration(
                  labelText: t('rides.visibility'),
                  prefixIcon: Icon(Icons.visibility_outlined),
                ),
                items: ['friends', 'club', 'team', 'public']
                    .map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text(t('rides.visibility.$value')),
                      ),
                    )
                    .toList(),
                onChanged: (value) => setState(() {
                  _visibility = value ?? 'friends';
                  if (_visibility != 'team') _teamId = null;
                  if (_visibility != 'club' && _visibility != 'team') {
                    _clubId = null;
                  }
                  if (!_visibleEvents.any(
                    (event) => _rideInt(event['id']) == _eventId,
                  )) {
                    _eventId = null;
                  }
                }),
              ),
              const SizedBox(height: 12),
              RideCostEditor(
                catalog: widget.catalog,
                mode: _costMode,
                currency: _costCurrency,
                amount: _costAmount,
                editing: widget.ride != null,
                onMode: (value) => setState(() => _costMode = value),
                onCurrency: (value) => setState(() => _costCurrency = value),
              ),
              const SizedBox(height: 12),
              if (_visibility == 'club' || _visibility == 'team')
                DropdownButtonFormField<int>(
                  isExpanded: true,
                  initialValue: _clubId,
                  decoration: InputDecoration(
                    labelText: t('rides.club'),
                    prefixIcon: Icon(Icons.shield_outlined),
                  ),
                  items: widget.clubs
                      .map(
                        (club) => DropdownMenuItem(
                          value: _rideInt(club['id']),
                          child: Text(_rideText(club['name'])),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => setState(() {
                    _clubId = value;
                    if (_teamId != null &&
                        !_visibleTeams.any(
                          (team) => _rideInt(team['id']) == _teamId,
                        )) {
                      _teamId = null;
                    }
                    if (!_visibleEvents.any(
                      (event) => _rideInt(event['id']) == _eventId,
                    )) {
                      _eventId = null;
                    }
                  }),
                ),
              if (_visibility == 'club' || _visibility == 'team')
                const SizedBox(height: 12),
              if (_visibility == 'team')
                DropdownButtonFormField<int>(
                  isExpanded: true,
                  initialValue: _teamId,
                  decoration: InputDecoration(
                    labelText: t('rides.team'),
                    prefixIcon: Icon(Icons.groups_outlined),
                  ),
                  items: _visibleTeams
                      .map(
                        (team) => DropdownMenuItem(
                          value: _rideInt(team['id']),
                          child: Text(_rideText(team['name'])),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => setState(() {
                    _teamId = value;
                    final selected = widget.teams
                        .where((team) => _rideInt(team['id']) == value)
                        .firstOrNull;
                    _clubId = _rideNullableInt(selected?['club_id']) ?? _clubId;
                    if (!_visibleEvents.any(
                      (event) => _rideInt(event['id']) == _eventId,
                    )) {
                      _eventId = null;
                    }
                  }),
                ),
              if (_visibility == 'team') const SizedBox(height: 12),
              DropdownButtonFormField<int>(
                isExpanded: true,
                initialValue: _eventId ?? 0,
                decoration: InputDecoration(
                  labelText: t('rides.eventOptional'),
                  prefixIcon: Icon(Icons.event_outlined),
                ),
                items: [
                  DropdownMenuItem<int>(
                    value: 0,
                    child: Text(t('rides.noEvent')),
                  ),
                  ..._visibleEvents.map(
                    (event) => DropdownMenuItem<int>(
                      value: _rideInt(event['id']),
                      child: Text(_rideText(event['title'])),
                    ),
                  ),
                ],
                onChanged: (value) =>
                    setState(() => _eventId = value == 0 ? null : value),
              ),
              const SizedBox(height: 12),
              RideLocationField(
                client: widget.client,
                controller: _from,
                label: t('rides.from'),
                required: true,
                onSelected: (place) => _chooseAddress(true, place),
              ),
              const SizedBox(height: 12),
              RideLocationField(
                client: widget.client,
                controller: _to,
                label: t('rides.to'),
                required: true,
                onSelected: (place) => _chooseAddress(false, place),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _pickDeparture,
                icon: Icon(Icons.calendar_month_outlined),
                label: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  child: Text(
                    '${t('rides.departure')}: ${_formatRideDate(context, _departure)}',
                  ),
                ),
              ),
              const SizedBox(height: 12),
              _RideField(
                controller: _seats,
                label: t('rides.seatsIncludingDriver'),
                icon: Icons.event_seat_outlined,
                keyboardType: TextInputType.number,
                required: true,
                validator: (value) {
                  final parsed = int.tryParse(value ?? '');
                  if (parsed == null || parsed < 1 || parsed > 20) {
                    return t('rides.seatsInvalid');
                  }
                  return null;
                },
              ),
              const SizedBox(height: 18),
              Eyebrow(t('rides.pickup')),
              const SizedBox(height: 6),
              Text(
                t('rides.pickupEditorHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 12),
              _RideField(
                controller: _pickupName,
                label: t('rides.pickupName'),
                icon: Icons.place_outlined,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    flex: 3,
                    child: _RideField(
                      controller: _street,
                      label: t('rides.street'),
                      icon: Icons.signpost_outlined,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _RideField(
                      controller: _house,
                      label: t('rides.house'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _RideField(
                      controller: _postal,
                      required: true,
                      label: t('rides.postal'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    flex: 2,
                    child: _RideField(
                      controller: _city,
                      required: true,
                      label: t('rides.city'),
                    ),
                  ),
                  const SizedBox(width: 10),
                ],
              ),
              const SizedBox(height: 12),
              CountryField(
                controller: _country,
                label: t('rides.country'),
                required: true,
              ),
              const SizedBox(height: 12),
              _RideField(
                controller: _note,
                label: t('rides.pickupNote'),
                icon: Icons.info_outline,
                maxLines: 2,
                maxLength: 500,
              ),
              const SizedBox(height: 12),
              Eyebrow(t('rides.destinationAddress')),
              const SizedBox(height: 8),
              Text(t('rides.destinationPrivacy')),
              const SizedBox(height: 12),
              for (final entry in {
                'postal_code': 'rides.postal',
                'city': 'rides.city',
                'country': 'rides.country',
                'street': 'rides.street',
                'house_number': 'rides.house',
              }.entries) ...[
                if (entry.key == 'country')
                  CountryField(
                    controller: _destination[entry.key]!,
                    label: t(entry.value),
                    required: true,
                  )
                else
                  _RideField(
                    controller: _destination[entry.key]!,
                    label: t(entry.value),
                    required: [
                      'postal_code',
                      'city',
                      'country',
                    ].contains(entry.key),
                    maxLength: entry.key == 'country'
                        ? 2
                        : entry.key == 'postal_code'
                        ? 30
                        : entry.key == 'house_number'
                        ? 40
                        : 255,
                  ),
                const SizedBox(height: 12),
              ],
              _RideField(
                controller: _contact,
                label: t('rides.contact'),
                icon: Icons.contact_phone_outlined,
                maxLines: 3,
                maxLength: 1000,
              ),
              const SizedBox(height: 18),
              if (_saveError != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text(
                    _saveError!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
              AirmiusButton(
                label: t(widget.ride == null ? 'rides.create' : 'save'),
                icon: Icons.save_outlined,
                onPressed: _saving ? null : _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RideField extends StatelessWidget {
  const _RideField({
    required this.controller,
    required this.label,
    this.icon,
    this.required = false,
    this.keyboardType,
    this.maxLines = 1,
    this.maxLength,
    this.validator,
  });

  final TextEditingController controller;
  final String label;
  final IconData? icon;
  final bool required;
  final TextInputType? keyboardType;
  final int maxLines;
  final int? maxLength;
  final FormFieldValidator<String>? validator;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      maxLines: maxLines,
      maxLength: maxLength,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: icon == null ? null : Icon(icon),
      ),
      validator:
          validator ??
          (value) {
            if (required && (value == null || value.trim().isEmpty)) {
              return AirmiusScope.of(context).t('required');
            }
            return null;
          },
    );
  }
}

class _SafetyLine extends StatelessWidget {
  const _SafetyLine({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: airmiusAccentColor(context)),
          const SizedBox(width: 12),
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
                const SizedBox(height: 3),
                Text(
                  body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RideError extends StatelessWidget {
  const _RideError({this.message, required this.onRetry});

  final String? message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.directions_car_outlined,
              color: Theme.of(context).colorScheme.error,
              size: 44,
            ),
            const SizedBox(height: 12),
            Text(
              t('rides.loadError'),
              textAlign: TextAlign.center,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            if (message != null && message!.trim().isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                message!,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.35,
                ),
              ),
            ],
            const SizedBox(height: 12),
            AirmiusButton(
              label: t('rides.retry'),
              icon: Icons.refresh_outlined,
              onPressed: onRetry,
            ),
          ],
        ),
      ),
    );
  }
}

JsonMap _rideMap(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

List<JsonMap> _rideList(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <JsonMap>[];

String _rideText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _rideInt(Object? value, {int fallback = 0}) =>
    value is int ? value : int.tryParse('${value ?? ''}') ?? fallback;

int? _rideNullableInt(Object? value) {
  final parsed = _rideInt(value);
  return parsed == 0 ? null : parsed;
}

DateTime? _rideDate(Object? value) {
  final parsed = DateTime.tryParse(_rideText(value));
  return parsed?.toLocal();
}

bool _isPast(JsonMap ride) {
  final departure = _rideDate(ride['departure_time']);
  return departure != null && departure.isBefore(DateTime.now());
}

String _formatRideDate(BuildContext context, DateTime? value) {
  if (value == null) return AirmiusScope.of(context).t('rides.dateUnknown');
  final localizations = MaterialLocalizations.of(context);
  return '${localizations.formatMediumDate(value)} · ${localizations.formatTimeOfDay(TimeOfDay.fromDateTime(value), alwaysUse24HourFormat: true)}';
}

String? _nullable(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}
