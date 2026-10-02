import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/platform_admin_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  for (final list in ['users', 'clubs', 'warnings']) {
    testWidgets(
      '$list rejects long drafts without poisoning refresh and recovers from 422',
      (tester) async {
        final transport = _ListTransport();
        await _pump(
          tester,
          transport,
          list == 'warnings' ? 'moderation' : list,
        );
        final search = find.byKey(ValueKey('$list-search'));
        await tester.ensureVisible(search);
        await tester.enterText(search, 'accepted');
        await tester.testTextInput.receiveAction(TextInputAction.search);
        await tester.pumpAndSettle();

        await tester.ensureVisible(search);
        // Pasted text and multi-code-point graphemes must not exceed Laravel's limit.
        for (final longText in ['x' * 256, 'e\u0301' * 128]) {
          await tester.enterText(search, longText);
          expect(tester.widget<TextField>(search).controller!.text, 'accepted');
        }
        final before = transport.requests.length;
        // Controller assignments bypass formatters; submission must still validate.
        tester.widget<TextField>(search).controller!.text = 'x' * 256;
        await tester.testTextInput.receiveAction(TextInputAction.search);
        await tester.pumpAndSettle();
        expect(transport.requests.length, before);
        expect(find.text('256 / 255'), findsOneWidget);
        await _tap(
          tester,
          find.descendant(of: search, matching: find.byIcon(Icons.search)),
        );
        expect(transport.requests.length, before);
        await _tap(tester, find.byTooltip('Refresh'));
        expect(transport.lastQuery['${list}_q'], 'accepted');

        await tester.ensureVisible(search);
        await tester.enterText(search, 'rejected query');
        await tester.testTextInput.receiveAction(TextInputAction.search);
        await tester.pumpAndSettle();
        expect(find.text('Search query rejected by server.'), findsOneWidget);
        expect(search, findsOneWidget);
        await _tap(tester, find.text('Try again'));
        expect(transport.lastQuery['${list}_q'], 'accepted');
        await _tap(tester, find.byTooltip('Refresh'));
        expect(transport.lastQuery['${list}_q'], 'accepted');

        await tester.ensureVisible(search);
        await tester.enterText(search, 'corrected');
        await tester.testTextInput.receiveAction(TextInputAction.search);
        await tester.pumpAndSettle();
        expect(transport.lastQuery['${list}_q'], 'corrected');
        expect(
          transport.requests.where(
            (request) => request.query['${list}_q'] == 'rejected query',
          ),
          hasLength(1),
        );
        expect(
          transport.requests.any(
            (request) => (request.query['${list}_q'] ?? '').runes.length > 255,
          ),
          isFalse,
        );
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets('non-validation failures remain visible and retryable', (
    tester,
  ) async {
    final transport = _ListTransport();
    await _pump(tester, transport, 'users');
    final search = find.byKey(const ValueKey('users-search'));
    await tester.ensureVisible(search);
    await tester.enterText(search, 'server failure');
    await tester.testTextInput.receiveAction(TextInputAction.search);
    await tester.pumpAndSettle();
    expect(find.text('Service unavailable.'), findsOneWidget);
    expect(search, findsNothing);
    await _tap(tester, find.text('Try again'));
    expect(transport.lastQuery['users_q'], 'server failure');
    expect(find.text('Service unavailable.'), findsOneWidget);
  });

  test(
    'platform client forwards independent page and filter parameters',
    () async {
      final transport = _ListTransport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
      );
      const query = {
        'users_page': '3',
        'users_q': 'member@example.test',
        'users_status': 'suspended',
        'clubs_page': '5',
        'clubs_q': 'Club',
        'clubs_status': 'pending',
        'flags_page': '2',
        'flags_status': 'open',
        'flags_category': 'rare-category',
        'reports_page': '4',
        'reports_appeal': 'pending',
        'reports_status': 'dismissed',
        'warnings_page': '5',
        'warnings_category': 'rare-category',
        'warnings_severity': 'high',
        'warnings_q': 'warning',
      };
      await client.adminPlatformDashboard(query: query);
      expect(transport.requests.single.method, 'GET');
      expect(transport.requests.single.path, '/api/v1/admin/platform');
      expect(transport.requests.single.query, query);
    },
  );

  for (final section in ['users', 'clubs']) {
    testWidgets('$section list searches and filters beyond the first page', (
      tester,
    ) async {
      final transport = _ListTransport();
      await _pump(tester, transport, section);
      await _tap(tester, find.byKey(ValueKey('$section-next')));
      expect(transport.lastQuery['${section}_page'], '2');
      final search = find.byKey(ValueKey('$section-search'));
      await tester.ensureVisible(search);
      await tester.enterText(search, 'Older record');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      expect(transport.lastQuery['${section}_q'], 'Older record');
      expect(transport.lastQuery['${section}_page'], '1');
      await _tap(tester, find.byKey(ValueKey('$section-next')));
      await _tap(tester, find.byKey(ValueKey('$section-status-all')));
      await _tap(
        tester,
        find.text(section == 'users' ? 'Suspended' : 'Verified').last,
      );
      expect(
        transport.lastQuery['${section}_status'],
        section == 'users' ? 'suspended' : 'verified',
      );
      expect(transport.lastQuery['${section}_page'], '1');
      expect(transport.lastQuery['${section}_q'], 'Older record');
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets(
    'moderation has independent paging and category filters even for empty results',
    (tester) async {
      final transport = _ListTransport(permission: 'moderation.manage');
      await _pump(tester, transport, 'moderation');
      expect(find.byType(ChoiceChip), findsOneWidget);
      for (final list in ['flags', 'reports', 'warnings']) {
        await _tap(tester, find.byKey(ValueKey('$list-next')));
        expect(transport.lastQuery['${list}_page'], '2');
      }
      for (final list in ['flags', 'warnings']) {
        await _tap(tester, find.byKey(ValueKey('$list-category-all')));
        await _tap(tester, find.text('rare-category').last);
        expect(transport.lastQuery['${list}_category'], 'rare-category');
        expect(transport.lastQuery['${list}_page'], '1');
        expect(transport.lastQuery['reports_page'], '2');
        expect(
          find.byKey(ValueKey('$list-category-rare-category')),
          findsOneWidget,
        );
      }
      await _tap(tester, find.byKey(const ValueKey('reports-appeal-all')));
      await _tap(tester, find.text('Under review').last);
      expect(transport.lastQuery['reports_appeal'], 'pending');
      expect(transport.lastQuery['reports_page'], '1');
      expect(transport.lastQuery['warnings_category'], 'rare-category');
      expect(find.text('170'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('server page correction is retained by subsequent requests', (
    tester,
  ) async {
    final transport = _ListTransport(clampPages: true);
    await _pump(tester, transport, 'users');
    await _tap(tester, find.byKey(const ValueKey('users-next')));
    expect(transport.lastQuery['users_page'], '2');
    await _tap(tester, find.byTooltip('Refresh'));
    expect(transport.lastQuery['users_page'], '1');
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'sports search matches name slug and category with active filters',
    (tester) async {
      final transport = _ListTransport();
      await _pump(tester, transport, 'sports');
      for (final term in ['rowing', 'river-slug', 'water']) {
        final search = find.byKey(const ValueKey('sports-search'));
        await tester.ensureVisible(search);
        await tester.enterText(search, term);
        await tester.testTextInput.receiveAction(TextInputAction.search);
        await tester.pumpAndSettle();
        expect(find.text('Rowing'), findsOneWidget);
        expect(find.text('Running'), findsNothing);
      }
      await _tap(tester, find.byKey(const ValueKey('sports-status-all')));
      await _tap(tester, find.text('Inactive').last);
      expect(find.text('Rowing'), findsNothing);
      expect(find.byKey(const ValueKey('sports-search')), findsOneWidget);
      final search = find.byKey(const ValueKey('sports-search'));
      await tester.ensureVisible(search);
      await tester.enterText(search, '');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      expect(find.text('Running'), findsOneWidget);
      expect(
        transport.requests.where(
          (request) => request.path == '/api/v1/admin/platform',
        ),
        hasLength(1),
      );
      expect(tester.takeException(), isNull);
    },
  );
}

Future<void> _tap(WidgetTester tester, Finder finder) async {
  await tester.ensureVisible(finder);
  await tester.pumpAndSettle();
  await tester.tap(finder);
  await tester.pumpAndSettle();
}

Future<void> _pump(
  WidgetTester tester,
  _ListTransport transport,
  String section,
) async {
  tester.view.physicalSize = const Size(390, 1000);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final tokens = AirmiusMemoryTokenStore();
  await tokens.write(
    AirmiusSession(
      token: 'test-token',
      locale: 'en',
      user: AirmiusUser(
        id: 1,
        name: 'Specialist',
        email: 'specialist@example.test',
        role: 'member',
        permissions: [transport.permission],
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
      language: AirmiusLanguage.en,
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
          home: PlatformAdminScreen(initialSection: section),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _ListTransport implements AirmiusApiTransport {
  _ListTransport({this.permission = 'system.manage', this.clampPages = false});
  final String permission;
  final bool clampPages;
  final requests = <AirmiusApiRequest>[];
  Map<String, String> get lastQuery => requests.last.query;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (request.query.values.contains('rejected query')) {
      return AirmiusApiResponse(
        statusCode: 422,
        body: jsonEncode({'message': 'Search query rejected by server.'}),
      );
    }
    if (request.query.values.contains('server failure')) {
      return AirmiusApiResponse(
        statusCode: 500,
        body: jsonEncode({'message': 'Service unavailable.'}),
      );
    }
    Map<String, Object> meta(String list) => {
      'current_page': clampPages
          ? 1
          : int.parse(request.query['${list}_page'] ?? '1'),
      'last_page': 3,
      'total': 51,
      'per_page': 25,
    };
    final body = request.path == '/api/v1/me'
        ? {
            'data': {
              'id': 1,
              'name': 'Specialist',
              'email': 'specialist@example.test',
              'permissions': [permission],
            },
          }
        : {
            'data': {
              'abilities': {'system_manage': permission == 'system.manage'},
              'summary': {'moderation_open': 170, 'warnings_90_days': 105},
              'users': [],
              'users_meta': meta('users'),
              'clubs': [],
              'clubs_meta': meta('clubs'),
              'sports': [
                {
                  'id': 1,
                  'name': 'Rowing',
                  'slug': 'river-slug',
                  'category': 'Water',
                  'is_active': true,
                },
                {
                  'id': 2,
                  'name': 'Running',
                  'slug': 'run',
                  'category': 'Land',
                  'is_active': false,
                },
              ],
              'moderation': {
                for (final list in ['flags', 'reports', 'warnings']) ...{
                  list: [],
                  '${list}_meta': meta(list),
                },
                'flag_categories': ['rare-category'],
                'warning_categories': ['rare-category'],
              },
            },
          };
    return AirmiusApiResponse(statusCode: 200, body: jsonEncode(body));
  }
}
