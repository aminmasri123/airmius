import 'dart:convert';
import 'dart:typed_data';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/exercise_content.dart';
import 'package:airmius/core/exercise_content_upload.dart';
import 'package:airmius/screens/exercise_edit_screen.dart';
import 'package:airmius/screens/training_plans_logs_screen.dart';
import 'package:airmius/widgets/exercise_content_editor.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

class _Transport implements AirmiusApiTransport {
  AirmiusApiRequest? last;
  int status = 200;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    last = request;
    return AirmiusApiResponse(
      statusCode: status,
      body: status == 200
          ? '{"data":{"id":1}}'
          : '{"message":"Bitte Eingaben prüfen","errors":{"name":["Name überprüfen"]}}',
    );
  }
}

Map<String, dynamic> fixture() => {
  'id': 7,
  'name': 'Squat',
  'title': 'Squat',
  'original_locale': 'de',
  'original_content': {
    'title': 'Kniebeuge',
    'description': 'Beschreibung',
    'instructions': 'Absenken',
    'notes': 'Kontrolle',
    'todos': ['Atmen'],
  },
  'translations': {
    'fr': {'title': 'Squat', 'instructions': 'Descendez'},
    'en': {'title': 'Squat', 'instructions': ' '},
  },
  'explanation_images': [
    {
      'id': 'first',
      'url': 'https://example.com/first.jpg',
      'caption': 'Départ',
      'texts': {
        'de': {'caption': 'Start'},
        'fr': {'caption': 'Départ'},
      },
    },
  ],
};

Widget host(Widget child, {AirmiusLanguage language = AirmiusLanguage.de}) =>
    AirmiusScope(
      language: language,
      setLanguage: (_) {},
      child: MaterialApp(home: child),
    );

