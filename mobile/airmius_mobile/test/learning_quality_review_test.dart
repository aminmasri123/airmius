import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_preferences_store_base.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/learning_quality_review_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('review is denied without permission and does not load courses', (
    tester,
  ) async {
    final transport = _Transport(allowed: false);
    await _pump(tester, transport);
    expect(find.text('Access denied'), findsOneWidget);
    expect(
      transport.requests.where((r) => r.path.contains('/admin/learning')),
      isEmpty,
    );
  });

  testWidgets(
    'review prefills values, cancels, saves and refreshes at large text scale',
    (tester) async {
      final transport = _Transport();
      await _pump(tester, transport);
      await tester.tap(find.text('Private course'));
      await tester.pumpAndSettle();
      expect(find.text('Existing feedback'), findsOneWidget);
      expect(
        tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value,
        isTrue,
      );
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(transport.requests.where((r) => r.method == 'PUT'), isEmpty);

      await tester.tap(find.text('Private course'));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Changes requested').last);
      await tester.pumpAndSettle();
      await tester.enterText(
        find.widgetWithText(TextField, 'Review note'),
        'Needs examples',
      );
      await tester.tap(find.byType(SwitchListTile));
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();
      final request = transport.requests.singleWhere((r) => r.method == 'PUT');
      expect(request.path, '/api/v1/admin/learning/courses/4/quality');
      expect(request.body, {
        'quality_status': 'changes_requested',
        'quality_note': 'Needs examples',
        'featured': false,
      });
      expect(
        transport.requests
            .where((r) => r.path == '/api/v1/admin/learning/courses')
            .length,
        2,
      );
      expect(find.text('Course quality status saved.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('failed review preserves input and permits retry', (
    tester,
  ) async {
    final transport = _Transport()..failSave = true;
    await _pump(tester, transport);
    await tester.tap(find.text('Private course'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextField, 'Review note'),
      'Keep my draft',
    );
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    expect(find.text('Review rejected by server'), findsOneWidget);
    expect(find.text('Keep my draft'), findsOneWidget);
    transport.failSave = false;
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    expect(find.byType(AlertDialog), findsNothing);
  });

  testWidgets(
    'course search, pagination, load error and retry use native API',
    (tester) async {
      final transport = _Transport()..failLoad = true;
      await _pump(tester, transport);
      expect(find.text('Review rejected by server'), findsOneWidget);
      transport.failLoad = false;
      await tester.tap(find.text('Retry'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Next page'));
      await tester.pumpAndSettle();
      expect(transport.requests.last.query['page'], '2');
      await tester.enterText(find.byType(TextField), 'needle');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      expect(transport.requests.last.query, {'q': 'needle', 'page': '1'});
      expect(find.text('No courses found'), findsOneWidget);
    },
  );
}

Future<void> _pump(WidgetTester tester, _Transport transport) async {
  tester.view.physicalSize = const Size(390, 1000);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final tokens = AirmiusMemoryTokenStore();
  await tokens.write(
    AirmiusSession(
      token: 'test',
      locale: 'en',
      user: AirmiusUser.fromJson(transport.user),
    ),
  );
  final container = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://airmius.test',
      enableOfflineQueue: false,
    ),
    transport: transport,
    tokenStore: tokens,
    pushDeviceStore: _Store(),
  );
  await container.authState.restore();
  await tester.pumpWidget(
    AirmiusServicesScope(
      container: container,
      child: MaterialApp(
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(
            context,
          ).copyWith(textScaler: const TextScaler.linear(1.4)),
          child: child!,
        ),
        home: const LearningQualityReviewScreen(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _Store implements AirmiusPreferencesStore {
  @override
  Future<String?> readString(String key) async => null;
  @override
  Future<void> writeString(String key, String value) async {}
}

class _Transport implements AirmiusApiTransport {
  _Transport({this.allowed = true});
  final bool allowed;
  bool failSave = false;
  bool failLoad = false;
  final requests = <AirmiusApiRequest>[];
  Map<String, dynamic> get user => {
    'id': 1,
    'name': 'Reviewer',
    'email': 'reviewer@example.test',
    'role': 'member',
    'permissions': [if (allowed) 'subscriptions.manage'],
  };

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (request.path == '/api/v1/me') {
      return AirmiusApiResponse(
        statusCode: 200,
        body: jsonEncode({'data': user}),
      );
    }
    if ((request.method == 'PUT' && failSave) ||
        (request.method == 'GET' && failLoad)) {
      return const AirmiusApiResponse(
        statusCode: 422,
        body: '{"message":"Review rejected by server"}',
      );
    }
    if (request.method == 'PUT') {
      return const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}');
    }
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': request.query['q'] == 'needle'
            ? []
            : [
                {
                  'id': 4,
                  'title': 'Private course',
                  'quality_status': 'pending',
                  'quality_note': 'Existing feedback',
                  'featured_at': '2026-10-01',
                  'tutor': {'name': 'Creator'},
                },
              ],
        'last_page': 2,
        'next_page_url': request.query['page'] == '2'
            ? null
            : '/api/v1/admin/learning/courses?page=2',
      }),
    );
  }
}
