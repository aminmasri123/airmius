import 'package:airmius/widgets/club_year_report_review.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('narrow report shows previous period, delta and reconciliation', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: ClubYearReportReview(
              report: {
                'period': {'starts_on': '2026-01-01', 'ends_on': '2026-12-31'},
                'closed_at': '2027-01-03T12:00:00Z',
                'reconciliation': {'mismatched_entry_count': 2},
                'comparison': {
                  'period': {
                    'name': '2025',
                    'starts_on': '2025-01-01',
                    'ends_on': '2025-12-31',
                  },
                  'summary': {
                    'finance_entries': {
                      'income_amount': 0.3,
                      'expense_amount': 0.1,
                      'net_amount': 0.2,
                    },
                  },
                  'net_change_cents': -10,
                },
              },
            ),
          ),
        ),
      ),
    );
    expect(find.text('Previous period: 2025'), findsOneWidget);
    expect(
      find.text('Balance change from previous period: -0.10 €'),
      findsOneWidget,
    );
    expect(find.text('Closed · As of: 2027-01-03'), findsOneWidget);
    expect(find.textContaining('resolve before closing): 2'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('missing comparison is explicit', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: ClubYearReportReview(
            report: {
              'period': {'starts_on': '2025-01-01', 'ends_on': '2025-12-31'},
            },
          ),
        ),
      ),
    );
    expect(find.text('No previous period comparison available.'), findsOneWidget);
    expect(find.text('Provisional report'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
