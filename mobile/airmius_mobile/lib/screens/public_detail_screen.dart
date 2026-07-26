import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'certificate_verification_screen.dart';
import 'legal_document_screen.dart';
import 'public_blog_reader_screen.dart';
import 'public_interest_screen.dart';
import 'public_top_content_screen.dart';

/// Localized compatibility page for older public-detail links.
///
/// The former screen contained static roadmap copy. It now communicates the
/// public scope clearly and routes visitors into real guest-safe screens.
class PublicDetailScreen extends StatelessWidget {
  const PublicDetailScreen({
    super.key,
    required this.title,
    required this.body,
    required this.icon,
    required this.kind,
  });

  final String title;
  final String body;
  final IconData icon;
  final String kind;

  bool get _isBlog => icon == Icons.article_outlined;
  bool get _isMarketplace => icon == Icons.storefront_outlined;
  bool get _isLearning => icon == Icons.school_outlined;
  bool get _isSponsors => icon == Icons.handshake_outlined;
  bool get _isTopContent => icon == Icons.auto_awesome_outlined;

  String _kindLabel(AirmiusScope scope) => kind == 'Legal'
      ? scope.t('publicDetail.kindLegal')
      : scope.t('publicDetail.kindPublic');

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(title, style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: title,
        subtitle: body,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 60,
                    height: 60,
                    decoration: BoxDecoration(
                      color: airmiusAccentColor(
                        context,
                      ).withValues(alpha: 0.16),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(
                        color: airmiusAccentColor(
                          context,
                        ).withValues(alpha: 0.38),
                      ),
                    ),
                    child: Icon(
                      icon,
                      color: airmiusAccentColor(context),
                      size: 30,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Eyebrow(_kindLabel(AirmiusScope.of(context))),
                        const SizedBox(height: 6),
                        Text(
                          title,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontSize: 24,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          body,
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.35,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('publicDetail.eyebrow')),
                  const SizedBox(height: 10),
                  Text(
                    t('publicDetail.publicDataTitle'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(t('publicDetail.publicDataBody')),
                  const SizedBox(height: 12),
                  _InfoLine(
                    icon: Icons.verified_user_outlined,
                    title: t('publicDetail.safeTitle'),
                    body: t('publicDetail.safeBody'),
                  ),
                  _InfoLine(
                    icon: Icons.open_in_new_outlined,
                    title: t('publicDetail.nextTitle'),
                    body: t('publicDetail.nextBody'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            ..._actions(context),
          ],
        ),
      ),
    );
  }

  List<Widget> _actions(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    if (kind == 'Legal') {
      return [
        AirmiusButton(
          label: t('publicDetail.openLegal'),
          icon: Icons.description_outlined,
          onPressed: () => Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => LegalDocumentScreen(initialDocument: title),
            ),
          ),
        ),
        const SizedBox(height: 10),
        AirmiusButton(
          label: t('publicDetail.contact'),
          icon: Icons.contact_support_outlined,
          secondary: true,
          onPressed: () => Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => const LegalDocumentScreen(
                initialDocument: 'Kontakt & Melden',
              ),
            ),
          ),
        ),
      ];
    }
    if (_isTopContent) {
      return [
        AirmiusButton(
          label: t('publicDetail.openTopContent'),
          icon: Icons.auto_awesome_outlined,
          onPressed: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const PublicTopContentScreen()),
          ),
        ),
      ];
    }
    if (_isBlog) {
      return [
        AirmiusButton(
          label: t('publicDetail.openBlog'),
          icon: Icons.article_outlined,
          onPressed: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const PublicBlogReaderScreen()),
          ),
        ),
      ];
    }
    if (_isLearning) {
      return [
        AirmiusButton(
          label: t('publicDetail.courseInterest'),
          icon: Icons.school_outlined,
          onPressed: () => _openInterest(
            context,
            t('publicDetail.learningTopic'),
            'learning_interest',
          ),
        ),
        const SizedBox(height: 10),
        AirmiusButton(
          label: t('publicDetail.verifyCertificate'),
          icon: Icons.fact_check_outlined,
          secondary: true,
          onPressed: () => Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => const CertificateVerificationScreen(),
            ),
          ),
        ),
      ];
    }
    if (_isMarketplace || _isSponsors) {
      return [
        AirmiusButton(
          label: t('publicDetail.contactProvider'),
          icon: _isSponsors
              ? Icons.handshake_outlined
              : Icons.storefront_outlined,
          onPressed: () => _openInterest(
            context,
            title,
            _isSponsors ? 'partner_interest' : 'marketplace_interest',
          ),
        ),
      ];
    }
    return [
      AirmiusButton(
        label: t('publicDetail.contact'),
        icon: Icons.send_outlined,
        secondary: true,
        onPressed: () => _openInterest(context, title, kind),
      ),
    ];
  }

  void _openInterest(BuildContext context, String topic, String interestKind) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) =>
            PublicInterestScreen(topic: topic, kind: interestKind, icon: icon),
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  const _InfoLine({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: Theme.of(context).colorScheme.primary),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: TextStyle(fontWeight: FontWeight.w900)),
                const SizedBox(height: 3),
                Text(body),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
