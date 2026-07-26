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

class SponsorsCenterScreen extends StatefulWidget {
  const SponsorsCenterScreen({super.key});

  @override
  State<SponsorsCenterScreen> createState() => _SponsorsCenterScreenState();
}

class _SponsorsCenterScreenState extends State<SponsorsCenterScreen> {
  Future<JsonMap>? _future;
  String _scope = 'all';

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

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
    _future ??= _client.publicSponsors();
  }

  void _reload() {
    setState(() {
      _future = _client.publicSponsors();
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
          final visible = _scope == 'all'
              ? sponsors
              : sponsors
                    .where(
                      (sponsor) => _sponsorText(sponsor['scope']) == _scope,
                    )
                    .toList();

          return PageFrame(
            title: t('sponsors.title'),
            subtitle: t('sponsors.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('sponsors.partnerships')),
                      const SizedBox(height: 8),
                      Text(
                        t('sponsors.hero'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        t('sponsors.transparency'),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          height: 1.4,
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

class _SponsorCard extends StatelessWidget {
  const _SponsorCard({required this.sponsor, required this.onWebsite});

  final JsonMap sponsor;
  final VoidCallback onWebsite;

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
