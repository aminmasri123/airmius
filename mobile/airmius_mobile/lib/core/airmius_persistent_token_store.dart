import 'dart:convert';

import 'airmius_api_models.dart';
import 'airmius_auth_state.dart';
import 'airmius_preferences_store.dart';

class AirmiusPersistentTokenStore implements AirmiusTokenStore {
  AirmiusPersistentTokenStore({
    AirmiusPreferencesStore? store,
  }) : _store = store ?? createAirmiusPreferencesStore();

  final AirmiusPreferencesStore _store;

  @override
  Future<AirmiusSession?> read() async {
    final raw = await _store.readString(_sessionKey);
    if (raw == null || raw.trim().isEmpty) {
      return null;
    }

    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map<String, dynamic>) {
        return null;
      }

      final token = decoded['token'];
      final locale = decoded['locale'];
      final user = decoded['user'];
      final expiresAt = decoded['expires_at'];
      if (token is! String || token.isEmpty || locale is! String || locale.isEmpty) {
        return null;
      }

      return AirmiusSession(
        token: token,
        locale: locale,
        user: user is JsonMap ? AirmiusUser.fromJson(user) : null,
        expiresAt: expiresAt is String && expiresAt.isNotEmpty ? DateTime.tryParse(expiresAt) : null,
      );
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> write(AirmiusSession session) {
    final user = session.user;
    final payload = jsonEncode({
      'token': session.token,
      'locale': session.locale,
      'expires_at': session.expiresAt?.toIso8601String(),
      'user': user == null
          ? null
          : {
              'id': user.id,
              'name': user.name,
              'first_name': user.firstName,
              'last_name': user.lastName,
              'birth_date': user.birthDate?.toIso8601String(),
              'guardian_email': user.guardianEmail,
              'country': user.country,
              'street': user.street,
              'house_number': user.houseNumber,
              'postal_code': user.postalCode,
              'city': user.city,
              'state': user.state,
              'email': user.email,
              'role': user.role,
              'avatar_url': user.avatarUrl,
            },
    });
    return _store.writeString(_sessionKey, payload);
  }

  @override
  Future<void> clear() {
    return _store.writeString(_sessionKey, '');
  }

  static const _sessionKey = 'airmius.auth.session';
}
