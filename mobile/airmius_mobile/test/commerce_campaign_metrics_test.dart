import 'dart:convert';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/commerce_center_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _MetricsTransport implements AirmiusApiTransport {
  _MetricsTransport(this.impressions, this.clicks);
  int? impressions;
  int? clicks;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async =>
      AirmiusApiResponse(
        statusCode: 200,
        body: jsonEncode({
          'data': {
            'campaigns': [
              {
                'id': 12,
                'name': 'CTR Test',
                'status': 'active',
                'budget_cents': 5000,
                'spent_cents': 1200,
                if (impressions != null) 'impressions': impressions,
                if (clicks != null) 'clicks': clicks,
              },
            ],
          },
        }),
      );
}

Future<void> _pump(
  WidgetTester tester,
  _MetricsTransport transport,
  AirmiusLanguage language,
) async {
  tester.view.physicalSize = const Size(390, 1200);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final services = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://example.test',
      enableOfflineQueue: false,
    ),
    transport: transport,
    tokenStore: AirmiusMemoryTokenStore(),
  );
  addTearDown(services.authState.dispose);
  await tester.pumpWidget(
    AirmiusScope(
      language: language,
      setLanguage: (_) {},
      child: AirmiusServicesScope(
        container: services,
        child: MaterialApp(
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(
              context,
            ).copyWith(textScaler: const TextScaler.linear(1.3)),
            child: child!,
          ),
          home: const CommerceCenterScreen(initialSection: 'ads'),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  for (final sample in [
    (views: 100, clicks: 5, expected: r'5,00\s*%'),
    (views: 3, clicks: 1, expected: r'33,33\s*%'),
    (views: 0, clicks: 5, expected: r'0,00\s*%'),
    (views: null, clicks: null, expected: r'0,00\s*%'),
  ]) {
    testWidgets(
      'campaign CTR displays ${sample.expected} for ${sample.clicks}/${sample.views}',
      (tester) async {
        await _pump(
          tester,
          _MetricsTransport(sample.views, sample.clicks),
          AirmiusLanguage.de,
        );
        final metric = find.textContaining(
          RegExp('Klickrate \\(CTR\\): ${sample.expected}'),
        );
        await tester.scrollUntilVisible(
          metric,
          250,
          scrollable: find.byType(Scrollable).first,
        );
        expect(metric, findsOneWidget);
        expect(find.byTooltip('Klicks ÷ Ansichten × 100'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets('campaign CTR uses locale formatting and updates after refresh', (
    tester,
  ) async {
    final transport = _MetricsTransport(100, 5);
    await _pump(tester, transport, AirmiusLanguage.en);
    expect(
      find.textContaining(RegExp(r'Click rate \(CTR\): 5\.00\s*%')),
      findsOneWidget,
    );
    transport.impressions = 200;
    transport.clicks = 20;
    await tester.tap(find.byTooltip('Refresh'));
    await tester.pumpAndSettle();
    expect(
      find.textContaining(RegExp(r'Click rate \(CTR\): 10\.00\s*%')),
      findsOneWidget,
    );
    expect(
      find.textContaining(RegExp(r'Click rate \(CTR\): 5\.00\s*%')),
      findsNothing,
    );
    expect(tester.takeException(), isNull);
  });
}
