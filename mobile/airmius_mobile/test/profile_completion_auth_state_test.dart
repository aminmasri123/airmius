import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_auth_state.dart';

void main() {
  late _ProfileTransport transport;
  late AirmiusAuthState auth;

  setUp(() async {
    transport = _ProfileTransport();
    auth = AirmiusAuthState(
      tokenStore: AirmiusMemoryTokenStore(),
      clientFactory: (session) => AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: session?.token,
      ),
    );
    await auth.signInWithToken(token: 'test-session');
  });

  tearDown(() => auth.dispose());

  test(
    'profile permission failure preserves session and explains consent',
    () async {
      transport.status = 403;
      transport.body = '{"message":"Bitte warte auf die Elternfreigabe."}';
      await auth.completeProfile(
        payload: const {},
        preserveAuthenticatedPhase: true,
      );

      expect(auth.isAuthenticated, isTrue);
      expect(auth.error, 'Bitte warte auf die Elternfreigabe.');
      expect(auth.error, isNot(contains('Passwort')));
      expect(auth.isCompletingProfile, isFalse);
    },
  );

  test('expired profile session asks for a new login', () async {
    transport.status = 401;
    transport.body = '{"message":"Unauthenticated."}';
    await auth.completeProfile(
      payload: const {},
      preserveAuthenticatedPhase: true,
    );

    expect(auth.phase, AirmiusAuthPhase.expired);
    expect(
      auth.error,
      'Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.',
    );
    expect(auth.isCompletingProfile, isFalse);
  });

  test('validation failure keeps the session and field feedback', () async {
    transport.status = 422;
    transport.body =
        '{"errors":{"guardian_email":["Bitte Eltern-E-Mail prüfen."]}}';
    await auth.completeProfile(
      payload: const {},
      preserveAuthenticatedPhase: true,
    );

    expect(auth.isAuthenticated, isTrue);
    expect(auth.error, 'Bitte Eltern-E-Mail prüfen.');
  });

  test(
    'duplicate saves are ignored and the returned profile is retained',
    () async {
      transport.pending = Completer<AirmiusApiResponse>();
      final saving = auth.completeProfile(
        payload: const {'first_name': 'Kind'},
        preserveAuthenticatedPhase: true,
      );
      expect(auth.isCompletingProfile, isTrue);
      await auth.completeProfile(
        payload: const {'first_name': 'Duplicate'},
        preserveAuthenticatedPhase: true,
      );
      expect(transport.saves, 1);
      transport.pending!.complete(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"id":7,"name":"Kind Airmius","first_name":"Kind","role":"minor_pending_consent"}}',
        ),
      );
      await saving;

      expect(auth.isAuthenticated, isTrue);
      expect(auth.user?.firstName, 'Kind');
      expect(auth.user?.hasRole('minor_pending_consent'), isTrue);
      expect(auth.isCompletingProfile, isFalse);
      expect(auth.error, isNull);
    },
  );

  test('an actual rejected login still reports invalid credentials', () async {
    await auth.signIn(email: 'child@example.test', password: 'wrong');
    expect(auth.error, 'E-Mail oder Passwort ist falsch.');
  });

  test('login lockout displays remaining time instead of wrong password', () async {
    transport.loginResponse = const AirmiusApiResponse(
      statusCode: 429,
      body: '{"errors":{"email":["Zu viele falsche Anmeldeversuche. Bitte warte 180 Sekunden oder setze dein Passwort zurück."]}}',
    );
    await auth.signIn(email: 'child@example.test', password: 'wrong');
    expect(auth.error, contains('180 Sekunden'));
    expect(auth.error, isNot('E-Mail oder Passwort ist falsch.'));
  });
}

class _ProfileTransport implements AirmiusApiTransport {
  AirmiusApiResponse? loginResponse;
  int status = 200;
  String body =
      '{"data":{"id":7,"name":"Kind Airmius","role":"minor_pending_consent"}}';
  int saves = 0;
  Completer<AirmiusApiResponse>? pending;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.path == '/api/v1/auth/login') {
      if (loginResponse != null) return loginResponse!;
      return const AirmiusApiResponse(
        statusCode: 401,
        body: '{"message":"Invalid credentials."}',
      );
    }
    if (request.path == '/api/v1/me/profile') {
      saves++;
      if (pending != null) return pending!.future;
      return AirmiusApiResponse(statusCode: status, body: body);
    }
    return const AirmiusApiResponse(
      statusCode: 200,
      body:
          '{"data":{"id":7,"name":"Kind Airmius","role":"minor_pending_consent"}}',
    );
  }
}
