import 'package:flutter/material.dart';
import '../widgets/country_field.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_date_input.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
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
  final _nationality = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _country = TextEditingController();
  final _street = TextEditingController();
  final _house = TextEditingController();
  final _zip = TextEditingController();
  final _city = TextEditingController();
  final _state = TextEditingController();
  final _license = TextEditingController();
  final _guardianName = TextEditingController();
  final _guardianEmail = TextEditingController();
  final _guardianPhone = TextEditingController();
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
  bool _profileAddressWasComplete = false;
  bool _submitting = false;
  int _activeTab = 0;
  bool _validationVisible = false;
  Set<String> _validationErrors = <String>{};
  final Map<String, bool> _acceptedDocuments = {};
  int? _selectedClubId;
  Future<List<AirmiusClub>>? _clubsFuture;
  AirmiusClub? _selectedClub;

  static const Map<String, String> _defaultFieldModes = {
    'first_name': 'required',
    'last_name': 'required',
    'birth_date': 'required',
    'gender': 'required',
    'email': 'required',
    'phone': 'optional',
    'country': 'required',
    'street': 'required',
    'house_number': 'required',
    'postal_code': 'required',
    'city': 'required',
    'state': 'optional',
    'athlete_license_number': 'optional',
    'guardian_name': 'optional',
    'guardian_email': 'optional',
    'guardian_phone': 'optional',
    'emergency_contact_name': 'optional',
    'emergency_contact_phone': 'optional',
    'sepa_iban': 'optional',
    'sepa_bic': 'optional',
    'sepa_mandate_consent': 'optional',
  };

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_profilePrefilled) return;
    _profilePrefilled = true;
    final authUser = AirmiusServicesScope.of(context).authState.user;
    _selectedClubId = widget.clubId;
    _clubsFuture = _loadEligibleClubs();
    if (authUser != null) {
      _profileAddressWasComplete = _hasCompleteProfileAddress(authUser);
      _prefillFromUser(authUser);
    }
  }

  Future<List<AirmiusClub>> _loadEligibleClubs() async {
    final services = AirmiusServicesScope.of(context);

    // When the form was opened from a club profile, loading the whole club
    // directory first only delays the data needed for the first tab.
    if (widget.clubId != null) {
      final direct = await services.repositories.clubs.club(widget.clubId!);
      if (!direct.acceptsMembershipApplications ||
          direct.isMember ||
          direct.hasPendingMembershipRequest) {
        return const <AirmiusClub>[];
      }

      _selectedClub = direct;
      _syncClubSettings(direct);
      if (mounted) setState(() {});
      return [direct];
    }

    final page = await services.repositories.clubs.searchClubs();
    final eligibleSummaries = page.items
        .where(
          (summary) =>
              summary.acceptsMembershipApplications &&
              !summary.isMember &&
              !summary.hasPendingMembershipRequest,
        )
        .toList(growable: false);

    // Keep the directory flow fast as well: detail requests are independent
    // and can be resolved concurrently instead of one after another.
    final loaded = await Future.wait(
      eligibleSummaries.map(
        (summary) => services.repositories.clubs.club(summary.id),
      ),
    );
    final clubs = <AirmiusClub>[];
    final loadedIds = <int>{};
    for (final club in loaded) {
      if (loadedIds.add(club.id)) clubs.add(club);
    }
    return clubs;
  }

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _birthday.dispose();
    _nationality.dispose();
    _email.dispose();
    _phone.dispose();
    _country.dispose();
    _street.dispose();
    _house.dispose();
    _zip.dispose();
    _city.dispose();
    _state.dispose();
    _license.dispose();
    _guardianName.dispose();
    _guardianEmail.dispose();
    _guardianPhone.dispose();
    _emergencyName.dispose();
    _emergencyPhone.dispose();
    _iban.dispose();
    _bic.dispose();
    _consentSignature.dispose();
    super.dispose();
  }

  JsonMap get _clubSettings =>
      _selectedClub?.management?.settings ?? const <String, dynamic>{};

  List<JsonMap> get _applicationFields {
    final raw = _clubSettings['membership_application_fields'];
    final base = raw is List
        ? raw.whereType<JsonMap>().toList(growable: false)
        : const <JsonMap>[];
    final selectedType = _membershipTypes.cast<JsonMap?>().firstWhere(
      (type) =>
          type?['id']?.toString() == _membershipType ||
          type?['slug']?.toString() == _membershipType,
      orElse: () => null,
    );
    final overrides = selectedType?['application_fields'];
    if (overrides is! JsonMap) return base;
    return base
        .map(
          (field) => {
            ...field,
            'mode': overrides[field['key']] ?? field['mode'],
          },
        )
        .toList(growable: false);
  }

  JsonMap? _field(String key) {
    for (final field in _applicationFields) {
      if (field['key']?.toString() == key) return field;
    }
    return null;
  }

  String _fieldMode(String key) {
    final mode = _field(key)?['mode']?.toString();
    if (key == 'gender') return 'required';
    return mode ?? _defaultFieldModes[key] ?? 'off';
  }

  bool _fieldVisible(String key) => _fieldMode(key) != 'off';

  bool _fieldRequired(String key) => _fieldMode(key) == 'required';

  String _fieldLabel(String key, String fallback) {
    final label = _field(key)?['label']?.toString().trim();
    return label == null || label.isEmpty ? fallback : label;
  }

  String _label(String key, String fallback) =>
      '${_fieldLabel(key, fallback)}${_fieldRequired(key) ? ' *' : ''}';

  Object? _fieldValue(String key) => switch (key) {
    'first_name' => _firstName.text,
    'last_name' => _lastName.text,
    'birth_date' => _parseBirthDate(_birthday.text),
    'gender' => _gender,
    'nationality' => _nationality.text,
    'athlete_license_number' => _license.text,
    'email' => _email.text,
    'phone' => _phone.text,
    'country' => _country.text,
    'street' => _street.text,
    'house_number' => _house.text,
    'postal_code' => _zip.text,
    'city' => _city.text,
    'state' => _state.text,
    'guardian_name' => _guardianName.text,
    'guardian_email' => _guardianEmail.text,
    'guardian_phone' => _guardianPhone.text,
    'emergency_contact_name' => _emergencyName.text,
    'emergency_contact_phone' => _emergencyPhone.text,
    'sepa_iban' => _iban.text,
    'sepa_bic' => _bic.text,
    'sepa_mandate_consent' => _sepaAccepted,
    _ => null,
  };

  bool _fieldHasMissingValue(String key) {
    final value = _fieldValue(key);
    if (value is String) return value.trim().isEmpty;
    return value == null || value == false;
  }

  bool _hasCompleteProfileAddress(AirmiusUser user) {
    return [
      user.street,
      user.houseNumber,
      user.postalCode,
      user.city,
    ].every((value) => value?.trim().isNotEmpty == true);
  }

  bool _hasEnteredAddress() {
    const addressKeys = ['street', 'house_number', 'postal_code', 'city'];
    final visibleKeys = addressKeys
        .where(_fieldVisible)
        .toList(growable: false);
    if (visibleKeys.isEmpty) return false;
    return visibleKeys.every((key) => !_fieldHasMissingValue(key));
  }

  bool get _shouldAskToSaveAddress =>
      !_profileAddressWasComplete && _hasEnteredAddress();

  List<String> _missingValidationKeys() {
    final missing = <String>[];
    if (_membershipTypes.isEmpty) missing.add('membership_type');

    final fieldKeys = <String>{
      ..._defaultFieldModes.keys,
      for (final field in _applicationFields)
        if (_defaultFieldModes.containsKey(field['key']?.toString()))
          field['key'].toString(),
    };
    for (final key in fieldKeys) {
      if (!_fieldRequired(key)) continue;
      if (_fieldHasMissingValue(key)) missing.add(key);
    }

    if (_visibleDocuments.isEmpty) {
      if (!_privacyAccepted) missing.add('legacy_privacy');
      if (!_rulesAccepted) missing.add('legacy_rules');
      if (!_contributionAccepted) missing.add('legacy_contribution');
    } else {
      for (final document in _visibleDocuments) {
        if (document['is_required'] != true) continue;
        final id = document['id']?.toString();
        if (id != null && _acceptedDocuments[id] != true) {
          missing.add('accepted_documents.$id');
        }
      }
    }

    return missing;
  }

  int _tabIndexForValidationKey(String key) {
    if (key == 'membership_type') return 0;
    if (key.startsWith('accepted_documents.') || key.startsWith('legacy_')) {
      return 6;
    }
    if (const {
      'first_name',
      'last_name',
      'birth_date',
      'gender',
      'nationality',
    }.contains(key)) {
      return 1;
    }
    if (const {'athlete_license_number', 'email', 'phone'}.contains(key)) {
      return 2;
    }
    if (const {
      'country',
      'street',
      'house_number',
      'postal_code',
      'city',
      'state',
    }.contains(key)) {
      return 3;
    }
    if (const {
      'guardian_name',
      'guardian_email',
      'guardian_phone',
      'emergency_contact_name',
      'emergency_contact_phone',
    }.contains(key)) {
      return 4;
    }
    if (const {'sepa_iban', 'sepa_bic', 'sepa_mandate_consent'}.contains(key)) {
      return 5;
    }
    return 4;
  }

  Set<int> get _validationErrorTabs => {
    for (final key in _missingValidationKeys()) _tabIndexForValidationKey(key),
  };

  bool _fieldHasError(String key) =>
      _validationVisible &&
      _validationErrors.contains(key) &&
      _fieldHasMissingValue(key);

  String? _fieldErrorText(String key) => _fieldHasError(key)
      ? AirmiusScope.of(context).t('application.requiredField')
      : null;

  String _membershipAmount(JsonMap type, String Function(String) t) {
    final raw = type['amount'];
    final amount = double.tryParse(raw?.toString().replaceAll(',', '.') ?? '');
    if (amount == null) return t('application.contributionByAgreement');
    return NumberFormat.simpleCurrency(
      locale: AirmiusScope.of(context).language.locale.toLanguageTag(),
      name: 'EUR',
    ).format(amount);
  }

  String _membershipInterval(String? interval, String Function(String) t) =>
      switch (interval) {
        'monthly' => t('application.cycle.monthly'),
        'quarterly' => t('application.cycle.quarterly'),
        'four_monthly' => t('application.cycle.fourMonthly'),
        'semi_yearly' => t('application.cycle.halfYearly'),
        'yearly' => t('application.cycle.yearly'),
        'once' => t('application.cycle.once'),
        _ => t('application.cycle.none'),
      };

  List<String> get _allowedPaymentMethods {
    final raw = _clubSettings['membership_payment_methods'];
    final methods = raw is List
        ? raw
              .map((method) => method.toString())
              .where((method) => method.isNotEmpty)
              .toList()
        : const <String>[];
    return methods.isEmpty ? const ['bank_transfer', 'cash'] : methods;
  }

  String get _effectivePaymentMethod =>
      _allowedPaymentMethods.contains(_paymentMethod)
      ? _paymentMethod
      : _allowedPaymentMethods.first;

  String get _billingInterval =>
      const [
        'none',
        'monthly',
        'quarterly',
        'four_monthly',
        'semi_yearly',
        'yearly',
        'once',
      ].contains(_interval)
      ? _interval
      : 'monthly';

  List<JsonMap> get _membershipTypes =>
      _selectedClub?.management?.membershipTypes ?? const <JsonMap>[];

  List<String> get _membershipTypeValues {
    return _membershipTypes
        .map((type) => (type['slug'] ?? type['id']).toString())
        .where((value) => value.isNotEmpty)
        .toList(growable: false);
  }

  List<JsonMap> get _visibleDocuments {
    final raw = _clubSettings['membership_application_documents'];
    if (raw is List) {
      final selectedType = _membershipTypes.cast<JsonMap?>().firstWhere(
        (type) =>
            type?['id']?.toString() == _membershipType ||
            type?['slug']?.toString() == _membershipType,
        orElse: () => null,
      );
      final selectedTypeId = selectedType?['id']?.toString();
      return raw
          .whereType<JsonMap>()
          .where((document) {
            final typeId = document['membership_type_id']?.toString();
            return typeId == null || typeId.isEmpty || typeId == selectedTypeId;
          })
          .toList(growable: false);
    }
    return const <JsonMap>[];
  }

  void _syncClubSettings(AirmiusClub club) {
    final settings = club.management?.settings ?? const <String, dynamic>{};
    final documents = settings['membership_application_documents'];
    _acceptedDocuments
      ..clear()
      ..addEntries(
        documents is List
            ? documents
                  .whereType<JsonMap>()
                  .map((document) {
                    final id = document['id']?.toString();
                    return id == null || id.isEmpty
                        ? const MapEntry('', false)
                        : MapEntry(id, false);
                  })
                  .where((entry) => entry.key.isNotEmpty)
            : const <MapEntry<String, bool>>[],
      );
    if (!_allowedPaymentMethods.contains(_paymentMethod)) {
      _paymentMethod = _allowedPaymentMethods.first;
    }
    final values = _membershipTypeValues;
    if (values.isNotEmpty && !values.contains(_membershipType)) {
      _membershipType = values.first;
    } else if (values.isEmpty) {
      _membershipType = '';
    }
    _validationVisible = false;
    _validationErrors = <String>{};
  }

  Widget _configuredTextField(
    String key,
    String fallback,
    TextEditingController controller, {
    String? hint,
    TextInputType? keyboardType,
    List<TextInputFormatter>? inputFormatters,
  }) {
    if (!_fieldVisible(key)) return const SizedBox.shrink();
    return AirmiusTextField(
      label: _label(key, fallback),
      hint: hint,
      controller: controller,
      keyboardType: keyboardType,
      inputFormatters: inputFormatters,
      errorText: _fieldErrorText(key),
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
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
                        _ApplicationHero(clubName: _selectedClub?.name),
                        const SizedBox(height: 16),
                        _ApplicationTabs(
                          title: t('application.membershipApplication'),
                          labels: [
                            t('application.membershipType'),
                            t('application.personalData'),
                            t('application.contactData'),
                            t('application.addressData'),
                            t('application.guardian'),
                            t('application.paymentData'),
                            t('application.documentsRules'),
                          ],
                          activeIndex: _activeTab,
                          errorIndexes: _validationVisible
                              ? _validationErrorTabs
                              : const <int>{},
                          onChanged: (index) =>
                              setState(() => _activeTab = index),
                        ),
                        const SizedBox(height: 14),
                        ..._applicationTab(t),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            if (_activeTab > 0)
                              AirmiusButton(
                                label: t('common.back'),
                                icon: Icons.arrow_back_outlined,
                                secondary: true,
                                onPressed: () => setState(() => _activeTab--),
                              )
                            else
                              const SizedBox.shrink(),
                            const Spacer(),
                            Text(
                              '${_activeTab + 1} / 7',
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const Spacer(),
                            if (_activeTab < 6)
                              AirmiusButton(
                                label: t('membership.next'),
                                icon: Icons.arrow_forward_outlined,
                                onPressed: _nextApplicationTab,
                              )
                            else
                              AirmiusButton(
                                label: _submitting
                                    ? t('application.sending')
                                    : t('application.send'),
                                icon: Icons.send_outlined,
                                onPressed: _submitting ? null : _submit,
                              ),
                          ],
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

  List<Widget> _applicationTab(String Function(String) t) {
    switch (_activeTab) {
      case 1:
        return _personalTab(t);
      case 2:
        return _contactTab(t);
      case 3:
        return _addressTab(t);
      case 4:
        return _additionalTab(t);
      case 5:
        return _paymentTab(t);
      case 6:
        return _documentsTab(t);
      default:
        return _membershipTab(t);
    }
  }

  List<Widget> _membershipTab(String Function(String) t) => [
    if (_membershipTypes.isEmpty)
      AirmiusPanel(
        title: t('application.membershipType'),
        child: Text(
          t('application.noMembershipTypes'),
          style: TextStyle(
            color: airmiusMutedColor(context),
            fontWeight: FontWeight.w700,
            height: 1.45,
          ),
        ),
      ),
    if (_membershipTypes.isNotEmpty) ...[
      const SizedBox(height: 12),
      AirmiusPanel(
        title: t('application.membershipCosts'),
        child: Column(
          children: [
            for (final type in _membershipTypes) ...[
              GestureDetector(
                onTap: () => setState(
                  () =>
                      _membershipType = (type['slug'] ?? type['id']).toString(),
                ),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: airmiusInputColor(context),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color:
                          (type['id']?.toString() == _membershipType ||
                              type['slug']?.toString() == _membershipType)
                          ? airmiusAccentColor(context)
                          : airmiusBorderColor(context),
                      width:
                          (type['id']?.toString() == _membershipType ||
                              type['slug']?.toString() == _membershipType)
                          ? 2
                          : 1,
                    ),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              type['name']?.toString() ??
                                  t('application.membershipType'),
                              style: TextStyle(
                                color: airmiusTextColor(context),
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            if (type['description']
                                    ?.toString()
                                    .trim()
                                    .isNotEmpty ==
                                true) ...[
                              const SizedBox(height: 4),
                              Text(
                                type['description'].toString(),
                                style: TextStyle(
                                  color: airmiusMutedColor(context),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(width: 12),
                      Text(
                        '${_membershipAmount(type, t)}\n${_membershipInterval(type['billing_interval']?.toString(), t)}',
                        textAlign: TextAlign.end,
                        style: TextStyle(
                          color: airmiusAccentColor(context),
                          fontWeight: FontWeight.w900,
                          height: 1.35,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              if (type != _membershipTypes.last) const SizedBox(height: 8),
            ],
          ],
        ),
      ),
    ],
    const SizedBox(height: 12),
    AirmiusPanel(
      title: t('application.membershipApplication'),
      child: Text(
        t('application.fieldsHint'),
        style: TextStyle(
          color: airmiusMutedColor(context),
          fontWeight: FontWeight.w700,
          height: 1.45,
        ),
      ),
    ),
  ];

  List<Widget> _personalTab(String Function(String) t) => [
    if (_fieldVisible('first_name') ||
        _fieldVisible('last_name') ||
        _fieldVisible('gender') ||
        _fieldVisible('birth_date') ||
        _fieldVisible('nationality'))
      _FormSection(
        title: t('application.personalData'),
        children: [
          _configuredTextField(
            'first_name',
            t('application.firstName'),
            _firstName,
          ),
          _configuredTextField(
            'last_name',
            t('application.lastName'),
            _lastName,
          ),
          DropdownButtonFormField<String>(
            initialValue: _gender.isEmpty ? null : _gender,
            dropdownColor: airmiusSurfaceSoftColor(context),
            decoration: InputDecoration(
              labelText: _label('gender', t('application.gender')),
              prefixIcon: Icon(
                Icons.wc_outlined,
                color: airmiusMutedColor(context),
              ),
              errorText: _fieldErrorText('gender'),
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
                child: Text(t('application.gender.unspecified')),
              ),
            ],
            onChanged: (value) => setState(() => _gender = value ?? ''),
          ),
          _configuredTextField(
            'birth_date',
            t('application.birthDate'),
            _birthday,
            hint: t('application.dateHint'),
            keyboardType: TextInputType.datetime,
            inputFormatters: const [AirmiusDateInputFormatter()],
          ),
          _configuredTextField(
            'nationality',
            'Staatsangehörigkeit',
            _nationality,
          ),
        ],
      ),
  ];

  List<Widget> _contactTab(String Function(String) t) => [
    if (_fieldVisible('athlete_license_number'))
      _FormSection(
        title: t('application.sportData'),
        children: [
          _configuredTextField(
            'athlete_license_number',
            t('application.licenseNumber'),
            _license,
          ),
        ],
      ),
    if (_fieldVisible('email') || _fieldVisible('phone'))
      _FormSection(
        title: t('application.contactData'),
        children: [
          _configuredTextField(
            'email',
            t('application.email'),
            _email,
            keyboardType: TextInputType.emailAddress,
          ),
          _configuredTextField(
            'phone',
            t('application.phone'),
            _phone,
            keyboardType: TextInputType.phone,
          ),
        ],
      ),
  ];

  List<Widget> _addressTab(String Function(String) t) => [
    if (_fieldVisible('country') ||
        _fieldVisible('street') ||
        _fieldVisible('house_number') ||
        _fieldVisible('postal_code') ||
        _fieldVisible('city') ||
        _fieldVisible('state'))
      _FormSection(
        title: t('application.addressData'),
        children: [
          if (_fieldVisible('country'))
            CountryField(
              controller: _country,
              label: _fieldLabel('country', 'Land'),
              required: _fieldRequired('country'),
            ),
          _configuredTextField('street', t('application.street'), _street),
          _configuredTextField(
            'house_number',
            t('application.houseNumber'),
            _house,
          ),
          _configuredTextField(
            'postal_code',
            t('application.postalCode'),
            _zip,
          ),
          _configuredTextField('city', t('application.city'), _city),
          _configuredTextField('state', t('application.stateRegion'), _state),
        ],
      ),
  ];

  List<Widget> _additionalTab(String Function(String) t) => [
    if (_fieldVisible('guardian_name') ||
        _fieldVisible('guardian_email') ||
        _fieldVisible('guardian_phone'))
      _FormSection(
        title: t('application.guardian'),
        children: [
          _configuredTextField(
            'guardian_name',
            t('application.guardianName'),
            _guardianName,
          ),
          _configuredTextField(
            'guardian_email',
            t('application.guardianEmail'),
            _guardianEmail,
            keyboardType: TextInputType.emailAddress,
          ),
          _configuredTextField(
            'guardian_phone',
            'Telefon Erziehungsberechtigte/r',
            _guardianPhone,
            keyboardType: TextInputType.phone,
          ),
        ],
      ),
    if (_fieldVisible('emergency_contact_name') ||
        _fieldVisible('emergency_contact_phone'))
      _FormSection(
        title: t('application.emergencyContact'),
        children: [
          _configuredTextField(
            'emergency_contact_name',
            t('application.emergencyName'),
            _emergencyName,
          ),
          _configuredTextField(
            'emergency_contact_phone',
            t('application.emergencyPhone'),
            _emergencyPhone,
            keyboardType: TextInputType.phone,
          ),
        ],
      ),
  ];

  List<Widget> _paymentTab(String Function(String) t) => [
    _SelectPanel(
      title: t('application.paymentMethod'),
      value: _effectivePaymentMethod,
      values: _allowedPaymentMethods,
      labels: {
        'bank_transfer': t('application.payment.bankTransfer'),
        'cash': t('application.payment.cash'),
        'sepa_debit': t('application.payment.sepa'),
      },
      onChanged: (value) => setState(() => _paymentMethod = value),
    ),
    const SizedBox(height: 12),
    _SelectPanel(
      title: t('application.paymentCycle'),
      value: _billingInterval,
      values: const [
        'none',
        'monthly',
        'quarterly',
        'four_monthly',
        'semi_yearly',
        'yearly',
        'once',
      ],
      labels: {
        'none': 'Kein Intervall',
        'monthly': t('application.cycle.monthly'),
        'quarterly': t('application.cycle.quarterly'),
        'four_monthly': t('application.cycle.fourMonthly'),
        'semi_yearly': t('application.cycle.halfYearly'),
        'yearly': t('application.cycle.yearly'),
        'once': 'Einmalig',
      },
      onChanged: (value) => setState(() => _interval = value),
    ),
    const SizedBox(height: 12),
    if (_fieldVisible('sepa_iban') || _fieldVisible('sepa_bic'))
      _FormSection(
        title: t('application.paymentData'),
        children: [
          _configuredTextField('sepa_iban', t('application.iban'), _iban),
          _configuredTextField('sepa_bic', t('application.bic'), _bic),
        ],
      ),
  ];

  List<Widget> _documentsTab(String Function(String) t) => [
    _DocumentAcceptancePanel(
      privacyAccepted: _privacyAccepted,
      rulesAccepted: _rulesAccepted,
      contributionAccepted: _contributionAccepted,
      sepaAccepted: _sepaAccepted,
      documents: _visibleDocuments,
      acceptedDocuments: _acceptedDocuments,
      errorDocumentIds: {
        for (final key in _validationErrors)
          if (key.startsWith('accepted_documents.'))
            key.substring('accepted_documents.'.length),
      },
      privacyError:
          _validationVisible &&
          _validationErrors.contains('legacy_privacy') &&
          !_privacyAccepted,
      rulesError:
          _validationVisible &&
          _validationErrors.contains('legacy_rules') &&
          !_rulesAccepted,
      contributionError:
          _validationVisible &&
          _validationErrors.contains('legacy_contribution') &&
          !_contributionAccepted,
      onPrivacy: (value) => setState(() => _privacyAccepted = value),
      onRules: (value) => setState(() => _rulesAccepted = value),
      onContribution: (value) => setState(() => _contributionAccepted = value),
      onSepa: (value) => setState(() => _sepaAccepted = value),
      onOpen: (document, preview) =>
          _openMembershipDocument(document, preview: preview),
      onDocument: (id, value) => setState(() {
        _acceptedDocuments[id] = value;
        final document = _visibleDocuments.where(
          (item) => item['id']?.toString() == id,
        );
        if (document.isEmpty) return;
        final type = document.first['type']?.toString();
        if (type == 'privacy') {
          _privacyAccepted = value;
        } else if (type == 'rules' || type == 'statutes') {
          _rulesAccepted = value;
        } else if (type == 'fees') {
          _contributionAccepted = value;
        } else if (type == 'sepa') {
          _sepaAccepted = value;
        }
      }),
    ),
    const SizedBox(height: 12),
    AirmiusTextField(
      label: t('application.signature'),
      hint: t('application.signatureHint'),
      controller: _consentSignature,
      textInputAction: TextInputAction.next,
    ),
  ];

  Widget _clubPicker() {
    if (widget.clubId != null) return const SizedBox.shrink();
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
                _syncClubSettings(_selectedClub!);
              });
            },
          ),
        );
      },
    );
  }

  Future<void> _submit() async {
    final clubId = _selectedClubId;
    final services = AirmiusServicesScope.of(context);
    if (clubId == null) {
      _toast(AirmiusScope.of(context).t('membership.selectClubFirst'));
      return;
    }
    final validation = _validateForm();
    if (validation != null) {
      _toast(validation);
      return;
    }

    var saveAddress = false;
    if (_shouldAskToSaveAddress) {
      final choice = await _askToSaveAddress();
      if (!mounted || choice == null) return;
      saveAddress = choice;
    }

    setState(() => _submitting = true);
    if (saveAddress) {
      try {
        await _saveAddressToProfile();
      } catch (_) {
        if (!mounted) return;
        setState(() => _submitting = false);
        _toast(AirmiusScope.of(context).t('application.addressSaveFailed'));
        return;
      }
    }
    try {
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
          builder: (_) => MembershipRequestStatusScreen(
            clubId: application.clubId,
            applicationId: application.id,
          ),
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

  Future<bool?> _askToSaveAddress() {
    final t = AirmiusScope.of(context).t;
    return showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('application.saveAddressTitle')),
        content: Text(t('application.saveAddressBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('application.addressOnly')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('application.saveAddress')),
          ),
        ],
      ),
    );
  }

  Future<void> _saveAddressToProfile() async {
    final services = AirmiusServicesScope.of(context);
    final user = services.authState.user;
    final country = _country.text.trim().isNotEmpty
        ? _country.text.trim().toUpperCase()
        : (user?.country?.trim().isNotEmpty == true
              ? user!.country!.trim().toUpperCase()
              : 'DE');
    final client = services.clientForSession(services.authState.session);
    await client.updateSettings({
      'country': country,
      'street': _street.text.trim(),
      'house_number': _house.text.trim(),
      'postal_code': _zip.text.trim(),
      'city': _city.text.trim(),
      'state': _state.text.trim(),
    });
    _profileAddressWasComplete = true;
    await services.authState.refreshUser();
  }

  void _nextApplicationTab() {
    final currentMissing = _missingValidationKeys()
        .where((key) => _tabIndexForValidationKey(key) == _activeTab)
        .toList(growable: false);
    setState(() {
      _validationVisible = true;
      _validationErrors = {..._validationErrors, ...currentMissing};
    });
    if (currentMissing.isNotEmpty) {
      _toast(AirmiusScope.of(context).t('membership.requiredFields'));
      return;
    }
    if (_activeTab < 6) setState(() => _activeTab++);
  }

  String? _validateForm() {
    final t = AirmiusScope.of(context).t;
    final missing = _missingValidationKeys();
    setState(() {
      _validationVisible = true;
      _validationErrors = missing.toSet();
      if (missing.isNotEmpty) {
        _activeTab = _tabIndexForValidationKey(missing.first);
      }
    });
    if (_membershipTypes.isEmpty) return t('application.noMembershipTypes');
    if (missing.any(
      (key) =>
          key.startsWith('accepted_documents.') || key.startsWith('legacy_'),
    )) {
      return t('membership.acceptRequired');
    }
    if (missing.isNotEmpty) return t('membership.requiredFields');
    return null;
  }

  JsonMap _applicationPayload() {
    final birthDate = _parseBirthDate(_birthday.text);
    final authUser = AirmiusServicesScope.of(context).authState.user;
    final data =
        <String, Object?>{
          'first_name': _firstName.text.trim(),
          'last_name': _lastName.text.trim(),
          'birth_date': formatAirmiusApiDate(birthDate),
          'gender': _gender,
          'nationality': _nationality.text.trim(),
          'email': _email.text.trim(),
          'phone': _phone.text.trim(),
          'country': _country.text.trim().isNotEmpty
              ? _country.text.trim()
              : (authUser?.country?.trim().isNotEmpty == true
                    ? authUser!.country!.trim()
                    : 'DE'),
          'street': _street.text.trim(),
          'house_number': _house.text.trim(),
          'postal_code': _zip.text.trim(),
          'city': _city.text.trim(),
          'state': _state.text.trim(),
          'athlete_license_number': _license.text.trim(),
          'guardian_name': _guardianName.text.trim(),
          'guardian_email': _guardianEmail.text.trim(),
          'guardian_phone': _guardianPhone.text.trim(),
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
        acceptedDocuments[id] =
            _acceptedDocuments[id] ??
            switch (type) {
              'privacy' => _privacyAccepted,
              'rules' || 'statutes' => _rulesAccepted,
              'fees' => _contributionAccepted,
              'sepa' => _sepaAccepted,
              _ => false,
            };
      }
    }

    final payment = _effectivePaymentMethod;
    final interval = _billingInterval;
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
          (_membershipType == 'general' && slug == 'standard') ||
          type['id']?.toString() == _membershipType;
    });
    final type = matching.isNotEmpty ? matching.first : types.first;
    final id = type['id'];
    return id is int ? id : int.tryParse('$id');
  }

  void _prefillFromUser(AirmiusUser user) {
    final nameParts = _splitName(user);
    _fillIfEmpty(_firstName, user.firstName ?? nameParts.$1);
    _fillIfEmpty(_lastName, user.lastName ?? nameParts.$2);
    _fillIfEmpty(_nationality, user.country);
    if (_gender.isEmpty && _membershipGenderOptions.contains(user.gender)) {
      _gender = user.gender!;
    }
    _fillIfEmpty(_birthday, formatAirmiusDate(user.birthDate));
    _fillIfEmpty(_email, user.email);
    _fillIfEmpty(_country, user.country ?? 'DE');
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

  DateTime? _parseBirthDate(String value) => parseAirmiusDate(value);

  Future<void> _openMembershipDocument(
    JsonMap document, {
    required bool preview,
  }) async {
    var rawUrl = document['url']?.toString().trim() ?? '';
    if (preview && document['file_id'] != null) {
      rawUrl = rawUrl.replaceFirst(RegExp(r'/download/?$'), '/preview');
    }
    final uri = safeExternalHttpUrl(rawUrl, httpsOnly: false);
    if (uri == null) {
      _toast('Dokument-Link ist nicht verfügbar.');
      return;
    }
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && mounted) {
      _toast('Dokument konnte nicht geöffnet werden.');
    }
  }

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _ApplicationHero extends StatelessWidget {
  const _ApplicationHero({this.clubName});

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

class _ApplicationTabs extends StatelessWidget {
  const _ApplicationTabs({
    required this.title,
    required this.labels,
    required this.activeIndex,
    required this.errorIndexes,
    required this.onChanged,
  });

  final String title;
  final List<String> labels;
  final int activeIndex;
  final Set<int> errorIndexes;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (var index = 0; index < labels.length; index++) ...[
              if (index > 0) const SizedBox(width: 8),
              ChoiceChip(
                label: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(labels[index]),
                    if (errorIndexes.contains(index)) ...[
                      const SizedBox(width: 6),
                      Container(
                        width: 17,
                        height: 17,
                        alignment: Alignment.center,
                        decoration: const BoxDecoration(
                          color: AirmiusColors.red,
                          shape: BoxShape.circle,
                        ),
                        child: const Text(
                          '!',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
                selected: activeIndex == index,
                onSelected: (_) => onChanged(index),
                selectedColor: airmiusAccentColor(
                  context,
                ).withValues(alpha: .24),
                backgroundColor: airmiusSurfaceColor(context),
                labelStyle: TextStyle(
                  color: activeIndex == index
                      ? airmiusTextColor(context)
                      : airmiusMutedColor(context),
                  fontWeight: FontWeight.w900,
                ),
                side: BorderSide(
                  color: errorIndexes.contains(index)
                      ? AirmiusColors.red
                      : activeIndex == index
                      ? airmiusAccentColor(context)
                      : airmiusBorderColor(context),
                ),
              ),
            ],
          ],
        ),
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
    this.documents = const [],
    this.acceptedDocuments = const {},
    this.errorDocumentIds = const {},
    this.privacyError = false,
    this.rulesError = false,
    this.contributionError = false,
    this.onDocument,
    this.onOpen,
  });

  final bool privacyAccepted;
  final bool rulesAccepted;
  final bool contributionAccepted;
  final bool sepaAccepted;
  final ValueChanged<bool> onPrivacy;
  final ValueChanged<bool> onRules;
  final ValueChanged<bool> onContribution;
  final ValueChanged<bool> onSepa;
  final List<JsonMap> documents;
  final Map<String, bool> acceptedDocuments;
  final Set<String> errorDocumentIds;
  final bool privacyError;
  final bool rulesError;
  final bool contributionError;
  final void Function(String id, bool value)? onDocument;
  final Future<void> Function(JsonMap document, bool preview)? onOpen;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      title: t('application.documentsRules'),
      child: Column(
        children: [
          if (documents.isEmpty) ...[
            _CheckRow(
              title: '${t('application.acceptPrivacy')} *',
              value: privacyAccepted,
              hasError: privacyError,
              onChanged: onPrivacy,
            ),
            _CheckRow(
              title: '${t('application.acceptRules')} *',
              value: rulesAccepted,
              hasError: rulesError,
              onChanged: onRules,
            ),
            _CheckRow(
              title: '${t('membership.document.fees')} *',
              value: contributionAccepted,
              hasError: contributionError,
              onChanged: onContribution,
            ),
            _CheckRow(
              title: t('membership.document.sepa'),
              value: sepaAccepted,
              onChanged: onSepa,
            ),
          ] else
            for (final document in documents) ...[
              _MembershipDocumentRow(
                document: document,
                value: acceptedDocuments[document['id']?.toString()] ?? false,
                hasError: errorDocumentIds.contains(document['id']?.toString()),
                onOpen: onOpen,
                onChanged: onDocument == null || document['id'] == null
                    ? null
                    : (value) => onDocument!(document['id'].toString(), value),
              ),
              if (document['description']?.toString().trim().isNotEmpty == true)
                Align(
                  alignment: Alignment.centerLeft,
                  child: Padding(
                    padding: const EdgeInsets.only(left: 12, bottom: 8),
                    child: Text(
                      document['description'].toString(),
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                ),
            ],
        ],
      ),
    );
  }
}

