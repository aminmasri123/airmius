import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_repositories.dart';

void main() {
  test('member duplicate merge sends explicit target and resolution', () async {
    final transport = _DuplicateMergeTransport();
    final repository = AirmiusApiClubRepository(
      AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
        token: 'token',
      ),
    );

    final management = await repository.mergeClubExternalMember(7, 12, 42, {
      'resolution': 'keep_registered',
      'confirm_email': 'duplicate@example.test',
    });

    expect(management.canManage, isTrue);
    expect(transport.request?.method, 'POST');
    expect(
      transport.request?.path,
      '/api/v1/clubs/7/external-members/12/merge/42',
    );
    expect(transport.request?.body, {
      'resolution': 'keep_registered',
      'confirm_email': 'duplicate@example.test',
    });
  });
}

class _DuplicateMergeTransport implements AirmiusApiTransport {
  AirmiusApiRequest? request;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    this.request = request;
    return const AirmiusApiResponse(
      statusCode: 200,
      body: '{"data":{"can_manage":true,"external_members":[]}}',
    );
  }
}
