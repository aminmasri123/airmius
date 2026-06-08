import 'package:flutter/material.dart';

class AirmiusThemeModeScope extends InheritedWidget {
  const AirmiusThemeModeScope({
    super.key,
    required this.mode,
    required this.setMode,
    required super.child,
  });

  final ThemeMode mode;
  final ValueChanged<ThemeMode> setMode;

  static AirmiusThemeModeScope of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<AirmiusThemeModeScope>();
    if (scope == null) {
      throw StateError('AirmiusThemeModeScope fehlt im Widget-Baum.');
    }
    return scope;
  }

  @override
  bool updateShouldNotify(AirmiusThemeModeScope oldWidget) {
    return mode != oldWidget.mode;
  }
}
