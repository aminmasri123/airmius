import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_module_access.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/admin_backoffice_screen.dart';
import 'package:airmius/screens/editorial_management_screen.dart';
import 'package:airmius/screens/platform_admin_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('editorial search and status reset pagination', (tester) async {
    final transport = _ParityTransport();
    await _pump(tester, transport, const EditorialManagementScreen());
    await tester.ensureVisible(find.byIcon(Icons.chevron_right));
    await tester.tap(find.byIcon(Icons.chevron_right));
    await tester.pumpAndSettle();
    expect(transport.requests.last.query['page'], '2');
    await tester.enterText(find.byType(TextField), 'Article');
    await tester.testTextInput.receiveAction(TextInputAction.search);
    await tester.pumpAndSettle();
    expect(transport.requests.last.query, containsPair('q', 'Article'));
    expect(transport.requests.last.query['page'], '1');
    await tester.ensureVisible(find.widgetWithText(ChoiceChip, 'Entwurf'));
    await tester.tap(find.widgetWithText(ChoiceChip, 'Entwurf'));
    await tester.pumpAndSettle();
    expect(transport.requests.last.query['status'], 'draft');
    expect(transport.requests.last.query['page'], '1');
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'contracts paginate and search without write controls for readers',
    (tester) async {
      final transport = _ParityTransport();
      await _pump(
        tester,
        transport,
        const AdminBackofficeScreen(initialSection: 'contracts'),
      );
      await tester.ensureVisible(find.byIcon(Icons.chevron_right));
      await tester.pumpAndSettle();
      await tester.tap(find.byIcon(Icons.chevron_right));
      await tester.pumpAndSettle();
      expect(transport.requests.last.query['contracts_page'], '2');
      await tester.enterText(find.byType(TextField), 'REF-22');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      expect(transport.requests.last.query['contracts_q'], 'REF-22');
      expect(transport.requests.last.query['contracts_page'], '1');
      expect(find.byIcon(Icons.add_circle_outline), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  for (final section in ['roles', 'moderation']) {
    testWidgets('$section specialist sees only their platform section', (
      tester,
    ) async {
      final transport = _ParityTransport(
        permission: section == 'roles'
            ? 'users.assign_roles'
            : 'moderation.manage',
      );
      await _pump(
        tester,
        transport,
        PlatformAdminScreen(initialSection: section),
        authenticate: true,
      );
      expect(
        transport.requests.any(
          (request) => request.path == '/api/v1/admin/platform',
        ),
        isTrue,
      );
      expect(find.byType(ChoiceChip), findsOneWidget);
      expect(
        find.widgetWithText(
          ChoiceChip,
          section == 'roles' ? 'Rollen & Rechte' : 'Moderation',
        ),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    });
  }

  test(
    'specialist access does not grant platform or other specialist access',
    () {
      const roleManager = AirmiusUser(
        id: 1,
        name: 'Role manager',
        email: 'roles@example.test',
        role: 'member',
        permissions: ['users.assign_roles'],
      );
      expect(
        AirmiusModuleAccess.canOpenPlatformSection(roleManager, 'roles'),
        isTrue,
      );
      expect(
        AirmiusModuleAccess.canOpenPlatformSection(roleManager, 'users'),
        isFalse,
      );
      expect(
        AirmiusModuleAccess.canOpenPlatformSection(roleManager, 'moderation'),
        isFalse,
      );
      const adminWithoutTwoFactor = AirmiusUser(
        id: 2,
        name: 'Admin',
        email: 'admin@example.test',
        role: 'admin',
        roles: ['admin'],
        permissions: ['system.manage', 'users.assign_roles'],
      );
      expect(
        AirmiusModuleAccess.canOpenPlatformSection(
          adminWithoutTwoFactor,
          'roles',
        ),
        isFalse,
      );
    },
  );
}

Future<void> _pump(
  WidgetTester tester,
  _ParityTransport transport,
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

class _ParityTransport implements AirmiusApiTransport {
  _ParityTransport({this.permission = 'finance.view'});
  final String permission;
  final requests = <AirmiusApiRequest>[];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    final Object body;
    if (request.path == '/api/v1/me') {
      body = {
        'data': {
          'id': 1,
          'name': 'Specialist',
          'email': 'specialist@example.test',
          'permissions': [permission],
        },
      };
    } else if (request.path == '/api/v1/editorial/posts') {
      body = {
        'data': [],
        'categories': [],
        'can': {},
        'meta': {
          'current_page': int.parse(request.query['page'] ?? '1'),
          'last_page': 2,
          'total': 31,
        },
      };
    } else if (request.path == '/api/v1/admin/backoffice') {
      body = {
        'data': {
          'abilities': {'finance_view': true, 'finance_edit': false},
          'contracts': [],
          'contracts_meta': {
            'current_page': int.parse(request.query['contracts_page'] ?? '1'),
            'last_page': 2,
            'total': 21,
          },
          'options': {
            'contract_statuses': ['active', 'cancelled'],
            'contract_categories': ['other'],
          },
        },
      };
    } else {
      body = {
        'data': {
          'abilities': {
            'roles_assign': permission == 'users.assign_roles',
            'moderation_manage': permission == 'moderation.manage',
            'system_manage': false,
          },
          'roles': [],
          'permission_groups': [],
          'moderation': {},
        },
      };
    }
    return AirmiusApiResponse(statusCode: 200, body: jsonEncode(body));
  }
}
