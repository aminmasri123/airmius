import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_service_container.dart';

void main() {
  test(
    'club metadata client covers configuration and subject endpoints',
    () async {
      final transport = _RecordingTransport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
        token: 'token',
      );

      await client.clubMetadata(7);
      await client.saveClubMetadataCustomField(7, {'label': 'Trikotgröße'});
      await client.saveClubMetadataCustomField(7, {
        'label': 'Größe',
      }, fieldId: 2);
      await client.deleteClubMetadataCustomField(7, 2);
      await client.saveClubMetadataCategory(7, {'name': 'Jugend'});
      await client.saveClubMetadataCategory(7, {'name': 'U18'}, categoryId: 3);
      await client.deleteClubMetadataCategory(7, 3);
      await client.saveClubMetadataNumberRange(7, {'name': 'Mitglieder'});
      await client.saveClubMetadataNumberRange(7, {
        'name': 'Aktiv',
      }, numberRangeId: 4);
      await client.setClubMetadataNumberRangeDefault(7, 4, enabled: true);
      await client.setClubMetadataNumberRangeDefault(7, 4, enabled: false);
      await client.deleteClubMetadataNumberRange(7, 4);
      await client.clubMetadataSubject(7, 'member', 9);
      await client.saveClubMetadataSubject(7, 'member', 9, {
        'values': {'2': 'L'},
        'category_ids': [3],
      });

      expect(
        transport.requests.map(
          (request) => '${request.method} ${request.path}',
        ),
        [
          'GET /api/v1/clubs/7/metadata',
          'POST /api/v1/clubs/7/metadata/custom-fields',
          'PUT /api/v1/clubs/7/metadata/custom-fields/2',
          'DELETE /api/v1/clubs/7/metadata/custom-fields/2',
          'POST /api/v1/clubs/7/metadata/categories',
          'PUT /api/v1/clubs/7/metadata/categories/3',
          'DELETE /api/v1/clubs/7/metadata/categories/3',
          'POST /api/v1/clubs/7/metadata/number-ranges',
          'PUT /api/v1/clubs/7/metadata/number-ranges/4',
          'PUT /api/v1/clubs/7/metadata/number-ranges/4/default',
          'DELETE /api/v1/clubs/7/metadata/number-ranges/4/default',
          'DELETE /api/v1/clubs/7/metadata/number-ranges/4',
          'GET /api/v1/clubs/7/metadata/subjects/member/9',
          'PUT /api/v1/clubs/7/metadata/subjects/member/9',
        ],
      );
      expect(transport.requests.last.body?['category_ids'], [3]);
    },
  );

  test(
    'club metadata mutations are neither queued offline nor retried',
    () async {
      final inner = _ThrowingTransport();
      final queued = AirmiusQueuedTransport(
        inner: inner,
        timeout: const Duration(seconds: 1),
        maxRetries: 2,
      );
      final client = AirmiusApiClient(
        transport: queued,
        baseUrl: 'https://example.test',
        token: 'token',
      );

      await expectLater(
        client.saveClubMetadataCustomField(1, {'label': 'Test'}),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(inner.calls, 1);
      queued.offline = true;
      await expectLater(
        client.saveClubMetadataSubject(1, 'team', 2, {
          'values': <String, dynamic>{},
          'category_ids': <int>[],
        }),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(inner.calls, 1);
      expect(queued.queue, isEmpty);
    },
  );
}

class _RecordingTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    return const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}');
  }
}

class _ThrowingTransport implements AirmiusApiTransport {
  int calls = 0;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    calls += 1;
    throw StateError('connection lost');
  }
}
