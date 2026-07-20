// ignore_for_file: deprecated_member_use, avoid_web_libraries_in_flutter

import 'dart:html' as html;

import 'airmius_preferences_store_base.dart';

AirmiusPreferencesStore createPlatformPreferencesStore() {
  return const AirmiusWebPreferencesStore();
}

class AirmiusWebPreferencesStore implements AirmiusPreferencesStore {
  const AirmiusWebPreferencesStore();

  @override
  Future<String?> readString(String key) async => html.window.localStorage[key];

  @override
  Future<void> writeString(String key, String value) async {
    html.window.localStorage[key] = value;
  }
}
