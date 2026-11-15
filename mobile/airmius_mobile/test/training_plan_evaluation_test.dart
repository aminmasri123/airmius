import 'dart:async';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_theme.dart';
import 'package:airmius/screens/training_plan_evaluation_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Map<String, dynamic> fixture({int page = 1}) => {
  'plans': [
    {'id': 7, 'title': 'Wochenplan Müller'},
  ],
  'plan_id': 7,
  'weeks': ['2026-11-09', 'unscheduled'],
  'summary': {
    'athletes': 21,
    'reported': 1,
    'missing': 20,
    'feedback_open': 1,
    'pain': 1,
  },
  'pagination': {'page': page, 'last_page': 2, 'total': 21},
  'rows': [
    {
      'id': page,
      'name': page == 1 ? 'Sportler A' : 'Sportler B',
      'teams': ['Verein A · Team A'],
      'reported': 1,
      'feedback_open': 1,
      'pain': true,
      'sessions': [
        {
          'id': 10,
          'title': 'Montag',
          'scheduled_at': '2026-11-09T12:00:00Z',
          'missing': false,
          'reports': [
            {
              'id': 42,
              'status': 'partial',
              'performed_at': '2026-11-09T13:00:00Z',
              'pain': 3,
              'rpe': 7,
              'notes': 'Nur ein Satz geschafft.',
              'feedback_open': true,
              'snapshot': true,
              'feedback': [
                {
                  'id': 1,
                  'role': 'athlete',
                  'author': 'Sportler A',
                  'created_at': '2026-11-09T13:00:00Z',
                  'body': 'Bitte Übung anpassen.',
                },
              ],
              'exercises': [
                {
                  'title': 'Liegestütze',
                  'planned': [
                    {'set_index': 1, 'reps': 20},
                    {'set_index': 2, 'reps': 20},
                  ],
                  'actual': [
                    {'set_index': 1, 'reps': 12, 'completed': true},
                    {
                      'set_index': 2,
                      'reps': 99,
                      'completed': false,
                      'skip_reason': 'Schmerzen',
                    },
                  ],
                },
              ],
            },
          ],
        },
      ],
    },
  ],
};

Future<void> pumpScreen(
  WidgetTester tester,
  EvaluationLoader load, {
  EvaluationReply? reply,
}) async {
  await tester.binding.setSurfaceSize(const Size(390, 844));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(
    AirmiusScope(
      language: AirmiusLanguage.de,
      setLanguage: (_) {},
      child: MaterialApp(
        theme: AirmiusTheme.dark(AirmiusThemePalette.dark),
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(
            context,
          ).copyWith(textScaler: const TextScaler.linear(1.3)),
          child: child!,
        ),
        home: TrainingPlanEvaluationScreen(
          load: load,
          reply: reply ?? (_, _) async {},
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets(
    'planned and actual sets, skipped sets and feedback fit narrow screens',
    (tester) async {
      await pumpScreen(tester, (_) async => fixture());
      final athlete = find.text('Sportler A');
      await tester.ensureVisible(athlete);
      await tester.pumpAndSettle();
      await tester.tap(athlete);
      await tester.pumpAndSettle();
      expect(find.text('Geplant'), findsOneWidget);
      expect(find.text('Tatsächlich'), findsOneWidget);
      expect(find.text('Satz 1: 20 Wdh.'), findsOneWidget);
      expect(find.text('Satz 1: 12 Wdh.'), findsOneWidget);
      expect(find.text('Satz 2: Nicht abgeschlossen'), findsOneWidget);
      expect(find.textContaining('99 Wdh.'), findsNothing);
      expect(find.text('Bitte Übung anpassen.'), findsOneWidget);
      expect(find.textContaining('Teilweise absolviert'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('pagination and pending filter reach the loader', (tester) async {
    final requests = <Map<String, String>>[];
    await pumpScreen(tester, (filters) async {
      requests.add(filters);
      return fixture(page: int.parse(filters['page']!));
    });
    await tester.ensureVisible(find.text('Weiter'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Weiter'));
    await tester.pumpAndSettle();
    expect(requests.last['page'], '2');
    expect(find.text('Sportler B'), findsOneWidget);
    expect(find.text('Sportler A'), findsNothing);
    await tester.ensureVisible(find.byType(CheckboxListTile));
    await tester.pumpAndSettle();
    await tester.tap(find.byType(CheckboxListTile));
    await tester.pumpAndSettle();
    expect(requests.last['page'], '1');
    expect(requests.last['open_only'], '1');
    expect(tester.takeException(), isNull);
  });

  testWidgets('reply is sent once and refreshes the review', (tester) async {
    var loads = 0;
    var sends = 0;
    final pending = Completer<void>();
    await pumpScreen(
      tester,
      (_) async {
        loads++;
        return fixture();
      },
      reply: (id, body) async {
        expect(id, 42);
        expect(body, 'Bitte weniger Wiederholungen.');
        sends++;
        await pending.future;
      },
    );
    await tester.ensureVisible(find.text('Sportler A'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Sportler A'));
    await tester.pumpAndSettle();
    final field = find.widgetWithText(
      TextField,
      'Antwort oder Anpassung für den Sportler',
    );
    await tester.ensureVisible(field);
    await tester.pumpAndSettle();
    await tester.enterText(field, 'Bitte weniger Wiederholungen.');
    await tester.ensureVisible(find.text('Antwort senden'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Antwort senden'));
    await tester.pump();
    await tester.tap(find.text('Antwort senden'));
    await tester.pump();
    expect(sends, 1);
    pending.complete();
    await tester.pumpAndSettle();
    expect(loads, 2);
    expect(tester.takeException(), isNull);
  });

  testWidgets('failed refresh removes previously visible athlete data', (
    tester,
  ) async {
    var loads = 0;
    await pumpScreen(tester, (_) async {
      if (++loads > 1) throw StateError('Access revoked');
      return fixture();
    });
    expect(find.text('Sportler A'), findsOneWidget);
    await tester.tap(find.byTooltip('Aktualisieren'));
    await tester.pumpAndSettle();
    expect(find.text('Sportler A'), findsNothing);
    expect(find.byKey(const Key('evaluation-error')), findsOneWidget);
  });

  testWidgets('permission revoked while replying clears protected report', (
    tester,
  ) async {
    await pumpScreen(
      tester,
      (_) async => fixture(),
      reply: (_, _) async {
        throw const AirmiusApiException(
          statusCode: 404,
          body: '',
          path: '/trainer-cockpit/plan-evaluation/logs/42/feedback',
        );
      },
    );
    await tester.ensureVisible(find.text('Sportler A'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Sportler A'));
    await tester.pumpAndSettle();
    final field = find.widgetWithText(
      TextField,
      'Antwort oder Anpassung für den Sportler',
    );
    await tester.ensureVisible(field);
    await tester.pumpAndSettle();
    await tester.enterText(field, 'Bitte weniger Wiederholungen.');
    await tester.ensureVisible(find.text('Antwort senden'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Antwort senden'));
    await tester.pumpAndSettle();
    expect(find.text('Sportler A'), findsNothing);
    expect(find.text('Bitte Übung anpassen.'), findsNothing);
    expect(find.byKey(const Key('evaluation-error')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
