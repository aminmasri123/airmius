import 'dart:async';
import 'dart:convert';

import 'airmius_api_client.dart';
import 'airmius_firebase_push_token_provider.dart';
import 'airmius_api_repositories.dart';
import 'airmius_auth_state.dart';
import 'airmius_preferences_store.dart';
import 'airmius_push_device_registry.dart';
import 'airmius_secure_token_store.dart';

class AirmiusAppEnvironment {
  const AirmiusAppEnvironment({
    required this.apiBaseUrl,
    this.locale = 'de',
    this.enableOfflineQueue = true,
    this.requestTimeout = const Duration(seconds: 45),
  });

  final String apiBaseUrl;
  final String locale;
  final bool enableOfflineQueue;
  final Duration requestTimeout;
}

class AirmiusServiceContainer {
  AirmiusServiceContainer({
    required this.environment,
    required AirmiusApiTransport transport,
    AirmiusTokenStore? tokenStore,
    AirmiusPreferencesStore? offlineQueueStore,
    AirmiusPreferencesStore? pushDeviceStore,
    AirmiusPushTokenProvider? pushTokenProvider,
  }) : tokenStore = tokenStore ?? AirmiusSecureTokenStore(),
       pushDevices = AirmiusPushDeviceRegistry(
         store: pushDeviceStore ?? createAirmiusPreferencesStore(),
         tokenProvider: pushTokenProvider ?? AirmiusFirebasePushTokenProvider(),
         locale: environment.locale,
       ),
       transport = environment.enableOfflineQueue
           ? AirmiusQueuedTransport(
               inner: transport,
               timeout: environment.requestTimeout,
               store: offlineQueueStore ?? createAirmiusPreferencesStore(),
             )
           : transport {
    authState = AirmiusAuthState(
      tokenStore: this.tokenStore,
      clientFactory: clientForSession,
      onAuthenticated: (session) =>
          pushDevices.registerIfOptedIn(clientForSession(session)),
      onBeforeSignOut: (session) =>
          pushDevices.unregister(clientForSession(session)),
    );
  }

  final AirmiusAppEnvironment environment;
  final AirmiusApiTransport transport;
  final AirmiusTokenStore tokenStore;
  final AirmiusPushDeviceRegistry pushDevices;
  late final AirmiusAuthState authState;

  AirmiusApiClient clientForSession(AirmiusSession? session) =>
      AirmiusApiClient(
        transport: transport,
        baseUrl: environment.apiBaseUrl,
        token: session?.token,
        locale: session?.locale ?? environment.locale,
      );

  AirmiusRepositoryBundle repositoriesFor(AirmiusSession? session) =>
      AirmiusRepositoryBundle.api(clientForSession(session));

  AirmiusRepositoryBundle get repositories =>
      repositoriesFor(authState.session);
}

class AirmiusQueuedTransport implements AirmiusApiTransport {
  AirmiusQueuedTransport({
    required this.inner,
    required this.timeout,
    this.store,
    this.storageKey = defaultStorageKey,
    this.maxRetries = 2,
  });

  static const defaultStorageKey = 'airmius.offline.queue.v1';

  final AirmiusApiTransport inner;
  final Duration timeout;
  final AirmiusPreferencesStore? store;
  final String storageKey;
  final int maxRetries;
  final List<AirmiusQueuedRequest> queue = [];

  bool offline = false;
  bool _queueRestored = false;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    await _restoreQueue();

    if (offline) {
      if (_isSensitiveRequest(request)) {
        return const AirmiusApiResponse(
          statusCode: 503,
          body:
              '{"error":"offline_sensitive_request","message":"Diese Aktion benötigt eine aktive Verbindung und wird aus Sicherheitsgründen nicht offline gespeichert."}',
        );
      }
      final queued = AirmiusQueuedRequest(
        request: request,
        queuedAt: DateTime.now(),
      );
      queue.add(queued);
      await _persistQueue();
      return const AirmiusApiResponse(statusCode: 202, body: '{"queued":true}');
    }

    var attempt = 0;
    Object? lastError;
    while (attempt <= maxRetries) {
      try {
        return await inner.send(request).timeout(timeout);
      } catch (error) {
        lastError = error;
        attempt += 1;
      }
    }

