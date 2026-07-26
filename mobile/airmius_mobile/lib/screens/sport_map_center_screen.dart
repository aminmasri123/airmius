import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SportMapCenterScreen extends StatefulWidget {
  const SportMapCenterScreen({super.key});

  @override
  State<SportMapCenterScreen> createState() => _SportMapCenterScreenState();
}

class _SportMapCenterScreenState extends State<SportMapCenterScreen> {
  String _section = 'routes';
  Future<_SportMapBundle>? _bundleFuture;
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _bundleFuture ??= _loadBundle();
  }

  Future<_SportMapBundle> _loadBundle() async {
    final responses = await Future.wait([
      _client.sportRoutes(),
      _client.sportTracks(),
      _client.sportPlaces(),
    ]);
    return _SportMapBundle(
      routes: _smMaps(responses[0]['data']),
      tracks: _smMaps(responses[1]['data']),
      places: _smMaps(responses[2]['data']),
    );
  }

  void _reload() {
    setState(() => _bundleFuture = _loadBundle());
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
          t('sportMap.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('sportMap.reload'),
            onPressed: _busy ? null : _reload,
            icon: Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('sportMap.title'),
        subtitle: t('sportMap.subtitle'),
        child: FutureBuilder<_SportMapBundle>(
          future: _bundleFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const _SportMapLoading();
            }
            if (snapshot.hasError) {
              return _SportMapError(error: snapshot.error, onRetry: _reload);
            }
            return _buildContent(snapshot.data ?? const _SportMapBundle());
          },
        ),
      ),
    );
  }

  Widget _buildContent(_SportMapBundle bundle) {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('sportMap.overview')),
              const SizedBox(height: 8),
              Text(
                t('sportMap.overviewHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 14),
              LayoutBuilder(
                builder: (context, constraints) {
                  final width = (constraints.maxWidth - 20) / 3;
                  return Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      SizedBox(
                        width: width,
                        child: MetricCard(
                          value: '${bundle.routes.length}',
                          label: t('sportMap.routes'),
                        ),
                      ),
                      SizedBox(
                        width: width,
                        child: MetricCard(
                          value: '${bundle.tracks.length}',
                          label: t('sportMap.tracks'),
                        ),
                      ),
                      SizedBox(
                        width: width,
                        child: MetricCard(
                          value: '${bundle.places.length}',
                          label: t('sportMap.places'),
                        ),
                      ),
                    ],
                  );
                },
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        SegmentedButton<String>(
          segments: [
            ButtonSegment(
              value: 'routes',
              icon: Icon(Icons.route_outlined),
              label: Text(t('sportMap.routes')),
            ),
            ButtonSegment(
              value: 'tracks',
              icon: Icon(Icons.timeline_outlined),
              label: Text(t('sportMap.tracks')),
            ),
            ButtonSegment(
              value: 'places',
              icon: Icon(Icons.place_outlined),
              label: Text(t('sportMap.places')),
            ),
          ],
          selected: {_section},
          onSelectionChanged: (selection) =>
              setState(() => _section = selection.first),
          showSelectedIcon: false,
        ),
        const SizedBox(height: 14),
        if (_section == 'routes') _buildRoutes(bundle.routes),
        if (_section == 'tracks') _buildTracks(bundle.tracks),
        if (_section == 'places') _buildPlaces(bundle.places),
      ],
    );
  }

  Widget _buildRoutes(List<JsonMap> routes) {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: AirmiusButton(
                label: t('sportMap.createRoute'),
                icon: Icons.add_road_outlined,
                onPressed: _busy ? null : () => _editRoute(),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: AirmiusButton(
                label: t('sportMap.generateRoute'),
                icon: Icons.auto_awesome_rounded,
                onPressed: _busy ? null : () => _generateRoute(),
                secondary: true,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        if (routes.isEmpty)
          _SportMapEmpty(
            icon: Icons.route_outlined,
            text: t('sportMap.emptyRoutes'),
          )
        else
          for (final route in routes) ...[
            _RouteCard(
              route: route,
              busy: _busy,
              onDuplicate: () => _duplicateRoute(route),
              onEdit: _smBool(route['can_edit'])
                  ? () => _editRoute(route)
                  : null,
              onDelete: _smBool(route['can_edit'])
                  ? () => _delete(
                      type: 'route',
                      id: _smInt(route['id']),
                      successKey: 'sportMap.routeDeleted',
                    )
                  : null,
            ),
            const SizedBox(height: 10),
          ],
      ],
    );
  }

  Widget _buildTracks(List<JsonMap> tracks) {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          borderColor: airmiusAccentColor(context).withValues(alpha: .45),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.info_outline, color: airmiusAccentColor(context)),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  t('sportMap.trackSafetyHint'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        if (tracks.isEmpty)
          _SportMapEmpty(
            icon: Icons.timeline_outlined,
            text: t('sportMap.emptyTracks'),
          )
        else
          for (final track in tracks) ...[
            _TrackCard(
              track: track,
              busy: _busy,
              onComplete:
                  _smBool(track['can_edit']) &&
                      ['recording', 'paused'].contains(_smText(track['status']))
                  ? () => _completeTrack(track)
                  : null,
              onDelete: _smBool(track['can_edit'])
                  ? () => _delete(
                      type: 'track',
                      id: _smInt(track['id']),
                      successKey: 'sportMap.trackDeleted',
                    )
                  : null,
            ),
            const SizedBox(height: 10),
          ],
      ],
    );
  }

  Widget _buildPlaces(List<JsonMap> places) {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusButton(
          label: t('sportMap.createPlace'),
          icon: Icons.add_location_alt_outlined,
          onPressed: _busy ? null : () => _editPlace(),
        ),
        const SizedBox(height: 12),
        if (places.isEmpty)
          _SportMapEmpty(
            icon: Icons.place_outlined,
            text: t('sportMap.emptyPlaces'),
          )
        else
          for (final place in places) ...[
            _PlaceCard(
              place: place,
              busy: _busy,
              onEdit: _smBool(place['can_edit'])
                  ? () => _editPlace(place)
                  : null,
              onDelete: _smBool(place['can_edit'])
                  ? () => _delete(
                      type: 'place',
                      id: _smInt(place['id']),
                      successKey: 'sportMap.placeDeleted',
                    )
                  : null,
            ),
            const SizedBox(height: 10),
          ],
      ],
    );
  }

  Future<void> _editRoute([JsonMap? route]) async {
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _RouteEditorDialog(route: route),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async {
        final id = _smInt(route?['id']);
        if (id > 0) {
          await _client.updateSportRoute(id, payload);
        } else {
          await _client.createSportRoute(payload);
        }
      },
      successKey: route == null
          ? 'sportMap.routeCreated'
          : 'sportMap.routeUpdated',
    );
  }

  Future<void> _generateRoute() async {
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => const _RouteGeneratorDialog(),
    );
    if (payload == null || !mounted) return;
    await _run(() async {
      final generated = await _client.generateSportRouteProposal(payload);
      await _client.createSportRoute(
        _routePayloadFromProposal(_smMap(generated['data'])),
      );
    }, successKey: 'sportMap.routeGenerated');
  }

  JsonMap _routePayloadFromProposal(JsonMap proposal) {
    final waypoints = _smMaps(proposal['waypoints']);
    final routeGeometry = _smMap(proposal['route_geometry']);
    final metrics = _smMap(proposal['metrics']);
    final distanceMeters = _smInt(proposal['distance_meters']);
    final durationSeconds = _smInt(proposal['estimated_duration_seconds']);
    final elevationGain = _smInt(proposal['elevation_gain_meters']);
    final elevationLoss = _smInt(proposal['elevation_loss_meters']);
    final payload = <String, dynamic>{
      'title': _smText(
        proposal['title'],
        fallback: AirmiusScope.of(context).t('sportMap.generatedRoute'),
      ),
      'sport_type': _smText(proposal['sport_type'], fallback: 'running'),
      'difficulty': _smText(
        proposal['difficulty'],
        fallback: 'easy',
      ),
      'surface': _smNullable(
        _smText(proposal['surface'], fallback: ''),
      ),
      'waypoints': waypoints,
      if (metrics.isNotEmpty) 'metrics': metrics,
      if (distanceMeters > 0) 'distance_meters': distanceMeters,
      if (durationSeconds > 0) 'estimated_duration_seconds': durationSeconds,
      if (elevationGain > 0) 'elevation_gain_meters': elevationGain,
      if (elevationLoss > 0) 'elevation_loss_meters': elevationLoss,
      if (routeGeometry.isNotEmpty) 'route_geometry': routeGeometry,
      'status': 'planned',
    };
    return payload;
  }

  Future<void> _duplicateRoute(JsonMap route) async {
    await _run(() async {
      await _client.duplicateSportRoute(_smInt(route['id']));
    }, successKey: 'sportMap.routeDuplicated');
  }

  Future<void> _editPlace([JsonMap? place]) async {
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _PlaceEditorDialog(place: place),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async {
        final id = _smInt(place?['id']);
        if (id > 0) {
          await _client.updateSportPlace(id, payload);
        } else {
          await _client.createSportPlace(payload);
        }
      },
      successKey: place == null
          ? 'sportMap.placeCreated'
          : 'sportMap.placeUpdated',
    );
  }

  Future<void> _completeTrack(JsonMap track) async {
    await _run(() async {
      await _client.completeSportTrack(_smInt(track['id']));
    }, successKey: 'sportMap.trackCompleted');
  }

  Future<void> _delete({
    required String type,
    required int id,
    required String successKey,
  }) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: airmiusSurfaceColor(context),
        title: Text(t('sportMap.deleteTitle')),
        content: Text(t('sportMap.deleteQuestion')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('cancel')),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
            ),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(() async {
      if (type == 'route') await _client.deleteSportRoute(id);
      if (type == 'track') await _client.deleteSportTrack(id);
      if (type == 'place') await _client.deleteSportPlace(id);
    }, successKey: successKey);
  }

  Future<void> _run(
    Future<void> Function() operation, {
    required String successKey,
  }) async {
    final t = AirmiusScope.of(context).t;
    setState(() => _busy = true);
    try {
      await operation();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t(successKey))));
      _reload();
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${t('sportMap.saveError')} ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }
}

