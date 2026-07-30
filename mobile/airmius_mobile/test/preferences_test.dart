import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_preferences.dart';
import 'package:airmius/core/airmius_preferences_store_stub.dart';
import 'package:airmius/models/footer_navigation_destination.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('onboarding profile survives a preferences round trip', () async {
    final store = AirmiusMemoryPreferencesStore();
    final preferences = AirmiusPreferences(store: store);

    await preferences.writeOnboardingProfile(
      role: 'Trainer',
      workspace: 'Team',
      permissions: {
        'privacy': true,
        'push': false,
        'location': true,
        'camera': false,
        'files': true,
        'guardian': false,
      },
    );

    final profile = await preferences.readOnboardingProfile();
    expect(profile?['role'], 'Trainer');
    expect(profile?['workspace'], 'Team');
    expect((profile?['permissions'] as Map)['location'], true);
    expect((profile?['permissions'] as Map)['push'], false);
  });

  test('footer navigation is stored separately for each user', () async {
    final preferences = AirmiusPreferences(
      store: AirmiusMemoryPreferencesStore(),
    );
    const firstUserNavigation = [
      FooterNavigationDestination.messages,
      FooterNavigationDestination.feed,
      FooterNavigationDestination.drink,
    ];
    const secondUserNavigation = [
      FooterNavigationDestination.training,
      FooterNavigationDestination.teams,
      FooterNavigationDestination.profile,
      FooterNavigationDestination.settings,
    ];

    await preferences.writeFooterNavigation(7, firstUserNavigation);
    await preferences.writeFooterNavigation(8, secondUserNavigation);

    expect(await preferences.readFooterNavigation(7), firstUserNavigation);
    expect(await preferences.readFooterNavigation(8), secondUserNavigation);
  });

  test('footer navigation enforces three to five unique items', () async {
    final preferences = AirmiusPreferences(
      store: AirmiusMemoryPreferencesStore(),
    );

    expect(
      () => preferences.writeFooterNavigation(7, const [
        FooterNavigationDestination.feed,
        FooterNavigationDestination.profile,
      ]),
      throwsArgumentError,
    );
    expect(
      () => preferences.writeFooterNavigation(7, const [
        FooterNavigationDestination.feed,
        FooterNavigationDestination.feed,
        FooterNavigationDestination.profile,
      ]),
      throwsArgumentError,
    );
  });

  test(
    'footer sanitizer removes inaccessible destinations and keeps minimum',
    () {
      const user = AirmiusUser(
        id: 7,
        name: 'Mina Sport',
        email: 'mina@example.test',
        role: 'player',
        roles: ['player'],
        permissions: ['training.view'],
      );

      final destinations = sanitizeFooterNavigation(
        destinations: const [
          FooterNavigationDestination.files,
          FooterNavigationDestination.messages,
          FooterNavigationDestination.feed,
        ],
        user: user,
      );

      expect(destinations, isNot(contains(FooterNavigationDestination.files)));
      expect(destinations, contains(FooterNavigationDestination.messages));
      expect(destinations, hasLength(3));
    },
  );
}
