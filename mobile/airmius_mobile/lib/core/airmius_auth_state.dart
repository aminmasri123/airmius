import 'dart:convert';

import 'package:flutter/foundation.dart';

import 'airmius_api_client.dart';
import 'airmius_api_models.dart';
import 'airmius_api_repositories.dart';

class AirmiusSession {
  const AirmiusSession({
    required this.token,
    required this.locale,
    this.user,
    this.expiresAt,
  });

  final String token;
  final String locale;
  final AirmiusUser? user;
  final DateTime? expiresAt;

  bool get isExpired => expiresAt != null && DateTime.now().isAfter(expiresAt!);
  bool get isAuthenticated => token.isNotEmpty && !isExpired;

  AirmiusSession copyWith({
    String? token,
    String? locale,
    AirmiusUser? user,
    DateTime? expiresAt,
    bool clearUser = false,
  }) {
    return AirmiusSession(
      token: token ?? this.token,
      locale: locale ?? this.locale,
      user: clearUser ? null : user ?? this.user,
      expiresAt: expiresAt ?? this.expiresAt,
    );
  }
}

abstract class AirmiusTokenStore {
  Future<AirmiusSession?> read();
  Future<void> write(AirmiusSession session);
  Future<void> clear();
}

class AirmiusMemoryTokenStore implements AirmiusTokenStore {
  AirmiusSession? _session;

  @override
  Future<AirmiusSession?> read() async => _session;

  @override
  Future<void> write(AirmiusSession session) async {
    _session = session;
  }

  @override
  Future<void> clear() async {
    _session = null;
  }
}

enum AirmiusAuthPhase {
  booting,
  guest,
  authenticated,
  expired,
  loading,
  error,
}

class AirmiusAuthState extends ChangeNotifier {
  AirmiusAuthState({
    required this.tokenStore,
    required this.clientFactory,
  });

  final AirmiusTokenStore tokenStore;
  final AirmiusApiClient Function(AirmiusSession? session) clientFactory;

  AirmiusSession? _session;
  AirmiusAuthPhase _phase = AirmiusAuthPhase.booting;
  String? _error;

  AirmiusSession? get session => _session;
  AirmiusAuthPhase get phase => _phase;
  String? get error => _error;
  bool get isAuthenticated => _session?.isAuthenticated == true && _phase == AirmiusAuthPhase.authenticated;
  AirmiusUser? get user => _session?.user;

  Future<void> restore() async {
    _setPhase(AirmiusAuthPhase.loading);
    final restored = await tokenStore.read();
    if (restored == null) {
      _session = null;
      _setPhase(AirmiusAuthPhase.guest);
      return;
    }
    if (restored.token.isEmpty) {
      _session = null;
      _setPhase(AirmiusAuthPhase.guest);
      return;
    }
    if (restored.isExpired) {
      _session = restored;
      _setPhase(AirmiusAuthPhase.expired);
      return;
    }
    final resolved = await _refreshUserProfileIfPossible(restored);
    _session = resolved == null ? restored : restored.copyWith(user: resolved);
    _setPhase(AirmiusAuthPhase.authenticated);
  }

  Future<void> signIn({required String email, required String password, String locale = 'de'}) async {
    _error = null;
    _setPhase(AirmiusAuthPhase.loading);
    try {
      final guestClient = clientFactory(null);
      final json = await guestClient.login(email: email, password: password);
      await _completeTokenSignIn(json, locale: locale, missingTokenMessage: 'Login fehlgeschlagen: Token vom Server fehlt.');
    } catch (error) {
      if (error is AirmiusApiException) {
        _error = _readableAuthError(error);
      } else {
        _error = error.toString();
      }
      _setPhase(AirmiusAuthPhase.error);
    }
  }

