import 'dart:convert';

import 'package:flutter/material.dart';

import 'airmius_l10n.dart';
import 'airmius_accessibility_scope.dart';
import 'airmius_preferences_store.dart';
import 'airmius_theme.dart';
import '../models/footer_navigation_destination.dart';

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

  Future<List<FooterNavigationDestination>?> readFooterNavigation(
    int userId,
  ) async {
    final raw = await _store.readString(_footerNavigationKey(userId));
    if (raw == null || raw.isEmpty) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return null;
      final destinations = decoded
          .whereType<String>()
          .map(FooterNavigationDestination.fromStorageKey)
          .whereType<FooterNavigationDestination>()
          .toList(growable: false);
      if (destinations.length < 3 ||
          destinations.length > 5 ||
          destinations.toSet().length != destinations.length) {
        return null;
      }
      return destinations;
    } catch (_) {
      return null;
    }
  }

  Future<void> writeFooterNavigation(
    int userId,
    List<FooterNavigationDestination> destinations,
  ) {
    if (destinations.length < 3 || destinations.length > 5) {
      throw ArgumentError.value(
        destinations.length,
        'destinations',
        'Footer navigation must contain between 3 and 5 items.',
      );
    }
    if (destinations.toSet().length != destinations.length) {
      throw ArgumentError.value(
        destinations,
        'destinations',
        'Footer navigation items must be unique.',
      );
    }
    return _store.writeString(
      _footerNavigationKey(userId),
      jsonEncode(
        destinations.map((destination) => destination.storageKey).toList(),
      ),
    );
  }

  Future<String?> readClubStartFocus(int userId, int clubId) async {
    final value = await _store.readString(_clubStartFocusKey(userId, clubId));
    return const {'members', 'single_team', 'multiple_teams'}.contains(value)
        ? value
        : value == 'teams'
        ? 'single_team'
        : null;
  }

  Future<void> writeClubStartFocus(int userId, int clubId, String focus) {
    if (!const {'members', 'single_team', 'multiple_teams'}.contains(focus)) {
      throw ArgumentError.value(focus, 'focus');
    }
    return _store.writeString(_clubStartFocusKey(userId, clubId), focus);
  }

  Future<List<String>?> readClubQuickActions(int userId, int clubId) async {
    final raw = await _store.readString(_clubQuickActionsKey(userId, clubId));
    if (raw == null || raw.isEmpty) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return null;
      final actions = decoded
          .whereType<String>()
          .where(_clubActionIds.contains)
          .toSet()
          .toList();
      return actions.isNotEmpty && actions.length <= 5 ? actions : null;
    } catch (_) {
      return null;
    }
  }

  Future<void> writeClubQuickActions(
    int userId,
    int clubId,
    List<String> actions,
  ) {
    if (actions.isEmpty ||
        actions.length > 5 ||
        actions.toSet().length != actions.length ||
        !actions.every(_clubActionIds.contains)) {
      throw ArgumentError.value(actions, 'actions');
    }
    return _store.writeString(
      _clubQuickActionsKey(userId, clubId),
      jsonEncode(actions),
    );
  }

  static const _languageKey = 'airmius.language';
  static const _themeModeKey = 'airmius.themeMode';
  static const _themePaletteKey = 'airmius.themePalette';
  static const _textSizeKey = 'airmius.textSize';
  static const _permissionOnboardingCompleteKey =
      'airmius.permissionOnboardingComplete';
  static const _onboardingProfileKey = 'airmius.onboardingProfile';
  static String _footerNavigationKey(int userId) =>
      'airmius.footerNavigation.v1.$userId';
  static String _clubStartFocusKey(int userId, int clubId) =>
      'airmius.clubStartFocus.v1.$userId.$clubId';
  static const _clubActionIds = {
    'addMember',
    'calendar',
    'todos',
    'members',
    'teams',
    'events',
    'announcements',
    'finance',
    'documents',
  };
  static String _clubQuickActionsKey(int userId, int clubId) =>
      'airmius.clubQuickActions.v1.$userId.$clubId';
}
