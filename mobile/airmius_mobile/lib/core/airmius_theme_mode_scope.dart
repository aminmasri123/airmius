import 'package:flutter/material.dart';

import 'airmius_theme.dart';

class AirmiusThemeModeScope extends InheritedWidget {
  const AirmiusThemeModeScope({
    super.key,
    required this.mode,
    required this.setMode,
    required this.palette,
    required this.setPalette,
    required super.child,
  });

  final ThemeMode mode;
  final ValueChanged<ThemeMode> setMode;
  final AirmiusThemePalette palette;
  final ValueChanged<AirmiusThemePalette> setPalette;

  static AirmiusThemeModeScope of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<AirmiusThemeModeScope>();
    if (scope == null) {
      throw StateError('AirmiusThemeModeScope fehlt im Widget-Baum.');
    }
    return scope;
  }

  @override
  bool updateShouldNotify(AirmiusThemeModeScope oldWidget) {
    return mode != oldWidget.mode || palette != oldWidget.palette;
  }
}
