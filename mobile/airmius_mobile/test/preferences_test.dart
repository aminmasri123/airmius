import 'package:airmius/core/airmius_preferences.dart';
import 'package:airmius/core/airmius_preferences_store_stub.dart';
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
}
