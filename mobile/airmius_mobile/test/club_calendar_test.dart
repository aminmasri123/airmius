import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/club_tasks_screen.dart';

Future<void> openCalendar(WidgetTester tester, AirmiusLanguage language) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final container = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://example.test',
      enableOfflineQueue: false,
    ),
    transport: _CalendarTransport(),
  );
  await tester.pumpWidget(
    AirmiusScope(
      language: language,
      setLanguage: (_) {},
      child: AirmiusServicesScope(
        container: container,
        child: MaterialApp(
          locale: language.locale,
          supportedLocales: AirmiusLanguage.values.map((value) => value.locale),
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          home: const ClubTasksScreen(clubId: 7, initialCalendar: true),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  for (final language in AirmiusLanguage.values) {
    testWidgets('all calendar modes fit a phone in ${language.name}', (
      tester,
    ) async {
      await openCalendar(tester, language);
      final context = tester.element(find.byType(ClubTasksScreen));
      final scope = AirmiusScope.of(context);
      final localizations = MaterialLocalizations.of(context);
      final now = DateTime.now();
      for (final mode in ['day', 'week', 'month', 'year']) {
        final label = scope.t('clubTasks.calendar.$mode');
        expect(label, isNot('clubTasks.calendar.$mode'));
        await tester.tap(find.text(label));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      }
      for (var month = 1; month <= 12; month++) {
        expect(
          find.text(localizations.formatMonthYear(DateTime(now.year, month))),
          findsOneWidget,
        );
      }
      await tester.tap(find.byTooltip(localizations.nextPageTooltip));
      await tester.pumpAndSettle();
      expect(
        find.text(localizations.formatYear(DateTime(now.year + 1))),
        findsOneWidget,
      );
      await tester.tap(find.byTooltip(localizations.previousPageTooltip));
      await tester.pumpAndSettle();
      expect(find.text(localizations.formatYear(now)), findsOneWidget);
      await tester.tap(
        find.descendant(
          of: find.byWidgetPredicate((widget) => widget is SegmentedButton),
          matching: find.text(scope.t('clubTasks.tab.tasks')),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text(scope.t('clubTasks.tab.calendar')));
      await tester.pumpAndSettle();
      expect(find.text(localizations.formatYear(now)), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets(
    'month navigation clamps day 31 and retains it when changing views',
    (tester) async {
      await openCalendar(tester, AirmiusLanguage.en);
      final context = tester.element(find.byType(ClubTasksScreen));
      final localizations = MaterialLocalizations.of(context);
      var date = DateUtils.dateOnly(DateTime.now());
      await tester.tap(find.text('Day'));
      await tester.pumpAndSettle();
      while (date.day != 31) {
        await tester.tap(find.byTooltip(localizations.nextPageTooltip));
        await tester.pumpAndSettle();
        date = DateTime(date.year, date.month, date.day + 1);
      }
      await tester.tap(find.text('Month'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip(localizations.nextPageTooltip));
      await tester.pumpAndSettle();
      final target = DateTime(date.year, date.month + 1);
      final expected = DateTime(
        target.year,
        target.month,
        DateUtils.getDaysInMonth(target.year, target.month),
      );
      expect(
        find.text(localizations.formatMonthYear(expected)),
        findsOneWidget,
      );
      await tester.tap(find.text('Day'));
      await tester.pumpAndSettle();
      expect(
        find.text(localizations.formatFullDate(expected)),
        findsNWidgets(2),
      );
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('year navigation clamps leap day in both directions', (
    tester,
  ) async {
    await openCalendar(tester, AirmiusLanguage.en);
    final context = tester.element(find.byType(ClubTasksScreen));
    final localizations = MaterialLocalizations.of(context);
    final now = DateTime.now();
    var leapYear = now.year;
    while (DateUtils.getDaysInMonth(leapYear, 2) != 29) {
      leapYear++;
    }
    Future<void> move(int delta) async {
      await tester.tap(
        find.byTooltip(
          delta > 0
              ? localizations.nextPageTooltip
              : localizations.previousPageTooltip,
        ),
      );
      await tester.pumpAndSettle();
    }

    await tester.tap(find.text('Year'));
    await tester.pumpAndSettle();
    for (var year = now.year; year < leapYear; year++) {
      await move(1);
    }
    await tester.tap(find.text('Month'));
    await tester.pumpAndSettle();
    for (var month = now.month; month != 2; month += month < 2 ? 1 : -1) {
      await move(month < 2 ? 1 : -1);
    }
    await tester.tap(find.text('Day'));
    await tester.pumpAndSettle();
    // Passing February in earlier years may clamp the cursor to the 28th.
    final leapDay = DateTime(leapYear, 2, 29);
    for (
      var attempt = 0;
      find.text(localizations.formatFullDate(leapDay)).evaluate().isEmpty &&
          attempt < 29;
      attempt++
    ) {
      await move(1);
    }
    expect(find.text(localizations.formatFullDate(leapDay)), findsNWidgets(2));
    for (final delta in [1, -1]) {
      await tester.tap(find.text('Year'));
      await tester.pumpAndSettle();
      await move(delta);
      await tester.tap(find.text('Day'));
      await tester.pumpAndSettle();
      final expectedYear = leapYear + delta;
      expect(
        find.text(localizations.formatFullDate(DateTime(expectedYear, 2, 28))),
        findsNWidgets(2),
      );
      if (delta == 1) {
        await tester.tap(find.text('Year'));
        await tester.pumpAndSettle();
        await move(-1);
        await tester.tap(find.text('Day'));
        await tester.pumpAndSettle();
        await move(1);
      }
    }
    expect(tester.takeException(), isNull);
  });
}

class _CalendarTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    final now = DateTime.now();
    final date =
        '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode(
        request.path.endsWith('/tasks')
            ? request.method == 'POST'
                  ? {
                      'data': {
                        'id': 99,
                        'title': request.body?['title'] ?? 'New task',
                        'status': request.body?['status'] ?? 'open',
                        'priority': request.body?['priority'] ?? 'normal',
                        'visibility': request.body?['visibility'] ?? 'club',
                        'assigned_to': request.body?['assigned_to'],
                        'team_id': request.body?['team_id'],
                        'due_at': request.body?['due_at'],
                        'checklist': request.body?['checklist'] ?? const [],
                        'attachment_links':
                            request.body?['attachment_links'] ?? const [],
                      },
                    }
                  : {
                      'data': List.generate(
                        9,
                        (index) => {
                          'id': index + 1,
                          'title': 'Deadline $index',
                          'status': 'open',
                          'due_at': date,
                        },
                      ),
                      'meta': {
                        'members': [
                          {
                            'id': 11,
                            'name': 'Mira Member',
                            'email': 'mira@example.test',
                          },
                          {
                            'id': 12,
                            'name': 'Alex Trainer',
                            'email': 'alex@example.test',
                          },
                        ],
                        'calendar_events': [
                          {
                            'id': 20,
                            'title': 'Calendar event',
                            'start_time': '${date}T12:00:00',
                          },
                        ],
                      },
                    }
            : {
                'data': {'id': 7, 'name': 'Calendar Club', 'can_manage': true},
              },
      ),
    );
  }
}
