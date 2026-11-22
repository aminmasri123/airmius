import 'package:airmius/core/airmius_api_client.dart';
import 'package:flutter_test/flutter_test.dart';

class _Transport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    return const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}');
  }
}

void main() {
  test('service hours use club-scoped endpoints and period query', () async {
    final transport = _Transport();
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://example.test',
      token: 'test',
    );

    await client.clubServiceHourPeriods(26);
    await client.clubServiceHours(26, 12);
    await client.writeClubServiceHours(26, 'service-hour-records', {
      'period_id': 12,
      'user_id': 7,
      'minutes': 90,
    });

    expect(
      transport.requests[0].path,
      '/api/v1/clubs/26/service-hours/periods',
    );
    expect(transport.requests[1].query['period_id'], '12');
    expect(transport.requests[2].method, 'POST');
    expect(transport.requests[2].body?['minutes'], 90);
    expect(
      () => client.writeClubServiceHours(
        26,
        '../clubs/1/service-hour-records',
        {},
      ),
      throwsArgumentError,
    );
  });
}
