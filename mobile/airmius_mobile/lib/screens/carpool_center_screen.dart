import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class CarpoolCenterScreen extends StatefulWidget {
  const CarpoolCenterScreen({super.key});

  @override
  State<CarpoolCenterScreen> createState() => _CarpoolCenterScreenState();
}

class _CarpoolCenterScreenState extends State<CarpoolCenterScreen> {
  Future<JsonMap>? _future;
  String _filter = 'upcoming';
  bool _busy = false;

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

  void _reload() {
    setState(() {
      _future = _client.rides();
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
    List<JsonMap> teams, {
    JsonMap? ride,
  }) async {
    final payload = await showModalBottomSheet<JsonMap>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) =>
          _RideEditorSheet(clubs: clubs, teams: teams, ride: ride),
    );
    if (payload == null || !mounted) return;

    await _run(
      () => ride == null
          ? _client.createRide(payload)
          : _client.updateRide(_rideInt(ride['id']), payload),
      t(ride == null ? 'rides.created' : 'rides.updated'),
    );
  }

  Future<void> _requestRide(JsonMap ride) async {
    final controller = TextEditingController();
    final send = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('rides.requestTitle')),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(t('rides.requestHint'), style: TextStyle(height: 1.4)),
            const SizedBox(height: 14),
            TextField(
              controller: controller,
              maxLength: 500,
              maxLines: 4,
              decoration: InputDecoration(
                labelText: t('rides.messageOptional'),
                prefixIcon: Icon(Icons.message_outlined),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('rides.sendRequest')),
          ),
        ],
      ),
    );
    final message = controller.text;
    controller.dispose();
    if (send != true || !mounted) return;
    await _run(
      () => _client.requestRide(_rideInt(ride['id']), message: message),
      t('rides.requestSent'),
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
  ) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => _RideDetailsSheet(
        ride: ride,
        busy: _busy,
        onEdit: () {
          Navigator.pop(sheetContext);
          _editRide(clubs, teams, ride: ride);
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
            return _RideError(onRetry: _reload);
          }

          final root = _rideMap(snapshot.data?['data']);
          final rides = _rideList(root['rides']);
          final clubs = _rideList(root['clubs']);
          final teams = _rideList(root['teams']);
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
                (sum, ride) =>
                    sum +
                    (_rideInt(ride['seats']) -
                            _rideInt(ride['participants_count']))
                        .clamp(0, 20),
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
                        onPressed: _busy ? null : () => _editRide(clubs, teams),
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
                        onTap: () => _showDetails(ride, clubs, teams),
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
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(_formatRideDate(context, departure)),
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
    final privatePickup = _rideText(ride['pickup_private_label']);
    final publicPickup = _rideText(ride['pickup_public_label']);
    final contact = _rideText(ride['contact_details']);
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
            AirmiusPanel(
              child: Column(
                children: [
                  _DetailLine(
                    icon: Icons.schedule_outlined,
                    label: t('rides.departure'),
                    value: _formatRideDate(
                      context,
                      _rideDate(ride['departure_time']),
                    ),
                  ),
                  _DetailLine(
                    icon: Icons.event_seat_outlined,
                    label: t('rides.seats'),
                    value: t('rides.seatCount')
                        .replaceFirst(
                          '{used}',
                          '${_rideInt(ride['participants_count'])}',
                        )
                        .replaceFirst('{total}', '${_rideInt(ride['seats'])}'),
                  ),
                  _DetailLine(
                    icon: Icons.visibility_outlined,
                    label: t('rides.visibility'),
                    value: t(
                      'rides.visibility.${_rideText(ride['visibility'], fallback: 'public')}',
                    ),
                  ),
                  if (publicPickup.isNotEmpty)
                    _DetailLine(
                      icon: Icons.location_on_outlined,
                      label: privatePickup.isEmpty
                          ? t('rides.approxPickup')
                          : t('rides.pickup'),
                      value: privatePickup.isNotEmpty
                          ? privatePickup
                          : publicPickup,
                    ),
                  if (privatePickup.isEmpty && publicPickup.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(
                        t('rides.privateAfterApproval'),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontSize: 13,
                          height: 1.35,
                        ),
                      ),
                    ),
                  if (contact.isNotEmpty)
                    _DetailLine(
                      icon: Icons.contact_phone_outlined,
                      label: t('rides.contact'),
                      value: contact,
                    ),
                ],
              ),
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
                                  onPressed: busy
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
  const _RideEditorSheet({required this.clubs, required this.teams, this.ride});

  final List<JsonMap> clubs;
  final List<JsonMap> teams;
  final JsonMap? ride;

  @override
  State<_RideEditorSheet> createState() => _RideEditorSheetState();
}

class _RideEditorSheetState extends State<_RideEditorSheet> {
  final _formKey = GlobalKey<FormState>();
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
  late String _visibility;
  int? _clubId;
  int? _teamId;
  late DateTime _departure;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    final ride = widget.ride ?? const <String, dynamic>{};
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
      _contact,
      _seats,
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

  void _submit() {
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

    Navigator.pop(context, {
      'visibility': _visibility,
      'club_id': _visibility == 'club' || _visibility == 'team'
          ? _clubId
          : null,
      'team_id': _visibility == 'team' ? _teamId : null,
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
    });
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.94,
      minChildSize: 0.7,
      maxChildSize: 0.98,
      builder: (context, scrollController) => Form(
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
                    onPressed: () => Navigator.pop(context),
                    icon: Icon(Icons.close),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
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
                }),
              ),
              const SizedBox(height: 12),
              if (_visibility == 'club' || _visibility == 'team')
                DropdownButtonFormField<int>(
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
                  }),
                ),
              if (_visibility == 'club' || _visibility == 'team')
                const SizedBox(height: 12),
              if (_visibility == 'team')
                DropdownButtonFormField<int>(
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
                  }),
                ),
              if (_visibility == 'team') const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _RideField(
                      controller: _from,
                      label: t('rides.from'),
                      icon: Icons.trip_origin_outlined,
                      required: true,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _RideField(
                      controller: _to,
                      label: t('rides.to'),
                      icon: Icons.flag_outlined,
                      required: true,
                    ),
                  ),
                ],
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
                      label: t('rides.postal'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    flex: 2,
                    child: _RideField(
                      controller: _city,
                      label: t('rides.city'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  SizedBox(
                    width: 82,
                    child: _RideField(
                      controller: _country,
                      label: t('rides.country'),
                      maxLength: 2,
                    ),
                  ),
                ],
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
              _RideField(
                controller: _contact,
                label: t('rides.contact'),
                icon: Icons.contact_phone_outlined,
                maxLines: 3,
                maxLength: 1000,
              ),
              const SizedBox(height: 18),
              AirmiusButton(
                label: t(widget.ride == null ? 'rides.create' : 'save'),
                icon: Icons.save_outlined,
                onPressed: _submit,
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

class _DetailLine extends StatelessWidget {
  const _DetailLine({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: airmiusAccentColor(context), size: 22),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w800,
                    height: 1.3,
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
  const _RideError({required this.onRetry});

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
