import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../widgets/airmius_widgets.dart';
import 'file_manager_screen.dart';
import 'guardian_center_screen.dart';
import 'privacy_consent_center_screen.dart';
import 'support_helpdesk_screen.dart';

/// Single, action-oriented entry point for media safety.
///
/// Policy decisions are enforced by the API and the destination screens. This
/// page only explains the rules and routes people to the real flows, so it
/// never presents fabricated moderation counts or a fake save action.
class MediaGuidelinesScreen extends StatelessWidget {
  const MediaGuidelinesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('mediaPolicy.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t('mediaPolicy.title'),
        subtitle: t('mediaPolicy.subtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.verified_user_outlined,
                    color: Theme.of(context).colorScheme.primary,
                    size: 32,
                  ),
                  const SizedBox(width: 12),
                  Expanded(child: Text(t('mediaPolicy.intro'))),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _PolicyCard(
              icon: Icons.folder_outlined,
              title: t('mediaPolicy.upload'),
              body: t('mediaPolicy.uploadHint'),
              action: t('mediaPolicy.openFiles'),
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => const FileManagerScreen(),
                ),
              ),
            ),
            const SizedBox(height: 10),
            _PolicyCard(
              icon: Icons.privacy_tip_outlined,
              title: t('mediaPolicy.consent'),
              body: t('mediaPolicy.consentHint'),
              action: t('mediaPolicy.openPrivacy'),
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => const PrivacyConsentCenterScreen(),
                ),
              ),
            ),
            const SizedBox(height: 10),
            _PolicyCard(
              icon: Icons.family_restroom_outlined,
              title: t('mediaPolicy.guardian'),
              body: t('mediaPolicy.guardianHint'),
              action: t('mediaPolicy.openGuardian'),
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => const GuardianCenterScreen(),
                ),
              ),
            ),
            const SizedBox(height: 10),
            _PolicyCard(
              icon: Icons.support_agent_outlined,
              title: t('mediaPolicy.report'),
              body: t('mediaPolicy.reportHint'),
              action: t('mediaPolicy.openSupport'),
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => const SupportHelpdeskScreen(),
                ),
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: Theme.of(
                context,
              ).colorScheme.secondary.withValues(alpha: .45),
              child: Row(
                children: [
                  Icon(
                    Icons.lock_outline,
                    color: Theme.of(context).colorScheme.secondary,
                  ),
                  const SizedBox(width: 10),
                  Expanded(child: Text(t('mediaPolicy.serverRule'))),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PolicyCard extends StatelessWidget {
  const _PolicyCard({
    required this.icon,
    required this.title,
    required this.body,
    required this.action,
    required this.onPressed,
  });

  final IconData icon;
  final String title;
  final String body;
  final String action;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, color: theme.colorScheme.primary, size: 28),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(body),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: AirmiusButton(
              label: action,
              icon: Icons.arrow_forward_outlined,
              onPressed: onPressed,
              secondary: true,
            ),
          ),
        ],
      ),
    );
  }
}
