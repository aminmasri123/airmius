import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/club_newsletter_subscribers_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('manager can send and record a hard bounce in the app', (
    tester,
  ) async {
    final transport = _NewsletterTransport();
    await _mount(tester, transport);

    expect(find.text('Letzte Zustellungen'), findsOneWidget);
    expect(find.text('member@example.org'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.send_outlined));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).at(0), 'T26-05 Versand');
    await tester.enterText(find.byType(TextField).at(1), 'Nachricht');
    await tester.tap(find.text('Jetzt senden'));
    await tester.pumpAndSettle();

    final send = transport.requests.firstWhere(
      (request) => request.path.endsWith('/newsletter/send'),
    );
    expect(send.body?['subject'], 'T26-05 Versand');
    expect(send.body?['idempotency_key'], isNotEmpty);

    await tester.ensureVisible(find.text('Zustellfehler erfassen'));
    await tester.tap(find.text('Zustellfehler erfassen'));
    await tester.pumpAndSettle();
    expect(find.text('Dauerhaft unzustellbar'), findsOneWidget);
    await tester.tap(find.text('Fehler speichern'));
    await tester.pumpAndSettle();

    final bounce = transport.requests.firstWhere(
      (request) => request.path.endsWith('/deliveries/12/bounce'),
    );
    expect(bounce.body?['bounce_type'], 'hard');
    expect(bounce.body?['failure_reason'], 'T26-05 App-Test');
    expect(tester.takeException(), isNull);
  });
}

Future<void> _mount(WidgetTester tester, _NewsletterTransport transport) async {
  final container = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://example.test',
      enableOfflineQueue: false,
    ),
    transport: transport,
  );
  await tester.pumpWidget(
    AirmiusScope(
      language: AirmiusLanguage.de,
      setLanguage: (_) {},
      child: AirmiusServicesScope(
        container: container,
        child: const MaterialApp(
          home: ClubNewsletterSubscribersScreen(clubId: 7),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _NewsletterTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (request.method == 'POST' && request.path.endsWith('/newsletter/send')) {
      return _response({
        'data': {'id': 3, 'status': 'sent', 'deliveries_count': 1},
      }, statusCode: 201);
    }
    if (request.method == 'POST' &&
        request.path.endsWith('/deliveries/12/bounce')) {
      return _response({
        'data': {'id': 12, 'status': 'bounced', 'bounce_type': 'hard'},
      });
    }
    return _response({
      'data': const [],
      'total': 0,
      'current_page': 1,
      'last_page': 1,
      'failures': const [],
      'suppressions': const [],
      'recent_deliveries': [
        {
          'id': 12,
          'email': 'member@example.org',
          'status': 'sent',
          'sent_at': '2026-11-27T10:00:00Z',
        },
      ],
    });
  }

  AirmiusApiResponse _response(
    Map<String, dynamic> body, {
    int statusCode = 200,
  }) => AirmiusApiResponse(statusCode: statusCode, body: jsonEncode(body));
}
