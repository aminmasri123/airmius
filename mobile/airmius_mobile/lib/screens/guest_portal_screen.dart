import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../widgets/airmius_widgets.dart';
import 'blog_media_center_screen.dart';
import 'guest_club_directory_screen.dart';
import 'guest_jobs_careers_screen.dart';
import 'guest_learning_certificate_screen.dart';
import 'guest_marketplace_parity_screen.dart';
import 'legal_status_center_screen.dart';
import 'public_detail_screen.dart';
import 'public_interest_screen.dart';
import 'public_location_submission_screen.dart';
import 'public_top_content_screen.dart';
import 'sponsors_center_screen.dart';
import 'support_helpdesk_screen.dart';

/// Public entry point. It intentionally contains only guest-safe screens and
/// never exposes internal operations or private account data.
class GuestPortalScreen extends StatelessWidget {
  const GuestPortalScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('guestPortal.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Airmius',
        subtitle: t('guestPortal.subtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const AirmiusLogo(),
                  const SizedBox(height: 18),
                  Eyebrow(t('guestPortal.eyebrow')),
                  const SizedBox(height: 8),
                  Text(
                    t('guestPortal.headline'),
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(t('guestPortal.body')),
                  const SizedBox(height: 16),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(
                        label: t('guestPortal.clubs'),
                        icon: Icons.groups_outlined,
                        onPressed: () =>
                            _open(context, const GuestClubDirectoryScreen()),
                      ),
                      AirmiusButton(
                        label: t('guestPortal.contact'),
                        icon: Icons.support_agent_outlined,
                        secondary: true,
                        onPressed: () =>
                            _open(context, const SupportHelpdeskScreen()),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _PublicGrid(items: _primaryItems),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    t('guestPortal.legal'),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 10),
                  for (final item in _legalItems) ...[
                    _PublicLine(item: item),
                    const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  static void _open(BuildContext context, Widget screen) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => screen));
  }

  static void _openDetail(
    BuildContext context,
    String title,
    String body,
    IconData icon,
  ) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => PublicDetailScreen(
          title: title,
          body: body,
          icon: icon,
          kind: 'Public',
        ),
      ),
    );
  }

  static void _openItem(BuildContext context, _PublicItem item) {
    switch (item.action) {
      case _GuestAction.blog:
        _open(context, const BlogMediaCenterScreen());
        return;
      case _GuestAction.marketplace:
        _open(context, const GuestMarketplaceParityScreen());
        return;
      case _GuestAction.learning:
        _open(context, const GuestLearningCertificateScreen());
        return;
      case _GuestAction.jobs:
        _open(context, const GuestJobsCareersScreen());
        return;
      case _GuestAction.sponsors:
        _open(context, const SponsorsCenterScreen());
        return;
      case _GuestAction.topContent:
        _open(context, const PublicTopContentScreen());
        return;
      case _GuestAction.contact:
        _open(context, const SupportHelpdeskScreen());
        return;
      case _GuestAction.location:
        _open(context, const PublicLocationSubmissionScreen());
        return;
      case _GuestAction.interest:
        _open(
          context,
          PublicInterestScreen(
            topic: 'Airmius',
            kind: 'Public',
            icon: Icons.waving_hand_outlined,
          ),
        );
        return;
      case _GuestAction.legal:
        _open(context, const LegalStatusCenterScreen());
        return;
      case _GuestAction.detail:
        _openDetail(
          context,
          item.label(context),
          item.body(context),
          item.icon,
        );
        return;
    }
  }
}

enum _GuestAction {
  blog,
  marketplace,
  learning,
  jobs,
  sponsors,
  topContent,
  contact,
  location,
  interest,
  legal,
  detail,
}

class _PublicGrid extends StatelessWidget {
  const _PublicGrid({required this.items});

  final List<_PublicItem> items;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth > 620 ? 3 : 2;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: items.length,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: columns == 3 ? 1.35 : 0.98,
          ),
          itemBuilder: (context, index) {
            final item = items[index];
            return AirmiusPanel(
              onTap: () => GuestPortalScreen._openItem(context, item),
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    item.icon,
                    color: Theme.of(context).colorScheme.primary,
                    size: 25,
                  ),
                  const Spacer(),
                  Text(
                    t(item.titleKey),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    t(item.bodyKey),
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 12, height: 1.25),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }
}

