import 'package:flutter/material.dart';

import 'airmius_l10n.dart';
import 'airmius_preferences_store.dart';

class AirmiusPreferences {
  AirmiusPreferences({
    AirmiusPreferencesStore? store,
  }) : _store = store ?? createAirmiusPreferencesStore();

  final AirmiusPreferencesStore _store;

  Future<AirmiusLanguage?> readLanguage() async {
    final value = await _store.readString(_languageKey);
    for (final language in AirmiusLanguage.values) {
      if (language.code.toLowerCase() == value) {
        return language;
      }
    }
    return null;
  }

  Future<void> writeLanguage(AirmiusLanguage language) {
    return _store.writeString(_languageKey, language.code.toLowerCase());
  }

  Future<ThemeMode?> readThemeMode() async {
    final value = await _store.readString(_themeModeKey);
    return switch (value) {
      'light' => ThemeMode.light,
      'dark' => ThemeMode.dark,
      'system' => ThemeMode.system,
      _ => null,
    };
  }

  Future<void> writeThemeMode(ThemeMode mode) {
    final value = switch (mode) {
      ThemeMode.light => 'light',
      ThemeMode.dark => 'dark',
      ThemeMode.system => 'system',
    };
    return _store.writeString(_themeModeKey, value);
  }

  static const _languageKey = 'airmius.language';
  static const _themeModeKey = 'airmius.themeMode';
}
