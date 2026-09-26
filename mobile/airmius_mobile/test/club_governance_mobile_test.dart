import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/models/club_summary.dart';
import 'package:airmius/screens/club_governance_screen.dart';

void main() {
  test(
    'governance client uses scoped endpoints and preserves payloads',
    () async {
      final transport = _RecordingTransport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
        token: 'token',
      );

      await client.clubGovernance(7);
      await client.saveClubGovernanceBody(7, {
        'type': 'board',
        'name': 'Vorstand',
        'is_public': true,
      });
      await client.saveClubGovernanceAssignment(7, 4, {
        'user_id': 9,
        'position_title': 'Vorsitz',
        'is_public': true,
      }, assignmentId: 12);
      await client.deleteClubGovernanceAssignment(7, 4, 12);
      await client.deleteClubGovernanceBody(7, 4);

      expect(
        transport.requests.map(
          (request) => '${request.method} ${request.path}',
        ),
        [
          'GET /api/v1/clubs/7/governance',
          'POST /api/v1/clubs/7/governance/bodies',
          'PUT /api/v1/clubs/7/governance/bodies/4/assignments/12',
          'DELETE /api/v1/clubs/7/governance/bodies/4/assignments/12',
          'DELETE /api/v1/clubs/7/governance/bodies/4',
        ],
      );
      expect(transport.requests[2].body?['position_title'], 'Vorsitz');
    },
  );

  test('governance mutations are neither queued offline nor retried', () async {
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
      client.saveClubGovernanceBody(1, {
        'type': 'committee',
        'name': 'Sportausschuss',
        'is_public': false,
      }),
      throwsA(isA<AirmiusApiException>()),
    );
    expect(inner.calls, 1);
    queued.offline = true;
    await expectLater(
      client.deleteClubGovernanceBody(1, 2),
      throwsA(isA<AirmiusApiException>()),
    );
    expect(queued.queue, isEmpty);
    expect(inner.calls, 1);
  });

  test('governance labels are translated in every supported language', () {
    for (final language in AirmiusLanguage.values) {
      final scope = AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: const SizedBox.shrink(),
      );
      for (final key in [
        'clubGovernance.title',
        'clubGovernance.type.board',
        'clubGovernance.type.committee',
        'clubGovernance.type.working_group',
        'clubGovernance.addAssignment',
        'clubGovernance.responsibilities',
      ]) {
        expect(scope.t(key), isNot(key), reason: '${language.name}: $key');
      }
    }
  });

  testWidgets('governance screen renders bodies and manager actions', (
    tester,
  ) async {
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://example.test',
        enableOfflineQueue: false,
      ),
      transport: _GovernanceTransport(),
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
          child: MaterialApp(home: ClubGovernanceScreen(club: club)),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Vorstand'), findsWidgets);
    expect(find.text('Alex Vorstand'), findsOneWidget);
    expect(find.textContaining('Strategie'), findsOneWidget);
    expect(find.byTooltip('Gremium anlegen'), findsOneWidget);

    await tester.tap(find.byTooltip('Gremium anlegen'));
    await tester.pumpAndSettle();
    expect(find.text('Gremium anlegen'), findsOneWidget);
    expect(find.text('Öffentlich anzeigen'), findsOneWidget);
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

class _GovernanceTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    return const AirmiusApiResponse(
      statusCode: 200,
      body:
          '''{"data":{"bodies":[{"id":4,"type":"board","name":"Vorstand","description":"Leitung","starts_on":"2026-01-01","ends_on":"2027-12-31","is_public":true,"assignments":[{"id":12,"user_id":9,"club_external_member_id":null,"position_title":"Vorsitz","responsibilities":"Strategie und Vertretung","starts_on":"2026-01-01","ends_on":"2027-12-31","is_public":true,"person":{"name":"Alex Vorstand"}}]}],"member_options":[{"kind":"user","id":9,"name":"Alex Vorstand"}],"can_manage":true,"can_edit":true,"can_delete":true}}''',
    );
  }
}
