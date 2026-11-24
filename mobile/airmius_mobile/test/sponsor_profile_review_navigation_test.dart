import 'package:airmius/core/airmius_deep_links.dart';
import 'package:airmius/navigation/airmius_deep_link_navigator.dart';
import 'package:airmius/screens/sponsor_cockpit_screen.dart';
import 'package:airmius/screens/sponsor_management_screen.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('deliverable notification opens the correct sponsor club and performance', () {
    const resolver = AirmiusDeepLinkResolver();
    final target = resolver.resolve(
      'airmius://sponsors?sponsor_id=17&section=partnerships&club_id=26&deliverable_id=89',
    );
    final screen = AirmiusDeepLinkNavigator.screenFor(target) as SponsorCockpitScreen;
    expect(screen.initialSponsorId, 17);
    expect(screen.initialSection, 2);
    expect(screen.initialClubId, 26);
    expect(screen.initialDeliverableId, 89);
  });
  test('partnership notification opens the targeted request', () {
    const resolver = AirmiusDeepLinkResolver();
    final target = resolver.resolve('airmius://admin/sponsors?partnership_id=42');
    final screen = AirmiusDeepLinkNavigator.screenFor(target) as SponsorManagementScreen;

    expect(screen.initialPartnershipId, 42);
    expect(screen.initialSponsorId, isNull);
  });

  test('profile review notification opens the targeted admin sponsor screen', () {
    const resolver = AirmiusDeepLinkResolver();
    final target = resolver.resolve(
      'airmius://admin/sponsors?sponsor_id=42',
    );

    expect(target.type, AirmiusDeepLinkTargetType.adminSponsorManagement);
    expect(target.id, 42);
    final screen = AirmiusDeepLinkNavigator.screenFor(target);
    expect(screen, isA<SponsorManagementScreen>());
    expect((screen as SponsorManagementScreen).initialSponsorId, 42);
  });

  test('profile decision notification opens the targeted sponsor cockpit', () {
    const resolver = AirmiusDeepLinkResolver();
    final target = resolver.resolve('airmius://sponsors?sponsor_id=17');

    expect(target.type, AirmiusDeepLinkTargetType.sponsorApplication);
    final screen = AirmiusDeepLinkNavigator.screenFor(target);
    expect(screen, isA<SponsorCockpitScreen>());
    expect((screen as SponsorCockpitScreen).initialSponsorId, 17);
  });
}
