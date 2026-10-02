import 'dart:convert';

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

  void _reload() {
    final future = _load();
    setState(() {
      _future = future;
    });
  }

  void _open(Widget screen) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => screen),
    ).then((_) => mounted ? _reload() : null);
  }

  Future<void> _editProfile(Map<String, dynamic>? profile) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _SponsorProfileEditor(
        client: _client,
        profile: profile,
        accountEmail:
            AirmiusServicesScope.of(context).authState.user?.email ?? '',
      ),
    );
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
                if (profile != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: AirmiusPanel(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _text(profile['name']),
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                          Text(_profileCopy(context, 'verification_title')),
                          Text(
                            _profileCopy(
                              context,
                              'verification_${_text(profile['verification_status'], fallback: 'pending_review')}',
                            ),
                          ),
                          if (_text(profile['verification_note']).isNotEmpty)
                            Text(_text(profile['verification_note'])),
                        ],
                      ),
                    ),
                  ),
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
                _nextStep(summary, profile, capabilities),
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
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        mainAxisExtent: 60 + MediaQuery.textScalerOf(context).scale(100),
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
    Map<String, dynamic> capabilities,
  ) {
    final hasActiveCampaign = _int(summary['active_campaigns']) > 0;
    final needsProfile =
        profile == null && capabilities['edit_own_profile'] == true;
    final title = needsProfile
        ? t('sponsorCockpit.nextProfile')
        : hasActiveCampaign
        ? t('sponsorCockpit.nextMeasure')
        : t('sponsorCockpit.nextCampaign');
    final body = needsProfile
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
        Flexible(
          child: TextButton(onPressed: onTap, child: Text(action)),
        ),
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

class _SponsorProfileEditor extends StatefulWidget {
  const _SponsorProfileEditor({
    required this.client,
    required this.profile,
    required this.accountEmail,
  });

  final AirmiusApiClient client;
  final Map<String, dynamic>? profile;
  final String accountEmail;

  @override
  State<_SponsorProfileEditor> createState() => _SponsorProfileEditorState();
}

class _SponsorProfileEditorState extends State<_SponsorProfileEditor> {
  final _form = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _fields;
  late bool _legalAccuracy;
  late bool _dataPrivacy;
  bool _busy = false;
  String? _error;
  Map<String, String> _errors = {};

  @override
  void initState() {
    super.initState();
    _fields = {
      for (final key in [
        'name',
        'legal_name',
        'country_code',
        'registration_number',
        'vat_id',
        'contact_name',
        'email',
        'website',
        'logo',
        'logo_light',
        'logo_dark',
      ])
        key: TextEditingController(
          text: _text(
            widget.profile?[key],
            fallback: switch (key) {
              'country_code' => 'DE',
              'email' => widget.accountEmail,
              _ => '',
            },
          ),
        ),
    };
    _legalAccuracy = widget.profile?['legal_accuracy_accepted'] == true;
    _dataPrivacy = widget.profile?['data_privacy_accepted'] == true;
  }

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (_busy) return;
    final t = AirmiusScope.of(context).t;
    setState(() {
      _error = null;
      _errors = {};
    });
    final valid = _form.currentState!.validate();
    if (!valid || !_legalAccuracy || !_dataPrivacy) {
      setState(() => _error = t('seller.required'));
      return;
    }
    setState(() => _busy = true);
    try {
      // Only the self-service fields are writable; ownership and verification
      // remain server-controlled for both creation and editing.
      final response = await widget.client.updateSponsorWorkspaceProfile({
        for (final entry in _fields.entries)
          entry.key: entry.key == 'country_code'
              ? entry.value.text.trim().toUpperCase()
              : entry.value.text.trim(),
        'rule_legal_accuracy': _legalAccuracy,
        'rule_data_privacy': _dataPrivacy,
      });
      if (!mounted) return;
      if (response['queued'] == true) {
        setState(() {
          _busy = false;
          _error = _profileCopy(context, 'queued');
        });
        return;
      }
      // A dismissed sheet can stay mounted during its exit animation.
      if (ModalRoute.of(context)?.isCurrent == true) {
        Navigator.pop(context, true);
      }
    } on AirmiusApiException catch (exception) {
      if (!mounted) return;
      final errors = <String, String>{};
      try {
        final body = _map(jsonDecode(exception.body));
        for (final entry in _map(body['errors']).entries) {
          if (_fields.containsKey(entry.key) ||
              entry.key == 'rule_legal_accuracy' ||
              entry.key == 'rule_data_privacy') {
            // Retain the API client's diagnostic filtering for inline errors.
            errors[entry.key] = AirmiusApiException(
              statusCode: exception.statusCode,
              path: exception.path,
              body: jsonEncode({
                'errors': {entry.key: entry.value},
              }),
            ).userMessage;
          }
        }
      } catch (_) {
        // Non-JSON failures still use the sanitized general message.
      }
      setState(() {
        _busy = false;
        _error = exception.userMessage;
        _errors = errors;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = t('sponsorCockpit.saveFailed');
      });
    }
  }

  Widget _field(
    String key,
    String label, {
    bool required = false,
    int max = 255,
  }) {
    final isUrl = key == 'website' || key.startsWith('logo');
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        key: ValueKey('sponsor-profile-$key'),
        controller: _fields[key],
        enabled: !_busy,
        maxLength: max,
        textCapitalization: key == 'country_code'
            ? TextCapitalization.characters
            : TextCapitalization.none,
        keyboardType: isUrl
            ? TextInputType.url
            : key == 'email'
            ? TextInputType.emailAddress
            : TextInputType.text,
        textInputAction: TextInputAction.next,
        decoration: InputDecoration(
          labelText: required ? '$label *' : label,
          hintText: isUrl ? 'https://' : null,
          errorText: _errors[key],
          errorMaxLines: 4,
          counterText: '',
        ),
        onChanged: (_) {
          if (_errors.containsKey(key)) setState(() => _errors.remove(key));
        },
        validator: (value) {
          final text = value?.trim() ?? '';
          if (required && text.isEmpty) return t('seller.required');
          if (key == 'country_code' && text.length != 2) {
            return t('seller.countryCodeHint');
          }
          return null;
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SingleChildScrollView(
        padding: EdgeInsets.fromLTRB(
          20,
          20,
          20,
          20 + MediaQuery.viewPaddingOf(context).bottom,
        ),
        child: Form(
          key: _form,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      t(
                        widget.profile == null
                            ? 'sponsorCockpit.createProfile'
                            : 'sponsorCockpit.editProfile',
                      ),
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                  ),
                  IconButton(
                    tooltip: t('common.cancel'),
                    onPressed: _busy ? null : () => Navigator.pop(context),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              Text(t('sponsorCockpit.profileBody')),
              const SizedBox(height: 8),
              Text(_profileCopy(context, 'review_after_save')),
              const SizedBox(height: 18),
              _field('name', t('sponsorCockpit.brandName'), required: true),
              _field(
                'legal_name',
                _profileCopy(context, 'legal_name'),
                required: true,
              ),
              _field(
                'country_code',
                _profileCopy(context, 'country'),
                required: true,
                max: 2,
              ),
              _field(
                'registration_number',
                _profileCopy(context, 'registration'),
                max: 120,
              ),
              _field('vat_id', _profileCopy(context, 'vat_id'), max: 80),
              _field('contact_name', t('sponsorCockpit.contact')),
              _field('email', t('sponsorCockpit.email')),
              _field('website', t('sponsorCockpit.website')),
              _field('logo', t('sponsorCockpit.logoUrl'), max: 2048),
              _field(
                'logo_light',
                _profileCopy(context, 'logo_light'),
                max: 2048,
              ),
              _field(
                'logo_dark',
                _profileCopy(context, 'logo_dark'),
                max: 2048,
              ),
              CheckboxListTile(
                key: const ValueKey('sponsor-profile-rule_legal_accuracy'),
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                title: Text(_profileCopy(context, 'legal_accuracy')),
                subtitle: _errors['rule_legal_accuracy'] == null
                    ? null
                    : Text(
                        _errors['rule_legal_accuracy']!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                value: _legalAccuracy,
                onChanged: _busy
                    ? null
                    : (value) => setState(() {
                        _legalAccuracy = value == true;
                        _errors.remove('rule_legal_accuracy');
                      }),
              ),
              CheckboxListTile(
                key: const ValueKey('sponsor-profile-rule_data_privacy'),
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                title: Text(_profileCopy(context, 'data_privacy')),
                subtitle: _errors['rule_data_privacy'] == null
                    ? null
                    : Text(
                        _errors['rule_data_privacy']!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                value: _dataPrivacy,
                onChanged: _busy
                    ? null
                    : (value) => setState(() {
                        _dataPrivacy = value == true;
                        _errors.remove('rule_data_privacy');
                      }),
              ),
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
              const SizedBox(height: 18),
              FilledButton.icon(
                key: const ValueKey('sponsor-profile-save'),
                onPressed: _busy ? null : _save,
                icon: _busy
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
      ),
    );
  }
}

// Keep sponsor-only copy local while sharing the web workspace's legal wording.
String _profileCopy(BuildContext context, String key) {
  final language = AirmiusScope.of(context).language;
  return _profileMessages[language]?[key] ??
      _profileMessages[AirmiusLanguage.en]?[key] ??
      key;
}

const _profileMessages = <AirmiusLanguage, Map<String, String>>{
  AirmiusLanguage.de: {
    'legal_name': 'Rechtlicher Unternehmens-/Organisationsname',
    'country': 'Sitzland',
    'registration': 'Register-/Vereinsnummer (optional)',
    'vat_id': 'USt-IdNr. (optional)',
    'legal_accuracy':
        'Ich bestätige, dass die rechtlichen Angaben korrekt sind und ich für die Organisation handeln darf.',
    'data_privacy':
        'Ich nutze Kontakt- und Kampagnendaten nur zweckgebunden und beachte die Datenschutzregeln.',
    'verification_title': 'Öffentliche Verifizierung',
    'verification_pending_review':
        'Deine Angaben werden geprüft und sind bis zur Freigabe nicht öffentlich sichtbar.',
    'verification_verified':
        'Dein geprüftes Sponsorprofil ist öffentlich sichtbar.',
    'verification_rejected':
        'Bitte korrigiere die beanstandeten Angaben und reiche das Profil erneut ein.',
    'logo_light': 'Logo für hellen Hintergrund',
    'logo_dark': 'Logo für dunklen Hintergrund',
    'review_after_save': 'Nach dem Speichern wird das Profil erneut geprüft.',
    'queued':
        'Das Profil wurde zur späteren Übertragung vorgemerkt. Die Speicherung auf dem Server ist noch nicht bestätigt.',
  },
  AirmiusLanguage.en: {
    'legal_name': 'Legal company/organisation name',
    'country': 'Country of establishment',
    'registration': 'Registration number (optional)',
    'vat_id': 'VAT ID (optional)',
    'legal_accuracy':
        'I confirm the legal details are correct and I am authorised to act for the organisation.',
    'data_privacy':
        'I use contact and campaign data only for its intended purpose and follow privacy rules.',
    'verification_title': 'Public verification',
    'verification_pending_review':
        'Your details are under review and remain private until approved.',
    'verification_verified':
        'Your verified sponsor profile is publicly visible.',
    'verification_rejected':
        'Correct the flagged details and submit the profile again.',
    'logo_light': 'Logo for light backgrounds',
    'logo_dark': 'Logo for dark backgrounds',
    'review_after_save': 'Saving submits the profile for review again.',
    'queued':
        'The profile is queued for later delivery. Saving on the server is not yet confirmed.',
  },
  AirmiusLanguage.fr: {
    'legal_name': 'Raison sociale de l’entreprise/organisation',
    'country': 'Pays d’établissement',
    'registration': 'Numéro d’immatriculation (facultatif)',
    'vat_id': 'N° TVA (facultatif)',
    'legal_accuracy':
        'Je confirme que les informations légales sont exactes et que je peux agir pour l’organisation.',
    'data_privacy':
        'J’utilise les données de contact et de campagne uniquement aux fins prévues et respecte les règles de confidentialité.',
    'verification_title': 'Vérification publique',
    'verification_pending_review':
        'Vos informations sont en cours de vérification et restent privées jusqu’à leur approbation.',
    'verification_verified':
        'Votre profil sponsor vérifié est visible publiquement.',
    'verification_rejected':
        'Corrigez les informations signalées et renvoyez le profil.',
    'logo_light': 'Logo sur fond clair',
    'logo_dark': 'Logo sur fond sombre',
    'review_after_save':
        'Après enregistrement, le profil sera à nouveau examiné.',
    'queued':
        'Le profil est en attente de transmission. Son enregistrement sur le serveur n’est pas encore confirmé.',
  },
  AirmiusLanguage.ar: {
    'legal_name': 'الاسم القانوني للشركة أو المؤسسة',
    'country': 'بلد التأسيس',
    'registration': 'رقم التسجيل (اختياري)',
    'vat_id': 'الرقم الضريبي (اختياري)',
    'legal_accuracy':
        'أؤكد صحة البيانات القانونية وأنني مخوّل بالتصرف نيابةً عن المؤسسة.',
    'data_privacy':
        'أستخدم بيانات الاتصال والحملات للغرض المحدد فقط وألتزم بقواعد الخصوصية.',
    'verification_title': 'التحقق العلني',
    'verification_pending_review':
        'بياناتك قيد المراجعة وستبقى غير علنية حتى الموافقة.',
    'verification_verified': 'ملف الراعي الموثّق ظاهر للعامة.',
    'verification_rejected':
        'صحّح البيانات المشار إليها ثم أرسل الملف من جديد.',
    'logo_light': 'شعار للخلفيات الفاتحة',
    'logo_dark': 'شعار للخلفيات الداكنة',
    'review_after_save': 'يُرسل الملف للمراجعة مجدداً عند الحفظ.',
    'queued':
        'الملف في انتظار الإرسال لاحقاً. لم يتم تأكيد حفظه على الخادم بعد.',
  },
};

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
