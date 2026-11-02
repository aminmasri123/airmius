import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';

void main() {
  test('missing sport profile explains how to continue instead of generic 422', () {
    final error = AirmiusApiException(
      statusCode: 422,
      path: '/api/v1/training/ai/plans/preview',
      body: jsonEncode({
        'message': List.filled(50, 'Fehlende Angaben').join(' '),
        'missing_profile_fields': [{'label': 'Leistungsdaten'}],
        'profile_estimate_allowed': true,
      }),
    );
    expect(error.userMessage, contains('Sportprofil'));
    expect(error.userMessage, contains('Schätzwerte erlauben'));
    expect(error.userMessage, isNot(contains('Serverfehler')));
  });
}
