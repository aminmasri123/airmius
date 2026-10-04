import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_deep_links.dart';
import 'package:airmius/navigation/airmius_deep_link_navigator.dart';
import 'package:airmius/screens/sport_matching_screen.dart';

void main() {
  test('existing interest notification opens its matching', () {
    final notification = AirmiusNotification.fromJson({
      'id': 1,
      'type': 'sport_matching.application',
      'data': {'matching_id': 42},
    });
    final target = AirmiusDeepLinkResolver().resolve(notification.actionUrl!);
    expect(target.type, AirmiusDeepLinkTargetType.sportMatching);
    expect(target.requiresAuth, isTrue);
    final screen =
        AirmiusDeepLinkNavigator.screenFor(target) as SportMatchingScreen;
    expect(screen.initialMatchingId, 42);
  });

  test('push web URL resolves to matching', () {
    final target = AirmiusDeepLinkResolver().resolve(
      'https://airmius.com/sport-matching?matching_id=42',
    );
    expect(target.type, AirmiusDeepLinkTargetType.sportMatching);
    expect(target.id, 42);
  });
}
