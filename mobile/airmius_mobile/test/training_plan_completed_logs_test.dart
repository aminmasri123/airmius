import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/training_plans_logs_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'completed plan logs keep plan and status filters in the API request',
    () async {
      final transport = _RecordingTransport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
        token: 'test-token',
      );

      await client.trainingLogs(
        page: 2,
        perPage: 50,
        trainingPlanId: 71,
        status: 'completed',
      );

      expect(transport.requests.single.path, '/api/v1/training/logs');
      expect(transport.requests.single.query, {
        'page': '2',
        'per_page': '50',
        'training_plan_id': '71',
        'status': 'completed',
      });
    },
  );

  testWidgets('plan actions open the completed athlete training list', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final transport = _RecordingTransport(planDetail: true);
    final services = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://example.test',
        enableOfflineQueue: false,
      ),
      transport: transport,
    );

    await tester.pumpWidget(
      AirmiusServicesScope(
        container: services,
        child: AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: const MaterialApp(
            home: TrainingPlanApiDetailScreen(planId: 71),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Aktionen'));
    await tester.pumpAndSettle();
    expect(find.text('Abgeschlossene Trainings ansehen'), findsOneWidget);

    await tester.tap(find.text('Abgeschlossene Trainings ansehen'));
    await tester.pumpAndSettle();
    expect(find.text('Abgeschlossene Trainings ansehen'), findsOneWidget);
    expect(
      transport.requests.map((request) => request.path),
      contains('/api/v1/training/logs'),
    );
    expect(find.textContaining('Mira Runner'), findsOneWidget);
    expect(find.text('Abgeschlossene Intervalle'), findsOneWidget);
    expect(
      transport.requests.last.query,
      containsPair('training_plan_id', '71'),
    );
    expect(transport.requests.last.query, containsPair('status', 'completed'));
    expect(tester.takeException(), isNull);
  });
}

class _RecordingTransport implements AirmiusApiTransport {
  _RecordingTransport({this.planDetail = false});

  final bool planDetail;
  final requests = <AirmiusApiRequest>[];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest value) async {
    requests.add(value);
    if (planDetail && value.path == '/api/v1/training/plans/71') {
      return const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":71,"title":"T37-10 Wochenplan","cadence":"weekly","status":"published","share_permission":"read","target_type":"team","is_template":false,"can_write":true,"can_delete":true,"can_manage_audience":true,"assignments_count":1,"assignments":[{"user":{"id":22,"name":"Mira Runner"}}],"items":[]}}',
      );
    }
    if (planDetail && value.path == '/api/v1/training/logs') {
      return const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":91,"user_id":22,"created_by":22,"training_plan_id":71,"title":"Abgeschlossene Intervalle","status":"completed","performed_at":"2026-11-27T10:00:00Z","duration_minutes":48,"distance_meters":8400,"metrics":{"privacy_scope":"trainer","wellness":{"rpe":7}},"athlete":{"id":22,"name":"Mira Runner"},"entries":[],"feedbacks":[]}],"meta":{"current_page":1,"last_page":1}}',
      );
    }
    return const AirmiusApiResponse(
      statusCode: 200,
      body: '{"data":[],"meta":{"current_page":1,"last_page":1}}',
    );
  }
}
