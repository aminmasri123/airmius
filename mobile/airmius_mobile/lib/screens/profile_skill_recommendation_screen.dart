import 'package:flutter/material.dart';

import 'sports_center_screen.dart';

/// Compatibility route for legacy skill/recommendation links.
class ProfileSkillRecommendationScreen extends StatelessWidget {
  const ProfileSkillRecommendationScreen({
    super.key,
    this.person,
    required this.skill,
    required this.status,
  });

  final String? person;
  final String skill;
  final String status;

  @override
  Widget build(BuildContext context) => const SportsCenterScreen();
}
