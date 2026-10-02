import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/widgets/inventory_checkout_dialog.dart';
import 'package:airmius/screens/facility_booking_resource_scheduler_suite_screen.dart';
import 'package:airmius/screens/club_asset_inventory_checkout_suite_screen.dart';

void main() {
  for (final scheduled in [false, true]) {
    testWidgets(
      'checkout validates quantity and returns scheduled=$scheduled payload',
      (tester) async {
        Map<String, dynamic>? result;
        await tester.pumpWidget(
          AirmiusScope(
            language: AirmiusLanguage.en,
            setLanguage: (_) {},
            child: MaterialApp(
              home: Builder(
                builder: (context) => Scaffold(
                  body: TextButton(
                    onPressed: () async {
                      result = await showDialog<Map<String, dynamic>>(
                        context: context,
                        builder: (_) => InventoryCheckoutDialog(
                          name: 'Court',
                          scheduled: scheduled,
                        ),
                      );
                    },
                    child: const Text('Open'),
                  ),
                ),
              ),
            ),
          ),
        );
        await tester.tap(find.text('Open'));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        await tester.enterText(find.byType(TextField), '0');
        await tester.pump();
        expect(
          tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
          isNull,
        );
        await tester.enterText(find.byType(TextField), '2');
        await tester.pump();
        await tester.tap(find.byType(FilledButton));
        await tester.pumpAndSettle();
        expect(result?['quantity'], 2);
        expect(result?.containsKey('starts_at'), scheduled);
        expect(result?.containsKey('due_at'), scheduled);
        if (scheduled) {
          expect(
            DateTime.parse(
              result!['due_at'],
            ).isAfter(DateTime.parse(result!['starts_at'])),
            isTrue,
          );
        }
      },
    );
  }

  testWidgets(
    'facility entry uses persisted inventory with scheduling enabled',
    (tester) async {
      await tester.pumpWidget(const MaterialApp(home: SizedBox()));
      final context = tester.element(find.byType(SizedBox));
      final screen = const FacilityBookingResourceSchedulerSuiteScreen().build(
        context,
      );
      expect(screen, isA<ClubAssetInventoryCheckoutSuiteScreen>());
      expect(
        (screen as ClubAssetInventoryCheckoutSuiteScreen).scheduledCheckout,
        isTrue,
      );
    },
  );
}
