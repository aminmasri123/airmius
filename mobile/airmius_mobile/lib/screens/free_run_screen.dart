import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import 'package:permission_handler/permission_handler.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_free_run_draft_store.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';

class FreeRunResult {
  const FreeRunResult({required this.logId, required this.trackId});

  final int logId;
  final int trackId;
}

enum FreeRunLocationState {
  ready,
  serviceDisabled,
  permissionDenied,
  permissionPermanentlyDenied,
  unavailable,
}

class FreeRunLocationSample {
  const FreeRunLocationSample({
    required this.latitude,
    required this.longitude,
    required this.recordedAt,
    this.elevationMeters,
    this.accuracyMeters,
  });

  final double latitude;
  final double longitude;
  final DateTime recordedAt;
  final double? elevationMeters;
  final double? accuracyMeters;

  LatLng get latLng => LatLng(latitude, longitude);

  Map<String, dynamic> toJson() => {
    'latitude': latitude,
    'longitude': longitude,
    'recorded_at': recordedAt.toIso8601String(),
    if (elevationMeters != null) 'elevation_m': elevationMeters,
    if (accuracyMeters != null) 'accuracy_m': accuracyMeters,
  };

  static FreeRunLocationSample? fromJson(Object? value) {
    if (value is! Map) return null;
    final latitude = double.tryParse('${value['latitude'] ?? ''}');
    final longitude = double.tryParse('${value['longitude'] ?? ''}');
    final recordedAt = DateTime.tryParse('${value['recorded_at'] ?? ''}');
    if (latitude == null || longitude == null || recordedAt == null) {
      return null;
    }
    return FreeRunLocationSample(
      latitude: latitude,
      longitude: longitude,
      recordedAt: recordedAt,
      elevationMeters: double.tryParse('${value['elevation_m'] ?? ''}'),
      accuracyMeters: double.tryParse('${value['accuracy_m'] ?? ''}'),
    );
  }
}

abstract interface class FreeRunLocationSource {
  bool get supportsBackgroundTracking;

  Future<FreeRunLocationState> prepare();

  Future<void> prepareBackgroundTracking();

  Future<FreeRunLocationSample> current();

  Stream<FreeRunLocationSample> watch({
    required String notificationTitle,
    required String notificationText,
    required String notificationChannelName,
  });

  Future<bool> openAppSettings();

  Future<bool> openLocationSettings();
}

class GeolocatorFreeRunLocationSource implements FreeRunLocationSource {
  const GeolocatorFreeRunLocationSource();

  @override
  bool get supportsBackgroundTracking =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.android;

  @override
  Future<FreeRunLocationState> prepare() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      return FreeRunLocationState.serviceDisabled;
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.deniedForever) {
      return FreeRunLocationState.permissionPermanentlyDenied;
    }
    if (permission == LocationPermission.denied) {
      return FreeRunLocationState.permissionDenied;
    }
    return FreeRunLocationState.ready;
  }

  @override
  Future<void> prepareBackgroundTracking() async {
    if (!supportsBackgroundTracking) return;
    final status = await Permission.notification.status;
    if (status.isDenied) await Permission.notification.request();
  }

  @override
  Future<FreeRunLocationSample> current() async {
    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        timeLimit: Duration(seconds: 20),
      ),
    );
    return _fromPosition(position);
  }

  @override
  Stream<FreeRunLocationSample> watch({
    required String notificationTitle,
    required String notificationText,
    required String notificationChannelName,
  }) {
    final LocationSettings settings;
    if (supportsBackgroundTracking) {
      settings = AndroidSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 5,
        intervalDuration: const Duration(seconds: 5),
        foregroundNotificationConfig: ForegroundNotificationConfig(
          notificationTitle: notificationTitle,
          notificationText: notificationText,
          notificationChannelName: notificationChannelName,
          notificationIcon: const AndroidResource(
            name: 'airmius_notification_icon',
            defType: 'drawable',
          ),
          enableWakeLock: true,
          setOngoing: true,
        ),
      );
    } else {
      settings = const LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 5,
      );
    }
    return Geolocator.getPositionStream(
      locationSettings: settings,
    ).map(_fromPosition);
  }

  @override
  Future<bool> openAppSettings() => Geolocator.openAppSettings();

  @override
  Future<bool> openLocationSettings() => Geolocator.openLocationSettings();

  static FreeRunLocationSample _fromPosition(Position position) {
    return FreeRunLocationSample(
      latitude: position.latitude,
      longitude: position.longitude,
      elevationMeters: position.altitude,
      accuracyMeters: position.accuracy,
      recordedAt: position.timestamp,
    );
  }
}

enum _FreeRunPhase { preparing, ready, recording, paused, saving, completed }

class FreeRunScreen extends StatefulWidget {
  const FreeRunScreen({
    super.key,
    this.client,
    this.locationSource,
    this.draftStore,
    this.showMapTiles = true,
  });

