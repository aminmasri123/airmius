import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_date_input.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class EditFormScreen extends StatefulWidget {
  const EditFormScreen({
    super.key,
    required this.title,
    required this.subtitle,
    this.mode = EditFormMode.basic,
  });

  final String title;
  final String subtitle;
  final EditFormMode mode;

  @override
  State<EditFormScreen> createState() => _EditFormScreenState();
}

enum EditFormMode { basic, profile, privacy, payment, admin, file, chat, event }

class _EditFormScreenState extends State<EditFormScreen> {
  bool _enabled = true;
  bool _publicVisible = true;
  String _status = 'Aktiv';
  String _role = 'Mitglied';
  String _gender = '';
  bool _profileInitialized = false;
  bool _saving = false;
  String? _error;
  late final TextEditingController _firstNameController;
  late final TextEditingController _lastNameController;
  late final TextEditingController _bioController;
  late final TextEditingController _birthDateController;
  late final TextEditingController _phoneController;
  late final TextEditingController _countryController;
  late final TextEditingController _streetController;
  late final TextEditingController _houseNumberController;
  late final TextEditingController _postalCodeController;
  late final TextEditingController _cityController;
  late final TextEditingController _stateController;

  @override
  void initState() {
    super.initState();
    _firstNameController = TextEditingController();
    _lastNameController = TextEditingController();
    _bioController = TextEditingController();
    _birthDateController = TextEditingController();
    _phoneController = TextEditingController();
    _countryController = TextEditingController();
    _streetController = TextEditingController();
    _houseNumberController = TextEditingController();
    _postalCodeController = TextEditingController();
    _cityController = TextEditingController();
    _stateController = TextEditingController();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();

    if (widget.mode != EditFormMode.profile || _profileInitialized) {
      return;
    }

    final user = AirmiusServicesScope.of(context).authState.user;
    _firstNameController.text = user?.firstName?.trim().isNotEmpty == true
        ? user!.firstName!.trim()
        : '';
    _lastNameController.text = user?.lastName?.trim().isNotEmpty == true
        ? user!.lastName!.trim()
        : '';
    _bioController.text = user?.bio?.trim() ?? '';
    _birthDateController.text = formatAirmiusDate(user?.birthDate);
    _phoneController.text = user?.phone?.trim() ?? '';
    _countryController.text = (user?.country ?? 'DE').trim().toUpperCase();
    _streetController.text = user?.street?.trim() ?? '';
    _houseNumberController.text = user?.houseNumber?.trim() ?? '';
    _postalCodeController.text = user?.postalCode?.trim() ?? '';
    _cityController.text = user?.city?.trim() ?? '';
    _stateController.text = user?.state?.trim() ?? '';

    final gender = user?.gender?.trim() ?? '';
    if (_gender.isEmpty && _genderOptions.contains(gender)) {
      _gender = gender;
    }

    _profileInitialized = true;
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _bioController.dispose();
    _birthDateController.dispose();
    _phoneController.dispose();
    _countryController.dispose();
    _streetController.dispose();
    _houseNumberController.dispose();
    _postalCodeController.dispose();
    _cityController.dispose();
    _stateController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          widget.title,
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.subtitle,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('profile.edit.eyebrow')),
                  const SizedBox(height: 8),
                  Text(
                    widget.title,
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    widget.subtitle,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            ..._fieldsForMode(t),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(
                _error!,
                style: TextStyle(
                  color: airmiusSemanticColor(context, AirmiusColors.red),
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
            const SizedBox(height: 16),
            AirmiusButton(
              label: _saving
                  ? t('profile.edit.saving')
                  : t('profile.edit.save'),
              icon: Icons.save_outlined,
              onPressed: _saving ? null : _save,
            ),
            const SizedBox(height: 10),
            AirmiusButton(
              label: t('profile.edit.cancel'),
              icon: Icons.close_outlined,
              secondary: true,
              onPressed: _saving ? null : () => Navigator.pop(context),
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _fieldsForMode(String Function(String) t) {
    return switch (widget.mode) {
      EditFormMode.profile => _profileFields(t),
      EditFormMode.privacy => _privacyFields(),
      EditFormMode.payment => _paymentFields(),
      EditFormMode.admin => _adminFields(),
      EditFormMode.file => _fileFields(),
      EditFormMode.chat => _chatFields(),
      EditFormMode.event => _eventFields(),
      EditFormMode.basic => _basicFields(),
    };
  }

  List<Widget> _basicFields() {
    return const [
      AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusTextField(label: 'Titel', hint: 'Name oder Bezeichnung'),
            SizedBox(height: 12),
            AirmiusTextField(
              label: 'Beschreibung',
              hint: 'Kurzbeschreibung',
              maxLines: 4,
            ),
          ],
        ),
      ),
    ];
  }

  List<Widget> _profileFields(String Function(String) t) {
    return [
      AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Eyebrow(t('profile.edit.personal')),
            const SizedBox(height: 12),
            AirmiusTextField(
              label: t('profileGate.firstName'),
              hint: t('profileGate.firstName'),
              controller: _firstNameController,
            ),
            const SizedBox(height: 12),
            AirmiusTextField(
              label: t('profileGate.lastName'),
              hint: t('profileGate.lastName'),
              controller: _lastNameController,
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _gender.isEmpty ? null : _gender,
              isExpanded: true,
              dropdownColor: airmiusSurfaceSoftColor(context),
              decoration: InputDecoration(
                labelText: t('profileGate.gender'),
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
                  child: Text(t('profileGate.gender.female')),
                ),
                DropdownMenuItem(
                  value: 'male',
                  child: Text(t('profileGate.gender.male')),
                ),
                DropdownMenuItem(
                  value: 'diverse',
                  child: Text(t('profileGate.gender.diverse')),
                ),
                DropdownMenuItem(
                  value: 'not_specified',
                  child: Text(t('profileGate.gender.notSpecified')),
                ),
              ],
              onChanged: (value) => setState(() => _gender = value ?? ''),
            ),
            const SizedBox(height: 12),
            AirmiusTextField(
              label: t('profile.edit.bio'),
              hint: t('profile.edit.bioHint'),
              controller: _bioController,
              maxLines: 3,
            ),
            const SizedBox(height: 18),
            Eyebrow(t('profile.edit.contactAddress')),
            const SizedBox(height: 12),
            AirmiusTextField(
              label: t('profile.edit.phone'),
              hint: t('profile.edit.phoneHint'),
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              icon: Icons.phone_outlined,
            ),
            const SizedBox(height: 12),
            AirmiusTextField(
              label: t('profile.edit.birthDate'),
              hint: t('profileGate.birthDateHint'),
              controller: _birthDateController,
              keyboardType: TextInputType.datetime,
              inputFormatters: const [AirmiusDateInputFormatter()],
              icon: Icons.cake_outlined,
            ),
            const SizedBox(height: 12),
            AirmiusTextField(
              label: t('profile.edit.country'),
              hint: t('profile.edit.countryHint'),
              controller: _countryController,
              icon: Icons.public_outlined,
            ),
            const SizedBox(height: 12),
            AirmiusTextField(
              label: t('profile.edit.street'),
              hint: t('profile.edit.streetHint'),
              controller: _streetController,
              icon: Icons.home_outlined,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  flex: 2,
                  child: AirmiusTextField(
                    label: t('profile.edit.houseNumber'),
                    controller: _houseNumberController,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  flex: 3,
                  child: AirmiusTextField(
                    label: t('profile.edit.postalCode'),
                    controller: _postalCodeController,
                    keyboardType: TextInputType.number,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            AirmiusTextField(
              label: t('profile.edit.city'),
              hint: t('profile.edit.cityHint'),
              controller: _cityController,
              icon: Icons.location_city_outlined,
            ),
            const SizedBox(height: 12),
            AirmiusTextField(
              label: t('profile.edit.state'),
              controller: _stateController,
            ),
          ],
        ),
      ),
    ];
  }

