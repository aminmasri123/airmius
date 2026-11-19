import 'dart:async';
import 'dart:typed_data';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/learning_lesson_asset_upload.dart';
import 'package:airmius/widgets/learning_lesson_upload_field.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

class _UnusedTransport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) =>
      throw StateError('Multipart only');
}

void main() {
  test(
    'video uploads stream bytes with progress, correct purpose and authentication',
    () async {
      final progress = <double>[];
      final api = AirmiusApiClient(
        transport: _UnusedTransport(),
        baseUrl: 'https://example.test/api/v1',
        token: 'test-token',
        locale: 'fr',
      );
      final result = await uploadLearningLessonAsset(
        api: api,
        courseId: 4,
        file: PlatformFile(
          name: 'lesson.mp4',
          size: 3,
          readStream: Stream.fromIterable([
            [1, 2],
            [3],
          ]),
        ),
        purpose: 'lesson_video',
        onProgress: progress.add,
        transport: MockClient((request) async {
          expect(request.url.path, '/api/v1/learning-studio/courses/4/uploads');
          expect(request.headers['Authorization'], 'Bearer test-token');
          expect(request.headers['X-Airmius-Locale'], 'fr');
          expect(request.body, contains('lesson_video'));
          expect(request.body, contains('filename="lesson.mp4"'));
          expect(request.body, contains('video/mp4'));
          return http.Response(
            '{"url":"https://example.test/lesson.mp4"}',
            200,
          );
        }),
      );
      expect(result['url'], endsWith('lesson.mp4'));
      expect(progress.last, 1);
      await expectLater(
        uploadLearningLessonAsset(
          api: api,
          courseId: 4,
          file: PlatformFile(name: 'bad.php', size: 3),
          purpose: 'lesson_attachment',
        ),
        throwsArgumentError,
      );
      await expectLater(
        uploadLearningLessonAsset(
          api: api,
          courseId: 4,
          file: PlatformFile(
            name: 'guide.pdf',
            size: 1,
            bytes: Uint8List.fromList([1]),
          ),
          purpose: 'lesson_attachment',
          transport: MockClient(
            (_) async => http.Response('{"message":"Denied"}', 403),
          ),
        ),
        throwsA(isA<AirmiusApiException>()),
      );
    },
  );

  for (final language in AirmiusLanguage.values) {
    testWidgets(
      'multiple files preserve existing links and show uploading state in ${language.name}',
      (tester) async {
        tester.view.physicalSize = const Size(390, 844);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final controller = TextEditingController(
          text: 'https://example.test/existing.pdf',
        );
        addTearDown(controller.dispose);
        final first = Completer<AirmiusJson>();
        final busy = <bool>[];
        final names = <String>[];
        await tester.pumpWidget(
          AirmiusScope(
            language: language,
            setLanguage: (_) {},
            child: MaterialApp(
              home: Scaffold(
                body: SingleChildScrollView(
                  child: LearningLessonUploadField(
                    controller: controller,
                    video: false,
                    onBusy: busy.add,
                    pickFiles: () async => [
                      PlatformFile(name: 'image.png', size: 3),
                      PlatformFile(name: 'guide.pdf', size: 3),
                    ],
                    upload: (file, purpose, progress) {
                      names.add(file.name);
                      expect(purpose, 'lesson_attachment');
                      progress(.5);
                      return names.length == 1
                          ? first.future
                          : Future.value({
                              'url': 'https://example.test/guide.pdf',
                            });
                    },
                  ),
                ),
              ),
            ),
          ),
        );
        await tester.tap(find.byKey(const ValueKey('lesson-files-upload')));
        await tester.pump();
        expect(busy, [true]);
        expect(find.byType(LinearProgressIndicator), findsOneWidget);
        expect(controller.text, 'https://example.test/existing.pdf');
        first.complete({'url': 'https://example.test/image.png'});
        await tester.pumpAndSettle();
        expect(names, ['image.png', 'guide.pdf']);
        expect(busy, [true, false]);
        expect(controller.text.split('\n'), [
          'https://example.test/existing.pdf',
          'https://example.test/image.png',
          'https://example.test/guide.pdf',
        ]);
        expect(find.text('guide.pdf'), findsOneWidget);
        await tester.tap(find.byIcon(Icons.close).first);
        await tester.pumpAndSettle();
        expect(
          controller.text,
          'https://example.test/existing.pdf\nhttps://example.test/guide.pdf',
        );
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'video upload replaces only after success; cancellation and errors keep the old link',
    (tester) async {
      final controller = TextEditingController(
        text: 'https://example.test/old.mp4',
      );
      addTearDown(controller.dispose);
      var selection = 0, calls = 0;
      await tester.pumpWidget(
        AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: MaterialApp(
            home: Scaffold(
              body: LearningLessonUploadField(
                controller: controller,
                video: true,
                onBusy: (_) {},
                pickFiles: () async => selection++ == 0
                    ? null
                    : [PlatformFile(name: 'video.mp4', size: 3)],
                upload: (file, purpose, progress) async {
                  expect(purpose, 'lesson_video');
                  if (calls++ == 0) {
                    return {'url': 'https://example.test/new.mp4'};
                  }
                  throw StateError('Network');
                },
              ),
            ),
          ),
        ),
      );
      final button = find.byKey(const ValueKey('lesson-video-upload'));
      await tester.tap(button);
      await tester.pumpAndSettle();
      expect(controller.text, 'https://example.test/old.mp4');
      expect(calls, 0);
      await tester.tap(button);
      await tester.pumpAndSettle();
      expect(controller.text, 'https://example.test/new.mp4');
      await tester.tap(button);
      await tester.pumpAndSettle();
      expect(controller.text, 'https://example.test/new.mp4');
      expect(tester.takeException(), isNull);
    },
  );
}
