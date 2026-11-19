import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/core/exercise_content.dart';
import 'package:airmius/widgets/exercise_content_editor.dart';
import 'package:airmius/widgets/exercise_content_view.dart';
import 'package:airmius/widgets/exercise_network_image.dart';

void main() {
  test('image authentication is restricted to this API and image endpoints', () {
    const base = 'https://example.test';
    expect(
      exerciseImageHeaders(
        '$base/api/v1/training/exercises/7/images/position',
        base,
        'secret',
      ),
      {'Authorization': 'Bearer secret'},
    );
    expect(
      exerciseImageHeaders(
        '/api/v1/training/items/9/images/cover',
        base,
        'secret',
      ),
      {'Authorization': 'Bearer secret'},
    );
    for (final url in [
      'https://other.test/api/v1/training/items/9/images/cover',
      'http://example.test/api/v1/training/items/9/images/cover',
      '$base/public/image.png',
      'https://name:password@example.test/api/v1/training/items/9/images/cover',
    ]) {
      expect(exerciseImageHeaders(url, base, 'secret'), isEmpty);
    }
    expect(
      exerciseImageHeaders('/api/v1/training/items/9/images/cover', base, null),
      isEmpty,
    );
  });

  testWidgets(
    'editor and library attach current session when fetching protected images',
    (tester) async {
      final tokens = AirmiusMemoryTokenStore();
      await tokens.write(
        const AirmiusSession(
          token: 'local-image-token',
          locale: 'de',
          user: AirmiusUser(
            id: 1,
            name: 'Owner',
            email: 'owner@example.test',
            role: 'player',
          ),
        ),
      );
      final services = AirmiusServiceContainer(
        environment: const AirmiusAppEnvironment(
          apiBaseUrl: 'https://example.test',
          enableOfflineQueue: false,
        ),
        transport: _Transport(),
        tokenStore: tokens,
      );
      await services.authState.restore();
      final content = <String, dynamic>{
        'original_locale': 'de',
        'title': 'Image test',
        'explanation_images': [
          {
            'id': 'position',
            'url':
                'https://example.test/api/v1/training/exercises/7/images/position',
            'texts': {},
          },
        ],
      };
      await tester.pumpWidget(
        AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: AirmiusServicesScope(
            container: services,
            child: MaterialApp(
              home: Scaffold(
                body: SingleChildScrollView(
                  child: Column(
                    children: [
                      ExerciseContentView(content: content),
                      ExerciseContentEditor(
                        draft: ExerciseContentDraft(content),
                        onChanged: () {},
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.byType(ExerciseNetworkImage), findsNWidgets(2));
      final images = tester.widgetList<Image>(find.byType(Image));
      expect(images, hasLength(2));
      for (final image in images) {
        final provider = image.image as NetworkImage;
        expect(provider.headers, {'Authorization': 'Bearer local-image-token'});
        expect(Uri.parse(provider.url).queryParameters['_viewer'], '1');
      }
      expect(tester.takeException(), isNull);
    },
  );
}

class _Transport implements AirmiusApiTransport {
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async =>
      AirmiusApiResponse(
        statusCode: 200,
        body: jsonEncode({
          'data': {
            'id': 1,
            'name': 'Owner',
            'email': 'owner@example.test',
            'role': 'player',
          },
        }),
      );
}
