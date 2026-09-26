import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/core/sepa_batch_labels.dart';
import 'package:airmius/core/sepa_fee_recharge_labels.dart';
import 'package:airmius/screens/club_sepa_batches_screen.dart';

class _Transport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  bool fail = false;
  bool canManage = true;
  bool enableNotices = false;
  bool enableResults = false;
  bool enableFees = false;
  bool enableFeeCorrections = false;
  bool enableFeeRechargeDrafts = false;
  bool enableFeeRechargeApprovals = false;
  bool enableFeeRechargeVoids = false;
  bool enableFeeRechargeCredits = false;
  AirmiusJson? settlement;
  bool preparedNotices = false;
  String mailStatus = 'prepared';
  String? providerStatus;
  String? bounceType;
  String status = 'approved';

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (fail) throw Exception('Connection lost after request');
    if (request.path.endsWith('/export')) {
      return const AirmiusApiResponse(
        statusCode: 200,
        body: '<Document>stored XML</Document>',
      );
    }
    if (request.method == 'POST') {
      if (request.path.endsWith('/notice')) status = 'notified';
      if (request.path.endsWith('/notices')) preparedNotices = true;
      if (request.path.endsWith('/notices/send')) mailStatus = 'queued';
      if (request.path.endsWith('/settle')) {
        settlement = {
          'status': 'settled',
          'payment_id': 55,
          'settlement_reference': request.body?['reference'],
          'settled_on': request.body?['booked_on'],
        };
      }
      if (request.path.endsWith('/return')) {
        settlement = {
          'status': 'returned',
          'returned_by': 9,
          'return_reference': request.body?['reference'],
          'return_reason': request.body?['reason'],
          'returned_on': request.body?['booked_on'],
          'return_fee_cents': request.body?['fee_cents'] ?? 0,
        };
      }
      return const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}');
    }
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'can_manage': canManage,
        'notices_available': enableNotices,
        'results_available': enableResults,
        'fees_available': enableFees,
        'fee_corrections_available': enableFeeCorrections,
        'fee_recharge_drafts_available': enableFeeRechargeDrafts,
        'fee_recharge_approvals_available': enableFeeRechargeApprovals,
        'fee_recharge_voids_available': enableFeeRechargeVoids,
        'fee_recharge_credits_available': enableFeeRechargeCredits,
        'data': {
          'current_page': 1,
          'next_page_url': null,
          'data': [
            {
              'id': 7,
              'reference': 'AIR-TEST',
              'status': status,
              'created_by': 9,
              'creditor_name': 'Testverein',
              'creditor_id': 'DE-TEST',
              'collection_date': '2026-10-10',
              'total_cents': 8000,
              'notices': preparedNotices
                  ? [
                      {
                        'id': 11,
                        'status': mailStatus,
                        'provider_status': providerStatus,
                        'bounce_type': bounceType,
                        'content': {
                          'email': 'payer@example.test',
                          'subject': 'SEPA R-1',
                          'body': '80,00 EUR am 10.10.2026 – Mandat M-1',
                        },
                      },
                    ]
                  : [],
              'items': [
                {
                  'id': 1,
                  'invoice_id': 1,
                  'number': 'R-1',
                  'name': 'Mitglied',
                  'amount_cents': 8000,
                  'mandate_reference': 'M-1',
                  'iban_last4': '3000',
                  'settlement': settlement,
                  'payment_options': [
                    {
                      'id': 55,
                      'amount': '80.00',
                      'paid_at': '2026-10-10',
                      'reference': 'EXISTING',
                    },
                  ],
                  if (settlement != null)
                    'invoice': {
                      'id': 1,
                      'number': 'R-1',
                      'amount': '100.00',
                      'status': settlement!['status'] == 'returned'
                          ? 'overdue'
                          : 'paid',
                      'outstanding_amount': settlement!['status'] == 'returned'
                          ? '80.00'
                          : '0.00',
                    },
                },
              ],
            },
          ],
        },
      }),
    );
  }
}

