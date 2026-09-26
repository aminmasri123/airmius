import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/screens/club_sepa_fee_screen.dart';

class _Fees implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  bool fail = false;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (request.method == 'POST') {
      if (fail) throw Exception('Lost response');
      return const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}');
    }
    final page = int.parse(request.query['page']!);
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': {
          'current_page': page,
          'next_page_url': page == 1 ? '/next' : null,
          'data': [
            {
              'id': page == 1 ? 11 : 22,
              'amount': '3.50',
              'booked_on': '2026-10-11T00:00:00.000000Z',
              'reference': 'BANK-FEE-$page',
            },
          ],
        },
      }),
    );
  }
}

Future<void> _screen(
  WidgetTester tester,
  _Fees transport,
  void Function(bool?) result,
) async {
  await tester.binding.setSurfaceSize(const Size(390, 1400));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(
    AirmiusScope(
      language: AirmiusLanguage.de,
      setLanguage: (_) {},
      child: MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () async {
                result(
                  await Navigator.of(context).push<bool>(
                    MaterialPageRoute(
                      builder: (_) => ClubSepaFeeScreen(
                        client: AirmiusApiClient(
                          transport: transport,
                          baseUrl: 'https://example.test',
                        ),
                        clubId: 2,
                        batchId: 7,
                        itemId: 8,
                      ),
                    ),
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
}

Future<void> _mode(WidgetTester tester, String label) async {
  await tester.tap(find.byType(DropdownButtonFormField<String>));
  await tester.pumpAndSettle();
  await tester.tap(find.text(label).last);
  await tester.pumpAndSettle();
}

void main() {
  testWidgets(
    'existing expense search pages and explicit link preserve selected bank evidence',
    (tester) async {
      final transport = _Fees();
      bool? saved;
      await _screen(tester, transport, (value) => saved = value);
      await _mode(tester, 'Vorhandene Ausgabe verknüpfen');
      await tester.enterText(find.byType(TextField), 'BANK-FEE');
      await tester.tap(find.text('Ausgaben suchen'));
      await tester.pumpAndSettle();
      expect(transport.requests.single.query, {'q': 'BANK-FEE', 'page': '1'});
      await tester.tap(find.text('Weiter'));
      await tester.pumpAndSettle();
      expect(transport.requests.last.query['page'], '2');
      await tester.tap(
        find.widgetWithText(
          OutlinedButton,
          '#22 · 3,50 € · 2026-10-11 · BANK-FEE-2',
        ),
      );
      await tester.pumpAndSettle();
      final apply = find.widgetWithText(FilledButton, 'Ausgabe verknüpfen');
      expect(tester.widget<FilledButton>(apply).onPressed, isNull);
      await tester.tap(find.byType(CheckboxListTile));
      await tester.pumpAndSettle();
      await tester.tap(apply);
      await tester.pumpAndSettle();
      expect(saved, true);
      expect(
        transport.requests.last.path,
        '/api/v1/clubs/2/sepa-batches/7/items/8/fee',
      );
      expect(transport.requests.last.body, {
        'confirmed': true,
        'amount_cents': 350,
        'booked_on': '2026-10-11',
        'reference': 'BANK-FEE-2',
        'finance_entry_id': 22,
      });
      expect(tester.takeException(), isNull);
    },
  );
  for (final fail in [false, true]) {
    testWidgets(
      'new fee requires fresh confirmation after edits; failure $fail',
      (tester) async {
        final transport = _Fees()..fail = fail;
        bool? saved;
        await _screen(tester, transport, (value) => saved = value);
        await _mode(tester, 'Neue Ausgabe anlegen');
        final fields = find.byType(TextField);
        await tester.enterText(fields.at(0), '3,50');
        await tester.enterText(fields.at(1), '2026-10-11');
        await tester.enterText(fields.at(2), 'BANK-FEE');
        await tester.tap(find.byType(CheckboxListTile));
        await tester.pumpAndSettle();
        await tester.enterText(fields.at(0), '4,50');
        await tester.pumpAndSettle();
        final apply = find.widgetWithText(FilledButton, 'Ausgabe buchen');
        expect(tester.widget<FilledButton>(apply).onPressed, isNull);
        expect(transport.requests, isEmpty);
        await tester.tap(find.byType(CheckboxListTile));
        await tester.pumpAndSettle();
        await tester.tap(apply);
        await tester.pumpAndSettle();
        expect(transport.requests, hasLength(1));
        expect(transport.requests.single.body!['amount_cents'], 450);
        expect(
          transport.requests.single.body!.containsKey('finance_entry_id'),
          false,
        );
        expect(saved, fail ? isNull : isTrue);
        if (fail) expect(tester.widget<FilledButton>(apply).onPressed, isNull);
        expect(tester.takeException(), isNull);
      },
    );
  }
}
