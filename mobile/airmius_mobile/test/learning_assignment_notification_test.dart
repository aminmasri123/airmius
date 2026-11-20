import 'dart:convert';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_deep_links.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/screens/learning_assessment_editor_screen.dart';
import 'package:airmius/screens/learning_studio_course_suite_screen.dart';
import 'package:airmius/screens/lesson_detail_screen.dart';
import 'package:airmius/navigation/airmius_deep_link_navigator.dart';

void main() {
  test(
    'legacy submission alerts open exact instructor course and submission',
    () {
      final alert = AirmiusNotification.fromJson({
        'id': 1,
        'type': 'learning.assignment.submitted',
        'data': {
          'url': '/notifications',
          'course_id': 7,
          'assignment_id': 3,
          'submission_id': 9,
        },
      });
      final target = const AirmiusDeepLinkResolver().resolve(alert.actionUrl!);
      expect(target.type, AirmiusDeepLinkTargetType.learningStudio);
      expect(target.requiresAuth, isTrue);
      final screen =
          AirmiusDeepLinkNavigator.screenFor(target)
              as LearningStudioCourseSuiteScreen;
      expect(screen.initialCourseId, 7);
      expect(screen.initialAssignmentId, 3);
      expect(screen.initialSubmissionId, 9);
    },
  );
  test('graded alerts open learner task and never the instructor studio', () {
    final alert = AirmiusNotification.fromJson({
      'id': 1,
      'type': 'learning.assignment.graded',
      'data': {'course_id': 7, 'assignment_id': 3, 'lesson_id': 4},
    });
    final target = const AirmiusDeepLinkResolver().resolve(alert.actionUrl!);
    expect(target.type, AirmiusDeepLinkTargetType.learningCourse);
    final screen =
        AirmiusDeepLinkNavigator.screenFor(target) as LessonDetailScreen;
    expect(screen.courseId, 7);
    expect(screen.initialLessonId, 4);
    expect(screen.initialAssignmentId, 3);
    final web = const AirmiusDeepLinkResolver().resolve(
      'https://airmius.com/learning/studio?course=7&assignment=3&submission=9',
    );
    expect(web.type, AirmiusDeepLinkTargetType.learningStudio);
  });
  testWidgets(
    'submission notification opens the specific student response for grading',
    (tester) async {
      final tokens = AirmiusMemoryTokenStore();
      await tokens.write(
        const AirmiusSession(
          token: 'owner-token',
          locale: 'de',
          user: AirmiusUser(
            id: 1,
            name: 'Author',
            email: 'author@example.test',
            role: 'trainer',
          ),
        ),
      );
      final services = AirmiusServiceContainer(
        environment: const AirmiusAppEnvironment(
          apiBaseUrl: 'https://example.test',
          enableOfflineQueue: false,
        ),
        transport: _StudioTransport(),
        tokenStore: tokens,
      );
      await services.authState.restore();
      await tester.pumpWidget(
        AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: AirmiusServicesScope(
            container: services,
            child: const MaterialApp(
              home: LearningStudioCourseSuiteScreen(
                initialCourseId: 7,
                initialAssignmentId: 3,
                initialSubmissionId: 9,
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Unique submitted answer'), findsOneWidget);
      expect(find.text('Other response'), findsNothing);
      expect(find.byType(DropdownButtonFormField<String>), findsWidgets);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('learner sees automatic points and feedback on the linked task', (
    tester,
  ) async {
    final services = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://example.test',
        enableOfflineQueue: false,
      ),
      transport: _StudioTransport(),
      tokenStore: AirmiusMemoryTokenStore(),
    );
    await tester.pumpWidget(
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: (_) {},
        child: AirmiusServicesScope(
          container: services,
          child: const MaterialApp(
            home: LessonDetailScreen(
              courseId: 7,
              initialTitle: '',
              initialAssignmentId: 3,
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Punkte: 60 / 80'), findsOneWidget);
    expect(find.text('Automatically checked response'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'assignment editor offers automatic solutions and persists its grading settings',
    (tester) async {
      JsonMap? saved;
      await tester.pumpWidget(
        AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: MaterialApp(
            home: LearningAssessmentEditorScreen(
              assignment: true,
              initial: {
                'id': 3,
                'title': 'Auto task',
                'points': 80,
                'grading_mode': 'automatic',
                'pass_percent': 75,
                'questions': [
                  {
                    'id': 'q',
                    'question': 'Fixed word',
                    'question_type': 'short_text',
                    'correct_options': ['Answer'],
                    'options': [],
                  },
                ],
              },
              uploadImage: (_) async => 'https://example.test/q.png',
              onSave: (payload) async {
                saved = payload;
              },
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Automatisch korrigieren'), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('assessment-save')));
      await tester.pumpAndSettle();
      expect(saved?['grading_mode'], 'automatic');
      expect(saved?['points'], 80);
      expect(saved?['pass_percent'], 75);
      expect((saved?['questions'] as List).single['correct_options'], [
        'Answer',
      ]);
      expect(tester.takeException(), isNull);
    },
  );
}

class _StudioTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.path == '/api/v1/learning/courses/7') {
      return AirmiusApiResponse(
        statusCode: 200,
        body: jsonEncode({
          'data': {
            'course': {'id': 7, 'title': 'Course'},
            'enrollment': {'id': 1, 'status': 'active', 'progress_percent': 0},
            'sections': [],
            'quizzes': [],
            'assignments': [
              {
                'id': 3,
                'title': 'Task',
                'points': 100,
                'grading_mode': 'automatic',
                'submission': {
                  'id': 9,
                  'score': 60,
                  'status': 'needs_revision',
                  'feedback': 'Automatically checked response',
                  'grading_snapshot': {'points': 80},
                },
              },
            ],
          },
        }),
      );
    }
    final Object data = request.path == '/api/v1/me'
        ? {
            'id': 1,
            'name': 'Author',
            'email': 'author@example.test',
            'role': 'trainer',
          }
        : {
            'courses': [
              {'id': 7, 'title': 'Course'},
            ],
            'selectedCourse': {
              'id': 7,
              'title': 'Course',
              'sections': [],
              'quizzes': [],
              'assignments': [
                {
                  'id': 3,
                  'title': 'Task',
                  'points': 100,
                  'submissions': [
                    {
                      'id': 8,
                      'body': 'Other response',
                      'status': 'submitted',
                      'user': {'name': 'Other'},
                    },
                    {
                      'id': 9,
                      'body': 'Unique submitted answer',
                      'status': 'submitted',
                      'user': {'name': 'Target'},
                    },
                  ],
                },
              ],
            },
          };
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({'data': data}),
    );
  }
}
