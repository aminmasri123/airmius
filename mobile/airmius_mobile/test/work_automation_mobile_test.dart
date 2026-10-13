import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_preferences_store.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/core/work_automation_labels.dart';
import 'package:airmius/core/work_automation_request_store.dart';
import 'package:airmius/screens/work_automation_screen.dart';

void main() {
  setUpAll(() => initializeDateFormatting('en'));
  test(
    'automation API uses club-scoped endpoints and exact retry attempts',
    () async {
      final transport = _AutomationTransport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
      );
      await client.workAutomationClubs();
      await client.workAutomationJobs(7, status: 'failed', page: 2);
      await client.workAutomationRecipients(7, ['members.manage']);
      await client.createWorkAutomationJob(7, {
        'kind': 'reminder',
        'idempotency_key': 'stable-key',
        'payload': {'title': 'Reminder', 'body': 'Message'},
      });
      await client.workAutomationJob(7, 3);
      await client.retryWorkAutomationJob(7, 3, 2);
      expect(transport.requests.map((r) => '${r.method} ${r.path}'), [
        'GET /api/v1/work-automation-clubs',
        'GET /api/v1/clubs/7/work-automation-jobs',
        'GET /api/v1/clubs/7/work-automation-recipients',
        'POST /api/v1/clubs/7/work-automation-jobs',
        'GET /api/v1/clubs/7/work-automation-jobs/3',
        'POST /api/v1/clubs/7/work-automation-jobs/3/retry',
      ]);
      expect(transport.requests[1].query, {'page': '2', 'status': 'failed'});
      expect(transport.requests[2].query, {
        'recipient_roles[0]': 'members.manage',
      });
      expect(transport.requests.last.body, {'expected_attempts': 2});
    },
  );

  test('mutations are never automatically retried or queued offline', () async {
    final inner = _FailingTransport();
    final transport = AirmiusQueuedTransport(
      inner: inner,
      timeout: const Duration(seconds: 1),
      maxRetries: 2,
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://example.test',
    );
    await expectLater(
      client.createWorkAutomationJob(7, {'idempotency_key': 'stable'}),
      throwsA(isA<AirmiusApiException>()),
    );
    expect(inner.calls, 1);
    transport.offline = true;
    await expectLater(
      client.retryWorkAutomationJob(7, 3, 1),
      throwsA(isA<AirmiusApiException>()),
    );
    expect(transport.queue, isEmpty);
    expect(inner.calls, 1);
  });

  test(
    'uncertain request survives restart with same key, account and club isolation',
    () async {
      final preferences = _MemoryStore();
      final first = WorkAutomationRequestStore(store: preferences);
      final payload = {
        'kind': 'reminder',
        'idempotency_key': WorkAutomationRequestStore.newKey(),
        'payload': {'title': 'Saved', 'body': 'Exact message'},
      };
      await first.save('server:7', 9, payload);
      final restored = WorkAutomationRequestStore(store: preferences);
      expect(await restored.read('server:7', 9), payload);
      expect(await restored.read('server:8', 9), isNull);
      expect(await restored.read('server:7', 10), isNull);
      await restored.clear('server:7', 9);
      expect(await first.read('server:7', 9), isNull);
    },
  );

  testWidgets(
    'small create dialog returns a real notification request and blocks zero recipients',
    (tester) async {
      AirmiusJson? result;
      await tester.pumpWidget(
        _localized(
          MaterialApp(
            home: Builder(
              builder: (context) => Scaffold(
                body: TextButton(
                  onPressed: () async {
                    result = await showDialog<AirmiusJson>(
                      context: context,
                      builder: (_) => WorkAutomationCreateDialog(
                        permissions: const [],
                        preview: (_) async => 1,
                      ),
                    );
                  },
                  child: const Text('Open'),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Open'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const ValueKey('automation-title')),
        'Reminder',
      );
      await tester.enterText(
        find.byKey(const ValueKey('automation-body')),
        'Exact message',
      );
      await tester.ensureVisible(find.text('Queue job'));
      await tester.tap(find.text('Queue job'));
      await tester.pumpAndSettle();
      expect(result, {
        'kind': 'reminder',
        'recipient_roles': [],
        'payload': {'title': 'Reminder', 'body': 'Exact message'},
        'scheduled_at': null,
      });
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(
        _localized(
          MaterialApp(
            home: WorkAutomationCreateDialog(
              permissions: const [],
              preview: (_) async => 0,
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull,
      );
    },
  );

  testWidgets(
    'screen uses server failure state, persists creation, refreshes and retries actual job',
    (tester) async {
      final transport = _AutomationTransport();
      final store = WorkAutomationRequestStore(store: _MemoryStore());
      final container = AirmiusServiceContainer(
        environment: const AirmiusAppEnvironment(
          apiBaseUrl: 'https://example.test',
          enableOfflineQueue: false,
        ),
        transport: transport,
      );
      Widget screen() => _localized(
        AirmiusServicesScope(
          container: container,
          child: MaterialApp(
            home: WorkAutomationScreen(initialClubId: 7, requestStore: store),
          ),
        ),
      );
      await tester.pumpWidget(screen());
      await tester.pumpAndSettle();
      expect(find.textContaining('Failed · Attempts: 1'), findsOneWidget);
      await tester.tap(find.byTooltip('Create job'));
      await tester.pumpAndSettle();
      expect(find.text('Authorized recipients: 1'), findsOneWidget);
      await tester.enterText(
        find.byKey(const ValueKey('automation-title')),
        'Persisted reminder',
      );
      await tester.enterText(
        find.byKey(const ValueKey('automation-body')),
        'Saved message',
      );
      await tester.ensureVisible(find.text('Queue job'));
      await tester.tap(find.text('Queue job'));
      await tester.pumpAndSettle();
      expect(transport.createdPayload?['idempotency_key'], isNotEmpty);
      expect(find.textContaining('Queued'), findsWidgets);
      await tester.tap(find.text('Close'));
      await tester.pumpAndSettle();
      await tester.pumpWidget(const SizedBox());
      await tester.pumpAndSettle();
      await tester.pumpWidget(screen());
      await tester.pumpAndSettle();
      expect(find.text('Persisted reminder #4'), findsOneWidget);
      await tester.tap(find.text('Retry').first);
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Retry'));
      await tester.pumpAndSettle();
      expect(
        transport.requests.lastWhere((r) => r.path.endsWith('/retry')).body,
        {'expected_attempts': 1},
      );
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets(
    'no permission never shows a create action or fabricated success',
    (tester) async {
      final transport = _AutomationTransport()..forbidden = true;
      final container = AirmiusServiceContainer(
        environment: const AirmiusAppEnvironment(
          apiBaseUrl: 'https://example.test',
          enableOfflineQueue: false,
        ),
        transport: transport,
      );
      await tester.pumpWidget(
        _localized(
          AirmiusServicesScope(
            container: container,
            child: const MaterialApp(
              home: WorkAutomationScreen(initialClubId: 7),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('No permission for work automation'), findsOneWidget);
      expect(find.byTooltip('Create job'), findsNothing);
      expect(find.textContaining('Completed'), findsNothing);
      await tester.pumpWidget(const SizedBox());
    },
  );

  test('automation labels cover all supported languages', () {
    for (final language in AirmiusLanguage.values) {
      for (final key in [
        'title',
        'supported',
        'queued',
        'scheduled',
        'failed',
        'retry',
        'uncertain',
        'recipientCount',
        'noRecipients',
      ]) {
        expect(workAutomationLabel(language, key), isNot(key));
      }
    }
  });
}

Widget _localized(Widget child) => AirmiusScope(
  language: AirmiusLanguage.en,
  setLanguage: (_) {},
  child: child,
);

class _MemoryStore implements AirmiusPreferencesStore {
  final values = <String, String>{};
  @override
  Future<String?> readString(String key) async => values[key];
  @override
  Future<void> writeString(String key, String value) async {
    values[key] = value;
  }
}

class _FailingTransport implements AirmiusApiTransport {
  int calls = 0;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    calls++;
    throw StateError('Connection lost');
  }
}

class _AutomationTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  bool forbidden = false;
  AirmiusJson? createdPayload;
  final jobs = <AirmiusJson>[
    {
      'id': 3,
      'club_id': 7,
      'kind': 'escalation',
      'title': 'Failed reminder',
      'body': 'Saved failure',
      'status': 'failed',
      'attempts': 1,
      'idempotency_key': 'failed-key',
      'recipient_roles': [],
      'can_retry': true,
      'error_code': 'queue_worker_failed',
      'queued_at': '2026-10-13T10:00:00Z',
      'history': [],
    },
  ];
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (forbidden) return const AirmiusApiResponse(statusCode: 403, body: '{}');
    dynamic data;
    AirmiusJson? meta;
    if (request.path == '/api/v1/work-automation-clubs') {
      data = [
        {'id': 7, 'name': 'Test Club', 'can_create': true},
      ];
    } else if (request.path.endsWith('/work-automation-recipients')) {
      data = {'count': 1};
    } else if (request.path.endsWith('/retry')) {
      final job = jobs.first;
      job['status'] = 'queued';
      job['can_retry'] = false;
      data = job;
    } else if (request.path.endsWith('/work-automation-jobs') &&
        request.method == 'POST') {
      createdPayload = request.body;
      final job = <String, dynamic>{
        'id': 4,
        'club_id': 7,
        'kind': request.body!['kind'],
        ...request.body!['payload'],
        'status': 'queued',
        'attempts': 0,
        'idempotency_key': request.body!['idempotency_key'],
        'recipient_roles': [],
        'can_retry': false,
        'queued_at': '2026-10-13T11:00:00Z',
        'history': [],
      };
      jobs.add(job);
      data = job;
    } else if (request.path.endsWith('/work-automation-jobs')) {
      data = jobs;
      meta = {
        'can_create': true,
        'recipient_permissions': [
          {'key': 'members.manage', 'label': 'Manage members'},
        ],
        'last_page': 2,
      };
    } else {
      data = jobs.firstWhere(
        (job) => request.path.endsWith('/${job['id']}'),
        orElse: () => jobs.first,
      );
    }
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({'data': data, 'meta': ?meta}),
    );
  }
}
