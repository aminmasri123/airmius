import 'package:flutter/widgets.dart';

enum AirmiusTextSize {
  normal(1.0),
  large(1.15),
  extraLarge(1.3),
  veryLarge(1.5);

  const AirmiusTextSize(this.scale);

  final double scale;

  String get key => name;
}

AirmiusTextSize airmiusTextSizeFromKey(String? key) {
  return AirmiusTextSize.values.firstWhere(
    (size) => size.key == key,
    orElse: () => AirmiusTextSize.normal,
  );
}

class AirmiusAccessibilityScope extends InheritedWidget {
  const AirmiusAccessibilityScope({
    super.key,
    required this.textSize,
    required this.setTextSize,
    required super.child,
  });

  final AirmiusTextSize textSize;
  final ValueChanged<AirmiusTextSize> setTextSize;

  static AirmiusAccessibilityScope of(BuildContext context) {
    final scope = context
        .dependOnInheritedWidgetOfExactType<AirmiusAccessibilityScope>();
    if (scope == null) {
      throw StateError('AirmiusAccessibilityScope fehlt im Widget-Baum.');
    }
    return scope;
  }

  @override
  bool updateShouldNotify(AirmiusAccessibilityScope oldWidget) {
    return textSize != oldWidget.textSize;
  }
}
