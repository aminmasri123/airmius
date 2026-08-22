import 'package:airmius/core/airmius_preferences.dart';
import 'package:airmius/core/airmius_preferences_store_base.dart';
import 'package:airmius/core/airmius_preferences_store_io.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'mobile onboarding completion is read from persistent storage',
    () async {
      final backend = _MemorySecureBackend({
        'airmius.permissionOnboardingComplete': 'true',
      });
      final store = AirmiusSecurePreferencesStore(
        backend: backend,
        legacyStore: _MemoryPreferencesStore(),
      );

      final preferences = AirmiusPreferences(store: store);

      expect(await preferences.readPermissionOnboardingComplete(), isTrue);
    },
  );

  test(
    'legacy onboarding completion is migrated to persistent storage',
    () async {
      final backend = _MemorySecureBackend();
      final legacyStore = _MemoryPreferencesStore({
        'airmius.permissionOnboardingComplete': 'true',
      });
      final store = AirmiusSecurePreferencesStore(
        backend: backend,
        legacyStore: legacyStore,
      );

      final preferences = AirmiusPreferences(store: store);

      expect(await preferences.readPermissionOnboardingComplete(), isTrue);
      expect(
        backend.values['airmius.permissionOnboardingComplete'],
        equals('true'),
      );
    },
  );

  test(
    'mobile preference writes fall back when secure storage is unavailable',
    () async {
      final legacyStore = _MemoryPreferencesStore();
      final store = AirmiusSecurePreferencesStore(
        backend: _FailingSecureBackend(),
        legacyStore: legacyStore,
      );

      await AirmiusPreferences(
        store: store,
      ).writePermissionOnboardingComplete(true);

      expect(
        legacyStore.values['airmius.permissionOnboardingComplete'],
        equals('true'),
      );
    },
  );
}

class _MemorySecureBackend implements AirmiusSecurePreferencesBackend {
  _MemorySecureBackend([Map<String, String>? initial]) : values = {...?initial};

  final Map<String, String> values;

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async {
    values[key] = value;
  }
}

class _FailingSecureBackend implements AirmiusSecurePreferencesBackend {
  @override
  Future<String?> read(String key) async => throw StateError('unavailable');

  @override
  Future<void> write(String key, String value) async =>
      throw StateError('unavailable');
}

class _MemoryPreferencesStore implements AirmiusPreferencesStore {
  _MemoryPreferencesStore([Map<String, String>? initial])
    : values = {...?initial};

  final Map<String, String> values;

  @override
  Future<String?> readString(String key) async => values[key];

  @override
  Future<void> writeString(String key, String value) async {
    values[key] = value;
  }
}
