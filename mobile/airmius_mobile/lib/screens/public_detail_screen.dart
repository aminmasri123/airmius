import 'package:flutter/material.dart';
import 'public_growth_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ad_campaign_screen.dart';
import 'certificate_verification_screen.dart';
import 'legal_document_screen.dart';
import 'public_blog_reader_screen.dart';
import 'public_interest_screen.dart';
import 'public_top_content_screen.dart';
import 'public_growth_operations_screen.dart';

class PublicDetailScreen extends StatelessWidget {
  const PublicDetailScreen({super.key, required this.title, required this.body, required this.icon, required this.kind});

  final String title;
  final String body;
  final IconData icon;
  final String kind;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.campaign_outlined), label: const Text('Growth Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => PublicGrowthOperationsScreen(initialTab: 'Leads')))),
        
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
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
                    decoration: BoxDecoration(color: AirmiusColors.blue.withValues(alpha: 0.16), borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.blue.withValues(alpha: 0.38))),
                    child: Icon(icon, color: AirmiusColors.blue, size: 30),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Eyebrow(kind),
                        const SizedBox(height: 6),
                        Text(title, style: const TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900)),
                        const SizedBox(height: 6),
                        Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            ..._sections(context),
            const SizedBox(height: 14),
            ..._actions(context),
          ],
        ),
      ),
    );
  }

  List<Widget> _actions(BuildContext context) {
    final lower = title.toLowerCase();
    if (kind == 'Legal') {
      return [
        AirmiusButton(label: 'Rechtstext oeffnen', icon: Icons.description_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LegalDocumentScreen(initialDocument: title)))),
        const SizedBox(height: 10),
        AirmiusButton(label: 'Kontakt & Melden', icon: Icons.contact_support_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LegalDocumentScreen(initialDocument: 'Kontakt & Melden')))),
      ];
    }
    if (lower.contains('top-inhalte')) {
      return [
        AirmiusButton(label: 'Top-Inhalte oeffnen', icon: Icons.auto_awesome_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PublicTopContentScreen()))),
      ];
    }
    if (lower.contains('jobs')) {
      return [
        AirmiusButton(label: 'Interesse senden', icon: Icons.send_outlined, onPressed: () => _openInterest(context, 'Job', 'Jobs')),
      ];
    }
    if (lower.contains('sponsoren')) {
      return [
        AirmiusButton(label: 'Sponsor kontaktieren', icon: Icons.handshake_outlined, onPressed: () => _openInterest(context, 'Sponsoring', 'Partner')),
        const SizedBox(height: 10),
        AirmiusButton(label: 'Kampagne ansehen', icon: Icons.campaign_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AdCampaignScreen(title: 'Sponsor Kampagne', status: 'Aktiv')))),
      ];
    }
    if (lower.contains('werbeagentur')) {
      return [
        AirmiusButton(label: 'Projekt anfragen', icon: Icons.request_quote_outlined, onPressed: () => _openInterest(context, 'Werbeagentur', 'Lead')),
        const SizedBox(height: 10),
        AirmiusButton(label: 'Ads planen', icon: Icons.campaign_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AdCampaignScreen(title: 'Werbeagentur Kampagne', status: 'Planung')))),
      ];
    }
    if (lower.contains('preise')) {
      return [
        AirmiusButton(label: 'Plan anfragen', icon: Icons.sell_outlined, onPressed: () => _openInterest(context, 'Preise', 'Plans')),
      ];
    }
    if (lower.contains('marketplace')) {
      return [
        AirmiusButton(label: 'Anbieter kontaktieren', icon: Icons.storefront_outlined, onPressed: () => _openInterest(context, 'Marketplace', 'Shop')),
        const SizedBox(height: 10),
        AirmiusButton(label: 'Marketplace Ad', icon: Icons.campaign_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AdCampaignScreen(title: 'Marketplace Ad', status: 'Aktiv')))),
      ];
    }
    if (lower.contains('blog')) {
      return [
        AirmiusButton(label: 'Blog lesen', icon: Icons.article_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PublicBlogReaderScreen()))),
        const SizedBox(height: 10),
        AirmiusButton(label: 'RSS & Kategorien', icon: Icons.rss_feed_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PublicBlogReaderScreen(initialCategory: 'Alle')))),
      ];
    }
    if (lower.contains('e-learning')) {
      return [
        AirmiusButton(label: 'Kursinteresse senden', icon: Icons.school_outlined, onPressed: () => _openInterest(context, 'E-Learning', 'Kurse')),
        const SizedBox(height: 10),
        AirmiusButton(label: 'Zertifikat pruefen', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CertificateVerificationScreen()))),
      ];
    }
    return [
      AirmiusButton(label: 'Mehr erfahren', icon: Icons.info_outline, secondary: true, onPressed: () => _openInterest(context, title, kind)),
    ];
  }

  void _openInterest(BuildContext context, String topic, String interestKind) {
    Navigator.push(context, MaterialPageRoute(builder: (_) => PublicInterestScreen(topic: topic, kind: interestKind, icon: icon)));
  }

  List<Widget> _sections(BuildContext context) {
    final lower = title.toLowerCase();
    if (lower.contains('vereine')) return _clubs();
    if (lower.contains('marketplace')) return _marketplace();
    if (lower.contains('e-learning')) return _learning();
    if (lower.contains('blog')) return _blog();
    if (lower.contains('jobs')) return _jobs();
    if (lower.contains('sponsoren')) return _sponsors();
    if (lower.contains('gamification')) return _gamification();
    if (lower.contains('werbeagentur')) return _agency();
    if (lower.contains('top-inhalte')) return _topContent();
    if (kind == 'Legal') return _legal();
    if (lower.contains('preise')) return _pricing();
    return _generic();
  }

  List<Widget> _clubs() => const [
        _PublicInfoCard(icon: Icons.search, title: 'Vereinssuche', body: 'Oeffentliche Vereinsliste mit Ort, Mitgliederzahl, Status und Beitrittsmoeglichkeit.', status: 'Public'),
        SizedBox(height: 12),
        _PublicInfoCard(icon: Icons.assignment_outlined, title: 'Mitgliedschaftsanfrage', body: 'Interessierte koennen nach Login ein Formular ausfuellen und Dokumente bestaetigen.', status: 'Login'),
        SizedBox(height: 12),
        _PublicInfoCard(icon: Icons.verified_outlined, title: 'Verifizierung', body: 'Verifizierte Vereine erscheinen mit Status und sichtbaren Profilinformationen.', status: 'Trust'),
      ];

  List<Widget> _marketplace() => const [
        _PublicInfoCard(icon: Icons.storefront_outlined, title: 'Produkte & Anbieter', body: 'Produktkarten, Anbieterprofile, Varianten und oeffentliche Produktdetails.', status: 'Shop'),
        SizedBox(height: 12),
        _PublicInfoCard(icon: Icons.shopping_cart_outlined, title: 'Checkout', body: 'Warenkorb und Gast-/User-Checkout werden spaeter an Commerce-API angeschlossen.', status: 'Checkout'),
      ];

  List<Widget> _learning() => const [
        _PublicInfoCard(icon: Icons.school_outlined, title: 'Kurskatalog', body: 'Oeffentliche Kurse, Lektionen, Bewertungen und Zertifikatspruefung.', status: 'Kurse'),
        SizedBox(height: 12),
        _PublicInfoCard(icon: Icons.verified_outlined, title: 'Zertifikat verifizieren', body: 'Code pruefen und Zertifikatsstatus anzeigen.', status: 'Verify'),
      ];

  List<Widget> _blog() => const [
        _PublicInfoCard(icon: Icons.article_outlined, title: 'Artikel-Liste', body: 'Kategorien, Suchauszug, Autor, Lesezeit und Veroeffentlichungsdatum.', status: 'Blog'),
        SizedBox(height: 12),
        _PublicInfoCard(icon: Icons.rss_feed_outlined, title: 'RSS & Kategorien', body: 'RSS und Kategorien bleiben als Public-Content-Struktur sichtbar.', status: 'RSS'),
      ];

  List<Widget> _jobs() => const [
        _PublicInfoCard(icon: Icons.work_outline, title: 'Stellen & Engagement', body: 'Jobkarten mit Organisation, Ort, Beschreibung und Interesse senden.', status: 'Jobs'),
        SizedBox(height: 12),
        _PublicInfoCard(icon: Icons.send_outlined, title: 'Interesse senden', body: 'Kontaktformular fuer Bewerber oder Interessenten.', status: 'Form'),
      ];

  List<Widget> _sponsors() => const [
        _PublicInfoCard(icon: Icons.handshake_outlined, title: 'Sponsorenprofile', body: 'Partnerkarten, Sichtbarkeit, Kampagnen und Kontakt.', status: 'Partner'),
        SizedBox(height: 12),
        _PublicInfoCard(icon: Icons.campaign_outlined, title: 'Kampagnen', body: 'Sponsoring- und Anzeigenbereiche fuer Vereine.', status: 'Ads'),
      ];

  List<Widget> _gamification() => const [
        _PublicInfoCard(icon: Icons.workspace_premium_outlined, title: 'Badges & Fortschritt', body: 'Motivation, Regeln, Aktivitaet und Belohnungen.', status: 'Badges'),
        SizedBox(height: 12),
        _PublicInfoCard(icon: Icons.leaderboard_outlined, title: 'Engagement', body: 'Leaderboard- und Aktivitaetsbereiche spaeter ueber API.', status: 'Score'),
      ];

  List<Widget> _agency() => const [
        _PublicInfoCard(icon: Icons.web_outlined, title: 'Vereinswebsite', body: 'Landingpage, Anfrageformular, Kampagnen und digitale Praesenz.', status: 'Website'),
        SizedBox(height: 12),
        _PublicInfoCard(icon: Icons.request_quote_outlined, title: 'Anfrage senden', body: 'Projektumfang, Kontakt und Angebotsstatus.', status: 'Lead'),
      ];

  List<Widget> _pricing() => const [
        _PublicInfoCard(icon: Icons.sell_outlined, title: 'Preisplaene', body: 'User-, Club- und Zusatzpakete mit Leistungsuebersicht.', status: 'Plans'),
        SizedBox(height: 12),
        _PublicInfoCard(icon: Icons.payments_outlined, title: 'Checkout-Einstieg', body: 'Stripe, PayPal oder Banktransfer werden spaeter angebunden.', status: 'Pay'),
      ];

  List<Widget> _topContent() => const [
        _PublicInfoCard(icon: Icons.auto_awesome_outlined, title: 'Kuratierte Inhalte', body: 'Blog, Kurse, Marketplace, Vereine, Sponsoren und Gamification als mobile Public-Auswahl.', status: 'Top'),
        SizedBox(height: 12),
        _PublicInfoCard(icon: Icons.query_stats_outlined, title: 'Beliebtheit & Sichtbarkeit', body: 'Spaeter sortiert Laravel nach Relevanz, Kategorie, Sprache und Public-Freigabe.', status: 'Ranking'),
      ];

  List<Widget> _legal() => const [
        _PublicInfoCard(icon: Icons.description_outlined, title: 'Rechtstext', body: 'Struktur fuer Abschnitte, Stand, Kontakt und Download.', status: 'Legal'),
        SizedBox(height: 12),
        _PublicInfoCard(icon: Icons.report_outlined, title: 'Kontakt & Melden', body: 'Meldungen, Support und rechtliche Kontaktwege.', status: 'Support'),
      ];

  List<Widget> _generic() => const [
        _PublicInfoCard(icon: Icons.public_outlined, title: 'Public Content', body: 'Native Detailseite fuer oeffentliche Inhalte vorbereitet.', status: 'Public'),
      ];
}

class _PublicInfoCard extends StatelessWidget {
  const _PublicInfoCard({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          StatusPill(status),
        ],
      ),
    );
  }
}

