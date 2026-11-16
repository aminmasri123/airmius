import 'dart:convert';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_deep_links.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/navigation/airmius_deep_link_navigator.dart';
import 'package:airmius/screens/carpool_ride_screen.dart';
import 'package:airmius/screens/carpool_center_screen.dart';
import 'package:airmius/screens/notification_detail_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class RequestTransport implements AirmiusApiTransport {
  RequestTransport({
    this.driver = true,
    this.fail = false,
    this.loadStatus = 200,
  });
  final int loadStatus;
  final bool driver;
  final bool fail;
  final calls = <AirmiusApiRequest>[];
  final pending = [
    {'id': 2, 'name': 'Sportler A', 'message': 'Bitte mitnehmen'},
    {'id': 3, 'name': 'Sportler B'},
  ];
  final members = [
    {'id': 1, 'name': 'Fahrer'},
  ];
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    calls.add(request);
    if (request.path == '/api/v1/rides') {
      return const AirmiusApiResponse(
        statusCode: 200,
        body: '{"data":{"rides":[],"clubs":[],"teams":[],"events":[]}}',
      );
    }
    if (request.method == 'GET' && loadStatus != 200) {
      return AirmiusApiResponse(
        statusCode: loadStatus,
        body: '{"message":"Unavailable"}',
      );
    }
    if (request.method == 'POST') {
      if (fail) {
        return const AirmiusApiResponse(
          statusCode: 422,
          body: '{"message":"Anfrage nicht mehr verfügbar"}',
        );
      }
      final id = int.parse(request.path.split('/')[6]);
      if (request.path.endsWith('approve')) {
        members.add({
          'id': id,
          'name': pending.firstWhere((p) => p['id'] == id)['name']!,
        });
      }
      pending.removeWhere((p) => p['id'] == id);
    }
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': {
          'id': 12,
          'from': 'Saarbrücken',
          'to': 'Berlin',
          'driver': {'id': 1, 'name': 'Fahrer'},
          'is_driver': driver,
          'departure_time': '2026-12-01T10:00:00Z',
          'seats': 3,
          'participants_count': members.length,
          'available_seats': 3 - members.length,
          'pending_requests': driver ? pending : [],
          'users': members,
        },
      }),
    );
  }
}

