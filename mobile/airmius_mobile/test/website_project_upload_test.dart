import 'dart:convert';
import 'dart:typed_data';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_deep_links.dart';
import 'package:airmius/core/website_project_upload.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  test(
    'project notifications resolve to the specific project in app and web',
    () {
      const resolver = AirmiusDeepLinkResolver();
      for (final link in [
        'airmius://website-projects/19',
        'https://airmius.com/commerce/website-projects/19',
      ]) {
        final target = resolver.resolve(link);
        expect(target.type, AirmiusDeepLinkTargetType.websiteProject);
        expect(target.id, 19);
        expect(target.requiresAuth, isTrue);
      }
      expect(
        resolver.resolve('airmius://website-projects/nope').type,
        AirmiusDeepLinkTargetType.unknown,
      );
    },
  );

  test(
    'uploads multiple real multipart files with the same retry key and auth',
    () async {
      var calls = 0;
      final transport = MockClient((request) async {
        calls++;
        expect(
          request.url.path,
          '/api/v1/commerce/website-requests/19/messages',
        );
        expect(request.headers['Authorization'], 'Bearer token');
        expect(
          request.headers['content-type'],
          contains('multipart/form-data'),
        );
        final text = utf8.decode(request.bodyBytes, allowMalformed: true);
        expect(text, contains('name="request_key"'));
        expect(text, contains('retry-upload'));
        expect(text, contains('filename="plan.pdf"'));
        expect(text, contains('filename="design.png"'));
        expect(text, contains('name="files[]"'));
        return http.Response('{"ok":true}', 200);
      });
      final api = AirmiusApiClient(
        transport: _UnusedTransport(),
        baseUrl: 'https://example.test/api/v1',
        token: 'token',
      );
      final files = [
        PlatformFile(
          name: 'plan.pdf',
          size: 4,
          bytes: Uint8List.fromList([1, 2, 3, 4]),
        ),
        PlatformFile(
          name: 'design.png',
          size: 2,
          bytes: Uint8List.fromList([5, 6]),
        ),
      ];
      await sendWebsiteProjectFiles(
        api,
        19,
        'Plan',
        'retry-upload',
        files,
        transport: transport,
      );
      await sendWebsiteProjectFiles(
        api,
        19,
        'Plan',
        'retry-upload',
        files,
        transport: transport,
      );
      expect(calls, 2);
    },
  );

  test(
    'server errors are surfaced for retry without pretending success',
    () async {
      final api = AirmiusApiClient(
        transport: _UnusedTransport(),
        baseUrl: 'https://example.test',
        token: 'token',
      );
      final transport = MockClient(
        (_) async => http.Response('{"message":"Upload rejected"}', 422),
      );
      await expectLater(
        sendWebsiteProjectFiles(api, 19, '', 'retry', [], transport: transport),
        throwsA(isA<AirmiusApiException>()),
      );
    },
  );
}

class _UnusedTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) =>
      throw UnimplementedError();
}
