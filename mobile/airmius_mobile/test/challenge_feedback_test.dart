import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/challenge_feedback.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('challenge feedback never exposes technical response bodies', () {
    for (final language in AirmiusLanguage.values) {
      for (final status in [401, 403, 404, 422, 429, 500, 599]) {
        final message = challengeErrorMessage(
          AirmiusApiException(
            statusCode: status,
            body: 'SQLSTATE secret',
            path: '/private',
          ),
          language,
        );
        expect(message, isNotEmpty);
        expect(message, isNot(contains('SQLSTATE')));
        expect(message, isNot(contains('/private')));
      }
    }
  });
  test('retry is translated and sign-in failures explain recovery', () {
    expect(challengeRetryLabel(AirmiusLanguage.en), 'Try again');
    expect(challengeRetryLabel(AirmiusLanguage.fr), 'Réessayer');
    expect(challengeRetryLabel(AirmiusLanguage.ar), 'حاول مجددًا');
    expect(
      challengeErrorMessage(
        const AirmiusApiException(statusCode: 401, body: '', path: ''),
        AirmiusLanguage.de,
      ),
      contains('erneut an'),
    );
  });
}
