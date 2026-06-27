import 'package:flutter/material.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'guest_blog_content_screen.dart';
import 'guest_learning_certificate_screen.dart';
import 'guest_marketplace_parity_screen.dart';
import 'guest_pricing_plans_screen.dart';
import 'public_detail_screen.dart';
import 'public_growth_guest_pages_screen.dart';
import 'public_top_content_screen.dart';
import 'public_growth_operations_screen.dart';
import 'support_helpdesk_screen.dart';

class GuestPortalScreen extends StatelessWidget {
  const GuestPortalScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.campaign_outlined), label: const Text('Public Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => PublicGrowthOperationsScreen(initialTab: 'Leads')))),
        
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Gastseite', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Airmius',
        subtitle: 'Oeffentliche Web-App-Bereiche als native Mobile-UI',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: const [
                  AirmiusLogo(),
                  SizedBox(height: 18),
                  Eyebrow('Gastseite'),
                  SizedBox(height: 8),
                  Text('Vereine, Kurse, Marketplace, Preise, Jobs und Wissen fuer Sportorganisationen.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.12)),
                  SizedBox(height: 10),
                  Text('Dieser Bereich bildet die oeffentliche mobile Web-App nativ ab und bleibt spaeter mit Laravel-Inhalten verbunden.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                AirmiusButton(label: 'Vereine entdecken', icon: Icons.groups_outlined, onPressed: () => _open(context, 'Vereine', 'Oeffentliche Vereinsliste mit Suche und Beitrittsmoeglichkeit.', Icons.groups_outlined, 'Public')),
                AirmiusButton(label: 'Funktionen', icon: Icons.apps_outlined, secondary: true, onPressed: () => _openScreen(context, const PublicGrowthGuestPagesScreen())),
                AirmiusButton(label: 'Preise ansehen', icon: Icons.sell_outlined, secondary: true, onPressed: () => _openScreen(context, const GuestPricingPlansScreen())),
                AirmiusButton(label: 'Kontakt', icon: Icons.support_agent_outlined, secondary: true, onPressed: () => _openScreen(context, const SupportHelpdeskScreen())),
                AirmiusButton(label: 'Top-Inhalte', icon: Icons.auto_awesome_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PublicTopContentScreen()))),
              ],
            ),
            const SizedBox(height: 14),
            _PublicGrid(items: _primaryItems),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Rechtliches'),
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

  static void _open(BuildContext context, String title, String body, IconData icon, String trailing) {
    Navigator.push(context, MaterialPageRoute(builder: (_) => PublicDetailScreen(title: title, body: body, icon: icon, kind: trailing)));
  }

  static void _openScreen(BuildContext context, Widget screen) {
    Navigator.push(context, MaterialPageRoute(builder: (_) => screen));
  }

  static void _openItem(BuildContext context, _PublicItem item) {
    switch (item.title) {
      case 'Marketplace':
        _openScreen(context, const GuestMarketplaceParityScreen());
        return;
      case 'Funktionen':
        _openScreen(context, const PublicGrowthGuestPagesScreen());
        return;
      case 'E-Learning':
        _openScreen(context, const GuestLearningCertificateScreen());
        return;
      case 'Blog':
        _openScreen(context, const GuestBlogContentScreen());
        return;
      case 'Top-Inhalte':
        _openScreen(context, const PublicTopContentScreen());
        return;
      case 'Kontakt & Melden':
        _openScreen(context, const SupportHelpdeskScreen());
        return;
      default:
        _open(context, item.title, item.body, item.icon, 'Public');
    }
  }
}

class _PublicGrid extends StatelessWidget {
  const _PublicGrid({required this.items});

  final List<_PublicItem> items;

  @override
  Widget build(BuildContext context) {
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
                  Icon(item.icon, color: AirmiusColors.blue, size: 25),
                  const Spacer(),
                  Text(item.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 5),
                  Text(item.body, maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.25)),
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
    return InkWell(
      onTap: () => GuestPortalScreen._openItem(context, item),
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
        child: Row(
          children: [
            Icon(item.icon, color: AirmiusColors.blue),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 3),
                  Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ],
        ),
      ),
    );
  }
}

class _PublicItem {
  const _PublicItem({required this.title, required this.body, required this.icon});

  final String title;
  final String body;
  final IconData icon;
}

const _primaryItems = [
  _PublicItem(title: 'Funktionen', body: 'Oeffentliche Funktionen und Bereiche der Gastseite im Ueberblick.', icon: Icons.apps_outlined),
  _PublicItem(title: 'Vereine', body: 'Vereine suchen, oeffentliche Profile ansehen und Beitritt starten.', icon: Icons.groups_outlined),
  _PublicItem(title: 'Marketplace', body: 'Produkte, Anbieter, Warenkorb und Bestellungen entdecken.', icon: Icons.storefront_outlined),
  _PublicItem(title: 'E-Learning', body: 'Kurse, Zertifikate und Lerninhalte fuer Sportorganisationen.', icon: Icons.school_outlined),
  _PublicItem(title: 'Blog', body: 'Praxiswissen, Updates und Ideen fuer digitale Sportorganisation.', icon: Icons.article_outlined),
  _PublicItem(title: 'Jobs', body: 'Organisationen koennen Stellen und Engagement-Moeglichkeiten zeigen.', icon: Icons.work_outline),
  _PublicItem(title: 'Sponsoren', body: 'Partner, Sponsoring und Sichtbarkeit fuer Vereine.', icon: Icons.handshake_outlined),
  _PublicItem(title: 'Gamification', body: 'Badges, Motivation, Fortschritt und Vereinsaktivitaet.', icon: Icons.workspace_premium_outlined),
  _PublicItem(title: 'Werbeagentur', body: 'Websites, Kampagnen und digitale Praesenz fuer Vereine.', icon: Icons.campaign_outlined),
  _PublicItem(title: 'Top-Inhalte', body: 'Kuratierte Inhalte aus Blog, Kursen, Marketplace, Vereinen und Sponsoring.', icon: Icons.auto_awesome_outlined),
];

const _legalItems = [
  _PublicItem(title: 'Impressum', body: 'Anbieterkennzeichnung und Kontaktinformationen.', icon: Icons.badge_outlined),
  _PublicItem(title: 'Datenschutz', body: 'Datenschutzerklaerung, Rechte und Verarbeitung.', icon: Icons.privacy_tip_outlined),
  _PublicItem(title: 'AGB', body: 'Allgemeine Geschaeftsbedingungen.', icon: Icons.gavel_outlined),
  _PublicItem(title: 'Community-Richtlinien', body: 'Regeln fuer Verhalten, Inhalte und Sicherheit.', icon: Icons.diversity_3_outlined),
  _PublicItem(title: 'Jugendschutz', body: 'Schutz minderjaehriger Nutzer und Erziehungsberechtigte.', icon: Icons.family_restroom_outlined),
  _PublicItem(title: 'Cookies', body: 'Cookie-Hinweise und Tracking-Einstellungen.', icon: Icons.cookie_outlined),
  _PublicItem(title: 'Widerruf', body: 'Widerrufsrecht und Rueckabwicklung.', icon: Icons.assignment_return_outlined),
  _PublicItem(title: 'Kontakt & Melden', body: 'Kontaktformular, Meldungen und Support.', icon: Icons.report_outlined),
];

