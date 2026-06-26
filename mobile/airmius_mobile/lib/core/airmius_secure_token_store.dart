import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'airmius_api_models.dart';
import 'airmius_auth_state.dart';
import 'airmius_persistent_token_store.dart';

class AirmiusSecureTokenStore implements AirmiusTokenStore {
  AirmiusSecureTokenStore({
    FlutterSecureStorage? storage,
    AirmiusTokenStore? fallback,
  })  : _storage = storage ??
            FlutterSecureStorage(
              aOptions: AndroidOptions(migrateWithBackup: true),
            ),
        _fallback = fallback ?? AirmiusPersistentTokenStore();

  final FlutterSecureStorage _storage;
  final AirmiusTokenStore _fallback;

  @override
  Future<AirmiusSession?> read() async {
    try {
      final raw = await _storage.read(key: _sessionKey);
      final secureSession = _decode(raw);
      if (secureSession != null) {
        return secureSession;
      }

      final fallbackSession = await _fallback.read();
      if (fallbackSession != null) {
        await write(fallbackSession);
      }
      return fallbackSession;
    } catch (_) {
      return _fallback.read();
    }
  }

  @override
  Future<void> write(AirmiusSession session) async {
    final payload = _encode(session);
    try {
      await _storage.write(key: _sessionKey, value: payload);
      await _fallback.clear();
    } catch (_) {
      await _fallback.write(session);
    }
  }

  @override
  Future<void> clear() async {
    try {
      await _storage.delete(key: _sessionKey);
    } finally {
      await _fallback.clear();
    }
  }

  static String _encode(AirmiusSession session) {
    final user = session.user;
    return jsonEncode({
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
  }

  static AirmiusSession? _decode(String? raw) {
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

  static const _sessionKey = 'airmius.auth.session';
}