  Future<void> register({required JsonMap payload, String locale = 'de'}) async {
    _error = null;
    _setPhase(AirmiusAuthPhase.loading);
    try {
      final guestClient = clientFactory(null);
      final email = payload['email']?.toString().trim() ?? '';
      if (email.isNotEmpty) {
        final existingMessage = await _existingRegistrationEmailMessage(guestClient, email);
        if (existingMessage != null) {
          _error = existingMessage;
          _setPhase(AirmiusAuthPhase.error);
          return;
        }
      }

      final json = await guestClient.register(payload);
      await _completeTokenSignIn(json, locale: locale, missingTokenMessage: 'Registrierung fehlgeschlagen: Token vom Server fehlt.');
    } catch (error) {
      _error = error is AirmiusApiException ? _readableAuthError(error) : error.toString();
      _setPhase(AirmiusAuthPhase.authenticated);
    }
  }

  Future<String?> _existingRegistrationEmailMessage(AirmiusApiClient client, String email) async {
    try {
      final json = await client.registrationEmailStatus(email: email);
      final data = json['data'];
      final exists = data is JsonMap ? _truthy(data['exists']) : _truthy(json['exists']);
      if (!exists) return null;

      final message = data is JsonMap ? data['message']?.toString().trim() : json['message']?.toString().trim();
      return message == null || message.isEmpty ? 'Dieses Konto existiert bereits. Bitte melde dich an oder nutze Passwort vergessen.' : message;
    } on AirmiusApiException catch (error) {
      if (error.statusCode == 404 || error.statusCode == 405) {
        return null;
      }
      return null;
    } catch (_) {
      return null;
    }
  }

  Future<void> signInWithToken({required String token, String locale = 'de'}) async {
    final normalizedToken = token.trim();
    if (normalizedToken.isEmpty) {
      _error = 'Social Login fehlgeschlagen: Token vom Server fehlt.';
      _setPhase(AirmiusAuthPhase.error);
      return;
    }

    _error = null;
    _setPhase(AirmiusAuthPhase.loading);
    try {
      final tokenSession = AirmiusSession(token: normalizedToken, locale: locale);
      final user = await _refreshUserProfileIfPossible(tokenSession);
      final session = tokenSession.copyWith(user: user, clearUser: user == null);
      await tokenStore.write(session);
      _session = session;
      _setPhase(AirmiusAuthPhase.authenticated);
    } catch (error) {
      _error = error is AirmiusApiException ? _readableAuthError(error) : error.toString();
      _setPhase(AirmiusAuthPhase.error);
    }
  }

  Future<void> refreshUser() async {
    final current = _session;
    if (current == null || !current.isAuthenticated) return;
    _setPhase(AirmiusAuthPhase.loading);
    try {
      final repos = AirmiusRepositoryBundle.api(clientFactory(current));
      final user = await repos.auth.currentUser();
      final next = current.copyWith(user: user);
      await tokenStore.write(next);
      _session = next;
      _setPhase(AirmiusAuthPhase.authenticated);
    } catch (error) {
      _error = error.toString();
      _setPhase(AirmiusAuthPhase.error);
    }
  }

  Future<void> completeProfile({required JsonMap payload}) async {
    final current = _session;
    if (current == null || !current.isAuthenticated) return;
    _error = null;
    _setPhase(AirmiusAuthPhase.loading);
    try {
      final sessionClient = clientFactory(current);
      final json = await sessionClient.updateProfile(payload);
      final updatedUser = _extractUser(json) ?? await _refreshUserProfileIfPossible(current);
      final next = current.copyWith(user: updatedUser, clearUser: updatedUser == null);
      await tokenStore.write(next);
      _session = next;
      _setPhase(AirmiusAuthPhase.authenticated);
    } catch (error) {
      _error = error is AirmiusApiException ? _readableAuthError(error) : error.toString();
      _setPhase(AirmiusAuthPhase.error);
    }
  }

  Future<void> updateLocale(String locale) async {
    final current = _session;
    if (current == null) return;
    final next = current.copyWith(locale: locale);
    await tokenStore.write(next);
    _session = next;
    notifyListeners();
  }

