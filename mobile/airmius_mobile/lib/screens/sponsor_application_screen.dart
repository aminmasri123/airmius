import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import '../widgets/country_field.dart';
import 'sponsor_cockpit_screen.dart';

class SponsorApplicationScreen extends StatefulWidget {
  const SponsorApplicationScreen({super.key});

  @override
  State<SponsorApplicationScreen> createState() =>
      _SponsorApplicationScreenState();
}

class _SponsorApplicationScreenState extends State<SponsorApplicationScreen> {
  final _formKey = GlobalKey<FormState>();
  final _company = TextEditingController();
  final _legalName = TextEditingController();
  final _website = TextEditingController();
  final _country = TextEditingController(text: 'DE');
  final _contact = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _interests = TextEditingController();
  final _goals = TextEditingController();
  final _message = TextEditingController();

  bool _loading = true;
  bool _saving = false;
  bool _accurate = false;
  bool _privacy = false;
  int? _applicationId;
  String? _status;
  String? _reviewNotes;
  DateTime? _requestedAt;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_loading) _load();
  }

  Future<void> _load() async {
    try {
      final response = await _client.roleApplications();
      final applications = (response['data'] as List? ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .where((item) => item['type'] == 'sponsor')
          .toList();
      if (applications.isNotEmpty) {
        final application = applications.first;
        final data = application['application_data'] is Map
            ? Map<String, dynamic>.from(application['application_data'] as Map)
            : <String, dynamic>{};
        _status = application['status']?.toString();
        _applicationId = int.tryParse('${application['id'] ?? ''}');
        _reviewNotes = application['review_notes']?.toString();
        _requestedAt = DateTime.tryParse(
          '${application['requested_at'] ?? ''}',
        );
        _company.text = '${data['company_name'] ?? ''}';
        _legalName.text = '${data['legal_name'] ?? ''}';
        _website.text = '${data['website'] ?? ''}';
        _country.text = '${data['country_code'] ?? 'DE'}';
        _contact.text = '${data['contact_name'] ?? ''}';
        _email.text = '${data['contact_email'] ?? ''}';
        _phone.text = '${data['phone'] ?? ''}';
        _interests.text = '${data['interests'] ?? ''}';
        _goals.text = '${data['goals'] ?? ''}';
        _message.text = '${application['message'] ?? ''}';
      }
    } catch (_) {
      if (mounted) _toast(t('sponsorApplication.loadFailed'));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _withdraw() async {
    final id = _applicationId;
    if (id == null || _status != 'pending') return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('sponsorApplication.withdrawTitle')),
        content: Text(t('sponsorApplication.withdrawConfirm')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('sponsorApplication.withdraw')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _saving = true);
    try {
      await _client.withdrawRoleApplication(id);
      if (!mounted) return;
      setState(() => _status = 'withdrawn');
      _toast(t('sponsorApplication.withdrawn'));
    } catch (error) {
      if (mounted) _toast(error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  String _statusText() => switch (_status) {
    'pending' => t('sponsorApplication.pending'),
    'rejected' => t('sponsorApplication.rejected'),
    'withdrawn' => t('sponsorApplication.withdrawnStatus'),
    _ => '',
  };

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (!_accurate || !_privacy) {
      _toast(t('sponsorApplication.confirmRequired'));
      return;
    }
    setState(() => _saving = true);
    try {
      final response = await _client.submitRoleApplication(
        type: 'sponsor',
        message: _message.text,
        applicationData: {
          'company_name': _company.text.trim(),
          'legal_name': _legalName.text.trim(),
          'website': _website.text.trim(),
          'country_code': _country.text.trim().toUpperCase(),
          'contact_name': _contact.text.trim(),
          'contact_email': _email.text.trim(),
          'phone': _phone.text.trim(),
          'interests': _interests.text.trim(),
          'goals': _goals.text.trim(),
          'legal_accuracy': true,
          'data_privacy': true,
        },
      );
      if (!mounted) return;
      final payload = response['data'] is Map
          ? Map<String, dynamic>.from(response['data'] as Map)
          : <String, dynamic>{};
      final application = payload['application'] is Map
          ? Map<String, dynamic>.from(payload['application'] as Map)
          : <String, dynamic>{};
      setState(() {
        _status = 'pending';
        _applicationId = int.tryParse('${application['id'] ?? ''}');
        _requestedAt =
            DateTime.tryParse('${application['requested_at'] ?? ''}') ??
            DateTime.now();
        _reviewNotes = null;
        _accurate = false;
        _privacy = false;
      });
      _toast(t('sponsorApplication.submitted'));
    } catch (error) {
      if (mounted) _toast(error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  String _required(String? value) => value == null || value.trim().isEmpty
      ? t('sponsorApplication.required')
      : '';

  @override
  Widget build(BuildContext context) {
    final approved = _status == 'approved';
    return Scaffold(
      appBar: AppBar(title: Text(t('sponsorApplication.title'))),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : PageFrame(
              title: t('sponsorApplication.title'),
              subtitle: t('sponsorApplication.intro'),
              child: approved
                  ? AirmiusPanel(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            t('sponsorApplication.approved'),
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 12),
                          AirmiusButton(
                            label: t('sponsorApplication.openCockpit'),
                            icon: Icons.dashboard_outlined,
                            onPressed: () => Navigator.pushReplacement(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const SponsorCockpitScreen(),
                              ),
                            ),
                          ),
                        ],
                      ),
                    )
                  : Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          if (_status != null) ...[
                            AirmiusPanel(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  Eyebrow(t('sponsorApplication.status')),
                                  const SizedBox(height: 8),
                                  Text(
                                    _statusText(),
                                    style: TextStyle(
                                      color: airmiusTextColor(context),
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                  if (_requestedAt != null) ...[
                                    const SizedBox(height: 8),
                                    Text(
                                      '${t('sponsorApplication.requestedAt')}: ${MaterialLocalizations.of(context).formatMediumDate(_requestedAt!.toLocal())}',
                                      style: TextStyle(
                                        color: airmiusMutedColor(context),
                                      ),
                                    ),
                                  ],
                                  if (_status == 'pending') ...[
                                    const SizedBox(height: 14),
                                    AirmiusButton(
                                      label: t('sponsorApplication.withdraw'),
                                      icon: Icons.cancel_outlined,
                                      secondary: true,
                                      onPressed: _saving ? null : _withdraw,
                                    ),
                                  ],
                                ],
                              ),
                            ),
                            const SizedBox(height: 12),
                          ],
                          if (_reviewNotes?.isNotEmpty == true) ...[
                            AirmiusPanel(
                              child: Text(
                                '${t('sponsorApplication.reviewNote')}: $_reviewNotes',
                              ),
                            ),
                            const SizedBox(height: 12),
                          ],
                          _section(t('sponsorApplication.company'), [
                            _field(
                              _company,
                              t('sponsorApplication.companyName'),
                              required: true,
                            ),
                            _field(
                              _legalName,
                              t('sponsorApplication.legalName'),
                            ),
                            _field(
                              _website,
                              t('sponsorApplication.website'),
                              keyboard: TextInputType.url,
                            ),
                            CountryField(
                              controller: _country,
                              label: t('sponsorApplication.country'),
                              required: true,
                            ),
                          ]),
                          const SizedBox(height: 12),
                          _section(t('sponsorApplication.contact'), [
                            _field(
                              _contact,
                              t('sponsorApplication.contactName'),
                              required: true,
                            ),
                            _field(
                              _email,
                              t('sponsorApplication.email'),
                              required: true,
                              keyboard: TextInputType.emailAddress,
                            ),
                            _field(
                              _phone,
                              t('sponsorApplication.phone'),
                              keyboard: TextInputType.phone,
                            ),
                          ]),
                          const SizedBox(height: 12),
                          _section(t('sponsorApplication.interest'), [
                            _field(
                              _interests,
                              t('sponsorApplication.interests'),
                              lines: 3,
                            ),
                            _field(
                              _goals,
                              t('sponsorApplication.goals'),
                              lines: 4,
                            ),
                            _field(
                              _message,
                              t('sponsorApplication.message'),
                              lines: 3,
                            ),
                            CheckboxListTile(
                              contentPadding: EdgeInsets.zero,
                              value: _accurate,
                              onChanged: (value) =>
                                  setState(() => _accurate = value == true),
                              title: Text(t('sponsorApplication.accuracy')),
                            ),
                            CheckboxListTile(
                              contentPadding: EdgeInsets.zero,
                              value: _privacy,
                              onChanged: (value) =>
                                  setState(() => _privacy = value == true),
                              title: Text(t('sponsorApplication.privacy')),
                            ),
                          ]),
                          const SizedBox(height: 16),
                          AirmiusButton(
                            label: _saving
                                ? t('sponsorApplication.saving')
                                : (_status == 'pending'
                                      ? t('sponsorApplication.update')
                                      : t('sponsorApplication.submit')),
                            icon: Icons.send_outlined,
                            onPressed: _saving ? null : _submit,
                          ),
                        ],
                      ),
                    ),
            ),
    );
  }

  Widget _section(String title, List<Widget> children) => AirmiusPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          title,
          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 12),
        for (var i = 0; i < children.length; i++) ...[
          children[i],
          if (i != children.length - 1) const SizedBox(height: 12),
        ],
      ],
    ),
  );

  Widget _field(
    TextEditingController controller,
    String label, {
    bool required = false,
    int lines = 1,
    int? maxLength,
    TextInputType? keyboard,
  }) => TextFormField(
    controller: controller,
    maxLines: lines,
    maxLength: maxLength,
    keyboardType: keyboard,
    decoration: InputDecoration(labelText: required ? '$label *' : label),
    validator: required
        ? (value) {
            final error = _required(value);
            return error.isEmpty ? null : error;
          }
        : null,
  );

  @override
  void dispose() {
    for (final controller in [
      _company,
      _legalName,
      _website,
      _country,
      _contact,
      _email,
      _phone,
      _interests,
      _goals,
      _message,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }
}
