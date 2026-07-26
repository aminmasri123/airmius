import 'package:flutter/material.dart';

import 'club_profile_editor_screen.dart';

/// Compatibility entry point for club visibility settings.
class ClubVisibilitySettingsScreen extends StatelessWidget {
  const ClubVisibilitySettingsScreen({super.key, this.initialTab = 'Public'});

  final String initialTab;

  @override
  Widget build(BuildContext context) => const ClubProfileEditorScreen();
}
