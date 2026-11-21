import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/club_metadata_subject_screen.dart';

void main() {
  for (final readOnly in [false, true]) {
    testWidgets(
      'older subject response resolves edit rights from server; readOnly=$readOnly',
      (tester) async {
        final transport = _SubjectTransport(legacy: true, readOnly: readOnly);
        await tester.pumpWidget(_subjectApp(transport));
        await tester.pumpAndSettle();
        expect(
          transport.requests.where((r) => r.path == '/api/v1/clubs/7/metadata'),
          hasLength(1),
        );
        expect(
          find.text('Nur Leserechte – Änderungen sind nicht erlaubt.'),
          readOnly ? findsOneWidget : findsNothing,
        );
        expect(
          tester
              .widget<TextFormField>(
                find.byKey(const ValueKey('metadata-value-1')),
              )
              .enabled,
          !readOnly,
        );
        if (!readOnly) {
          await tester.tap(find.text('Speichern'));
          await tester.pumpAndSettle();
          expect(find.byKey(const ValueKey('metadata-manage')), findsOneWidget);
          expect(
            tester
                .widget<TextFormField>(
                  find.byKey(const ValueKey('metadata-value-1')),
                )
                .enabled,
            true,
          );
        } else {
          expect(find.text('Speichern'), findsNothing);
          expect(transport.requests.where((r) => r.method == 'PUT'), isEmpty);
        }
        expect(tester.takeException(), isNull);
      },
    );
  }
  testWidgets(
    'unavailable legacy permission is an error, not a read-only decision',
    (tester) async {
      await tester.pumpWidget(
        _subjectApp(_SubjectTransport(legacy: true, failPermission: true)),
      );
      await tester.pumpAndSettle();
      expect(
        find.text('Nur Leserechte – Änderungen sind nicht erlaubt.'),
        findsNothing,
      );
      expect(find.text('Speichern'), findsNothing);
      expect(find.text('Erneut versuchen'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
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
    await tester.drag(find.byType(ListView), const Offset(0, -300));
    await tester.pumpAndSettle();
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
  for (final readOnly in [true, false]) {
    testWidgets(
      'inline long list keeps management and save reachable; readOnly=$readOnly',
      (tester) async {
        tester.view.physicalSize = const Size(360, 760);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final transport = _SubjectTransport(readOnly: readOnly, longList: true);
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
        expect(find.byType(TextFormField).first, findsOneWidget);
        expect(
          find.byKey(const ValueKey('metadata-manage')),
          readOnly ? findsNothing : findsOneWidget,
        );
        expect(
          find.text('Speichern'),
          readOnly ? findsNothing : findsOneWidget,
        );
        expect(
          tester
              .widget<TextFormField>(find.byType(TextFormField).first)
              .enabled,
          !readOnly,
        );
        await tester.scrollUntilVisible(
          find.byKey(const ValueKey('metadata-value-49')),
          450,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.pumpAndSettle();
        if (!readOnly) {
          expect(
            tester
                .getRect(find.byKey(const ValueKey('metadata-manage')))
                .bottom,
            lessThan(250),
          );
          expect(
            tester
                .getRect(find.byKey(const ValueKey('metadata-value-49')))
                .bottom,
            lessThanOrEqualTo(tester.getRect(find.text('Speichern')).top),
          );
          tester.view.viewInsets = const FakeViewPadding(bottom: 280);
          addTearDown(tester.view.resetViewInsets);
          await tester.pumpAndSettle();
          expect(
            tester.getRect(find.text('Speichern')).bottom,
            lessThanOrEqualTo(480),
          );
        }
        expect(transport.requests.where((r) => r.method == 'PUT'), isEmpty);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'inline draft is preserved on save failure and parent save can retry',
    (tester) async {
      final key = GlobalKey<ClubMetadataSubjectScreenState>();
      final transport = _SubjectTransport(failSave: true);
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
            child: MaterialApp(
              home: Scaffold(
                body: ClubMetadataSubjectScreen(
                  key: key,
                  embedded: true,
                  clubId: 7,
                  subjectType: 'team',
                  subjectId: 9,
                  subjectTitle: 'U18',
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Eigene Daten bearbeiten'), findsNothing);
      await tester.enterText(
        find.byKey(const ValueKey('metadata-value-1')),
        'Unsaved draft',
      );
      final save = key.currentState!.saveIfChanged();
      await tester.pumpAndSettle();
      expect(await save, false);
      expect(
        tester
            .widget<TextFormField>(
              find.byKey(const ValueKey('metadata-value-1')),
            )
            .controller!
            .text,
        'Unsaved draft',
      );
      expect(find.textContaining('Invalid value'), findsWidgets);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'draft survives a member-tab round trip and management permission refresh',
    (tester) async {
      final transport = _SubjectTransport();
      final key = GlobalKey<ClubMetadataSubjectScreenState>();
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
            child: MaterialApp(
              home: DefaultTabController(
                length: 4,
                initialIndex: 3,
                child: Scaffold(
                  appBar: AppBar(
                    bottom: const TabBar(
                      tabs: [
                        Tab(text: 'Member'),
                        Tab(text: 'Fee'),
                        Tab(text: 'Address'),
                        Tab(text: 'Data'),
                      ],
                    ),
                  ),
                  body: TabBarView(
                    children: [
                      const Text('First tab'),
                      const Text('Second tab'),
                      const Text('Third tab'),
                      ClubMetadataSubjectScreen(
                        key: key,
                        embedded: true,
                        clubId: 7,
                        subjectType: 'team',
                        subjectId: 9,
                        subjectTitle: 'U18',
                        onManage: () async {
                          transport.readOnly = true;
                        },
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const ValueKey('metadata-value-1')),
        'Keep this draft',
      );
      await tester.tap(find.text('Member'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Data'));
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<TextFormField>(
              find.byKey(const ValueKey('metadata-value-1')),
            )
            .controller!
            .text,
        'Keep this draft',
      );
      await tester.tap(find.byKey(const ValueKey('metadata-manage')));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('metadata-manage')), findsNothing);
      final input = tester.widget<TextFormField>(
        find.byKey(const ValueKey('metadata-value-1')),
      );
      expect(input.enabled, false);
      expect(input.controller!.text, 'Keep this draft');
      final saved = await key.currentState!.saveIfChanged();
      expect(saved, false);
      expect(transport.requests.where((r) => r.method == 'PUT'), isEmpty);
      expect(tester.takeException(), isNull);
    },
  );
}

class _SubjectTransport implements AirmiusApiTransport {
  _SubjectTransport({
    this.readOnly = false,
    this.longList = false,
    this.failSave = false,
    this.legacy = false,
    this.failPermission = false,
  });
  bool readOnly;
  final bool longList;
  final bool failSave;
  final bool legacy;
  final bool failPermission;
  final requests = <AirmiusApiRequest>[];

  static const body =
      '''{"data":{"subject_type":"team","subject_id":9,"entity_type":"team","fields":[{"id":1,"key":"short","label":"Kurztext","field_type":"text","options":null,"is_required":true,"is_sensitive":false,"is_active":true,"sort_order":0,"value":"Vorhanden"},{"id":2,"key":"medical","label":"Medizin","field_type":"textarea","options":null,"is_required":false,"is_sensitive":true,"is_active":true,"sort_order":1,"value":"Intern"},{"id":3,"key":"history","label":"Historie","field_type":"text","options":null,"is_required":false,"is_sensitive":false,"is_active":false,"sort_order":2,"value":"Bleibt"}],"categories":[{"id":4,"name":"Leistung","color":"#123ABC","is_active":true,"sort_order":0,"selected":true},{"id":5,"name":"Alt","color":"#999999","is_active":false,"sort_order":1,"selected":true}]}}''';

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (request.path == '/api/v1/clubs/7/metadata') {
      return AirmiusApiResponse(
        statusCode: failPermission ? 503 : 200,
        body: jsonEncode({
          'data': {'can_edit': !readOnly},
        }),
      );
    }
    if (request.method == 'PUT' && failSave)
      return const AirmiusApiResponse(
        statusCode: 422,
        body:
            '{"message":"Please check the value","errors":{"values.1":["Invalid value"]}}',
      );
    final data = jsonDecode(body) as Map<String, dynamic>;
    if (!legacy) data['data']['can_edit'] = !readOnly;
    if (longList) {
      for (var i = 10; i < 50; i++) {
        data['data']['fields'].add({
          'id': i,
          'label': 'Feld $i',
          'field_type': 'text',
          'value': '',
          'is_active': true,
        });
      }
    }
    return AirmiusApiResponse(statusCode: 200, body: jsonEncode(data));
  }
}

Widget _subjectApp(_SubjectTransport transport) {
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
      child: const MaterialApp(
        home: ClubMetadataSubjectScreen(
          clubId: 7,
          subjectType: 'team',
          subjectId: 9,
          subjectTitle: 'U18',
        ),
      ),
    ),
  );
}
