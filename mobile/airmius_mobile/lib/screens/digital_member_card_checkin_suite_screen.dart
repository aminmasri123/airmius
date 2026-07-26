import 'package:flutter/material.dart';

import 'member_card_screen.dart';

/// Compatibility entry point for older deep links and the developer suite.
/// The former static preview now opens the real API-backed member card flow.
class DigitalMemberCardCheckinSuiteScreen extends StatelessWidget {
  const DigitalMemberCardCheckinSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) => const MemberCardScreen();
}
