import 'dart:convert';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/carpool_center_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class RideTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (request.method == 'POST') {
      return const AirmiusApiResponse(
        statusCode: 422,
        body: '{"message":"Adresse prüfen"}',
      );
    }
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': {'rides': [], 'clubs': [], 'teams': [], 'events': []},
      }),
    );
  }
}

void main() {
  testWidgets('postcode and city searches combine and reset on a small phone', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final transport = RideTransport();
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
          child: const MaterialApp(home: CarpoolCenterScreen()),
        ),
      ),
    );
    await tester.pumpAndSettle();
    final from = find.widgetWithText(TextField, 'Start: PLZ oder Stadt');
    final to = find.widgetWithText(TextField, 'Ziel: PLZ oder Stadt');
    await tester.ensureVisible(from);
    await tester.enterText(from, '66271');
    await tester.ensureVisible(to);
    await tester.enterText(to, 'Metz');
    await tester.ensureVisible(find.text('Fahrten suchen'));
    await tester.tap(find.text('Fahrten suchen'));
    await tester.pumpAndSettle();
    expect(transport.requests.last.query['from_q'], '66271');
    expect(transport.requests.last.query['to_q'], 'Metz');
    await tester.ensureVisible(find.text('Suche zurücksetzen'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Suche zurücksetzen'));
    await tester.pumpAndSettle();
    expect(transport.requests.last.query['from_q'] ?? '', '');
    await tester.ensureVisible(find.text('Fahrt anbieten'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Fahrt anbieten'));
    await tester.pumpAndSettle();
    Future<void> fill(String label, String value, {bool last = false}) async {
      final matching = find.widgetWithText(TextFormField, label);
      final field = last ? matching.last : matching.first;
      await tester.ensureVisible(field);
      await tester.pumpAndSettle();
      await tester.enterText(field, value);
    }

    await fill('Start', 'Bahnhof');
    await fill('Ziel', 'Sporthalle');
    await fill('PLZ', '66271');
    await fill('Ort', 'Kleinblittersdorf');
    await fill('PLZ', '57000', last: true);
    await fill('Ort', 'Metz', last: true);
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.ensureVisible(find.text('Fahrt erstellen'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Fahrt erstellen'));
    await tester.pumpAndSettle();
    final saved = transport.requests.lastWhere(
      (request) => request.method == 'POST',
    );
    expect(saved.body?['pickup_postal_code'], '66271');
    expect(saved.body?['destination_postal_code'], '57000');
    expect(saved.body?['destination_city'], 'Metz');
    expect(find.text('Adresse prüfen'), findsOneWidget);
    expect(find.widgetWithText(TextFormField, 'Metz'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
