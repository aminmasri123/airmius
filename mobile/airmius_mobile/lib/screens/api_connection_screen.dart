import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/api_contract.dart';
import '../widgets/airmius_widgets.dart';

class ApiConnectionScreen extends StatefulWidget {
  const ApiConnectionScreen({super.key});

  @override
  State<ApiConnectionScreen> createState() => _ApiConnectionScreenState();
}

class _ApiConnectionScreenState extends State<ApiConnectionScreen> {
  Future<AirmiusJson>? _metaFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _metaFuture ??= AirmiusServicesScope.of(context)
        .clientForSession(AirmiusServicesScope.of(context).authState.session)
        .apiMeta();
  }

  void _refreshMeta() {
    final services = AirmiusServicesScope.of(context);
    setState(() {
      _metaFuture = services
          .clientForSession(services.authState.session)
          .apiMeta();
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final services = AirmiusServicesScope.of(context);
    final activeBaseUrl = services.environment.apiBaseUrl;

    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            theme.appBarTheme.backgroundColor ?? airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'API Connection',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
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
                  const Text(
                    'Die Webversion wird nicht geraten, sondern systematisch angebunden.',
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Diese Übersicht zeigt den aktiven Laravel-Vertrag und die Antwortdaten des echten API-Clients. Der Verbindungsstatus wird direkt aus der aktuellen Anfrage gelesen.',
                    style: TextStyle(height: 1.4),
                  ),
                  const SizedBox(height: 14),
                  AirmiusPanel(
                    padding: const EdgeInsets.all(12),
                    borderColor: theme.colorScheme.primary.withValues(
                      alpha: .45,
                    ),
                    child: SelectableText(
                      activeBaseUrl,
                      style: TextStyle(
                        color: theme.colorScheme.primary,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            FutureBuilder<AirmiusJson>(
              future: _metaFuture,
              builder: (context, snapshot) {
                final data = snapshot.data?['data'];
                final meta = data is Map<String, dynamic>
                    ? data
                    : const <String, dynamic>{};
                final flags = meta['feature_flags'];
                final flagCount = flags is Map ? flags.length : 0;
                final status =
                    snapshot.connectionState == ConnectionState.waiting
                    ? '…'
                    : snapshot.hasError
                    ? 'Offline'
                    : 'Online';
                final cards = [
                  MetricCard(
                    value: meta['api_version']?.toString() ?? 'v1',
                    label: 'API',
                  ),
                  MetricCard(
                    value: meta['minimum_app_version']?.toString() ?? '-',
                    label: 'Min App',
                  ),
                  MetricCard(value: '$flagCount', label: 'Flags'),
                  MetricCard(value: status, label: 'Status'),
                ];
                return LayoutBuilder(
                  builder: (context, constraints) {
                    final columns = constraints.maxWidth < 520 ? 2 : 4;
                    final width =
                        (constraints.maxWidth - (10 * (columns - 1))) / columns;
                    return Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: cards
                          .map((card) => SizedBox(width: width, child: card))
                          .toList(),
                    );
                  },
                );
              },
            ),
            const SizedBox(height: 16),
            for (final group in _groups) ...[
              _EndpointGroupCard(group: group),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: theme.colorScheme.primary.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Live-Verbindung'),
                  const SizedBox(height: 8),
                  Text(
                    'Aktive Basis-URL: $activeBaseUrl',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label: 'API-Status aktualisieren',
                    icon: Icons.refresh_outlined,
                    onPressed: _refreshMeta,
                  ),
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
              decoration: BoxDecoration(
                color: group.color.withValues(alpha: .13),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: group.color.withValues(alpha: .45)),
              ),
              child: Icon(group.icon, color: group.color),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    group.title,
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontWeight: FontWeight.w900,
                      fontSize: 16,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    group.body,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      StatusPill(group.status, color: group.color),
                      const StatusPill('Laravel'),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        for (final endpoint in group.endpoints.map(
          AirmiusApiContract.mobileApiPath,
        )) ...[_EndpointLine(endpoint), const SizedBox(height: 8)],
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
    decoration: BoxDecoration(
      color: airmiusSurfaceSoftColor(context),
      borderRadius: BorderRadius.circular(13),
      border: Border.all(color: airmiusBorderColor(context)),
    ),
    child: SelectableText(
      endpoint,
      style: TextStyle(
        color: airmiusTextColor(context),
        fontWeight: FontWeight.w800,
        fontSize: 12,
      ),
    ),
  );
}

class _EndpointGroup {
  const _EndpointGroup({
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
    required this.color,
    required this.endpoints,
  });

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
  final List<String> endpoints;
}

final _groups = <_EndpointGroup>[
  _EndpointGroup(
    title: 'Auth & Konto',
    body:
        'Login, Registrierung, 2FA, E-Mail-Verifizierung, Sprache, Profil, Export und Kontolöschung.',
    status: 'Core',
    icon: Icons.manage_accounts_outlined,
    color: AirmiusColors.blue,
    endpoints: [
      '/api/v1/auth/login',
      '/api/v1/auth/register',
      '/api/v1/auth/two-factor-challenge',
      '/api/v1/me',
      '/api/v1/me/language',
    ],
  ),
  _EndpointGroup(
    title: 'Suche & Workspaces',
    body:
        'Globale Suche, Autocomplete, Arbeitsbereiche, Kontextwechsel und Feature-Meta.',
    status: 'Core',
    icon: Icons.manage_search_outlined,
    color: AirmiusColors.blue,
    endpoints: [
      AirmiusApiContract.globalSearch,
      AirmiusApiContract.workspaces,
      AirmiusApiContract.meta,
    ],
  ),
  _EndpointGroup(
    title: 'Vereine & Mitgliedschaft',
    body:
        'Clubprofil, Mitglieder, Anträge, Formularschema, Beitrag, Dokumente und Rückzug.',
    status: 'Verein',
    icon: Icons.groups_2_outlined,
    color: AirmiusColors.green,
    endpoints: [
      AirmiusApiContract.clubs,
      AirmiusApiContract.clubMembershipRequests(26),
      AirmiusApiContract.clubMembershipFormSchema(26),
      AirmiusApiContract.clubMembershipDocuments(26),
      AirmiusApiContract.clubMembershipRequestWithdraw(26, 1),
    ],
  ),
  _EndpointGroup(
    title: 'Teams & Events',
    body:
        'Kader, Einladungen, Join-Requests, Strafkatalog, Teamdateien, Events, Teilnahme, Warteliste und Anwesenheit.',
    status: 'Team',
    icon: Icons.diversity_3_outlined,
    color: AirmiusColors.green,
    endpoints: [
      AirmiusApiContract.teams,
      AirmiusApiContract.teamMembers(1),
      AirmiusApiContract.teamInvitations(1),
      AirmiusApiContract.teamPenalties(1),
      AirmiusApiContract.teamPenaltyRules(1),
      AirmiusApiContract.teamPenaltyFees(1),
      AirmiusApiContract.events,
      AirmiusApiContract.eventJoin(1),
    ],
  ),
  _EndpointGroup(
    title: 'Dateien & Uploads',
    body:
        'Upload, Dateimanager, Vereinsdokumente, Teamdateien, Share-Links und Mitgliedsantrag-Dateien.',
    status: 'Files',
    icon: Icons.folder_outlined,
    color: AirmiusColors.amber,
    endpoints: [
      AirmiusApiContract.uploads,
      AirmiusApiContract.files,
      AirmiusApiContract.folders,
      AirmiusApiContract.sharedFiles,
      AirmiusApiContract.clubDocuments(26),
    ],
  ),
  _EndpointGroup(
    title: 'Chat & Benachrichtigungen',
    body:
        'Notifications, Preferences, Konversationen, Nachrichten, Reaktionen, Typing, Mute und Einladungen.',
    status: 'Inbox',
    icon: Icons.forum_outlined,
    color: AirmiusColors.blue,
    endpoints: [
      AirmiusApiContract.notifications,
      AirmiusApiContract.notificationPreferences,
      AirmiusApiContract.conversations,
      AirmiusApiContract.conversationMessages(1),
      AirmiusApiContract.messagesRead(),
    ],
  ),
  _EndpointGroup(
    title: 'Social, Safety & Guardian',
    body:
        'Feed, Stories, Freunde, Fahrgemeinschaften, Guardian Consent, Maturity Gates und Reports.',
    status: 'Safety',
    icon: Icons.security_outlined,
    color: AirmiusColors.red,
    endpoints: [
      AirmiusApiContract.feed,
      AirmiusApiContract.stories,
      AirmiusApiContract.friends,
      AirmiusApiContract.carpools,
      AirmiusApiContract.guardianConsents,
      AirmiusApiContract.maturityGates,
    ],
  ),
  _EndpointGroup(
    title: 'Sport & Wellbeing',
    body:
        'Sportprofile, Ziele, Training, Logs, Nutrition, Wasser, Routen, Tracks, Orte und Coach Weekly.',
    status: 'Sport',
    icon: Icons.sports_outlined,
    color: AirmiusColors.green,
    endpoints: [
      AirmiusApiContract.sports,
      AirmiusApiContract.trainingPlans,
      AirmiusApiContract.trainingLogs,
      AirmiusApiContract.nutrition,
      AirmiusApiContract.sportRoutes,
      AirmiusApiContract.sportTracks,
    ],
  ),
  _EndpointGroup(
    title: 'Learning & Gamification',
    body:
        'Kurse, Lektionen, Quiz, Aufgaben, Zertifikate, Badges, XP-Regeln und Leaderboard.',
    status: 'Growth',
    icon: Icons.school_outlined,
    color: AirmiusColors.amber,
    endpoints: [
      AirmiusApiContract.courses,
      AirmiusApiContract.lessons,
      AirmiusApiContract.certificates,
      AirmiusApiContract.badges,
      AirmiusApiContract.gamificationRules,
    ],
  ),
  _EndpointGroup(
    title: 'Marketplace & Commerce',
    body:
        'Produkte, Cart, Checkout, Orders, Returns, Seller, Coupons, Payouts, Kampagnen und Ads.',
    status: 'Commerce',
    icon: Icons.storefront_outlined,
    color: AirmiusColors.blue,
    endpoints: [
      AirmiusApiContract.marketplaceProducts,
      AirmiusApiContract.marketplaceOrders,
      AirmiusApiContract.commerceProducts,
      AirmiusApiContract.commerceOrders,
      AirmiusApiContract.commerceCampaigns,
      AirmiusApiContract.commerceAds,
    ],
  ),
  _EndpointGroup(
    title: 'Billing & Abos',
    body:
        'Subscriptions, Checkout, Banktransfer, Payments, Invoices, Providerkosten und Outfit-Abos.',
    status: 'Billing',
    icon: Icons.receipt_long_outlined,
    color: AirmiusColors.amber,
    endpoints: [
      AirmiusApiContract.subscriptionPlans,
      AirmiusApiContract.subscriptions,
      AirmiusApiContract.billing,
      AirmiusApiContract.payments,
      AirmiusApiContract.invoices,
      AirmiusApiContract.outfitSubscriptions,
    ],
  ),
  _EndpointGroup(
    title: 'Admin, Public & Legal',
    body:
        'Admin, Moderation, Club-Verifizierung, Public Leads, Blog, Legal, Sponsoren und Systembetrieb.',
    status: 'Admin',
    icon: Icons.admin_panel_settings_outlined,
    color: AirmiusColors.green,
    endpoints: [
      AirmiusApiContract.admin,
      AirmiusApiContract.adminModeration,
      AirmiusApiContract.adminClubVerifications,
      AirmiusApiContract.publicLeads,
      AirmiusApiContract.publicBlog,
      AirmiusApiContract.legalPages,
    ],
  ),
];
