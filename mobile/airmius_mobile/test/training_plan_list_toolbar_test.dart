import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/widgets/training_plan_list_toolbar.dart';
import 'package:airmius/core/training_plan_catalog.dart';

void main() {
  test(
    'catalog includes later pages and never returns partial data after denial',
    () async {
      final pages = <int>[];
      final result = await loadTrainingPlanCatalog((page) async {
        pages.add(page);
        return {
          'data': [
            {'id': page, 'status': page == 1 ? 'published' : 'draft'},
          ],
          'meta': {'last_page': 2},
          'capabilities': {'can_manage_training_plans': true},
        };
      });
      expect(pages, [1, 2]);
      expect(result['data'], hasLength(2));
      await expectLater(
        loadTrainingPlanCatalog((page) async {
          if (page == 2) throw StateError('Access revoked');
          return {
            'data': [
              {'id': 1},
            ],
            'meta': {'last_page': 2},
          };
        }),
        throwsStateError,
      );
    },
  );
  test('status and search combine without conflating private with draft', () {
    expect(
      matchesTrainingPlan(
        text: 'Private GYM Team A',
        status: 'published',
        query: 'gym',
        selectedStatus: 'published',
      ),
      isTrue,
    );
    expect(
      matchesTrainingPlan(
        text: 'Private GYM Team A',
        status: 'published',
        query: 'gym',
        selectedStatus: 'draft',
      ),
      isFalse,
    );
    expect(
      matchesTrainingPlan(
        text: 'Tempo Team B',
        status: 'draft',
        query: '  TEAM b ',
        selectedStatus: '',
      ),
      isTrue,
    );
    expect(
      matchesTrainingPlan(
        text: 'Tempo Team B',
        status: 'draft',
        query: 'missing',
        selectedStatus: '',
      ),
      isFalse,
    );
  });
  for (final language in AirmiusLanguage.values) {
    testWidgets('search, status and reset fit in ${language.name}', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(320, 780);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      String query = '', status = '';
      await tester.pumpWidget(
        AirmiusScope(
          language: language,
          setLanguage: (_) {},
          child: MaterialApp(
            locale: language.locale,
            supportedLocales: AirmiusLanguage.values.map((v) => v.locale),
            localizationsDelegates: GlobalMaterialLocalizations.delegates,
            home: Scaffold(
              body: StatefulBuilder(
                builder: (context, setState) => Padding(
                  padding: const EdgeInsets.all(16),
                  child: TrainingPlanListToolbar(
                    query: query,
                    status: status,
                    counts: const {'draft': 2, 'published': 5},
                    onQuery: (v) => setState(() => query = v),
                    onStatus: (v) => setState(() => status = v),
                    onReset: () => setState(() {
                      query = '';
                      status = '';
                    }),
                  ),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.enterText(find.byType(TextFormField), 'GYM');
      await tester.pumpAndSettle();
      expect(query, 'GYM');
      final search = tester.widget<TextFormField>(find.byType(TextFormField));
      expect(search.controller!.text, 'GYM');
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      final copy = AirmiusScope.of(
        tester.element(find.byType(TrainingPlanListToolbar)),
      );
      await tester.tap(
        find.text('${copy.t('trainingHub.status.draft')} (2)').last,
      );
      await tester.pumpAndSettle();
      expect(status, 'draft');
      await tester.tap(find.byType(TextButton));
      await tester.pumpAndSettle();
      expect(query, '');
      expect(status, '');
      expect(
        tester
            .widget<TextFormField>(find.byType(TextFormField))
            .controller!
            .text,
        '',
      );
      expect(tester.takeException(), isNull);
    });
  }
}
