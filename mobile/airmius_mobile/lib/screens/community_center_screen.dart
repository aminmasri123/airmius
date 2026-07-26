import 'package:flutter/material.dart';

import 'friends_social_graph_screen.dart';

/// Compatibility entry point kept for dashboard/deep-link callers.
///
/// The former page contained local sample contacts and fake invite actions.
/// The canonical friends center owns the API-backed data and actions now, so
/// every entry point lands on the same truthful surface.
class CommunityCenterScreen extends StatelessWidget {
  const CommunityCenterScreen({super.key});

  @override
  Widget build(BuildContext context) => const FriendsSocialGraphScreen();
}
