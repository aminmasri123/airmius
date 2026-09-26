import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/screens/club_sepa_fee_correction_screen.dart';

class _Transport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  bool fail = false;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (fail) throw Exception('Lost response');
    return const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}');
  }
}

AirmiusJson result() => {
  'fee_entry': {'amount': '3.50', 'booked_on': '2026-10-11'},
  'fee_corrections': [
    {
      'id': 1,
      'revision': 1,
      'previous_amount_cents': 350,
      'amount_cents': 200,
      'booked_on': '2026-10-12',
      'reference': 'REF-OLD',
      'reason': 'Earlier correction',
    },
  ],
};

Future<void> _screen(
  WidgetTester tester,
  _Transport transport,
  void Function(bool?) onResult,
) async {
  await tester.binding.setSurfaceSize(const Size(390, 1500));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(
    AirmiusScope(
      language: AirmiusLanguage.de,
      setLanguage: (_) {},
      child: MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              child: const Text('Open'),
              onPressed: () async {
                onResult(
                  await Navigator.of(context).push<bool>(
                    MaterialPageRoute(
                      builder: (_) => ClubSepaFeeCorrectionScreen(
                        client: AirmiusApiClient(
                          transport: transport,
                          baseUrl: 'https://example.test',
                        ),
                        clubId: 2,
                        batchId: 7,
                        itemId: 8,
                        result: result(),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ),
      ),
    ),
  );
  await tester.tap(find.text('Open'));
  await tester.pumpAndSettle();
}

Future<void> fill(WidgetTester tester, String amount) async {
  final fields = find.byType(TextField);
  await tester.enterText(fields.at(0), amount);
  await tester.enterText(fields.at(1), '2026-10-13');
  await tester.enterText(fields.at(2), 'BANK-CORRECTION');
  await tester.enterText(fields.at(3), 'Correct bank evidence');
  await tester.tap(find.byType(CheckboxListTile));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets(
    'cancel current fee with a confirmed zero and preserve history revision',
    (tester) async {
      final transport = _Transport();
      bool? saved;
      await _screen(tester, transport, (value) => saved = value);
      expect(find.text('Aktueller Gebührenbetrag: 2,00 €'), findsOneWidget);
      await fill(tester, '0');
      await tester.tap(find.text('Korrektur buchen'));
      await tester.pumpAndSettle();
      expect(saved, true);
      expect(transport.requests, hasLength(1));
      final request = transport.requests.single;
      expect(
        request.path,
        '/api/v1/clubs/2/sepa-batches/7/items/8/fee-corrections',
      );
      expect(request.body!['amount_cents'], 0);
      expect(request.body!['expected_revision'], 1);
      expect(
        request.body!['request_id'],
        matches(
          RegExp(
            r'^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$',
          ),
        ),
      );
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets(
    'ambiguous failure freezes payload and explicit retry reuses the same UUID',
    (tester) async {
      final transport = _Transport()..fail = true;
      bool? saved;
      await _screen(tester, transport, (value) => saved = value);
      await fill(tester, '1,25');
      await tester.tap(find.text('Korrektur buchen'));
      await tester.pumpAndSettle();
      expect(transport.requests, hasLength(1));
      expect(saved, isNull);
      for (final field in tester.widgetList<TextField>(
        find.byType(TextField),
      )) {
        expect(field.enabled, false);
      }
      final retry = find.widgetWithText(
        FilledButton,
        'Dieselbe Korrektur erneut prüfen',
      );
      expect(tester.widget<FilledButton>(retry).onPressed, isNull);
      final original = Map<String, dynamic>.from(
        transport.requests.single.body!,
      );
      transport.fail = false;
      await tester.tap(find.byType(CheckboxListTile));
      await tester.pumpAndSettle();
      await tester.tap(retry);
      await tester.pumpAndSettle();
      expect(saved, true);
      expect(transport.requests, hasLength(2));
      expect(transport.requests.last.body, original);
      expect(tester.takeException(), isNull);
    },
  );
  testWidgets('editing revokes confirmation and invalid dates do not send', (
    tester,
  ) async {
    final transport = _Transport();
    await _screen(tester, transport, (_) {});
    await fill(tester, '1.00');
    await tester.enterText(find.byType(TextField).at(1), '2026-10-11');
    await tester.pumpAndSettle();
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNull,
    );
    await tester.tap(find.byType(CheckboxListTile));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Korrektur buchen'));
    await tester.pumpAndSettle();
    expect(transport.requests, isEmpty);
    expect(tester.takeException(), isNull);
  });
  testWidgets(
    'read-only summary displays Arabic history without mutation controls',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(390, 1000));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.pumpWidget(
        AirmiusScope(
          language: AirmiusLanguage.ar,
          setLanguage: (_) {},
          child: MaterialApp(
            home: Scaffold(
              body: Directionality(
                textDirection: TextDirection.rtl,
                child: ListView(
                  children: [SepaFeeCorrectionSummary(result: result())],
                ),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.byType(ExpansionTile));
      await tester.pumpAndSettle();
      expect(find.textContaining('Earlier correction'), findsOneWidget);
      expect(find.byType(FilledButton), findsNothing);
      expect(find.byType(TextField), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
}
