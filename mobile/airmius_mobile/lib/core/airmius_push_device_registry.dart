import 'dart:convert';
import 'dart:math';

import 'package:flutter/foundation.dart';

import 'airmius_api_client.dart';
import 'airmius_preferences_store_base.dart';

class AirmiusPushToken {
  const AirmiusPushToken({required this.token, this.provider});

  final String token;
  final String? provider;
}

abstract class AirmiusPushTokenProvider {
  Future<AirmiusPushToken?> requestToken();

  Future<AirmiusPushToken?> currentToken();
}

class AirmiusNoopPushTokenProvider implements AirmiusPushTokenProvider {
  const AirmiusNoopPushTokenProvider();

  @override
  Future<AirmiusPushToken?> requestToken() async => null;

  @override
  Future<AirmiusPushToken?> currentToken() async => null;
}

class AirmiusPushDeviceRegistration {
  const AirmiusPushDeviceRegistration({
    required this.deviceId,
    required this.platform,
    required this.provider,
    this.status,
    this.tokenFingerprint,
    this.registeredAt,
  });

  factory AirmiusPushDeviceRegistration.fromJson(Map<dynamic, dynamic> json) {
    return AirmiusPushDeviceRegistration(
      deviceId: json['device_id']?.toString() ?? '',
      platform: json['platform']?.toString() ?? 'unknown',
      provider: json['provider']?.toString() ?? 'fcm',
      status: json['status']?.toString(),
      tokenFingerprint: json['token_fingerprint']?.toString(),
      registeredAt: DateTime.tryParse(json['registered_at']?.toString() ?? ''),
    );
  }

  AirmiusJson toJson() {
    return {
      'device_id': deviceId,
      'platform': platform,
      'provider': provider,
      if (status != null) 'status': status,
      if (tokenFingerprint != null) 'token_fingerprint': tokenFingerprint,
      if (registeredAt != null)
        'registered_at': registeredAt!.toIso8601String(),
    };
  }

  final String deviceId;
  final String platform;
  final String provider;
  final String? status;
  final String? tokenFingerprint;
  final DateTime? registeredAt;
}

class AirmiusPushRegistrationResult {
  const AirmiusPushRegistrationResult({
    required this.status,
    this.registration,
    this.message,
  });

  final String status;
  final AirmiusPushDeviceRegistration? registration;
  final String? message;

  bool get isRegistered => status == 'registered' || status == 'updated';
}

class AirmiusPushDeviceRegistry {
  AirmiusPushDeviceRegistry({
    required this.store,
    this.tokenProvider = const AirmiusNoopPushTokenProvider(),
    this.locale = 'de',
    this.appVersion,
    this.buildNumber,
    String Function()? deviceIdFactory,
  }) : _deviceIdFactory = deviceIdFactory ?? _defaultDeviceId;

  static const defaultChannels = [
    'event_reminders',
    'chat_mentions',
    'social_updates',
    'training_updates',
    'commerce_orders',
    'club_billing',
  ];

  static const optInStorageKey = 'airmius.push.opt_in.v1';
  static const deviceIdStorageKey = 'airmius.push.device_id.v1';
  static const registrationStorageKey = 'airmius.push.registration.v1';

  final AirmiusPreferencesStore store;
  final AirmiusPushTokenProvider tokenProvider;
  final String locale;
  final String? appVersion;
  final String? buildNumber;
  final String Function() _deviceIdFactory;

  Future<bool> optInEnabled() async {
    return (await store.readString(optInStorageKey)) == 'true';
  }

  /// Remembers push consent after the system notification permission was
  /// granted. The authenticated session callback performs the API
  /// registration once a valid access token is available.
  Future<bool> enableIfPermissionGranted() async {
    try {
      final token = await tokenProvider.currentToken();
      if (token == null || token.token.trim().isEmpty) return false;

      await store.writeString(optInStorageKey, 'true');
      return true;
    } catch (_) {
      // Push setup must never block login or permission onboarding.
      return false;
    }
  }

