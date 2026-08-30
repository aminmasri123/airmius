import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_deep_links.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('challenge API client uses the shared v1 challenge contract', () async {
    final transport = _RecordingTransport();
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://example.test',
      token: 'token',
    );

    await client.challenges(status: 'active');
    await client.challenge(12);
    await client.createChallenge({'title': '10.000 Schritte'});
    await client.joinChallenge(12);
    await client.respondToChallenge(12, 'accepted');
    await client.checkInChallenge(
      12,
      '2026-08-30',
      value: 10500,
      note: 'Geschafft',
    );
    await client.challengeComments(12);
    await client.addChallengeComment(12, 'Weiter so!');

    expect(
      transport.requests.map((request) => '${request.method} ${request.path}'),
      containsAllInOrder([
        'GET /api/v1/challenges',
        'GET /api/v1/challenges/12',
        'POST /api/v1/challenges',
        'POST /api/v1/challenges/12/join',
        'PUT /api/v1/challenges/12/invitation',
        'PUT /api/v1/challenges/12/check-ins/2026-08-30',
        'GET /api/v1/challenges/12/comments',
        'POST /api/v1/challenges/12/comments',
      ]),
    );
    expect(transport.requests.first.query['status'], 'active');
    expect(transport.requests[5].body?['value'], 10500);
  });

  test('challenge notification deep links resolve to a challenge detail', () {
    const resolver = AirmiusDeepLinkResolver();
    final target = resolver.resolve('airmius://challenges/42');

    expect(target.type, AirmiusDeepLinkTargetType.challenge);
    expect(target.id, 42);
    expect(target.requiresAuth, isTrue);
  });
}

class _RecordingTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    return const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}');
  }
}
