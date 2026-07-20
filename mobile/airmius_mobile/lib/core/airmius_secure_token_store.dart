import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'airmius_api_models.dart';
import 'airmius_auth_state.dart';
import 'airmius_persistent_token_store.dart';

class AirmiusSecureTokenStore implements AirmiusTokenStore {
  AirmiusSecureTokenStore({
    FlutterSecureStorage? storage,
    AirmiusSecureSessionStorage? secureStorage,
    AirmiusTokenStore? fallback,
  }) : _storage =
           secureStorage ??
           AirmiusFlutterSecureSessionStorage(
             storage ??
                 FlutterSecureStorage(
                   aOptions: AndroidOptions(migrateWithBackup: true),
                 ),
           ),
       _legacyMigrationStore = fallback ?? AirmiusPersistentTokenStore();

  final AirmiusSecureSessionStorage _storage;
  final AirmiusTokenStore _legacyMigrationStore;

  @override
  Future<AirmiusSession?> read() async {
    try {
      final raw = await _storage.read(key: _sessionKey);
      final secureSession = _decode(raw);
      if (secureSession != null) {
        return secureSession;
      }

      final legacySession = await _legacyMigrationStore.read();
      if (legacySession != null) {
        await _storage.write(key: _sessionKey, value: _encode(legacySession));
        await _legacyMigrationStore.clear();
      }
      return legacySession;
    } catch (_) {
      await _legacyMigrationStore.clear();
      return null;
    }
  }

  @override
  Future<void> write(AirmiusSession session) async {
    final payload = _encode(session);
    try {
      await _storage.write(key: _sessionKey, value: payload);
    } finally {
      await _legacyMigrationStore.clear();
    }
  }

  @override
  Future<void> clear() async {
    try {
      await _storage.delete(key: _sessionKey);
    } finally {
      await _legacyMigrationStore.clear();
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
              'gender': user.gender,
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
              'bio': user.bio,
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
      if (token is! String ||
          token.isEmpty ||
          locale is! String ||
          locale.isEmpty) {
        return null;
      }

      return AirmiusSession(
        token: token,
        locale: locale,
        user: user is JsonMap ? AirmiusUser.fromJson(user) : null,
        expiresAt: expiresAt is String && expiresAt.isNotEmpty
            ? DateTime.tryParse(expiresAt)
            : null,
      );
    } catch (_) {
      return null;
    }
  }

  static const _sessionKey = 'airmius.auth.session';
}

abstract class AirmiusSecureSessionStorage {
  Future<String?> read({required String key});

  Future<void> write({required String key, required String value});

  Future<void> delete({required String key});
}

class AirmiusFlutterSecureSessionStorage
    implements AirmiusSecureSessionStorage {
  const AirmiusFlutterSecureSessionStorage(this.storage);

  final FlutterSecureStorage storage;

  @override
  Future<String?> read({required String key}) => storage.read(key: key);

  @override
  Future<void> write({required String key, required String value}) =>
      storage.write(key: key, value: value);

  @override
  Future<void> delete({required String key}) => storage.delete(key: key);
}
