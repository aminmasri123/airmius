import 'dart:convert';
import 'dart:io';

import 'airmius_preferences_store_base.dart';

AirmiusPreferencesStore createPlatformPreferencesStore() {
  return AirmiusFilePreferencesStore();
}

class AirmiusFilePreferencesStore implements AirmiusPreferencesStore {
  AirmiusFilePreferencesStore();

  File get _file {
    final basePath = Platform.environment['APPDATA'] ?? Platform.environment['HOME'] ?? Directory.systemTemp.path;
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
