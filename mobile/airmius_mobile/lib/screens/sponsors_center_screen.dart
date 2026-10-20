import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'sponsor_management_screen.dart';
import 'sponsor_application_screen.dart';
import 'sponsor_discovery_detail_screen.dart';

class SponsorsCenterScreen extends StatefulWidget {
  const SponsorsCenterScreen({super.key, this.initialSponsorId});

  final int? initialSponsorId;

  @override
  State<SponsorsCenterScreen> createState() => _SponsorsCenterScreenState();
}

class _SponsorsCenterScreenState extends State<SponsorsCenterScreen> {
  Future<JsonMap>? _future;
  String _scope = 'all';
  String _query = '';
  String _country = 'all';
  String _region = 'all';
  int? _nearbyClubId;
  bool _openedInitialSponsor = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  String _tx(String de, String en) =>
      Localizations.localeOf(context).languageCode == 'de' ? de : en;

  bool get _canManage {
    final user = AirmiusServicesScope.of(context).authState.user;
    return user?.can('finance.edit') == true ||
        user?.can('system.manage') == true ||
        user?.hasAnyRole(const [
              'club_owner',
              'club_admin',
              'club_manager',
              'academy_manager',
              'financial_controller',
              'super_admin',
              'admin',
            ]) ==
            true;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<JsonMap> _load() async {
    final authenticated = AirmiusServicesScope.of(context).authState.isAuthenticated;
    final public = await _client.publicSponsors();
    if (!authenticated) return public;
    final inquiries = await _client.sponsorInquiries();
    return {...public, 'inquiries': inquiries};
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  Future<void> _openWebsite(String value) async {
    final uri = safeExternalHttpUrl(value, httpsOnly: false);
    if (uri == null) {
      _toast(t('sponsors.invalidWebsite'));
      return;
    }
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) &&
        mounted) {
      _toast(t('sponsors.openFailed'));
    }
  }