class _SportMapBundle {
  const _SportMapBundle({
    this.routes = const [],
    this.tracks = const [],
    this.places = const [],
  });

  final List<JsonMap> routes;
  final List<JsonMap> tracks;
  final List<JsonMap> places;
}

class _RouteCard extends StatelessWidget {
  const _RouteCard({
    required this.route,
    required this.busy,
    required this.onDuplicate,
    this.onEdit,
    this.onDelete,
  });

  final JsonMap route;
  final bool busy;
  final VoidCallback onDuplicate;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final start = _smMap(route['start']);
    final end = _smMap(route['end']);
    return AirmiusPanel(
      onTap: () => _showRouteDetails(context, route),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _MapIcon(icon: Icons.route_outlined),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _smText(
                    route['title'],
                    fallback: t('sportMap.untitledRoute'),
                  ),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  [
                    if (_smText(start['name']).isNotEmpty)
                      _smText(start['name']),
                    if (_smText(end['name']).isNotEmpty) _smText(end['name']),
                  ].join(' → '),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.3,
                  ),
                ),
                const SizedBox(height: 9),
                Wrap(
                  spacing: 7,
                  runSpacing: 7,
                  children: [
                    StatusPill(
                      '${_smNumberLabel(route['distance_km'])} km',
                      color: Theme.of(context).colorScheme.secondary,
                    ),
                    StatusPill(
                      _smLocalized(
                        context,
                        'sportMap.sport',
                        _smText(route['sport_type'], fallback: 'other'),
                      ),
                    ),
                    StatusPill(
                      _smLocalized(
                        context,
                        'sportMap.visibility',
                        _smText(route['visibility'], fallback: 'private'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          PopupMenuButton<String>(
            enabled: !busy,
            tooltip: t('sportMap.actions'),
            onSelected: (value) {
              if (value == 'duplicate') onDuplicate();
              if (value == 'edit') onEdit?.call();
              if (value == 'delete') onDelete?.call();
            },
            itemBuilder: (_) => [
              PopupMenuItem(
                value: 'duplicate',
                child: ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(Icons.copy_outlined),
                  title: Text(t('sportMap.duplicate')),
                ),
              ),
              if (onEdit != null)
                PopupMenuItem(
                  value: 'edit',
                  child: ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(Icons.edit_outlined),
                    title: Text(t('edit')),
                  ),
                ),
              if (onDelete != null)
                PopupMenuItem(
                  value: 'delete',
                  child: ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(
                      Icons.delete_outline,
                      color: Theme.of(context).colorScheme.error,
                    ),
                    title: Text(t('delete')),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TrackCard extends StatelessWidget {
  const _TrackCard({
    required this.track,
    required this.busy,
    this.onComplete,
    this.onDelete,
  });

  final JsonMap track;
  final bool busy;
  final VoidCallback? onComplete;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final status = _smText(track['status'], fallback: 'completed');
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _MapIcon(
            icon: status == 'recording'
                ? Icons.gps_fixed
                : Icons.timeline_outlined,
            color: status == 'recording'
                ? Theme.of(context).colorScheme.error
                : airmiusAccentColor(context),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _smText(
                    track['title'],
                    fallback: t('sportMap.untitledTrack'),
                  ),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 7,
                  runSpacing: 7,
                  children: [
                    StatusPill(
                      '${_smNumberLabel(track['distance_km'])} km',
                      color: Theme.of(context).colorScheme.secondary,
                    ),
                    StatusPill(_durationLabel(track['duration_seconds'])),
                    StatusPill(
                      _smLocalized(context, 'sportMap.trackStatus', status),
                      color: status == 'recording'
                          ? Theme.of(context).colorScheme.error
                          : airmiusAccentColor(context),
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (onComplete != null || onDelete != null)
            PopupMenuButton<String>(
              enabled: !busy,
              tooltip: t('sportMap.actions'),
              onSelected: (value) =>
                  value == 'complete' ? onComplete?.call() : onDelete?.call(),
              itemBuilder: (_) => [
                if (onComplete != null)
                  PopupMenuItem(
                    value: 'complete',
                    child: ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(Icons.flag_outlined),
                      title: Text(t('sportMap.completeTrack')),
                    ),
                  ),
                if (onDelete != null)
                  PopupMenuItem(
                    value: 'delete',
                    child: ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(
                        Icons.delete_outline,
                        color: Theme.of(context).colorScheme.error,
                      ),
                      title: Text(t('delete')),
                    ),
                  ),
              ],
            ),
        ],
      ),
    );
  }
}

class _PlaceCard extends StatelessWidget {
  const _PlaceCard({
    required this.place,
    required this.busy,
    this.onEdit,
    this.onDelete,
  });

  final JsonMap place;
  final bool busy;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final location = _smMap(place['location']);
    final city = _smText(location['city'] ?? place['city']);
    final address = _smText(location['address'] ?? place['address']);
    return AirmiusPanel(
      onTap: () => _showPlaceDetails(context, place),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _MapIcon(icon: Icons.place_outlined),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _smText(place['name'], fallback: t('sportMap.untitledPlace')),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (address.isNotEmpty || city.isNotEmpty) ...[
                  const SizedBox(height: 5),
                  Text(
                    [address, city].where((part) => part.isNotEmpty).join(', '),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.3,
                    ),
                  ),
                ],
                const SizedBox(height: 9),
                Wrap(
                  spacing: 7,
                  runSpacing: 7,
                  children: [
                    StatusPill(
                      _smLocalized(
                        context,
                        'sportMap.placeType',
                        _smText(place['type'], fallback: 'other'),
                      ),
                    ),
                    StatusPill(
                      _smLocalized(
                        context,
                        'sportMap.visibility',
                        _smText(place['visibility'], fallback: 'public'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (onEdit != null || onDelete != null)
            PopupMenuButton<String>(
              enabled: !busy,
              tooltip: t('sportMap.actions'),
              onSelected: (value) =>
                  value == 'edit' ? onEdit?.call() : onDelete?.call(),
              itemBuilder: (_) => [
                if (onEdit != null)
                  PopupMenuItem(
                    value: 'edit',
                    child: ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(Icons.edit_outlined),
                      title: Text(t('edit')),
                    ),
                  ),
                if (onDelete != null)
                  PopupMenuItem(
                    value: 'delete',
                    child: ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(
                        Icons.delete_outline,
                        color: Theme.of(context).colorScheme.error,
                      ),
                      title: Text(t('delete')),
                    ),
                  ),
              ],
            ),
        ],
      ),
    );
  }
}

class _MapIcon extends StatelessWidget {
  const _MapIcon({required this.icon, this.color});

  final IconData icon;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final iconColor = color ?? airmiusAccentColor(context);
    return Container(
      width: 50,
      height: 50,
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Icon(icon, color: iconColor),
    );
  }
}

class _SportMapEmpty extends StatelessWidget {
  const _SportMapEmpty({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 24),
        child: Column(
          children: [
            Icon(icon, color: airmiusMutedColor(context), size: 38),
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

class _RouteEditorDialog extends StatefulWidget {
  const _RouteEditorDialog({this.route});

  final JsonMap? route;

  @override
  State<_RouteEditorDialog> createState() => _RouteEditorDialogState();
}

class _RouteEditorDialogState extends State<_RouteEditorDialog> {
  late final TextEditingController _title;
  late final TextEditingController _description;
  late final TextEditingController _startName;
  late final TextEditingController _startLat;
  late final TextEditingController _startLng;
  late final TextEditingController _endName;
  late final TextEditingController _endLat;
  late final TextEditingController _endLng;
  late final TextEditingController _surface;
  late String _sportType;
  late String _visibility;
  late String _difficulty;
  String? _error;

  @override
  void initState() {
    super.initState();
    final route = widget.route ?? const {};
    final start = _smMap(route['start']);
    final end = _smMap(route['end']);
    _title = TextEditingController(text: _smText(route['title']));
    _description = TextEditingController(text: _smText(route['description']));
    _startName = TextEditingController(text: _smText(start['name']));
    _startLat = TextEditingController(text: _smInput(start['latitude']));
    _startLng = TextEditingController(text: _smInput(start['longitude']));
    _endName = TextEditingController(text: _smText(end['name']));
    _endLat = TextEditingController(text: _smInput(end['latitude']));
    _endLng = TextEditingController(text: _smInput(end['longitude']));
    _surface = TextEditingController(text: _smText(route['surface']));
    _sportType = _smText(route['sport_type'], fallback: 'running');
    _visibility = _smText(route['visibility'], fallback: 'private');
    if (_visibility == 'team') _visibility = 'private';
    _difficulty = _smText(route['difficulty'], fallback: 'moderate');
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _startName.dispose();
    _startLat.dispose();
    _startLng.dispose();
    _endName.dispose();
    _endLat.dispose();
    _endLng.dispose();
    _surface.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      backgroundColor: airmiusSurfaceColor(context),
      title: Text(
        t(widget.route == null ? 'sportMap.createRoute' : 'sportMap.editRoute'),
      ),
      content: SizedBox(
        width: 580,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: _title,
                autofocus: true,
                maxLength: 160,
                decoration: InputDecoration(
                  labelText: t('sportMap.name'),
                  errorText: _error,
                ),
                onChanged: (_) {
                  if (_error != null) setState(() => _error = null);
                },
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _description,
                minLines: 2,
                maxLines: 4,
                maxLength: 4000,
                decoration: InputDecoration(
                  labelText: t('sportMap.description'),
                ),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: _SmDropdown(
                      value: _sportType,
                      label: t('sportMap.sportType'),
                      values: _sportTypes,
                      prefix: 'sportMap.sport',
                      onChanged: (value) => setState(() => _sportType = value),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _SmDropdown(
                      value: _visibility,
                      label: t('sportMap.visibilityLabel'),
                      values: const ['private', 'public'],
                      prefix: 'sportMap.visibility',
                      onChanged: (value) => setState(() => _visibility = value),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: _SmDropdown(
                      value: _difficulty,
                      label: t('sportMap.difficulty'),
                      values: const ['easy', 'moderate', 'hard', 'expert'],
                      prefix: 'sportMap.difficultyValue',
                      onChanged: (value) => setState(() => _difficulty = value),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextField(
                      controller: _surface,
                      maxLength: 60,
                      decoration: InputDecoration(
                        labelText: t('sportMap.surface'),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              _CoordinateEditor(
                title: t('sportMap.start'),
                name: _startName,
                latitude: _startLat,
                longitude: _startLng,
              ),
              const SizedBox(height: 12),
              _CoordinateEditor(
                title: t('sportMap.destination'),
                name: _endName,
                latitude: _endLat,
                longitude: _endLng,
              ),
              const SizedBox(height: 10),
              Text(
                t('sportMap.coordinateHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  fontSize: 12,
                ),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton.icon(
          onPressed: _submit,
          icon: Icon(Icons.save_outlined),
          label: Text(t('save')),
        ),
      ],
    );
  }

  void _submit() {
    final t = AirmiusScope.of(context).t;
    final startLat = _smDoubleOrNull(_startLat.text);
    final startLng = _smDoubleOrNull(_startLng.text);
    final endLat = _smDoubleOrNull(_endLat.text);
    final endLng = _smDoubleOrNull(_endLng.text);
    if (_title.text.trim().isEmpty) {
      setState(() => _error = t('sportMap.nameRequired'));
      return;
    }
    if (!_validCoordinates(startLat, startLng) ||
        !_validCoordinates(endLat, endLng)) {
      setState(() => _error = t('sportMap.coordinatesInvalid'));
      return;
    }
    final existingWaypoints = _smMaps(widget.route?['waypoints']);
    final startPoint = <String, dynamic>{
      'name': _smNullable(_startName.text),
      'latitude': startLat,
      'longitude': startLng,
    };
    final endPoint = <String, dynamic>{
      'name': _smNullable(_endName.text),
      'latitude': endLat,
      'longitude': endLng,
    };
    final waypoints = existingWaypoints.length > 2
        ? [
            startPoint,
            ...existingWaypoints.sublist(1, existingWaypoints.length - 1),
            endPoint,
          ]
        : [startPoint, endPoint];
    Navigator.pop(context, <String, dynamic>{
      'title': _title.text.trim(),
      'description': _smNullable(_description.text),
      'sport_type': _sportType,
      'visibility': _visibility,
      'status': _smText(widget.route?['status'], fallback: 'planned'),
      'difficulty': _difficulty,
      'surface': _smNullable(_surface.text),
      'waypoints': waypoints,
    });
  }
}

class _RouteGeneratorDialog extends StatefulWidget {
  const _RouteGeneratorDialog();

  @override
  State<_RouteGeneratorDialog> createState() => _RouteGeneratorDialogState();
}

class _RouteGeneratorDialogState extends State<_RouteGeneratorDialog> {
  late final TextEditingController _title;
  late final TextEditingController _startName;
  late final TextEditingController _startLat;
  late final TextEditingController _startLng;
  late final TextEditingController _destinationName;
  late final TextEditingController _destinationLat;
  late final TextEditingController _destinationLng;
  late final TextEditingController _distance;
  late final TextEditingController _duration;
  late String _sportType;
  late String _routeType;
  late String _targetMode;
  String? _error;

  @override
  void initState() {
    super.initState();
    _title = TextEditingController();
    _startName = TextEditingController();
    _startLat = TextEditingController();
    _startLng = TextEditingController();
    _destinationName = TextEditingController();
    _destinationLat = TextEditingController();
    _destinationLng = TextEditingController();
    _distance = TextEditingController(text: '5');
    _duration = TextEditingController(text: '45');
    _sportType = 'running';
    _routeType = 'roundtrip';
    _targetMode = 'distance';
  }

  @override
  void dispose() {
    _title.dispose();
    _startName.dispose();
    _startLat.dispose();
    _startLng.dispose();
    _destinationName.dispose();
    _destinationLat.dispose();
    _destinationLng.dispose();
    _distance.dispose();
    _duration.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final isPointToPoint = _routeType == 'point_to_point';
    return AlertDialog(
      backgroundColor: airmiusSurfaceColor(context),
      title: Text(t('sportMap.generatorTitle')),
      content: SizedBox(
        width: 580,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: _title,
                maxLength: 160,
                decoration: InputDecoration(
                  labelText: t('sportMap.name'),
                  hintText: t('sportMap.routeAutoTitleHint'),
                  errorText: _error,
                ),
                onChanged: (_) {
                  if (_error != null) setState(() => _error = null);
                },
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: _SmDropdown(
                      value: _sportType,
                      label: t('sportMap.sportType'),
                      values: _sportTypes,
                      prefix: 'sportMap.sport',
                      onChanged: (value) => setState(() => _sportType = value),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _SmDropdown(
                      value: _routeType,
                      label: t('sportMap.routeType'),
                      values: _routeTypes,
                      prefix: 'sportMap.routeType',
                      onChanged: (value) => setState(() => _routeType = value),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: _SmDropdown(
                      value: _targetMode,
                      label: t('sportMap.targetMode'),
                      values: _routeTargetModes,
                      prefix: 'sportMap.targetMode',
                      onChanged: (value) =>
                          setState(() => _targetMode = value),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextField(
                      controller: _targetMode == 'distance'
                          ? _distance
                          : _duration,
                      keyboardType: TextInputType.numberWithOptions(
                        decimal: _targetMode == 'distance',
                      ),
                      decoration: InputDecoration(
                        labelText: _targetMode == 'distance'
                            ? t('sportMap.distance')
                            : '${t('sportMap.targetMode.duration')} (${t('sportMap.minutes')})',
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              _CoordinateEditor(
                title: t('sportMap.start'),
                name: _startName,
                latitude: _startLat,
                longitude: _startLng,
              ),
              const SizedBox(height: 10),
              if (isPointToPoint)
                _CoordinateEditor(
                  title: t('sportMap.destination'),
                  name: _destinationName,
                  latitude: _destinationLat,
                  longitude: _destinationLng,
                ),
              if (isPointToPoint) const SizedBox(height: 10),
              Text(
                isPointToPoint
                    ? t('sportMap.generatorPointHint')
                    : t('sportMap.generatorHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  fontSize: 12,
                ),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton.icon(
          onPressed: _submit,
          icon: Icon(Icons.auto_awesome_rounded),
          label: Text(t('sportMap.generateRoute')),
        ),
      ],
    );
  }

  void _submit() {
    final t = AirmiusScope.of(context).t;
    final startLat = _smDoubleOrNull(_startLat.text);
    final startLng = _smDoubleOrNull(_startLng.text);
    final destinationLat = _smDoubleOrNull(_destinationLat.text);
    final destinationLng = _smDoubleOrNull(_destinationLng.text);
    final distance = _smDoubleOrNull(_distance.text);
    final duration = _smDoubleOrNull(_duration.text);
    if (!_validCoordinates(startLat, startLng)) {
      setState(() => _error = t('sportMap.coordinatesInvalid'));
      return;
    }
    if (_routeType == 'point_to_point' &&
        !_validCoordinates(destinationLat, destinationLng)) {
      setState(() => _error = t('sportMap.destinationRequired'));
      return;
    }
    if (_targetMode == 'distance' && (distance == null || distance <= 0)) {
      setState(() => _error = t('sportMap.distanceInvalid'));
      return;
    }
    if (_targetMode == 'duration' && (duration == null || duration <= 0)) {
      setState(() => _error = t('sportMap.durationInvalid'));
      return;
    }
    final payload = <String, dynamic>{
      'title': _smNullable(_title.text),
      'sport_type': _sportType,
      'route_type': _routeType,
      'target_mode': _targetMode,
      'start': {
        'name': _smNullable(_startName.text),
        'latitude': startLat,
        'longitude': startLng,
      },
      if (_routeType == 'point_to_point')
        'waypoints': [
          {
            'name': _smNullable(_startName.text),
            'latitude': startLat,
            'longitude': startLng,
          },
          {
            'name': _smNullable(_destinationName.text),
            'latitude': destinationLat,
            'longitude': destinationLng,
          },
        ],
    };
    if (_targetMode == 'distance') {
      payload['distance_km'] = distance!;
    } else {
      payload['duration_minutes'] = duration!.round();
    }
    Navigator.pop(context, payload);
  }
}

class _PlaceEditorDialog extends StatefulWidget {
  const _PlaceEditorDialog({this.place});

  final JsonMap? place;

  @override
  State<_PlaceEditorDialog> createState() => _PlaceEditorDialogState();
}

class _PlaceEditorDialogState extends State<_PlaceEditorDialog> {
  late final TextEditingController _name;
  late final TextEditingController _description;
  late final TextEditingController _latitude;
  late final TextEditingController _longitude;
  late final TextEditingController _address;
  late final TextEditingController _city;
  late final TextEditingController _country;
  late String _type;
  late String _visibility;
  String? _error;

  @override
  void initState() {
    super.initState();
    final place = widget.place ?? const {};
    final location = _smMap(place['location']);
    _name = TextEditingController(text: _smText(place['name']));
    _description = TextEditingController(text: _smText(place['description']));
    _latitude = TextEditingController(
      text: _smInput(place['latitude'] ?? location['latitude']),
    );
    _longitude = TextEditingController(
      text: _smInput(place['longitude'] ?? location['longitude']),
    );
    _address = TextEditingController(
      text: _smText(place['address'] ?? location['address']),
    );
    _city = TextEditingController(
      text: _smText(place['city'] ?? location['city']),
    );
    _country = TextEditingController(
      text: _smText(
        place['country_code'] ?? location['country_code'],
        fallback: 'DE',
      ),
    );
    _type = _smText(place['type'], fallback: 'other');
    _visibility = _smText(place['visibility'], fallback: 'public');
    if (_visibility == 'team') _visibility = 'private';
  }

  @override
  void dispose() {
    _name.dispose();
    _description.dispose();
    _latitude.dispose();
    _longitude.dispose();
    _address.dispose();
    _city.dispose();
    _country.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      backgroundColor: airmiusSurfaceColor(context),
      title: Text(
        t(widget.place == null ? 'sportMap.createPlace' : 'sportMap.editPlace'),
      ),
      content: SizedBox(
        width: 560,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: _name,
                autofocus: true,
                maxLength: 160,
                decoration: InputDecoration(
                  labelText: t('sportMap.name'),
                  errorText: _error,
                ),
                onChanged: (_) {
                  if (_error != null) setState(() => _error = null);
                },
              ),
              const SizedBox(height: 8),
              _SmDropdown(
                value: _type,
                label: t('sportMap.placeTypeLabel'),
                values: _placeTypes,
                prefix: 'sportMap.placeType',
                onChanged: (value) => setState(() => _type = value),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _description,
                minLines: 2,
                maxLines: 4,
                maxLength: 4000,
                decoration: InputDecoration(
                  labelText: t('sportMap.description'),
                ),
              ),
              const SizedBox(height: 8),
              _CoordinateEditor(
                title: t('sportMap.coordinates'),
                latitude: _latitude,
                longitude: _longitude,
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _address,
                maxLength: 255,
                decoration: InputDecoration(labelText: t('sportMap.address')),
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _city,
                      maxLength: 120,
                      decoration: InputDecoration(
                        labelText: t('sportMap.city'),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  SizedBox(
                    width: 110,
                    child: TextField(
                      controller: _country,
                      maxLength: 2,
                      textCapitalization: TextCapitalization.characters,
                      decoration: InputDecoration(
                        labelText: t('sportMap.country'),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              _SmDropdown(
                value: _visibility,
                label: t('sportMap.visibilityLabel'),
                values: const ['private', 'public'],
                prefix: 'sportMap.visibility',
                onChanged: (value) => setState(() => _visibility = value),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton.icon(
          onPressed: _submit,
          icon: Icon(Icons.save_outlined),
          label: Text(t('save')),
        ),
      ],
    );
  }

  void _submit() {
    final t = AirmiusScope.of(context).t;
    final latitude = _smDoubleOrNull(_latitude.text);
    final longitude = _smDoubleOrNull(_longitude.text);
    if (_name.text.trim().isEmpty) {
      setState(() => _error = t('sportMap.nameRequired'));
      return;
    }
    if (!_validCoordinates(latitude, longitude)) {
      setState(() => _error = t('sportMap.coordinatesInvalid'));
      return;
    }
    Navigator.pop(context, <String, dynamic>{
      'name': _name.text.trim(),
      'type': _type,
      'description': _smNullable(_description.text),
      'latitude': latitude,
      'longitude': longitude,
      'address': _smNullable(_address.text),
      'city': _smNullable(_city.text),
      'country_code': _country.text.trim().isEmpty
          ? null
          : _country.text.trim().toUpperCase(),
      'visibility': _visibility,
    });
  }
}

class _CoordinateEditor extends StatelessWidget {
  const _CoordinateEditor({
    required this.title,
    required this.latitude,
    required this.longitude,
    this.name,
  });

  final String title;
  final TextEditingController? name;
  final TextEditingController latitude;
  final TextEditingController longitude;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            title,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          if (name != null) ...[
            const SizedBox(height: 10),
            TextField(
              controller: name,
              maxLength: 120,
              decoration: InputDecoration(labelText: t('sportMap.pointName')),
            ),
          ],
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: latitude,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                    signed: true,
                  ),
                  decoration: InputDecoration(
                    labelText: t('sportMap.latitude'),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: TextField(
                  controller: longitude,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                    signed: true,
                  ),
                  decoration: InputDecoration(
                    labelText: t('sportMap.longitude'),
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

class _SmDropdown extends StatelessWidget {
  const _SmDropdown({
    required this.value,
    required this.label,
    required this.values,
    required this.prefix,
    required this.onChanged,
  });

  final String value;
  final String label;
  final List<String> values;
  final String prefix;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<String>(
      initialValue: value,
      decoration: InputDecoration(labelText: label),
      items: values
          .map(
            (item) => DropdownMenuItem(
              value: item,
              child: Text(_smLocalized(context, prefix, item)),
            ),
          )
          .toList(),
      onChanged: (next) {
        if (next != null) onChanged(next);
      },
    );
  }
}

class _SportMapLoading extends StatelessWidget {
  const _SportMapLoading();

  @override
  Widget build(BuildContext context) {
    return const AirmiusPanel(
      child: Padding(
        padding: EdgeInsets.all(28),
        child: Center(child: CircularProgressIndicator()),
      ),
    );
  }
}

class _SportMapError extends StatelessWidget {
  const _SportMapError({required this.error, required this.onRetry});

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
            t('sportMap.loadError'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(message, style: TextStyle(color: airmiusMutedColor(context))),
          const SizedBox(height: 14),
          AirmiusButton(
            label: t('sportMap.reload'),
            icon: Icons.refresh_outlined,
            secondary: true,
            onPressed: onRetry,
          ),
        ],
      ),
    );
  }
}

Future<void> _showRouteDetails(BuildContext context, JsonMap route) {
  final t = AirmiusScope.of(context).t;
  final start = _smMap(route['start']);
  final end = _smMap(route['end']);
  return showModalBottomSheet<void>(
    context: context,
    backgroundColor: airmiusSurfaceColor(context),
    showDragHandle: true,
    builder: (_) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 4, 18, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              _smText(route['title'], fallback: t('sportMap.untitledRoute')),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontSize: 21,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 12),
            _DetailLine(
              icon: Icons.trip_origin,
              label: t('sportMap.start'),
              value: _pointLabel(start),
            ),
            _DetailLine(
              icon: Icons.flag_outlined,
              label: t('sportMap.destination'),
              value: _pointLabel(end),
            ),
            _DetailLine(
              icon: Icons.straighten,
              label: t('sportMap.distance'),
              value: '${_smNumberLabel(route['distance_km'])} km',
            ),
            _DetailLine(
              icon: Icons.schedule_outlined,
              label: t('sportMap.duration'),
              value: _durationLabel(route['estimated_duration_seconds']),
            ),
            if (_smText(route['description']).isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(
                _smText(route['description']),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                ),
              ),
            ],
          ],
        ),
      ),
    ),
  );
}

Future<void> _showPlaceDetails(BuildContext context, JsonMap place) {
  final t = AirmiusScope.of(context).t;
  final location = _smMap(place['location']);
  return showModalBottomSheet<void>(
    context: context,
    backgroundColor: airmiusSurfaceColor(context),
    showDragHandle: true,
    builder: (_) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 4, 18, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              _smText(place['name'], fallback: t('sportMap.untitledPlace')),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontSize: 21,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 12),
            _DetailLine(
              icon: Icons.place_outlined,
              label: t('sportMap.coordinates'),
              value:
                  '${_smNumberLabel(place['latitude'] ?? location['latitude'])}, '
                  '${_smNumberLabel(place['longitude'] ?? location['longitude'])}',
            ),
            if (_smText(location['address']).isNotEmpty)
              _DetailLine(
                icon: Icons.signpost_outlined,
                label: t('sportMap.address'),
                value: _smText(location['address']),
              ),
            if (_smText(location['city']).isNotEmpty)
              _DetailLine(
                icon: Icons.location_city_outlined,
                label: t('sportMap.city'),
                value: _smText(location['city']),
              ),
            if (_smText(place['description']).isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(
                _smText(place['description']),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                ),
              ),
            ],
          ],
        ),
      ),
    ),
  );
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
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: airmiusAccentColor(context), size: 20),
          const SizedBox(width: 10),
          SizedBox(
            width: 92,
            child: Text(
              label,
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
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

const _sportTypes = [
  'running',
  'trail_running',
  'cycling',
  'mountainbike',
  'walking',
  'wandern',
  'football',
  'skateboard',
  'fitness',
  'other',
];
const _routeTypes = ['roundtrip', 'point_to_point'];
const _routeTargetModes = ['distance', 'duration'];

const _placeTypes = [
  'football_pitch',
  'running_track',
  'skatepark',
  'basketball_court',
  'tennis_court',
  'calisthenics_park',
  'swimming_pool',
  'climbing_spot',
  'trailhead',
  'sports_hall',
  'other',
];

JsonMap _smMap(Object? value) {
  if (value is JsonMap) return value;
  if (value is Map) {
    return value.map((key, item) => MapEntry('$key', item));
  }
  return const {};
}

List<JsonMap> _smMaps(Object? value) => value is List
    ? value.map(_smMap).where((item) => item.isNotEmpty).toList()
    : const [];

String _smText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty || text == 'null' ? fallback : text;
}

int _smInt(Object? value) =>
    value is num ? value.round() : int.tryParse('$value') ?? 0;

double _smNumber(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;

bool _smBool(Object? value) =>
    value == true || value == 1 || '$value'.toLowerCase() == 'true';

String _smNumberLabel(Object? value) {
  final number = _smNumber(value);
  return number == number.roundToDouble()
      ? '${number.round()}'
      : number
            .toStringAsFixed(2)
            .replaceAll(RegExp(r'0+$'), '')
            .replaceAll(RegExp(r'\.$'), '');
}

String _smInput(Object? value) {
  if (value == null || '$value' == 'null') return '';
  return _smNumber(value).toString();
}

double? _smDoubleOrNull(String value) =>
    double.tryParse(value.trim().replaceAll(',', '.'));

String? _smNullable(String value) => value.trim().isEmpty ? null : value.trim();

bool _validCoordinates(double? latitude, double? longitude) =>
    latitude != null &&
    longitude != null &&
    latitude >= -90 &&
    latitude <= 90 &&
    longitude >= -180 &&
    longitude <= 180;

String _smLocalized(BuildContext context, String prefix, String value) {
  final key = '$prefix.$value';
  final translated = AirmiusScope.of(context).t(key);
  return translated == key ? value : translated;
}

String _durationLabel(Object? secondsValue) {
  final seconds = _smInt(secondsValue);
  if (seconds <= 0) return '—';
  final hours = seconds ~/ 3600;
  final minutes = (seconds % 3600) ~/ 60;
  return hours > 0 ? '${hours}h ${minutes}m' : '${minutes}m';
}

String _pointLabel(JsonMap point) {
  final name = _smText(point['name']);
  final coordinates =
      '${_smNumberLabel(point['latitude'])}, ${_smNumberLabel(point['longitude'])}';
  return name.isEmpty ? coordinates : '$name · $coordinates';
}
