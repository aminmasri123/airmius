import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/exercise_suitability.dart';
import 'package:airmius/screens/exercise_edit_screen.dart';
import 'package:airmius/widgets/exercise_sports_field.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Widget host(Widget child) => AirmiusScope(
  language: AirmiusLanguage.de,
  setLanguage: (_) {},
  child: MaterialApp(home: child),
);

class CaptureTransport implements AirmiusApiTransport {
  AirmiusApiRequest? request;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    this.request = request;
    // Keep the editor open to also verify that a failed save retains its selection.
    return const AirmiusApiResponse(
      statusCode: 422,
      body: '{"message":"Bitte prüfen"}',
    );
  }
}

void main() {
  test('explicit general selection takes precedence over legacy sport', () {
    expect(exerciseSports({}), isEmpty);
    expect(exerciseSports({'sport_type': 'Laufen'}), ['Laufen']);
    expect(
      exerciseSports({'sport_types': [], 'sport_type': 'Laufen'}),
      isEmpty,
    );
  });

  testWidgets(
    'search finds sports in a long catalog and keeps choices across queries',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(360, 740));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      List<String>? saved;
      await tester.pumpWidget(
        host(
          Scaffold(
            body: ExerciseSportsField(
              selected: const [],
              sports: [
                for (var i = 0; i < 140; i++) {'value': 'Sport $i'},
                {'value': 'Fußball'},
                {'value': 'Straßenlauf'},
              ],
              onChanged: (value) => saved = value,
            ),
          ),
        ),
      );
      expect(find.text('Sportübergreifend'), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('exercise-suitability')));
      await tester.pumpAndSettle();
      expect(find.byType(ExerciseSportsPicker), findsOneWidget);
      expect(find.byType(AlertDialog), findsNothing);
      await tester.enterText(
        find.byKey(const ValueKey('exercise-sport-search')),
        'fuss',
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Fußball'));
      await tester.enterText(
        find.byKey(const ValueKey('exercise-sport-search')),
        'strassen',
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Straßenlauf'));
      await tester.pump();
      await tester.tap(find.text('Auswahl übernehmen (2)'));
      await tester.pumpAndSettle();
      expect(saved, ['Fußball', 'Straßenlauf']);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'general reset and cancelling do not silently change the original selection',
    (tester) async {
      List<String>? saved;
      await tester.pumpWidget(
        host(
          Scaffold(
            body: ExerciseSportsField(
              selected: const ['Fußball', 'Straßenlauf'],
              sports: const [],
              onChanged: (value) => saved = value,
            ),
          ),
        ),
      );
      await tester.tap(find.byKey(const ValueKey('exercise-suitability')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Sportübergreifend'));
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(saved, isNull);
      await tester.tap(find.byKey(const ValueKey('exercise-suitability')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Sportübergreifend'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Auswahl übernehmen'));
      await tester.pumpAndSettle();
      expect(saved, isEmpty);
    },
  );

  testWidgets(
    'editing saves sport suitability separately from category and retains both on failure',
    (tester) async {
      final transport = CaptureTransport();
      await tester.pumpWidget(
        host(
          ExerciseEditScreen(
            client: AirmiusApiClient(
              transport: transport,
              baseUrl: 'https://example.com',
              token: 'test',
            ),
            exercise: const {
              'id': 7,
              'name': 'Liegestütze',
              'original_locale': 'de',
              'sport_types': ['Fußball', 'Straßenlauf'],
              'category': 'strength',
            },
            sports: const [
              {'value': 'Fußball'},
              {'value': 'Straßenlauf'},
            ],
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.byKey(const ValueKey('exercise-category')),
        400,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(find.byKey(const ValueKey('exercise-category')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Aufwärmen').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Übung speichern'));
      await tester.pumpAndSettle();
      expect(transport.request?.body?['sport_types'], [
        'Fußball',
        'Straßenlauf',
      ]);
      expect(transport.request?.body?['category'], 'warmup');
      expect(find.text('Aufwärmen'), findsOneWidget);
      expect(find.byType(ExerciseEditScreen), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