  List<Widget> _privacyFields() {
    return [
      AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Eyebrow('Sichtbarkeit'),
            SwitchListTile(
              value: _publicVisible,
              onChanged: (value) => setState(() => _publicVisible = value),
              title: const Text(
                'Profil sichtbar',
                style: TextStyle(
                  color: AirmiusColors.text,
                  fontWeight: FontWeight.w900,
                ),
              ),
              subtitle: const Text(
                'Andere Nutzer können dein Profil finden.',
                style: TextStyle(color: AirmiusColors.muted),
              ),
              activeThumbColor: AirmiusColors.blue,
              contentPadding: EdgeInsets.zero,
            ),
            SwitchListTile(
              value: _enabled,
              onChanged: (value) => setState(() => _enabled = value),
              title: const Text(
                'Benachrichtigungen erlauben',
                style: TextStyle(
                  color: AirmiusColors.text,
                  fontWeight: FontWeight.w900,
                ),
              ),
              subtitle: const Text(
                'Push, E-Mail und Vereinsupdates.',
                style: TextStyle(color: AirmiusColors.muted),
              ),
              activeThumbColor: AirmiusColors.blue,
              contentPadding: EdgeInsets.zero,
            ),
          ],
        ),
      ),
      const SizedBox(height: 12),
      const AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Eyebrow('Datenrechte'),
            SizedBox(height: 10),
            AirmiusTextField(
              label: 'Export-Kommentar',
              hint: 'Optionaler Hinweis für Datenexport oder Löschanfrage',
              maxLines: 3,
            ),
          ],
        ),
      ),
    ];
  }

  List<Widget> _paymentFields() {
    return const [
      AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Eyebrow('Zahlung'),
            SizedBox(height: 12),
            AirmiusTextField(
              label: 'Zahlmethode',
              hint: 'Überweisung, Bar, SEPA',
            ),
            SizedBox(height: 12),
            AirmiusTextField(label: 'IBAN', hint: 'DE...'),
            SizedBox(height: 12),
            AirmiusTextField(
              label: 'Rechnungsadresse',
              hint: 'Adresse für Rechnungen',
              maxLines: 3,
            ),
          ],
        ),
      ),
    ];
  }

  List<Widget> _adminFields() {
    return [
      AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Eyebrow('Admin-Aktion'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _status,
              dropdownColor: AirmiusColors.cardSoft,
              decoration: const InputDecoration(labelText: 'Status'),
              items: const ['Aktiv', 'In Prüfung', 'Gesperrt', 'Abgelehnt']
                  .map(
                    (item) => DropdownMenuItem(value: item, child: Text(item)),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _status = value ?? _status),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _role,
              dropdownColor: AirmiusColors.cardSoft,
              decoration: const InputDecoration(labelText: 'Rolle'),
              items: const ['Mitglied', 'Trainer', 'Club Admin', 'System Admin']
                  .map(
                    (item) => DropdownMenuItem(value: item, child: Text(item)),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _role = value ?? _role),
            ),
            const SizedBox(height: 12),
            const AirmiusTextField(
              label: 'Interne Notiz',
              hint: 'Warum wird der Status geändert?',
              maxLines: 3,
            ),
          ],
        ),
      ),
    ];
  }

  List<Widget> _fileFields() {
    return const [
      AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Eyebrow('Datei'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Dateiname', hint: 'Datenschutz.pdf'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Kategorie', hint: 'Vereinsdokumente'),
            SizedBox(height: 12),
            AirmiusTextField(
              label: 'Verknuepfung',
              hint: 'Mitgliedsantrag, Beitragsregel, Team',
            ),
          ],
        ),
      ),
    ];
  }

  List<Widget> _chatFields() {
    return const [
      AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Eyebrow('Nachricht'),
            SizedBox(height: 12),
            AirmiusTextField(
              label: 'Antwort',
              hint: 'Nachricht schreiben...',
              maxLines: 4,
            ),
          ],
        ),
      ),
    ];
  }

  List<Widget> _eventFields() {
    return const [
      AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Eyebrow('Termin'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Titel', hint: 'Intervalltraining'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Ort', hint: 'Sportplatz'),
            SizedBox(height: 12),
            AirmiusTextField(
              label: 'Notiz',
              hint: 'Teilnehmerinfo, Material, Absagegrund...',
              maxLines: 3,
            ),
          ],
        ),
      ),
    ];
  }

  Future<void> _save() async {
    if (widget.mode != EditFormMode.profile) {
      Navigator.pop(context);
      return;
    }

    final services = AirmiusServicesScope.of(context);
    final t = AirmiusScope.of(context).t;
    final authState = services.authState;
    final user = authState.user;
    final firstName = _firstNameController.text.trim();
    final lastName = _lastNameController.text.trim();
    final country = _countryController.text.trim().toUpperCase();
    final birthDate = parseAirmiusDate(_birthDateController.text);

    if (firstName.isEmpty || lastName.isEmpty || _gender.isEmpty) {
      setState(() => _error = t('profile.edit.validation'));
      return;
    }

    if (birthDate == null || country.length != 2) {
      setState(() => _error = t('profile.edit.birthCountryMissing'));
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    await authState.completeProfile(
      preserveAuthenticatedPhase: true,
      payload: {
        'first_name': firstName,
        'last_name': lastName,
        'birth_date': formatAirmiusApiDate(birthDate),
        'gender': _gender,
        'country': country,
        'bio': _bioController.text.trim(),
        'phone': _phoneController.text.trim(),
        'street': _streetController.text.trim(),
        'house_number': _houseNumberController.text.trim(),
        'postal_code': _postalCodeController.text.trim(),
        'city': _cityController.text.trim(),
        'state': _stateController.text.trim(),
        'guardian_email': user?.guardianEmail ?? '',
      },
    );

    if (!mounted) return;

    final error = authState.error;
    if (error != null && error.isNotEmpty) {
      setState(() {
        _saving = false;
        _error = error;
      });
      return;
    }

    Navigator.pop(context);
  }
}

const _genderOptions = ['female', 'male', 'diverse', 'not_specified'];
