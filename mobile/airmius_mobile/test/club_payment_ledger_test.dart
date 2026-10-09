import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/core/airmius_theme.dart';
import 'package:airmius/screens/club_membership_management_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _LedgerTransport implements AirmiusApiTransport {
  _LedgerTransport(this.management);
  final Map<String, dynamic> management;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    final club = {
      'id': 4,
      'owner_id': 1,
      'name': 'Ledger Club',
      'can_manage': true,
    };
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': request.path == '/api/v1/clubs'
            ? [club]
            : {...club, 'management': management},
      }),
    );
  }
}

Future<void> _pump(
  WidgetTester tester,
  Map<String, dynamic> management, {
  String section = 'payments',
}) async {
  tester.view.physicalSize = const Size(390, 2400);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final services = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://example.test',
      enableOfflineQueue: false,
    ),
    transport: _LedgerTransport(management),
  );
  await tester.pumpWidget(
    AirmiusScope(
      language: AirmiusLanguage.de,
      setLanguage: (_) {},
      child: AirmiusServicesScope(
        container: services,
        child: MaterialApp(
          theme: AirmiusTheme.dark(),
          home: ClubMembershipManagementScreen(
            initialClubId: 4,
            initialSection: section,
          ),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Map<String, dynamic> _payment(String status) => {
  'id': 5,
  'invoice_id': null,
  'amount': '20.00',
  'status': status,
  'method': 'cash',
  'receipt_number': 'BELEG-5',
  'paid_at': '2026-10-01',
  'user': {'id': 2, 'name': 'Mira'},
};

void main() {
  for (final status in ['cancelled', 'failed', 'pending']) {
    testWidgets('$status receipt is not displayed as received money', (
      tester,
    ) async {
      await _pump(tester, {
        'can_manage': true,
        'payments': [_payment(status)],
      });
      final payment = find.text('Mira').last;
      await tester.ensureVisible(payment);
      await tester.tap(payment);
      await tester.pumpAndSettle();
      final expected = status == 'cancelled'
          ? 'Storniert'
          : status == 'failed'
          ? 'Zahlung fehlgeschlagen'
          : 'Zahlung ausstehend';
      expect(find.text(expected), findsWidgets);
      expect(find.text('0,00 EUR'), findsOneWidget);
      final amount = tester.widget<Text>(find.text('20,00 EUR').last);
      expect(amount.style?.color, isNot(AirmiusColors.green));
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets(
    'external open balance uses the server total even without a loaded invoice',
    (tester) async {
      await _pump(tester, {
        'can_manage': true,
        'summary': {
          'member_open_balances': {'external:2': '80.00'},
        },
        'external_members': [
          {
            'id': 2,
            'name': 'Max Extern',
            'email': 'max@example.test',
            'membership_status': 'active',
          },
        ],
        'invoices': [],
      }, section: 'members');
      expect(find.text('Offen 80,00 EUR'), findsOneWidget);
      expect(find.text('Max Extern'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'an older invoice opened from payment shows all installments and period',
    (tester) async {
      final installment = {..._payment('paid'), 'invoice_id': 9};
      final invoice = {
        'id': 9,
        'number': 'R-2026-9',
        'title': 'Jahresbeitrag',
        'status': 'open',
        'user_id': 7,
        'membership_user_id': 2,
        'member': {'id': 2, 'name': 'Mira', 'is_external': false},
        'user': {'id': 7, 'name': 'Familienzahler'},
        'amount': '100.00',
        'received_amount': '30.00',
        'outstanding_amount': '70.00',
        'billing_period_start': '2026-01-01',
        'billing_period_end': '2026-12-31',
        'payments': [
          installment,
          {
            ...installment,
            'id': 6,
            'amount': '10.00',
            'receipt_number': 'BELEG-6',
          },
        ],
      };
      await _pump(tester, {
        'can_manage': true,
        'invoices': [],
        'payments': [
          {...installment, 'invoice': invoice},
        ],
      });
      await tester.ensureVisible(find.text('Mira').last);
      await tester.tap(find.text('Mira').last);
      await tester.pumpAndSettle();
      expect(find.text('R-2026-9'), findsOneWidget);
      expect(find.text('01.01.2026 - 31.12.2026'), findsOneWidget);
      expect(find.text('30,00 EUR'), findsOneWidget);
      expect(find.text('70,00 EUR'), findsOneWidget);
      expect(find.text('BELEG-5'), findsOneWidget);
      expect(find.text('BELEG-6'), findsOneWidget);
      expect(find.text('Familienzahler'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  test('ledger statuses are translated in every supported language', () {
    for (final language in AirmiusLanguage.values) {
      final scope = AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: const SizedBox.shrink(),
      );
      for (final key in [
        'paymentFailed',
        'paymentPending',
        'paymentCredited',
        'creditApplied',
      ]) {
        expect(scope.t('membership.$key'), isNot('membership.$key'));
      }
    }
  });
}
