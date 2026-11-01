import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/screens/role_assignment_screen.dart';

void main() {
  testWidgets('user role editor keeps existing roles and saves an added role', (
    tester,
  ) async {
    final transport = _AssignmentTransport();
    await tester.pumpWidget(
      MaterialApp(
        home: RoleAssignmentScreen(
          client: AirmiusApiClient(
            transport: transport,
            baseUrl: 'https://airmius.test',
          ),
          userId: 7,
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Rollen für Testperson'), findsOneWidget);
    expect(
      tester
          .widget<CheckboxListTile>(
            find.widgetWithText(CheckboxListTile, 'player'),
          )
          .value,
      isTrue,
    );
    await tester.ensureVisible(find.text('coach'));
    await tester.tap(find.text('coach'));
    await tester.pump();
    await tester.tap(find.text('Rollen speichern'));
    await tester.pumpAndSettle();
    expect(transport.saved!['roles'], containsAll(['player', 'coach']));
    expect(transport.saved!['expected_roles'], ['player']);
    expect(find.textContaining('Rollen gespeichert.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('role entry searches a person before changing any roles', (
    tester,
  ) async {
    final transport = _AssignmentTransport();
    await tester.pumpWidget(
      MaterialApp(
        home: RoleAssignmentScreen(
          client: AirmiusApiClient(
            transport: transport,
            baseUrl: 'https://airmius.test',
          ),
          roleName: 'coach',
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Rollen speichern'), findsNothing);
    await tester.tap(find.text('Testperson'));
    await tester.pumpAndSettle();
    expect(find.text('Rollen speichern'), findsOneWidget);
    expect(transport.saved, isNull);
    expect(
      tester
          .widget<CheckboxListTile>(
            find.widgetWithText(CheckboxListTile, 'player'),
          )
          .value,
      isTrue,
    );
    expect(tester.takeException(), isNull);
  });
}

class _AssignmentTransport implements AirmiusApiTransport {
  AirmiusJson? saved;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.method == 'PATCH') {
      saved = request.body;
      return AirmiusApiResponse(
        statusCode: 200,
        body: jsonEncode({
          'data': {'roles': request.body!['roles']},
        }),
      );
    }
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': {
          'users': {
            'data': [
              {
                'id': 7,
                'name': 'Testperson',
                'email': 'test@example.test',
                'roles': ['player'],
              },
            ],
            'current_page': 1,
            'last_page': 1,
          },
          'roles': [
            {'name': 'player', 'description': 'Spieler', 'permissions': []},
            {
              'name': 'coach',
              'description': 'Trainer',
              'permissions': [
                {'name': 'training.view', 'description': 'Trainings ansehen'},
              ],
            },
          ],
        },
      }),
    );
  }
}
