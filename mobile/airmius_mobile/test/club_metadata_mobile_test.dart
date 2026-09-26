import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/models/club_summary.dart';
import 'package:airmius/screens/club_metadata_screen.dart';

void main() {
  test('metadata configuration labels exist in every supported language', () {
    for (final language in AirmiusLanguage.values) {
      final scope = AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: const SizedBox.shrink(),
      );
      for (final key in [
        'clubMetadata.title',
        'clubMetadata.fields',
        'clubMetadata.categories',
        'clubMetadata.ranges',
        'clubMetadata.required',
        'clubMetadata.sensitive',
        'clubMetadata.setDefault',
        'clubMetadata.entity.external_member',
        'clubMetadata.entity.shop_credit_note',
      ]) {
        expect(scope.t(key), isNot(key), reason: '${language.name}: $key');
      }
    }
  });

  testWidgets('metadata screen renders all configuration sections', (
    tester,
  ) async {
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://example.test',
        enableOfflineQueue: false,
      ),
      transport: _MetadataTransport(),
    );
    final club = ClubSummary.fromAirmiusClub(
      AirmiusClub.fromJson(const {
        'id': 7,
        'name': 'Airmius SC',
        'can_manage': true,
      }),
    );

    await tester.pumpWidget(
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: (_) {},
        child: AirmiusServicesScope(
          container: container,
          child: MaterialApp(home: ClubMetadataScreen(club: club)),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Eigene Daten und Nummernkreise'), findsOneWidget);
    expect(find.text('Trikotgröße'), findsOneWidget);
    expect(find.textContaining('Pflichtfeld'), findsOneWidget);
    expect(find.textContaining('Sensibel'), findsOneWidget);

    await tester.tap(find.text('Kategorien'));
    await tester.pumpAndSettle();
    expect(find.text('Jugend'), findsOneWidget);

    await tester.tap(find.text('Nummernkreise'));
    await tester.pumpAndSettle();
    expect(find.text('Mitglieder 2026'), findsOneWidget);
    expect(find.textContaining('M-2026-0007'), findsOneWidget);
    expect(find.text('Standard aufheben'), findsOneWidget);
  });

  testWidgets('metadata viewer sees configuration without write controls', (
    tester,
  ) async {
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://example.test',
        enableOfflineQueue: false,
      ),
      transport: _MetadataReadOnlyTransport(),
    );
    final club = ClubSummary.fromAirmiusClub(
      AirmiusClub.fromJson(const {
        'id': 7,
        'name': 'Airmius SC',
        'can_view_metadata': true,
      }),
    );

    await tester.pumpWidget(
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: (_) {},
        child: AirmiusServicesScope(
          container: container,
          child: MaterialApp(home: ClubMetadataScreen(club: club)),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Trikotgröße'), findsOneWidget);
    expect(find.text('Hinzufügen'), findsNothing);
    expect(find.byType(PopupMenuButton<String>), findsNothing);
  });
}

class _MetadataTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    return const AirmiusApiResponse(
      statusCode: 200,
      body:
          '''{"data":{"custom_fields":[{"id":2,"entity_type":"member","key":"shirt_size","label":"Trikotgröße","field_type":"select","options":["S","M","L"],"is_required":true,"is_sensitive":true,"is_active":true,"sort_order":0}],"categories":[{"id":3,"scope":"member","name":"Jugend","color":"#2563EB","is_active":true,"sort_order":0}],"number_ranges":[{"id":4,"scope":"member","name":"Mitglieder 2026","prefix":"M-{YYYY}-","suffix":"","padding":4,"start_number":1,"next_number":7,"reset_policy":"yearly","is_active":true,"preview":"M-2026-0007","allocations_count":6,"is_default":true}],"can_manage":true,"can_edit":true,"can_delete":true}}''',
    );
  }
}

class _MetadataReadOnlyTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    return const AirmiusApiResponse(
      statusCode: 200,
      body:
          '''{"data":{"custom_fields":[{"id":2,"entity_type":"member","key":"shirt_size","label":"Trikotgröße","field_type":"text","options":null,"is_required":false,"is_sensitive":false,"is_active":true,"sort_order":0}],"categories":[],"number_ranges":[],"can_manage":false,"can_edit":false,"can_delete":false}}''',
    );
  }
}
