import 'package:airmius/core/airmius_free_run_draft_store.dart';
import 'package:airmius/core/airmius_preferences_store_stub.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('free run draft is user scoped and restores GPS points', () async {
    final preferences = AirmiusMemoryPreferencesStore();
    final store = AirmiusFreeRunDraftStore(store: preferences);

    await store.write(
      userId: 7,
      payload: {
        'track_id': 91,
        'started_at': '2026-08-15T10:00:00.000',
        'active_seconds': 360,
        'status': 'paused',
        'points': [
          {
            'latitude': 52.52,
            'longitude': 13.405,
            'recorded_at': '2026-08-15T10:00:00.000',
          },
        ],
      },
    );

    final restored = await store.read(userId: 7);
    expect(restored?['track_id'], 91);
    expect(restored?['active_seconds'], 360);
    expect(restored?['points'], hasLength(1));
    expect(await store.read(userId: 8), isNull);

    expect(
      await store.hasAcceptedAndroidBackgroundDisclosure(userId: 7),
      isFalse,
    );
    await store.acceptAndroidBackgroundDisclosure(userId: 7);
    expect(
      await store.hasAcceptedAndroidBackgroundDisclosure(userId: 7),
      isTrue,
    );
    expect(
      await store.hasAcceptedAndroidBackgroundDisclosure(userId: 8),
      isFalse,
    );

    await store.clear(userId: 7);
    expect(await store.read(userId: 7), isNull);
    expect(
      await store.hasAcceptedAndroidBackgroundDisclosure(userId: 7),
      isTrue,
    );
  });

  test('expired free run drafts are removed', () async {
    final preferences = AirmiusMemoryPreferencesStore();
    final store = AirmiusFreeRunDraftStore(
      store: preferences,
      retention: Duration.zero,
    );

    await store.write(
      userId: 7,
      payload: const {
        'track_id': 91,
        'started_at': '2026-08-15T10:00:00.000',
        'points': [],
      },
    );

    expect(await store.read(userId: 7), isNull);
  });
}
