import 'dart:convert';

import 'package:flutter/material.dart';

import 'airmius_l10n.dart';
import 'airmius_accessibility_scope.dart';
import 'airmius_preferences_store.dart';
import 'airmius_theme.dart';

class AirmiusPreferences {
  AirmiusPreferences({AirmiusPreferencesStore? store})
    : _store = store ?? createAirmiusPreferencesStore();

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

  Future<AirmiusThemePalette?> readThemePalette() async {
    final value = await _store.readString(_themePaletteKey);
    if (value == null || value.isEmpty) {
      return null;
    }
    return airmiusThemePaletteFromKey(value);
  }

  Future<void> writeThemePalette(AirmiusThemePalette palette) {
    return _store.writeString(_themePaletteKey, palette.key);
  }

  Future<AirmiusTextSize> readTextSize() async {
    return airmiusTextSizeFromKey(await _store.readString(_textSizeKey));
  }

  Future<void> writeTextSize(AirmiusTextSize textSize) {
    return _store.writeString(_textSizeKey, textSize.key);
  }

  Future<bool> readPermissionOnboardingComplete() async {
    return await _store.readString(_permissionOnboardingCompleteKey) == 'true';
  }

  Future<void> writePermissionOnboardingComplete(bool complete) {
    return _store.writeString(
      _permissionOnboardingCompleteKey,
      complete ? 'true' : 'false',
    );
  }

  Future<void> writeOnboardingProfile({
    required String role,
    required String workspace,
    required Map<String, bool> permissions,
  }) {
    return _store.writeString(
      _onboardingProfileKey,
      jsonEncode({
        'role': role,
        'workspace': workspace,
        'permissions': permissions,
        'completedAt': DateTime.now().toUtc().toIso8601String(),
      }),
    );
  }

  Future<Map<String, dynamic>?> readOnboardingProfile() async {
    final raw = await _store.readString(_onboardingProfileKey);
    if (raw == null || raw.isEmpty) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      return decoded.map((key, value) => MapEntry('$key', value));
    } catch (_) {
      return null;
    }
  }

  static const _languageKey = 'airmius.language';
  static const _themeModeKey = 'airmius.themeMode';
  static const _themePaletteKey = 'airmius.themePalette';
  static const _textSizeKey = 'airmius.textSize';
  static const _permissionOnboardingCompleteKey =
      'airmius.permissionOnboardingComplete';
  static const _onboardingProfileKey = 'airmius.onboardingProfile';
}