class _MembershipDocumentRow extends StatelessWidget {
  const _MembershipDocumentRow({
    required this.document,
    required this.value,
    required this.onChanged,
    this.hasError = false,
    this.onOpen,
  });

  final JsonMap document;
  final bool value;
  final ValueChanged<bool>? onChanged;
  final bool hasError;
  final Future<void> Function(JsonMap document, bool preview)? onOpen;

  @override
  Widget build(BuildContext context) {
    final title = document['title']?.toString().trim();
    final type = document['type']?.toString().trim();
    final requiredMark = document['is_required'] == true ? ' *' : '';
    final displayTitle = title == null || title.isEmpty
        ? (type == null || type.isEmpty ? 'Dokument' : type)
        : title;
    return Column(
      children: [
        _CheckRow(
          title: '$displayTitle$requiredMark',
          value: value,
          hasError: hasError,
          onChanged: onChanged ?? (_) {},
        ),
        if (onOpen != null &&
            document['url']?.toString().trim().isNotEmpty == true)
          Padding(
            padding: const EdgeInsets.only(left: 12, right: 12, bottom: 8),
            child: Row(
              children: [
                OutlinedButton.icon(
                  onPressed: () => onOpen!(document, true),
                  icon: const Icon(Icons.visibility_outlined, size: 17),
                  label: Text(
                    AirmiusScope.of(context).t('application.readDocument'),
                  ),
                ),
                const SizedBox(width: 8),
                OutlinedButton.icon(
                  onPressed: () => onOpen!(document, false),
                  icon: const Icon(Icons.download_outlined, size: 17),
                  label: Text(
                    AirmiusScope.of(context).t('application.downloadDocument'),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _CheckRow extends StatelessWidget {
  const _CheckRow({
    required this.title,
    required this.value,
    required this.onChanged,
    this.hasError = false,
  });

  final String title;
  final bool value;
  final ValueChanged<bool> onChanged;
  final bool hasError;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: hasError
              ? AirmiusColors.red
              : value
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
