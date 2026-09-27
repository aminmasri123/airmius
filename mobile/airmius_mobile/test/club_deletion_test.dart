import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/club_deletion_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

void main() {
  setUpAll(() => initializeDateFormatting('de'));

  testWidgets(
    'confirmation gates scheduling; owner can then cancel on a small screen',
    (tester) async {
      tester.view.physicalSize = const Size(360, 740);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final transport = _DeletionTransport();
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
              home: ClubDeletionScreen(
                clubId: 7,
                clubName: 'Sportverein Beispiel',
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      final buttonText = find.text('Löschung in 30 Tagen vormerken');
      await tester.scrollUntilVisible(buttonText, 180);
      final button = find.ancestor(
        of: buttonText,
        matching: find.byWidgetPredicate((widget) => widget is FilledButton),
      );
      expect(tester.widget<FilledButton>(button).onPressed, isNull);
      await tester.enterText(find.byType(TextField), 'Ja, ich bin mir sicher');
      await tester.pumpAndSettle();
      await tester.ensureVisible(button);
      await tester.tap(button);
      await tester.pumpAndSettle();
      expect(
        transport.requests.where((r) => r.method == 'DELETE').single.path,
        '/api/v1/clubs/7',
      );
      expect(find.text('Löschung vorgemerkt'), findsOneWidget);
      final cancel = find.text('Löschung zurücknehmen');
      await tester.scrollUntilVisible(cancel, 180);
      await tester.tap(cancel);
      await tester.pumpAndSettle();
      expect(transport.requests.last.path, '/api/v1/clubs/7/deletion');
      expect(find.byType(TextField), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  test(
    'deletion requests and cancellations are never queued offline',
    () async {
      final transport = AirmiusQueuedTransport(
        inner: _DeletionTransport(),
        timeout: const Duration(seconds: 5),
      )..offline = true;
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
      );
      await expectLater(
        client.deleteClub(7, confirmation: 'Ja, ich bin mir sicher'),
        throwsA(isA<AirmiusApiException>()),
      );
      await expectLater(
        client.cancelClubDeletion(7),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(transport.queue, isEmpty);
    },
  );
}

class _DeletionTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  String? date;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (request.method == 'DELETE') {
      date = request.path.endsWith('/deletion') ? null : '2026-10-27T12:00:00Z';
    }
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': {
          'scheduled_at': date,
          'confirmation': 'Ja, ich bin mir sicher',
          'blocker': null,
          'blocked': false,
        },
      }),
    );
  }
}