class _PublicLine extends StatelessWidget {
  const _PublicLine({required this.item});

  final _PublicItem item;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Semantics(
      button: true,
      label: t(item.titleKey),
      child: InkWell(
        onTap: () => GuestPortalScreen._openItem(context, item),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surfaceContainerHighest,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Theme.of(context).dividerColor),
          ),
          child: Row(
            children: [
              Icon(item.icon, color: Theme.of(context).colorScheme.primary),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      t(item.titleKey),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 3),
                    Text(t(item.bodyKey)),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}

class _PublicItem {
  const _PublicItem({
    required this.titleKey,
    required this.bodyKey,
    required this.icon,
    required this.action,
  });

  final String titleKey;
  final String bodyKey;
  final IconData icon;
  final _GuestAction action;

  String label(BuildContext context) => AirmiusScope.of(context).t(titleKey);

  String body(BuildContext context) => AirmiusScope.of(context).t(bodyKey);
}

const _primaryItems = [
  _PublicItem(
    titleKey: 'guestPortal.blog',
    bodyKey: 'guestPortal.blogBody',
    icon: Icons.article_outlined,
    action: _GuestAction.blog,
  ),
  _PublicItem(
    titleKey: 'guestPortal.marketplace',
    bodyKey: 'guestPortal.marketplaceBody',
    icon: Icons.storefront_outlined,
    action: _GuestAction.marketplace,
  ),
  _PublicItem(
    titleKey: 'guestPortal.learning',
    bodyKey: 'guestPortal.learningBody',
    icon: Icons.school_outlined,
    action: _GuestAction.learning,
  ),
  _PublicItem(
    titleKey: 'guestPortal.jobs',
    bodyKey: 'guestPortal.jobsBody',
    icon: Icons.work_outline,
    action: _GuestAction.jobs,
  ),
  _PublicItem(
    titleKey: 'guestPortal.sponsors',
    bodyKey: 'guestPortal.sponsorsBody',
    icon: Icons.handshake_outlined,
    action: _GuestAction.sponsors,
  ),
  _PublicItem(
    titleKey: 'guestPortal.gamification',
    bodyKey: 'guestPortal.gamificationBody',
    icon: Icons.workspace_premium_outlined,
    action: _GuestAction.detail,
  ),
  _PublicItem(
    titleKey: 'guestPortal.agency',
    bodyKey: 'guestPortal.agencyBody',
    icon: Icons.campaign_outlined,
    action: _GuestAction.interest,
  ),
  _PublicItem(
    titleKey: 'guestPortal.contact',
    bodyKey: 'guestPortal.contactBody',
    icon: Icons.support_agent_outlined,
    action: _GuestAction.contact,
  ),
  _PublicItem(
    titleKey: 'publicLocation.title',
    bodyKey: 'publicLocation.subtitle',
    icon: Icons.add_location_alt_outlined,
    action: _GuestAction.location,
  ),
  _PublicItem(
    titleKey: 'guestPortal.topContent',
    bodyKey: 'guestPortal.body',
    icon: Icons.auto_awesome_outlined,
    action: _GuestAction.topContent,
  ),
];

const _legalItems = [
  _PublicItem(
    titleKey: 'guestPortal.imprint',
    bodyKey: 'legalHub.imprintBody',
    icon: Icons.badge_outlined,
    action: _GuestAction.legal,
  ),
  _PublicItem(
    titleKey: 'guestPortal.privacy',
    bodyKey: 'legalHub.privacyBody',
    icon: Icons.privacy_tip_outlined,
    action: _GuestAction.legal,
  ),
  _PublicItem(
    titleKey: 'guestPortal.terms',
    bodyKey: 'legalHub.termsBody',
    icon: Icons.gavel_outlined,
    action: _GuestAction.legal,
  ),
  _PublicItem(
    titleKey: 'guestPortal.guidelines',
    bodyKey: 'legalHub.communityBody',
    icon: Icons.diversity_3_outlined,
    action: _GuestAction.legal,
  ),
  _PublicItem(
    titleKey: 'guestPortal.youth',
    bodyKey: 'legalHub.minorsBody',
    icon: Icons.family_restroom_outlined,
    action: _GuestAction.legal,
  ),
];