Future<void> _screen(
  WidgetTester tester,
  _Transport transport, {
  AirmiusLanguage language = AirmiusLanguage.de,
}) async {
  final services = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://airmius.test',
      enableOfflineQueue: false,
    ),
    transport: transport,
    tokenStore: AirmiusMemoryTokenStore(),
  );
  await tester.pumpWidget(
    AirmiusServicesScope(
      container: services,
      child: AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: MaterialApp(
          builder: (context, child) => Directionality(
            textDirection: language.isRtl
                ? TextDirection.rtl
                : TextDirection.ltr,
            child: child!,
          ),
          home: const ClubSepaBatchesScreen(
            clubId: 2,
            invoices: [
              {
                'id': 1,
                'number': 'R-1',
                'status': 'open',
                'amount': '100.00',
                'outstanding_amount': '80.00',
              },
              {'id': 2, 'number': 'R-2', 'status': 'paid', 'amount': '100.00'},
            ],
          ),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  test('SEPA XML stays raw and carries authenticated POST headers', () async {
    final transport = _Transport();
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'test-token',
    );
    expect(
      await client.exportClubSepaBatch(2, 7),
      '<Document>stored XML</Document>',
    );
    expect(transport.requests.single.method, 'POST');
    expect(
      transport.requests.single.headers['Authorization'],
      'Bearer test-token',
    );
  });

  test('SEPA mutations never retry an ambiguous transport failure', () async {
    for (final action in [
      '',
      '/7/approve',
      '/7/notice',
      '/7/cancel',
      '/7/export',
      '/7/items/8/fee',
      '/7/items/8/fee-corrections',
      '/7/items/8/fee-recharges',
      '/7/items/8/fee-recharges/9/approve',
      '/7/items/8/fee-recharges/9/void-requests',
      '/7/items/8/fee-recharges/9/void-requests/10/approve',
      '/7/items/8/fee-recharges/9/credit-requests',
      '/7/items/8/fee-recharges/9/credit-requests/10/approve',
      '/7/items/8/fee-recharges/9/credit-requests/10/refund',
    ]) {
      final inner = _Transport()..fail = true;
      final queue = AirmiusQueuedTransport(
        inner: inner,
        timeout: const Duration(seconds: 1),
      );
      final response = await queue.send(
        AirmiusApiRequest(
          method: 'POST',
          path: '/api/v1/clubs/2/sepa-batches$action',
        ),
      );
      expect(response.statusCode, 599);
      expect(inner.requests, hasLength(1));
      expect(queue.queue, isEmpty);
    }
  });

  test(
    'SEPA actions require online access and never enter the offline queue',
    () async {
      final inner = _Transport();
      final queue = AirmiusQueuedTransport(
        inner: inner,
        timeout: const Duration(seconds: 1),
      )..offline = true;
      for (final method in ['GET', 'POST']) {
        final response = await queue.send(
          AirmiusApiRequest(
            method: method,
            path: '/api/v1/clubs/2/sepa-batches',
          ),
        );
        expect(response.statusCode, 503);
      }
      expect(queue.queue, isEmpty);
      expect(inner.requests, isEmpty);
    },
  );

  test('read requests retain existing retry behavior', () async {
    final inner = _Transport()..fail = true;
    final queue = AirmiusQueuedTransport(
      inner: inner,
      timeout: const Duration(seconds: 1),
    );
    await queue.send(
      const AirmiusApiRequest(
        method: 'GET',
        path: '/api/v1/clubs/2/sepa-batches',
      ),
    );
    expect(inner.requests, hasLength(3));
  });

  testWidgets(
    'read-only finance access shows records without mutation controls',
    (tester) async {
      await _screen(tester, _Transport()..canManage = false);
      expect(find.textContaining('AIR-TEST'), findsOneWidget);
      expect(find.text(sepaBatchLabels['de']!['prepare']!), findsNothing);
      expect(find.text(sepaBatchLabels['de']!['record']!), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'notice requires explicit confirmation and sends dispatch evidence',
    (tester) async {
      final transport = _Transport();
      await _screen(tester, transport);
      final label = sepaBatchLabels['de']!['record']!;
      await tester.tap(find.text(label));
      await tester.pumpAndSettle();
      expect(find.text(sepaBatchLabels['de']!['noticeHelp']!), findsOneWidget);
      final submit = find.widgetWithText(FilledButton, label);
      expect(tester.widget<FilledButton>(submit).onPressed, isNull);
      await tester.enterText(find.byType(TextFormField).at(0), '2026-09-23');
      await tester.enterText(
        find.byType(TextFormField).at(1),
        'Versandnachweis 123',
      );
      await tester.ensureVisible(find.byType(Checkbox));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(Checkbox));
      await tester.pumpAndSettle();
      await tester.tap(submit);
      await tester.pumpAndSettle();
      final post = transport.requests.singleWhere((r) => r.method == 'POST');
      expect(post.path, '/api/v1/clubs/2/sepa-batches/7/notice');
      expect(post.body, {
        'sent_on': '2026-09-23',
        'channel': 'email',
        'reference': 'Versandnachweis 123',
        'confirmed': true,
      });
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'creation selects open invoices and cancellation sends no request',
    (tester) async {
      final transport = _Transport();
      await _screen(tester, transport);
      await tester.tap(find.text(sepaBatchLabels['de']!['prepare']!));
      await tester.pumpAndSettle();
      expect(find.byType(CheckboxListTile), findsOneWidget);
      expect(find.textContaining('R-1 · 80.00'), findsOneWidget);
      await tester.tap(find.widgetWithText(TextButton, 'Cancel'));
      await tester.pumpAndSettle();
      expect(transport.requests.where((r) => r.method == 'POST'), isEmpty);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('Arabic batch list renders in RTL', (tester) async {
    await _screen(tester, _Transport(), language: AirmiusLanguage.ar);
    expect(find.text(sepaBatchLabels['ar']!['title']!), findsOneWidget);
    expect(
      Directionality.of(tester.element(find.byType(ClubSepaBatchesScreen))),
      TextDirection.rtl,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'mail preview does not send until recipients are explicitly confirmed',
    (tester) async {
      final transport = _Transport()..enableNotices = true;
      await _screen(tester, transport);
      final labels = sepaBatchLabels['de']!;
      await tester.ensureVisible(find.text(labels['prepareMessages']!));
      await tester.tap(find.text(labels['prepareMessages']!));
      await tester.pumpAndSettle();
      expect(transport.requests.where((r) => r.method == 'POST'), hasLength(1));
      expect(
        transport.requests.lastWhere((r) => r.method == 'POST').path,
        '/api/v1/clubs/2/sepa-batches/7/notices',
      );
      await tester.tap(find.text(labels['noticePreview']!));
      await tester.pumpAndSettle();
      expect(find.text('SEPA R-1'), findsOneWidget);
      expect(find.textContaining('payer@example.test'), findsOneWidget);
      await tester.tap(find.text(labels['noticePreview']!));
      await tester.pumpAndSettle();
      final send = find.widgetWithText(OutlinedButton, labels['sendMessages']!);
      await tester.ensureVisible(send);
      await tester.tap(send);
      await tester.pumpAndSettle();
      final confirm = find.widgetWithText(
        FilledButton,
        labels['sendMessages']!,
      );
      expect(tester.widget<FilledButton>(confirm).onPressed, isNull);
      await tester.tap(find.byType(Checkbox));
      await tester.pumpAndSettle();
      await tester.tap(confirm);
      await tester.pumpAndSettle();
      final post = transport.requests.lastWhere((r) => r.method == 'POST');
      expect(post.path, '/api/v1/clubs/2/sepa-batches/7/notices/send');
      expect(post.body, {'confirmed': true});
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('mail preview separates provider bounce from acceptance', (
    tester,
  ) async {
    final transport = _Transport()
      ..enableNotices = true
      ..preparedNotices = true
      ..mailStatus = 'sent'
      ..providerStatus = 'bounced'
      ..bounceType = 'HardBounce';
    await _screen(tester, transport);
    final labels = sepaBatchLabels['de']!;
    await tester.tap(find.text(labels['noticePreview']!));
    await tester.pumpAndSettle();
    expect(find.textContaining(labels['mail_sent']!), findsOneWidget);
    expect(find.textContaining(labels['provider_bounced']!), findsOneWidget);
    expect(find.textContaining('HardBounce'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'return requires evidence and confirmation and submits fee in cents',
    (tester) async {
      final transport = _Transport()
        ..enableResults = true
        ..status = 'exported';
      await _screen(tester, transport);
      final labels = sepaBatchLabels['de']!;
      await tester.ensureVisible(find.text(labels['returnItem']!));
      await tester.tap(find.text(labels['returnItem']!));
      await tester.pumpAndSettle();
      final submit = find.widgetWithText(FilledButton, labels['returnItem']!);
      expect(tester.widget<FilledButton>(submit).onPressed, isNull);
      for (final entry in [
        '2026-10-11',
        'BANK-RETURN',
        'Bank return reason',
        '3,50',
      ].asMap().entries) {
        final field = find.byType(TextFormField).at(entry.key);
        await tester.ensureVisible(field);
        await tester.enterText(field, entry.value);
      }
      FocusManager.instance.primaryFocus?.unfocus();
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byType(Checkbox));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(Checkbox));
      await tester.pumpAndSettle();
      await tester.tap(submit);
      await tester.pumpAndSettle();
      final post = transport.requests.singleWhere(
        (request) => request.method == 'POST',
      );
      expect(post.path, '/api/v1/clubs/2/sepa-batches/7/items/1/return');
      expect(post.body, {
        'confirmed': true,
        'booked_on': '2026-10-11',
        'reference': 'BANK-RETURN',
        'reason': 'Bank return reason',
        'fee_cents': 350,
      });
      expect(find.textContaining(labels['result_returned']!), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('read-only bank results have no bookkeeping controls', (
    tester,
  ) async {
    final transport = _Transport()
      ..enableResults = true
      ..status = 'exported'
      ..canManage = false
      ..enableFees = true
      ..settlement = {
        'status': 'returned',
        'returned_on': '2026-10-11',
        'return_reason': 'Bank reason',
        'return_reference': 'RETURN',
        'fee_entry': {
          'id': 22,
          'amount': '3.50',
          'booked_on': '2026-10-11',
          'reference': 'FEE-READONLY',
        },
      };
    await _screen(tester, transport);
    expect(
      find.textContaining(sepaBatchLabels['de']!['result_returned']!),
      findsOneWidget,
    );
    expect(find.text(sepaBatchLabels['de']!['settleItem']!), findsNothing);
    expect(find.text(sepaBatchLabels['de']!['returnItem']!), findsNothing);
    expect(find.text(sepaBatchLabels['de']!['retryItem']!), findsNothing);
    expect(find.textContaining('FEE-READONLY'), findsOneWidget);
    expect(find.text('Bankgebühr buchen'), findsNothing);
    expect(find.text('Gebühr korrigieren oder stornieren'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  for (final available in [false, true]) {
    testWidgets(
      'fee correction navigation follows server availability $available and reloads on return',
      (tester) async {
        await tester.binding.setSurfaceSize(const Size(390, 1200));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final transport = _Transport()
          ..enableResults = true
          ..enableFees = true
          ..enableFeeCorrections = available
          ..status = 'exported'
          ..settlement = {
            'status': 'returned',
            'returned_on': '2026-10-11',
            'return_reference': 'RETURN',
            'return_reason': 'Bank reason',
            'fee_entry': {
              'id': 22,
              'amount': '3.50',
              'booked_on': '2026-10-11',
              'reference': 'FEE',
            },
            'fee_corrections': [
              {
                'id': 1,
                'revision': 1,
                'previous_amount_cents': 350,
                'amount_cents': 100,
                'booked_on': '2026-10-12',
                'reference': 'CORRECTION',
                'reason': 'Correction reason',
              },
            ],
          };
        await _screen(tester, transport);
        expect(find.text('Aktueller Gebührenbetrag: 1,00 €'), findsOneWidget);
        final action = find.widgetWithText(
          OutlinedButton,
          'Gebühr korrigieren oder stornieren',
        );
        expect(action, available ? findsOneWidget : findsNothing);
        if (available) {
          final before = transport.requests.length;
          await tester.ensureVisible(action);
          await tester.tap(action);
          await tester.pumpAndSettle();
          expect(find.byType(TextField), findsNWidgets(4));
          await tester.pageBack();
          await tester.pumpAndSettle();
          expect(transport.requests.length, before + 1);
          expect(transport.requests.last.method, 'GET');
        }
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'fee recharge summary is read-only and navigation follows server capability',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(390, 1400));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final proposal = <String, dynamic>{
        'id': 31,
        'status': 'draft',
        'amount_cents': 250,
        'fee_amount_cents': 350,
        'fee_revision': 0,
        'member_id': 8,
        'member': {'id': 8, 'name': 'Mitglied Beispiel'},
        'due_date': '2026-10-20',
        'basis': 'Rücklastschrift BANK-1',
        'reason': 'Vom Mitglied verursacht',
        'proposed_by': 11,
        'active_settlement_id': 2,
        'void_requests': <dynamic>[],
      };
      final transport = _Transport()
        ..enableResults = true
        ..enableFees = true
        ..enableFeeCorrections = true
        ..enableFeeRechargeDrafts = true
        ..enableFeeRechargeApprovals = true
        ..status = 'exported'
        ..settlement = {
          'status': 'returned',
          'returned_on': '2026-10-11',
          'return_reference': 'RETURN',
          'return_reason': 'Bank reason',
          'fee_entry': {
            'id': 22,
            'amount': '3.50',
            'booked_on': '2026-10-11',
            'reference': 'FEE',
          },
          'fee_corrections': <dynamic>[],
          'fee_recharges': [proposal],
        };
      await _screen(tester, transport);
      expect(find.textContaining('Mitglied Beispiel'), findsOneWidget);
      final action = find.widgetWithText(
        OutlinedButton,
        sepaFeeRechargeLabels['de']!['title']!,
      );
      expect(action, findsOneWidget);
      final before = transport.requests.length;
      await tester.ensureVisible(action);
      await tester.tap(action);
      await tester.pumpAndSettle();
      expect(find.text(sepaFeeRechargeLabels['de']!['help']!), findsOneWidget);
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(transport.requests.length, before + 1);

      transport.canManage = false;
      await tester.pumpWidget(const SizedBox.shrink());
      await _screen(tester, transport);
      expect(find.textContaining('Mitglied Beispiel'), findsOneWidget);
      expect(
        find.widgetWithText(
          OutlinedButton,
          sepaFeeRechargeLabels['de']!['title']!,
        ),
        findsNothing,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'receipt explicitly links an existing payment instead of creating another',
    (tester) async {
      final transport = _Transport()
        ..enableResults = true
        ..status = 'exported';
      await _screen(tester, transport);
      final labels = sepaBatchLabels['de']!;
      await tester.ensureVisible(find.text(labels['settleItem']!));
      await tester.tap(find.text(labels['settleItem']!));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField).at(0), '2026-10-10');
      await tester.enterText(find.byType(TextFormField).at(1), 'BANK-CREDIT');
      FocusManager.instance.primaryFocus?.unfocus();
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byType(DropdownButtonFormField<int>));
      await tester.tap(find.byType(DropdownButtonFormField<int>));
      await tester.pumpAndSettle();
      await tester.tap(
        find.textContaining('${labels['existingPayment']} #55').last,
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byType(Checkbox));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(Checkbox));
      await tester.pumpAndSettle();
      await tester.tap(
        find.widgetWithText(FilledButton, labels['settleItem']!),
      );
      await tester.pumpAndSettle();
      final post = transport.requests.singleWhere(
        (request) => request.method == 'POST',
      );
      expect(post.path, '/api/v1/clubs/2/sepa-batches/7/items/1/settle');
      expect(post.body?['payment_id'], 55);
      expect(post.body?['confirmed'], true);
      expect(tester.takeException(), isNull);
    },
  );
}
