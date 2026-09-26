import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/club_metadata_subject_screen.dart';

void main() {
  test('subject editor labels exist in every supported language', () {
    for (final language in AirmiusLanguage.values) {
      final scope = AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: const SizedBox.shrink(),
      );
      for (final key in [
        'clubMetadataValues.title',
        'clubMetadataValues.action',
        'clubMetadataValues.required',
        'clubMetadataValues.sensitive',
        'clubMetadataValues.inactiveHistory',
        'clubMetadataValues.saved',
      ]) {
        expect(scope.t(key), isNot(key), reason: '${language.name}: $key');
      }
    }
  });

  test('team and event models preserve metadata permission', () {
    final team = AirmiusTeam.fromJson(const {
      'id': 2,
      'club_id': 1,
      'name': 'U18',
      'can_manage_metadata': true,
    });
    final event = AirmiusEvent.fromJson(const {
      'id': 3,
      'title': 'Training',
      'start_time': '2026-09-24T18:00:00Z',
      'can_manage_metadata': true,
    });
    expect(team.canManageMetadata, isTrue);
    expect(event.canManageMetadata, isTrue);
  });

  testWidgets('subject editor renders types, history and saves active values', (
    tester,
  ) async {
    final transport = _SubjectTransport();
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
          child: const MaterialApp(
            home: ClubMetadataSubjectScreen(
              clubId: 7,
              subjectType: 'team',
              subjectId: 9,
              subjectTitle: 'U18',
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('U18'), findsOneWidget);
    expect(find.textContaining('Kurztext · Pflichtfeld'), findsOneWidget);
    expect(find.textContaining('Medizin · Sensibel'), findsOneWidget);
    expect(find.textContaining('Historie · Inaktiv'), findsOneWidget);
    expect(find.text('Historische Zuordnung, nur lesbar'), findsOneWidget);
    expect(find.text('Leistung'), findsOneWidget);

    await tester.drag(find.byType(ListView), const Offset(0, -600));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Speichern'));
    await tester.pumpAndSettle();

    final put = transport.requests.singleWhere(
      (request) => request.method == 'PUT',
    );
    expect(put.path, '/api/v1/clubs/7/metadata/subjects/team/9');
    expect(put.body?['values'], containsPair('1', 'Vorhanden'));
    expect(put.body?['values'], isNot(contains('3')));
    expect(put.body?['category_ids'], [4]);
  });
}

class _SubjectTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];

  static const body =
      '''{"data":{"subject_type":"team","subject_id":9,"entity_type":"team","fields":[{"id":1,"key":"short","label":"Kurztext","field_type":"text","options":null,"is_required":true,"is_sensitive":false,"is_active":true,"sort_order":0,"value":"Vorhanden"},{"id":2,"key":"medical","label":"Medizin","field_type":"textarea","options":null,"is_required":false,"is_sensitive":true,"is_active":true,"sort_order":1,"value":"Intern"},{"id":3,"key":"history","label":"Historie","field_type":"text","options":null,"is_required":false,"is_sensitive":false,"is_active":false,"sort_order":2,"value":"Bleibt"}],"categories":[{"id":4,"name":"Leistung","color":"#123ABC","is_active":true,"sort_order":0,"selected":true},{"id":5,"name":"Alt","color":"#999999","is_active":false,"sort_order":1,"selected":true}]}}''';

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    return const AirmiusApiResponse(statusCode: 200, body: body);
  }
}
