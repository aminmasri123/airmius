import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'api_connection_screen.dart';
import 'operations_hub_screen.dart';
import 'release_readiness_screen.dart';

class WebRouteParityScreen extends StatefulWidget {
  const WebRouteParityScreen({super.key});

  @override
  State<WebRouteParityScreen> createState() => _WebRouteParityScreenState();
}

class _WebRouteParityScreenState extends State<WebRouteParityScreen> {
  String _filter = 'Alle';

  @override
  Widget build(BuildContext context) {
    final groups = _filter == 'Alle' ? _groups : _groups.where((group) => group.area == _filter).toList();
    final covered = _groups.expand((group) => group.routes).where((route) => route.status != 'API offen').length;
    final total = _groups.expand((group) => group.routes).length;

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Web Route Parity', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Web Route Parity',
        subtitle: 'Abgleich der Laravel-Webbereiche mit nativer Flutter-Mobile-UI',
        trailing: StatusPill('$covered/$total', color: AirmiusColors.green),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const AirmiusLogo(),
                  const SizedBox(height: 14),
                  const Text('Die Web-App wird Modul fuer Modul in native Mobile-UI uebersetzt.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  const SizedBox(height: 8),
                  const Text('Diese Ansicht bildet die Route-Familien der Laravel-Webversion auf Flutter-Screens ab. So bleibt sichtbar, ob ein Bereich nur als API-Kontrakt existiert oder bereits native UI-Aktionen besitzt.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  Row(children: [
                    Expanded(child: MetricCard(value: '$covered', label: 'UI-Flows')),
                    const SizedBox(width: 10),
                    Expanded(child: MetricCard(value: '$total', label: 'Route-Gruppen')),
                    const SizedBox(width: 10),
                    const Expanded(child: MetricCard(value: 'Laravel', label: 'Quelle')),
                  ]),
                  const SizedBox(height: 14),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    for (final area in _areas)
                      ChoiceChip(
                        label: Text(area),
                        selected: _filter == area,
                        onSelected: (_) => setState(() => _filter = area),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.cardSoft,
                        side: BorderSide(color: _filter == area ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _filter == area ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      ),
                  ]),
                ],
              ),
            ),
            const SizedBox(height: 16),
            for (final group in groups) ...[
              _ParityGroupCard(group: group),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: AirmiusColors.blue.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Navigation'),
                  const SizedBox(height: 8),
                  const Text('Von hier aus geht es in den Operations Hub, den API-Kontrakt oder die Release-Prüfung. Das ist die Kontrollschicht fuer die vollstaendige Web-zu-Flutter-Konvertierung.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 12),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    AirmiusButton(label: 'Operations Hub', icon: Icons.hub_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => OperationsHubScreen()))),
                    AirmiusButton(label: 'API Connection', icon: Icons.api_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ApiConnectionScreen()))),
                    AirmiusButton(label: 'Release Ready', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ReleaseReadinessScreen()))),
                  ]),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ParityGroupCard extends StatelessWidget {
  const _ParityGroupCard({required this.group});

  final _ParityGroup group;

  @override
  Widget build(BuildContext context) {
    final done = group.routes.where((route) => route.status != 'API offen').length;
    return AirmiusPanel(
      borderColor: group.color.withValues(alpha: .44),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(color: group.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: group.color.withValues(alpha: .45))),
                child: Icon(group.icon, color: group.color),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(group.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                    const SizedBox(height: 5),
                    Text(group.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                    const SizedBox(height: 10),
                    Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(group.area, color: group.color), StatusPill('$done/${group.routes.length}', color: group.color)]),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (final route in group.routes) ...[
            _RouteParityLine(route: route, color: group.color),
            const SizedBox(height: 8),
          ],
        ],
      ),
    );
  }
}

class _RouteParityLine extends StatelessWidget {
  const _RouteParityLine({required this.route, required this.color});

  final _RouteMap route;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(route.icon, color: color, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(route.webRoute, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 4),
                  Text(route.flutterUi, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
                ],
              ),
            ),
            const SizedBox(width: 8),
            StatusPill(route.status, color: route.status == 'API offen' ? AirmiusColors.amber : color),
          ],
        ),
      );
}

class _ParityGroup {
  const _ParityGroup({required this.area, required this.title, required this.body, required this.icon, required this.color, required this.routes});

  final String area;
  final String title;
  final String body;
  final IconData icon;
  final Color color;
  final List<_RouteMap> routes;
}

