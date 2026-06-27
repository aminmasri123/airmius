import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../core/api_contract.dart';
import '../widgets/airmius_widgets.dart';

class ApiConnectionScreen extends StatelessWidget {
  const ApiConnectionScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('API Connection', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'API Connection',
        subtitle: 'Laravel-Kontrakt für die native Flutter-App',
        trailing: const StatusPill('API'),
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
                  const Text('Die Webversion wird nicht geraten, sondern systematisch angebunden.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  const SizedBox(height: 8),
                  const Text('Diese Übersicht ordnet die wichtigsten Laravel-Routen den nativen Flutter-Modulen zu. Später wird daraus der echte API-Client mit Token, Loading, Error, Retry und Offline-State.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  AirmiusPanel(
                    padding: const EdgeInsets.all(12),
                    borderColor: AirmiusColors.blue.withValues(alpha: .45),
                    child: SelectableText(AirmiusApiContract.baseUrl, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Row(children: const [Expanded(child: MetricCard(value: '12', label: 'Gruppen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '100+', label: 'Routen')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'v1', label: 'API'))]),
            const SizedBox(height: 16),
            for (final group in _groups) ...[
              _EndpointGroupCard(group: group),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Naechster Integrationsschritt'),
                  const SizedBox(height: 8),
                  const Text('Wenn die UI final genug ist, verbinden wir diese Gruppen mit einem AirmiusApiClient: Auth-Token, Request-Queue, Fehlertexte, Refresh, Uploads und Rollenrechte.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'API Client vormerken', icon: Icons.api_outlined, onPressed: () => openUiAction(context, title: 'API Client vormerken', body: 'AirmiusApiClient für Laravel v1 mit Auth, Uploads, Pagination, Mutationen, Fehlerstatus und Offline/Retry vorbereiten.', status: 'API', icon: Icons.api_outlined)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EndpointGroupCard extends StatelessWidget {
  const _EndpointGroupCard({required this.group});

  final _EndpointGroup group;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: group.color.withValues(alpha: .42),
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
                      Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(group.status, color: group.color), const StatusPill('Laravel')]),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            for (final endpoint in group.endpoints) ...[
              _EndpointLine(endpoint),
              const SizedBox(height: 8),
            ],
          ],
        ),
      );
}

class _EndpointLine extends StatelessWidget {
  const _EndpointLine(this.endpoint);

  final String endpoint;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(13), border: Border.all(color: AirmiusColors.border)),
        child: SelectableText(endpoint, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800, fontSize: 12)),
      );
}

class _EndpointGroup {
  const _EndpointGroup({required this.title, required this.body, required this.status, required this.icon, required this.color, required this.endpoints});

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
  final List<String> endpoints;
}

final _groups = <_EndpointGroup>[
  _EndpointGroup(title: 'Auth & Konto', body: 'Login, Registrierung, 2FA, E-Mail-Verifizierung, Sprache, Profil, Export und Kontolöschung.', status: 'Core', icon: Icons.manage_accounts_outlined, color: AirmiusColors.blue, endpoints: [AirmiusApiContract.login, AirmiusApiContract.register, AirmiusApiContract.twoFactor, AirmiusApiContract.me, AirmiusApiContract.language]),
  _EndpointGroup(title: 'Suche & Workspaces', body: 'Globale Suche, Autocomplete, Arbeitsbereiche, Kontextwechsel und Feature-Meta.', status: 'Core', icon: Icons.manage_search_outlined, color: AirmiusColors.blue, endpoints: [AirmiusApiContract.globalSearch, AirmiusApiContract.workspaces, AirmiusApiContract.meta]),
  _EndpointGroup(title: 'Vereine & Mitgliedschaft', body: 'Clubprofil, Mitglieder, Antraege, Formularschema, Beitrag, Dokumente und Rückzug.', status: 'Verein', icon: Icons.groups_2_outlined, color: AirmiusColors.green, endpoints: [AirmiusApiContract.clubs, AirmiusApiContract.clubMembershipRequests(26), AirmiusApiContract.clubMembershipFormSchema(26), AirmiusApiContract.clubMembershipDocuments(26), AirmiusApiContract.clubMembershipRequestWithdraw(26, 1)]),
  _EndpointGroup(title: 'Teams & Events', body: 'Kader, Einladungen, Join-Requests, Strafkatalog, Teamdateien, Events, Teilnahme, Warteliste und Anwesenheit.', status: 'Team', icon: Icons.diversity_3_outlined, color: AirmiusColors.green, endpoints: [AirmiusApiContract.teams, AirmiusApiContract.teamMembers(1), AirmiusApiContract.teamInvitations(1), AirmiusApiContract.teamPenalties(1), AirmiusApiContract.teamPenaltyRules(1), AirmiusApiContract.teamPenaltyFees(1), AirmiusApiContract.events, AirmiusApiContract.eventJoin(1)]),
  _EndpointGroup(title: 'Dateien & Uploads', body: 'Upload, Dateimanager, Vereinsdokumente, Teamdateien, Share-Links und Mitgliedsantrag-Dateien.', status: 'Files', icon: Icons.folder_outlined, color: AirmiusColors.amber, endpoints: [AirmiusApiContract.uploads, AirmiusApiContract.files, AirmiusApiContract.folders, AirmiusApiContract.sharedFiles, AirmiusApiContract.clubDocuments(26)]),
  _EndpointGroup(title: 'Chat & Benachrichtigungen', body: 'Notifications, Preferences, Konversationen, Nachrichten, Reaktionen, Typing, Mute und Einladungen.', status: 'Inbox', icon: Icons.forum_outlined, color: AirmiusColors.blue, endpoints: [AirmiusApiContract.notifications, AirmiusApiContract.notificationPreferences, AirmiusApiContract.conversations, AirmiusApiContract.conversationMessages(1), AirmiusApiContract.messagesRead()]),
  _EndpointGroup(title: 'Social, Safety & Guardian', body: 'Feed, Stories, Freunde, Fahrgemeinschaften, Guardian Consent, Maturity Gates und Reports.', status: 'Safety', icon: Icons.security_outlined, color: AirmiusColors.red, endpoints: [AirmiusApiContract.feed, AirmiusApiContract.stories, AirmiusApiContract.friends, AirmiusApiContract.carpools, AirmiusApiContract.guardianConsents, AirmiusApiContract.maturityGates]),
  _EndpointGroup(title: 'Sport & Wellbeing', body: 'Sportprofile, Ziele, Training, Logs, Nutrition, Wasser, Routen, Tracks, Orte und Coach Weekly.', status: 'Sport', icon: Icons.sports_outlined, color: AirmiusColors.green, endpoints: [AirmiusApiContract.sports, AirmiusApiContract.trainingPlans, AirmiusApiContract.trainingLogs, AirmiusApiContract.nutrition, AirmiusApiContract.sportRoutes, AirmiusApiContract.sportTracks]),
  _EndpointGroup(title: 'Learning & Gamification', body: 'Kurse, Lektionen, Quiz, Aufgaben, Zertifikate, Badges, XP-Regeln und Leaderboard.', status: 'Growth', icon: Icons.school_outlined, color: AirmiusColors.amber, endpoints: [AirmiusApiContract.courses, AirmiusApiContract.lessons, AirmiusApiContract.certificates, AirmiusApiContract.badges, AirmiusApiContract.gamificationRules]),
  _EndpointGroup(title: 'Marketplace & Commerce', body: 'Produkte, Cart, Checkout, Orders, Returns, Seller, Coupons, Payouts, Kampagnen und Ads.', status: 'Commerce', icon: Icons.storefront_outlined, color: AirmiusColors.blue, endpoints: [AirmiusApiContract.marketplaceProducts, AirmiusApiContract.marketplaceOrders, AirmiusApiContract.commerceProducts, AirmiusApiContract.commerceOrders, AirmiusApiContract.commerceCampaigns, AirmiusApiContract.commerceAds]),
  _EndpointGroup(title: 'Billing & Abos', body: 'Subscriptions, Checkout, Banktransfer, Payments, Invoices, Providerkosten und Outfit-Abos.', status: 'Billing', icon: Icons.receipt_long_outlined, color: AirmiusColors.amber, endpoints: [AirmiusApiContract.subscriptionPlans, AirmiusApiContract.subscriptions, AirmiusApiContract.billing, AirmiusApiContract.payments, AirmiusApiContract.invoices, AirmiusApiContract.outfitSubscriptions]),
  _EndpointGroup(title: 'Admin, Public & Legal', body: 'Admin, Moderation, Club-Verifizierung, Public Leads, Blog, Legal, Sponsoren und Systembetrieb.', status: 'Admin', icon: Icons.admin_panel_settings_outlined, color: AirmiusColors.green, endpoints: [AirmiusApiContract.admin, AirmiusApiContract.adminModeration, AirmiusApiContract.adminClubVerifications, AirmiusApiContract.publicLeads, AirmiusApiContract.publicBlog, AirmiusApiContract.legalPages]),
];

