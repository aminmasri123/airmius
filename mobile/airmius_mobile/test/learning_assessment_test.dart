import 'dart:convert';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/learning_assessment.dart';
import 'package:airmius/core/learning_assessment_copy.dart';
import 'package:airmius/screens/learning_assessment_editor_screen.dart';
import 'package:airmius/widgets/learning_assessment_fields.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';

List<JsonMap> questions() => [
  {
    'id': 1,
    'question': 'Single',
    'question_type': 'single_choice',
    'options': [
      {'id': 'a', 'text': 'First'},
      {'id': 'b', 'text': 'Second'},
    ],
    'correct_options': ['a'],
  },
  {
    'id': 2,
    'question': 'Multiple',
    'question_type': 'multiple_choice',
    'options': [
      {'id': 'a', 'text': 'First'},
      {'id': 'b', 'text': 'Second'},
    ],
    'correct_options': ['a', 'b'],
  },
  {
    'id': 3,
    'question': 'Order',
    'question_type': 'ordering',
    'options': [
      {'id': 'b', 'text': 'Second'},
      {'id': 'a', 'text': 'First'},
    ],
    'correct_options': ['a', 'b'],
  },
  {
    'id': 4,
    'question': 'Short',
    'question_type': 'short_text',
    'options': [],
    'correct_options': ['Answer'],
  },
  {
    'id': 5,
    'question': 'Long',
    'question_type': 'long_text',
    'options': [],
    'correct_options': [],
  },
];

