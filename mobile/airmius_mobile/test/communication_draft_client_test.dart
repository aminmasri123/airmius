import 'package:airmius/core/airmius_api_client.dart';
import 'package:flutter_test/flutter_test.dart';

class _DraftTransport implements AirmiusApiTransport {
  AirmiusApiRequest? request;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    this.request = request;
    return const AirmiusApiResponse(
      statusCode: 200,
      body:
          '{"data":{"subject":"Training fällt aus","body":"Hallo zusammen","editable":true,"draft_only":true,"auto_execute":false}}',
    );
  }
}

void main() {
  test('communication AI creates a draft without a send operation', () async {
    final transport = _DraftTransport();
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://example.test',
      token: 'test-token',
    );

    final response = await client.generateCommunicationDraft({
      'type': 'message',
      'audience': 'Alle aktiven Mitglieder',
      'purpose': 'Training fällt aus',
      'source_context': [
        {
          'label': 'Briefing',
          'reference': 'user-provided-announcement-brief',
          'excerpt': 'Training fällt aus',
        },
      ],
    });

    expect(transport.request?.method, 'POST');
    expect(transport.request?.path, '/api/v1/ai/communication-drafts');
    expect(transport.request?.body?['purpose'], 'Training fällt aus');
    expect(response['data']['editable'], isTrue);
    expect(response['data']['draft_only'], isTrue);
    expect(response['data']['auto_execute'], isFalse);
  });
}
