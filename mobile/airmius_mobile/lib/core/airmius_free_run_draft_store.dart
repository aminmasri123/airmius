import 'dart:convert';

import 'airmius_preferences_store.dart';

class AirmiusFreeRunDraftStore {
  AirmiusFreeRunDraftStore({
    AirmiusPreferencesStore? store,
    this.retention = const Duration(hours: 24),
  }) : _store = store ?? createAirmiusPreferencesStore();

  final AirmiusPreferencesStore _store;
  final Duration retention;

  Future<Map<String, dynamic>?> read({required int userId}) async {
    final raw = await _store.readString(_key(userId));
    if (raw == null || raw.trim().isEmpty) return null;

    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      final draft = Map<String, dynamic>.from(decoded);
      final savedAt = DateTime.tryParse('${draft['saved_at'] ?? ''}');
      if (savedAt == null || DateTime.now().difference(savedAt) > retention) {
        await clear(userId: userId);
        return null;
      }
      if (draft['user_id'] != userId) return null;
      return draft;
    } catch (_) {
      return null;
    }
  }

  Future<void> write({
    required int userId,
    required Map<String, dynamic> payload,
  }) {
    return _store.writeString(
      _key(userId),
      jsonEncode({
        ...payload,
        'schema_version': 1,
        'user_id': userId,
        'saved_at': DateTime.now().toIso8601String(),
      }),
    );
  }

  Future<void> clear({required int userId}) {
    return _store.writeString(_key(userId), '');
  }

  Future<bool> hasAcceptedAndroidBackgroundDisclosure({
    required int userId,
  }) async {
    return await _store.readString(_disclosureKey(userId)) == 'accepted';
  }

  Future<void> acceptAndroidBackgroundDisclosure({required int userId}) {
    return _store.writeString(_disclosureKey(userId), 'accepted');
  }

  static String _key(int userId) => 'airmius.freeRunDraft.v1.$userId';

  static String _disclosureKey(int userId) =>
      'airmius.freeRunAndroidBackgroundDisclosure.v1.$userId';
}
