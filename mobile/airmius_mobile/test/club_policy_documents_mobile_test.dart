import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/models/club_summary.dart';
import 'package:airmius/screens/club_policy_documents_screen.dart';
import 'package:airmius/screens/club_membership_management_screen.dart';

void main() {
  test(
    'policy document client uses scoped endpoints and preserves payloads',
    () async {
      final transport = _RecordingTransport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
        token: 'token',
      );

      await client.clubPolicyDocuments(7);
      await client.saveClubPolicyDocument(7, {
        'type': 'statutes',
        'title': 'Satzung',
        'version_label': '2026',
        'valid_from': '2026-01-01',
        'valid_until': null,
        'is_public': true,
        'file_id': 9,
      });
      await client.saveClubPolicyDocument(7, {
        'type': 'regulation',
        'title': 'Ordnung',
        'version_label': '2',
        'valid_from': '2027-01-01',
        'valid_until': null,
        'is_public': false,
        'file_id': 10,
      }, documentId: 4);
      await client.deleteClubPolicyDocument(7, 4);

      expect(
        transport.requests.map(
          (request) => '${request.method} ${request.path}',
        ),
        [
          'GET /api/v1/clubs/7/policy-documents',
          'POST /api/v1/clubs/7/policy-documents',
          'PUT /api/v1/clubs/7/policy-documents/4',
          'DELETE /api/v1/clubs/7/policy-documents/4',
        ],
      );
      expect(transport.requests[2].body?['file_id'], 10);
    },
  );

  test(
    'policy document mutations are neither queued offline nor retried',
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
        client.saveClubPolicyDocument(1, {
          'type': 'statutes',
          'title': 'Satzung',
          'version_label': '1',
          'valid_from': '2026-01-01',
          'is_public': false,
          'file_id': 2,
        }),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(inner.calls, 1);
      queued.offline = true;
      await expectLater(
        client.deleteClubPolicyDocument(1, 2),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(queued.queue, isEmpty);
      expect(inner.calls, 1);
    },
  );

  test('policy document labels are translated in every supported language', () {
    for (final language in AirmiusLanguage.values) {
      final scope = AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: const SizedBox.shrink(),
      );
      for (final key in [
        'clubPolicyDocuments.title',
        'clubPolicyDocuments.type.statutes',
        'clubPolicyDocuments.type.regulation',
        'clubPolicyDocuments.type.contribution_model',
        'clubPolicyDocuments.validFrom',
        'clubPolicyDocuments.public',
        'clubPolicyDocuments.required',
      ]) {
        expect(scope.t(key), isNot(key), reason: '${language.name}: $key');
      }
    }
  });

  test('management payload preserves contribution model versions', () {
    final management = AirmiusClubManagement.fromJson(const {
      'can_manage': true,
      'contribution_policy_documents': [
        {
          'id': 11,
          'title': 'Beitragsordnung',
          'version_label': '2026',
          'valid_from': '2026-01-01',
          'valid_until': '2026-12-31',
        },
      ],
    });

    expect(management.contributionPolicyDocuments, hasLength(1));
    expect(management.contributionPolicyDocuments.first['id'], 11);
  });

  test('contribution model link labels exist in every supported language', () {
    for (final language in AirmiusLanguage.values) {
      final scope = AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: const SizedBox.shrink(),
      );
      for (final key in [
        'membership.contributionModelVersion',
        'membership.noContributionModelVersion',
        'membership.approvedBasis',
      ]) {
        expect(scope.t(key), isNot(key), reason: '${language.name}: $key');
      }
    }
  });

  testWidgets('policy document screen renders versions and manager dialog', (
    tester,
  ) async {
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://example.test',
        enableOfflineQueue: false,
      ),
      transport: _PolicyDocumentTransport(),
    );
    final club = ClubSummary.fromAirmiusClub(
      AirmiusClub.fromJson(const {
        'id': 7,
        'name': 'Airmius SC',
        'can_manage': true,
        'is_member': true,
      }),
    );

    await tester.pumpWidget(
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: (_) {},
        child: AirmiusServicesScope(
          container: container,
          child: MaterialApp(home: ClubPolicyDocumentsScreen(club: club)),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Vereinssatzung'), findsOneWidget);
    expect(find.text('Satzungen'), findsOneWidget);
    expect(find.text('Ordnungen'), findsOneWidget);
    expect(find.text('Beitragsmodelle'), findsOneWidget);
    expect(find.text('satzung.pdf'), findsOneWidget);
    expect(find.byTooltip('Fassung anlegen'), findsOneWidget);

    await tester.tap(find.byTooltip('Fassung anlegen'));
    await tester.pumpAndSettle();
    expect(find.text('Fassung anlegen'), findsOneWidget);
    expect(find.text('Vereinsdatei'), findsOneWidget);
    expect(find.text('satzung.pdf'), findsOneWidget);
    await tester.tap(find.text('Speichern'));
    await tester.pumpAndSettle();
    expect(
      find.text('Titel, Version, Beginn und Datei sind erforderlich.'),
      findsOneWidget,
    );
  });

  testWidgets('membership rules render and edit their approved model version', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(900, 2200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://example.test',
        enableOfflineQueue: false,
      ),
      transport: _ContributionPolicyTransport(),
    );

    await tester.pumpWidget(
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: (_) {},
        child: AirmiusServicesScope(
          container: container,
          child: const MaterialApp(
            home: ClubMembershipManagementScreen(
              initialClubId: 7,
              initialSection: 'rules',
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Regeln').last);
    await tester.pumpAndSettle();
    expect(find.text('Jahresbeitrag'), findsOneWidget);
    expect(
      find.text('Beschlussgrundlage: Beitragsordnung · 2026'),
      findsOneWidget,
    );

    await tester.tap(find.text('Bearbeiten').last);
    await tester.pumpAndSettle();
    expect(find.text('Beitragsmodellfassung'), findsOneWidget);
    expect(find.text('Beitragsordnung · 2026'), findsOneWidget);
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

class _PolicyDocumentTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.path == '/api/v1/files') {
      return const AirmiusApiResponse(
        statusCode: 200,
        body:
            '''{"data":{"files":[{"id":9,"display_name":"satzung.pdf","type":"application/pdf","size":100}],"folders":[],"files_pagination":{},"folders_pagination":{}}}''',
      );
    }
    return const AirmiusApiResponse(
      statusCode: 200,
      body:
          '''{"data":{"documents":[{"id":4,"type":"statutes","title":"Vereinssatzung","version_label":"2026.1","valid_from":"2026-01-01","valid_until":null,"status":"current","is_public":true,"notes":"Beschlossen","file":{"id":9,"display_name":"satzung.pdf","type":"application/pdf","size":100,"download_url":"https://example.test/api/v1/clubs/7/policy-documents/4/download"}}],"types":["statutes","regulation","contribution_model"],"can_manage":true,"can_edit":true,"can_download":true,"can_delete":true}}''',
    );
  }
}

class _ContributionPolicyTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.path == '/api/v1/clubs') {
      return const AirmiusApiResponse(
        statusCode: 200,
        body:
            '''{"data":[{"id":7,"owner_id":1,"name":"Airmius SC","can_manage":true,"is_member":true}]}''',
      );
    }
    return const AirmiusApiResponse(
      statusCode: 200,
      body:
          '''{"data":{"id":7,"owner_id":1,"name":"Airmius SC","can_manage":true,"is_member":true,"management":{"can_manage":true,"permissions":{"can_manage_members":true},"membership_types":[],"contribution_intervals":["monthly","yearly"],"contribution_policy_documents":[{"id":11,"title":"Beitragsordnung","version_label":"2026","valid_from":"2026-01-01","valid_until":"2026-12-31"}],"contribution_rules":[{"id":31,"club_policy_document_id":11,"policy_document":{"id":11,"title":"Beitragsordnung","version_label":"2026"},"name":"Jahresbeitrag","amount":"120.00","billing_interval":"yearly","valid_from":"2026-01-01","valid_until":"2026-12-31","factor_key":"standard","is_active":true}]}}}''',
    );
  }
}
