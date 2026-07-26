import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_policy_documents_screen.dart';
import 'membership_request_status_screen.dart';

class MembershipApplicationFormScreen extends StatefulWidget {
  const MembershipApplicationFormScreen({super.key, this.clubId});

  final int? clubId;

  @override
  State<MembershipApplicationFormScreen> createState() =>
      _MembershipApplicationFormScreenState();
}

class _MembershipApplicationFormScreenState
    extends State<MembershipApplicationFormScreen> {
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _birthday = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _street = TextEditingController();
  final _house = TextEditingController();
  final _zip = TextEditingController();
  final _city = TextEditingController();
  final _state = TextEditingController();
  final _license = TextEditingController();
  final _guardianName = TextEditingController();
  final _guardianEmail = TextEditingController();
  final _emergencyName = TextEditingController();
  final _emergencyPhone = TextEditingController();
  final _iban = TextEditingController();
  final _bic = TextEditingController();
  final _consentSignature = TextEditingController();

  String _gender = '';
  String _membershipType = 'general';
  String _paymentMethod = 'bank_transfer';
  String _interval = 'monthly';
  bool _privacyAccepted = true;
  bool _rulesAccepted = true;
  bool _contributionAccepted = true;
  bool _sepaAccepted = false;
  bool _profilePrefilled = false;
  bool _submitting = false;
  int? _selectedClubId;
  Future<List<AirmiusClub>>? _clubsFuture;
  AirmiusClub? _selectedClub;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_profilePrefilled) return;
    _profilePrefilled = true;
    final authUser = AirmiusServicesScope.of(context).authState.user;
    _selectedClubId = widget.clubId;
    _clubsFuture = _loadEligibleClubs();
    if (authUser != null) {
      _prefillFromUser(authUser);
    }
  }

  Future<List<AirmiusClub>> _loadEligibleClubs() async {
    final services = AirmiusServicesScope.of(context);
    final page = await services.repositories.clubs.searchClubs();
    final clubs = <AirmiusClub>[];
    for (final summary in page.items) {
      if (!summary.acceptsMembershipApplications ||
          summary.isMember ||
          summary.hasPendingMembershipRequest) {
        continue;
      }
      clubs.add(await services.repositories.clubs.club(summary.id));
    }
    if (widget.clubId != null) {
      for (final club in clubs) {
        if (club.id == widget.clubId) {
          _selectedClub = club;
          break;
        }
      }
    }
    return clubs;
  }

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _birthday.dispose();
    _email.dispose();
    _phone.dispose();
    _street.dispose();
    _house.dispose();
    _zip.dispose();
    _city.dispose();
    _state.dispose();
    _license.dispose();
    _guardianName.dispose();
    _guardianEmail.dispose();
    _emergencyName.dispose();
    _emergencyPhone.dispose();
    _iban.dispose();
    _bic.dispose();
    _consentSignature.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final authUser = AirmiusServicesScope.of(context).authState.user;
    final showGuardianSection = !_isKnownAdult(
      authUser?.birthDate ?? _parseBirthDate(_birthday.text),
    );

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _clubPicker(),
                        const SizedBox(height: 16),
                        PageTitle(
                          title: t('application.membershipApplication'),
                          subtitle: t('application.fieldsHint'),
                        ),
                        const SizedBox(height: 16),
                        _ApplicationHero(
                          onSubmit: _submit,
                          clubName: _selectedClub?.name,
                        ),
                        const SizedBox(height: 16),
                        _SelectPanel(
                          title: t('application.membershipType'),
                          value: _membershipType,
                          values: const [
                            'general',
                            'active',
                            'trial',
                            'supporting',
                          ],
                          labels: {
                            'general': t('application.type.general'),
                            'active': t('application.type.active'),
                            'trial': t('application.type.trial'),
                            'supporting': t('application.type.supporting'),
                          },
                          onChanged: (value) =>
                              setState(() => _membershipType = value),
                        ),
                        const SizedBox(height: 12),
                        _FormSection(
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
                            DropdownButtonFormField<String>(
                              initialValue: _gender.isEmpty ? null : _gender,
                              dropdownColor: airmiusSurfaceSoftColor(context),
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
                                  child: Text(t('application.gender.female')),
                                ),
                                DropdownMenuItem(
                                  value: 'male',
                                  child: Text(t('application.gender.male')),
                                ),
                                DropdownMenuItem(
                                  value: 'diverse',
                                  child: Text(t('application.gender.diverse')),
                                ),
                                DropdownMenuItem(
                                  value: 'not_specified',
                                  child: Text(
                                    t('application.gender.unspecified'),
                                  ),
                                ),
                              ],
                              onChanged: (value) =>
                                  setState(() => _gender = value ?? ''),
                            ),
                            AirmiusTextField(
                              label: '${t('application.birthDate')} *',
                              controller: _birthday,
                            ),
                          ],
                        ),
                        _FormSection(
                          title: t('application.sportData'),
                          children: [
                            AirmiusTextField(
                              label: t('application.licenseNumber'),
                              controller: _license,
                            ),
                          ],
                        ),
                        _FormSection(
                          title: t('application.contactData'),
                          children: [
                            AirmiusTextField(
                              label: '${t('application.email')} *',
                              controller: _email,
                            ),
                            AirmiusTextField(
                              label: t('application.phone'),
                              controller: _phone,
                            ),
                          ],
                        ),
                        _FormSection(
                          title: t('application.addressData'),
                          children: [
                            AirmiusTextField(
                              label: '${t('application.street')} *',
                              controller: _street,
                            ),
                            AirmiusTextField(
                              label: '${t('application.houseNumber')} *',
                              controller: _house,
                            ),
                            AirmiusTextField(
                              label: '${t('application.postalCode')} *',
                              controller: _zip,
                            ),
                            AirmiusTextField(
                              label: '${t('application.city')} *',
                              controller: _city,
                            ),
                            AirmiusTextField(
                              label: t('application.stateRegion'),
                              controller: _state,
                            ),
                          ],
                        ),
                        if (showGuardianSection)
                          _FormSection(
                            title: t('application.guardian'),
                            children: [
                              AirmiusTextField(
                                label: t('application.guardianName'),
                                controller: _guardianName,
                              ),
                              AirmiusTextField(
                                label: t('application.guardianEmail'),
                                controller: _guardianEmail,
                              ),
                            ],
                          ),
                        _FormSection(
                          title: t('application.emergencyContact'),
                          children: [
                            AirmiusTextField(
                              label: t('application.emergencyName'),
                              controller: _emergencyName,
                            ),
                            AirmiusTextField(
                              label: t('application.emergencyPhone'),
                              controller: _emergencyPhone,
                            ),
                          ],
                        ),
                        _SelectPanel(
                          title: t('application.paymentMethod'),
                          value: _paymentMethod,
                          values: const ['bank_transfer', 'cash', 'sepa_debit'],
                          labels: {
                            'bank_transfer': t(
                              'application.payment.bankTransfer',
                            ),
                            'cash': t('application.payment.cash'),
                            'sepa_debit': t('application.payment.sepa'),
                          },
                          onChanged: (value) =>
                              setState(() => _paymentMethod = value),
                        ),
                        const SizedBox(height: 12),
                        _SelectPanel(
                          title: t('application.paymentCycle'),
                          value: _interval,
                          values: const [
                            'monthly',
                            'four_monthly',
                            'semi_yearly',
                            'yearly',
                          ],
                          labels: {
                            'monthly': t('application.cycle.monthly'),
                            'four_monthly': t('application.cycle.fourMonthly'),
                            'semi_yearly': t('application.cycle.halfYearly'),
                            'yearly': t('application.cycle.yearly'),
                          },
                          onChanged: (value) =>
                              setState(() => _interval = value),
                        ),
                        const SizedBox(height: 12),
                        _FormSection(
                          title: t('application.paymentData'),
                          children: [
                            AirmiusTextField(
                              label: t('application.iban'),
                              controller: _iban,
                            ),
                            AirmiusTextField(
                              label: t('application.bic'),
                              controller: _bic,
                            ),
                          ],
                        ),
                        _DocumentAcceptancePanel(
                          privacyAccepted: _privacyAccepted,
                          rulesAccepted: _rulesAccepted,
                          contributionAccepted: _contributionAccepted,
                          sepaAccepted: _sepaAccepted,
                          onPrivacy: (value) =>
                              setState(() => _privacyAccepted = value),
                          onRules: (value) =>
                              setState(() => _rulesAccepted = value),
                          onContribution: (value) =>
                              setState(() => _contributionAccepted = value),
                          onSepa: (value) =>
                              setState(() => _sepaAccepted = value),
                        ),
                        const SizedBox(height: 12),
                        AirmiusTextField(
                          label: t('application.signature'),
                          hint: t('application.signatureHint'),
                          controller: _consentSignature,
                          textInputAction: TextInputAction.next,
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: t('application.membershipApplication'),
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(
                                label: t('filesPreview.testLink'),
                                icon: Icons.policy_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => ClubPolicyDocumentsScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: t('uiAction.draft'),
                                icon: Icons.save_outlined,
                                secondary: true,
                                onPressed: () =>
                                    _toast(t('uiAction.draftBody')),
                              ),
                              AirmiusButton(
                                label: _submitting
                                    ? t('application.sending')
                                    : t('application.send'),
                                icon: Icons.send_outlined,
                                onPressed: _submitting ? null : _submit,
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _clubPicker() {
    final t = AirmiusScope.of(context).t;
    return FutureBuilder<List<AirmiusClub>>(
      future: _clubsFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return AirmiusPanel(
            title: t('membership.chooseClub'),
            child: Row(
              children: [
                const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
                const SizedBox(width: 10),
                Expanded(child: Text(t('membership.loadingClubs'))),
              ],
            ),
          );
        }
        if (snapshot.hasError) {
          return AirmiusPanel(
            borderColor: AirmiusColors.red.withValues(alpha: .45),
            title: t('membership.chooseClub'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(t('membership.loadClubsFailed')),
                const SizedBox(height: 10),
                AirmiusButton(
                  label: t('common.retry'),
                  icon: Icons.refresh_outlined,
                  secondary: true,
                  onPressed: () => setState(() {
                    _clubsFuture = _loadEligibleClubs();
                  }),
                ),
              ],
            ),
          );
        }
        final clubs = snapshot.data ?? const <AirmiusClub>[];
        if (clubs.isEmpty) {
          return AirmiusPanel(
            borderColor: AirmiusColors.amber.withValues(alpha: .45),
            title: t('membership.chooseClub'),
            child: Text(t('membership.noEligibleClubs')),
          );
        }
        final selectedValue = clubs.any((club) => club.id == _selectedClubId)
            ? _selectedClubId
            : null;
        return AirmiusPanel(
          title: t('membership.chooseClub'),
          child: DropdownButtonFormField<int>(
            initialValue: selectedValue,
            decoration: InputDecoration(
              labelText: t('membership.club'),
              prefixIcon: Icon(Icons.groups_2_outlined),
            ),
            items: [
              for (final club in clubs)
                DropdownMenuItem(value: club.id, child: Text(club.name)),
            ],
            onChanged: (id) {
              if (id == null) return;
              setState(() {
                _selectedClubId = id;
                _selectedClub = clubs.firstWhere((club) => club.id == id);
              });
            },
          ),
        );
      },
    );
  }

  Future<void> _submit() async {
    final clubId = _selectedClubId;
    if (clubId == null) {
      _toast(AirmiusScope.of(context).t('membership.selectClubFirst'));
      return;
    }
    final validation = _validateForm();
    if (validation != null) {
      _toast(validation);
      return;
    }

    setState(() => _submitting = true);
    try {
      final services = AirmiusServicesScope.of(context);
      final application = await services.repositories.memberships.applyToClub(
        clubId,
        _applicationPayload(),
      );
      if (!mounted) return;
      setState(() => _submitting = false);
      _toast(AirmiusScope.of(context).t('membership.sent'));
      await Navigator.push<void>(
        context,
        MaterialPageRoute(
          builder: (_) =>
              MembershipRequestStatusScreen(clubId: application.clubId),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _submitting = false);
      final message = error is AirmiusApiException
          ? error.userMessage
          : AirmiusScope.of(context).t('membership.submitFailed');
      _toast(message);
    }
  }

  String? _validateForm() {
    final t = AirmiusScope.of(context).t;
    if (_firstName.text.trim().isEmpty ||
        _lastName.text.trim().isEmpty ||
        _email.text.trim().isEmpty ||
        _street.text.trim().isEmpty ||
        _house.text.trim().isEmpty ||
        _zip.text.trim().isEmpty ||
        _city.text.trim().isEmpty ||
        _gender.isEmpty ||
        _parseBirthDate(_birthday.text) == null) {
      return t('membership.requiredFields');
    }
    if (!_privacyAccepted || !_rulesAccepted || !_contributionAccepted) {
      return t('membership.acceptRequired');
    }
    if (_paymentMethod == 'sepa_debit' && !_sepaAccepted) {
      return t('membership.sepaRequired');
    }
    return null;
  }

  JsonMap _applicationPayload() {
    final birthDate = _parseBirthDate(_birthday.text);
    final authUser = AirmiusServicesScope.of(context).authState.user;
    final data =
        <String, Object?>{
          'first_name': _firstName.text.trim(),
          'last_name': _lastName.text.trim(),
          'birth_date': birthDate?.toIso8601String().split('T').first,
          'gender': _gender,
          'email': _email.text.trim(),
          'phone': _phone.text.trim(),
          'country': authUser?.country?.trim().isNotEmpty == true
              ? authUser!.country!.trim()
              : 'DE',
          'street': _street.text.trim(),
          'house_number': _house.text.trim(),
          'postal_code': _zip.text.trim(),
          'city': _city.text.trim(),
          'state': _state.text.trim(),
          'athlete_license_number': _license.text.trim(),
          'guardian_name': _guardianName.text.trim(),
          'guardian_email': _guardianEmail.text.trim(),
          'emergency_contact_name': _emergencyName.text.trim(),
          'emergency_contact_phone': _emergencyPhone.text.trim(),
          'sepa_iban': _iban.text.trim(),
          'sepa_bic': _bic.text.trim(),
          'sepa_mandate_consent': _sepaAccepted,
        }..removeWhere(
          (key, value) => value == null || (value is String && value.isEmpty),
        );

    final settings =
        _selectedClub?.management?.settings ?? const <String, dynamic>{};
    final documents = settings['membership_application_documents'];
    final acceptedDocuments = <String, bool>{};
    if (documents is List) {
      for (final document in documents.whereType<JsonMap>()) {
        final id = document['id']?.toString();
        if (id == null || id.isEmpty) continue;
        final type = document['type']?.toString();
        acceptedDocuments[id] = switch (type) {
          'privacy' => _privacyAccepted,
          'rules' || 'statutes' => _rulesAccepted,
          'fees' => _contributionAccepted,
          'sepa' => _sepaAccepted,
          _ => true,
        };
      }
    }

    final payment = _paymentMethod;
    final interval = _interval;
    final membershipTypeId = _selectedMembershipTypeId();
    final payload = <String, dynamic>{
      'type': 'membership',
      'application_data': data,
      'accepted_documents': acceptedDocuments,
      'preferred_payment_method': payment,
      'requested_billing_interval': interval,
      'consent_version': 'membership-v1',
      'consent_signature': _consentSignature.text.trim(),
    };
    if (membershipTypeId != null) {
      payload['club_membership_type_id'] = membershipTypeId;
    }
    return payload;
  }

  int? _selectedMembershipTypeId() {
    final types =
        _selectedClub?.management?.membershipTypes ?? const <JsonMap>[];
    if (types.isEmpty) return null;
    final matching = types.where((type) {
      final slug = type['slug']?.toString().toLowerCase();
      return slug == _membershipType ||
          (_membershipType == 'general' && slug == 'standard');
    });
    final type = matching.isNotEmpty ? matching.first : types.first;
    final id = type['id'];
    return id is int ? id : int.tryParse('$id');
  }

  void _prefillFromUser(AirmiusUser user) {
    final nameParts = _splitName(user);
    _fillIfEmpty(_firstName, user.firstName ?? nameParts.$1);
    _fillIfEmpty(_lastName, user.lastName ?? nameParts.$2);
    if (_gender.isEmpty && _membershipGenderOptions.contains(user.gender)) {
      _gender = user.gender!;
    }
    _fillIfEmpty(_birthday, _formatDate(user.birthDate));
    _fillIfEmpty(_email, user.email);
    _fillIfEmpty(_street, user.street);
    _fillIfEmpty(_house, user.houseNumber);
    _fillIfEmpty(_zip, user.postalCode);
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

  String? _formatDate(DateTime? date) {
    if (date == null) return null;
    final day = date.day.toString().padLeft(2, '0');
    final month = date.month.toString().padLeft(2, '0');
    return '$day.$month.${date.year}';
  }

  DateTime? _parseBirthDate(String value) {
    final text = value.trim();
    if (text.isEmpty) return null;
    final iso = DateTime.tryParse(text);
    if (iso != null) return iso;
    final match = RegExp(r'^(\d{1,2})\.(\d{1,2})\.(\d{4})$').firstMatch(text);
    if (match == null) return null;
    return DateTime.tryParse(
      '${match.group(3)}-${match.group(2)!.padLeft(2, '0')}-${match.group(1)!.padLeft(2, '0')}',
    );
  }

  bool _isKnownAdult(DateTime? birthDate) {
    if (birthDate == null) return false;
    final today = DateTime.now();
    var age = today.year - birthDate.year;
    final hadBirthdayThisYear =
        today.month > birthDate.month ||
        (today.month == birthDate.month && today.day >= birthDate.day);
    if (!hadBirthdayThisYear) age -= 1;
    return age >= 18;
  }

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _ApplicationHero extends StatelessWidget {
  const _ApplicationHero({required this.onSubmit, this.clubName});

  final VoidCallback onSubmit;
  final String? clubName;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            airmiusSurfaceSoftColor(context),
            airmiusSurfaceColor(context),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: Theme.of(context).colorScheme.outline),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              AirmiusAvatar((clubName ?? 'Verein').trim(), large: true),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow(
                      AirmiusScope.of(
                        context,
                      ).t('application.membershipApplication'),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      clubName ?? AirmiusScope.of(context).t('membership.club'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      AirmiusScope.of(context).t('application.readyToSend'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              AirmiusButton(
                label: AirmiusScope.of(context).t('application.send'),
                icon: Icons.send_outlined,
                onPressed: onSubmit,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            AirmiusScope.of(context).t('application.fieldsHint'),
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _SelectPanel extends StatelessWidget {
  const _SelectPanel({
    required this.title,
    required this.value,
    required this.values,
    required this.onChanged,
    this.labels = const {},
  });

  final String title;
  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;
  final Map<String, String> labels;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final item in values)
            ChoiceChip(
              label: Text(labels[item] ?? item),
              selected: value == item,
              onSelected: (_) => onChanged(item),
              selectedColor: airmiusAccentColor(context).withValues(alpha: .24),
              backgroundColor: airmiusSurfaceColor(context),
              labelStyle: TextStyle(
                color: value == item
                    ? airmiusTextColor(context)
                    : airmiusMutedColor(context),
                fontWeight: FontWeight.w900,
              ),
              side: BorderSide(
                color: value == item
                    ? airmiusAccentColor(context)
                    : airmiusBorderColor(context),
              ),
            ),
        ],
      ),
    );
  }
}

class _FormSection extends StatelessWidget {
  const _FormSection({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: AirmiusPanel(
        title: title,
        child: Column(
          children: [
            for (final child in children) ...[
              child,
              const SizedBox(height: 10),
            ],
          ],
        ),
      ),
    );
  }
}

class _DocumentAcceptancePanel extends StatelessWidget {
  const _DocumentAcceptancePanel({
    required this.privacyAccepted,
    required this.rulesAccepted,
    required this.contributionAccepted,
    required this.sepaAccepted,
    required this.onPrivacy,
    required this.onRules,
    required this.onContribution,
    required this.onSepa,
  });

  final bool privacyAccepted;
  final bool rulesAccepted;
  final bool contributionAccepted;
  final bool sepaAccepted;
  final ValueChanged<bool> onPrivacy;
  final ValueChanged<bool> onRules;
  final ValueChanged<bool> onContribution;
  final ValueChanged<bool> onSepa;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      title: t('application.documentsRules'),
      child: Column(
        children: [
          _CheckRow(
            title: '${t('application.acceptPrivacy')} *',
            value: privacyAccepted,
            onChanged: onPrivacy,
          ),
          _CheckRow(
            title: '${t('application.acceptRules')} *',
            value: rulesAccepted,
            onChanged: onRules,
          ),
          _CheckRow(
            title: '${t('membership.document.fees')} *',
            value: contributionAccepted,
            onChanged: onContribution,
          ),
          _CheckRow(
            title: t('membership.document.sepa'),
            value: sepaAccepted,
            onChanged: onSepa,
          ),
        ],
      ),
    );
  }
}

class _CheckRow extends StatelessWidget {
  const _CheckRow({
    required this.title,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: value
              ? airmiusAccentColor(context)
              : airmiusBorderColor(context),
        ),
      ),
      child: Row(
        children: [
          Checkbox(
            value: value,
            onChanged: (next) => onChanged(next ?? false),
            activeColor: airmiusAccentColor(context),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

const _membershipGenderOptions = ['female', 'male', 'diverse', 'not_specified'];
