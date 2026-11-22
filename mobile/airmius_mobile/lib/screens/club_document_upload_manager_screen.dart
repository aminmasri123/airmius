import 'package:flutter/material.dart';

import 'file_manager_screen.dart';

/// Compatibility entry point for club document uploads.
class ClubDocumentUploadManagerScreen extends StatelessWidget {
  const ClubDocumentUploadManagerScreen({super.key, this.initialClubId});

  final int? initialClubId;

  @override
  Widget build(BuildContext context) => FileManagerScreen(
    initialScope: initialClubId == null ? 'mine' : 'club',
    initialClubId: initialClubId,
  );
}