Future<void> showRide(
  WidgetTester tester,
  RequestTransport transport, {
  Widget? home,
}) async {
  final container = AirmiusServiceContainer(
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
        container: container,
        child: MaterialApp(
          home:
              home ??
              AirmiusDeepLinkNavigator.screenFor(
                const AirmiusDeepLinkResolver().resolve('airmius://rides/12'),
              ),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets(
    'deleted ride notification shows cancellation without loading the deleted ride',
    (tester) async {
      final transport = RequestTransport(loadStatus: 404);
      final notification = AirmiusNotification.fromJson({
        'id': 1,
        'read': true,
        'type': 'ride.deleted',
        'data': {'ride_id': 12, 'mobile_url': 'airmius://rides/12'},
      });
      expect(notification.actionUrl, 'airmius://rides?cancelled=1');
      await showRide(
        tester,
        transport,
        home: AirmiusDeepLinkNavigator.screenFor(
          const AirmiusDeepLinkResolver().resolve(notification.actionUrl!),
        ),
      );
      expect(find.text('Fahrt wurde abgesagt'), findsOneWidget);
      expect(find.text('Andere Fahrgemeinschaften finden'), findsOneWidget);
      expect(transport.calls, isEmpty);
      expect(find.text('Annehmen'), findsNothing);
      expect(find.text('Ablehnen'), findsNothing);
      await tester.tap(find.text('Andere Fahrgemeinschaften finden'));
      await tester.pumpAndSettle();
      expect(find.byType(CarpoolCenterScreen), findsOneWidget);
      expect(transport.calls.any((r) => r.path == '/api/v1/rides/12'), false);
    },
  );

  testWidgets('legacy deletion notification detail also shows cancellation', (
    tester,
  ) async {
    final transport = RequestTransport(loadStatus: 404);
    await showRide(
      tester,
      transport,
      home: NotificationDetailScreen(
        notification: AirmiusNotification.fromJson({
          'id': 1,
          'read': true,
          'type': 'ride.deleted',
          'data': {'ride_id': 12},
        }),
        typeLabel: 'Fahrgemeinschaften',
        icon: Icons.notifications,
        onChanged: () {},
      ),
    );
    expect(find.text('Fahrt wurde abgesagt'), findsOneWidget);
    expect(transport.calls, isEmpty);
  });

  for (final status in [404, 403, 405, 503]) {
    testWidgets(
      'old ride link explains HTTP $status without stale decision controls',
      (tester) async {
        await showRide(tester, RequestTransport(loadStatus: status));
        expect(
          find.text(
            status == 404
                ? 'Fahrt nicht mehr vorhanden'
                : 'Fahrt konnte nicht geladen werden',
          ),
          findsOneWidget,
        );
        expect(find.text('Andere Fahrgemeinschaften finden'), findsOneWidget);
        expect(find.text('Annehmen'), findsNothing);
        expect(find.text('Ablehnen'), findsNothing);
        if (status != 404) {
          expect(
            find.textContaining('möglicherweise vom Fahrer abgesagt'),
            findsNothing,
          );
        }
      },
    );
  }

  testWidgets(
    'legacy notification detail opens live requests instead of generic text',
    (tester) async {
      await showRide(
        tester,
        RequestTransport(),
        home: NotificationDetailScreen(
          notification: AirmiusNotification.fromJson({
            'id': 1,
            'read': true,
            'type': 'ride.requested',
            'data': {'ride_id': 12},
          }),
          typeLabel: 'Verein',
          icon: Icons.notifications,
          onChanged: () {},
        ),
      );
      expect(find.byType(CarpoolRideScreen), findsOneWidget);
      expect(find.text('Annehmen'), findsNWidgets(2));
      expect(find.text('Als gelesen markieren'), findsNothing);
    },
  );

  test('ride links and historical notification fallbacks target the ride', () {
    for (final url in [
      'airmius://rides/12',
      '/rides?ride=12',
      'https://airmius.com/rides?ride=12',
    ]) {
      final target = const AirmiusDeepLinkResolver().resolve(url);
      expect(target.type, AirmiusDeepLinkTargetType.ride);
      expect(target.id, 12);
      expect(target.requiresAuth, true);
    }
    expect(
      const AirmiusDeepLinkResolver().resolve('/rides/-1').type,
      AirmiusDeepLinkTargetType.unknown,
    );
    final notification = AirmiusNotification.fromJson({
      'id': 1,
      'type': 'ride.requested',
      'data': {'ride_id': 12, 'mobile_url': 'airmius://notifications'},
    });
    expect(notification.actionUrl, 'airmius://rides/12');
  });

  testWidgets(
    'driver approves and rejects two requests without leaving the ride',
    (tester) async {
      final transport = RequestTransport();
      await showRide(tester, transport);
      expect(find.byType(CarpoolRideScreen), findsOneWidget);
      await tester.ensureVisible(find.text('Annehmen').first);
      await tester.tap(find.text('Annehmen').first);
      await tester.pumpAndSettle();
      expect(transport.calls.last.path, '/api/v1/rides/12/requests/2/approve');
      expect(find.text('Sportler B'), findsOneWidget);
      expect(find.text('Annehmen'), findsOneWidget);
      await tester.ensureVisible(find.text('Ablehnen'));
      await tester.tap(find.text('Ablehnen'));
      await tester.pumpAndSettle();
      expect(transport.calls.last.path, '/api/v1/rides/12/requests/3/reject');
      await tester.drag(find.byType(ListView).first, const Offset(0, 800));
      await tester.pumpAndSettle();
      expect(find.text('Keine offenen Mitfahranfragen.'), findsOneWidget);
      expect(find.byType(CarpoolRideScreen), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('passengers never see request decision controls', (tester) async {
    await showRide(tester, RequestTransport(driver: false));
    expect(find.text('Annehmen'), findsNothing);
    expect(find.text('Ablehnen'), findsNothing);
  });

  testWidgets(
    'failed decision keeps pending request and shows the server error',
    (tester) async {
      await showRide(tester, RequestTransport(fail: true));
      await tester.ensureVisible(find.text('Annehmen').first);
      await tester.tap(find.text('Annehmen').first);
      await tester.pumpAndSettle();
      expect(find.text('Anfrage nicht mehr verfügbar'), findsOneWidget);
      expect(find.text('Sportler A'), findsOneWidget);
      expect(find.text('Annehmen'), findsNWidgets(2));
    },
  );
}
