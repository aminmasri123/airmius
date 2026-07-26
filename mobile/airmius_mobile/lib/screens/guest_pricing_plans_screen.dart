import 'package:flutter/material.dart';

import 'guest_portal_screen.dart';

/// Compatibility route for legacy public pricing links.
///
/// Prices are account-, country- and server-dependent. The former local
/// pricing cards were static demo data, so visitors now enter the guest-safe
/// portal, which links to current public offers and contact flows.
class GuestPricingPlansScreen extends StatelessWidget {
  const GuestPricingPlansScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const GuestPortalScreen();
  }
}
