import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'commerce_center_screen.dart';
import 'sponsors_center_screen.dart';

class SponsorCockpitScreen extends StatefulWidget {
  const SponsorCockpitScreen({super.key});

  @override
  State<SponsorCockpitScreen> createState() => _SponsorCockpitScreenState();
}

class _SponsorCockpitScreenState extends State<SponsorCockpitScreen> {
  Future<Map<String, dynamic>>? _future;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final response = await _client.sponsorWorkspace();
    return _map(response['data']);
  }

  void _reload() => setState(() => _future = _load());

  void _open(Widget screen) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => screen),
    ).then((_) => mounted ? _reload() : null);
  }

  Future<void> _editProfile(Map<String, dynamic>? profile) async {
    final name = TextEditingController(text: _text(profile?['name']));
    final contact = TextEditingController(
      text: _text(profile?['contact_name']),
    );
    final email = TextEditingController(
      text: _text(
        profile?['email'],
        fallback: AirmiusServicesScope.of(context).authState.user?.email ?? '',
      ),
    );
    final website = TextEditingController(text: _text(profile?['website']));
    final logo = TextEditingController(text: _text(profile?['logo']));
    var busy = false;
    String? error;

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          Future<void> save() async {
            if (name.text.trim().isEmpty || busy) return;
            setSheetState(() {
              busy = true;
              error = null;
            });
            try {
              await _client.updateSponsorWorkspaceProfile({
                'name': name.text.trim(),
                'contact_name': contact.text.trim(),
                'email': email.text.trim(),
                'website': website.text.trim(),
                'logo': logo.text.trim(),
                'logo_light': _text(profile?['logo_light']),
                'logo_dark': _text(profile?['logo_dark']),
              });
              if (sheetContext.mounted) Navigator.pop(sheetContext, true);
            } on AirmiusApiException catch (exception) {
              setSheetState(() {
                busy = false;
                error = exception.userMessage;
              });
            } catch (_) {
              setSheetState(() {
                busy = false;
                error = t('sponsorCockpit.saveFailed');
              });
            }
          }

          return Container(
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.surface,
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(24),
              ),
            ),
            padding: EdgeInsets.fromLTRB(
              20,
              14,
              20,
              20 + MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Container(
                      width: 42,
                      height: 4,
                      decoration: BoxDecoration(
                        color: airmiusBorderColor(context),
                        borderRadius: BorderRadius.circular(99),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  Text(
                    t('sponsorCockpit.profileTitle'),
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 22,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    t('sponsorCockpit.profileBody'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 18),
                  TextField(
                    controller: name,
                    textInputAction: TextInputAction.next,
                    decoration: InputDecoration(
                      labelText: t('sponsorCockpit.brandName'),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: contact,
                    textInputAction: TextInputAction.next,
                    decoration: InputDecoration(
                      labelText: t('sponsorCockpit.contact'),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: email,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    decoration: InputDecoration(
                      labelText: t('sponsorCockpit.email'),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: website,
                    keyboardType: TextInputType.url,
                    textInputAction: TextInputAction.next,
                    decoration: InputDecoration(
                      labelText: t('sponsorCockpit.website'),
                      hintText: 'https://',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: logo,
                    keyboardType: TextInputType.url,
                    onSubmitted: (_) => save(),
                    decoration: InputDecoration(
                      labelText: t('sponsorCockpit.logoUrl'),
                      hintText: 'https://',
                    ),
                  ),
                  if (error != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      error!,
                      style: const TextStyle(
                        color: Colors.redAccent,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                  const SizedBox(height: 18),
                  FilledButton.icon(
                    onPressed: busy ? null : save,
                    icon: busy
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.save_outlined),
                    label: Text(t('sponsorCockpit.saveProfile')),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );

    name.dispose();
    contact.dispose();
    email.dispose();
    website.dispose();
    logo.dispose();

    if (saved == true && mounted) {
      _toast(t('sponsorCockpit.saved'));
      _reload();
    }
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('sponsorCockpit.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('common.refresh'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final error = snapshot.error;
            return _SponsorCockpitError(
              message: error is AirmiusApiException
                  ? error.userMessage
                  : t('sponsorCockpit.loadFailed'),
              onRetry: _reload,
            );
          }

          final data = snapshot.data ?? const <String, dynamic>{};
          final summary = _map(data['summary']);
          final capabilities = _map(data['capabilities']);
          final profile = data['own_profile'] is Map
              ? _map(data['own_profile'])
              : null;
          final campaigns = _list(data['campaigns']);
          final partners = _list(data['partners']);

          return PageFrame(
            title: t('sponsorCockpit.title'),
            subtitle: t('sponsorCockpit.subtitle'),
            onRefresh: () async => _reload(),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _hero(profile, capabilities),
                if (profile == null && capabilities['edit_own_profile'] == true)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: _profilePrompt(profile),
                  ),
                const SizedBox(height: 14),
                _metrics(summary),
                const SizedBox(height: 16),
                _campaigns(campaigns),
                const SizedBox(height: 16),
                _partners(partners),
                const SizedBox(height: 16),
                _nextStep(summary, profile),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _hero(
    Map<String, dynamic>? profile,
    Map<String, dynamic> capabilities,
  ) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('sponsorCockpit.eyebrow')),
          const SizedBox(height: 8),
          Text(
            t('sponsorCockpit.heroTitle'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 22,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            t('sponsorCockpit.heroBody'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.45),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              FilledButton.icon(
                onPressed: () => _open(
                  const CommerceCenterScreen(
                    initialSection: 'ads',
                    openCampaignComposer: true,
                  ),
                ),
                icon: const Icon(Icons.add),
                label: Text(t('sponsorCockpit.createCampaign')),
              ),
              if (capabilities['edit_own_profile'] == true)
                OutlinedButton.icon(
                  onPressed: () => _editProfile(profile),
                  icon: const Icon(Icons.business_outlined),
                  label: Text(
                    profile == null
                        ? t('sponsorCockpit.createProfile')
                        : t('sponsorCockpit.editProfile'),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _profilePrompt(Map<String, dynamic>? profile) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.info_outline, color: airmiusAccentColor(context)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  t('sponsorCockpit.profileMissing'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  t('sponsorCockpit.profileMissingBody'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                TextButton(
                  onPressed: () => _editProfile(profile),
                  child: Text(t('sponsorCockpit.setupNow')),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _metrics(Map<String, dynamic> summary) {
    final locale = Localizations.localeOf(context).toLanguageTag();
    final number = NumberFormat.decimalPattern(locale);
    final currency = NumberFormat.currency(locale: locale, symbol: '€');
    final values = [
      (
        number.format(_int(summary['active_partners'])),
        t('sponsorCockpit.partners'),
      ),
      (
        number.format(_int(summary['active_campaigns'])),
        t('sponsorCockpit.activeCampaigns'),
      ),
      (
        number.format(_int(summary['impressions'])),
        t('sponsorCockpit.impressions'),
      ),
      (
        '${_double(summary['ctr']).toStringAsFixed(1)} %',
        t('sponsorCockpit.ctr'),
      ),
      (
        currency.format(_int(summary['budget_cents']) / 100),
        t('sponsorCockpit.budget'),
      ),
      (number.format(_int(summary['clicks'])), t('sponsorCockpit.clicks')),
    ];

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 1.65,
      ),
      itemCount: values.length,
      itemBuilder: (_, index) =>
          MetricCard(value: values[index].$1, label: values[index].$2),
    );
  }

  Widget _campaigns(List<Map<String, dynamic>> campaigns) {
    final locale = Localizations.localeOf(context).toLanguageTag();
    final number = NumberFormat.decimalPattern(locale);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _sectionHeader(
            t('sponsorCockpit.campaigns'),
            t('sponsorCockpit.openCampaigns'),
            () => _open(const CommerceCenterScreen(initialSection: 'ads')),
          ),
          const SizedBox(height: 12),
          if (campaigns.isEmpty)
            _empty(
              Icons.campaign_outlined,
              t('sponsorCockpit.noCampaigns'),
              t('sponsorCockpit.noCampaignsBody'),
            )
          else
            ...campaigns
                .take(5)
                .map(
                  (campaign) => Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.all(13),
                    decoration: BoxDecoration(
                      color: airmiusSurfaceSoftColor(context),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: airmiusBorderColor(context)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                _text(
                                  campaign['headline'],
                                  fallback: _text(campaign['name']),
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: airmiusTextColor(context),
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                            _status(_text(campaign['status'])),
                          ],
                        ),
                        const SizedBox(height: 9),
                        Text(
                          '${number.format(_int(campaign['impressions']))} ${t('sponsorCockpit.impressions')}  ·  '
                          '${number.format(_int(campaign['clicks']))} ${t('sponsorCockpit.clicks')}  ·  '
                          '${_double(campaign['ctr']).toStringAsFixed(1)} % CTR',
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
        ],
      ),
    );
  }

  Widget _partners(List<Map<String, dynamic>> partners) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _sectionHeader(
            t('sponsorCockpit.partnerships'),
            t('sponsorCockpit.publicView'),
            () => _open(const SponsorsCenterScreen()),
          ),
          const SizedBox(height: 12),
          if (partners.isEmpty)
            _empty(
              Icons.handshake_outlined,
              t('sponsorCockpit.noPartners'),
              t('sponsorCockpit.noPartnersBody'),
            )
          else
            ...partners
                .take(5)
                .map(
                  (partner) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: CircleAvatar(
                      backgroundColor: airmiusSurfaceSoftColor(context),
                      child: const Icon(Icons.handshake_outlined),
                    ),
                    title: Text(
                      _text(partner['name']),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    subtitle: Text(
                      _text(
                        _map(partner['club'])['name'],
                        fallback: t('sponsorCockpit.platform'),
                      ),
                    ),
                    trailing: _status(_text(partner['status'])),
                  ),
                ),
        ],
      ),
    );
  }

  Widget _nextStep(
    Map<String, dynamic> summary,
    Map<String, dynamic>? profile,
  ) {
    final hasActiveCampaign = _int(summary['active_campaigns']) > 0;
    final title = profile == null
        ? t('sponsorCockpit.nextProfile')
        : hasActiveCampaign
        ? t('sponsorCockpit.nextMeasure')
        : t('sponsorCockpit.nextCampaign');
    final body = profile == null
        ? t('sponsorCockpit.nextProfileBody')
        : hasActiveCampaign
        ? t('sponsorCockpit.nextMeasureBody')
        : t('sponsorCockpit.nextCampaignBody');
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Eyebrow(t('sponsorCockpit.nextStep')),
          const SizedBox(height: 8),
          Text(
            title,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            body,
            style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
          ),
        ],
      ),
    );
  }

  Widget _sectionHeader(String title, String action, VoidCallback onTap) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        TextButton(onPressed: onTap, child: Text(action)),
      ],
    );
  }

  Widget _empty(IconData icon, String title, String body) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 14),
      child: Column(
        children: [
          Icon(icon, size: 38, color: airmiusMutedColor(context)),
          const SizedBox(height: 10),
          Text(
            title,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            body,
            textAlign: TextAlign.center,
            style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
          ),
        ],
      ),
    );
  }

  Widget _status(String value) {
    final active = value == 'active';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: active
            ? Colors.green.withValues(alpha: 0.12)
            : airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(99),
        border: Border.all(
          color: active
              ? Colors.green.withValues(alpha: 0.5)
              : airmiusBorderColor(context),
        ),
      ),
      child: Text(
        t('sponsorCockpit.status.$value'),
        style: TextStyle(
          color: active ? Colors.greenAccent : airmiusMutedColor(context),
          fontSize: 10,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _SponsorCockpitError extends StatelessWidget {
  const _SponsorCockpitError({required this.message, required this.onRetry});

  final String message;
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
            const Icon(Icons.cloud_off_outlined, size: 48),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: Text(t('common.retry')),
            ),
          ],
        ),
      ),
    );
  }
}

Map<String, dynamic> _map(Object? value) => value is Map
    ? value.map((key, item) => MapEntry('$key', item))
    : <String, dynamic>{};

List<Map<String, dynamic>> _list(Object? value) => value is List
    ? value.whereType<Map>().map(_map).toList(growable: false)
    : const [];

String _text(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;

double _double(Object? value) => value is num
    ? value.toDouble()
    : double.tryParse(value?.toString() ?? '') ?? 0;
