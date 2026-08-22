import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'sponsor_ads_operations_screen.dart';

/// Public, localized agency journey backed by the rate-limited agency API.
class GuestAdAgencyScreen extends StatefulWidget {
  const GuestAdAgencyScreen({super.key});

  @override
  State<GuestAdAgencyScreen> createState() => _GuestAdAgencyScreenState();
}

class _GuestAdAgencyScreenState extends State<GuestAdAgencyScreen> {
  String _goal = 'reach';

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  Future<void> _openRequest([String? packageKey]) async {
    final services = AirmiusServicesScope.of(context);
    final user = services.authState.session?.user;
    final submitted = await showDialog<bool>(
      context: context,
      builder: (_) => _AgencyRequestDialog(
        client: _client,
        initialName: user?.name ?? '',
        initialEmail: user?.email ?? '',
        initialPhone: user?.phone ?? '',
        initialGoals:
            t('agencyMobile.goal.$_goal') +
            (packageKey == null
                ? ''
                : ' · ${t('agencyMobile.$packageKey.title')}'),
      ),
    );

    if (submitted == true && mounted) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(t('agencyMobile.sent'))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final packages = ['local', 'sponsor', 'content', 'performance'];
    final goals = ['reach', 'leads', 'sponsoring', 'content'];

    return Scaffold(
      appBar: AppBar(title: Text(t('agencyMobile.title'))),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
          children: [
            PageTitle(
              title: t('agencyMobile.title'),
              subtitle: t('agencyMobile.subtitle'),
            ),
            const SizedBox(height: 16),
            AirmiusPanel(
              gradient: true,
              title: t('agencyMobile.hero'),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    t('agencyMobile.heroBody'),
                    style: const TextStyle(
                      color: AirmiusColors.muted,
                      height: 1.45,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 14),
                  AirmiusButton(
                    label: t('agencyMobile.request'),
                    icon: Icons.send_outlined,
                    onPressed: _openRequest,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            AirmiusPanel(
              title: t('agencyMobile.goalTitle'),
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final goal in goals)
                    ChoiceChip(
                      label: Text(t('agencyMobile.goal.$goal')),
                      selected: _goal == goal,
                      onSelected: (_) => setState(() => _goal = goal),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            for (final package in packages) ...[
              AirmiusPanel(
                title: t('agencyMobile.$package.title'),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      t('agencyMobile.$package.body'),
                      style: const TextStyle(
                        color: AirmiusColors.muted,
                        height: 1.45,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 12),
                    AirmiusButton(
                      label: t('agencyMobile.packageRequest'),
                      icon: Icons.arrow_forward,
                      secondary: true,
                      onPressed: () => _openRequest(package),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
            ],
            AirmiusButton(
              label: t('agencyMobile.adsOps'),
              icon: Icons.campaign_outlined,
              secondary: true,
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const SponsorAdsOperationsScreen(),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AgencyRequestDialog extends StatefulWidget {
  const _AgencyRequestDialog({
    required this.client,
    required this.initialName,
    required this.initialEmail,
    required this.initialPhone,
    required this.initialGoals,
  });

  final AirmiusApiClient client;
  final String initialName;
  final String initialEmail;
  final String initialPhone;
  final String initialGoals;

  @override
  State<_AgencyRequestDialog> createState() => _AgencyRequestDialogState();
}

class _AgencyRequestDialogState extends State<_AgencyRequestDialog> {
  late final TextEditingController _name;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  late final TextEditingController _club;
  late final TextEditingController _domain;
  late final TextEditingController _goals;
  late final TextEditingController _notes;
  bool _privacyAccepted = false;
  bool _sending = false;
  String? _error;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: widget.initialName);
    _email = TextEditingController(text: widget.initialEmail);
    _phone = TextEditingController(text: widget.initialPhone);
    _club = TextEditingController();
    _domain = TextEditingController();
    _goals = TextEditingController(text: widget.initialGoals);
    _notes = TextEditingController();
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _club.dispose();
    _domain.dispose();
    _goals.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_name.text.trim().isEmpty ||
        !_email.text.contains('@') ||
        _club.text.trim().isEmpty ||
        _goals.text.trim().isEmpty ||
        !_privacyAccepted) {
      setState(() => _error = t('agencyMobile.required'));
      return;
    }

    setState(() {
      _sending = true;
      _error = null;
    });

    try {
      await widget.client.submitPublicAgencyRequest({
        'guest_name': _name.text.trim(),
        'guest_email': _email.text.trim(),
        'guest_phone': _phone.text.trim().isEmpty ? null : _phone.text.trim(),
        'club_name': _club.text.trim(),
        'domain': _domain.text.trim().isEmpty ? null : _domain.text.trim(),
        'goals': _goals.text.trim(),
        'notes': _notes.text.trim().isEmpty ? null : _notes.text.trim(),
        'accepted_privacy': true,
      }, idempotencyKey: 'agency-${DateTime.now().microsecondsSinceEpoch}');
      if (mounted) Navigator.pop(context, true);
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _sending = false;
        _error = t('agencyMobile.submitError');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(t('agencyMobile.formTitle')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _AgencyField(
              controller: _name,
              label: t('agencyMobile.name'),
              enabled: !_sending,
              autofillHints: const [AutofillHints.name],
            ),
            _AgencyField(
              controller: _email,
              label: t('agencyMobile.email'),
              enabled: !_sending,
              keyboardType: TextInputType.emailAddress,
              autofillHints: const [AutofillHints.email],
            ),
            _AgencyField(
              controller: _phone,
              label: t('agencyMobile.phone'),
              enabled: !_sending,
              keyboardType: TextInputType.phone,
              autofillHints: const [AutofillHints.telephoneNumber],
            ),
            _AgencyField(
              controller: _club,
              label: t('agencyMobile.club'),
              enabled: !_sending,
            ),
            _AgencyField(
              controller: _domain,
              label: t('agencyMobile.domain'),
              enabled: !_sending,
              keyboardType: TextInputType.url,
            ),
            _AgencyField(
              controller: _goals,
              label: t('agencyMobile.goals'),
              enabled: !_sending,
              minLines: 2,
            ),
            _AgencyField(
              controller: _notes,
              label: t('agencyMobile.notes'),
              enabled: !_sending,
              minLines: 2,
            ),
            Text(
              t('agencyMobile.privacy'),
              style: const TextStyle(
                color: AirmiusColors.muted,
                fontSize: 12,
                height: 1.35,
              ),
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _privacyAccepted,
              onChanged: _sending
                  ? null
                  : (value) => setState(() {
                      _privacyAccepted = value ?? false;
                      if (_privacyAccepted) _error = null;
                    }),
              title: Text(t('agencyMobile.privacyAccept')),
              controlAffinity: ListTileControlAffinity.leading,
            ),
            if (_error != null)
              Text(
                _error!,
                style: const TextStyle(
                  color: AirmiusColors.red,
                  fontWeight: FontWeight.w800,
                ),
              ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _sending ? null : () => Navigator.pop(context),
          child: Text(t('agencyMobile.cancel')),
        ),
        FilledButton.icon(
          onPressed: _sending ? null : _submit,
          icon: _sending
              ? const SizedBox.square(
                  dimension: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.send_outlined),
          label: Text(
            t(_sending ? 'agencyMobile.sending' : 'agencyMobile.send'),
          ),
        ),
      ],
    );
  }
}

class _AgencyField extends StatelessWidget {
  const _AgencyField({
    required this.controller,
    required this.label,
    required this.enabled,
    this.keyboardType,
    this.autofillHints,
    this.minLines = 1,
  });

  final TextEditingController controller;
  final String label;
  final bool enabled;
  final TextInputType? keyboardType;
  final Iterable<String>? autofillHints;
  final int minLines;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: TextField(
        controller: controller,
        enabled: enabled,
        keyboardType: keyboardType,
        autofillHints: autofillHints,
        minLines: minLines,
        maxLines: minLines == 1 ? 1 : 5,
        maxLength: minLines == 1 ? 255 : 2000,
        decoration: InputDecoration(labelText: label),
      ),
    );
  }
}
