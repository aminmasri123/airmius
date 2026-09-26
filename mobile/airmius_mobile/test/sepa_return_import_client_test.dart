import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/sepa_return_import_client.dart';

class _UnusedTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) =>
      throw StateError('JSON transport must not receive files');
}

void main() {
  final api = AirmiusApiClient(
    transport: _UnusedTransport(),
    baseUrl: 'https://example.test/api/v1/',
    token: 'test-token',
    locale: 'fr',
  );
  final bytes = Uint8List.fromList(
    utf8.encode('end_to_end_id;reason\nR-1;MS03\n'),
  );
  test(
    'preview and confirmed upload preserve file bytes, credentials and separate confirmation',
    () async {
      final requests = <http.Request>[];
      final client = SepaReturnImportClient(
        api,
        transport: MockClient((request) async {
          requests.add(request);
          return http.Response('{"data":{"imported":1}}', 200);
        }),
      );
      addTearDown(client.close);
      await client.send(
        clubId: 2,
        batchId: 7,
        bytes: bytes,
        filename: 'returns.xml',
      );
      await client.send(
        clubId: 2,
        batchId: 7,
        bytes: bytes,
        previewToken: 'proof',
        confirmUnlinked: true,
      );
      expect(
        requests.first.url.path,
        '/api/v1/clubs/2/sepa-batches/7/returns/preview',
      );
      expect(
        requests.last.url.path,
        '/api/v1/clubs/2/sepa-batches/7/returns/import',
      );
      for (final request in requests) {
        expect(request.headers['Authorization'], 'Bearer test-token');
        expect(request.headers['X-Airmius-Locale'], 'fr');
        expect(
          request.headers['content-type'],
          startsWith('multipart/form-data;'),
        );
        expect(request.body, contains(utf8.decode(bytes)));
      }
      expect(requests.first.body, isNot(contains('name="confirmed"')));
      expect(requests.first.body, contains('filename="returns.xml"'));
      expect(requests.last.body, contains('name="preview_token"\r\n\r\nproof'));
      expect(requests.last.body, contains('name="confirmed"\r\n\r\n1'));
      expect(requests.last.body, contains('name="confirm_unlinked"\r\n\r\n1'));
    },
  );
  test('ambiguous transport error causes exactly one attempt', () async {
    var attempts = 0;
    final client = SepaReturnImportClient(
      api,
      transport: MockClient((request) async {
        attempts++;
        throw http.ClientException('Connection lost');
      }),
    );
    addTearDown(client.close);
    await expectLater(
      client.send(clubId: 2, batchId: 7, bytes: bytes, previewToken: 'proof'),
      throwsA(isA<http.ClientException>()),
    );
    expect(attempts, 1);
  });
  test(
    'oversized files never reach the server and validation errors remain errors',
    () async {
      var attempts = 0;
      final client = SepaReturnImportClient(
        api,
        transport: MockClient((request) async {
          attempts++;
          return http.Response('{"message":"Review required"}', 422);
        }),
      );
      addTearDown(client.close);
      await expectLater(
        client.send(clubId: 2, batchId: 7, bytes: Uint8List(2097153)),
        throwsArgumentError,
      );
      expect(attempts, 0);
      await expectLater(
        client.send(clubId: 2, batchId: 7, bytes: bytes),
        throwsA(
          isA<AirmiusApiException>().having((e) => e.statusCode, 'status', 422),
        ),
      );
      expect(attempts, 1);
    },
  );
}
