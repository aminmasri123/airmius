import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_repositories.dart';

void main() {
  test('member timeline creates and deletes entries through the API', () async {
    final transport = _MemberTimelineTransport();
    final repository = AirmiusApiClubRepository(
      AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
        token: 'token',
      ),
    );
    final payload = <String, Object?>{
      'subject_type': 'member',
      'subject_id': 42,
      'type': 'honor',
      'title': 'Gold honor pin',
      'occurred_on': '2026-09-20',
    };

    final entry = await repository.createClubMemberTimelineEntry(7, payload);
    await repository.deleteClubMemberTimelineEntry(7, 91);

    expect(entry['id'], 91);
    expect(transport.requests, hasLength(2));
    expect(transport.requests.first.method, 'POST');
    expect(transport.requests.first.path, '/api/v1/clubs/7/member-timeline');
    expect(transport.requests.first.body, payload);
    expect(transport.requests.last.method, 'DELETE');
    expect(transport.requests.last.path, '/api/v1/clubs/7/member-timeline/91');
  });
}

class _MemberTimelineTransport implements AirmiusApiTransport {
  final List<AirmiusApiRequest> requests = [];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    return AirmiusApiResponse(
      statusCode: request.method == 'DELETE' ? 204 : 201,
      body: request.method == 'DELETE'
          ? ''
          : '{"data":{"id":91,"type":"honor"}}',
    );
  }
}
