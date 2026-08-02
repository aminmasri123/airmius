import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';

import 'airmius_api_client.dart';
import 'airmius_api_models.dart';
import 'airmius_api_repositories.dart';
import 'airmius_l10n.dart';

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
  twoFactorRequired,
  authenticated,
  expired,
  loading,
  error,
}

class AirmiusAuthState extends ChangeNotifier {
  AirmiusAuthState({
    required this.tokenStore,
    required this.clientFactory,
    this.onAuthenticated,
    this.onBeforeSignOut,
  });

  final AirmiusTokenStore tokenStore;
  final AirmiusApiClient Function(AirmiusSession? session) clientFactory;
  final Future<void> Function(AirmiusSession session)? onAuthenticated;
  final Future<void> Function(AirmiusSession session)? onBeforeSignOut;

  AirmiusSession? _session;
  AirmiusAuthPhase _phase = AirmiusAuthPhase.booting;
  String? _error;
  String? _twoFactorChallengeToken;
  Set<String> _twoFactorAvailableMethods = const {};
  String _twoFactorLocale = 'de';
  String _locale = 'de';

  AirmiusSession? get session => _session;
  AirmiusAuthPhase get phase => _phase;
  String? get error => _error;
  bool get isAuthenticated =>
      _session?.isAuthenticated == true &&
      _phase == AirmiusAuthPhase.authenticated;
  AirmiusUser? get user => _session?.user;
  bool get requiresTwoFactor =>
      _phase == AirmiusAuthPhase.twoFactorRequired &&
      _twoFactorChallengeToken != null;
  bool get supportsTwoFactorEmail =>
      _twoFactorAvailableMethods.contains('email_otp');

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
    _locale = restored.locale;
    if (restored.isExpired) {
      _session = restored;
      _setPhase(AirmiusAuthPhase.expired);
      return;
    }
    final resolved = await _refreshUserProfileIfPossible(restored);
    _session = resolved == null ? restored : restored.copyWith(user: resolved);
    if (resolved != null) {
      await tokenStore.write(_session!);
    }
    _setPhase(AirmiusAuthPhase.authenticated);
    _notifyAuthenticated(_session!);
  }

  Future<void> signIn({
    required String email,
    required String password,
    String locale = 'de',
  }) async {
    _locale = locale;
    _error = null;
    _setPhase(AirmiusAuthPhase.loading);
    try {
      final guestClient = clientFactory(null);
      final json = await guestClient.login(
        email: email,
        password: password,
        deviceName: _mobileDeviceName,
      );
      final data = json['data'];
      if (data is JsonMap && _truthy(data['two_factor_required'])) {
        final challengeToken = data['challenge_token']?.toString().trim() ?? '';
        if (challengeToken.isEmpty) {
          _error = _authMessage('auth.error.unexpected');
          _setPhase(AirmiusAuthPhase.error);
          return;
        }
        _twoFactorChallengeToken = challengeToken;
        final methods = data['available_methods'];
        _twoFactorAvailableMethods = methods is List
            ? methods.map((method) => method.toString()).toSet()
            : const {};
        _twoFactorLocale = locale;
        _setPhase(AirmiusAuthPhase.twoFactorRequired);
        return;
      }
      await _completeTokenSignIn(
        json,
        locale: locale,
        missingTokenMessage: _authMessage('auth.error.tokenLogin'),
      );
    } catch (error) {
      if (error is AirmiusApiException) {
        _error = _readableAuthError(error);
      } else {
        _error = _authMessage('auth.error.unexpected');
      }
      _setPhase(AirmiusAuthPhase.error);
    }
  }

  Future<void> completeTwoFactor({
    required String value,
    bool recoveryCode = false,
    bool emailCode = false,
  }) async {
    _locale = _twoFactorLocale;
    final challengeToken = _twoFactorChallengeToken;
    final normalizedValue = value.trim();
    if (challengeToken == null || normalizedValue.isEmpty) {
      _error = _authMessage('auth.error.twoFactorMissing');
      _setPhase(AirmiusAuthPhase.twoFactorRequired);
      return;
    }

    _error = null;
    _setPhase(AirmiusAuthPhase.loading);
    try {
      final json = await clientFactory(null).completeTwoFactorChallenge(
        challengeToken: challengeToken,
        code: recoveryCode || emailCode
            ? null
            : normalizedValue.replaceAll(' ', ''),
        recoveryCode: recoveryCode ? normalizedValue : null,
        emailCode: emailCode ? normalizedValue.replaceAll(' ', '') : null,
      );
      await _completeTokenSignIn(
        json,
        locale: _twoFactorLocale,
        missingTokenMessage: _authMessage('auth.error.tokenLogin'),
      );
    } catch (error) {
      _error = error is AirmiusApiException
          ? _readableAuthError(error)
          : _authMessage('auth.error.unexpected');
      _setPhase(AirmiusAuthPhase.twoFactorRequired);
    }
  }

  Future<bool> requestTwoFactorEmailCode() async {
    _locale = _twoFactorLocale;
    final challengeToken = _twoFactorChallengeToken;
    if (challengeToken == null || !supportsTwoFactorEmail) {
      _error = _authMessage('auth.error.unexpected');
      _setPhase(AirmiusAuthPhase.twoFactorRequired);
      return false;
    }

    _error = null;
    _setPhase(AirmiusAuthPhase.loading);
    try {
      await clientFactory(
        null,
      ).requestTwoFactorEmailCode(challengeToken: challengeToken);
      _setPhase(AirmiusAuthPhase.twoFactorRequired);
      return true;
    } catch (error) {
      _error = error is AirmiusApiException
          ? _readableAuthError(error)
          : _authMessage('auth.error.unexpected');
      _setPhase(AirmiusAuthPhase.twoFactorRequired);
      return false;
    }
  }

  void cancelTwoFactor() {
    _twoFactorChallengeToken = null;
    _twoFactorAvailableMethods = const {};
    _error = null;
    _setPhase(AirmiusAuthPhase.guest);
  }

  Future<void> register({
    required JsonMap payload,
    String locale = 'de',
  }) async {
    _locale = locale;
    _error = null;
    _setPhase(AirmiusAuthPhase.loading);
    try {
      final guestClient = clientFactory(null);
      final email = payload['email']?.toString().trim() ?? '';
      if (email.isNotEmpty) {
        final existingMessage = await _existingRegistrationEmailMessage(
          guestClient,
          email,
        );
        if (existingMessage != null) {
          _error = existingMessage;
          _setPhase(AirmiusAuthPhase.error);
          return;
        }
      }

      final registrationPayload = JsonMap.from(payload)
        ..putIfAbsent('device_name', () => _mobileDeviceName);
      final json = await guestClient.register(registrationPayload);
      await _completeTokenSignIn(
        json,
        locale: locale,
        missingTokenMessage: _authMessage('auth.error.tokenRegister'),
      );
    } catch (error) {
      _error = error is AirmiusApiException
          ? _readableAuthError(error)
          : _authMessage('auth.error.unexpected');
      _setPhase(AirmiusAuthPhase.error);
    }
  }

  String get _mobileDeviceName {
    if (kIsWeb) return 'Airmius Web App';

    return switch (defaultTargetPlatform) {
      TargetPlatform.android => 'Airmius Android App',
      TargetPlatform.iOS => 'Airmius iPhone App',
      TargetPlatform.macOS => 'Airmius macOS App',
      TargetPlatform.windows => 'Airmius Windows App',
      TargetPlatform.linux => 'Airmius Linux App',
      TargetPlatform.fuchsia => 'Airmius Mobile App',
    };
  }

  Future<String?> _existingRegistrationEmailMessage(
    AirmiusApiClient client,
    String email,
  ) async {
    try {
      final json = await client.registrationEmailStatus(email: email);
      final data = json['data'];
      final exists = data is JsonMap
          ? _truthy(data['exists'])
          : _truthy(json['exists']);
      if (!exists) return null;

      return _authMessage('auth.error.accountExists');
    } on AirmiusApiException catch (error) {
      if (error.statusCode == 404 || error.statusCode == 405) {
        return null;
      }
      return null;
    } catch (_) {
      return null;
    }
  }

  Future<void> signInWithToken({
    required String token,
    String locale = 'de',
  }) async {
    _locale = locale;
    final normalizedToken = token.trim();
    if (normalizedToken.isEmpty) {
      _error = _authMessage('auth.error.tokenSocial');
      _setPhase(AirmiusAuthPhase.error);
      return;
    }

    _error = null;
    _setPhase(AirmiusAuthPhase.loading);
    try {
      final tokenSession = AirmiusSession(
        token: normalizedToken,
        locale: locale,
      );
      final user = await _refreshUserProfileIfPossible(tokenSession);
      final session = tokenSession.copyWith(
        user: user,
        clearUser: user == null,
      );
      await tokenStore.write(session);
      _session = session;
      _setPhase(AirmiusAuthPhase.authenticated);
      _notifyAuthenticated(session);
    } catch (error) {
      _error = error is AirmiusApiException
          ? _readableAuthError(error)
          : _authMessage('auth.error.unexpected');
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
      _error = _authMessage('auth.error.unexpected');
      _setPhase(AirmiusAuthPhase.error);
    }
  }

  Future<void> completeProfile({
    required JsonMap payload,
    bool preserveAuthenticatedPhase = false,
  }) async {
    final current = _session;
    if (current == null || !current.isAuthenticated) return;
    _error = null;
    if (!preserveAuthenticatedPhase) {
      _setPhase(AirmiusAuthPhase.loading);
    } else {
      notifyListeners();
    }
    try {
      final sessionClient = clientFactory(current);
      final json = await sessionClient.updateProfile(payload);
      final updatedUser =
          _extractUser(json) ?? await _refreshUserProfileIfPossible(current);
      final next = current.copyWith(
        user: updatedUser,
        clearUser: updatedUser == null,
      );
      await tokenStore.write(next);
      _session = next;
      _setPhase(AirmiusAuthPhase.authenticated);
    } catch (error) {
      _error = error is AirmiusApiException
          ? _readableAuthError(error)
          : _authMessage('auth.error.unexpected');
      _setPhase(
        preserveAuthenticatedPhase
            ? AirmiusAuthPhase.authenticated
            : AirmiusAuthPhase.error,
      );
    }
  }

  Future<void> updateLocale(String locale) async {
    _locale = locale;
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
        await _runBeforeSignOut(current);
        await sessionClient.logout();
      } catch (_) {
        // ignore backend errors on logout, local session is still removed to ensure the user can continue
      }
    }
    await tokenStore.clear();
    _session = null;
    _twoFactorChallengeToken = null;
    _twoFactorAvailableMethods = const {};
    _setPhase(AirmiusAuthPhase.guest);
  }

  void _notifyAuthenticated(AirmiusSession session) {
    final callback = onAuthenticated;
    if (callback == null) return;
    unawaited(callback(session).catchError((_) {}));
  }

  Future<void> _runBeforeSignOut(AirmiusSession session) async {
    final callback = onBeforeSignOut;
    if (callback == null) return;
    try {
      await callback(session);
    } catch (_) {
      // Logout must not be blocked by push-device cleanup.
    }
  }

  void _setPhase(AirmiusAuthPhase phase) {
    _phase = phase;
    notifyListeners();
  }

  Future<void> _completeTokenSignIn(
    JsonMap json, {
    required String locale,
    required String missingTokenMessage,
  }) async {
    final token = _tokenFrom(json);
    if (token.isEmpty) {
      _error = missingTokenMessage;
      _setPhase(AirmiusAuthPhase.error);
      return;
    }

    final loginUser = _extractUser(json);
    final tokenSession = AirmiusSession(
      token: token,
      locale: locale,
      user: loginUser,
    );
    final user = await _refreshUserProfileIfPossible(tokenSession);
    final session = tokenSession.copyWith(user: user, clearUser: user == null);
    await tokenStore.write(session);
    _session = session;
    _twoFactorChallengeToken = null;
    _twoFactorAvailableMethods = const {};
    _setPhase(AirmiusAuthPhase.authenticated);
    _notifyAuthenticated(session);
  }

  String _tokenFrom(JsonMap json) {
    final token = _pickFirstStringFrom([
      json['token'],
      json['access_token'],
      json['plain_text_token'],
      json['plainTextToken'],
      if (json['data'] is JsonMap) ...[
        if ((json['data'] as JsonMap)['token'] is Object)
          (json['data'] as JsonMap)['token'],
        if ((json['data'] as JsonMap)['access_token'] is Object)
          (json['data'] as JsonMap)['access_token'],
        if ((json['data'] as JsonMap)['plain_text_token'] is Object)
          (json['data'] as JsonMap)['plain_text_token'],
        if ((json['data'] as JsonMap)['plainTextToken'] is Object)
          (json['data'] as JsonMap)['plainTextToken'],
      ],
    ]);
    return token ?? '';
  }

  String _readableAuthError(AirmiusApiException error) {
    if (error.path.contains('/auth/login') &&
        _isLikelyInvalidCredentials(error)) {
      return _authMessage('auth.error.invalidCredentials');
    }

    final status = error.statusCode;
    if (status == 401 || status == 403) {
      return _authMessage('auth.error.invalidCredentials');
    }
    if (status == 422) {
      final parsed = _extractMessage(error.body);
      if (_isUniqueValidationMessage(parsed)) {
        return _authMessage('auth.error.accountExists');
      }
      final validation = _extractValidationMessage(error.body);
      if (validation.isNotEmpty) return _readableValidationMessage(validation);
      return _authMessage('auth.error.invalidInput');
    }
    if (status == 0) {
      if (error.path.contains('/auth/login')) {
        return _authMessage('auth.error.networkLogin');
      }
      return _authMessage('auth.error.network');
    }
    if (status == 599) {
      if (error.path.contains('/auth/login')) {
        return _authMessage('auth.error.connectionLogin');
      }
      return _authMessage('auth.error.connection');
    }
    if (status >= 500) {
      return _authMessage('auth.error.server', status: status);
    }
    if (status >= 400) {
      return _authMessage('auth.error.request', status: status);
    }
    return _authMessage('auth.error.fallback', status: error.statusCode);
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

  String _extractValidationMessage(String body) {
    final dataStart = body.indexOf('{');
    if (dataStart < 0) return '';
    try {
      final json = _safeJsonDecode(body.substring(dataStart).trim());
      if (json == null) return '';
      final errors = json['errors'];
      if (errors is Map) {
        for (final entry in errors.entries) {
          final first = _firstValidationMessage(entry.value);
          if (first.isNotEmpty) return '${entry.key}: $first';
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
    return normalized.contains('validation.unique') ||
        normalized.contains('already been taken') ||
        normalized.contains('bereits vergeben');
  }

  String _readableValidationMessage(String message) {
    return message;
  }

  AirmiusLanguage _languageForLocale(String locale) {
    return switch (locale.toLowerCase().split(RegExp('[-_]')).first) {
      'en' => AirmiusLanguage.en,
      'fr' => AirmiusLanguage.fr,
      'ar' => AirmiusLanguage.ar,
      _ => AirmiusLanguage.de,
    };
  }

  String _authMessage(String key, {int? status}) {
    return airmiusAuthMessage(_languageForLocale(_locale), key, status: status);
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

  Future<AirmiusUser?> _refreshUserProfileIfPossible(
    AirmiusSession session,
  ) async {
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
      if (data.containsKey('id') ||
          data.containsKey('name') ||
          data.containsKey('email')) {
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
