import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'airmius_preferences_store_base.dart';

AirmiusPreferencesStore createPlatformPreferencesStore() {
  if (defaultTargetPlatform == TargetPlatform.android ||
      defaultTargetPlatform == TargetPlatform.iOS) {
    return AirmiusSecurePreferencesStore();
  }
  return AirmiusFilePreferencesStore();
}

/// Keeps mobile preferences in the platform's persistent encrypted storage.
///
/// The previous implementation used [Directory.systemTemp] when Android did
/// not expose a HOME directory. That points at the app cache, which Android may
/// clear during maintenance and caused completed onboarding to reappear after
/// an update. Reads fall back to that legacy file once and migrate every value
/// so existing installations keep their language, theme and onboarding state.
class AirmiusSecurePreferencesStore implements AirmiusPreferencesStore {
  AirmiusSecurePreferencesStore({
    FlutterSecureStorage? storage,
    AirmiusSecurePreferencesBackend? backend,
    AirmiusPreferencesStore? legacyStore,
  }) : _backend =
           backend ??
           AirmiusFlutterSecurePreferencesBackend(
             storage ??
                 FlutterSecureStorage(
                   aOptions: AndroidOptions(migrateWithBackup: true),
                 ),
           ),
       _legacyStore = legacyStore ?? AirmiusFilePreferencesStore();

  final AirmiusSecurePreferencesBackend _backend;
  final AirmiusPreferencesStore _legacyStore;

  @override
  Future<String?> readString(String key) async {
    try {
      final persistentValue = await _backend.read(key);
      if (persistentValue != null) return persistentValue;

      final legacyValue = await _legacyStore.readString(key);
      if (legacyValue != null) {
        await _backend.write(key, legacyValue);
      }
      return legacyValue;
    } catch (_) {
      return _legacyStore.readString(key);
    }
  }

  @override
  Future<void> writeString(String key, String value) async {
    try {
      await _backend.write(key, value);
    } catch (_) {
      await _legacyStore.writeString(key, value);
    }
  }
}

abstract class AirmiusSecurePreferencesBackend {
  Future<String?> read(String key);

  Future<void> write(String key, String value);
}

class AirmiusFlutterSecurePreferencesBackend
    implements AirmiusSecurePreferencesBackend {
  const AirmiusFlutterSecurePreferencesBackend(this.storage);

  final FlutterSecureStorage storage;

  @override
  Future<String?> read(String key) => storage.read(key: key);

  @override
  Future<void> write(String key, String value) =>
      storage.write(key: key, value: value);
}

class AirmiusFilePreferencesStore implements AirmiusPreferencesStore {
  AirmiusFilePreferencesStore();

  File get _file {
    final basePath =
        Platform.environment['APPDATA'] ??
        Platform.environment['HOME'] ??
        Directory.systemTemp.path;
    return File('$basePath/.airmius_mobile_preferences.json');
  }

  @override
  Future<String?> readString(String key) async {
    final values = await _readValues();
    return values[key];
  }

  @override
  Future<void> writeString(String key, String value) async {
    final values = await _readValues();
    values[key] = value;
    await _file.writeAsString(jsonEncode(values), flush: true);
  }

  Future<Map<String, String>> _readValues() async {
    try {
      final file = _file;
      if (!await file.exists()) {
        return {};
      }
      final decoded = jsonDecode(await file.readAsString());
      if (decoded is! Map) {
        return {};
      }
      return decoded.map((key, value) => MapEntry('$key', '$value'));
    } catch (_) {
      return {};
    }
  }
}
