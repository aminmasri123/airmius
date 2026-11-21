import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/widgets/airmius_form.dart';

void main() {
  for (final language in AirmiusLanguage.values) {
    testWidgets(
      'invalid submit explains and reveals field in ${language.name}',
      (tester) async {
        final key = GlobalKey<FormState>();
        var saved = 0;
        await tester.pumpWidget(
          AirmiusScope(
            language: language,
            setLanguage: (_) {},
            child: MaterialApp(
              home: Scaffold(
                body: AirmiusForm(
                  key: key,
                  child: SingleChildScrollView(
                    child: Column(
                      children: [
                        const SizedBox(height: 900),
                        TextFormField(
                          key: const ValueKey('required'),
                          validator: (v) =>
                              v?.isNotEmpty == true ? null : 'Required',
                        ),
                      ],
                    ),
                  ),
                ),
                bottomNavigationBar: FilledButton(
                  onPressed: () {
                    if (key.currentState!.validate()) saved++;
                  },
                  child: const Text('Save'),
                ),
              ),
            ),
          ),
        );
        await tester.tap(find.text('Save'));
        await tester.pumpAndSettle();
        expect(saved, 0);
        expect(find.byType(SnackBar), findsOneWidget);
        expect(
          tester.getRect(find.byKey(const ValueKey('required'))).top,
          lessThan(550),
        );
        await tester.enterText(find.byType(TextFormField), 'Valid');
        await tester.tap(find.text('Save'));
        await tester.pumpAndSettle();
        expect(saved, 1);
        expect(tester.takeException(), isNull);
      },
    );
  }

  test('server errors map nested field keys and avoid technical output', () {
    const error = AirmiusApiException(
      statusCode: 422,
      path: '/api/v1/example',
      body:
          '{"message":"Invalid","errors":{"name":["Name already exists"],"options.0":["Duplicate option"]},"meta":{"request_id":"private"}}',
    );
    expect(formServerErrors(error), {
      'name': 'Name already exists',
      'options.0': 'Duplicate option',
    });
    expect(formServerErrors(StateError('offline')), isEmpty);
    expect(
      formServerErrors(
        const AirmiusApiException(
          statusCode: 500,
          path: '/api',
          body: '<html>error</html>',
        ),
      ),
      isEmpty,
    );
  });
}
