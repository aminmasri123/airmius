import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/club_deletion_screen.dart';
import 'package:airmius/screens/club_profile_editor_screen.dart';
import 'package:airmius/screens/club_setup_onboarding_screen.dart';
import 'package:airmius/widgets/airmius_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _ClubTransport implements AirmiusApiTransport {
  final paths = <String>[];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    paths.add(request.path);
    if (request.path.endsWith('/deletion')) {
      return AirmiusApiResponse(
        statusCode: 200,
        body: jsonEncode({
          'data': {'can_request': true, 'blocked': false},
        }),
      );
    }
    final id = int.tryParse(request.path.split('/').last) ?? 1;
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': {
          'id': id,
          'name': id == 2 ? 'Verein test 2' : 'Verein Test',
          'can_manage': id != 3,
          'can_delete': id != 3,
        },
      }),
    );
  }
}

Widget _app(Widget home, _ClubTransport transport) {
  final container = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://example.test',
      enableOfflineQueue: false,
    ),
    transport: transport,
  );
  return AirmiusScope(
    language: AirmiusLanguage.de,
    setLanguage: (_) {},
    child: AirmiusServicesScope(
      container: container,
      child: MaterialApp(home: home),
    ),
  );
}

void main() {
  testWidgets(
    'visibility settings retain the selected club through navigation',
    (tester) async {
      tester.view.physicalSize = const Size(420, 900);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final transport = _ClubTransport();
      await tester.pumpWidget(
        _app(const ClubSetupOnboardingScreen(clubId: 2), transport),
      );
      final visibility = find.text('Sichtbarkeit').first;
      await tester.drag(find.byType(CustomScrollView), const Offset(0, -1500));
      await tester.pumpAndSettle();
      final card = find
          .ancestor(of: visibility, matching: find.byType(AirmiusPanel))
          .first;
      await tester.tap(
        find.descendant(of: card, matching: find.byType(IconButton)),
      );
      await tester.pumpAndSettle();

      expect(find.byType(ClubProfileEditorScreen), findsOneWidget);
      expect(
        tester
            .widget<ClubProfileEditorScreen>(
              find.byType(ClubProfileEditorScreen),
            )
            .initialClubId,
        2,
      );
      expect(transport.paths, contains('/api/v1/clubs/2'));
      expect(transport.paths, isNot(contains('/api/v1/clubs/1')));
      expect(find.text('Verein test 2'), findsWidgets);
    },
  );

  testWidgets('deletion page receives the selected club ID and name', (
    tester,
  ) async {
    final transport = _ClubTransport();
    await tester.pumpWidget(
      _app(const ClubProfileEditorScreen(initialClubId: 2), transport),
    );
    await tester.pumpAndSettle();

    final deleteAction = find.ancestor(
      of: find.text('Verein löschen'),
      matching: find.byType(ListTile),
    );
    await tester.drag(find.byType(CustomScrollView), const Offset(0, -1000));
    await tester.pumpAndSettle();
    await tester.tap(deleteAction);
    await tester.pumpAndSettle();

    final deletion = tester.widget<ClubDeletionScreen>(
      find.byType(ClubDeletionScreen),
    );
    expect(deletion.clubId, 2);
    expect(deletion.clubName, 'Verein test 2');
    expect(transport.paths, contains('/api/v1/clubs/2/deletion'));
  });

  testWidgets('inaccessible selected club never falls back to another club', (
    tester,
  ) async {
    final transport = _ClubTransport();
    await tester.pumpWidget(
      _app(const ClubProfileEditorScreen(initialClubId: 3), transport),
    );
    await tester.pumpAndSettle();

    expect(transport.paths, contains('/api/v1/clubs/3'));
    expect(transport.paths, isNot(contains('/api/v1/clubs/1')));
    expect(find.text('Verein Test'), findsNothing);
  });
}
