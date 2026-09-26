import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_repositories.dart';

void main() {
  test('saved view repository uses the personal API contract', () async {
    final transport = _SavedViewTransport();
    final repository = AirmiusApiSearchRepository(
      AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
        token: 'token',
      ),
    );

    final views = await repository.savedViews(workspace: 'events');
    expect(views.single.name, 'Meine Termine');
    expect(views.single.configuration['query'], 'Training');

    final created = await repository.createSavedView(
      workspace: 'files',
      name: 'PDF-Dokumente',
      configuration: {
        'query': '.pdf',
        'filters': {'scope': 'club', 'club_id': 7},
      },
    );
    expect(created.id, 12);
    expect(transport.requests[1].body?['workspace'], 'files');
    expect(
      (transport.requests[1].body?['configuration'] as Map)['filters'],
      {'scope': 'club', 'club_id': 7},
    );

    await repository.deleteSavedView(created.id);
    expect(
      transport.requests.map((request) => '${request.method} ${request.path}'),
      [
        'GET /api/v1/saved-views',
        'POST /api/v1/saved-views',
        'DELETE /api/v1/saved-views/12',
      ],
    );
    expect(transport.requests.first.query, {'workspace': 'events'});
  });
}

class _SavedViewTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (request.method == 'GET') {
      return const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":11,"workspace":"events","name":"Meine Termine","configuration":{"query":"Training","filters":{"period":"upcoming"}},"is_favorite":true}]}',
      );
    }
    if (request.method == 'POST') {
      return const AirmiusApiResponse(
        statusCode: 201,
        body:
            '{"data":{"id":12,"workspace":"files","name":"PDF-Dokumente","configuration":{"query":".pdf","filters":{"scope":"club","club_id":7}},"is_favorite":true}}',
      );
    }
    return const AirmiusApiResponse(statusCode: 204, body: '{}');
  }
}
