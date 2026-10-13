import 'dart:convert';
import 'dart:math';

import 'airmius_api_client.dart';
import 'airmius_preferences_store.dart';

class WorkAutomationRequestStore {
  WorkAutomationRequestStore({AirmiusPreferencesStore? store})
    : _store = store ?? createAirmiusPreferencesStore();

  final AirmiusPreferencesStore _store;
  String _key(String account, int clubId) =>
      'airmius.work-automation.v1:$account:$clubId';

  static String newKey() {
    final random = Random.secure();
    return List.generate(
      24,
      (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0'),
    ).join();
  }

  Future<AirmiusJson?> read(String account, int clubId) async {
    final raw = await _store.readString(_key(account, clubId));
    if (raw == null || raw.isEmpty) return null;
    try {
      final value = jsonDecode(raw);
      return value is AirmiusJson &&
              value['idempotency_key'] is String &&
              value['payload'] is Map
          ? value
          : null;
    } catch (_) {
      return null;
    }
  }

  Future<void> save(String account, int clubId, AirmiusJson payload) =>
      _store.writeString(_key(account, clubId), jsonEncode(payload));

  Future<void> clear(String account, int clubId) =>
      _store.writeString(_key(account, clubId), '');
}