void main() {
  testWidgets(
    'plan item editor keeps the original content through all steps and a failed save',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(390, 844));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      Map<String, dynamic>? saved;
      final initial = fixture()..['explanation_images'] = <Object>[];
      await tester.pumpWidget(
        host(
          buildTrainingPlanItemEditorPreview(
            initial: initial,
            onSave: (payload) async {
              saved = payload;
              throw AirmiusApiException(
                statusCode: 422,
                body: '{"message":"Bitte prüfen"}',
                path: '/test',
              );
            },
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const ValueKey('de:de:title')),
        'Aus der App bearbeitet',
      );
      await tester.tap(find.text('Weiter').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Weiter').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Sicher speichern').last);
      await tester.pumpAndSettle();
      expect(saved?['title'], 'Aus der App bearbeitet');
      expect(saved?['instructions'], 'Absenken');
      expect(saved?['translations']['fr']['instructions'], 'Descendez');
      expect(find.text('Sicher speichern'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  test('partial translations and language switches fall back to originals', () {
    final en = localizeExercise(fixture(), 'en-US');
    expect(en['title'], 'Squat');
    expect(en['instructions'], 'Absenken');
    expect(en['todos'], ['Atmen']);
    expect(en['uses_original_fallback'], true);
    final ar = localizeExercise(fixture(), 'ar');
    expect(ar['title'], 'Kniebeuge');
    expect((ar['explanation_images'] as List).first['caption'], 'Start');
    expect(localizeExercise(fixture(), 'de')['uses_original_fallback'], false);
  });

  test(
    'editor loads originals and keeps translated names out of original fields',
    () {
      final draft = ExerciseContentDraft(fixture());
      expect(draft.originalTexts['title'], 'Kniebeuge');
      draft.translations['fr']!['title'] = 'Nouveau squat';
      final payload = draft.toPayload();
      expect(payload['title'], 'Kniebeuge');
      expect(payload['todos'], ['Atmen']);
      expect(payload['translations']['fr']['title'], 'Nouveau squat');
      expect(payload['translations'].containsKey('de'), false);
      expect(payload['explanation_images'][0]['id'], 'first');
    },
  );

  test(
    'missing image original does not reuse a previously displayed translation',
    () {
      final source = fixture()
        ..['explanation_images'] = [
          {
            'id': 'one',
            'caption': 'Français',
            'texts': {
              'fr': {'caption': 'Français'},
            },
          },
        ];
      expect(
        localizeExercise(source, 'ar')['explanation_images'][0]['caption'],
        '',
      );
      expect(localizeExercise({'name': 'Legacy'}, 'fr')['title'], 'Legacy');
    },
  );

  testWidgets('language selector preserves both versions in the page editor', (
    tester,
  ) async {
    final draft = ExerciseContentDraft({
      'title': 'Original',
      'original_locale': 'de',
    });
    await tester.pumpWidget(
      host(
        Scaffold(
          body: SingleChildScrollView(
            child: ExerciseContentEditor(draft: draft, onChanged: () {}),
          ),
        ),
      ),
    );
    await tester.tap(find.byKey(const ValueKey('content-de')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Français').last);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey('fr:de:title')),
      'Traduction',
    );
    expect(draft.originalTexts['title'], 'Original');
    expect(draft.translations['fr']!['title'], 'Traduction');
    await tester.tap(find.byKey(const ValueKey('content-fr')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Deutsch').last);
    await tester.pumpAndSettle();
    expect(find.text('Original'), findsOneWidget);
  });

  testWidgets('failed save stays on the full page and keeps entered text', (
    tester,
  ) async {
    final transport = _Transport()..status = 422;
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://example.com',
      token: 'test',
      locale: 'fr',
    );
    await tester.pumpWidget(
      host(
        Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => ExerciseEditScreen(
                    exercise: {
                      'id': 7,
                      'name': 'Original',
                      'original_locale': 'de',
                    },
                    client: client,
                  ),
                ),
              ),
              child: const Text('Open'),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Open'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey('de:de:title')),
      'Neuer Originalname',
    );
    await tester.tap(find.text('Übung speichern'));
    await tester.pumpAndSettle();
    expect(find.byType(ExerciseEditScreen), findsOneWidget);
    expect(find.text('Neuer Originalname'), findsOneWidget);
    expect(transport.last!.body!['name'], 'Neuer Originalname');
    expect(transport.last!.body!['original_locale'], 'de');
    expect(transport.last!.headers['X-Airmius-Locale'], 'fr');
    expect(find.byType(AlertDialog), findsNothing);
  });

  test(
    'JSON updates preserve explicit empty gallery and authorization',
    () async {
      final transport = _Transport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.com',
        token: 'test',
        locale: 'ar',
      );
      await saveExerciseContent(
        client: client,
        path: '/api/v1/training/exercises/7',
        payload: {'name': 'Original', 'explanation_images': <Object>[]},
        updating: true,
      );
      expect(transport.last!.method, 'PUT');
      expect(transport.last!.body!['explanation_images'], isEmpty);
      expect(transport.last!.headers['Authorization'], 'Bearer test');
      expect(transport.last!.headers['X-Airmius-Locale'], 'ar');
    },
  );

  testWidgets(
    'image order and removal retain captions for the remaining image',
    (tester) async {
      final draft = ExerciseContentDraft({
        'title': 'Original',
        'original_locale': 'de',
      });
      draft.images.addAll([
        ExerciseImageDraft(
          id: 'first',
          texts: {
            'de': {'caption': 'Start'},
          },
        ),
        ExerciseImageDraft(
          id: 'second',
          texts: {
            'de': {'caption': 'Ende'},
          },
        ),
      ]);
      await tester.pumpWidget(
        host(
          Scaffold(
            body: SingleChildScrollView(
              child: ExerciseContentEditor(
                draft: draft,
                onChanged: () {},
                showTextFields: false,
              ),
            ),
          ),
        ),
      );
      await tester.ensureVisible(find.byTooltip('Nach unten').first);
      await tester.tap(find.byTooltip('Nach unten').first);
      await tester.pumpAndSettle();
      expect(draft.images.map((image) => image.id), ['second', 'first']);
      await tester.ensureVisible(find.text('Entfernen').first);
      await tester.tap(find.text('Entfernen').first);
      await tester.pumpAndSettle();
      expect(draft.images.single.id, 'first');
      expect(
        draft.toPayload()['explanation_images'][0]['texts']['de']['caption'],
        'Start',
      );
    },
  );

  test('multipart carries nested image captions and method override', () async {
    final transport = _Transport();
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://example.com/prefix',
      token: 'test',
      locale: 'fr',
    );
    late http.Request received;
    final sender = MockClient((request) async {
      received = request;
      return http.Response('{"data":{"id":7}}', 200);
    });
    final result = await saveExerciseContent(
      client: client,
      path: '/api/v1/training/exercises/7',
      updating: true,
      httpClient: sender,
      payload: {
        'name': 'Original',
        'original_locale': 'de',
        'todos': <Object>[],
        'translations': {
          'fr': {'title': 'Traduction'},
        },
        'explanation_images': [
          {
            'id': 'new',
            'texts': {
              'fr': {'caption': 'Départ'},
            },
            'file': PlatformFile(
              name: 'image.png',
              size: 4,
              bytes: Uint8List.fromList([1, 2, 3, 4]),
            ),
          },
        ],
      },
    );
    expect(result['data']['id'], 7);
    expect(received.url.path, '/prefix/api/v1/training/exercises/7');
    final body = utf8.decode(received.bodyBytes, allowMalformed: true);
    expect(body, contains('name="_method"'));
    expect(body, contains('PUT'));
    expect(body, contains('name="explanation_images[0][file]"'));
    expect(body, contains('image/png'));
    expect(body, contains('name="translations[fr][title]"'));
    expect(body, contains('Départ'));
    expect(body, contains('name="content_empty_arrays[0]"'));
    expect(body, contains('todos'));
    expect(received.headers['Authorization'], 'Bearer test');
    sender.close();
  });
}
