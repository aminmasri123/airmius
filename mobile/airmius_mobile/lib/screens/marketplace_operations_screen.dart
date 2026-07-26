import 'package:flutter/material.dart';

import 'marketplace_screen.dart';

/// Legacy entry point for shop, cart and order operations.
class MarketplaceOperationsScreen extends StatelessWidget {
  const MarketplaceOperationsScreen({super.key, this.initialTab = 'Shop'});

  final String initialTab;

  @override
  Widget build(BuildContext context) => const MarketplaceScreen();
}
