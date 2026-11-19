import 'dart:convert';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/core/learning_deletion_copy.dart';
import 'package:airmius/screens/learning_studio_course_suite_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';

class _Transport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  bool courseDeleted = false, sectionDeleted = false;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (request.method == 'DELETE') {
      if (request.path.endsWith('/sections/8')) {
        sectionDeleted = true;
      } else {
        courseDeleted = true;
      }
      return const AirmiusApiResponse(
        statusCode: 200,
        body: '{"message":"Deleted"}',
      );
    }
    final course = {
      'id': 4,
      'title': 'My test course',
      'status': 'draft',
      'category': 'training',
      'level': 'beginner',
      'language': 'de',
      'is_free': true,
      'sections': sectionDeleted
          ? []
          : [
              {
                'id': 8,
                'title': 'My test section',
                'position': 1,
                'lessons': [],
              },
            ],
    };
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': {
          'courses': courseDeleted ? [] : [course],
          'selectedCourse': courseDeleted ? {} : course,
        },
      }),
    );
  }
}

void main() {
  for (final language in AirmiusLanguage.values) {
    for (final course in [true, false]) {
      testWidgets(
        '${course ? 'course' : 'section'} deletion can cancel and needs typed confirmation in ${language.name}',
        (tester) async {
          tester.view.physicalSize = const Size(390, 844);
          tester.view.devicePixelRatio = 1;
          addTearDown(tester.view.resetPhysicalSize);
          addTearDown(tester.view.resetDevicePixelRatio);
          final transport = _Transport();
          final container = AirmiusServiceContainer(
            environment: const AirmiusAppEnvironment(
              apiBaseUrl: 'https://example.test',
              enableOfflineQueue: false,
            ),
            transport: transport,
          );
          await tester.pumpWidget(
            AirmiusScope(
              language: language,
              setLanguage: (_) {},
              child: AirmiusServicesScope(
                container: container,
                child: MaterialApp(
                  locale: language.locale,
                  supportedLocales: AirmiusLanguage.values.map(
                    (value) => value.locale,
                  ),
                  localizationsDelegates: GlobalMaterialLocalizations.delegates,
                  home: const LearningStudioCourseSuiteScreen(),
                ),
              ),
            ),
          );
          await tester.pumpAndSettle();
          final labels = learningDeletionCopy[language.name]!;
          if (course) {
            final scope = AirmiusScope.of(
              tester.element(find.byType(LearningStudioCourseSuiteScreen)),
            );
            final tab = find.widgetWithText(
              ChoiceChip,
              scope.t('studio.settings'),
            );
            await tester.ensureVisible(tab);
            await tester.pumpAndSettle();
            await tester.tap(tab);
            await tester.pumpAndSettle();
          }
          final button = find.byKey(
            ValueKey(
              course ? 'learning-delete-course' : 'learning-delete-section-8',
            ),
          );
          await tester.scrollUntilVisible(
            button,
            300,
            scrollable: find.byType(Scrollable).first,
          );
          await tester.pumpAndSettle();
          await tester.tap(button);
          await tester.pumpAndSettle();
          expect(
            find.text(labels[course ? 'warn_course' : 'warn_section']!),
            findsOneWidget,
          );
          expect(
            tester
                .widget<FilledButton>(
                  find.byKey(const ValueKey('learning-delete-confirm')),
                )
                .onPressed,
            isNull,
          );
          await tester.tap(find.text(labels['cancel']!));
          await tester.pumpAndSettle();
          expect(
            transport.requests.where((r) => r.method == 'DELETE'),
            isEmpty,
          );
          await tester.tap(button);
          await tester.pumpAndSettle();
          final field = find.byKey(
            const ValueKey('learning-delete-confirmation'),
          );
          await tester.ensureVisible(field);
          await tester.pumpAndSettle();
          await tester.enterText(field, 'delete');
          await tester.pump();
          expect(
            tester
                .widget<FilledButton>(
                  find.byKey(const ValueKey('learning-delete-confirm')),
                )
                .onPressed,
            isNull,
          );
          await tester.enterText(field, 'DELETE');
          await tester.pumpAndSettle();
          await tester.tap(
            find.byKey(const ValueKey('learning-delete-confirm')),
          );
          await tester.pumpAndSettle();
          final request = transport.requests.singleWhere(
            (r) => r.method == 'DELETE',
          );
          expect(
            request.path,
            course
                ? '/api/v1/learning-studio/courses/4'
                : '/api/v1/learning-studio/courses/4/sections/8',
          );
          expect(request.body, {'confirmation': 'DELETE'});
          expect(
            find.text(course ? 'My test course' : 'My test section'),
            findsNothing,
          );
          expect(tester.takeException(), isNull);
        },
      );
    }
  }
}
