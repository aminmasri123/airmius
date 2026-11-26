import 'package:airmius/navigation/airmius_deep_link_navigator.dart';
import 'package:airmius/screens/profile_screen.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('own profile deep link opens profile without a routing screen', () {
    final target = AirmiusDeepLinkNavigator.resolve('airmius://profile');

    expect(AirmiusDeepLinkNavigator.screenFor(target), isA<ProfileScreen>());
  });
}
