import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/core/club_finance_workspace_labels.dart';
import 'package:airmius/models/club_summary.dart';
import 'package:airmius/screens/club_finance_workspace_screen.dart';

void main() {
  test('finance labels match all supported languages', () {
    final keys = clubFinanceWorkspaceLabels['de']!.keys.toSet();
    for (final language in ['de', 'en', 'fr', 'ar']) {
      expect(clubFinanceWorkspaceLabels[language]!.keys.toSet(), keys);
      expect(
        clubFinanceWorkspaceLabels[language]!.values.every((s) => s.isNotEmpty),
        isTrue,
      );
    }
  });

  test('finance writes are not retried or persisted offline', () async {
    for (final path in [
      'money-accounts',
      'money-transfers',
      'budgets',
      'procurements',
      'finance-scopes/payment/1',
      'year-periods/1/finance-close',
    ]) {
      final transport = _FinanceTransport(fail: true);
      final queued = AirmiusQueuedTransport(
        inner: transport,
        maxRetries: 2,
        timeout: const Duration(seconds: 1),
      );
      final client = AirmiusApiClient(
        transport: queued,
        baseUrl: 'https://example.test',
      );
      await expectLater(
        client.clubFinanceWrite(7, 'POST', path, {}),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(transport.requests, hasLength(1));
      queued.offline = true;
      await expectLater(
        client.clubFinanceWrite(7, 'POST', path, {}),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(queued.queue, isEmpty);
      expect(transport.requests, hasLength(1));
    }
  });

  test(
    'payment and refund use team-scoped endpoints and preserve account',
    () async {
      final transport = _FinanceTransport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
      );
      await client.markTeamPenaltyFeePaid(
        4,
        8,
        payload: {
          'account': 'bank',
          'club_money_account_id': 2,
          'paid_on': '2026-10-09',
        },
      );
      await client.refundTeamPenaltyFee(4, 8, {'paid_on': '2026-10-10'});
      expect(
        transport.requests.first.path,
        '/api/v1/teams/4/penalty-fees/8/paid',
      );
      expect(transport.requests.first.body?['club_money_account_id'], 2);
      expect(
        transport.requests.last.path,
        '/api/v1/teams/4/penalty-fees/8/refund',
      );
    },
  );

  for (final width in [390.0, 1200.0]) {
    testWidgets('native finance renders and validates at width $width', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(Size(width, 844));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final transport = _FinanceTransport();
      final container = AirmiusServiceContainer(
        environment: const AirmiusAppEnvironment(
          apiBaseUrl: 'https://example.test',
          enableOfflineQueue: false,
        ),
        transport: transport,
      );
      final club = ClubSummary.fromAirmiusClub(
        AirmiusClub.fromJson(const {
          'id': 7,
          'name': 'Test Club',
          'can_manage': true,
        }),
      );
      await tester.pumpWidget(
        AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: AirmiusServicesScope(
            container: container,
            child: MaterialApp(home: ClubFinanceWorkspaceScreen(club: club)),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('U17 Budget'), findsOneWidget);
      expect(find.text('Frei: 800.00 EUR'), findsOneWidget);
      await tester.tap(find.text('Kassen & Konten'));
      await tester.pumpAndSettle();
      expect(find.text('Team cash'), findsOneWidget);
      await tester.tap(find.text('Kasse / Konto anlegen'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Speichern'));
      await tester.pumpAndSettle();
      expect(transport.requests.where((r) => r.method != 'GET'), isEmpty);
      await tester.enterText(find.byType(TextFormField).first, 'U17 Cash');
      await tester.tap(find.text('Speichern'));
      await tester.pumpAndSettle();
      expect(
        transport.requests.lastWhere((r) => r.method == 'POST').path,
        '/api/v1/clubs/7/money-accounts',
      );
      expect(
        transport.requests
            .lastWhere((r) => r.method == 'POST')
            .body?['opening_cents'],
        0,
      );
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('transfer retry preserves its key after a lost response', (
    tester,
  ) async {
    final transport = _FinanceTransport(failTransferOnce: true);
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://example.test',
        enableOfflineQueue: false,
      ),
      transport: transport,
    );
    final club = ClubSummary.fromAirmiusClub(
      AirmiusClub.fromJson(const {
        'id': 7,
        'name': 'Test Club',
        'can_manage': true,
      }),
    );
    await tester.pumpWidget(
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: (_) {},
        child: AirmiusServicesScope(
          container: container,
          child: MaterialApp(home: ClubFinanceWorkspaceScreen(club: club)),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Kassen & Konten'));
    await tester.pumpAndSettle();
    for (var attempt = 0; attempt < 2; attempt++) {
      await tester.tap(find.text('Transfer'));
      await tester.pumpAndSettle();
      final dropdowns = find.byType(DropdownButtonFormField<dynamic>);
      await tester.tap(dropdowns.at(0));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Club bank').last);
      await tester.pumpAndSettle();
      await tester.tap(dropdowns.at(1));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Team cash').last);
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField).first, '10.00');
      await tester.tap(find.text('Speichern'));
      await tester.pumpAndSettle();
    }
    final transfers = transport.requests
        .where((r) => r.method == 'POST' && r.path.endsWith('/money-transfers'))
        .toList();
    expect(transfers, hasLength(2));
    expect(
      transfers[0].body?['idempotency_key'],
      transfers[1].body?['idempotency_key'],
    );
    expect(tester.takeException(), isNull);
  });
}

class _FinanceTransport implements AirmiusApiTransport {
  _FinanceTransport({this.fail = false, this.failTransferOnce = false});
  final bool fail;
  bool failTransferOnce;
  final requests = <AirmiusApiRequest>[];
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (fail) throw StateError('Connection lost');
    if (failTransferOnce &&
        request.method == 'POST' &&
        request.path.endsWith('/money-transfers')) {
      failTransferOnce = false;
      throw StateError('Response lost');
    }
    dynamic data = {};
    if (request.path.endsWith('finance-workspace')) {
      data = {
        'available': true,
        'can_manage': true,
        'can_approve': true,
        'can_close': true,
        'teams': [],
        'departments': [],
        'projects': [],
        'cost_centers': [],
        'periods': [
          {
            'id': 1,
            'name': '2026',
            'starts_on': '2026-01-01',
            'ends_on': '2026-12-31',
          },
        ],
        'accounts': [
          {
            'id': 1,
            'name': 'Club bank',
            'type': 'bank',
            'balance_cents': 50000,
          },
          {
            'id': 2,
            'name': 'Team cash',
            'type': 'cash',
            'balance_cents': 10000,
          },
        ],
      };
    }
    if (request.path.endsWith('/budgets')) {
      data = {
        'budgets': [
          {
            'id': 1,
            'name': 'U17 Budget',
            'planned_expense_cents': 100000,
            'approval_status': 'draft',
            'financial_report': {
              'actual_expense_cents': 8000,
              'reserved_expense_cents': 12000,
              'remaining_budget_cents': 80000,
            },
          },
        ],
      };
    }
    if (request.path.endsWith('/procurements')) {
      data = {'procurement_requests': []};
    }
    if (request.path == '/api/v1/clubs/7') {
      data = {
        'management': {'invoices': [], 'payments': []},
      };
    }
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({'data': data}),
    );
  }
}
