import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_auth_state.dart';

void main() {
  for (final status in [401, 429, 500, 599]) {
    test(
      'social login does not save a session when profile returns $status',
      () async {
        final store = AirmiusMemoryTokenStore();
        var authenticated = false;
        final transport = _ProfileTransport(status);
        final auth = AirmiusAuthState(
          tokenStore: store,
          clientFactory: (session) => AirmiusApiClient(
            transport: transport,
            baseUrl: 'https://airmius.test',
            token: session?.token,
          ),
          onAuthenticated: (_) async {
            authenticated = true;
          },
        );
        addTearDown(auth.dispose);

        await auth.signInWithToken(token: 'test-social-token');

        expect(transport.request?.path, '/api/v1/me');
        expect(auth.phase, AirmiusAuthPhase.error);
        expect(auth.error, isNotEmpty);
        expect(auth.isAuthenticated, isFalse);
        expect(await store.read(), isNull);
        expect(authenticated, isFalse);
      },
    );
  }

  test('social login can retry successfully after a profile failure', () async {
    final store = AirmiusMemoryTokenStore();
    final transport = _ProfileTransport(599);
    final auth = AirmiusAuthState(
      tokenStore: store,
      clientFactory: (session) => AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: session?.token,
      ),
    );
    addTearDown(auth.dispose);
    await auth.signInWithToken(token: 'test-social-token');
    transport.status = 200;

    await auth.signInWithToken(token: 'test-social-token');

    expect(auth.isAuthenticated, isTrue);
    expect(auth.user?.id, 7);
    expect(auth.error, isNull);
    expect((await store.read())?.user?.id, 7);
  });
}

class _ProfileTransport implements AirmiusApiTransport {
  _ProfileTransport(this.status);
  int status;
  AirmiusApiRequest? request;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    this.request = request;
    return AirmiusApiResponse(
      statusCode: status,
      body: status == 200
          ? '{"data":{"id":7,"name":"Test User","email":"user@example.test","role":"athlete"}}'
          : '{"message":"Request failed"}',
    );
  }
}
