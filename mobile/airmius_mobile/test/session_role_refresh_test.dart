import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_auth_state.dart';

void main() {
  test(
    'session validation adopts granted and revoked roles without leaving the page',
    () async {
      final transport = _RoleTransport();
      final store = AirmiusMemoryTokenStore();
      final auth = AirmiusAuthState(
        tokenStore: store,
        clientFactory: (session) => AirmiusApiClient(
          transport: transport,
          baseUrl: 'https://airmius.test',
          token: session?.token,
        ),
      );
      addTearDown(auth.dispose);
      await auth.signInWithToken(token: 'test-session');
      var notifications = 0;
      auth.addListener(() => notifications++);

      transport.roles = ['super_admin'];
      await auth.validateSession();
      expect(auth.user!.hasRole('super_admin'), isTrue);
      expect(auth.user!.can('system.manage'), isTrue);
      expect(auth.phase, AirmiusAuthPhase.authenticated);
      expect((await store.read())!.user!.roles, ['super_admin']);
      expect(notifications, 1);

      await auth.validateSession();
      expect(notifications, 1);

      transport.roles = ['player'];
      await auth.validateSession();
      expect(auth.user!.can('system.manage'), isFalse);
      expect(notifications, 2);
    },
  );

  test('late session validation cannot restore roles after logout', () async {
    final transport = _RoleTransport();
    final auth = AirmiusAuthState(
      tokenStore: AirmiusMemoryTokenStore(),
      clientFactory: (session) => AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: session?.token,
      ),
    );
    addTearDown(auth.dispose);
    await auth.signInWithToken(token: 'test-session');
    transport.pending = Completer<AirmiusApiResponse>();
    final checking = auth.validateSession();
    await auth.signOut();
    transport.pending!.complete(
      AirmiusApiResponse(
        statusCode: 200,
        body: jsonEncode({
          'data': {
            'id': 7,
            'name': 'Test',
            'roles': ['super_admin'],
          },
        }),
      ),
    );
    await checking;
    expect(auth.isAuthenticated, isFalse);
    expect(auth.user, isNull);
  });
}

class _RoleTransport implements AirmiusApiTransport {
  List<String> roles = ['player'];
  Completer<AirmiusApiResponse>? pending;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.path == '/api/v1/me') {
      if (pending != null) return pending!.future;
      return AirmiusApiResponse(
        statusCode: 200,
        body: jsonEncode({
          'data': {
            'id': 7,
            'name': 'Test',
            'email': 'test@example.test',
            'roles': roles,
            'permissions': [],
          },
        }),
      );
    }
    return const AirmiusApiResponse(statusCode: 200, body: '{}');
  }
}