  void _toast(String value) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(value)));
  }

  Future<void> _openSponsor(JsonMap sponsor, List<JsonMap> clubs, List<JsonMap> outgoing) async {
    await Navigator.push(context, MaterialPageRoute(builder: (_) => SponsorDiscoveryDetailScreen(
      sponsor: sponsor, clubs: clubs, outgoing: outgoing,
    )));
    if (mounted) _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            Theme.of(context).colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('sponsors.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          if (_canManage)
            IconButton(
              tooltip: t('sponsorAdmin.open'),
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const SponsorManagementScreen(),
                ),
              ).then((_) => _reload()),
              icon: const Icon(Icons.manage_accounts_outlined),
            ),
          IconButton(
            tooltip: t('sponsors.reload'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<JsonMap>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _SponsorError(onRetry: _reload);
          }
          final sponsors = _sponsorList(snapshot.data?['data']);
          final stats = _sponsorMap(snapshot.data?['stats']);
          final inquiries = _sponsorMap(snapshot.data?['inquiries']);
          final clubs = _sponsorList(inquiries['clubs']);
          final outgoing = _sponsorList(inquiries['outgoing']);
          final countries = sponsors.map((s) => _sponsorText(s['country_code'])).where((s) => s.isNotEmpty).toSet().toList()..sort();
          final regions = sponsors.where((s) => _country == 'all' || _sponsorText(s['country_code']) == _country)
              .map((s) => _sponsorText(s['region'])).where((s) => s.isNotEmpty).toSet().toList()..sort();
          final nearClub = clubs.where((club) => _sponsorInt(club['id']) == _nearbyClubId).firstOrNull;
          final visible = sponsors.where((sponsor) {
            final haystack = [sponsor['name'], sponsor['city'], sponsor['region'], sponsor['country_code']]
                .map((value) => _sponsorText(value).toLowerCase()).join(' ');
            return (_scope == 'all' || _sponsorText(sponsor['scope']) == _scope)
                && (_country == 'all' || _sponsorText(sponsor['country_code']) == _country)
                && (_region == 'all' || _sponsorText(sponsor['region']) == _region)
                && (nearClub == null || (_sponsorText(sponsor['city']).isNotEmpty
                    && _sponsorText(sponsor['city']).toLowerCase() == _sponsorText(nearClub['city']).toLowerCase()))
                && haystack.contains(_query.trim().toLowerCase());
          }).toList();
          if (!_openedInitialSponsor && widget.initialSponsorId != null) {
            _openedInitialSponsor = true;
            final target = sponsors.where((s) => _sponsorInt(s['id']) == widget.initialSponsorId).firstOrNull;
            if (target != null) WidgetsBinding.instance.addPostFrameCallback((_) {
              if (mounted) _openSponsor(target, clubs, outgoing);
            });
          }

          return PageFrame(
            title: t('sponsors.title'),
            subtitle: clubs.isEmpty
                ? t('sponsors.subtitle')
                : _tx('Finde passende Unternehmen und frage für deinen Verein Sponsoring an.',
                    'Find suitable companies and request sponsorship for your club.'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextField(
                  decoration: InputDecoration(labelText: _tx('Sponsor suchen', 'Search sponsors'), prefixIcon: const Icon(Icons.search)),
                  onChanged: (value) => setState(() => _query = value),
                ),
                const SizedBox(height: 12),
                Row(children: [
                  Expanded(child: DropdownButtonFormField<String>(
                    initialValue: _country, decoration: InputDecoration(labelText: _tx('Land', 'Country')),
                    items: ['all', ...countries].map((value) => DropdownMenuItem(value: value, child: Text(value == 'all' ? _tx('Alle', 'All') : value))).toList(),
                    onChanged: (value) => setState(() { _country = value ?? 'all'; _region = 'all'; }),
                  )),
                  const SizedBox(width: 10),
                  Expanded(child: DropdownButtonFormField<String>(
                    key: ValueKey(_country), initialValue: regions.contains(_region) ? _region : 'all',
                    decoration: InputDecoration(labelText: _tx('Region', 'Region')),
                    items: ['all', ...regions].map((value) => DropdownMenuItem(value: value, child: Text(value == 'all' ? _tx('Alle', 'All') : value))).toList(),
                    onChanged: (value) => setState(() => _region = value ?? 'all'),
                  )),
                ]),
                if (clubs.any((club) => _sponsorText(club['city']).isNotEmpty)) ...[
                  const SizedBox(height: 10),
                  DropdownButtonFormField<int?>(
                    initialValue: _nearbyClubId,
                    decoration: InputDecoration(labelText: _tx('In meinem Ort', 'In my city')),
                    items: [
                      DropdownMenuItem<int?>(value: null, child: Text(_tx('Überall suchen', 'Search everywhere'))),
                      ...clubs.where((club) => _sponsorText(club['city']).isNotEmpty).map((club) =>
                          DropdownMenuItem<int?>(value: _sponsorInt(club['id']), child: Text('${_sponsorText(club['name'])} · ${_sponsorText(club['city'])}'))),
                    ],
                    onChanged: (value) => setState(() => _nearbyClubId = value),
                  ),
                ],
                const SizedBox(height: 16),
                if (clubs.isEmpty) ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: AspectRatio(
                    aspectRatio: 4 / 5,
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        Image.asset(
                          'assets/images/sponsors/community-impact-hero.png',
                          fit: BoxFit.cover,
                          alignment: const Alignment(0.35, 0),
                        ),
                        const DecoratedBox(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [
                                Colors.transparent,
                                Color(0x66000000),
                                Color(0xEE000000),
                              ],
                              stops: [0, .42, 1],
                            ),
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.all(22),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.end,
                            children: [
                              Eyebrow(
                                _tx(
                                  'Partnerschaften mit Wirkung',
                                  'Partnerships with impact',
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                _tx(
                                  'Sport fördern. Region stärken. Marke sichtbar machen.',
                                  'Support sport. Strengthen communities. Build brand visibility.',
                                ),
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 27,
                                  height: 1.12,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              const SizedBox(height: 10),
                              Text(
                                _tx(
                                  'Entdecke Vereine und Projekte, die zu deinem Unternehmen passen.',
                                  'Discover clubs and projects that fit your business.',
                                ),
                                style: const TextStyle(
                                  color: Color(0xFFE5E7EB),
                                  height: 1.4,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                if (clubs.isEmpty) _SponsorBenefits(text: _tx),
                const SizedBox(height: 14),
                if (clubs.isEmpty) AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        t('sponsorApplication.title'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        t('sponsorApplication.intro'),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 12),
                      AirmiusButton(
                        label: t('sponsorApplication.open'),
                        icon: Icons.handshake_outlined,
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const SponsorApplicationScreen(),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: MetricCard(
                        value: '${_sponsorInt(stats['total'])}',
                        label: t('sponsors.all'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(
                        value: '${_sponsorInt(stats['platform'])}',
                        label: t('sponsors.platform'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(
                        value: '${_sponsorInt(stats['club'])}',
                        label: t('sponsors.clubs'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children:
                        [
                          ('all', 'sponsors.all'),
                          ('platform', 'sponsors.platform'),
                          ('club', 'sponsors.clubs'),
                          ('outfit_subscription', 'sponsors.outfit'),
                        ].map((option) {
                          final selected = _scope == option.$1;
                          return ChoiceChip(
                            selected: selected,
                            label: Text(t(option.$2)),
                            onSelected: (_) =>
                                setState(() => _scope = option.$1),
                            selectedColor: airmiusAccentColor(
                              context,
                            ).withValues(alpha: 0.22),
                            backgroundColor: airmiusSurfaceSoftColor(context),
                            side: BorderSide(
                              color: selected
                                  ? airmiusAccentColor(context)
                                  : airmiusBorderColor(context),
                            ),
                            labelStyle: TextStyle(
                              color: selected
                                  ? airmiusAccentColor(context)
                                  : airmiusMutedColor(context),
                              fontWeight: FontWeight.w900,
                            ),
                          );
                        }).toList(),
                  ),
                ),
                const SizedBox(height: 14),
                if (visible.isEmpty)
                  AirmiusPanel(
                    child: Text(
                      t('sponsors.empty'),
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.4,
                      ),
                    ),
                  )
                else
                  ...visible.map(
                    (sponsor) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _SponsorCard(
                        sponsor: sponsor,
                        onOpen: () => _openSponsor(sponsor, clubs, outgoing),
                        onWebsite: () =>
                            _openWebsite(_sponsorText(sponsor['website'])),
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _SponsorBenefits extends StatelessWidget {
  const _SponsorBenefits({required this.text});

  final String Function(String de, String en) text;

  @override
  Widget build(BuildContext context) {
    final items = [
      (
        Icons.location_on_outlined,
        text('Regionale Sichtbarkeit', 'Local visibility'),
        text(
          'Erreiche Mitglieder, Familien und Unterstützer direkt im Vereinsumfeld.',
          'Reach members, families and supporters in their club community.',
        ),
      ),
      (
        Icons.favorite_border,
        text('Nachvollziehbare Wirkung', 'Visible impact'),
        text(
          'Zeige transparent, welche Projekte und Ausrüstung du ermöglichst.',
          'Show which projects and equipment your support makes possible.',
        ),
      ),
      (
        Icons.handshake_outlined,
        text('Passende Partnerschaften', 'Relevant partnerships'),
        text(
          'Finde Engagements passend zu Marke, Region und Zielgruppe.',
          'Find opportunities aligned with your brand, region and audience.',
        ),
      ),
    ];

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            text('Warum Vereine fördern?', 'Why support clubs?'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 19,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            text(
              'Engagement, das Menschen erreicht und sichtbar bleibt.',
              'Support that reaches people and remains visible.',
            ),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
          ),
          const SizedBox(height: 14),
          for (var index = 0; index < items.length; index++) ...[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(items[index].$1, color: airmiusAccentColor(context)),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        items[index].$2,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        items[index].$3,
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
            if (index != items.length - 1) ...[
              const SizedBox(height: 14),
              Divider(color: airmiusBorderColor(context), height: 1),
              const SizedBox(height: 14),
            ],
          ],
        ],
      ),
    );
  }
}

class _SponsorCard extends StatelessWidget {
  const _SponsorCard({required this.sponsor, required this.onWebsite, required this.onOpen});

  final JsonMap sponsor;
  final VoidCallback onWebsite;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final logo = resolveAirmiusImageUrl(_sponsorText(sponsor['logo_url']));
    final website = _sponsorText(sponsor['website']);
    final club = _sponsorText(_sponsorMap(sponsor['club'])['name']);
    final scope = _sponsorText(sponsor['scope'], fallback: 'platform');
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 72,
            height: 72,
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: airmiusSurfaceColor(context),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: airmiusBorderColor(context)),
            ),
            child: logo == null
                ? Icon(
                    Icons.handshake_outlined,
                    color: airmiusAccentColor(context),
                    size: 32,
                  )
                : Image.network(
                    logo,
                    fit: BoxFit.contain,
                    errorBuilder: (_, _, _) => Icon(
                      Icons.handshake_outlined,
                      color: airmiusAccentColor(context),
                    ),
                  ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _sponsorText(
                    sponsor['name'],
                    fallback: t('sponsors.partner'),
                  ),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (_sponsorText(sponsor['city']).isNotEmpty || _sponsorText(sponsor['region']).isNotEmpty)
                  Text([sponsor['city'], sponsor['region']].map(_sponsorText).where((value) => value.isNotEmpty).join(' · ')),
                const SizedBox(height: 7),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(t('sponsors.scope.$scope')),
                    if (club.isNotEmpty)
                      StatusPill(
                        club,
                        color: Theme.of(context).colorScheme.secondary,
                      ),
                  ],
                ),
                if (website.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  AirmiusButton(
                    label: t('sponsors.website'),
                    icon: Icons.open_in_new_outlined,
                    secondary: true,
                    onPressed: onWebsite,
                  ),
                ],
                const SizedBox(height: 8),
                TextButton.icon(onPressed: onOpen, icon: const Icon(Icons.arrow_forward), label: Text(Localizations.localeOf(context).languageCode == 'de' ? 'Profil ansehen' : 'View profile')),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SponsorError extends StatelessWidget {
  const _SponsorError({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.handshake_outlined,
              color: Theme.of(context).colorScheme.error,
              size: 44,
            ),
            const SizedBox(height: 12),
            Text(t('sponsors.loadError')),
            const SizedBox(height: 12),
            AirmiusButton(
              label: t('sponsors.retry'),
              icon: Icons.refresh_outlined,
              onPressed: onRetry,
            ),
          ],
        ),
      ),
    );
  }
}

JsonMap _sponsorMap(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

List<JsonMap> _sponsorList(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <JsonMap>[];

String _sponsorText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _sponsorInt(Object? value) =>
    value is int ? value : int.tryParse('${value ?? ''}') ?? 0;
