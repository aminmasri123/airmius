import 'dart:async';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_service_container.dart';

void main() {
  late _Transport server;
  late AirmiusMemoryTokenStore store;
  late AirmiusAuthState auth;
  AirmiusApiClient client(AirmiusSession? session) => AirmiusApiClient(
    transport: AirmiusSessionTransport(
      inner: server,
      onUnauthorized: auth.expireSession,
    ),
    baseUrl: 'https://airmius.test',
    token: session?.token,
  );
  setUp(() async {
    server = _Transport();
    store = AirmiusMemoryTokenStore();
    auth = AirmiusAuthState(tokenStore: store, clientFactory: client);
    await auth.signInWithToken(token: 'phone');
  });
  tearDown(() => auth.dispose());

  test(
    'a revoked device clears credentials on any authenticated request',
    () async {
      server.status = 401;
      await expectLater(
        client(auth.session).settings(),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(auth.phase, AirmiusAuthPhase.expired);
      expect(auth.session, isNull);
      expect(await store.read(), isNull);
    },
  );
  test('heartbeat expires a revoked session without a user action', () async {
    server.status = 401;
    await auth.validateSession();
    expect(auth.phase, AirmiusAuthPhase.expired);
    expect(await store.read(), isNull);
  });
  test(
    'offline, forbidden and server failures do not sign the user out',
    () async {
      for (final status in [403, 503, 599]) {
        server.status = status;
        await auth.validateSession();
        expect(auth.isAuthenticated, isTrue);
        expect((await store.read())?.token, 'phone');
      }
    },
  );
  test('restore does not revive a revoked token', () async {
    server.status = 401;
    await auth.restore();
    expect(auth.phase, AirmiusAuthPhase.expired);
    expect(auth.session, isNull);
    expect(await store.read(), isNull);
  });
  test(
    'a late response for an old token does not revoke a new login',
    () async {
      final oldClient = client(auth.session);
      final pending = Completer<AirmiusApiResponse>();
      server.pending = pending;
      final request = oldClient.settings();
      server.pending = null;
      await auth.signInWithToken(token: 'new-phone');
      pending.complete(const AirmiusApiResponse(statusCode: 401, body: '{}'));
      await expectLater(request, throwsA(isA<AirmiusApiException>()));
      expect(auth.session?.token, 'new-phone');
      expect((await store.read())?.token, 'new-phone');
    },
  );
}

class _Transport implements AirmiusApiTransport {
  int status = 200;
  Completer<AirmiusApiResponse>? pending;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (pending != null) return pending!.future;
    return AirmiusApiResponse(
      statusCode: status,
      body: status == 200
          ? '{"data":{"id":1,"name":"Tester","email":"test@example.test"}}'
          : '{"message":"Unauthenticated."}',
    );
  }
}
