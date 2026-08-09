import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'privacy_data_erasure_sheet.dart';
import 'sport_integrations_screen.dart';

class PrivacyConsentCenterScreen extends StatefulWidget {
  const PrivacyConsentCenterScreen({super.key});

  @override
  State<PrivacyConsentCenterScreen> createState() =>
      _PrivacyConsentCenterScreenState();
}

class _PrivacyConsentCenterScreenState
    extends State<PrivacyConsentCenterScreen> {
  Future<_PrivacyBundle>? _future;
  bool _busy = false;
  String _profileVisibility = 'public';
  String _directMessagePrivacy = 'everyone';
  String _friendRequestPrivacy = 'everyone';
  bool _adsPersonalization = false;
  bool _adsMeasurement = false;
  bool _productAnalytics = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<_PrivacyBundle> _load() async {
    final bundle = _PrivacyBundle.fromJson(await _client.privacyCenter());
    _profileVisibility = bundle.profileVisibility;
    _directMessagePrivacy = bundle.directMessagePrivacy;
    _friendRequestPrivacy = bundle.friendRequestPrivacy;
    _adsPersonalization = bundle.adsPersonalization;
    _adsMeasurement = bundle.adsMeasurement;
    _productAnalytics = bundle.productAnalytics;
    return bundle;
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('privacy.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('privacy.reload'),
            onPressed: _busy ? null : _reload,
            icon: Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('privacy.title'),
        subtitle: t('privacy.subtitle'),
        child: FutureBuilder<_PrivacyBundle>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const _PrivacyLoading();
            }
            if (snapshot.hasError) {
              return _PrivacyError(error: snapshot.error, onRetry: _reload);
            }
            return _content(snapshot.data ?? const _PrivacyBundle());
          },
        ),
      ),
    );
  }

  Widget _content(_PrivacyBundle bundle) {
    final t = AirmiusScope.of(context).t;
    final enabledConsents = [
      _adsPersonalization,
      _adsMeasurement,
      _productAnalytics,
    ].where((value) => value).length;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('privacy.overview')),
              const SizedBox(height: 8),
              Text(
                t('privacy.overviewHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: MetricCard(
                      value: t(
                        _profileVisibility == 'private'
                            ? 'privacy.private'
                            : 'privacy.public',
                      ),
                      label: t('privacy.profile'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: MetricCard(
                      value: '$enabledConsents/3',
                      label: t('privacy.optionalConsents'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('privacy.visibility')),
              const SizedBox(height: 10),
              Text(
                t('privacy.visibilityHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 14),
              _PrivacyDropdown(
                label: t('privacy.profileVisibility'),
                value: _profileVisibility,
                values: const ['public', 'private'],
                labelFor: (value) => t(
                  value == 'private' ? 'privacy.private' : 'privacy.public',
                ),
                onChanged: (value) =>
                    setState(() => _profileVisibility = value),
              ),
              const SizedBox(height: 12),
              _PrivacyDropdown(
                label: t('privacy.directMessages'),
                value: _directMessagePrivacy,
                values: const ['everyone', 'friends'],
                labelFor: (value) => t(
                  value == 'friends'
                      ? 'privacy.friendsOnly'
                      : 'privacy.everyone',
                ),
                onChanged: (value) =>
                    setState(() => _directMessagePrivacy = value),
              ),
              const SizedBox(height: 12),
              _PrivacyDropdown(
                label: t('privacy.friendRequests'),
                value: _friendRequestPrivacy,
                values: const ['everyone', 'friends'],
                labelFor: (value) => t(
                  value == 'friends'
                      ? 'privacy.friendsOnly'
                      : 'privacy.everyone',
                ),
                onChanged: (value) =>
                    setState(() => _friendRequestPrivacy = value),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('privacy.optionalConsents')),
              const SizedBox(height: 8),
              Text(
                t('privacy.consentHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 10),
              Material(
                color: Colors.transparent,
                child: SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  value: _adsPersonalization,
                  onChanged: _busy
                      ? null
                      : (value) => setState(() => _adsPersonalization = value),
                  title: Text(
                    t('privacy.personalization'),
                    style: TextStyle(fontWeight: FontWeight.w900),
                  ),
                  subtitle: Text(t('privacy.personalizationHint')),
                ),
              ),
              Material(
                color: Colors.transparent,
                child: SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  value: _adsMeasurement,
                  onChanged: _busy
                      ? null
                      : (value) => setState(() => _adsMeasurement = value),
                  title: Text(
                    t('privacy.measurement'),
                    style: TextStyle(fontWeight: FontWeight.w900),
                  ),
                  subtitle: Text(t('privacy.measurementHint')),
                ),
              ),
              Material(
                color: Colors.transparent,
                child: SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  value: _productAnalytics,
                  onChanged: _busy
                      ? null
                      : (value) => setState(() => _productAnalytics = value),
                  title: Text(
                    t('privacy.productAnalytics'),
                    style: TextStyle(fontWeight: FontWeight.w900),
                  ),
                  subtitle: Text(t('privacy.productAnalyticsHint')),
                ),
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  AirmiusButton(
                    label: t('privacy.save'),
                    icon: Icons.save_outlined,
                    onPressed: _busy ? null : () => _save(bundle),
                  ),
                  AirmiusButton(
                    label: t('privacy.withdrawAll'),
                    icon: Icons.do_not_disturb_alt_outlined,
                    danger: true,
                    onPressed:
                        _busy ||
                            (!_adsPersonalization &&
                                !_adsMeasurement &&
                                !_productAnalytics)
                        ? null
                        : _withdrawAll,
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('privacy.connectedProviders')),
              const SizedBox(height: 8),
              Text(
                t('privacy.connectedProvidersHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 12),
              if (bundle.providers.isEmpty)
                Text(
                  t('privacy.noConnectedProviders'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                )
              else
                ...bundle.providers.map(
                  (provider) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Row(
                      children: [
                        Icon(
                          provider.kind == 'sport'
                              ? Icons.directions_run_outlined
                              : Icons.login_outlined,
                          color: airmiusAccentColor(context),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _providerName(provider.provider),
                                style: TextStyle(fontWeight: FontWeight.w900),
                              ),
                              Text(
                                t(
                                  provider.kind == 'sport'
                                      ? 'privacy.sportProvider'
                                      : 'privacy.loginProvider',
                                ),
                                style: TextStyle(
                                  color: airmiusMutedColor(context),
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                        Icon(
                          Icons.check_circle_outline,
                          color: Theme.of(context).colorScheme.secondary,
                        ),
                      ],
                    ),
                  ),
                ),
              const SizedBox(height: 2),
              AirmiusButton(
                label: t('privacy.manageProviders'),
                icon: Icons.hub_outlined,
                secondary: true,
                onPressed: _busy
                    ? null
                    : () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const SportIntegrationsScreen(),
                        ),
                      ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('privacy.yourRights')),
              const SizedBox(height: 8),
              Text(
                t('privacy.rightsHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 14),
              _PrivacyAction(
                icon: Icons.download_outlined,
                title: t('privacy.export'),
                body: t('privacy.exportHint'),
                onTap: _busy ? null : _exportData,
              ),
              const SizedBox(height: 10),
              _PrivacyAction(
                icon: Icons.edit_note_outlined,
                title: t('privacy.correction'),
                body: t('privacy.correctionHint'),
                onTap: _busy ? null : () => _correctData(bundle),
              ),
              const SizedBox(height: 10),
              _PrivacyAction(
                icon: Icons.delete_sweep_outlined,
                title: t('privacy.eraseData'),
                body: t('privacy.eraseDataHint'),
                onTap: _busy ? null : () => _openDataErasure(bundle),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        AirmiusPanel(
          borderColor: Theme.of(
            context,
          ).colorScheme.secondary.withValues(alpha: .45),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.shield_outlined,
                color: Theme.of(context).colorScheme.secondary,
                size: 28,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  t('privacy.minorSafety'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.4,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _save(_PrivacyBundle bundle) async {
    final t = AirmiusScope.of(context).t;
    final withdrawn = <String>[
      if (bundle.adsPersonalization && !_adsPersonalization)
        'ads_personalization',
      if (bundle.adsMeasurement && !_adsMeasurement) 'ads_measurement',
      if (bundle.productAnalytics && !_productAnalytics) 'product_analytics',
    ];
    await _run(() async {
      await _client.updateSettings({
        'country': bundle.country,
        'profile_visibility': _profileVisibility,
        'direct_message_privacy': _directMessagePrivacy,
        'friend_request_privacy': _friendRequestPrivacy,
        'ads_personalization_consent': _adsPersonalization,
        'ads_measurement_consent': _adsMeasurement,
        'product_analytics_consent': _productAnalytics,
      });
      if (withdrawn.isNotEmpty) {
        await _client.withdrawPrivacyConsents(withdrawn);
      }
    }, success: t('privacy.saved'));
  }

  Future<void> _withdrawAll() async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('privacy.withdrawTitle')),
        content: Text(t('privacy.withdrawConfirm')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('privacy.withdraw')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(() async {
      await _client.withdrawPrivacyConsents(const ['all']);
      _adsPersonalization = false;
      _adsMeasurement = false;
      _productAnalytics = false;
    }, success: t('privacy.withdrawn'));
  }

  Future<void> _exportData() async {
    final t = AirmiusScope.of(context).t;
    setState(() => _busy = true);
    try {
      final response = await _client.privacyExport();
      final export = response['data'] ?? response;
      final formatted = const JsonEncoder.withIndent('  ').convert(export);
      try {
        final date = DateTime.now().toIso8601String().split('T').first;
        final path = await FilePicker.platform.saveFile(
          dialogTitle: t('privacy.export'),
          fileName: 'airmius-datenauskunft-$date.json',
          type: FileType.custom,
          allowedExtensions: const ['json'],
          bytes: Uint8List.fromList(utf8.encode(formatted)),
        );
        if (!mounted || path == null) return;
        _toast(t('privacy.exportSaved'));
      } catch (_) {
        if (!mounted) return;
        await _showExportFallback(formatted);
      }
    } catch (error) {
      if (mounted) _toast(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _showExportFallback(String formatted) {
    final t = AirmiusScope.of(context).t;
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 4, 18, 24),
          child: Column(
            children: [
              Text(
                t('privacy.exportReady'),
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 12),
              Expanded(
                child: SingleChildScrollView(
                  child: SelectableText(
                    formatted,
                    style: TextStyle(fontFamily: 'monospace', fontSize: 12),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              AirmiusButton(
                label: t('privacy.copyExport'),
                icon: Icons.copy_all_outlined,
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: formatted));
                  if (!context.mounted) return;
                  Navigator.pop(context);
                  _toast(t('privacy.copied'));
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _correctData(_PrivacyBundle bundle) async {
    final payload = await _showCorrectionDialog(bundle);
    if (payload == null || !mounted) return;
    final t = AirmiusScope.of(context).t;
    final authState = AirmiusServicesScope.of(context).authState;
    await _run(() async {
      await _client.correctPrivacy(payload);
      await authState.refreshUser();
    }, success: t('privacy.corrected'));
  }

  Future<void> _openDataErasure(_PrivacyBundle bundle) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => DataErasureSheet(
        client: _client,
        usesSocialLogin: bundle.usesSocialLogin,
        accountEmail: bundle.email,
        categoryKeys: bundle.dataErasureCategoryKeys,
      ),
    );
  }

  Future<JsonMap?> _showCorrectionDialog(_PrivacyBundle bundle) async {
    final t = AirmiusScope.of(context).t;
    final firstName = TextEditingController(text: bundle.firstName);
    final lastName = TextEditingController(text: bundle.lastName);
    final email = TextEditingController(text: bundle.email);
    final country = TextEditingController(text: bundle.country);
    final city = TextEditingController(text: bundle.city);
    final postalCode = TextEditingController(text: bundle.postalCode);
    try {
      return await showDialog<JsonMap>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(t('privacy.correction')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  t('privacy.emailVerificationHint'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 12,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: firstName,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(
                    labelText: t('privacy.firstName'),
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: lastName,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(labelText: t('privacy.lastName')),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: email,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(labelText: t('privacy.email')),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: country,
                  textCapitalization: TextCapitalization.characters,
                  maxLength: 2,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(labelText: t('privacy.country')),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: postalCode,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(
                    labelText: t('privacy.postalCode'),
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: city,
                  textInputAction: TextInputAction.done,
                  decoration: InputDecoration(labelText: t('privacy.city')),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(t('cancel')),
            ),
            FilledButton(
              onPressed: () {
                final normalizedEmail = email.text.trim();
                final normalizedCountry = country.text.trim().toUpperCase();
                if (firstName.text.trim().isEmpty ||
                    lastName.text.trim().isEmpty ||
                    !normalizedEmail.contains('@') ||
                    normalizedCountry.length != 2) {
                  _toast(t('privacy.correctionInvalid'));
                  return;
                }
                Navigator.pop(context, {
                  'first_name': firstName.text.trim(),
                  'last_name': lastName.text.trim(),
                  'email': normalizedEmail,
                  'country': normalizedCountry,
                  'postal_code': postalCode.text.trim(),
                  'city': city.text.trim(),
                });
              },
              child: Text(t('privacy.saveCorrection')),
            ),
          ],
        ),
      );
    } finally {
      firstName.dispose();
      lastName.dispose();
      email.dispose();
      country.dispose();
      city.dispose();
      postalCode.dispose();
    }
  }

  Future<void> _run(
    Future<void> Function() action, {
    required String success,
  }) async {
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      _toast(success);
      _reload();
    } catch (error) {
      if (mounted) _toast(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _errorMessage(Object error) => error is AirmiusApiException
      ? error.userMessage
      : AirmiusScope.of(context).t('common.errorDetails');

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  String _providerName(String provider) => provider
      .split(RegExp(r'[_\-]'))
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

class _PrivacyBundle {
  const _PrivacyBundle({
    this.country = 'DE',
    this.firstName = '',
    this.lastName = '',
    this.email = '',
    this.city = '',
    this.postalCode = '',
    this.profileVisibility = 'public',
    this.directMessagePrivacy = 'everyone',
    this.friendRequestPrivacy = 'everyone',
    this.adsPersonalization = false,
    this.adsMeasurement = false,
    this.productAnalytics = false,
    this.providers = const [],
    this.usesSocialLogin = false,
    this.dataErasureCategoryKeys = const [
      'profile',
      'content',
      'messages',
      'files',
      'sport_and_health',
      'social_and_integrations',
      'commerce',
    ],
  });

  factory _PrivacyBundle.fromJson(JsonMap json) {
    final data = _privacyMap(json['data']);
    final user = _privacyMap(data['user']);
    final address = _privacyMap(data['profile_address']);
    final privacy = _privacyMap(data['privacy_settings']);
    final dataErasure = _privacyMap(data['data_erasure']);
    final providerData = _privacyMap(data['connected_providers']);
    final providers = (providerData['items'] as List<dynamic>? ?? const [])
        .map((item) => _PrivacyProvider.fromJson(_privacyMap(item)))
        .where((item) => item.provider.isNotEmpty)
        .toList(growable: false);
    final categoryKeys =
        (dataErasure['category_keys'] as List<dynamic>? ?? const [])
            .map((value) => _privacyText(value))
            .where((value) => value.isNotEmpty)
            .toList(growable: false);
    return _PrivacyBundle(
      country: _privacyText(
        address['country'] ?? user['country'],
        fallback: 'DE',
      ).toUpperCase(),
      firstName: _privacyText(user['first_name']),
      lastName: _privacyText(user['last_name']),
      email: _privacyText(dataErasure['account_email'] ?? user['email']),
      city: _privacyText(address['city'] ?? user['city']),
      postalCode: _privacyText(address['postal_code'] ?? user['postal_code']),
      profileVisibility: _privacyText(
        privacy['profile_visibility'],
        fallback: 'public',
      ),
      directMessagePrivacy: _privacyText(
        privacy['direct_message_privacy'],
        fallback: 'everyone',
      ),
      friendRequestPrivacy: _privacyText(
        privacy['friend_request_privacy'],
        fallback: 'everyone',
      ),
      adsPersonalization: _privacyBool(privacy['ads_personalization_consent']),
      adsMeasurement: _privacyBool(privacy['ads_measurement_consent']),
      productAnalytics: _privacyBool(privacy['product_analytics_consent']),
      providers: providers,
      usesSocialLogin: _privacyBool(dataErasure['uses_social_login']),
      dataErasureCategoryKeys: categoryKeys.isEmpty
          ? const [
              'profile',
              'content',
              'messages',
              'files',
              'sport_and_health',
              'social_and_integrations',
              'commerce',
            ]
          : categoryKeys,
    );
  }

  final String country;
  final String firstName;
  final String lastName;
  final String email;
  final String city;
  final String postalCode;
  final String profileVisibility;
  final String directMessagePrivacy;
  final String friendRequestPrivacy;
  final bool adsPersonalization;
  final bool adsMeasurement;
  final bool productAnalytics;
  final List<_PrivacyProvider> providers;
  final bool usesSocialLogin;
  final List<String> dataErasureCategoryKeys;
}

class _PrivacyProvider {
  const _PrivacyProvider({required this.kind, required this.provider});

  factory _PrivacyProvider.fromJson(JsonMap json) => _PrivacyProvider(
    kind: _privacyText(json['kind'], fallback: 'sport'),
    provider: _privacyText(json['provider']),
  );

  final String kind;
  final String provider;
}

class _PrivacyDropdown extends StatelessWidget {
  const _PrivacyDropdown({
    required this.label,
    required this.value,
    required this.values,
    required this.labelFor,
    required this.onChanged,
  });

  final String label;
  final String value;
  final List<String> values;
  final String Function(String) labelFor;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<String>(
      initialValue: values.contains(value) ? value : values.first,
      decoration: InputDecoration(labelText: label),
      items: values
          .map(
            (item) =>
                DropdownMenuItem(value: item, child: Text(labelFor(item))),
          )
          .toList(),
      onChanged: (next) {
        if (next != null) onChanged(next);
      },
    );
  }
}

class _PrivacyAction extends StatelessWidget {
  const _PrivacyAction({
    required this.icon,
    required this.title,
    required this.body,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String body;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: onTap,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: airmiusSurfaceSoftColor(context),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: airmiusBorderColor(context)),
            ),
            child: Icon(icon, color: airmiusAccentColor(context)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
        ],
      ),
    );
  }
}

class _PrivacyLoading extends StatelessWidget {
  const _PrivacyLoading();

  @override
  Widget build(BuildContext context) {
    return const AirmiusPanel(
      child: Padding(
        padding: EdgeInsets.all(30),
        child: Center(child: CircularProgressIndicator()),
      ),
    );
  }
}

class _PrivacyError extends StatelessWidget {
  const _PrivacyError({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      borderColor: Theme.of(context).colorScheme.error.withValues(alpha: .5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            t('privacy.loadError'),
            style: TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          Text(
            error is AirmiusApiException
                ? (error as AirmiusApiException).userMessage
                : t('common.errorDetails'),
            style: TextStyle(color: airmiusMutedColor(context)),
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('privacy.reload'),
            icon: Icons.refresh_outlined,
            secondary: true,
            onPressed: onRetry,
          ),
        ],
      ),
    );
  }
}

JsonMap _privacyMap(Object? value) {
  if (value is JsonMap) return value;
  if (value is Map) {
    return value.map((key, item) => MapEntry('$key', item));
  }
  return const {};
}

String _privacyText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty || text == 'null' ? fallback : text;
}

bool _privacyBool(Object? value) =>
    value == true || value == 1 || '$value'.toLowerCase() == 'true';