    return AirmiusApiResponse(
      statusCode: 599,
      body: jsonEncode({
        'error': 'transport_failed',
        'message': _transportErrorMessage(lastError),
      }),
    );
  }

  /// Passwords, authentication challenges, account deletion and session
  /// revocation must never be persisted in the offline queue. Besides being
  /// non-idempotent, these requests can contain credentials or one-time codes.
  bool _isSensitiveRequest(AirmiusApiRequest request) {
    final path = request.path.toLowerCase();
    return path.startsWith('/api/v1/auth/') ||
        path.startsWith('/api/v1/account') ||
        path.contains('/password') ||
        path.contains('/two-factor') ||
        path.contains('/two-factor-recovery') ||
        path.contains('/sessions') ||
        path.contains('/email/verification') ||
        path.endsWith('/share');
  }

  String _transportErrorMessage(Object? error) {
    if (error is AirmiusApiException) return error.userMessage;

    final text = error?.toString() ?? '';
    if (text.contains('ProgressEvent') || text.contains('[object')) {
      return 'Die API ist nicht erreichbar. Bitte prüfe AIRMIUS_API_BASE_URL, CORS und ob Laravel/XAMPP läuft.';
    }

    if (text.isEmpty) {
      return 'Die API ist nicht erreichbar. Bitte prüfe die Verbindung zum Server.';
    }

    return text;
  }

  Future<List<AirmiusApiResponse>> flush() async {
    await _restoreQueue();

    final pending = List<AirmiusQueuedRequest>.from(queue);
    queue.clear();
    await _persistQueue();

    final responses = <AirmiusApiResponse>[];
    for (final item in pending) {
      final response = await send(item.request);
      responses.add(response);
      if (response.statusCode == 599) {
        queue.add(item);
      }
    }
    await _persistQueue();
    return responses;
  }

  Future<void> _restoreQueue() async {
    if (_queueRestored) return;
    _queueRestored = true;

    final queueStore = store;
    if (queueStore == null) return;

    try {
      final raw = await queueStore.readString(storageKey);
      if (raw == null || raw.trim().isEmpty) return;

      final decoded = jsonDecode(raw);
      if (decoded is! List) return;

      queue
        ..clear()
        ..addAll(
          decoded
              .whereType<Map>()
              .map(AirmiusQueuedRequest.fromJson)
              .whereType<AirmiusQueuedRequest>(),
        );
    } catch (_) {
      queue.clear();
    }
  }

  Future<void> _persistQueue() async {
    final queueStore = store;
    if (queueStore == null) return;

    await queueStore.writeString(
      storageKey,
      jsonEncode(queue.map((item) => item.toJson()).toList()),
    );
  }
}

class AirmiusQueuedRequest {
  const AirmiusQueuedRequest({required this.request, required this.queuedAt});

  final AirmiusApiRequest request;
  final DateTime queuedAt;

  factory AirmiusQueuedRequest.fromJson(Map<dynamic, dynamic> json) {
    final request = json['request'];
    return AirmiusQueuedRequest(
      request: AirmiusApiRequest(
        method: '${(request as Map)['method']}',
        path: '${request['path']}',
        body: request['body'] is Map
            ? Map<String, dynamic>.from(request['body'] as Map)
            : null,
        query: request['query'] is Map
            ? Map<String, String>.from(
                (request['query'] as Map).map(
                  (key, value) => MapEntry('$key', '$value'),
                ),
              )
            : const {},
        headers: request['headers'] is Map
            ? Map<String, String>.from(
                (request['headers'] as Map).map(
                  (key, value) => MapEntry('$key', '$value'),
                ),
              )
            : const {},
      ),
      queuedAt: DateTime.tryParse('${json['queued_at']}') ?? DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() => {
    'queued_at': queuedAt.toIso8601String(),
    'request': {
      'method': request.method,
      'path': request.path,
      if (request.body != null) 'body': request.body,
      if (request.query.isNotEmpty) 'query': request.query,
      if (request.headers.isNotEmpty) 'headers': request.headers,
    },
  };
}

class AirmiusStaticTransport implements AirmiusApiTransport {
  const AirmiusStaticTransport(this.responses);

  final Map<String, AirmiusApiResponse> responses;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    final key = '${request.method} ${request.path}';
    return responses[key] ??
        const AirmiusApiResponse(
          statusCode: 404,
          body: '{"error":"not_found"}',
        );
  }
}
