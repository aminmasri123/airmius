import 'package:airmius/airmius_app.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('production social callbacks require HTTPS', () {
    expect(
      isAirmiusSocialLoginCallback(
        Uri.parse('https://airmius.com/auth/callback?token=secure'),
        web: false,
      ),
      isTrue,
    );
    expect(
      isAirmiusSocialLoginCallback(
        Uri.parse('http://airmius.com/auth/callback?token=exposed'),
        web: false,
      ),
      isFalse,
    );
  });

  test('local web callback remains available for development', () {
    expect(
      isAirmiusSocialLoginCallback(
        Uri.parse('http://127.0.0.1/auth/callback?token=dev'),
        web: true,
      ),
      isTrue,
    );
    expect(
      isAirmiusSocialLoginCallback(
        Uri.parse('http://127.0.0.1/auth/callback'),
        web: true,
      ),
      isFalse,
    );
    expect(
      isAirmiusSocialLoginCallback(
        Uri.parse('http://localhost/auth/callback#token=fragment-dev'),
        web: true,
      ),
      isTrue,
    );
  });
}