class _RouteMap {
  const _RouteMap({required this.webRoute, required this.flutterUi, required this.status, required this.icon});

  final String webRoute;
  final String flutterUi;
  final String status;
  final IconData icon;
}

const _areas = ['Alle', 'Public', 'Auth', 'Club', 'Social', 'Sport', 'Commerce', 'Admin', 'Ops'];

const _groups = <_ParityGroup>[
  _ParityGroup(area: 'Public', title: 'Gastseite & Public Growth', body: 'Landingpage, Public-Vereine, Preise, Jobs, Werbeagentur, Standort, Legal und Leads.', icon: Icons.public_outlined, color: AirmiusColors.blue, routes: [
    _RouteMap(webRoute: '/, /gastseite, /clubs', flutterUi: 'Guest Portal, Public Club Cards, Club Detail, Interest/Lead-Flows', status: 'Native UI', icon: Icons.open_in_new),
    _RouteMap(webRoute: '/preise, /abos, /jobs, /werbeagentur-fuer-vereine', flutterUi: 'Public Growth Operations mit Lead-, Job-, Website- und Preisinteresse', status: 'Native UI', icon: Icons.campaign_outlined),
    _RouteMap(webRoute: '/impressum, /datenschutz, /agb, /jugendschutz, /widerruf', flutterUi: 'Legal Support Operations und Legal Center Cards', status: 'Native UI', icon: Icons.gavel_outlined),
  ]),
  _ParityGroup(area: 'Auth', title: 'Auth, Konto & Onboarding', body: 'Login, Register, Passwort, 2FA, Mailverifizierung, Profilabschluss und erster App-Start.', icon: Icons.manage_accounts_outlined, color: AirmiusColors.green, routes: [
    _RouteMap(webRoute: '/login, /register, /forgot-password, /reset-password', flutterUi: 'LoginScreen und AuthFlowsScreen mit mobilen Formularen', status: 'Native UI', icon: Icons.login),
    _RouteMap(webRoute: '/user/two-factor-authentication, /email/verification-notification', flutterUi: 'Auth Operations und Account Operations fuer 2FA und Mailstatus', status: 'Native UI', icon: Icons.lock_outline),
    _RouteMap(webRoute: '/api/v1/me/language, /settings', flutterUi: 'App Onboarding, Localization Center und Settings Center', status: 'Native UI', icon: Icons.language_outlined),
  ]),
  _ParityGroup(area: 'Club', title: 'Vereine, Teams & Mitgliedschaft', body: 'Clubsuche, Clubprofil, Teamdetail, Mitgliedsantrag, Dokumente, Adminentscheidungen und Zahlrhythmus.', icon: Icons.groups_2_outlined, color: AirmiusColors.green, routes: [
    _RouteMap(webRoute: '/clubs/{club}, /clubs/{club}/membership-requests', flutterUi: 'Clubprofil, Membership Application und Membership Operations', status: 'Native UI', icon: Icons.assignment_ind_outlined),
    _RouteMap(webRoute: '/clubs/{club}/membership-form-schema, /membership-documents', flutterUi: 'Formularschema, Uploadpflicht, Vereinsdokumente und Dateimanager-Verknuepfung', status: 'Native UI', icon: Icons.description_outlined),
    _RouteMap(webRoute: '/teams, /teams/{team}, /teams/{team}/join-requests', flutterUi: 'Teams Center, Team Operations, Team Detail, Rollen, Kalender und Chat', status: 'Native UI', icon: Icons.diversity_3_outlined),
  ]),
  _ParityGroup(area: 'Social', title: 'Feed, Chat, Freunde & Safety', body: 'Posts, Stories, Kommentare, Notifications, Konversationen, Freundschaft, Fahrgemeinschaften und Guardian.', icon: Icons.forum_outlined, color: AirmiusColors.blue, routes: [
    _RouteMap(webRoute: '/feed, /stories, /maturity/feed-discovery', flutterUi: 'Social Operations, Feed Detail, Story- und Moderationsaktionen', status: 'Native UI', icon: Icons.dynamic_feed_outlined),
    _RouteMap(webRoute: '/chat/conversations, /messages, /notifications', flutterUi: 'Inbox/Chat Operations, Chat Detail, Push Preferences und Notification Detail', status: 'Native UI', icon: Icons.chat_bubble_outline),
    _RouteMap(webRoute: '/friends, /carpools, /guardian/consents, /maturity/gates', flutterUi: 'Safety Community Operations mit Consent, Elternlogin, Reports und Maturity', status: 'Native UI', icon: Icons.security_outlined),
  ]),
  _ParityGroup(area: 'Sport', title: 'Training, Sportprofil & Wellbeing', body: 'Events, Trainingsplaene, Logs, Coach, Ernaehrung, Wasser, Sportkarte, Routen und Tracks.', icon: Icons.sports_outlined, color: AirmiusColors.green, routes: [
    _RouteMap(webRoute: '/events, /training/plans, /training/logs, /trainer/actions', flutterUi: 'Training Operations, Event Detail, Trainer Cockpit und Coach Actions', status: 'Native UI', icon: Icons.event_available_outlined),
    _RouteMap(webRoute: '/nutrition, /nutrition/foods, /nutrition/ai/meal-image', flutterUi: 'Wellbeing Operations, Nutrition Detail, Barcode und Fotoanalyse UI', status: 'Native UI', icon: Icons.restaurant_outlined),
    _RouteMap(webRoute: '/sport-routes, /sport-tracks, /sport-places', flutterUi: 'Sportkarten- und Wellbeing-Flows fuer Routen, Tracks, Orte und Safety Checks', status: 'Native UI', icon: Icons.map_outlined),
  ]),
  _ParityGroup(area: 'Commerce', title: 'Marketplace, Billing, Outfit & Sponsoring', body: 'Produkte, Cart, Checkout, Orders, Retouren, Anbieter, Abos, Rechnungen, Outfit und Ads.', icon: Icons.storefront_outlined, color: AirmiusColors.amber, routes: [
    _RouteMap(webRoute: '/marketplace, /marketplace/products/{id}/checkout', flutterUi: 'Marketplace Operations, Produktdetail, Checkout Status und Return-Flows', status: 'Native UI', icon: Icons.shopping_bag_outlined),
    _RouteMap(webRoute: '/subscription-plans, /billing, /invoices, /payments', flutterUi: 'Billing Operations, Subscription Checkout, Banktransfer und Invoice Detail', status: 'Native UI', icon: Icons.receipt_long_outlined),
    _RouteMap(webRoute: '/outfit-subscriptions, /commerce/campaigns, /ads/active, /sponsors', flutterUi: 'Outfit Operations, Sponsor Ads Operations und Commerce Admin', status: 'Native UI', icon: Icons.handshake_outlined),
  ]),
  _ParityGroup(area: 'Admin', title: 'Admin, Trust & Content', body: 'Nutzer, Rollen, Permissions, Moderation, Club-Verifizierung, Blog, Medien, Learning Quality und System.', icon: Icons.admin_panel_settings_outlined, color: AirmiusColors.red, routes: [
    _RouteMap(webRoute: '/admin/users, /admin/members, /admin/roles-permissions', flutterUi: 'Access Operations, Nutzercenter, Rollen-/Permission-Detail und Admin Detail', status: 'Native UI', icon: Icons.verified_user_outlined),
    _RouteMap(webRoute: '/admin/moderation, /admin/club-verifications, /admin/operating-contracts', flutterUi: 'Trust Operations mit Reports, Flags, Inaktivitaet und Verifizierung', status: 'Native UI', icon: Icons.shield_outlined),
    _RouteMap(webRoute: '/admin/blogs, /admin/media-guidelines, /admin/learning/courses/{id}/quality', flutterUi: 'Content Operations, Blog/Medien Detail und Learning Quality', status: 'Native UI', icon: Icons.article_outlined),
  ]),
  _ParityGroup(area: 'Ops', title: 'Technische Web-Routen & API', body: 'Meta, CSRF, Webhooks, SEO, Mailcenter, Wartung, Systemstatus und spaetere Mobile-API.', icon: Icons.api_outlined, color: AirmiusColors.blue, routes: [
    _RouteMap(webRoute: '/api/v1/meta, /csrf-token, /settings', flutterUi: 'Platform Operations und API Connection mit BaseUrl/Feature-Kontrakt', status: 'Native UI', icon: Icons.api_outlined),
    _RouteMap(webRoute: '/webhooks/stripe, /webhooks/paypal, /robots.txt, /sitemap.xml', flutterUi: 'Platform Operations fuer Checkout, SEO und Public Betrieb', status: 'Native UI', icon: Icons.sync_outlined),
    _RouteMap(webRoute: '/admin/mail-center, /admin/settings, /admin/provider-costs', flutterUi: 'Admin/System Operations vorbereitet, echte API-Anbindung folgt', status: 'API offen', icon: Icons.settings_suggest_outlined),
  ]),
];
