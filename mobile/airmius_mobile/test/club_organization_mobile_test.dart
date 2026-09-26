import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/models/club_summary.dart';
import 'package:airmius/screens/club_organization_screen.dart';

void main() {
  test(
    'organization client uses scoped endpoints and preserves payloads',
    () async {
      final transport = _RecordingTransport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
        token: 'token',
      );

      await client.clubOrganization(7);
      await client.saveClubOrganizationUnit(7, 'departments', {
        'name': 'Fußball',
        'is_public': true,
      });
      await client.saveClubOrganizationUnit(7, 'training-groups', {
        'name': 'U16',
        'is_public': false,
      }, id: 9);
      await client.deleteClubOrganizationUnit(7, 'locations', 4);

      expect(
        transport.requests.map(
          (request) => '${request.method} ${request.path}',
        ),
        [
          'GET /api/v1/clubs/7/organization',
          'POST /api/v1/clubs/7/organization/departments',
          'PUT /api/v1/clubs/7/organization/training-groups/9',
          'DELETE /api/v1/clubs/7/organization/locations/4',
        ],
      );
      expect(transport.requests[1].body?['name'], 'Fußball');
      expect(
        () => client.saveClubOrganizationUnit(7, 'invalid', const {}),
        throwsArgumentError,
      );
    },
  );

  test(
    'organization mutations are neither queued offline nor retried',
    () async {
      final inner = _ThrowingTransport();
      final queued = AirmiusQueuedTransport(
        inner: inner,
        timeout: const Duration(seconds: 1),
        maxRetries: 2,
      );
      final client = AirmiusApiClient(
        transport: queued,
        baseUrl: 'https://example.test',
        token: 'token',
      );

      await expectLater(
        client.saveClubOrganizationUnit(1, 'departments', {
          'name': 'Laufen',
          'is_public': false,
        }),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(inner.calls, 1);

      queued.offline = true;
      await expectLater(
        client.deleteClubOrganizationUnit(1, 'departments', 2),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(queued.queue, isEmpty);
      expect(inner.calls, 1);
    },
  );

  test('organization labels are translated in every supported language', () {
    for (final language in AirmiusLanguage.values) {
      final scope = AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: const SizedBox.shrink(),
      );
      for (final key in [
        'clubOrganization.title',
        'clubOrganization.departments',
        'clubOrganization.locations',
        'clubOrganization.trainingGroups',
        'clubOrganization.teamAssignments',
        'clubOrganization.showPublicly',
      ]) {
        expect(scope.t(key), isNot(key), reason: '${language.name}: $key');
      }
    }
  });

  testWidgets('organization screen renders units and manager actions', (
    tester,
  ) async {
    final transport = _OrganizationTransport();
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://example.test',
        enableOfflineQueue: false,
      ),
      transport: transport,
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
          child: MaterialApp(home: ClubOrganizationScreen(club: club)),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Fußball'), findsOneWidget);
    expect(find.text('Sportzentrum'), findsOneWidget);
    expect(find.text('U16 Technik'), findsOneWidget);
    expect(find.byTooltip('Eintrag anlegen'), findsWidgets);

    await tester.tap(find.byTooltip('Eintrag anlegen').first);
    await tester.pumpAndSettle();
    expect(find.text('Eintrag anlegen'), findsOneWidget);
    expect(find.text('Öffentlich anzeigen'), findsOneWidget);
    await tester.tap(find.text('Abbrechen'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Staffel'), 350);
    expect(find.text('Staffel'), findsOneWidget);
  });
}

class _RecordingTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    return const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}');
  }
}

class _ThrowingTransport implements AirmiusApiTransport {
  int calls = 0;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    calls += 1;
    throw StateError('connection lost');
  }
}

class _OrganizationTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    return const AirmiusApiResponse(
      statusCode: 200,
      body:
          '''{"data":{"departments":[{"id":1,"name":"Fußball","sport_type":"Fußball","description":null,"is_public":true}],"locations":[{"id":2,"name":"Sportzentrum","city":"Köln","country":"DE","notes":"Halle 2","is_public":true}],"training_groups":[{"id":3,"name":"U16 Technik","club_department_id":1,"club_location_id":2,"sport_type":"Fußball","description":null,"is_public":false}],"team_assignments":[{"id":4,"name":"Staffel","sport_type":"Fußball","club_department_id":1,"club_location_id":2,"club_training_group_id":3}],"can_manage":true,"can_edit":true,"can_delete":true,"can_edit_team_assignments":true}}''',
    );
  }
}
