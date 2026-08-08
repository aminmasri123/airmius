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
    final name = TextEditingController(text: user?.name ?? '');
    final email = TextEditingController(text: user?.email ?? '');
    final phone = TextEditingController(text: user?.phone ?? '');
    final club = TextEditingController();
    final domain = TextEditingController();
    final goals = TextEditingController(
      text: t('agencyMobile.goal.$_goal') +
          (packageKey == null ? '' : ' · ${t('agencyMobile.$packageKey.title')}'),
    );
    final notes = TextEditingController();
    var privacyAccepted = false;
    var sending = false;
    String? error;

    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('agencyMobile.formTitle')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _AgencyField(
                  controller: name,
                  label: t('agencyMobile.name'),
                  enabled: !sending,
                  autofillHints: const [AutofillHints.name],
                ),
                _AgencyField(
                  controller: email,
                  label: t('agencyMobile.email'),
                  enabled: !sending,
                  keyboardType: TextInputType.emailAddress,
                  autofillHints: const [AutofillHints.email],
                ),
                _AgencyField(
                  controller: phone,
                  label: t('agencyMobile.phone'),
                  enabled: !sending,
                  keyboardType: TextInputType.phone,
                  autofillHints: const [AutofillHints.telephoneNumber],
                ),
                _AgencyField(
                  controller: club,
                  label: t('agencyMobile.club'),
                  enabled: !sending,
                ),
                _AgencyField(
                  controller: domain,
                  label: t('agencyMobile.domain'),
                  enabled: !sending,
                  keyboardType: TextInputType.url,
                ),
                _AgencyField(
                  controller: goals,
                  label: t('agencyMobile.goals'),
                  enabled: !sending,
                  minLines: 2,
                ),
                _AgencyField(
                  controller: notes,
                  label: t('agencyMobile.notes'),
                  enabled: !sending,
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
                  value: privacyAccepted,
                  onChanged: sending
                      ? null
                      : (value) => setDialogState(
                            () => privacyAccepted = value ?? false,
                          ),
                  title: Text(t('agencyMobile.privacyAccept')),
                  controlAffinity: ListTileControlAffinity.leading,
                ),
                if (error != null)
                  Text(
                    error!,
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
              onPressed: sending ? null : () => Navigator.pop(dialogContext),
              child: Text(t('agencyMobile.cancel')),
            ),
            FilledButton.icon(
              onPressed: sending
                  ? null
                  : () async {
                      if (name.text.trim().isEmpty ||
                          !email.text.contains('@') ||
                          club.text.trim().isEmpty ||
                          goals.text.trim().isEmpty ||
                          !privacyAccepted) {
                        setDialogState(
                          () => error = t('agencyMobile.required'),
                        );
                        return;
                      }
                      setDialogState(() {
                        sending = true;
                        error = null;
                      });
                      try {
                        await _client.submitPublicAgencyRequest(
                          {
                            'guest_name': name.text.trim(),
                            'guest_email': email.text.trim(),
                            'guest_phone': phone.text.trim().isEmpty
                                ? null
                                : phone.text.trim(),
                            'club_name': club.text.trim(),
                            'domain': domain.text.trim().isEmpty
                                ? null
                                : domain.text.trim(),
                            'goals': goals.text.trim(),
                            'notes': notes.text.trim().isEmpty
                                ? null
                                : notes.text.trim(),
                            'accepted_privacy': true,
                          },
                          idempotencyKey:
                              'agency-${DateTime.now().microsecondsSinceEpoch}',
                        );
                        if (dialogContext.mounted) {
                          Navigator.pop(dialogContext, true);
                        }
                      } catch (_) {
                        if (dialogContext.mounted) {
                          setDialogState(() {
                            sending = false;
                            error = t('agencyMobile.submitError');
                          });
                        }
                      }
                    },
              icon: sending
                  ? const SizedBox.square(
                      dimension: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.send_outlined),
              label: Text(
                t(sending ? 'agencyMobile.sending' : 'agencyMobile.send'),
              ),
            ),
          ],
        ),
      ),
    );

    name.dispose();
    email.dispose();
    phone.dispose();
    club.dispose();
    domain.dispose();
    goals.dispose();
    notes.dispose();

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
