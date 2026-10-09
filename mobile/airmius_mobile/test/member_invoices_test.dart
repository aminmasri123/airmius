import 'dart:convert';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/core/airmius_theme.dart';
import 'package:airmius/screens/billing_detail_screen.dart';
import 'package:airmius/screens/member_invoices_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

final _invoice = <String, dynamic>{
  'id': 7,
  'kind': 'club_invoice',
  'number': 'V-2026-7',
  'title': 'Jahresbeitrag',
  'status': 'open',
  'status_label': 'Teilweise bezahlt',
  'amount_cents': 3600,
  'currency': 'EUR',
  'received_amount': '10.00',
  'outstanding_amount': '26.00',
  'issued_at': '2026-10-09',
  'due_at': '2026-10-31',
  'billing_period_start': '2026-01-01',
  'billing_period_end': '2026-12-31',
  'club': {'name': 'Mein Verein'},
};

class _Transport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode(
        request.method == 'POST'
            ? {'message': 'Sent'}
            : {
                'data': [
                  {
                    ..._invoice,
                    'number': request.query['page'] == '2'
                        ? 'V-2026-8'
                        : 'V-2026-7',
                  },
                ],
                'meta': {
                  'current_page': int.tryParse(request.query['page'] ?? '1'),
                  'last_page': 2,
                },
              },
      ),
    );
  }
}

Future<void> _pump(
  WidgetTester tester,
  Widget screen,
  _Transport transport,
) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final services = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://example.test',
      enableOfflineQueue: false,
    ),
    transport: transport,
  );
  await tester.pumpWidget(
    AirmiusScope(
      language: AirmiusLanguage.de,
      setLanguage: (_) {},
      child: AirmiusServicesScope(
        container: services,
        child: MaterialApp(theme: AirmiusTheme.dark(), home: screen),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

BillingDetailScreen _detail(Map<String, dynamic> json) => BillingDetailScreen(
  title: 'V-2026-7',
  body: '',
  status: 'open',
  amount: '36,00 EUR',
  icon: Icons.receipt_long_outlined,
  invoice: AirmiusInvoice.fromJson(json),
);

void main() {
  testWidgets(
    'real invoice details replace subscription placeholder controls',
    (tester) async {
      await _pump(tester, _detail(_invoice), _Transport());
      expect(find.text('01.01.2026 - 31.12.2026'), findsOneWidget);
      expect(find.text('31.10.2026'), findsOneWidget);
      expect(find.text('Restbetrag'), findsOneWidget);
      expect(find.text('26.00 EUR'), findsOneWidget);
      expect(find.text('Rechnung herunterladen'), findsOneWidget);
      expect(find.byType(Switch), findsNothing);
      expect(find.textContaining('Kündigen'), findsNothing);
      expect(find.textContaining('UI-AKTION'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'member question uses the actual invoice endpoint and reports success',
    (tester) async {
      final transport = _Transport();
      await _pump(tester, _detail(_invoice), transport);
      await tester.enterText(
        find.byType(TextField),
        'Bitte den Rechnungsbetrag pruefen.',
      );
      await tester.ensureVisible(find.text('Nachricht senden'));
      await tester.tap(find.text('Nachricht senden'));
      await tester.pumpAndSettle();
      expect(
        transport.requests.single.path,
        '/api/v1/billing/invoices/club_invoice/7/question',
      );
      expect(
        transport.requests.single.body?['message'],
        'Bitte den Rechnungsbetrag pruefen.',
      );
      expect(find.text('Nachricht an den Verein gesendet.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('invoices list paginates and opens the selected actual invoice', (
    tester,
  ) async {
    final transport = _Transport();
    await _pump(tester, const MemberInvoicesScreen(), transport);
    expect(find.text('V-2026-7'), findsOneWidget);
    await tester.tap(find.text('Weitere laden'));
    await tester.pumpAndSettle();
    expect(transport.requests.last.query['page'], '2');
    expect(find.text('V-2026-8'), findsOneWidget);
    await tester.tap(find.text('V-2026-8'));
    await tester.pumpAndSettle();
    expect(find.text('Rechnung herunterladen'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'subscription invoice retains its kind without a club question form',
    (tester) async {
      await _pump(
        tester,
        _detail({..._invoice, 'kind': 'subscription_invoice'}),
        _Transport(),
      );
      expect(find.byType(TextField), findsNothing);
      expect(find.text('Rechnung herunterladen'), findsOneWidget);
    },
  );
}
