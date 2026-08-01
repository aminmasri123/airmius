import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_date_input.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';

class ApplicationScreen extends StatefulWidget {
  const ApplicationScreen({super.key, required this.club});

  final ClubSummary club;

  @override
  State<ApplicationScreen> createState() => _ApplicationScreenState();
}

class _ApplicationScreenState extends State<ApplicationScreen> {
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _birthDate = TextEditingController();
  final _gender = TextEditingController();
  final _license = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _country = TextEditingController();
  final _street = TextEditingController();
  final _houseNumber = TextEditingController();
  final _postalCode = TextEditingController();
  final _city = TextEditingController();
  final _state = TextEditingController();
  final _guardianName = TextEditingController();
  final _guardianEmail = TextEditingController();
  final _emergencyName = TextEditingController();
  final _emergencyPhone = TextEditingController();
  final _iban = TextEditingController();
  final _bic = TextEditingController();
  String _membershipType = 'general';
  String _paymentMethod = 'bank_transfer';
  String _paymentCycle = 'monthly';
  bool _documentsAccepted = false;
  bool _privacyAccepted = false;
  bool _uploadedDocument = false;
  bool _sending = false;
  String? _sendError;
  bool _profilePrefilled = false;

  bool get _canSend => _documentsAccepted && _privacyAccepted;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_profilePrefilled) return;
    _profilePrefilled = true;
    final authUser = AirmiusServicesScope.of(context).authState.user;
    if (authUser != null) {
      _prefillFromUser(authUser);
    }
  }

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _birthDate.dispose();
    _gender.dispose();
    _license.dispose();
    _email.dispose();
    _phone.dispose();
    _country.dispose();
    _street.dispose();
    _houseNumber.dispose();
    _postalCode.dispose();
    _city.dispose();
    _state.dispose();
    _guardianName.dispose();
    _guardianEmail.dispose();
    _emergencyName.dispose();
    _emergencyPhone.dispose();
    _iban.dispose();
    _bic.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final t = scope.t;
    final authUser = AirmiusServicesScope.of(context).authState.user;
    final showGuardianSection = !_isKnownAdult(
      authUser?.birthDate ?? _parseBirthDate(_birthDate.text),
    );
    return Scaffold(
      backgroundColor: Colors.black.withValues(alpha: 0.62),
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return Align(
              alignment: constraints.maxWidth < 700
                  ? Alignment.bottomCenter
                  : Alignment.center,
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxWidth: 672,
                  maxHeight: constraints.maxHeight - 24,
                ),
                child: Container(
                  margin: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: airmiusSurfaceColor(context),
                    borderRadius: BorderRadius.circular(24),
                    border: Border.all(color: airmiusBorderColor(context)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.34),
                        blurRadius: 30,
                        offset: const Offset(0, 18),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 14, 10, 8),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    scope.t('application'),
                                    style: TextStyle(
                                      color: airmiusTextColor(context),
                                      fontSize: 18,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    widget.club.name,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                      color: airmiusMutedColor(context),
                                      fontSize: 12,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            IconButton(
                              onPressed: _sending
                                  ? null
                                  : () => Navigator.pop(context),
                              icon: Icon(
                                Icons.close,
                                color: airmiusMutedColor(context),
                              ),
                            ),
                          ],
                        ),
                      ),
                      Expanded(
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.fromLTRB(16, 6, 16, 18),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              AirmiusPanel(
                                gradient: true,
                                child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.stretch,
                                  children: [
                                    Eyebrow(
                                      t('application.membershipApplication'),
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      widget.club.name,
                                      style: TextStyle(
                                        color: airmiusTextColor(context),
                                        fontSize: 24,
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                    const SizedBox(height: 14),
                                    _SelectField(
                                      label: t('application.membershipType'),
                                      value: _membershipType,
                                      items: [
                                        (
                                          'general',
                                          t('application.type.general'),
                                        ),
                                        (
                                          'active',
                                          t('application.type.active'),
                                        ),
                                        (
                                          'supporting',
                                          t('application.type.supporting'),
                                        ),
                                        ('trial', t('application.type.trial')),
                                      ],
                                      onChanged: (value) => setState(
                                        () => _membershipType = value,
                                      ),
                                    ),
                                    const SizedBox(height: 10),
                                    Text(
                                      t('application.fieldsHint'),
                                      style: TextStyle(
                                        color: airmiusMutedColor(context),
                                        height: 1.35,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 12),
                              _ProgressPanel(done: _canSend ? 6 : 4),
                              const SizedBox(height: 12),
                              _FormSection(
                                step: '1',
                                title: t('application.personalData'),
                                children: [
                                  AirmiusTextField(
                                    label: '${t('application.firstName')} *',
                                    controller: _firstName,
                                  ),
                                  AirmiusTextField(
                                    label: '${t('application.lastName')} *',
                                    controller: _lastName,
                                  ),
                                  AirmiusTextField(
                                    label: '${t('application.birthDate')} *',
                                    hint: t('application.dateHint'),
                                    icon: Icons.calendar_today_outlined,
                                    controller: _birthDate,
                                    keyboardType: TextInputType.datetime,
                                    inputFormatters: const [
                                      AirmiusDateInputFormatter(),
                                    ],
                                  ),
                                  DropdownButtonFormField<String>(
                                    isExpanded: true,
                                    initialValue:
                                        _membershipGenderOptions.contains(
                                          _gender.text.trim(),
                                        )
                                        ? _gender.text.trim()
                                        : null,
                                    dropdownColor: airmiusSurfaceSoftColor(
                                      context,
                                    ),
                                    decoration: InputDecoration(
                                      labelText: '${t('application.gender')} *',
                                      prefixIcon: Icon(
                                        Icons.wc_outlined,
                                        color: airmiusMutedColor(context),
                                      ),
                                    ),
                                    style: TextStyle(
                                      color: airmiusTextColor(context),
                                      fontWeight: FontWeight.w800,
                                    ),
                                    items: [
                                      DropdownMenuItem(
                                        value: 'female',
                                        child: Text(
                                          t('application.gender.female'),
                                        ),
                                      ),
                                      DropdownMenuItem(
                                        value: 'male',
                                        child: Text(
                                          t('application.gender.male'),
                                        ),
                                      ),
                                      DropdownMenuItem(
                                        value: 'diverse',
                                        child: Text(
                                          t('application.gender.diverse'),
                                        ),
                                      ),
                                      DropdownMenuItem(
                                        value: 'not_specified',
                                        child: Text(
                                          t('application.gender.unspecified'),
                                        ),
                                      ),
                                    ],
                                    onChanged: (value) => setState(
                                      () => _gender.text = value ?? '',
                                    ),
                                  ),
                                  AirmiusTextField(
                                    label: t('application.licenseNumber'),
                                    hint: t('application.licenseHint'),
                                    controller: _license,
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              _FormSection(
                                step: '2',
                                title: t('application.contactData'),
                                children: [
                                  AirmiusTextField(
                                    label: '${t('application.email')} *',
                                    icon: Icons.mail_outline,
                                    controller: _email,
                                  ),
                                  AirmiusTextField(
                                    label: t('application.phone'),
                                    hint: '+49 ...',
                                    icon: Icons.phone_outlined,
                                    controller: _phone,
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              _FormSection(
                                step: '3',
                                title: t('application.addressData'),
                                children: [
                                  AirmiusTextField(
                                    label: '${t('clubs.wizard.country')} *',
                                    hint: 'DE',
                                    controller: _country,
                                  ),
                                  AirmiusTextField(
                                    label: '${t('clubs.wizard.street')} *',
                                    controller: _street,
                                  ),
                                  AirmiusTextField(
                                    label: '${t('clubs.wizard.houseNumber')} *',
                                    controller: _houseNumber,
                                  ),
                                  AirmiusTextField(
                                    label: '${t('clubs.postalCode')} *',
                                    controller: _postalCode,
                                  ),
                                  AirmiusTextField(
                                    label: '${t('clubs.city')} *',
                                    controller: _city,
                                  ),
                                  AirmiusTextField(
                                    label: t('application.stateRegion'),
                                    controller: _state,
                                  ),
                                ],
                              ),
                              if (showGuardianSection) ...[
                                const SizedBox(height: 12),
                                _FormSection(
                                  step: '4',
                                  title: t('application.guardian'),
                                  children: [
                                    AirmiusTextField(
                                      label: t('application.guardianName'),
                                      hint: t('application.ifMinor'),
                                      controller: _guardianName,
                                    ),
                                    AirmiusTextField(
                                      label: t('application.guardianEmail'),
                                      hint: t('application.optional'),
                                      controller: _guardianEmail,
                                    ),
                                  ],
                                ),
                              ],
                              const SizedBox(height: 12),
                              _FormSection(
                                step: '5',
                                title: t('application.emergencyContact'),
                                children: [
                                  AirmiusTextField(
                                    label: t('application.emergencyName'),
                                    hint: t('application.name'),
                                    controller: _emergencyName,
                                  ),
                                  AirmiusTextField(
                                    label: t('application.emergencyPhone'),
                                    hint: '+49 ...',
                                    controller: _emergencyPhone,
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              AirmiusPanel(
                                child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.stretch,
                                  children: [
                                    _StepHeader(
                                      step: '6',
                                      title: t('application.paymentData'),
                                    ),
                                    const SizedBox(height: 14),
                                    _SelectField(
                                      label: t('application.paymentMethod'),
                                      value: _paymentMethod,
                                      items: [
                                        (
                                          'bank_transfer',
                                          t('application.payment.bankTransfer'),
                                        ),
                                        ('cash', t('application.payment.cash')),
                                        (
                                          'sepa_debit',
                                          t('application.payment.sepa'),
                                        ),
                                      ],
                                      onChanged: (value) => setState(
                                        () => _paymentMethod = value,
                                      ),
                                    ),
                                    const SizedBox(height: 12),
                                    _SelectField(
                                      label: t('application.paymentCycle'),
                                      value: _paymentCycle,
                                      items: [
                                        (
                                          'monthly',
                                          t('application.cycle.monthly'),
                                        ),
                                        (
                                          'quarterly',
                                          t('application.cycle.quarterly'),
                                        ),
                                        (
                                          'half_yearly',
                                          t('application.cycle.halfYearly'),
                                        ),
                                        (
                                          'yearly',
                                          t('application.cycle.yearly'),
                                        ),
                                      ],
                                      onChanged: (value) =>
                                          setState(() => _paymentCycle = value),
                                    ),
                                    const SizedBox(height: 12),
                                    AirmiusTextField(
                                      label: 'IBAN',
                                      hint: t('application.sepaOnly'),
                                      controller: _iban,
                                    ),
                                    const SizedBox(height: 12),
                                    AirmiusTextField(
                                      label: 'BIC',
                                      hint: t('application.optional'),
                                      controller: _bic,
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 12),
                              AirmiusPanel(
                                child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.stretch,
                                  children: [
                                    Eyebrow(t('application.documentsRules')),
                                    const SizedBox(height: 8),
                                    Text(
                                      t('application.documentsHint'),
                                      style: TextStyle(
                                        color: airmiusMutedColor(context),
                                        height: 1.35,
                                      ),
                                    ),
                                    const SizedBox(height: 12),
                                    _UploadTile(
                                      selected: _uploadedDocument,
                                      onTap: () => setState(
                                        () => _uploadedDocument =
                                            !_uploadedDocument,
                                      ),
                                    ),
                                    const SizedBox(height: 10),
                                    _CheckLine(
                                      label: t('application.acceptPrivacy'),
                                      checked: _privacyAccepted,
                                      onChanged: (value) => setState(
                                        () => _privacyAccepted = value,
                                      ),
                                    ),
                                    _CheckLine(
                                      label: t('application.acceptRules'),
                                      checked: _documentsAccepted,
                                      onChanged: (value) => setState(
                                        () => _documentsAccepted = value,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 16),
                              if (_sendError != null) ...[
                                AirmiusPanel(
                                  borderColor: AirmiusColors.red.withValues(
                                    alpha: .55,
                                  ),
                                  child: Text(
                                    _sendError!,
                                    style: TextStyle(
                                      color: airmiusMutedColor(context),
                                      height: 1.35,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 12),
                              ],
                              AirmiusButton(
                                label: _sending
                                    ? t('application.sending')
                                    : scope.t('send'),
                                icon: _sending
                                    ? Icons.sync_outlined
                                    : Icons.send_outlined,
                                onPressed: _canSend && !_sending
                                    ? _submit
                                    : null,
                              ),
                              const SizedBox(height: 24),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  Future<void> _submit() async {
    setState(() {
      _sending = true;
      _sendError = null;
    });
    try {
      final services = AirmiusServicesScope.of(context);
      await services.repositories.memberships.applyToClub(widget.club.id, {
        'type': 'membership',
        'preferred_payment_method': _paymentMethod,
        'requested_billing_interval': _paymentCycle,
        'application_data': {
          'membership_type': _membershipType,
          'first_name': _firstName.text.trim(),
          'last_name': _lastName.text.trim(),
          'birth_date': formatAirmiusApiDate(
            parseAirmiusDate(_birthDate.text),
          ),
          'gender': _gender.text.trim(),
          'license_number': _license.text.trim(),
          'email': _email.text.trim(),
          'phone': _phone.text.trim(),
          'country': _country.text.trim(),
          'street': _street.text.trim(),
          'house_number': _houseNumber.text.trim(),
          'postal_code': _postalCode.text.trim(),
          'city': _city.text.trim(),
          'state': _state.text.trim(),
          'guardian_name': _guardianName.text.trim(),
          'guardian_email': _guardianEmail.text.trim(),
          'emergency_name': _emergencyName.text.trim(),
          'emergency_phone': _emergencyPhone.text.trim(),
          'iban': _iban.text.trim(),
          'bic': _bic.text.trim(),
          'privacy_accepted': _privacyAccepted,
          'documents_accepted': _documentsAccepted,
          'uploaded_document': _uploadedDocument,
          'source': 'flutter_mobile',
        },
        'accepted_documents': [
          if (_privacyAccepted) 'privacy',
          if (_documentsAccepted) 'club_rules',
          if (_uploadedDocument) 'uploaded_document',
        ],
        'message': 'Membership: $_membershipType',
        'source': 'flutter_mobile',
      });
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _sendError =
            '${AirmiusScope.of(context).t('application.sendFailed')}: ${error is AirmiusApiException ? error.userMessage : AirmiusScope.of(context).t('common.errorDetails')}';
        _sending = false;
      });
    }
  }

  void _prefillFromUser(AirmiusUser user) {
    final nameParts = _splitName(user);
    _fillIfEmpty(_firstName, user.firstName ?? nameParts.$1);
    _fillIfEmpty(_lastName, user.lastName ?? nameParts.$2);
    _fillIfEmpty(_birthDate, _formatDate(user.birthDate));
    if (_gender.text.trim().isEmpty &&
        _membershipGenderOptions.contains(user.gender)) {
      _gender.text = user.gender!;
    }
    _fillIfEmpty(_email, user.email);
    _fillIfEmpty(_country, user.country ?? 'DE');
    _fillIfEmpty(_street, user.street);
    _fillIfEmpty(_houseNumber, user.houseNumber);
    _fillIfEmpty(_postalCode, user.postalCode);
    _fillIfEmpty(_city, user.city);
    _fillIfEmpty(_state, user.state);
    _fillIfEmpty(_guardianEmail, user.guardianEmail);
  }

  void _fillIfEmpty(TextEditingController controller, String? value) {
    final text = value?.trim();
    if (text == null || text.isEmpty || controller.text.trim().isNotEmpty) {
      return;
    }
    controller.text = text;
  }

  (String?, String?) _splitName(AirmiusUser user) {
    final parts = user.name
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .toList();
    if (parts.isEmpty) return (null, null);
    if (parts.length == 1) return (parts.first, null);
    return (parts.first, parts.skip(1).join(' '));
  }

  String? _formatDate(DateTime? date) => formatAirmiusDate(date);

  DateTime? _parseBirthDate(String value) => parseAirmiusDate(value);

  bool _isKnownAdult(DateTime? birthDate) {
    if (birthDate == null) return false;
    final today = DateTime.now();
    var age = today.year - birthDate.year;
    if (today.month < birthDate.month ||
        (today.month == birthDate.month && today.day < birthDate.day)) {
      age -= 1;
    }
    return age >= 18;
  }
}

const _membershipGenderOptions = ['female', 'male', 'diverse', 'not_specified'];

class _FormSection extends StatelessWidget {
  const _FormSection({
    required this.step,
    required this.title,
    required this.children,
  });

  final String step;
  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _StepHeader(step: step, title: title),
          const SizedBox(height: 14),
          for (var i = 0; i < children.length; i++) ...[
            children[i],
            if (i < children.length - 1) const SizedBox(height: 12),
          ],
        ],
      ),
    );
  }
}

class _StepHeader extends StatelessWidget {
  const _StepHeader({required this.step, required this.title});

  final String step;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 30,
          height: 30,
          decoration: BoxDecoration(
            color: airmiusAccentColor(context).withValues(alpha: 0.18),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: airmiusAccentColor(context).withValues(alpha: 0.45),
            ),
          ),
          child: Center(
            child: Text(
              step,
              style: TextStyle(
                color: airmiusAccentColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            title,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      ],
    );
  }
}

class _SelectField extends StatelessWidget {
  const _SelectField({
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
  });

  final String label;
  final String value;
  final List<(String, String)> items;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<String>(
      isExpanded: true,
      initialValue: value,
      dropdownColor: airmiusSurfaceSoftColor(context),
      style: TextStyle(
        color: airmiusTextColor(context),
        fontWeight: FontWeight.w800,
      ),
      decoration: InputDecoration(labelText: label),
      items: items
          .map((item) => DropdownMenuItem(value: item.$1, child: Text(item.$2)))
          .toList(),
      onChanged: (value) {
        if (value != null) onChanged(value);
      },
    );
  }
}

class _ProgressPanel extends StatelessWidget {
  const _ProgressPanel({required this.done});

  final int done;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Eyebrow(t('application.progress'))),
              Text(
                '$done / 6',
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              value: done / 6,
              minHeight: 9,
              backgroundColor: airmiusSurfaceSoftColor(context),
              valueColor: AlwaysStoppedAnimation<Color>(
                airmiusAccentColor(context),
              ),
            ),
          ),
          const SizedBox(height: 10),
          Text(
            done == 6
                ? t('application.readyToSend')
                : t('application.confirmRules'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
        ],
      ),
    );
  }
}

class _UploadTile extends StatelessWidget {
  const _UploadTile({required this.selected, required this.onTap});

  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: airmiusSurfaceSoftColor(context),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? AirmiusColors.green : airmiusBorderColor(context),
          ),
        ),
        child: Row(
          children: [
            Icon(
              selected
                  ? Icons.check_circle_outline
                  : Icons.upload_file_outlined,
              color: selected
                  ? AirmiusColors.green
                  : airmiusAccentColor(context),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                t('application.uploadDocument'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            Text(
              selected ? t('application.selected') : t('application.file'),
              style: TextStyle(color: airmiusMutedColor(context), fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}

class _CheckLine extends StatelessWidget {
  const _CheckLine({
    required this.label,
    required this.checked,
    required this.onChanged,
  });

  final String label;
  final bool checked;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Material(
      type: MaterialType.transparency,
      child: CheckboxListTile(
        value: checked,
        onChanged: (value) => onChanged(value ?? false),
        contentPadding: EdgeInsets.zero,
        activeColor: airmiusAccentColor(context),
        title: Text(
          label,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w700,
            height: 1.3,
          ),
        ),
      ),
    );
  }
}
