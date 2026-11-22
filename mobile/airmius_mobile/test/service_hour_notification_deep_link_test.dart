import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_deep_links.dart';
import 'package:airmius/navigation/airmius_deep_link_navigator.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('manager service hour response opens the exact ledger entry', () {
    final notification = AirmiusNotification.fromJson({
      'id': 41,
      'type': 'club.service_hour.member_accepted',
      'title': 'Dienststunde bestätigt',
      'body': 'Das Mitglied hat die Dienststunde bestätigt.',
      'data': {
        'club_id': 7,
        'period_id': 12,
        'service_hour_record_id': 33,
        'mobile_url': 'airmius://notifications',
      },
    });

    expect(
      notification.actionUrl,
      'airmius://clubs/7/service-hours?period=12&record=33',
    );
    final target = const AirmiusDeepLinkResolver().resolve(
      notification.actionUrl!,
    );
    expect(target.type, AirmiusDeepLinkTargetType.club);
    expect(target.id, 7);
    expect(target.section, 'service-hours');
    expect(target.query['period'], '12');
    expect(target.query['record'], '33');
    expect(target.requiresAuth, isTrue);

    final screen = AirmiusDeepLinkNavigator.screenFor(target);
    expect(screen, isA<AirmiusDeepLinkedClubServiceHoursScreen>());
    final serviceHours = screen as AirmiusDeepLinkedClubServiceHoursScreen;
    expect(serviceHours.clubId, 7);
    expect(serviceHours.periodId, 12);
    expect(serviceHours.recordId, 33);
  });

  test('disputed legacy response still opens the club service hours', () {
    final notification = AirmiusNotification.fromJson({
      'id': 42,
      'type': 'club.service_hour.member_disputed',
      'data': {
        'club_id': 7,
        'service_hour_record_id': 34,
        'mobile_url': 'airmius://notifications',
      },
    });

    expect(
      notification.actionUrl,
      'airmius://clubs/7/service-hours?record=34',
    );
  });
}