  Future<AirmiusPushDeviceRegistration?> lastRegistration() async {
    final raw = await store.readString(registrationStorageKey);
    if (raw == null || raw.trim().isEmpty) return null;

    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map) {
        return AirmiusPushDeviceRegistration.fromJson(decoded);
      }
    } catch (_) {
      return null;
    }
    return null;
  }

  Future<AirmiusPushRegistrationResult> setOptIn({
    required bool enabled,
    required AirmiusApiClient client,
  }) async {
    await store.writeString(optInStorageKey, enabled ? 'true' : 'false');
    if (!enabled) {
      return unregister(client, clearOptIn: false);
    }
    return registerOrRefresh(client, userInitiated: true);
  }

  Future<AirmiusPushRegistrationResult> registerIfOptedIn(
    AirmiusApiClient client,
  ) async {
    final storedOptIn = await store.readString(optInStorageKey);
    if (storedOptIn == 'false') {
      return const AirmiusPushRegistrationResult(status: 'disabled');
    }

    // Older installations may already have granted the Android notification
    // permission but have no push preference stored yet. Reuse that consent
    // and register the device instead of requiring a second settings action.
    if (storedOptIn == null || storedOptIn.trim().isEmpty) {
      if (!await enableIfPermissionGranted()) {
        return const AirmiusPushRegistrationResult(
          status: 'missing_token',
          message: 'Push-Token ist noch nicht verfügbar.',
        );
      }
    }

    return registerOrRefresh(client);
  }

  Future<AirmiusPushRegistrationResult> registerOrRefresh(
    AirmiusApiClient client, {
    bool userInitiated = false,
  }) async {
    final pushToken = userInitiated
        ? await tokenProvider.requestToken()
        : await tokenProvider.currentToken();
    final token = pushToken?.token.trim() ?? '';
    if (token.isEmpty) {
      return const AirmiusPushRegistrationResult(
        status: 'missing_token',
        message: 'Push-Token ist noch nicht verfügbar.',
      );
    }

    final deviceId = await _deviceId();
    final platform = _platform();
    final provider = pushToken?.provider?.trim().isNotEmpty == true
        ? pushToken!.provider!.trim()
        : _providerFor(platform);

    final response = await client.registerPushDevice({
      'device_id': deviceId,
      'platform': platform,
      'provider': provider,
      'token': token,
      'device_name': 'AIRMIUS ${platform.toUpperCase()}',
      if (appVersion != null) 'app_version': appVersion,
      if (buildNumber != null) 'build_number': buildNumber,
      'locale': locale,
      'timezone': DateTime.now().timeZoneName,
      'channels': defaultChannels,
      'permissions': {'notifications': true},
    });

    final data = response['data'];
    final device = data is Map ? data['device'] : null;
    final status = data is Map ? data['status']?.toString() : null;
    final registration = AirmiusPushDeviceRegistration.fromJson({
      if (device is Map) ...device,
      'device_id': deviceId,
      'platform': platform,
      'provider': provider,
      'status': status ?? 'registered',
      'registered_at': DateTime.now().toIso8601String(),
    });

    await store.writeString(
      registrationStorageKey,
      jsonEncode(registration.toJson()),
    );
    return AirmiusPushRegistrationResult(
      status: registration.status ?? 'registered',
      registration: registration,
    );
  }

  Future<AirmiusPushRegistrationResult> unregister(
    AirmiusApiClient client, {
    bool clearOptIn = true,
  }) async {
    if (clearOptIn) await store.writeString(optInStorageKey, 'false');

    final deviceId = await _storedDeviceId();
    if (deviceId == null) {
      await store.writeString(registrationStorageKey, '');
      return const AirmiusPushRegistrationResult(status: 'disabled');
    }

    try {
      await client.unregisterPushDevice(deviceId);
    } on AirmiusApiException catch (error) {
      if (error.statusCode != 404) rethrow;
    }

    await store.writeString(registrationStorageKey, '');
    return const AirmiusPushRegistrationResult(status: 'disabled');
  }

  Future<String> _deviceId() async {
    final existing = await _storedDeviceId();
    if (existing != null) return existing;

    final created = _deviceIdFactory();
    await store.writeString(deviceIdStorageKey, created);
    return created;
  }

  Future<String?> _storedDeviceId() async {
    final existing = await store.readString(deviceIdStorageKey);
    final trimmed = existing?.trim();
    return trimmed == null || trimmed.isEmpty ? null : trimmed;
  }

  static String _platform() {
    if (kIsWeb) return 'web';
    return switch (defaultTargetPlatform) {
      TargetPlatform.iOS => 'ios',
      TargetPlatform.android => 'android',
      _ => 'unknown',
    };
  }

  static String _providerFor(String platform) {
    if (platform == 'web') return 'web';
    return 'fcm';
  }

  static String _defaultDeviceId() {
    final random = Random();
    final stamp = DateTime.now().microsecondsSinceEpoch;
    final suffix = random.nextInt(1 << 32).toRadixString(16).padLeft(8, '0');
    return 'airmius-$stamp-$suffix';
  }
}
