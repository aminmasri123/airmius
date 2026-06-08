import 'package:flutter/widgets.dart';

import 'airmius_service_container.dart';

class AirmiusServicesScope extends InheritedWidget {
  const AirmiusServicesScope({
    super.key,
    required this.container,
    required super.child,
  });

  final AirmiusServiceContainer container;

  static AirmiusServiceContainer of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<AirmiusServicesScope>();
    assert(scope != null, 'AirmiusServicesScope is missing');
    return scope!.container;
  }

  @override
  bool updateShouldNotify(AirmiusServicesScope oldWidget) => container != oldWidget.container;
}
