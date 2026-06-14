import 'dart:async';
import 'dart:convert';

import 'airmius_api_client.dart';
import 'airmius_api_repositories.dart';
import 'airmius_auth_state.dart';
import 'airmius_secure_token_store.dart';

class AirmiusAppEnvironment {
  const AirmiusAppEnvironment({
    required this.apiBaseUrl,
    this.locale = 'de',
    this.enableOfflineQueue = true,
    this.requestTimeout = const Duration(seconds: 20),
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
  })  : tokenStore = tokenStore ?? AirmiusSecureTokenStore(),
        transport = environment.enableOfflineQueue ? AirmiusQueuedTransport(inner: transport, timeout: environment.requestTimeout) : transport {
    authState = AirmiusAuthState(tokenStore: this.tokenStore, clientFactory: clientForSession);
  }

  final AirmiusAppEnvironment environment;
  final AirmiusApiTransport transport;
  final AirmiusTokenStore tokenStore;
  late final AirmiusAuthState authState;

  AirmiusApiClient clientForSession(AirmiusSession? session) => AirmiusApiClient(
        transport: transport,
        baseUrl: environment.apiBaseUrl,
        token: session?.token,
        locale: session?.locale ?? environment.locale,
      );

  AirmiusRepositoryBundle repositoriesFor(AirmiusSession? session) => AirmiusRepositoryBundle.api(clientForSession(session));

  AirmiusRepositoryBundle get repositories => repositoriesFor(authState.session);
}

class AirmiusQueuedTransport implements AirmiusApiTransport {
  AirmiusQueuedTransport({
    required this.inner,
    required this.timeout,
    this.maxRetries = 2,
  });

  final AirmiusApiTransport inner;
  final Duration timeout;
  final int maxRetries;
  final List<AirmiusQueuedRequest> queue = [];

  bool offline = false;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (offline) {
      final queued = AirmiusQueuedRequest(request: request, queuedAt: DateTime.now());
      queue.add(queued);
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

  String _transportErrorMessage(Object? error) {
    if (error is AirmiusApiException) return error.userMessage;

    final text = error?.toString() ?? '';
    if (text.contains('ProgressEvent') || text.contains('[object')) {
      return 'Die API ist nicht erreichbar. Bitte pruefe AIRMIUS_API_BASE_URL, CORS und ob Laravel/XAMPP laeuft.';
    }

    if (text.isEmpty) {
      return 'Die API ist nicht erreichbar. Bitte pruefe die Verbindung zum Server.';
    }

    return text;
  }

  Future<List<AirmiusApiResponse>> flush() async {
    final pending = List<AirmiusQueuedRequest>.from(queue);
    queue.clear();
    final responses = <AirmiusApiResponse>[];
    for (final item in pending) {
      responses.add(await send(item.request));
    }
    return responses;
  }
}

class AirmiusQueuedRequest {
  const AirmiusQueuedRequest({
    required this.request,
    required this.queuedAt,
  });

  final AirmiusApiRequest request;
  final DateTime queuedAt;
}

class AirmiusStaticTransport implements AirmiusApiTransport {
  const AirmiusStaticTransport(this.responses);

  final Map<String, AirmiusApiResponse> responses;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    final key = '${request.method} ${request.path}';
    return responses[key] ?? const AirmiusApiResponse(statusCode: 404, body: '{"error":"not_found"}');
  }
}
