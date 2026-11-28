import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/club_membership_management_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _Transport implements AirmiusApiTransport {
  int clubReads = 0;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.path.endsWith('/sepa-batches')) {
      return const AirmiusApiResponse(
        statusCode: 200,
        body: '{"data":{"data":[]}}',
      );
    }
    const club = {'id': 4, 'name': 'SEPA Club', 'can_manage': true};
    if (request.path == '/api/v1/clubs/4') clubReads++;
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': request.path == '/api/v1/clubs'
            ? [club]
            : {
                ...club,
                'management': {'can_manage': true},
              },
      }),
    );
  }
}

void main() {
  testWidgets(
    'returning from SEPA batches refreshes membership financial data',
    (tester) async {
      tester.view.physicalSize = const Size(390, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final transport = _Transport();
      final services = AirmiusServiceContainer(
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
            container: services,
            child: const MaterialApp(
              home: ClubMembershipManagementScreen(
                initialClubId: 4,
                initialSection: 'export',
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      final readsBefore = transport.clubReads;
      final open = find.text('SEPA-Sammellauf').last;
      await tester.ensureVisible(open);
      await tester.pumpAndSettle();
      await tester.tap(open);
      await tester.pumpAndSettle();
      expect(find.text('Lastschriftläufe'), findsOneWidget);
      await tester.pageBack();
      await tester.pumpAndSettle();
      expect(transport.clubReads, readsBefore + 1);
      expect(tester.takeException(), isNull);
    },
  );
}
