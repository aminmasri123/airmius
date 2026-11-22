import 'package:flutter/material.dart';

import 'club_profile_editor_screen.dart';

/// Compatibility entry point for club visibility settings.
class ClubVisibilitySettingsScreen extends StatelessWidget {
  const ClubVisibilitySettingsScreen({
    super.key,
    this.initialTab = 'Public',
    this.initialClubId,
  });

  final String initialTab;
  final int? initialClubId;

  @override
  Widget build(BuildContext context) =>
      ClubProfileEditorScreen(initialClubId: initialClubId);
}
