import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_preferences_store_base.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/screens/learning_screen.dart';
import 'package:airmius/screens/learning_course_detail_screen.dart';
import 'package:airmius/screens/lesson_detail_screen.dart';
import 'package:airmius/screens/marketplace_screen.dart';

void main() {
  testWidgets(
    'my learning confirms hide, leave and rejoin without buying again',
    (tester) async {
      final transport = _Transport()
        ..enrolled = true
        ..paid = true;
      await _pump(tester, transport, const LearningScreen());
      await tester.tap(find.widgetWithText(ChoiceChip, 'My learning'));
      await tester.pumpAndSettle();
      Future<void> menu(String label) async {
        final finder = find.byType(PopupMenuButton<String>);
        await tester.ensureVisible(finder);
        await tester.pumpAndSettle();
        await tester.tap(finder);
        await tester.pumpAndSettle();
        await tester.tap(find.text(label).last);
        await tester.pumpAndSettle();
      }

      Future<void> confirm() async {
        await tester.tap(find.widgetWithText(FilledButton, 'Confirm'));
        await tester.pumpAndSettle();
      }

      await menu('Hide course');
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(transport.requests.where((r) => r.method == 'PUT'), isEmpty);
      await menu('Hide course');
      await confirm();
      expect(transport.hidden, isTrue);
      expect(transport.enrolled, isTrue);
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Hidden courses').last);
      await tester.pumpAndSettle();
      await menu('Unhide course');
      await confirm();
      expect(transport.hidden, isFalse);
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Active courses').last);
      await tester.pumpAndSettle();
      await menu('Leave course');
      await confirm();
      expect(transport.enrolled, isFalse);
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Left courses').last);
      await tester.pumpAndSettle();
      await menu('Rejoin course');
      await confirm();
      expect(transport.enrolled, isTrue);
      expect(
        transport.requests.where((r) => r.path.contains('/commerce')),
        isEmpty,
      );
      expect(
        transport.requests
            .where((r) => r.method == 'PUT')
            .map((r) => r.body?['action'])
            .toList(),
        ['hide', 'show', 'leave', 'resume'],
      );
      expect(tester.takeException(), isNull);
    },
  );
  for (final language in AirmiusLanguage.values) {
    testWidgets(
      'small-screen discovery fits at large text scale in ${language.name}',
      (tester) async {
        await _pump(
          tester,
          _Transport(),
          const LearningScreen(),
          language: language,
          large: true,
        );
        expect(find.byType(ChoiceChip), findsNWidgets(3));
        for (final chip in find.byType(ChoiceChip).evaluate()) {
          final box = chip.renderObject! as RenderBox;
          expect(box.size.width, lessThanOrEqualTo(320));
        }
        expect(tester.takeException(), isNull);
      },
    );
  }
  testWidgets(
    'catalog filters remain reachable with no matches, tabs fit and details precede enrollment',
    (tester) async {
      final transport = _Transport();
      await _pump(tester, transport, const LearningScreen());
      expect(find.byType(ChoiceChip), findsNWidgets(3));
      for (final chip in tester.widgetList<ChoiceChip>(
        find.byType(ChoiceChip),
      )) {
        expect(chip.label, isA<Center>());
        expect((chip.label as Center).child, isA<Text>());
      }
      final search = find.byType(TextField).first;
      await tester.enterText(search, 'no match');
      await tester.pumpAndSettle();
      expect(find.byType(DropdownButtonFormField<String>), findsNWidgets(4));
      await tester.enterText(search, '');
      await tester.pumpAndSettle();
      final button = find.text('View course');
      await tester.ensureVisible(button);
      await tester.tap(button);
      await tester.pumpAndSettle();
      expect(find.byType(LearningCourseDetailScreen), findsOneWidget);
      expect(transport.requests.where((r) => r.method == 'POST'), isEmpty);
      expect(find.text('Enroll for free'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'free enrollment can be cancelled and confirms once before enabling continue',
    (tester) async {
      final transport = _Transport();
      await _pump(
        tester,
        transport,
        const LearningCourseDetailScreen(courseId: 4),
      );
      await tester.tap(find.text('Enroll for free'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(transport.requests.where((r) => r.method == 'POST'), isEmpty);
      await tester.tap(find.text('Enroll for free'));
      await tester.pumpAndSettle();
      await tester.tap(
        find.widgetWithText(FilledButton, 'Enroll for free').last,
      );
      await tester.pumpAndSettle();
      expect(
        transport.requests.where((r) => r.path.endsWith('/enroll')),
        hasLength(1),
      );
      expect(find.text('Continue learning'), findsOneWidget);
      await tester.tap(find.text('Continue learning'));
      await tester.pumpAndSettle();
      final room = tester.widget<LessonDetailScreen>(
        find.byType(LessonDetailScreen),
      );
      expect(room.initialLessonId, 2);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'paid course opens its own marketplace product without free enrollment',
    (tester) async {
      final transport = _Transport()..paid = true;
      await _pump(
        tester,
        transport,
        const LearningCourseDetailScreen(courseId: 4),
      );
      final buy = find.byType(FilledButton).last;
      await tester.tap(buy);
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<MarketplaceScreen>(find.byType(MarketplaceScreen))
            .initialProductId,
        8,
      );
      expect(
        transport.requests.where((r) => r.path.endsWith('/enroll')),
        isEmpty,
      );
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'missing purchase offer is disabled and preview contains no enrollment actions',
    (tester) async {
      final transport = _Transport()
        ..paid = true
        ..product = null;
      await _pump(
        tester,
        transport,
        const LearningCourseDetailScreen(courseId: 4),
      );
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton).last).onPressed,
        isNull,
      );
      final preview = find.text('Preview lesson');
      await tester.scrollUntilVisible(
        preview,
        200,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(preview);
      await tester.pumpAndSettle();
      expect(find.text('Preview content'), findsOneWidget);
      expect(find.text('Mark as complete'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
}

Future<void> _pump(
  WidgetTester tester,
  _Transport transport,
  Widget home, {
  AirmiusLanguage language = AirmiusLanguage.en,
  bool large = false,
}) async {
  tester.view.physicalSize = Size(large ? 320 : 390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final tokens = AirmiusMemoryTokenStore();
  await tokens.write(
    AirmiusSession(
      token: 'test',
      locale: 'en',
      user: AirmiusUser.fromJson(transport.user),
    ),
  );
  final container = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://airmius.test',
      enableOfflineQueue: false,
    ),
    transport: transport,
    tokenStore: tokens,
    pushDeviceStore: _Store(),
  );
  await container.authState.restore();
  await tester.pumpWidget(
    AirmiusScope(
      language: language,
      setLanguage: (_) {},
      child: AirmiusServicesScope(
        container: container,
        child: MaterialApp(
          locale: language.locale,
          supportedLocales: AirmiusLanguage.values.map((l) => l.locale),
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(
              context,
            ).copyWith(textScaler: TextScaler.linear(large ? 1.4 : 1)),
            child: child!,
          ),
          home: home,
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _Store implements AirmiusPreferencesStore {
  @override
  Future<String?> readString(String key) async => null;
  @override
  Future<void> writeString(String key, String value) async {}
}

class _Transport implements AirmiusApiTransport {
  bool hidden = false;
  String participationStatus = 'active';
  bool paid = false, enrolled = false;
  int? product = 8;
  final requests = <AirmiusApiRequest>[];
  Map<String, dynamic> get user => {
    'id': 1,
    'name': 'Learner',
    'email': 'learner@example.test',
    'permissions': [],
  };
  Map<String, dynamic> get course => {
    'id': 4,
    'title': 'Useful course',
    'subtitle': 'Learn useful skills',
    'description': 'Course description',
    'category': 'training',
    'level': 'beginner',
    'language': 'en',
    'is_free': !paid,
    'is_enrolled': enrolled,
    'can_resume': participationStatus == 'left',
    'enrollment_id': enrolled || participationStatus == 'left' ? 1 : null,
    'price_cents': 2500,
    'currency': 'EUR',
    'purchase_product_id': product,
    'tutor': {'name': 'Coach'},
    'lessons_count': 2,
    'learning_goals': ['New skill'],
    'requirements': ['No experience'],
    'target_groups': ['Beginners'],
  };
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    Object data = {};
    if (request.path == '/api/v1/me') {
      data = user;
    } else if (request.path.endsWith('/enroll')) {
      enrolled = true;
      data = {'course': course};
    } else if (request.path == '/api/v1/learning') {
      data = {
        'catalog': [course],
        'enrollments': enrolled || participationStatus == 'left'
            ? [
                {
                  'id': 1,
                  'status': participationStatus,
                  'hidden_at': hidden ? '2026-11-19T12:00:00Z' : null,
                  'course': course,
                  'progress_percent': 50,
                  'next_lesson': {'id': 2, 'title': 'Next lesson'},
                },
              ]
            : [],
        'certificates': [],
      };
    } else if (request.path == '/api/v1/learning/courses/4') {
      data = {
        'course': course,
        'can_use_learning_room': enrolled,
        'enrollment': enrolled ? {'id': 1, 'progress_percent': 50} : null,
        'sections': [
          {
            'id': 1,
            'title': 'Chapter',
            'lessons': [
              {
                'id': 1,
                'title': 'Preview lesson',
                'is_preview': true,
                'completed': enrolled,
                'locked': false,
                'content': 'Preview content',
              },
              {
                'id': 2,
                'title': 'Next lesson',
                'locked': !enrolled,
                'completed': false,
                'content': enrolled ? 'Private content' : null,
              },
            ],
          },
        ],
      };
    }
    if (request.path == '/api/v1/learning/enrollments/1/participation') {
      final action = request.body?['action'];
      if (action == 'hide') hidden = true;
      if (action == 'show') hidden = false;
      if (action == 'leave') {
        enrolled = false;
        participationStatus = 'left';
      }
      if (action == 'resume') {
        enrolled = true;
        participationStatus = 'active';
        hidden = false;
      }
    }
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({'data': data}),
    );
  }
}
