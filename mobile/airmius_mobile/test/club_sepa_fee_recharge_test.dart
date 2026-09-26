import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/sepa_fee_recharge_labels.dart';
import 'package:airmius/screens/club_sepa_fee_recharge_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _Transport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  bool failNext = false;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (failNext) {
      failNext = false;
      throw Exception('Connection lost after request');
    }
    if (request.path.endsWith('/document-link')) {
      return const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"url":"https://airmius.test/documents/credit.pdf?signature=test"}}',
      );
    }
    return const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}');
  }
}

AirmiusJson _result({List<AirmiusJson> proposals = const []}) => {
  'status': 'returned',
  'fee_entry': {
    'id': 5,
    'amount': '3.50',
    'booked_on': '2026-09-20',
    'reference': 'BANK-1',
  },
  'fee_corrections': <AirmiusJson>[],
  'fee_recharges': proposals,
};

AirmiusJson _proposal({
  String status = 'draft',
  int proposedBy = 11,
  List<AirmiusJson> voids = const [],
  List<AirmiusJson> credits = const [],
}) => {
  'id': 31,
  'status': status,
  'amount_cents': 250,
  'fee_amount_cents': 350,
  'fee_revision': 0,
  'member_id': 8,
  'member': {'id': 8, 'name': 'Mitglied Beispiel'},
  'due_date': '2026-10-20',
  'basis': 'Rücklastschrift BANK-1',
  'reason': 'Vom Mitglied verursacht',
  'proposed_by': proposedBy,
  'active_settlement_id': status == 'cancelled' ? null : 2,
  if (status == 'approved')
    'invoice': {
      'id': 88,
      'number': 'R-GEB-88',
      'status': 'open',
      'amount': '2.50',
    },
  'void_requests': voids,
  'credit_requests': credits,
};

