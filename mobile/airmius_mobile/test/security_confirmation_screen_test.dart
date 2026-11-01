import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/screens/security_confirmation_screen.dart';

void main() {
  testWidgets(
    'email confirmation lets the user correct a code without a new email',
    (tester) async {
      final transport = _ConfirmationTransport();
      AirmiusJson? proof;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => TextButton(
                onPressed: () => Navigator.of(context).push<void>(
                  MaterialPageRoute(
                    builder: (_) => SecurityConfirmationScreen(
                      client: AirmiusApiClient(
                        transport: transport,
                        baseUrl: 'https://airmius.test',
                      ),
                      purpose: 'enable',
                      onConfirm: (credentials) async {
                        if (credentials['email_code'] == '000000') {
                          throw const AirmiusApiException(
                            statusCode: 422,
                            body: '{"message":"Code falsch"}',
                            path: '/test',
                          );
                        }
                        proof = credentials;
                      },
                    ),
                  ),
                ),
                child: const Text('Öffnen'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Öffnen'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Code per E-Mail senden'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), '000000');
      await tester.tap(find.text('Bestätigen'));
      await tester.pumpAndSettle();
      expect(find.text('Code falsch'), findsOneWidget);
      expect(find.byType(SecurityConfirmationScreen), findsOneWidget);
      expect(find.byType(AlertDialog), findsNothing);
      await tester.enterText(find.byType(TextField), '123456');
      await tester.tap(find.text('Bestätigen'));
      await tester.pumpAndSettle();
      expect(proof!['email_code'], '123456');
      expect(proof!.containsKey('current_password'), isFalse);
      expect(transport.sent, 1);
      expect(find.byType(SecurityConfirmationScreen), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('password remains an alternative without sending email', (
    tester,
  ) async {
    final transport = _ConfirmationTransport();
    AirmiusJson? proof;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SecurityConfirmationScreen(
            client: AirmiusApiClient(
              transport: transport,
              baseUrl: 'https://airmius.test',
            ),
            purpose: 'disable',
            onConfirm: (credentials) async {
              proof = credentials;
            },
          ),
        ),
      ),
    );
    await tester.tap(find.text('Passwort'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), ' local-password ');
    await tester.tap(find.text('Bestätigen'));
    await tester.pumpAndSettle();
    expect(proof!['current_password'], ' local-password ');
    expect(transport.sent, 0);
    expect(tester.takeException(), isNull);
  });
}

class _ConfirmationTransport implements AirmiusApiTransport {
  int sent = 0;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    sent++;
    expect(request.body!['purpose'], 'enable');
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': {'challenge_token': 'a' * 64},
      }),
    );
  }
}
