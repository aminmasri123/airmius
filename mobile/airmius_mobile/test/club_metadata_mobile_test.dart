import 'dart:convert';
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
  testWidgets(
    'selection rows add edit reorder remove validate and reload saved order',
    (tester) async {
      tester.view.physicalSize = const Size(360, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final transport = _OptionsTransport();
      await _mountMetadata(tester, transport);
      Future<void> edit() async {
        await tester.tap(find.byType(PopupMenuButton<String>).first);
        await tester.pumpAndSettle();
        await tester.tap(find.text('Bearbeiten').last);
        await tester.pumpAndSettle();
      }

      Future<void> tap(String key) async {
        tester.testTextInput.hide();
        await tester.pumpAndSettle();
        await tester.ensureVisible(find.byKey(ValueKey<String>(key)));
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(ValueKey<String>(key)));
        await tester.pumpAndSettle();
      }

      Future<void> enter(int index, String value) async {
        final input = find.byKey(ValueKey('metadata-option-$index'));
        await tester.ensureVisible(input);
        await tester.pumpAndSettle();
        await tester.enterText(input, value);
      }

      Future<void> save() async {
        tester.testTextInput.hide();
        await tester.pumpAndSettle();
        await tester.tap(find.text('Speichern'));
        await tester.pumpAndSettle();
      }

      await edit();
      expect(
        tester
            .widget<TextFormField>(
              find.byKey(const ValueKey('metadata-option-0')),
            )
            .controller!
            .text,
        'S',
      );
      await tap('option-down-0');
      await enter(1, ' XS ');
      await tap('option-remove-2');
      await tap('option-add');
      await save();
      expect(find.text('Bitte gib einen Auswahlwert ein.'), findsWidgets);
      expect(transport.requests.where((r) => r.method == 'PUT'), isEmpty);
      await enter(2, 'm');
      await save();
      expect(
        find.text('Dieser Auswahlwert ist bereits vorhanden.'),
        findsWidgets,
      );
      expect(transport.requests.where((r) => r.method == 'PUT'), isEmpty);
      await enter(2, 'XL');
      await save();
      expect(transport.savedOptions, ['M', 'XS', 'XL']);
      await edit();
      expect(
        tester
            .widget<TextFormField>(
              find.byKey(const ValueKey('metadata-option-1')),
            )
            .controller!
            .text,
        'XS',
      );
      await enter(0, 'XXL');
      await save();
      expect(transport.savedOptions, ['XXL', 'XS', 'XL']);
      expect(transport.requests.where((r) => r.method == 'PUT'), hasLength(2));
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('field errors explain invalid input and preserve entries', (
    tester,
  ) async {
    final transport = _MetadataTransport();
    await _mountMetadata(tester, transport);
    await tester.tap(find.text('Eintrag anlegen'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Speichern'));
    await tester.pumpAndSettle();
    expect(
      find.text('Bitte einen Namen mit höchstens 160 Zeichen eingeben.'),
      findsOneWidget,
    );
    final name = find.byKey(const ValueKey('metadata-field-label'));
    final key = find.byKey(const ValueKey('metadata-field-key'));
    await tester.enterText(name, 'Trikotnummer');
    await tester.enterText(key, 'Ungültiger Schlüssel!');
    await tester.tap(find.text('Speichern'));
    await tester.pumpAndSettle();
    expect(
      find.textContaining('Mit einem Kleinbuchstaben beginnen.'),
      findsOneWidget,
    );
    expect(find.text('Trikotnummer'), findsOneWidget);
    expect(transport.requests.where((r) => r.method == 'POST'), isEmpty);
    await tester.enterText(key, 'trikotnummer');
    await tester.tap(find.text('Speichern'));
    await tester.pumpAndSettle();
    expect(transport.requests.where((r) => r.method == 'POST'), hasLength(1));
  });

  for (final network in [false, true]) {
    testWidgets('server failure keeps form and values; network=$network', (
      tester,
    ) async {
      final transport = _MetadataTransport(
        failWriteOnce: true,
        networkFailure: network,
      );
      await _mountMetadata(tester, transport);
      await tester.tap(find.text('Kategorien'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Eintrag anlegen'));
      await tester.pumpAndSettle();
      final name = find.byKey(const ValueKey('metadata-field-name'));
      await tester.enterText(name, 'Jugend');
      await tester.tap(find.text('Speichern'));
      await tester.pumpAndSettle();
      expect(find.text('Kategorie anlegen'), findsOneWidget);
      expect(find.text('Jugend'), findsOneWidget);
      expect(find.textContaining('AirmiusApiException'), findsNothing);
      if (network) {
        expect(
          find.textContaining('Keine Verbindung zum Server.'),
          findsOneWidget,
        );
      } else {
        expect(find.text('Dieser Name ist bereits vergeben.'), findsWidgets);
        await tester.enterText(name, 'Neue Kategorie');
      }
      await tester.tap(find.text('Speichern'));
      await tester.pumpAndSettle();
      expect(find.text('Kategorie anlegen'), findsNothing);
      expect(transport.requests.where((r) => r.method == 'POST'), hasLength(2));
      expect(tester.takeException(), isNull);
    });
  }
  const editors = [
    ('Felder', 'Feld anlegen', 'Feld bearbeiten', 'custom-fields', 'label'),
    (
      'Kategorien',
      'Kategorie anlegen',
      'Kategorie bearbeiten',
      'categories',
      'name',
    ),
    (
      'Nummernkreise',
      'Nummernkreis anlegen',
      'Nummernkreis bearbeiten',
      'number-ranges',
      'name',
    ),
  ];
  for (final width in [320.0, 900.0]) {
    for (final editor in editors) {
      testWidgets('${editor.$2} is a page and saves at $width', (tester) async {
        await tester.binding.setSurfaceSize(Size(width, 844));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final transport = _MetadataTransport();
        await _mountMetadata(tester, transport);
        await tester.tap(find.text(editor.$1));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Eintrag anlegen'));
        await tester.pumpAndSettle();
        expect(find.text(editor.$2), findsOneWidget);
        expect(find.byType(AlertDialog), findsNothing);
        expect(find.byType(BackButton), findsOneWidget);
        await tester.tap(find.text('Speichern'));
        await tester.pumpAndSettle();
        expect(transport.requests.where((r) => r.method == 'POST'), isEmpty);
        final name = find.widgetWithText(TextField, 'Name');
        await tester.ensureVisible(name);
        await tester.enterText(name, 'Neuer Eintrag');
        if (editor.$4 == 'custom-fields') {
          final key = find.widgetWithText(TextField, 'Technischer Schlüssel');
          await tester.ensureVisible(key);
          await tester.enterText(key, 'new_entry');
        }
        if (width == 320) {
          tester.view.viewInsets = const FakeViewPadding(bottom: 300);
          addTearDown(tester.view.resetViewInsets);
          await tester.pumpAndSettle();
          expect(
            tester.getRect(find.text('Speichern')).bottom,
            lessThan(844 - 300 / tester.view.devicePixelRatio),
          );
        }
        await tester.tap(find.text('Speichern'));
        await tester.pumpAndSettle();
        final request = transport.requests.singleWhere(
          (r) => r.method == 'POST',
        );
        expect(request.path, '/api/v1/clubs/7/metadata/${editor.$4}');
        expect(request.body?[editor.$5], 'Neuer Eintrag');
        expect(find.text(editor.$2), findsNothing);
        expect(tester.takeException(), isNull);
      });
    }
  }
  for (final editor in editors) {
    testWidgets('${editor.$3} uses a page and back cancels', (tester) async {
      final transport = _MetadataTransport();
      await _mountMetadata(tester, transport);
      await tester.tap(find.text(editor.$1));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(PopupMenuButton<String>).first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Bearbeiten').last);
      await tester.pumpAndSettle();
      expect(find.text(editor.$3), findsOneWidget);
      expect(find.byType(AlertDialog), findsNothing);
      await tester.tap(find.byType(BackButton));
      await tester.pumpAndSettle();
      expect(transport.requests.where((r) => r.method != 'GET'), isEmpty);
    });
  }
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

Future<void> _mountMetadata(
  WidgetTester tester,
  AirmiusApiTransport transport,
) async {
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
        child: MaterialApp(home: ClubMetadataScreen(club: club)),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _MetadataTransport implements AirmiusApiTransport {
  _MetadataTransport({this.failWriteOnce = false, this.networkFailure = false});
  bool failWriteOnce;
  final bool networkFailure;
  final requests = <AirmiusApiRequest>[];
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (request.method == 'POST' && failWriteOnce) {
      failWriteOnce = false;
      if (networkFailure) throw StateError('Connection lost');
      return const AirmiusApiResponse(
        statusCode: 422,
        body:
            '{"message":"Validation failed","errors":{"name":["Dieser Name ist bereits vergeben."]}}',
      );
    }
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

class _OptionsTransport extends _MetadataTransport {
  List<dynamic>? savedOptions;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    final response = await super.send(request);
    if (request.method == 'PUT' && request.path.endsWith('/custom-fields/2')) {
      savedOptions = List.from(request.body!['options'] as List);
    }
    if (request.method == 'GET' && savedOptions != null) {
      final data = jsonDecode(response.body);
      data['data']['custom_fields'][0]['options'] = savedOptions;
      return AirmiusApiResponse(statusCode: 200, body: jsonEncode(data));
    }
    return response;
  }
}
