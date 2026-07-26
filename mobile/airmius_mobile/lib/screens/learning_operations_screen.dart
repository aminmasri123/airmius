import 'package:flutter/material.dart';

import 'learning_screen.dart';

/// Legacy entry point for learning operations.
class LearningOperationsScreen extends StatelessWidget {
  const LearningOperationsScreen({super.key, this.initialTab = 'Kurse'});

  final String initialTab;

  @override
  Widget build(BuildContext context) => const LearningScreen();
}
