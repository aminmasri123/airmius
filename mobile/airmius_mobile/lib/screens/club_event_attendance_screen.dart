import 'package:flutter/material.dart';

import 'training_center_screen.dart';

/// Compatibility route for legacy club-report and carpool links.
///
/// Events, attendance, waitlists and reminders are handled by the API-backed
/// [TrainingCenterScreen]. Keeping this constructor preserves old deep links
/// without exposing the former static operations mock-up.
class ClubEventAttendanceScreen extends StatelessWidget {
  const ClubEventAttendanceScreen({super.key, this.initialTab = 'Alle'});

  final String initialTab;

  @override
  Widget build(BuildContext context) => const TrainingCenterScreen();
}
