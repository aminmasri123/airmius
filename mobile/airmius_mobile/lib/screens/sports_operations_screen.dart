import 'package:flutter/material.dart';

import 'sports_center_screen.dart';

/// Legacy entry point for sport-profile operations.
class SportsOperationsScreen extends StatelessWidget {
  const SportsOperationsScreen({super.key, this.initialTab = 'Profil'});

  final String initialTab;

  @override
  Widget build(BuildContext context) => const SportsCenterScreen();
}
