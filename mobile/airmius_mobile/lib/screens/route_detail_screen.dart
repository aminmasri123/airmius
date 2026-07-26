import 'package:flutter/material.dart';

import 'sport_map_center_screen.dart';

/// Compatibility route for legacy map-detail links.
class RouteDetailScreen extends StatelessWidget {
  const RouteDetailScreen({
    super.key,
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
    this.mode = 'route',
  });

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String mode;

  @override
  Widget build(BuildContext context) => const SportMapCenterScreen();
}
