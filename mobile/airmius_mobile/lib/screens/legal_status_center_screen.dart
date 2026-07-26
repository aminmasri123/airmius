import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'privacy_consent_center_screen.dart';
import 'support_helpdesk_screen.dart';

class LegalStatusCenterScreen extends StatelessWidget {
  const LegalStatusCenterScreen({super.key});

  static const _legalDocuments = <_PublicDocument>[
    _PublicDocument(
      titleKey: 'legalHub.imprint',
      bodyKey: 'legalHub.imprintBody',
      path: '/impressum',
      icon: Icons.business_outlined,
    ),
    _PublicDocument(
      titleKey: 'legalHub.privacy',
      bodyKey: 'legalHub.privacyBody',
      path: '/datenschutz',
      icon: Icons.privacy_tip_outlined,
    ),
    _PublicDocument(
      titleKey: 'legalHub.terms',
      bodyKey: 'legalHub.termsBody',
      path: '/agb',
      icon: Icons.article_outlined,
    ),
    _PublicDocument(
      titleKey: 'legalHub.withdrawal',
      bodyKey: 'legalHub.withdrawalBody',
      path: '/widerruf',
      icon: Icons.assignment_return_outlined,
    ),
    _PublicDocument(
      titleKey: 'legalHub.cookies',
      bodyKey: 'legalHub.cookiesBody',
      path: '/cookies',
      icon: Icons.cookie_outlined,
    ),
    _PublicDocument(
      titleKey: 'legalHub.community',
      bodyKey: 'legalHub.communityBody',
      path: '/community-richtlinien',
      icon: Icons.groups_outlined,
    ),
    _PublicDocument(
      titleKey: 'legalHub.minors',
      bodyKey: 'legalHub.minorsBody',
      path: '/jugendschutz',
      icon: Icons.family_restroom_outlined,
    ),
    _PublicDocument(
      titleKey: 'legalHub.reporting',
      bodyKey: 'legalHub.reportingBody',
      path: '/kontakt-und-melden',
      icon: Icons.report_outlined,
    ),
  ];

  static const _publicPages = <_PublicDocument>[
    _PublicDocument(
      titleKey: 'legalHub.pricing',
      bodyKey: 'legalHub.pricingBody',
      path: '/abos',
      icon: Icons.sell_outlined,
    ),
    _PublicDocument(
      titleKey: 'legalHub.jobs',
      bodyKey: 'legalHub.jobsBody',
      path: '/jobs',
      icon: Icons.work_outline,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final accent = Theme.of(context).colorScheme.primary;
    final text =
        Theme.of(context).textTheme.bodyLarge?.color ?? AirmiusColors.text;
    final muted =
        Theme.of(context).textTheme.bodyMedium?.color ?? AirmiusColors.muted;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('legalHub.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t('legalHub.title'),
        subtitle: t('legalHub.subtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('legalHub.eyebrow')),
                  const SizedBox(height: 8),
                  Text(
                    t('legalHub.headline'),
                    style: TextStyle(
                      color: text,
                      fontSize: 23,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    t('legalHub.body'),
                    style: TextStyle(color: muted, height: 1.4),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(
                        label: t('legalHub.privacySettings'),
                        icon: Icons.tune_outlined,
                        onPressed: () => Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => const PrivacyConsentCenterScreen(),
                          ),
                        ),
                      ),
                      AirmiusButton(
                        label: t('legalHub.support'),
                        icon: Icons.support_agent_outlined,
                        secondary: true,
                        onPressed: () => Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => const SupportHelpdeskScreen(),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _DocumentSection(
              title: t('legalHub.legalDocuments'),
              documents: _legalDocuments,
              onOpen: (document) => _openDocument(context, document),
            ),
            const SizedBox(height: 14),
            _DocumentSection(
              title: t('legalHub.publicInformation'),
              documents: _publicPages,
              onOpen: (document) => _openDocument(context, document),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.open_in_new_outlined, color: accent),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      t('legalHub.externalNote'),
                      style: TextStyle(color: muted, height: 1.4),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openDocument(
    BuildContext context,
    _PublicDocument document,
  ) async {
    final origin = AirmiusServicesScope.of(
      context,
    ).environment.apiBaseUrl.trim();
    final uri = legalPublicUri(origin, document.path);
    if (uri == null) {
      _showError(context);
      return;
    }

    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && context.mounted) _showError(context);
  }

  void _showError(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(AirmiusScope.of(context).t('legalHub.openError'))),
    );
  }
}

@visibleForTesting
Uri? legalPublicUri(String configuredOrigin, String path) {
  final base = safeExternalHttpUrl(configuredOrigin, httpsOnly: false);
  if (base == null || !path.startsWith('/') || path.startsWith('//')) {
    return null;
  }
  return base.replace(path: path, query: null, fragment: null);
}

class _DocumentSection extends StatelessWidget {
  const _DocumentSection({
    required this.title,
    required this.documents,
    required this.onOpen,
  });

  final String title;
  final List<_PublicDocument> documents;
  final ValueChanged<_PublicDocument> onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      child: Column(
        children: [
          for (final document in documents)
            _DocumentTile(document: document, onTap: () => onOpen(document)),
        ],
      ),
    );
  }
}

class _DocumentTile extends StatelessWidget {
  const _DocumentTile({required this.document, required this.onTap});

  final _PublicDocument document;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final accent = Theme.of(context).colorScheme.primary;
    final text =
        Theme.of(context).textTheme.bodyLarge?.color ?? AirmiusColors.text;
    final muted =
        Theme.of(context).textTheme.bodyMedium?.color ?? AirmiusColors.muted;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(document.icon, color: accent),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      t(document.titleKey),
                      style: TextStyle(
                        color: text,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      t(document.bodyKey),
                      style: TextStyle(color: muted, height: 1.35),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Icon(Icons.open_in_new_outlined, color: muted, size: 20),
            ],
          ),
        ),
      ),
    );
  }
}

class _PublicDocument {
  const _PublicDocument({
    required this.titleKey,
    required this.bodyKey,
    required this.path,
    required this.icon,
  });

  final String titleKey;
  final String bodyKey;
  final String path;
  final IconData icon;
}
