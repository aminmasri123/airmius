import 'airmius_preferences_store_base.dart';

AirmiusPreferencesStore createPlatformPreferencesStore() {
  return AirmiusMemoryPreferencesStore();
}

class AirmiusMemoryPreferencesStore implements AirmiusPreferencesStore {
  final Map<String, String> _values = {};

  @override
  Future<String?> readString(String key) async => _values[key];

  @override
  Future<void> writeString(String key, String value) async {
    _values[key] = value;
  }
}
