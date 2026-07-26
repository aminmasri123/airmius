import 'package:flutter/material.dart';

import 'carpool_center_screen.dart';

/// Compatibility route for legacy ride-detail links.
class CarpoolDetailScreen extends StatelessWidget {
  const CarpoolDetailScreen({
    super.key,
    required this.title,
    required this.status,
  });

  final String title;
  final String status;

  @override
  Widget build(BuildContext context) => const CarpoolCenterScreen();
}
