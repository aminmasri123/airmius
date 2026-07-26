import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import 'training_event_detail_screen.dart';

/// Compatibility entry point for older navigation code.
///
/// Event administration now uses the same permission-aware, API-backed detail
/// surface as the regular event flow, so no demo state can diverge from the
/// server.
class EventAdminDetailScreen extends StatelessWidget {
  const EventAdminDetailScreen({
    super.key,
    required this.event,
    this.fallbackBody = '',
  });

  final AirmiusEvent event;
  final String fallbackBody;

  @override
  Widget build(BuildContext context) =>
      TrainingEventDetailScreen(event: event, fallbackBody: fallbackBody);
}
