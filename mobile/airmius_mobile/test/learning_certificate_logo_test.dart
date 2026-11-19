import 'dart:async';
import 'dart:typed_data';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/learning_certificate_logo_upload.dart';
import 'package:airmius/widgets/learning_certificate_logo_field.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

class _UnusedTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) => throw StateError('Use multipart transport');
}

void main() {
  test(
    'multipart logo upload sends authentication and preserves URL and errors',
    () async {
      final api = AirmiusApiClient(
        transport: _UnusedTransport(),
        baseUrl: 'https://example.test/api/v1',
        token: 'test-token',
        locale: 'de',
      );
      final client = MockClient((request) async {
        expect(request.url.path, '/api/v1/learning-studio/certificate-logo');
        expect(request.headers['Authorization'], 'Bearer test-token');
        expect(
          request.headers['Content-Type'],
          contains('multipart/form-data'),
        );
        expect(request.body, contains('name="file"; filename="logo.png"'));
        expect(request.body, contains('image/png'));
        return http.Response(
          '{"url":"https://example.test/storage/logo.png"}',
          201,
        );
      });
      expect(
        await uploadLearningCertificateLogo(
          api: api,
          bytes: Uint8List.fromList([1, 2, 3]),
          filename: 'logo.png',
          transport: client,
        ),
        'https://example.test/storage/logo.png',
      );
      await expectLater(
        uploadLearningCertificateLogo(
          api: api,
          bytes: Uint8List.fromList([1]),
          filename: 'logo.png',
          transport: MockClient(
            (_) async => http.Response('{"message":"Invalid image"}', 422),
          ),
        ),
        throwsA(isA<AirmiusApiException>()),
      );
    },
  );

  for (final language in AirmiusLanguage.values) {
    testWidgets(
      'logo picker supports upload and keeps the URL alternative in ${language.name}',
      (tester) async {
        final controller = TextEditingController(
          text: 'https://example.test/old.png',
        );
        addTearDown(controller.dispose);
        final result = Completer<String>();
        final busy = <bool>[];
        await tester.pumpWidget(
          AirmiusScope(
            language: language,
            setLanguage: (_) {},
            child: MaterialApp(
              home: Scaffold(
                body: LearningCertificateLogoField(
                  controller: controller,
                  upload: (file) {
                    expect(file.name, 'logo.png');
                    return result.future;
                  },
                  onBusy: busy.add,
                  pickImage: () async => PlatformFile(
                    name: 'logo.png',
                    size: 3,
                    bytes: Uint8List.fromList([1, 2, 3]),
                  ),
                ),
              ),
            ),
          ),
        );
        await tester.tap(find.byKey(const ValueKey('certificate-logo-upload')));
        await tester.pump();
        expect(busy, [true]);
        expect(controller.text, 'https://example.test/old.png');
        expect(find.byType(LinearProgressIndicator), findsOneWidget);
        result.complete('https://example.test/new.png');
        await tester.pumpAndSettle();
        expect(controller.text, 'https://example.test/new.png');
        expect(busy, [true, false]);
        expect(find.text('logo.png'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'invalid file never uploads and failed upload retains the old URL',
    (tester) async {
      final controller = TextEditingController(
        text: 'https://example.test/old.png',
      );
      addTearDown(controller.dispose);
      var invalid = true;
      var calls = 0;
      await tester.pumpWidget(
        AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: MaterialApp(
            home: Scaffold(
              body: LearningCertificateLogoField(
                controller: controller,
                onBusy: (_) {},
                pickImage: () async => PlatformFile(
                  name: invalid ? 'document.pdf' : 'logo.jpg',
                  size: 1,
                  bytes: Uint8List.fromList([1]),
                ),
                upload: (_) async {
                  calls++;
                  throw StateError('test failure');
                },
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.byKey(const ValueKey('certificate-logo-upload')));
      await tester.pumpAndSettle();
      expect(calls, 0);
      invalid = false;
      await tester.tap(find.byKey(const ValueKey('certificate-logo-upload')));
      await tester.pumpAndSettle();
      expect(calls, 1);
      expect(controller.text, 'https://example.test/old.png');
      expect(tester.takeException(), isNull);
    },
  );
}
