import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_repositories.dart';

void main() {
  test(
    'prospect repository uses club-scoped list and mutation endpoints',
    () async {
      final transport = _ProspectTransport();
      final repository = AirmiusApiMembershipRepository(
        AirmiusApiClient(
          transport: transport,
          baseUrl: 'https://example.test',
          token: 'token',
        ),
      );

      final page = await repository.clubProspects(7, status: 'trial_scheduled');
      final created = await repository.createClubProspect(7, {
        'name': 'Ada Prospect',
        'status': 'trial_scheduled',
      });
      final updated = await repository.updateClubProspect(7, 31, {
        'name': 'Ada Prospect',
        'status': 'trial_completed',
        'trial_outcome': 'interested',
      });
      final archived = await repository.archiveClubProspect(7, 31);

      expect(page.items.single.name, 'Ada Prospect');
      expect(page.items.single.teamName, 'U18');
      expect(page.items.single.membershipTypeName, 'Active');
      expect(created.status, 'trial_scheduled');
      expect(updated.status, 'trial_completed');
      expect(updated.trialOutcome, 'interested');
      expect(archived.status, 'archived');

      expect(transport.requests, hasLength(4));
      expect(transport.requests[0].method, 'GET');
      expect(
        transport.requests[0].path,
        '/api/v1/clubs/7/membership-prospects',
      );
      expect(transport.requests[0].query['status'], 'trial_scheduled');
      expect(transport.requests[1].method, 'POST');
      expect(
        transport.requests[1].path,
        '/api/v1/clubs/7/membership-prospects',
      );
      expect(transport.requests[2].method, 'PUT');
      expect(
        transport.requests[2].path,
        '/api/v1/clubs/7/membership-prospects/31',
      );
      expect(
        transport.requests[3].path,
        '/api/v1/clubs/7/membership-prospects/31/archive',
      );
    },
  );
}

class _ProspectTransport implements AirmiusApiTransport {
  final List<AirmiusApiRequest> requests = [];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    final isList = request.method == 'GET';
    final status = request.path.endsWith('/archive')
        ? 'archived'
        : request.method == 'PUT'
        ? 'trial_completed'
        : 'trial_scheduled';
    final prospect = <String, Object?>{
      'id': 31,
      'club_id': 7,
      'name': 'Ada Prospect',
      'email': 'ada@example.test',
      'status': status,
      'trial_at': '2026-10-05T14:30:00Z',
      if (status == 'trial_completed') 'trial_outcome': 'interested',
      'team_id': 9,
      'team': <String, Object?>{'id': 9, 'name': 'U18'},
      'club_membership_type_id': 4,
      'membership_type': <String, Object?>{'id': 4, 'name': 'Active'},
      'created_at': '2026-09-26T10:00:00Z',
    };
    final body = isList
        ? <String, Object?>{
            'data': [prospect],
            'meta': <String, Object?>{'current_page': 1, 'last_page': 1},
          }
        : <String, Object?>{'data': prospect};
    return AirmiusApiResponse(
      statusCode: request.method == 'POST' && !request.path.endsWith('/archive')
          ? 201
          : 200,
      body: jsonEncode(body),
    );
  }
}
