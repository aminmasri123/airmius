import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:flutter_test/flutter_test.dart';

class _RecordingTransport implements AirmiusApiTransport {
  _RecordingTransport(this.response);

  final AirmiusApiResponse response;
  final requests = <AirmiusApiRequest>[];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    return response;
  }
}

void main() {
  test(
    'guardian client uses protected multi-guardian endpoints safely',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'guardian-token',
      );

      await client.guardianChildren();
      await client.guardianInvitations(page: 2, perPage: 10);
      await client.acceptGuardianInvitation(77, requestId: 'request-77');
      await client.declineGuardianInvitation(78, requestId: 'request-78');

      expect(transport.requests.map((request) => request.path), [
        '/api/v1/guardian/children',
        '/api/v1/guardian/invitations',
        '/api/v1/guardian/invitations/77/accept',
        '/api/v1/guardian/invitations/78/decline',
      ]);
      expect(transport.requests.map((request) => request.method), [
        'GET',
        'GET',
        'POST',
        'POST',
      ]);
      expect(transport.requests[0].query, {'page': '1', 'per_page': '20'});
      expect(transport.requests[1].query, {'page': '2', 'per_page': '10'});
      expect(transport.requests[2].headers['Idempotency-Key'], 'request-77');
      expect(transport.requests[2].body?['request_id'], 'request-77');
    },
  );

  test(
    'guardian invitation page maps relationship contract without secrets',
    () {
      final page = AirmiusGuardianInvitationPage.fromJson({
        'data': {
          'summary': {'total': 2, 'open': 1, 'accepted': 1, 'needs_review': 0},
          'invitations': [
            {
              'id': 77,
              'club': {'id': 5, 'name': 'Airmius Juniors'},
              'child': {
                'id': 12,
                'name': 'Junior Example',
                'email': 'junior@example.test',
                'birth_date': '2013-08-01',
                'age': 13,
              },
              'guardian_email': 'parent@example.test',
              'relationship_type': 'guardian',
              'status': 'invited',
              'status_label': 'Einladung offen',
              'is_primary': false,
              'can_accept': true,
              'can_decline': true,
              'guardian_consent_token': 'must-not-be-consumed',
            },
          ],
        },
        'current_page': 1,
        'last_page': 3,
        'total': 21,
      });

      expect(page.summary.open, 1);
      expect(page.pagination.lastPage, 3);
      expect(page.invitations.single.id, 77);
      expect(page.invitations.single.club?.name, 'Airmius Juniors');
      expect(page.invitations.single.child?.age, 13);
      expect(page.invitations.single.canAccept, isTrue);
    },
  );
}
