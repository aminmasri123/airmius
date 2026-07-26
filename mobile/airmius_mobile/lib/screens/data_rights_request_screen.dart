import 'package:flutter/material.dart';

import 'privacy_consent_center_screen.dart';

/// Backwards-compatible entry point for the data-rights route.
///
/// The old screen only displayed a form and a local snackbar. The privacy
/// center owns the protected export, correction and consent contracts, so all
/// callers now land on that single audited flow.
class DataRightsRequestScreen extends StatelessWidget {
  const DataRightsRequestScreen({super.key});

  @override
  Widget build(BuildContext context) => const PrivacyConsentCenterScreen();
}