Future<void> mount(
  WidgetTester tester,
  Widget home, {
  AirmiusLanguage language = AirmiusLanguage.en,
}) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  addTearDown(tester.view.resetViewInsets);
  await tester.pumpWidget(
    AirmiusScope(
      language: language,
      setLanguage: (_) {},
      child: MaterialApp(
        locale: language.locale,
        supportedLocales: AirmiusLanguage.values.map((l) => l.locale),
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: home,
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  test(
    'legacy editing, image-only validation, and all types retain their identity',
    () {
      final legacy = editableAssessmentQuestions([
        {
          'id': 9,
          'question': 'Old',
          'options': ['Yes', 'No'],
          'correct_options': ['Yes'],
        },
      ]);
      expect(legacy.single['correct_options'], ['option_0']);
      expect(legacy.single['id'], 9);
      final q = newAssessmentQuestion();
      q['image_url'] = 'https://example.test/q.png';
      q['options'] = [
        {'id': 'a', 'image_url': 'https://example.test/a.png'},
        {'id': 'b', 'text': 'Other'},
      ];
      q['correct_options'] = ['a'];
      expect(assessmentEditorError([q], true), isNull);
      q['image_url'] = null;
      expect(assessmentEditorError([q], true), 'text_or_image');
      expect(
        assessmentEditorError(editableAssessmentQuestions(questions()), true),
        isNull,
      );
    },
  );

  for (final language in AirmiusLanguage.values) {
    testWidgets(
      'editable mixed quiz has spaced fields and keyboard-safe save in ${language.name}',
      (tester) async {
        JsonMap? saved;
        await mount(
          tester,
          LearningAssessmentEditorScreen(
            assignment: false,
            initial: {
              'id': 7,
              'title': 'Mixed quiz',
              'pass_percent': 70,
              'questions': questions(),
            },
            uploadImage: (_) async => 'https://example.test/image.png',
            onSave: (payload) async {
              saved = jsonDecode(jsonEncode(payload)) as JsonMap;
            },
          ),
          language: language,
        );
        final copy = learningAssessmentCopy[language.name]!;
        final title = find.byKey(const ValueKey('assessment-title'));
        final description = find.widgetWithText(
          TextFormField,
          copy['description']!,
        );
        expect(
          tester.getRect(description).top - tester.getRect(title).bottom,
          greaterThanOrEqualTo(12),
        );
        expect(find.byType(DropdownButtonFormField<String>), findsNWidgets(5));
        tester.view.viewInsets = const FakeViewPadding(bottom: 300);
        await tester.pumpAndSettle();
        expect(
          tester.getRect(find.byKey(const ValueKey('assessment-save'))).bottom,
          lessThanOrEqualTo(544),
        );
        tester.view.resetViewInsets();
        await tester.pumpAndSettle();
        await tester.tap(find.byKey(const ValueKey('assessment-save')));
        await tester.pumpAndSettle();
        expect(saved?['questions'], hasLength(5));
        expect(
          (saved!['questions'] as List).last['question_type'],
          'long_text',
        );
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'participants can answer every type and reorder without converting to a set',
    (tester) async {
      final answers = <String, dynamic>{};
      await mount(
        tester,
        Scaffold(
          body: SingleChildScrollView(
            child: LearningAssessmentAnswerFields(
              questions: questions(),
              answers: answers,
            ),
          ),
        ),
      );
      Future<void> tap(Finder finder) async {
        await tester.ensureVisible(finder);
        await tester.pumpAndSettle();
        await tester.tap(finder);
        await tester.pumpAndSettle();
      }

      Finder choice(String question, String text) => find.descendant(
        of: find.byKey(ValueKey('answer-$question')),
        matching: find.widgetWithText(CheckboxListTile, text),
      );
      await tap(choice('1', 'First'));
      await tap(choice('1', 'Second'));
      expect(answers['1'], ['b']);
      await tap(choice('2', 'First'));
      await tap(choice('2', 'Second'));
      expect(answers['2'], ['a', 'b']);
      await tap(find.byTooltip('Move up').last);
      expect(answers['3'], ['a', 'b']);
      for (final id in ['4', '5']) {
        final field = find.descendant(
          of: find.byKey(ValueKey('answer-$id')),
          matching: find.byType(TextFormField),
        );
        await tester.ensureVisible(field);
        await tester.pumpAndSettle();
        await tester.enterText(field, id == '4' ? 'Answer' : 'Long response');
      }
      expect(assessmentAnswersComplete(questions(), answers), isTrue);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'assignment edit retains data on save failure and closes only after success',
    (tester) async {
      var calls = 0;
      JsonMap? saved;
      await mount(
        tester,
        Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => LearningAssessmentEditorScreen(
                    assignment: true,
                    initial: {
                      'id': 6,
                      'title': 'Edited assignment',
                      'instructions': 'Instructions',
                      'points': 80,
                      'questions': [questions().last],
                    },
                    uploadImage: (_) async => 'https://example.test/image.png',
                    onSave: (payload) async {
                      calls++;
                      if (calls == 1) throw StateError('Network unavailable');
                      saved = payload;
                    },
                  ),
                ),
              ),
              child: const Text('Open editor'),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Open editor'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      expect(find.byType(LearningAssessmentEditorScreen), findsOneWidget);
      expect(find.text('Edited assignment'), findsOneWidget);
      await tester.tap(find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      expect(saved?['questions'], hasLength(1));
      expect(saved?['points'], 80);
      expect(find.byType(LearningAssessmentEditorScreen), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('manual review requires all long responses and sends feedback', (
    tester,
  ) async {
    JsonMap? saved;
    await mount(
      tester,
      LearningQuizReviewScreen(
        attempt: {
          'id': 1,
          'user': {'name': 'Student'},
          'question_snapshot': questions(),
          'answers': {
            '1': ['a'],
            '2': ['a', 'b'],
            '3': ['a', 'b'],
            '4': 'Answer',
            '5': 'Long answer',
          },
        },
        onSave: (payload) async {
          saved = payload;
        },
      ),
    );
    final button = find.widgetWithText(FilledButton, 'Save review');
    expect(tester.widget<FilledButton>(button).onPressed, isNull);
    final correct = find.widgetWithText(RadioListTile<bool>, 'Correct answer');
    await tester.ensureVisible(correct);
    await tester.pumpAndSettle();
    await tester.tap(correct);
    await tester.pumpAndSettle();
    final feedback = find.widgetWithText(TextField, 'Feedback');
    await tester.ensureVisible(feedback);
    await tester.pumpAndSettle();
    await tester.enterText(feedback, 'Good reasoning');
    await tester.tap(button);
    await tester.pumpAndSettle();
    expect(saved?['manual_grades'], {'5': true});
    expect(saved?['feedback'], 'Good reasoning');
    expect(tester.takeException(), isNull);
  });
}
