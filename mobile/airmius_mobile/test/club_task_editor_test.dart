import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/screens/club_tasks_screen.dart';

import 'club_calendar_test.dart' show openCalendar;

void main() {
  for (final language in AirmiusLanguage.values) {
    testWidgets('task editor is a full page in ${language.name}', (
      tester,
    ) async {
      await openCalendar(tester, language);
      final scope = AirmiusScope.of(
        tester.element(find.byType(ClubTasksScreen)),
      );
      await tester.tap(find.byType(FloatingActionButton));
      await tester.pumpAndSettle();

      expect(find.byType(AlertDialog), findsNothing);
      expect(find.byType(BackButton), findsOneWidget);
      expect(find.text(scope.t('clubTasks.add')), findsOneWidget);
      expect(tester.getSize(find.byType(Form)).width, 358);

      await tester.enterText(find.byType(TextFormField).first, 'New task');
      tester.view.viewInsets = const FakeViewPadding(bottom: 300);
      await tester.pumpAndSettle();
      for (final tab in ['planning', 'checklist', 'basic']) {
        final finder = find.text(scope.t('clubTasks.editor.$tab'));
        await tester.ensureVisible(finder);
        await tester.tap(finder);
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      }
      expect(find.text('New task'), findsOneWidget);
      expect(
        tester.getBottomRight(find.text(scope.t('common.save'))).dy,
        lessThan(844 - 300),
      );

      tester.view.resetViewInsets();
      await tester.tap(find.byType(BackButton));
      await tester.pumpAndSettle();
      expect(find.byType(Form), findsNothing);
      expect(find.byType(FloatingActionButton), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('task editor searches assignees and accepts checklist lines', (
    tester,
  ) async {
    await openCalendar(tester, AirmiusLanguage.de);
    final scope = AirmiusScope.of(tester.element(find.byType(ClubTasksScreen)));

    await tester.tap(find.byType(FloatingActionButton));
    await tester.pumpAndSettle();
    await tester.tap(find.text(scope.t('clubTasks.editor.planning')));
    await tester.pumpAndSettle();

    await tester.tap(find.text(scope.t('clubTasks.unassigned')).first);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextField, scope.t('membership.search')),
      'mira',
    );
    await tester.pumpAndSettle();
    expect(find.text('Mira Member'), findsOneWidget);
    expect(find.text('Alex Trainer'), findsNothing);
    await tester.tap(find.text('Mira Member'));
    await tester.pumpAndSettle();
    expect(find.text('Mira Member'), findsOneWidget);

    await tester.tap(find.text(scope.t('clubTasks.editor.checklist')));
    await tester.pumpAndSettle();
    final checklistField = find.widgetWithText(
      TextFormField,
      scope.t('clubTasks.checklist'),
    );
    await tester.enterText(
      checklistField,
      'Bälle bestellen\nTrikots abholen\n[x] Halle reserviert',
    );
    await tester.tap(find.widgetWithText(OutlinedButton, 'Teilaufgabe'));
    await tester.pumpAndSettle();

    final field = tester.widget<TextFormField>(checklistField);
    expect(field.controller?.text, endsWith('\n'));
    expect(tester.takeException(), isNull);
  });
}