Future<void> _screen(
  WidgetTester tester,
  _Transport transport, {
  AirmiusJson? result,
  int? userId = 22,
  bool canManage = true,
  bool drafts = true,
  bool approvals = true,
  bool voids = true,
  bool credits = true,
  AirmiusLanguage language = AirmiusLanguage.de,
  Future<bool> Function(Uri uri)? documentOpener,
}) async {
  await tester.pumpWidget(
    AirmiusScope(
      language: language,
      setLanguage: (_) {},
      child: MaterialApp(
        home: ClubSepaFeeRechargeScreen(
          client: AirmiusApiClient(
            transport: transport,
            baseUrl: 'https://airmius.test',
            token: 'token',
          ),
          clubId: 2,
          batchId: 7,
          itemId: 9,
          result: result ?? _result(),
          currentUserId: userId,
          canManage: canManage,
          draftsAvailable: drafts,
          approvalsAvailable: approvals,
          voidsAvailable: voids,
          creditsAvailable: credits,
          documentOpener: documentOpener,
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _confirmAndSubmit(WidgetTester tester) async {
  final confirmation = find.byKey(const ValueKey('recharge-confirm'));
  await tester.scrollUntilVisible(
    confirmation,
    300,
    scrollable: find.byType(Scrollable).first,
  );
  await tester.tap(confirmation);
  await tester.pump();
  await tester.tap(find.byKey(const ValueKey('recharge-submit')));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('proposal validates, confirms and sends a revision snapshot', (
    tester,
  ) async {
    final transport = _Transport();
    await _screen(tester, transport);
    await tester.tap(find.byKey(const ValueKey('recharge-propose-0-0')));
    await tester.pump();
    await tester.enterText(
      find.byKey(const ValueKey('recharge-amount')),
      '2,50',
    );
    await tester.enterText(
      find.byKey(const ValueKey('recharge-date')),
      '2026-10-20',
    );
    await tester.enterText(
      find.byKey(const ValueKey('recharge-basis')),
      'Rücklastschrift BANK-1',
    );
    await tester.enterText(
      find.byKey(const ValueKey('recharge-reason')),
      'Vom Mitglied verursacht',
    );
    await _confirmAndSubmit(tester);

    final request = transport.requests.single;
    expect(
      request.path,
      '/api/v1/clubs/2/sepa-batches/7/items/9/fee-recharges',
    );
    expect(request.body?['amount_cents'], 250);
    expect(request.body?['expected_revision'], 0);
    expect(request.body?['confirmed'], true);
    expect(request.body?['request_id'], matches(RegExp(r'^[0-9a-f-]{36}$')));
  });

  testWidgets('ambiguous failure freezes and resends the exact request', (
    tester,
  ) async {
    final transport = _Transport()..failNext = true;
    await _screen(tester, transport);
    await tester.tap(find.byKey(const ValueKey('recharge-propose-0-0')));
    await tester.pump();
    await tester.enterText(
      find.byKey(const ValueKey('recharge-date')),
      '2026-10-20',
    );
    await tester.enterText(
      find.byKey(const ValueKey('recharge-basis')),
      'Prüfgrundlage',
    );
    await tester.enterText(
      find.byKey(const ValueKey('recharge-reason')),
      'Begründung',
    );
    await _confirmAndSubmit(tester);

    expect(
      find.text(sepaFeeRechargeLabels['de']!['uncertain']!),
      findsOneWidget,
    );
    expect(
      tester
          .widget<TextField>(find.byKey(const ValueKey('recharge-amount')))
          .enabled,
      false,
    );
    final firstBody = jsonEncode(transport.requests.single.body);
    await _confirmAndSubmit(tester);
    expect(transport.requests, hasLength(2));
    expect(transport.requests[1].path, transport.requests[0].path);
    expect(jsonEncode(transport.requests[1].body), firstBody);
  });

  testWidgets('approval is second-person only and records basis confirmation', (
    tester,
  ) async {
    final transport = _Transport();
    await _screen(tester, transport, result: _result(proposals: [_proposal()]));
    await tester.tap(find.byKey(const ValueKey('recharge-approve-31-0')));
    await tester.pump();
    await tester.enterText(
      find.byKey(const ValueKey('recharge-account')),
      '4970',
    );
    await tester.tap(find.byKey(const ValueKey('recharge-basis-confirm')));
    await tester.pump();
    await _confirmAndSubmit(tester);
    expect(
      transport.requests.single.path,
      '/api/v1/clubs/2/sepa-batches/7/items/9/fee-recharges/31/approve',
    );
    expect(transport.requests.single.body, {
      'confirmed': true,
      'basis_confirmed': true,
      'revenue_account': '4970',
    });
  });

  testWidgets('same person cannot approve their own proposal', (tester) async {
    await _screen(
      tester,
      _Transport(),
      result: _result(proposals: [_proposal(proposedBy: 22)]),
    );
    expect(find.byKey(const ValueKey('recharge-approve-31-0')), findsNothing);
  });

  testWidgets('void request carries a stable id and documented reason', (
    tester,
  ) async {
    final transport = _Transport();
    await _screen(
      tester,
      transport,
      result: _result(proposals: [_proposal(status: 'approved')]),
    );
    await tester.tap(find.byKey(const ValueKey('recharge-request_void-31-0')));
    await tester.pump();
    await tester.enterText(
      find.byKey(const ValueKey('recharge-reason')),
      'Rechnung darf storniert werden',
    );
    await _confirmAndSubmit(tester);
    expect(
      transport.requests.single.path,
      '/api/v1/clubs/2/sepa-batches/7/items/9/fee-recharges/31/void-requests',
    );
    expect(
      transport.requests.single.body?['reason'],
      'Rechnung darf storniert werden',
    );
    expect(
      transport.requests.single.body?['request_id'],
      matches(RegExp(r'^[0-9a-f-]{36}$')),
    );
  });

  testWidgets('void approval is hidden from its requester', (tester) async {
    final pending = <String, dynamic>{
      'id': 41,
      'status': 'pending',
      'reason': 'Storno erforderlich',
      'requested_by': 22,
    };
    await _screen(
      tester,
      _Transport(),
      result: _result(
        proposals: [
          _proposal(status: 'approved', voids: [pending]),
        ],
      ),
    );
    expect(
      find.byKey(const ValueKey('recharge-approve_void-31-41')),
      findsNothing,
    );
    expect(
      find.byKey(const ValueKey('recharge-withdraw_void-31-41')),
      findsOneWidget,
    );
  });

  testWidgets('credit request requires evidence and receives a stable id', (
    tester,
  ) async {
    final transport = _Transport();
    await _screen(
      tester,
      transport,
      result: _result(proposals: [_proposal(status: 'approved')]),
    );
    await tester.tap(
      find.byKey(const ValueKey('recharge-request_credit-31-0')),
    );
    await tester.pump();
    await tester.enterText(
      find.byKey(const ValueKey('recharge-reason')),
      'Zahlung und Bankbeleg geprüft',
    );
    await _confirmAndSubmit(tester);
    expect(
      transport.requests.single.path,
      '/api/v1/clubs/2/sepa-batches/7/items/9/fee-recharges/31/credit-requests',
    );
    expect(
      transport.requests.single.body?['reason'],
      'Zahlung und Bankbeleg geprüft',
    );
    expect(
      transport.requests.single.body?['request_id'],
      matches(RegExp(r'^[0-9a-f-]{36}$')),
    );
  });

  testWidgets('credit approval is hidden from its requester', (tester) async {
    final pending = <String, dynamic>{
      'id': 51,
      'status': 'pending',
      'reason': 'Gutschrift erforderlich',
      'amount_cents': 250,
      'requested_by': 22,
    };
    await _screen(
      tester,
      _Transport(),
      result: _result(
        proposals: [
          _proposal(status: 'approved', credits: [pending]),
        ],
      ),
    );
    expect(
      find.byKey(const ValueKey('recharge-approve_credit-31-51')),
      findsNothing,
    );
    expect(
      find.byKey(const ValueKey('recharge-withdraw_credit-31-51')),
      findsOneWidget,
    );
  });

  testWidgets('ambiguous refund retry preserves bank evidence exactly', (
    tester,
  ) async {
    final transport = _Transport()..failNext = true;
    final issued = <String, dynamic>{
      'id': 51,
      'status': 'issued',
      'reason': 'Gutschrift erforderlich',
      'amount_cents': 250,
      'refund_due_cents': 100,
      'credit_note_number': 'AIR-GS-FEE-2-51',
      'requested_by': 11,
    };
    await _screen(
      tester,
      transport,
      result: _result(
        proposals: [
          _proposal(status: 'credited', credits: [issued]),
        ],
      ),
    );
    final refundAction = find.byKey(const ValueKey('recharge-refund-31-51'));
    await tester.ensureVisible(refundAction);
    await tester.tap(refundAction);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey('recharge-refund-date')),
      '2026-10-22',
    );
    await tester.enterText(
      find.byKey(const ValueKey('recharge-refund-reference')),
      'BANK-REFUND-1',
    );
    await tester.enterText(
      find.byKey(const ValueKey('recharge-refund-finance-entry')),
      '77',
    );
    await _confirmAndSubmit(tester);
    final firstBody = jsonEncode(transport.requests.single.body);
    expect(
      transport.requests.single.path,
      '/api/v1/clubs/2/sepa-batches/7/items/9/fee-recharges/31/credit-requests/51/refund',
    );
    expect(transport.requests.single.body?['finance_entry_id'], 77);
    await _confirmAndSubmit(tester);
    expect(transport.requests, hasLength(2));
    expect(jsonEncode(transport.requests.last.body), firstBody);
  });

  testWidgets('credit document uses a short-lived external HTTPS link', (
    tester,
  ) async {
    final transport = _Transport();
    Uri? opened;
    final completed = <String, dynamic>{
      'id': 51,
      'status': 'completed',
      'reason': 'Gutschrift erforderlich',
      'amount_cents': 250,
      'refund_due_cents': 0,
      'credit_note_number': 'AIR-GS-FEE-2-51',
      'requested_by': 11,
    };
    await _screen(
      tester,
      transport,
      result: _result(
        proposals: [
          _proposal(status: 'credited', credits: [completed]),
        ],
      ),
      documentOpener: (uri) async {
        opened = uri;
        return true;
      },
    );
    final action = find.byKey(const ValueKey('recharge-credit-document-31-51'));
    await tester.ensureVisible(action);
    await tester.tap(action);
    await tester.pumpAndSettle();
    expect(
      transport.requests.single.path,
      '/api/v1/clubs/2/sepa-batches/7/items/9/fee-recharges/31/credit-requests/51/document-link',
    );
    expect(opened?.scheme, 'https');
    expect(opened?.queryParameters['signature'], 'test');
  });

  testWidgets('read-only Arabic view shows proposals without actions', (
    tester,
  ) async {
    await _screen(
      tester,
      _Transport(),
      result: _result(
        proposals: [
          _proposal(
            status: 'approved',
            voids: [
              {
                'id': 41,
                'status': 'pending',
                'reason': 'طلب موثق',
                'requested_by': 11,
              },
            ],
          ),
        ],
      ),
      canManage: false,
      language: AirmiusLanguage.ar,
    );
    expect(find.textContaining('Mitglied Beispiel'), findsOneWidget);
    expect(find.textContaining('طلب موثق'), findsOneWidget);
    expect(find.byType(OutlinedButton), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
