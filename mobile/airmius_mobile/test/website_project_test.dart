import 'package:intl/date_symbol_data_local.dart';
import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/screens/website_project_screen.dart';

void main() {
  setUpAll(() => initializeDateFormatting('de'));
  testWidgets('requester reads brief and sends a question on a narrow screen', (
    tester,
  ) async {
    final transport = _ProjectTransport();
    await _pump(tester, transport, const WebsiteProjectScreen(projectId: 7));
    expect(find.text('Neue Website'), findsOneWidget);
    expect(find.byType(DropdownButtonFormField<String>), findsNothing);
    await tester.scrollUntilVisible(
      find.byType(TextField),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.enterText(
      find.byType(TextField),
      'Welche Bilder braucht ihr?',
    );
    final send = find.byType(FilledButton);
    await tester.ensureVisible(send);
    await tester.tap(send);
    await tester.pumpAndSettle();
    expect(transport.sent.single.body!['body'], 'Welche Bilder braucht ihr?');
    expect(transport.sent.single.body!['request_key'], isNotEmpty);
    expect(
      tester.widget<TextField>(find.byType(TextField)).controller!.text,
      isEmpty,
    );
    expect(tester.takeException(), isNull);
  });
}

class _ProjectTransport implements AirmiusApiTransport {
  final permission = 'marketplace.manage';
  final sent = <AirmiusApiRequest>[];
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.path.endsWith('/messages')) {
      sent.add(request);
      return AirmiusApiResponse(statusCode: 200, body: '{"ok":true}');
    }
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': {
          'id': 7,
          'project_title': 'Neue Website',
          'status': 'new',
          'goals': 'Mitglieder gewinnen',
          'inspiration_urls': ['https://example.org'],
          'created_at': '2026-11-27T12:00:00Z',
        },
        'messages': {'data': [], 'last_page': 1},
      }),
    );
  }
}

Future<void> _pump(
  WidgetTester tester,
  _ProjectTransport transport,
  Widget screen, {
  bool authenticate = false,
}) async {
  tester.view.physicalSize = const Size(390, 1200);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final tokens = AirmiusMemoryTokenStore();
  if (authenticate) {
    await tokens.write(
      AirmiusSession(
        token: 'test-token',
        locale: 'de',
        user: AirmiusUser(
          id: 1,
          name: 'Specialist',
          email: 'specialist@example.test',
          role: 'member',
          permissions: [transport.permission],
        ),
      ),
    );
  }
  final container = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://example.test',
      enableOfflineQueue: false,
    ),
    transport: transport,
    tokenStore: tokens,
  );
  if (authenticate) await container.authState.restore();
  await tester.pumpWidget(
    AirmiusScope(
      language: AirmiusLanguage.de,
      setLanguage: (_) {},
      child: AirmiusServicesScope(
        container: container,
        child: MaterialApp(
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(
              context,
            ).copyWith(textScaler: const TextScaler.linear(1.3)),
            child: child!,
          ),
          home: screen,
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}
