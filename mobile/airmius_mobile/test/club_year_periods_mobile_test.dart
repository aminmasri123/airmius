import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/models/club_summary.dart';
import 'package:airmius/screens/club_year_periods_screen.dart';

void main() {
  test(
    'year period client uses scoped endpoints and preserves payloads',
    () async {
      final transport = _RecordingTransport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
        token: 'token',
      );

      await client.clubYearPeriods(7);
      await client.clubYearPeriodReport(
        7,
        type: 'business',
        periodId: 'unassigned',
      );
      await client.saveClubYearPeriod(7, {
        'type': 'business',
        'name': '2026',
        'starts_on': '2026-01-01',
        'ends_on': '2026-12-31',
      });
      await client.saveClubYearPeriod(7, {
        'type': 'sport',
        'name': '2026/27',
        'starts_on': '2026-07-01',
        'ends_on': '2027-06-30',
      }, periodId: 4);
      await client.deleteClubYearPeriod(7, 4);

      expect(
        transport.requests.map(
          (request) => '${request.method} ${request.path}',
        ),
        [
          'GET /api/v1/clubs/7/year-periods',
          'GET /api/v1/clubs/7/year-periods/report',
          'POST /api/v1/clubs/7/year-periods',
          'PUT /api/v1/clubs/7/year-periods/4',
          'DELETE /api/v1/clubs/7/year-periods/4',
        ],
      );
      expect(transport.requests[3].body?['type'], 'sport');
      expect(transport.requests[1].query, {
        'type': 'business',
        'period_id': 'unassigned',
      });
    },
  );

  test(
    'year period mutations are neither queued offline nor retried',
    () async {
      final inner = _ThrowingTransport();
      final queued = AirmiusQueuedTransport(
        inner: inner,
        timeout: const Duration(seconds: 1),
        maxRetries: 2,
      );
      final client = AirmiusApiClient(
        transport: queued,
        baseUrl: 'https://example.test',
        token: 'token',
      );

      await expectLater(
        client.saveClubYearPeriod(1, {
          'type': 'contribution',
          'name': '2026',
          'starts_on': '2026-01-01',
          'ends_on': '2026-12-31',
        }),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(inner.calls, 1);
      queued.offline = true;
      await expectLater(
        client.deleteClubYearPeriod(1, 2),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(queued.queue, isEmpty);
      expect(inner.calls, 1);
    },
  );

  test('year period labels are translated in every supported language', () {
    for (final language in AirmiusLanguage.values) {
      final scope = AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: const SizedBox.shrink(),
      );
      for (final key in [
        'clubYearPeriods.title',
        'clubYearPeriods.type.business',
        'clubYearPeriods.type.contribution',
        'clubYearPeriods.type.sport',
        'clubYearPeriods.status.current',
        'clubYearPeriods.invalidRange',
        'clubYearPeriods.report',
        'clubYearPeriods.unassignedSeparate',
        'clubYearPeriods.entryCount',
      ]) {
        expect(scope.t(key), isNot(key), reason: '${language.name}: $key');
      }
    }
  });

  testWidgets(
    'year period screen renders independent types and manager actions',
    (tester) async {
      final container = AirmiusServiceContainer(
        environment: const AirmiusAppEnvironment(
          apiBaseUrl: 'https://example.test',
          enableOfflineQueue: false,
        ),
        transport: _YearPeriodTransport(),
      );
      final club = ClubSummary.fromAirmiusClub(
        AirmiusClub.fromJson(const {
          'id': 7,
          'name': 'Airmius SC',
          'can_manage': true,
          'is_member': true,
        }),
      );

      await tester.pumpWidget(
        AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: AirmiusServicesScope(
            container: container,
            child: MaterialApp(home: ClubYearPeriodsScreen(club: club)),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Geschäftsjahre'), findsOneWidget);
      expect(find.text('Beitragsjahre'), findsOneWidget);
      expect(find.text('Sportjahre'), findsOneWidget);
      expect(find.text('Geschäftsjahr 2026'), findsOneWidget);
      expect(find.text('Saison 2026/27'), findsOneWidget);
      expect(find.byTooltip('Vereinsjahr anlegen'), findsOneWidget);
      expect(find.byTooltip('Auswerten'), findsNWidgets(3));
      expect(find.text('Historisch unzugeordnete Vorgänge'), findsNWidgets(3));

      await tester.tap(find.byTooltip('Auswerten').first);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));
      expect(find.text('Geschäftsjahr 2026'), findsNWidgets(2));
      expect(find.text('Offener Betrag'), findsOneWidget);
      expect(find.text('Buchungen'), findsNWidgets(2));
      expect(
        find.text('Davon getrennt historisch unzugeordnet'),
        findsOneWidget,
      );
      await tester.tap(find.text('Schließen'));
      await tester.pumpAndSettle();

      await tester.tap(find.byTooltip('Vereinsjahr anlegen'));
      await tester.pumpAndSettle();
      expect(find.text('Vereinsjahr anlegen'), findsOneWidget);
      await tester.tap(find.text('Speichern'));
      await tester.pumpAndSettle();
      expect(
        find.text('Name, Beginn und Ende sind erforderlich.'),
        findsOneWidget,
      );
    },
  );
}

class _RecordingTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    return const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}');
  }
}

class _ThrowingTransport implements AirmiusApiTransport {
  int calls = 0;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    calls += 1;
    throw StateError('connection lost');
  }
}

class _YearPeriodTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.path.endsWith('/report')) {
      return const AirmiusApiResponse(
        statusCode: 200,
        body:
            '''{"data":{"type":"business","selection":"period","period":{"id":1,"type":"business","name":"Geschäftsjahr 2026","starts_on":"2026-01-01","ends_on":"2026-12-31"},"summary":{"invoices":{"total_count":2,"open_amount":25,"paid_amount":10,"overdue_count":1},"bank_transactions":{"total_count":1,"credit_amount":25,"debit_amount":0,"net_amount":25},"finance_entries":{"total_count":1,"income_amount":10,"expense_amount":0,"net_amount":10}},"unassigned":{"invoices":{"total_count":1},"bank_transactions":{"total_count":1},"finance_entries":{"total_count":1}}}}''',
      );
    }
    return const AirmiusApiResponse(
      statusCode: 200,
      body:
          '''{"data":{"periods":[{"id":1,"type":"business","name":"Geschäftsjahr 2026","starts_on":"2026-01-01","ends_on":"2026-12-31","status":"current"},{"id":2,"type":"contribution","name":"Beiträge 2026/27","starts_on":"2026-04-01","ends_on":"2027-03-31","status":"current"},{"id":3,"type":"sport","name":"Saison 2026/27","starts_on":"2026-07-01","ends_on":"2027-06-30","status":"current"}],"can_manage":true,"can_edit":true,"can_delete":true,"can_view_reports":true}}''',
    );
  }
}
