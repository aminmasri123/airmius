import 'package:flutter/material.dart';

import 'legal_status_center_screen.dart';

/// Compatibility entry point for legacy legal-document links.
///
/// Legal documents now open through the API-safe public legal hub, where
/// official URLs, privacy settings and support are available without local
/// placeholder actions.
class LegalDocumentScreen extends StatelessWidget {
  const LegalDocumentScreen({super.key, this.initialDocument = 'Datenschutz'});

  final String initialDocument;

  @override
  Widget build(BuildContext context) => const LegalStatusCenterScreen();
}