  Future<void> signOut() async {
    _error = null;
    _setPhase(AirmiusAuthPhase.loading);
    final current = _session;
    if (current != null && current.token.isNotEmpty) {
      try {
        final sessionClient = clientFactory(current);
        await sessionClient.logout();
      } catch (_) {
        // ignore backend errors on logout, local session is still removed to ensure the user can continue
      }
    }
    await tokenStore.clear();
    _session = null;
    _setPhase(AirmiusAuthPhase.guest);
  }

  void _setPhase(AirmiusAuthPhase phase) {
    _phase = phase;
    notifyListeners();
  }

  Future<void> _completeTokenSignIn(JsonMap json, {required String locale, required String missingTokenMessage}) async {
    final token = _tokenFrom(json);
    if (token.isEmpty) {
      _error = missingTokenMessage;
      _setPhase(AirmiusAuthPhase.error);
      return;
    }

    final loginUser = _extractUser(json);
    final tokenSession = AirmiusSession(token: token, locale: locale, user: loginUser);
    final user = await _refreshUserProfileIfPossible(tokenSession);
    final session = tokenSession.copyWith(user: user, clearUser: user == null);
    await tokenStore.write(session);
    _session = session;
    _setPhase(AirmiusAuthPhase.authenticated);
  }

  String _tokenFrom(JsonMap json) {
    final token = _pickFirstStringFrom([
      json['token'],
      json['access_token'],
      json['plain_text_token'],
      json['plainTextToken'],
      if (json['data'] is JsonMap) ...[
        if ((json['data'] as JsonMap)['token'] is Object) (json['data'] as JsonMap)['token'],
        if ((json['data'] as JsonMap)['access_token'] is Object) (json['data'] as JsonMap)['access_token'],
        if ((json['data'] as JsonMap)['plain_text_token'] is Object) (json['data'] as JsonMap)['plain_text_token'],
        if ((json['data'] as JsonMap)['plainTextToken'] is Object) (json['data'] as JsonMap)['plainTextToken'],
      ],
    ]);
    return token ?? '';
  }

  String _readableAuthError(AirmiusApiException error) {
    if (error.path.contains('/auth/login') && _isLikelyInvalidCredentials(error)) {
      return 'E-Mail oder Passwort ist falsch.';
    }

    final status = error.statusCode;
    if (status == 401 || status == 403) {
      return 'E-Mail oder Passwort ist falsch.';
    }
    if (status == 422) {
      final parsed = _extractMessage(error.body);
      if (parsed.isNotEmpty) return parsed;
      return 'Bitte prüfe deine Eingaben und versuche es erneut.';
    }
    if (status == 0) {
      if (error.path.contains('/auth/login')) {
        return 'Login nicht bestätigt. Bitte prüfe E-Mail/Passwort oder lass die Server-Verbindung/CORS-Einstellungen prüfen.';
      }
      return 'Netzwerkfehler: Der Server ist nicht erreichbar. Bitte URL, Internetverbindung und CORS/Sicherheit prüfen.';
    }
    if (status == 599) {
      if (error.path.contains('/auth/login')) {
        return 'Login nicht bestätigt. Bitte prüfe E-Mail/Passwort oder lass die Server-Verbindung/CORS-Einstellungen prüfen.';
      }
      return 'Verbindung abgebrochen: Der Server konnte die Anfrage nicht korrekt beantworten (mögliche CORS-/Netzwerkproblematik).';
    }
    if (status >= 500) {
      return 'Server-Fehler (HTTP $status). Bitte später erneut versuchen.';
    }
    if (status >= 400) {
      return 'Anfrage abgelehnt (HTTP $status). Bitte Daten prüfen oder Support kontaktieren.';
    }

    final parsed = _extractMessage(error.body);
    if (parsed.isNotEmpty) {
      return '$parsed (HTTP ${error.statusCode})';
    }
    return 'Anmeldung fehlgeschlagen (HTTP ${error.statusCode}).';
  }

