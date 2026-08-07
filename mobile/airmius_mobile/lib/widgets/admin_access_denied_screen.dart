import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import 'airmius_widgets.dart';

/// Fallback for direct links to protected administration screens.
///
/// Navigation visibility is not a security boundary, so protected screens
/// use this guard before loading any administration data.
class AdminAccessDeniedScreen extends StatelessWidget {
  const AdminAccessDeniedScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('adminHub.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t('adminHub.title'),
        subtitle: t('adminHub.forbidden'),
        child: AirmiusPanel(
          gradient: true,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.lock_outline, size: 56),
              const SizedBox(height: 16),
              Text(t('adminHub.forbiddenBody'), textAlign: TextAlign.center),
            ],
          ),
        ),
      ),
    );
  }
}
