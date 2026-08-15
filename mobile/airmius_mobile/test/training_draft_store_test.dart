import 'package:airmius/core/airmius_preferences_store_stub.dart';
import 'package:airmius/core/airmius_training_draft_store.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('live workout draft is scoped and survives a round trip', () async {
    final preferences = AirmiusMemoryPreferencesStore();
    final store = AirmiusTrainingDraftStore(store: preferences);

    await store.write(
      userId: 7,
      planItemId: 42,
      payload: {
        'started_at': '2026-08-15T10:00:00.000',
        'actual_exercises': [
          {'exercise_key': 'squat', 'completed': true},
        ],
      },
    );

    final restored = await store.read(userId: 7, planItemId: 42);
    expect(restored?['started_at'], '2026-08-15T10:00:00.000');
    expect((restored?['actual_exercises'] as List).length, 1);
    expect(await store.read(userId: 8, planItemId: 42), isNull);

    await store.clear(userId: 7, planItemId: 42);
    expect(await store.read(userId: 7, planItemId: 42), isNull);
  });

  test('expired workout drafts are ignored and removed', () async {
    final preferences = AirmiusMemoryPreferencesStore();
    final store = AirmiusTrainingDraftStore(
      store: preferences,
      retention: Duration.zero,
    );

    await store.write(
      userId: 7,
      planItemId: 42,
      payload: const {'actual_exercises': []},
    );

    expect(await store.read(userId: 7, planItemId: 42), isNull);
  });
}
