import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/screens/club_delegation_form_screen.dart';

void main() {
  Future<void> open(
    WidgetTester tester,
    Future<void> Function(JsonMap) submit,
  ) async {
    await tester.pumpWidget(
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: (_) {},
        child: MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                child: const Text('Öffnen'),
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<bool>(
                    builder: (_) => ClubDelegationFormScreen(
                      clubName: 'Sportverein',
                      memberName: 'Alex',
                      memberId: 42,
                      permissions: [
                        for (var i = 0; i < 18; i++)
                          {'key': 'permission.$i', 'label': 'Berechtigung $i'},
                      ],
                      teams: const [
                        {'id': 4, 'name': 'U17'},
                      ],
                      departments: const [
                        {'id': 8, 'name': 'Leichtathletik'},
                      ],
                      onSubmit: submit,
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Öffnen'));
    await tester.pumpAndSettle();
  }

  for (final width in [320.0, 900.0]) {
    testWidgets(
      'full page scrolls and grants without duplicate submissions at $width',
      (tester) async {
        await tester.binding.setSurfaceSize(Size(width, 844));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final completer = Completer<void>();
        final sent = <JsonMap>[];
        await open(tester, (payload) {
          sent.add(payload);
          return completer.future;
        });
        expect(find.byType(AlertDialog), findsNothing);
        expect(find.byType(BackButton), findsOneWidget);
        final permission = find.text('Berechtigung 17');
        await tester.drag(find.byType(ListView), const Offset(0, -1800));
        await tester.pumpAndSettle();
        await tester.tap(permission);
        await tester.pumpAndSettle();
        expect(tester.getRect(find.text('Vergeben')).bottom, lessThan(844));
        await tester.tap(find.text('Vergeben'));
        await tester.pump();
        await tester.tap(find.text('Vergeben'));
        expect(sent, hasLength(1));
        expect(sent.single['grantee_user_id'], 42);
        expect(sent.single['permissions'], ['permission.17']);
        expect(sent.single['scope_id'], isNull);
        expect(
          DateTime.parse(
            sent.single['ends_at'],
          ).isAfter(DateTime.parse(sent.single['starts_at'])),
          isTrue,
        );
        completer.complete();
        await tester.pumpAndSettle();
        expect(find.byType(ClubDelegationFormScreen), findsNothing);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets('failure keeps selections and allows retry with team scope', (
    tester,
  ) async {
    var attempts = 0;
    JsonMap? sent;
    await open(tester, (payload) async {
      sent = payload;
      if (attempts++ == 0) throw Exception('offline');
    });
    await tester.tap(find.text('Ganzer Verein'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Mannschaft').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('U17').last);
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView), const Offset(0, -280));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Berechtigung 0'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Vergeben'));
    await tester.pumpAndSettle();
    expect(find.byType(ClubDelegationFormScreen), findsOneWidget);
    expect(sent?['scope_type'], 'team');
    expect(sent?['scope_id'], 4);
    await tester.tap(find.text('Vergeben'));
    await tester.pumpAndSettle();
    expect(attempts, 2);
    expect(sent?['permissions'], ['permission.0']);
    expect(find.byType(ClubDelegationFormScreen), findsNothing);
  });

  testWidgets('back cancels without granting access', (tester) async {
    var requests = 0;
    await open(tester, (_) async {
      requests++;
    });
    await tester.tap(find.byType(BackButton));
    await tester.pumpAndSettle();
    expect(requests, 0);
    expect(find.text('Öffnen'), findsOneWidget);
  });

  testWidgets('422 shows only the readable rule beside the save button', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(320, 844));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    const message =
        'Die Vertretung kann nur an ein aktives Mitglied dieses Vereins vergeben werden.';
    await open(tester, (_) async {
      throw const AirmiusApiException(
        statusCode: 422,
        path: '/api/v1/clubs/26/permission-delegations',
        body:
            '{"message":"$message","errors":{},"code":"unprocessable_entity","meta":{"request_id":"example"}}',
      );
    });
    await tester.drag(find.byType(ListView), const Offset(0, -1800));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Berechtigung 17'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Vergeben'));
    await tester.pumpAndSettle();
    expect(find.text(message), findsOneWidget);
    expect(tester.getRect(find.text(message)).bottom, lessThan(844));
    expect(find.textContaining('AirmiusApiException'), findsNothing);
    expect(find.textContaining('/api/v1/'), findsNothing);
    expect(find.textContaining('request_id'), findsNothing);
    expect(find.byType(ClubDelegationFormScreen), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
