import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/api_contract.dart';

void main() {
  test('normalizes legacy Laravel display paths to the mobile API', () {
    expect(
      AirmiusApiContract.mobileApiPath('/friends/auth/login'),
      '/api/v1/auth/login',
    );
    expect(
      AirmiusApiContract.mobileApiPath('/friends/chat/messages/42'),
      '/api/v1/chat/messages/42',
    );
  });

  test('keeps canonical and non-legacy paths unchanged', () {
    expect(AirmiusApiContract.mobileApiPath('/api/v1/meta'), '/api/v1/meta');
    expect(
      AirmiusApiContract.mobileApiPath('/shared-files/token'),
      '/shared-files/token',
    );
    expect(AirmiusApiContract.mobileApiPath('  /friends  '), '/api/v1');
  });
}