  final AirmiusApiClient? client;
  final FreeRunLocationSource? locationSource;
  final AirmiusFreeRunDraftStore? draftStore;
  final bool showMapTiles;

  @override
  State<FreeRunScreen> createState() => _FreeRunScreenState();
}

class _FreeRunScreenState extends State<FreeRunScreen>
    with WidgetsBindingObserver {
  final MapController _mapController = MapController();
  final List<FreeRunLocationSample> _points = [];
  final List<FreeRunLocationSample> _pendingPoints = [];

  late final FreeRunLocationSource _locationSource;
  late final AirmiusFreeRunDraftStore _draftStore;
  _FreeRunPhase _phase = _FreeRunPhase.preparing;
  FreeRunLocationState _locationState = FreeRunLocationState.unavailable;
  FreeRunLocationSample? _currentSample;
  StreamSubscription<FreeRunLocationSample>? _positionSubscription;
  Timer? _ticker;
  DateTime? _startedAt;
  DateTime? _activeSegmentStartedAt;
  DateTime _lastFlushAt = DateTime.fromMillisecondsSinceEpoch(0);
  Duration _finishedActiveDuration = Duration.zero;
  int? _trackId;
  int? _logId;
  int? _userId;
  double _distanceMeters = 0;
  double? _summaryDistanceMeters;
  bool _initialized = false;
  bool _mapReady = false;
  bool _followLocation = true;
  bool _flushing = false;
  bool _syncWarning = false;
  bool _backgroundPaused = false;
  String? _errorKey;

  AirmiusApiClient get _client {
    if (widget.client != null) return widget.client!;
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  Duration get _activeDuration {
    final segmentStartedAt = _activeSegmentStartedAt;
    if (_phase == _FreeRunPhase.recording && segmentStartedAt != null) {
      return _finishedActiveDuration +
          DateTime.now().difference(segmentStartedAt);
    }
    return _finishedActiveDuration;
  }

  double get _displayDistanceMeters =>
      _summaryDistanceMeters ?? _distanceMeters;

  bool get _hasActiveRun =>
      _phase == _FreeRunPhase.recording || _phase == _FreeRunPhase.paused;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _locationSource =
        widget.locationSource ?? const GeolocatorFreeRunLocationSource();
    _draftStore = widget.draftStore ?? AirmiusFreeRunDraftStore();
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted && _phase == _FreeRunPhase.recording) setState(() {});
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialized) return;
    _initialized = true;
    _userId = AirmiusServicesScope.of(context).authState.user?.id;
    unawaited(_prepare());
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed &&
        _phase == _FreeRunPhase.recording) {
      if (_locationSource.supportsBackgroundTracking) {
        unawaited(_saveDraft());
      } else {
        unawaited(_pause(background: true));
      }
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _ticker?.cancel();
    unawaited(_positionSubscription?.cancel());
    _mapController.dispose();
    super.dispose();
  }

  Future<void> _prepare() async {
    final userId = _userId;
    if (userId == null) {
      if (mounted) {
        setState(() {
          _phase = _FreeRunPhase.ready;
          _errorKey = 'freeRun.sessionRequired';
        });
      }
      return;
    }

    final draft = await _draftStore.read(userId: userId);
    if (draft != null) _restoreDraft(draft);

    if (!await _ensureAndroidBackgroundDisclosure()) return;

    try {
      final state = await _locationSource.prepare();
      if (!mounted) return;
      setState(() => _locationState = state);
      if (state == FreeRunLocationState.ready) {
        final sample = await _locationSource.current();
        if (!mounted) return;
        setState(() {
          _currentSample = sample;
          if (_phase == _FreeRunPhase.preparing) {
            _phase = _FreeRunPhase.ready;
          }
        });
        _moveMap(_points.isNotEmpty ? _points.last : sample);
      } else if (_phase == _FreeRunPhase.preparing) {
        setState(() => _phase = _FreeRunPhase.ready);
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _locationState = FreeRunLocationState.unavailable;
        if (_phase == _FreeRunPhase.preparing) _phase = _FreeRunPhase.ready;
      });
    }
  }

  void _restoreDraft(Map<String, dynamic> draft) {
    final trackId = int.tryParse('${draft['track_id'] ?? ''}');
    final startedAt = DateTime.tryParse('${draft['started_at'] ?? ''}');
    if (trackId == null || startedAt == null) return;

    final rawPoints = draft['points'];
    final restoredPoints = rawPoints is List
        ? rawPoints
              .map(FreeRunLocationSample.fromJson)
              .whereType<FreeRunLocationSample>()
              .toList()
        : <FreeRunLocationSample>[];
    if (restoredPoints.isEmpty) return;

    _trackId = trackId;
    _startedAt = startedAt;
    _finishedActiveDuration = Duration(
      seconds: int.tryParse('${draft['active_seconds'] ?? 0}') ?? 0,
    );
    _points
      ..clear()
      ..addAll(restoredPoints);
    _distanceMeters = _calculateDistance(restoredPoints);
    _currentSample = restoredPoints.last;
    _phase = _FreeRunPhase.paused;
  }

  Future<void> _retryLocation() async {
    setState(() {
      _locationState = FreeRunLocationState.unavailable;
      _errorKey = null;
      if (!_hasActiveRun) _phase = _FreeRunPhase.preparing;
    });
    if (await _ensureAndroidBackgroundDisclosure()) {
      await _prepareLocationOnly();
    }
  }

  Future<bool> _ensureAndroidBackgroundDisclosure() async {
    if (!_locationSource.supportsBackgroundTracking) return true;
    final userId = _userId;
    if (userId == null) return false;
    final accepted = await _draftStore.hasAcceptedAndroidBackgroundDisclosure(
      userId: userId,
    );
    if (accepted) return true;
    if (!mounted) return false;

    final confirmed = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        icon: const Icon(Icons.location_on_outlined),
        title: Text(
          AirmiusScope.of(dialogContext).t('freeRun.backgroundDisclosureTitle'),
        ),
        content: Text(
          AirmiusScope.of(dialogContext).t('freeRun.backgroundDisclosureBody'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(AirmiusScope.of(dialogContext).t('freeRun.notNow')),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(AirmiusScope.of(dialogContext).t('freeRun.enableGps')),
          ),
        ],
      ),
    );
    if (confirmed != true) {
      if (mounted) {
        setState(() {
          _locationState = FreeRunLocationState.permissionDenied;
          if (_phase == _FreeRunPhase.preparing) {
            _phase = _FreeRunPhase.ready;
          }
        });
      }
      return false;
    }

    await _draftStore.acceptAndroidBackgroundDisclosure(userId: userId);
    return true;
  }

  Future<void> _prepareLocationOnly() async {
    try {
      final state = await _locationSource.prepare();
      if (!mounted) return;
      setState(() => _locationState = state);
      if (state == FreeRunLocationState.ready) {
        final sample = await _locationSource.current();
        if (!mounted) return;
        setState(() {
          _currentSample = sample;
          if (_phase == _FreeRunPhase.preparing) _phase = _FreeRunPhase.ready;
        });
        _moveMap(sample);
      } else if (_phase == _FreeRunPhase.preparing) {
        setState(() => _phase = _FreeRunPhase.ready);
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _locationState = FreeRunLocationState.unavailable;
        if (_phase == _FreeRunPhase.preparing) _phase = _FreeRunPhase.ready;
      });
    }
  }

  Future<void> _start() async {
    if (_locationState != FreeRunLocationState.ready || _trackId != null) {
      return;
    }
    final title = AirmiusScope.of(context).t('freeRun.title');
    setState(() {
      _phase = _FreeRunPhase.preparing;
      _errorKey = null;
    });

    try {
      await _locationSource.prepareBackgroundTracking();
      final first = _currentSample ?? await _locationSource.current();
      final startedAt = DateTime.now();
      final response = await _client.createSportTrack({
        'title': title,
        'sport_type': 'running',
        'status': 'recording',
        'started_at': startedAt.toIso8601String(),
        'track_points': [first.toJson()],
      });
      final track = _responseData(response);
      final trackId = _asInt(track['id']);
      if (trackId == null) throw StateError('Missing track id');
      if (!mounted) return;

      setState(() {
        _trackId = trackId;
        _startedAt = startedAt;
        _activeSegmentStartedAt = DateTime.now();
        _finishedActiveDuration = Duration.zero;
        _points
          ..clear()
          ..add(first);
        _pendingPoints.clear();
        _distanceMeters = 0;
        _currentSample = first;
        _phase = _FreeRunPhase.recording;
      });
      _listenToPositions();
      await _saveDraft();
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _phase = _FreeRunPhase.ready;
        _errorKey = 'freeRun.startFailed';
      });
    }
  }

  void _listenToPositions() {
    unawaited(_positionSubscription?.cancel());
    final t = AirmiusScope.of(context).t;
    _positionSubscription = _locationSource
        .watch(
          notificationTitle: t('freeRun.notificationTitle'),
          notificationText: t('freeRun.notificationText'),
          notificationChannelName: t('freeRun.notificationChannel'),
        )
        .listen(
          _acceptSample,
          onError: (_) {
            if (!mounted) return;
            setState(() {
              _syncWarning = true;
              _errorKey = 'freeRun.gpsInterrupted';
            });
          },
        );
  }

  void _acceptSample(FreeRunLocationSample sample) {
    if (!mounted || _phase != _FreeRunPhase.recording) return;
    final accuracy = sample.accuracyMeters;
    if (accuracy != null && accuracy > 50) {
      setState(() => _currentSample = sample);
      return;
    }

    final previous = _points.isEmpty ? null : _points.last;
    if (previous != null) {
      final distance = _distanceBetween(previous, sample);
      final seconds =
          sample.recordedAt
              .difference(previous.recordedAt)
              .inMilliseconds
              .abs() /
          1000;
      if (distance < 3 || (seconds > 0 && distance / seconds > 12)) {
        setState(() => _currentSample = sample);
        return;
      }
      _distanceMeters += distance;
    }

    setState(() {
      _currentSample = sample;
      _points.add(sample);
      _pendingPoints.add(sample);
    });
    if (_followLocation) _moveMap(sample);

    final shouldFlush =
        _pendingPoints.length >= 5 ||
        DateTime.now().difference(_lastFlushAt) >= const Duration(seconds: 20);
    if (shouldFlush) unawaited(_flushPending());
    unawaited(_saveDraft());
  }

  Future<void> _pause({bool background = false}) async {
    if (_phase != _FreeRunPhase.recording) return;
    final segmentStartedAt = _activeSegmentStartedAt;
    if (segmentStartedAt != null) {
      _finishedActiveDuration += DateTime.now().difference(segmentStartedAt);
    }
    _activeSegmentStartedAt = null;
    await _positionSubscription?.cancel();
    _positionSubscription = null;
    if (!mounted) return;
    setState(() {
      _phase = _FreeRunPhase.paused;
      _backgroundPaused = background;
    });
    await _flushPending(force: true);
    final trackId = _trackId;
    if (trackId != null) {
      try {
        await _client.updateSportTrack(trackId, {'status': 'paused'});
      } catch (_) {
        if (mounted) setState(() => _syncWarning = true);
      }
    }
    await _saveDraft();
  }

  Future<void> _resume() async {
    final trackId = _trackId;
    if (_phase != _FreeRunPhase.paused ||
        trackId == null ||
        _locationState != FreeRunLocationState.ready) {
      return;
    }
    try {
      await _client.updateSportTrack(trackId, {'status': 'recording'});
      if (!mounted) return;
      setState(() {
        _activeSegmentStartedAt = DateTime.now();
        _phase = _FreeRunPhase.recording;
        _backgroundPaused = false;
        _errorKey = null;
      });
      _listenToPositions();
      await _saveDraft();
    } catch (_) {
      if (mounted) setState(() => _errorKey = 'freeRun.resumeFailed');
    }
  }

  Future<void> _flushPending({bool force = false}) async {
    if (_flushing || _pendingPoints.isEmpty || _trackId == null) return;
    if (!force &&
        _pendingPoints.length < 5 &&
        DateTime.now().difference(_lastFlushAt) < const Duration(seconds: 20)) {
      return;
    }

    _flushing = true;
    final batch = List<FreeRunLocationSample>.from(_pendingPoints);
    _pendingPoints.removeRange(0, batch.length);
    try {
      await _client.appendSportTrackPoints(
        _trackId!,
        batch.map((point) => point.toJson()).toList(),
      );
      _lastFlushAt = DateTime.now();
      if (mounted) setState(() => _syncWarning = false);
    } catch (_) {
      _pendingPoints.insertAll(0, batch);
      if (mounted) setState(() => _syncWarning = true);
    } finally {
      _flushing = false;
    }
  }

  Future<void> _finish() async {
    if (_phase == _FreeRunPhase.recording) await _pause();
    if (!mounted || _phase != _FreeRunPhase.paused) return;
    final result = await _showFinishSheet();
    if (result == null || !mounted) return;
    await _finalize(result);
  }

  Future<Map<String, dynamic>?> _showFinishSheet() {
    var rpe = 5.0;
    var privacy = 'trainer';
    final notesController = TextEditingController();
    final t = AirmiusScope.of(context).t;
    return showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => SafeArea(
          child: SingleChildScrollView(
            padding: EdgeInsets.fromLTRB(
              20,
              0,
              20,
              20 + MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  t('freeRun.finishTitle'),
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 6),
                Text(t('freeRun.finishHint')),
                const SizedBox(height: 18),
                Text('${t('freeRun.effort')}: ${rpe.round()}/10'),
                Slider(
                  value: rpe,
                  min: 1,
                  max: 10,
                  divisions: 9,
                  label: '${rpe.round()}',
                  onChanged: (value) => setSheetState(() => rpe = value),
                ),
                const SizedBox(height: 8),
                DropdownButtonFormField<String>(
                  initialValue: privacy,
                  decoration: InputDecoration(
                    labelText: t('freeRun.visibility'),
                  ),
                  items: [
                    DropdownMenuItem(
                      value: 'private',
                      child: Text(t('freeRun.visibility.private')),
                    ),
                    DropdownMenuItem(
                      value: 'trainer',
                      child: Text(t('freeRun.visibility.trainer')),
                    ),
                    DropdownMenuItem(
                      value: 'team',
                      child: Text(t('freeRun.visibility.team')),
                    ),
                  ],
                  onChanged: (value) {
                    if (value != null) setSheetState(() => privacy = value);
                  },
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: notesController,
                  maxLines: 3,
                  maxLength: 5000,
                  decoration: InputDecoration(
                    labelText: t('freeRun.notes'),
                    hintText: t('freeRun.notesHint'),
                    alignLabelWithHint: true,
                  ),
                ),
                const SizedBox(height: 8),
                FilledButton.icon(
                  onPressed: () => Navigator.of(sheetContext).pop({
                    'rpe': rpe.round(),
                    'privacy': privacy,
                    'notes': notesController.text.trim(),
                  }),
                  icon: const Icon(Icons.save_outlined),
                  label: Text(t('freeRun.saveRun')),
                ),
              ],
            ),
          ),
        ),
      ),
    ).whenComplete(notesController.dispose);
  }

  Future<void> _finalize(Map<String, dynamic> result) async {
    final trackId = _trackId;
    final startedAt = _startedAt;
    if (trackId == null || startedAt == null) return;
    final t = AirmiusScope.of(context).t;
    setState(() {
      _phase = _FreeRunPhase.saving;
      _errorKey = null;
    });

    try {
      await _flushPending(force: true);
      if (_pendingPoints.isNotEmpty) {
        throw StateError('Pending points could not be synchronized');
      }
      final durationSeconds = math.max(0, _activeDuration.inSeconds);
      final trackResponse = await _client.completeSportTrack(
        trackId,
        activeDurationSeconds: durationSeconds,
      );
      final track = _responseData(trackResponse);
      final distanceMeters =
          double.tryParse('${track['distance_meters'] ?? ''}') ??
          _distanceMeters;
      final durationMinutes = durationSeconds == 0
          ? 0
          : math.max(1, (durationSeconds / 60).ceil());
      final rpe = _asInt(result['rpe']) ?? 5;
      final logResponse = await _client.createTrainingLog({
        'sport_route_track_id': trackId,
        'title': t('freeRun.title'),
        'sport_type': 'running',
        'status': 'completed',
        'performed_at': startedAt.toIso8601String(),
        'duration_minutes': durationMinutes,
        'distance_km': double.parse((distanceMeters / 1000).toStringAsFixed(3)),
        'intensity': rpe <= 3 ? 'locker' : (rpe >= 8 ? 'hart' : 'mittel'),
        'privacy_scope': result['privacy'],
        'wellness': {'rpe': rpe},
        if ('${result['notes'] ?? ''}'.trim().isNotEmpty)
          'notes': '${result['notes']}'.trim(),
        'entries': [
          {
            'title': t('freeRun.runningEntry'),
            'duration_minutes': durationSeconds / 60,
            'distance_km': double.parse(
              (distanceMeters / 1000).toStringAsFixed(3),
            ),
            'tracking_mode': 'distance',
            'completed': true,
          },
        ],
      });
      final log = _responseData(logResponse);
      final logId = _asInt(log['id']);
      if (logId == null) throw StateError('Missing training log id');
      final userId = _userId;
      if (userId != null) await _draftStore.clear(userId: userId);
      if (!mounted) return;
      setState(() {
        _logId = logId;
        _summaryDistanceMeters = distanceMeters;
        _phase = _FreeRunPhase.completed;
      });
      _fitTrack();
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _phase = _FreeRunPhase.paused;
        _errorKey = 'freeRun.saveFailed';
      });
      await _saveDraft();
    }
  }

  Future<void> _saveDraft() async {
    final userId = _userId;
    final trackId = _trackId;
    final startedAt = _startedAt;
    if (userId == null || trackId == null || startedAt == null) return;
    await _draftStore.write(
      userId: userId,
      payload: {
        'track_id': trackId,
        'started_at': startedAt.toIso8601String(),
        'active_seconds': _activeDuration.inSeconds,
        'status': _phase == _FreeRunPhase.recording ? 'recording' : 'paused',
        'points': _points.map((point) => point.toJson()).toList(),
      },
    );
  }

  Future<void> _discard({bool ask = true}) async {
    if (ask) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(AirmiusScope.of(context).t('freeRun.discardTitle')),
          content: Text(AirmiusScope.of(context).t('freeRun.discardBody')),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: Text(AirmiusScope.of(context).t('freeRun.keepRun')),
            ),
            TextButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: Text(AirmiusScope.of(context).t('freeRun.discard')),
            ),
          ],
        ),
      );
      if (confirmed != true) return;
    }

    if (_phase == _FreeRunPhase.recording) await _pause();
    final trackId = _trackId;
    if (trackId != null) {
      try {
        await _client.deleteSportTrack(trackId);
      } catch (_) {
        if (mounted) setState(() => _errorKey = 'freeRun.discardFailed');
        return;
      }
    }
    final userId = _userId;
    if (userId != null) await _draftStore.clear(userId: userId);
    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _handleBack() async {
    if (!_hasActiveRun) {
      Navigator.of(context).pop();
      return;
    }
    if (_phase == _FreeRunPhase.recording) await _pause();
    if (!mounted) return;
    final action = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(AirmiusScope.of(context).t('freeRun.leaveTitle')),
        content: Text(AirmiusScope.of(context).t('freeRun.leaveBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop('continue'),
            child: Text(AirmiusScope.of(context).t('freeRun.keepRun')),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop('discard'),
            child: Text(AirmiusScope.of(context).t('freeRun.discard')),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop('leave'),
            child: Text(AirmiusScope.of(context).t('freeRun.leavePaused')),
          ),
        ],
      ),
    );
    if (!mounted) return;
    if (action == 'discard') {
      await _discard(ask: false);
    } else if (action == 'leave') {
      Navigator.of(context).pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final canPop = !_hasActiveRun;
    return PopScope<FreeRunResult>(
      canPop: canPop,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) unawaited(_handleBack());
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(t('freeRun.title')),
          actions: [
            if (_hasActiveRun)
              IconButton(
                tooltip: t('freeRun.discard'),
                onPressed: _phase == _FreeRunPhase.saving ? null : _discard,
                icon: const Icon(Icons.delete_outline),
              ),
          ],
        ),
        body: SafeArea(
          top: false,
          child: Column(
            children: [
              Expanded(child: _buildMap(t)),
              _buildControlSurface(t),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildMap(String Function(String) t) {
    final center =
        _currentSample?.latLng ??
        (_points.isNotEmpty
            ? _points.last.latLng
            : const LatLng(52.52, 13.405));
    final colorScheme = Theme.of(context).colorScheme;
    return Stack(
      fit: StackFit.expand,
      children: [
        FlutterMap(
          mapController: _mapController,
          options: MapOptions(
            initialCenter: center,
            initialZoom: 16,
            minZoom: 3,
            maxZoom: 19,
            backgroundColor: colorScheme.surfaceContainerHighest,
            onMapReady: () {
              _mapReady = true;
              if (_points.length > 1 && _phase == _FreeRunPhase.completed) {
                _fitTrack();
              } else if (_currentSample != null) {
                _moveMap(_currentSample!);
              }
            },
            onPositionChanged: (_, hasGesture) {
              if (hasGesture && _followLocation && mounted) {
                setState(() => _followLocation = false);
              }
            },
          ),
          children: [
            if (widget.showMapTiles)
              TileLayer(
                urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                userAgentPackageName: 'com.airmius.app',
                maxZoom: 19,
              ),
            if (_points.length > 1)
              PolylineLayer(
                polylines: [
                  Polyline(
                    points: _points.map((point) => point.latLng).toList(),
                    strokeWidth: 6,
                    color: colorScheme.primary,
                    borderStrokeWidth: 2,
                    borderColor: colorScheme.surface,
                  ),
                ],
              ),
            if (_currentSample != null)
              MarkerLayer(
                markers: [
                  Marker(
                    point: _currentSample!.latLng,
                    width: 34,
                    height: 34,
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: colorScheme.primary,
                        border: Border.all(color: Colors.white, width: 3),
                        boxShadow: const [
                          BoxShadow(color: Colors.black38, blurRadius: 8),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            const RichAttributionWidget(
              attributions: [
                TextSourceAttribution('OpenStreetMap contributors'),
              ],
            ),
          ],
        ),
        PositionedDirectional(
          top: 12,
          start: 12,
          end: 64,
          child: _MapStatusBanner(
            icon: _statusIcon,
            color: _statusColor(colorScheme),
            label: _statusLabel(t),
          ),
        ),
        PositionedDirectional(
          end: 12,
          bottom: 14,
          child: FloatingActionButton.small(
            heroTag: 'free-run-recenter',
            tooltip: t('freeRun.recenter'),
            onPressed: _currentSample == null
                ? null
                : () {
                    setState(() => _followLocation = true);
                    _moveMap(_currentSample!);
                  },
            child: const Icon(Icons.my_location),
          ),
        ),
      ],
    );
  }

  Widget _buildControlSurface(String Function(String) t) {
    final colorScheme = Theme.of(context).colorScheme;
    return Material(
      color: colorScheme.surface,
      elevation: 8,
      child: AnimatedSize(
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
          child: switch (_phase) {
            _FreeRunPhase.preparing => _buildPreparing(t),
            _FreeRunPhase.ready => _buildReady(t),
            _FreeRunPhase.recording ||
            _FreeRunPhase.paused => _buildLiveControls(t),
            _FreeRunPhase.saving => _buildSaving(t),
            _FreeRunPhase.completed => _buildCompleted(t),
          },
        ),
      ),
    );
  }

  Widget _buildPreparing(String Function(String) t) {
    return SizedBox(
      height: 154,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const CircularProgressIndicator(),
          const SizedBox(height: 16),
          Text(t('freeRun.preparingGps')),
        ],
      ),
    );
  }

  Widget _buildReady(String Function(String) t) {
    final locationReady = _locationState == FreeRunLocationState.ready;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          t('freeRun.readyTitle'),
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 4),
        Text(
          _errorKey == null
              ? t(
                  _locationSource.supportsBackgroundTracking
                      ? 'freeRun.androidReadyHint'
                      : 'freeRun.readyHint',
                )
              : t(_errorKey!),
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: 14),
        if (!locationReady) _buildLocationAction(t),
        if (locationReady)
          SizedBox(
            height: 56,
            child: FilledButton.icon(
              onPressed: _start,
              icon: const Icon(Icons.play_arrow_rounded),
              label: Text(t('freeRun.start')),
            ),
          ),
      ],
    );
  }

  Widget _buildLocationAction(String Function(String) t) {
    final permanentlyDenied =
        _locationState == FreeRunLocationState.permissionPermanentlyDenied;
    final serviceDisabled =
        _locationState == FreeRunLocationState.serviceDisabled;
    return SizedBox(
      height: 52,
      child: OutlinedButton.icon(
        onPressed: () async {
          if (permanentlyDenied) {
            await _locationSource.openAppSettings();
          } else if (serviceDisabled) {
            await _locationSource.openLocationSettings();
          }
          await _retryLocation();
        },
        icon: Icon(
          serviceDisabled ? Icons.location_off_outlined : Icons.gps_fixed,
        ),
        label: Text(
          t(
            permanentlyDenied
                ? 'freeRun.openSettings'
                : serviceDisabled
                ? 'freeRun.openLocationSettings'
                : 'freeRun.retryGps',
          ),
        ),
      ),
    );
  }

  Widget _buildLiveControls(String Function(String) t) {
    final paused = _phase == _FreeRunPhase.paused;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: _RunMetric(
                label: t('freeRun.time'),
                value: _formatDuration(_activeDuration),
              ),
            ),
            Expanded(
              child: _RunMetric(
                label: t('freeRun.distance'),
                value:
                    '${(_displayDistanceMeters / 1000).toStringAsFixed(2)} km',
              ),
            ),
            Expanded(
              child: _RunMetric(
                label: t('freeRun.pace'),
                value: _formatPace(_activeDuration, _displayDistanceMeters),
              ),
            ),
          ],
        ),
        if (_backgroundPaused || _syncWarning || _errorKey != null) ...[
          const SizedBox(height: 12),
          Text(
            t(
              _backgroundPaused
                  ? 'freeRun.backgroundPaused'
                  : _errorKey ?? 'freeRun.syncWarning',
            ),
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: Theme.of(context).colorScheme.error,
            ),
          ),
        ],
        const SizedBox(height: 14),
        Row(
          children: [
            Expanded(
              child: SizedBox(
                height: 58,
                child: FilledButton.tonalIcon(
                  onPressed: paused ? _resume : _pause,
                  icon: Icon(
                    paused ? Icons.play_arrow_rounded : Icons.pause_rounded,
                  ),
                  label: Text(t(paused ? 'freeRun.resume' : 'freeRun.pause')),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: SizedBox(
                height: 58,
                child: FilledButton.icon(
                  onPressed: _finish,
                  icon: const Icon(Icons.stop_rounded),
                  label: Text(t('freeRun.finish')),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildSaving(String Function(String) t) {
    return SizedBox(
      height: 154,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const CircularProgressIndicator(),
          const SizedBox(height: 16),
          Text(t('freeRun.saving')),
        ],
      ),
    );
  }

  Widget _buildCompleted(String Function(String) t) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Icon(
              Icons.check_circle,
              color: Theme.of(context).colorScheme.secondary,
              size: 28,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                t('freeRun.savedTitle'),
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        Row(
          children: [
            Expanded(
              child: _RunMetric(
                label: t('freeRun.time'),
                value: _formatDuration(_activeDuration),
              ),
            ),
            Expanded(
              child: _RunMetric(
                label: t('freeRun.distance'),
                value:
                    '${(_displayDistanceMeters / 1000).toStringAsFixed(2)} km',
              ),
            ),
            Expanded(
              child: _RunMetric(
                label: t('freeRun.pace'),
                value: _formatPace(_activeDuration, _displayDistanceMeters),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        SizedBox(
          height: 54,
          child: FilledButton.icon(
            onPressed: () => Navigator.of(
              context,
            ).pop(FreeRunResult(logId: _logId!, trackId: _trackId!)),
            icon: const Icon(Icons.check),
            label: Text(t('freeRun.done')),
          ),
        ),
      ],
    );
  }

  IconData get _statusIcon {
    if (_phase == _FreeRunPhase.recording) return Icons.gps_fixed;
    if (_phase == _FreeRunPhase.paused) return Icons.pause_circle_outline;
    if (_phase == _FreeRunPhase.completed) return Icons.check_circle_outline;
    return switch (_locationState) {
      FreeRunLocationState.ready => Icons.gps_fixed,
      FreeRunLocationState.serviceDisabled => Icons.location_off_outlined,
      FreeRunLocationState.permissionDenied ||
      FreeRunLocationState.permissionPermanentlyDenied =>
        Icons.gps_off_outlined,
      FreeRunLocationState.unavailable => Icons.gps_not_fixed,
    };
  }

  Color _statusColor(ColorScheme colorScheme) {
    if (_phase == _FreeRunPhase.recording) return colorScheme.secondary;
    if (_phase == _FreeRunPhase.paused) return colorScheme.tertiary;
    if (_locationState == FreeRunLocationState.ready) {
      return colorScheme.primary;
    }
    return colorScheme.error;
  }

  String _statusLabel(String Function(String) t) {
    if (_phase == _FreeRunPhase.recording) {
      final accuracy = _currentSample?.accuracyMeters;
      if (accuracy != null && accuracy > 50) return t('freeRun.gpsWeak');
      return t('freeRun.recording');
    }
    if (_phase == _FreeRunPhase.paused) return t('freeRun.paused');
    if (_phase == _FreeRunPhase.completed) return t('freeRun.saved');
    return switch (_locationState) {
      FreeRunLocationState.ready => t('freeRun.gpsReady'),
      FreeRunLocationState.serviceDisabled => t('freeRun.locationDisabled'),
      FreeRunLocationState.permissionDenied => t('freeRun.permissionDenied'),
      FreeRunLocationState.permissionPermanentlyDenied => t(
        'freeRun.permissionPermanentlyDenied',
      ),
      FreeRunLocationState.unavailable => t('freeRun.gpsUnavailable'),
    };
  }

  void _moveMap(FreeRunLocationSample sample) {
    if (!_mapReady) return;
    _mapController.move(
      sample.latLng,
      math.max(16, _mapController.camera.zoom),
    );
  }

  void _fitTrack() {
    if (!_mapReady || _points.length < 2) return;
    _mapController.fitCamera(
      CameraFit.bounds(
        bounds: LatLngBounds.fromPoints(
          _points.map((point) => point.latLng).toList(),
        ),
        padding: const EdgeInsets.all(44),
      ),
    );
  }

  static Map<String, dynamic> _responseData(Map<String, dynamic> response) {
    final data = response['data'];
    return data is Map ? Map<String, dynamic>.from(data) : response;
  }

  static int? _asInt(Object? value) => int.tryParse('${value ?? ''}');

  static double _calculateDistance(List<FreeRunLocationSample> points) {
    var total = 0.0;
    for (var index = 1; index < points.length; index++) {
      total += _distanceBetween(points[index - 1], points[index]);
    }
    return total;
  }

  static double _distanceBetween(
    FreeRunLocationSample from,
    FreeRunLocationSample to,
  ) {
    return Geolocator.distanceBetween(
      from.latitude,
      from.longitude,
      to.latitude,
      to.longitude,
    );
  }

  static String _formatDuration(Duration duration) {
    final hours = duration.inHours;
    final minutes = duration.inMinutes.remainder(60).toString().padLeft(2, '0');
    final seconds = duration.inSeconds.remainder(60).toString().padLeft(2, '0');
    return hours > 0 ? '$hours:$minutes:$seconds' : '$minutes:$seconds';
  }

  static String _formatPace(Duration duration, double distanceMeters) {
    if (distanceMeters < 50 || duration.inSeconds <= 0) return '--:--';
    final secondsPerKm = (duration.inSeconds / (distanceMeters / 1000)).round();
    final minutes = secondsPerKm ~/ 60;
    final seconds = (secondsPerKm % 60).toString().padLeft(2, '0');
    return '$minutes:$seconds /km';
  }
}

class _RunMetric extends StatelessWidget {
  const _RunMetric({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 66,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.fade,
            softWrap: false,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelMedium,
          ),
        ],
      ),
    );
  }
}

class _MapStatusBanner extends StatelessWidget {
  const _MapStatusBanner({
    required this.icon,
    required this.color,
    required this.label,
  });

  final IconData icon;
  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: AlignmentDirectional.centerStart,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface.withValues(alpha: 0.94),
          borderRadius: BorderRadius.circular(8),
          boxShadow: const [BoxShadow(color: Colors.black26, blurRadius: 8)],
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 18, color: color),
              const SizedBox(width: 7),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelLarge,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
