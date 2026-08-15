import 'dart:convert';

import 'airmius_preferences_store.dart';

class AirmiusTrainingDraftStore {
  AirmiusTrainingDraftStore({
    AirmiusPreferencesStore? store,
    this.retention = const Duration(hours: 24),
  }) : _store = store ?? createAirmiusPreferencesStore();

  final AirmiusPreferencesStore _store;
  final Duration retention;

  Future<Map<String, dynamic>?> read({
    required int userId,
    required int planItemId,
  }) async {
    final raw = await _store.readString(_key(userId, planItemId));
    if (raw == null || raw.trim().isEmpty) return null;

    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      final draft = Map<String, dynamic>.from(decoded);
      final savedAt = DateTime.tryParse('${draft['saved_at'] ?? ''}');
      if (savedAt == null || DateTime.now().difference(savedAt) > retention) {
        await clear(userId: userId, planItemId: planItemId);
        return null;
      }
      if (draft['user_id'] != userId || draft['plan_item_id'] != planItemId) {
        return null;
      }
      return draft;
    } catch (_) {
      return null;
    }
  }

  Future<void> write({
    required int userId,
    required int planItemId,
    required Map<String, dynamic> payload,
  }) {
    return _store.writeString(
      _key(userId, planItemId),
      jsonEncode({
        ...payload,
        'schema_version': 1,
        'user_id': userId,
        'plan_item_id': planItemId,
        'saved_at': DateTime.now().toIso8601String(),
      }),
    );
  }

  Future<void> clear({required int userId, required int planItemId}) {
    return _store.writeString(_key(userId, planItemId), '');
  }

  static String _key(int userId, int planItemId) =>
      'airmius.trainingDraft.v1.$userId.$planItemId';
}
