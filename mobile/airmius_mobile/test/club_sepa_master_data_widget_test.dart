import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/core/airmius_theme.dart';
import 'package:airmius/screens/club_membership_management_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _SepaTransport implements AirmiusApiTransport {
  _SepaTransport(this.management);
  int saves = 0;
  bool fail = false;
  final Map<String, dynamic> management;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.method == 'PUT') {
      saves++;
      if (fail) {
        return const AirmiusApiResponse(
          statusCode: 422,
          body: '{"message":"Bitte Bankdaten prüfen."}',
        );
      }
    }
    final club = {
      'id': 4,
      'owner_id': 1,
      'name': 'Ledger Club',
      'can_manage': true,
    };
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': request.path == '/api/v1/clubs'
            ? [club]
            : {...club, 'management': management},
      }),
    );
  }
}

Future<void> _pump(
  WidgetTester tester,
  Map<String, dynamic> management, {
  String section = 'export',
  required _SepaTransport transport,
}) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final services = AirmiusServiceContainer(
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
        container: services,
        child: MaterialApp(
          theme: AirmiusTheme.dark(),
          home: ClubMembershipManagementScreen(
            initialClubId: 4,
            initialSection: section,
          ),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets(
    'SEPA settings retain input after local and server validation errors',
    (tester) async {
      final management = <String, dynamic>{
        'can_manage': true,
        'settings': <String, dynamic>{},
      };
      final transport = _SepaTransport(management);
      await _pump(tester, management, transport: transport);
      final configure = find.text('SEPA konfigurieren');
      await tester.ensureVisible(configure);
      await tester.pumpAndSettle();
      await tester.tap(configure);
      await tester.pumpAndSettle();
      final fields = find.descendant(
        of: find.byType(AlertDialog),
        matching: find.byType(TextField),
      );
      expect(fields, findsNWidgets(4));
      await tester.enterText(fields.at(0), 'DE98ZZZ09999999999');
      await tester.enterText(fields.at(1), 'Testverein');
      await tester.enterText(fields.at(2), 'DE89370400440532013001');
      await tester.tap(find.widgetWithText(FilledButton, 'Speichern'));
      await tester.pumpAndSettle();
      expect(transport.saves, 0);
      expect(find.byType(AlertDialog), findsOneWidget);
      expect(find.textContaining('IBAN ungültig'), findsOneWidget);
      expect(find.text('Testverein'), findsOneWidget);
      await tester.enterText(fields.at(2), 'DE89370400440532013000');
      transport.fail = true;
      await tester.tap(find.widgetWithText(FilledButton, 'Speichern'));
      await tester.pumpAndSettle();
      expect(transport.saves, 1);
      expect(find.byType(AlertDialog), findsOneWidget);
      expect(find.text('Testverein'), findsOneWidget);
      transport.fail = false;
      await tester.tap(find.widgetWithText(FilledButton, 'Speichern'));
      await tester.pumpAndSettle();
      expect(transport.saves, 2);
      expect(find.byType(AlertDialog), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
}
