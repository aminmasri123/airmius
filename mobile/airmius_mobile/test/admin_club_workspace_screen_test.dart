import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/screens/admin_club_workspace_screen.dart';

void main() {
  testWidgets(
    'subscription change requires confirmation and uses selected club',
    (tester) async {
      tester.view.resetPhysicalSize();
      await tester.binding.setSurfaceSize(const Size(390, 844));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final transport = _ClubTransport(billing: true);
      await tester.pumpWidget(
        MaterialApp(
          home: AdminClubWorkspaceScreen(
            clubId: 7,
            client: AirmiusApiClient(
              transport: transport,
              baseUrl: 'https://airmius.test',
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Abo & Abrechnung'));
      await tester.tap(find.text('Abo & Abrechnung'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Abo ändern'));
      await tester.tap(find.text('Abo ändern'));
      await tester.pumpAndSettle();
      expect(transport.saved, isNull);
      await tester.tap(find.text('Bestätigen'));
      await tester.pumpAndSettle();
      expect(transport.saved?['subscription_plan_id'], 3);
      expect(transport.path, '/api/v1/admin/backoffice/clubs/7/subscription');
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'club page saves edited fields and keeps the save button visible',
    (tester) async {
      final transport = _ClubTransport();
      await tester.pumpWidget(
        MaterialApp(
          home: AdminClubWorkspaceScreen(
            clubId: 7,
            client: AirmiusApiClient(
              transport: transport,
              baseUrl: 'https://airmius.test',
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Testverein'), findsOneWidget);
      await tester.tap(find.text('Vereinsdaten'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).first, 'Neuer Vereinsname');
      await tester.tap(find.text('Vereinsdaten speichern'));
      await tester.pumpAndSettle();
      expect(transport.saved?['name'], 'Neuer Vereinsname');
      expect(find.text('Änderung gespeichert.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('billing is hidden without permission', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: AdminClubWorkspaceScreen(
          clubId: 7,
          client: AirmiusApiClient(
            transport: _ClubTransport(),
            baseUrl: 'https://airmius.test',
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Abo & Abrechnung'));
    await tester.tap(find.text('Abo & Abrechnung'));
    await tester.pumpAndSettle();
    expect(
      find.text('Für die Abonnementverwaltung fehlt dir die Berechtigung.'),
      findsOneWidget,
    );
    expect(find.text('Abo ändern'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

class _ClubTransport implements AirmiusApiTransport {
  _ClubTransport({this.billing = false});
  final bool billing;
  String? path;
  AirmiusJson? saved;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.method == 'PATCH' || request.method == 'PUT') {
      path = request.path;
      saved = request.body;
      return AirmiusApiResponse(
        statusCode: 200,
        body: '{"data":{"saved":true}}',
      );
    }
    final page = {'data': [], 'current_page': 1, 'last_page': 1};
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': {
          'club': {
            'id': 7,
            'name': saved?['name'] ?? 'Testverein',
            'verification_status': 'pending_verification',
            'users_count': 0,
            'external_members_count': 0,
            'teams_count': 0,
          },
          'fields': {'name': 'Vereinsname', 'city': 'Ort'},
          'canManageSubscription': billing,
          'canManageRoles': false,
          'plans': billing
              ? [
                  {'id': 3, 'name': 'Vereinstarif'},
                ]
              : [],
          'subscription': billing
              ? {'subscription_plan_id': 3, 'status': 'active'}
              : null,
          'members': page,
          'teams': page,
          'invoices': null,
          'history': [],
        },
      }),
    );
  }
}
