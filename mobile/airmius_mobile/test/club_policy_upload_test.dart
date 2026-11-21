import 'dart:typed_data';
import 'package:file_picker/file_picker.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/club_policy_upload_client.dart';

void main() {
  test(
    'policy multipart upload includes club, document type, credentials and file',
    () async {
      final transport = MockClient((request) async {
        expect(request.url.path, '/api/v1/uploads');
        expect(request.headers['Authorization'], 'Bearer token');
        expect(
          request.body,
          contains('name="policy_document_type"\r\n\r\nregulation'),
        );
        expect(request.body, contains('name="club_id"\r\n\r\n7'));
        expect(request.body, contains('filename="order.pdf"'));
        return http.Response('{"data":{"id":17,"folder_id":5}}', 201);
      });
      final result = await uploadClubPolicyFile(
        AirmiusApiClient(
          transport: _UnusedTransport(),
          baseUrl: 'https://example.test/api/v1',
          token: 'token',
        ),
        7,
        'regulation',
        PlatformFile(
          name: 'order.pdf',
          size: 4,
          bytes: Uint8List.fromList([37, 80, 68, 70]),
        ),
        transport: transport,
      );
      expect(result['id'], 17);
      expect(result['folder_id'], 5);
      transport.close();
    },
  );
}

class _UnusedTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async =>
      throw StateError('Unexpected JSON request');
}
