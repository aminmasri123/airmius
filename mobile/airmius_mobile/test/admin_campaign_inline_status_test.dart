import 'dart:convert';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/admin_commerce_operations_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _Transport extends AirmiusApiTransport {
  String status = 'active';
  bool reject = false;
  final updates = <AirmiusApiRequest>[];
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.method == 'PATCH') {
      updates.add(request);
      if (reject) {
        return const AirmiusApiResponse(
          statusCode: 422,
          body: '{"message":"Status change rejected"}',
        );
      }
      status = 'paused';
    }
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': request.path.endsWith('/catalog')
            ? {
                'campaigns': [
                  {
                    'id': 8,
                    'name': 'Test campaign',
                    'status': status,
                    'user_id': 2,
                    'placement': 'feed',
                    'budget_cents': 2000,
                  },
                ],
              }
            : {},
      }),
    );
  }
}

void main() {
  for (final reject in [false, true]) {
    testWidgets('inline campaign status save, rejected=$reject', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(1000, 1600));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final transport = _Transport()..reject = reject;
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
          language: AirmiusLanguage.en,
          setLanguage: (_) {},
          child: AirmiusServicesScope(
            container: services,
            child: const MaterialApp(
              home: AdminCommerceOperationsScreen(initialSection: 'campaigns'),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('campaign-status-8-active')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Paused').last);
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Save'));
      await tester.pumpAndSettle();
      expect(
        transport.updates.single.path,
        '/api/v1/admin/commerce/campaigns/8/status',
      );
      if (reject) {
        expect(find.text('Status change rejected'), findsOneWidget);
        expect(transport.status, 'active');
      } else {
        expect(
          find.byKey(const ValueKey('campaign-status-8-paused')),
          findsOneWidget,
        );
      }
    });
  }
}