  bool _isLikelyInvalidCredentials(AirmiusApiException error) {
    final message = _extractMessage(error.body).toLowerCase();
    final body = error.body.toLowerCase();
    final combined = '$message $body';
    final hints = <String>[
      'credentials',
      'credential',
      'password',
      'passwort',
      'unauthorized',
      'unauthenticated',
      'invalid',
      'unguelt',
      'incorrect',
      'falsch',
      'verifiziert',
    ];
    final hasHint = hints.any((hint) => combined.contains(hint));
    final hasAuthLoginPath = error.path.contains('/auth/login');
    if (!hasAuthLoginPath) return false;
    if (hasHint) return true;
    return false;
  }

  String _extractMessage(String body) {
    final dataStart = body.indexOf('{');
    if (dataStart < 0) return '';
    try {
      final parsed = body.substring(dataStart);
      final decoded = parsed.trim();
      final json = _safeJsonDecode(decoded);
      if (json == null) return '';
      final message = json['message'];
      if (message is String && message.trim().isNotEmpty) return message.trim();
      final errors = json['errors'];
      if (errors is Map) {
        final emailErrors = errors['email'];
        final emailMessage = _firstValidationMessage(emailErrors);
        if (_isUniqueValidationMessage(emailMessage)) {
          return 'Dieses Konto existiert bereits. Bitte melde dich an oder nutze Passwort vergessen.';
        }

        for (final entry in errors.values) {
          final first = _firstValidationMessage(entry);
          if (first.isNotEmpty) return _readableValidationMessage(first);
        }
      }
    } catch (_) {
      return '';
    }
    return '';
  }

  String _firstValidationMessage(Object? value) {
    if (value is List && value.isNotEmpty) {
      return value.first.toString().trim();
    }
    if (value is String) {
      return value.trim();
    }
    return '';
  }

  bool _isUniqueValidationMessage(String message) {
    final normalized = message.toLowerCase();
    return normalized.contains('validation.unique') || normalized.contains('already been taken') || normalized.contains('bereits vergeben');
  }

  String _readableValidationMessage(String message) {
    if (_isUniqueValidationMessage(message)) {
      return 'Dieses Konto existiert bereits. Bitte melde dich an oder nutze Passwort vergessen.';
    }
    return message;
  }

  bool _truthy(Object? value) {
    if (value == true) return true;
    final normalized = value?.toString().trim().toLowerCase();
    return normalized == 'true' || normalized == '1' || normalized == 'yes';
  }

  Map<String, dynamic>? _safeJsonDecode(String body) {
    try {
      final decoded = JsonDecoder().convert(body);
      if (decoded is Map<String, dynamic>) return decoded;
    } catch (_) {
      return null;
    }
    return null;
  }

  Future<AirmiusUser?> _refreshUserProfileIfPossible(AirmiusSession session) async {
    if (session.token.isEmpty) return session.user;
    try {
      final repos = AirmiusRepositoryBundle.api(clientFactory(session));
      final currentUser = await repos.auth.currentUser();
      return currentUser;
    } catch (_) {
      return session.user;
    }
  }

  AirmiusUser? _extractUser(JsonMap json) {
    if (json['user'] is JsonMap) {
      return AirmiusUser.fromJson(json['user'] as JsonMap);
    }
    final data = json['data'];
    if (data is JsonMap) {
      if (data['user'] is JsonMap) {
        return AirmiusUser.fromJson(data['user'] as JsonMap);
      }
      if (data.containsKey('id') || data.containsKey('name') || data.containsKey('email')) {
        return AirmiusUser.fromJson(data);
      }
    }
    return null;
  }

  String? _pickFirstStringFrom(Iterable<dynamic> values) {
    for (final value in values) {
      if (value == null) continue;
      final text = value.toString().trim();
      if (text.isNotEmpty) return text;
    }
    return null;
  }
}
