import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/support_helpdesk_screen.dart';
import 'package:airmius/screens/admin_support_ticket_screen.dart';
import 'package:airmius/screens/support_ticket_service_center_suite_screen.dart';

void main() {
  for (final role in ['member', 'admin', 'support']) {
    testWidgets('ticket notification opens the correct page for $role', (
      tester,
    ) async {
      await _pump(
        tester,
        _Transport(role: role),
        home: const SupportTicketServiceCenterSuiteScreen(ticketId: 42),
      );
      expect(find.byType(AlertDialog), findsNothing);
      expect(find.byType(SupportHelpdeskScreen), findsNothing);
      if (role == 'member') {
        expect(find.widgetWithText(AppBar, 'Ticket 42'), findsOneWidget);
      } else {
        expect(find.byType(AdminSupportTicketScreen), findsOneWidget);
      }
    });
  }
  testWidgets(
    'withdraw requires confirmation; hide and restore preserve withdrawn ticket',
    (tester) async {
      final transport = _Transport();
      await _pump(tester, transport);
      await tester.tap(find.text('Anfrage zurückziehen'));
      await tester.pumpAndSettle();
      expect(transport.mutations, isEmpty);
      await tester.tap(find.widgetWithText(TextButton, 'Schließen').last);
      await tester.pumpAndSettle();
      expect(transport.mutations, isEmpty);
      await tester.tap(find.text('Anfrage zurückziehen'));
      await tester.pumpAndSettle();
      await tester.tap(
        find.widgetWithText(FilledButton, 'Anfrage zurückziehen'),
      );
      await tester.pumpAndSettle();
      expect(transport.withdrawn, isTrue);
      expect(find.text('Anfrage zurückziehen'), findsNothing);
      expect(find.textContaining('Zurückgezogen'), findsWidgets);
      await tester.tap(find.text('Aus meiner Liste ausblenden'));
      await tester.pumpAndSettle();
      await tester.tap(
        find.widgetWithText(FilledButton, 'Aus meiner Liste ausblenden'),
      );
      await tester.pumpAndSettle();
      expect(find.text('Lifecycle ticket'), findsNothing);
      await tester.tap(
        find.widgetWithText(SwitchListTile, 'Ausgeblendete Anfragen'),
      );
      await tester.pumpAndSettle();
      expect(find.text('Lifecycle ticket'), findsOneWidget);
      await tester.tap(find.text('Wieder anzeigen'));
      await tester.pumpAndSettle();
      expect(find.text('Lifecycle ticket'), findsNothing);
      await tester.tap(
        find.widgetWithText(SwitchListTile, 'Ausgeblendete Anfragen'),
      );
      await tester.pumpAndSettle();
      expect(find.text('Lifecycle ticket'), findsOneWidget);
      expect(transport.withdrawn, isTrue);
      expect(transport.mutations.map((r) => r.method), [
        'POST',
        'PATCH',
        'PATCH',
      ]);
      expect(transport.mutations[1].body, {'hidden': true});
      expect(transport.mutations[2].body, {'hidden': false});
      expect(tester.takeException(), isNull);
    },
  );

  test(
    'lifecycle actions never report offline success or persist in queue',
    () async {
      final inner = _Transport();
      final queued = AirmiusQueuedTransport(
        inner: inner,
        timeout: const Duration(seconds: 1),
      )..offline = true;
      final client = AirmiusApiClient(
        transport: queued,
        baseUrl: 'https://example.test',
        token: 'test',
      );
      await expectLater(
        client.withdrawSupportTicket(42),
        throwsA(isA<AirmiusApiException>()),
      );
      await expectLater(
        client.setSupportTicketVisibility(42, hidden: true),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(queued.queue, isEmpty);
      expect(inner.mutations, isEmpty);
    },
  );

  testWidgets('failed withdrawal keeps the ticket and offers retry', (
    tester,
  ) async {
    final transport = _Transport()..fail = true;
    await _pump(tester, transport);
    await tester.tap(find.text('Anfrage zurückziehen'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Anfrage zurückziehen'));
    await tester.pumpAndSettle();
    expect(transport.withdrawn, isFalse);
    expect(find.text('Lifecycle ticket'), findsOneWidget);
    expect(find.text('Anfrage zurückziehen'), findsOneWidget);
    expect(find.text('Server unavailable'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pump(
  WidgetTester tester,
  _Transport transport, {
  Widget home = const SupportHelpdeskScreen(),
}) async {
  tester.view.physicalSize = const Size(600, 1400);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final tokens = AirmiusMemoryTokenStore();
  await tokens.write(
    AirmiusSession(
      token: 'test',
      locale: 'de',
      user: AirmiusUser(
        id: 1,
        name: 'Owner',
        role: transport.role,
        permissions: transport.permissions,
        email: 'test@example.test',
      ),
    ),
  );
  final container = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://example.test',
      enableOfflineQueue: false,
    ),
    transport: transport,
    tokenStore: tokens,
  );
  await container.authState.restore();
  await tester.pumpWidget(
    AirmiusScope(
      language: AirmiusLanguage.de,
      setLanguage: (_) {},
      child: AirmiusServicesScope(
        container: container,
        child: MaterialApp(home: home),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _Transport implements AirmiusApiTransport {
  _Transport({this.role = 'member'});
  final String role;
  List<String> get permissions => role == 'support' ? ['support.tickets'] : [];
  bool hidden = false;
  bool withdrawn = false;
  bool fail = false;
  final mutations = <AirmiusApiRequest>[];
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    Object body = {'data': []};
    if (request.path == '/api/v1/me') {
      body = {
        'data': {
          'id': 1,
          'name': 'Owner',
          'email': 'test@example.test',
          'role': role,
          'permissions': permissions,
        },
      };
    } else if (request.path == '/api/v1/admin/support/tickets') {
      body = {
        'data': {'tickets': []},
      };
    } else if (request.path == '/api/v1/support/tickets/42') {
      body = {
        'data': {'id': 42, 'subject': 'Ticket 42', 'message': 'Test'},
      };
    } else if (request.path.endsWith('/withdraw') ||
        request.path.endsWith('/visibility')) {
      mutations.add(request);
      if (fail) {
        return const AirmiusApiResponse(
          statusCode: 503,
          body: '{"message":"Server unavailable"}',
        );
      }
      if (request.path.endsWith('/withdraw')) withdrawn = true;
      if (request.path.endsWith('/visibility')) {
        hidden = request.body!['hidden'] == true;
      }
      body = {'message': 'OK'};
    } else if (request.path == '/api/v1/support/tickets') {
      body = {
        'data': [
          if (hidden == (request.query['hidden'] == '1'))
            {
              'id': 42,
              'subject': 'Lifecycle ticket',
              'status': withdrawn ? 'closed' : 'open',
              'can_withdraw': !withdrawn,
              'can_hide': true,
              'withdrawn_at': withdrawn ? '2026-11-16T12:00:00Z' : null,
              'requester_hidden_at': hidden ? '2026-11-16T12:00:00Z' : null,
            },
        ],
      };
    }
    return AirmiusApiResponse(statusCode: 200, body: jsonEncode(body));
  }
}
