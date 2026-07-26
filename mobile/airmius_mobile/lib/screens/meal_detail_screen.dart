import 'package:flutter/material.dart';

import 'nutrition_center_screen.dart';

/// Compatibility route for legacy meal links.
class MealDetailScreen extends StatelessWidget {
  const MealDetailScreen({
    super.key,
    required this.title,
    required this.body,
    required this.kcal,
    required this.icon,
    this.mode = 'meal',
  });

  final String title;
  final String body;
  final String kcal;
  final IconData icon;
  final String mode;

  @override
  Widget build(BuildContext context) => const NutritionCenterScreen();
}
