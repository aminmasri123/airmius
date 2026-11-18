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
    if (request.path.endsWith('/rides/locations')) {
      return AirmiusApiResponse(
        statusCode: 200,
        body: jsonEncode({
          'data': [
            {
              'label': 'Metz, 57000, France',
              'name': 'Metz',
              'city': 'Metz',
              'postal_code': '57000',
              'country': 'FR',
              'latitude': 49.12,
              'longitude': 6.18,
            },
          ],
        }),
      );
    }
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
    await tester.ensureVisible(from);
    await tester.pumpAndSettle();
    await tester.enterText(from, 'Metz');
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Metz, 57000, France').last);
    await tester.tap(find.text('Metz, 57000, France').last);
    await tester.pumpAndSettle();
    for (final km in [5, 10, 25]) {
      final radius = find.widgetWithText(
        DropdownButtonFormField<int>,
        'Umkreis',
      );
      await tester.ensureVisible(radius);
      await tester.pumpAndSettle();
      await tester.tap(radius);
      await tester.pumpAndSettle();
      await tester.tap(find.text('$km km').last);
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Fahrten suchen'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Fahrten suchen'));
      await tester.pumpAndSettle();
      final query = transport.requests
          .lastWhere((r) => r.path.endsWith('/rides'))
          .query;
      expect(query['radius_km'], '$km');
      expect(query['from_lat'], '49.12');
      expect(query['from_q'], '57000 Metz FR');
    }
    await tester.ensureVisible(from);
    await tester.pumpAndSettle();
    await tester.enterText(from, 'Other town');
    final before = transport.requests
        .where((r) => r.path.endsWith('/rides'))
        .length;
    await tester.ensureVisible(find.text('Fahrten suchen'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Fahrten suchen'));
    await tester.pumpAndSettle();
    expect(
      transport.requests.where((r) => r.path.endsWith('/rides')).length,
      before,
    );
    expect(
      find.text(
        'Bitte einen Ort aus den Vorschlägen für die Umkreissuche auswählen.',
      ),
      findsOneWidget,
    );
    await tester.ensureVisible(find.text('Fahrt anbieten'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Fahrt anbieten'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const ValueKey('ride-cost-mode')));
    await tester.tap(find.byKey(const ValueKey('ride-cost-mode')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Kostenlos').last);
    await tester.pumpAndSettle();
    Future<void> fill(String label, String value, {bool last = false}) async {
      final matching = find.widgetWithText(TextFormField, label);
      final field = last ? matching.last : matching.first;
      await tester.ensureVisible(field);
      await tester.pumpAndSettle();
      await tester.enterText(field, value);
    }

    await fill('Start', 'Bahnhof');
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Metz, 57000, France').last);
    await tester.tap(find.text('Metz, 57000, France').last);
    await tester.pumpAndSettle();
    expect(find.widgetWithText(TextFormField, '57000'), findsOneWidget);
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
    expect(
      tester
          .widget<TextFormField>(find.widgetWithText(TextFormField, 'Ort').last)
          .controller
          ?.text,
      'Metz',
    );
    expect(tester.takeException(), isNull);
  });
}
