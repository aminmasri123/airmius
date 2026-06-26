import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_auth_state.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ProfileCompletionGateScreen extends StatefulWidget {
  const ProfileCompletionGateScreen({super.key, required this.authState});

  final AirmiusAuthState authState;

  @override
  State<ProfileCompletionGateScreen> createState() => _ProfileCompletionGateScreenState();
}

class _ProfileCompletionGateScreenState extends State<ProfileCompletionGateScreen> {
  late final TextEditingController _firstNameController;
  late final TextEditingController _lastNameController;
  late final TextEditingController _birthDateController;
  late final TextEditingController _countryController;
  late final TextEditingController _guardianEmailController;
  String _gender = '';
  String? _localError;

  @override
  void initState() {
    super.initState();
    final user = widget.authState.user;
    _firstNameController = TextEditingController(text: user?.firstName ?? _splitName(user).$1 ?? '');
    _lastNameController = TextEditingController(text: user?.lastName ?? _splitName(user).$2 ?? '');
    _birthDateController = TextEditingController(text: _dateText(user?.birthDate));
    _countryController = TextEditingController(text: (user?.country ?? 'DE').toUpperCase());
    _guardianEmailController = TextEditingController(text: user?.guardianEmail ?? '');
    _gender = _genderValues.contains(user?.gender) ? user!.gender! : '';
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _birthDateController.dispose();
    _countryController.dispose();
    _guardianEmailController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final loading = widget.authState.phase == AirmiusAuthPhase.loading;
    final birthDate = DateTime.tryParse(_birthDateController.text.trim());
    final minor = birthDate != null && _isMinor(birthDate);
    final error = _localError ?? widget.authState.error;

    return Scaffold(
      backgroundColor: AirmiusColors.background,
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 560),
            child: ListView(
              padding: const EdgeInsets.all(20),
              shrinkWrap: true,
              children: [
                const SizedBox(height: 12),
                const Icon(Icons.shield_outlined, color: AirmiusColors.blue, size: 48),
                const SizedBox(height: 18),
                const Text(
                  'Profil vervollstaendigen',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AirmiusColors.text, fontSize: 28, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Diese Angaben sind wichtig fuer Jugendschutz, Elternfreigabe und faire Nutzung der Sportplattform.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AirmiusColors.muted, height: 1.4),
                ),
                const SizedBox(height: 22),
                AirmiusPanel(
                  child: Column(
                    children: [
                      AirmiusTextField(label: 'Vorname', icon: Icons.person_outline, controller: _firstNameController),
                      const SizedBox(height: 12),
                      AirmiusTextField(label: 'Nachname', icon: Icons.badge_outlined, controller: _lastNameController),
                      const SizedBox(height: 12),
                      AirmiusTextField(
                        label: 'Geburtsdatum',
                        hint: 'YYYY-MM-DD',
                        icon: Icons.cake_outlined,
                        controller: _birthDateController,
                        keyboardType: TextInputType.datetime,
                        suffixIcon: IconButton(
                          onPressed: loading ? null : _pickBirthDate,
                          icon: const Icon(Icons.calendar_month_outlined, color: AirmiusColors.blue),
                        ),
                        onChanged: (_) => setState(() {}),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        value: _gender.isEmpty ? null : _gender,
                        dropdownColor: AirmiusColors.cardSoft,
                        decoration: const InputDecoration(
                          labelText: 'Geschlecht',
                          prefixIcon: Icon(Icons.wc_outlined, color: AirmiusColors.muted),
                        ),
                        style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
                        items: const [
                          DropdownMenuItem(value: 'female', child: Text('Weiblich')),
                          DropdownMenuItem(value: 'male', child: Text('Maennlich')),
                          DropdownMenuItem(value: 'diverse', child: Text('Divers')),
                          DropdownMenuItem(value: 'not_specified', child: Text('Keine Angabe')),
                        ],
                        onChanged: loading ? null : (value) => setState(() => _gender = value ?? ''),
                      ),
                      const SizedBox(height: 12),
                      AirmiusTextField(label: 'Land', hint: 'DE', icon: Icons.public_outlined, controller: _countryController),
                      if (minor) ...[
                        const SizedBox(height: 12),
                        AirmiusTextField(
                          label: 'E-Mail Erziehungsberechtigte/r',
                          icon: Icons.family_restroom_outlined,
                          controller: _guardianEmailController,
                          keyboardType: TextInputType.emailAddress,
                        ),
                      ],
                      if (error != null && error.isNotEmpty) ...[
                        const SizedBox(height: 14),
                        Text(error, style: const TextStyle(color: AirmiusColors.red, fontWeight: FontWeight.w800)),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                AirmiusButton(
                  label: loading ? 'Speichere...' : 'Profil speichern',
                  icon: Icons.save_outlined,
                  onPressed: loading ? null : _save,
                ),
                const SizedBox(height: 10),
                AirmiusButton(
                  label: 'Abmelden',
                  icon: Icons.logout_outlined,
                  secondary: true,
                  onPressed: loading ? null : widget.authState.signOut,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _pickBirthDate() async {
    final initial = DateTime.tryParse(_birthDateController.text.trim()) ?? DateTime(DateTime.now().year - 18, 1, 1);
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(1900),
      lastDate: DateTime.now(),
    );
    if (picked == null) return;
    setState(() => _birthDateController.text = _dateText(picked));
  }

  Future<void> _save() async {
    final birthDate = DateTime.tryParse(_birthDateController.text.trim());
    final country = _countryController.text.trim().toUpperCase();
    if (_firstNameController.text.trim().isEmpty ||
        _lastNameController.text.trim().isEmpty ||
        birthDate == null ||
        _gender.isEmpty ||
        country.length != 2) {
      setState(() => _localError = 'Bitte Vorname, Nachname, Geburtsdatum, Geschlecht und Land ausfuellen.');
      return;
    }
    if (_isMinor(birthDate) && _guardianEmailController.text.trim().isEmpty) {
      setState(() => _localError = 'Bei Nutzern unter 16 Jahren brauchen wir die E-Mail eines Erziehungsberechtigten.');
      return;
    }

    setState(() => _localError = null);
    await widget.authState.completeProfile(
      payload: {
        'first_name': _firstNameController.text.trim(),
        'last_name': _lastNameController.text.trim(),
        'birth_date': _dateText(birthDate),
        'gender': _gender,
        'country': country,
        'guardian_email': _guardianEmailController.text.trim(),
      },
    );
  }
}

class GuardianConsentPendingScreen extends StatelessWidget {
  const GuardianConsentPendingScreen({super.key, required this.authState});

  final AirmiusAuthState authState;

  @override
  Widget build(BuildContext context) {
    final email = authState.user?.guardianEmail;
    return Scaffold(
      backgroundColor: AirmiusColors.background,
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: AirmiusPanel(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.mark_email_unread_outlined, color: AirmiusColors.blue, size: 52),
                    const SizedBox(height: 16),
                    const Text(
                      'Elternfreigabe ausstehend',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      email == null || email.isEmpty
                          ? 'Dein Konto wartet auf die Zustimmung eines Erziehungsberechtigten.'
                          : 'Wir haben die Freigabe an $email gesendet. Danach wird dein Konto automatisch freigeschaltet.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: AirmiusColors.muted, height: 1.4),
                    ),
                    const SizedBox(height: 20),
                    AirmiusButton(label: 'Status aktualisieren', icon: Icons.refresh_outlined, onPressed: authState.refreshUser),
                    const SizedBox(height: 10),
                    AirmiusButton(label: 'Abmelden', icon: Icons.logout_outlined, secondary: true, onPressed: authState.signOut),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

const _genderValues = ['female', 'male', 'diverse', 'not_specified'];

(String?, String?) _splitName(AirmiusUser? user) {
  final name = user?.name.trim() ?? '';
  if (name.isEmpty) return (null, null);
  final parts = name.split(RegExp(r'\s+'));
  return (parts.first, parts.length > 1 ? parts.sublist(1).join(' ') : null);
}

String _dateText(DateTime? date) {
  if (date == null) return '';
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '${date.year}-$month-$day';
}

bool _isMinor(DateTime birthDate) {
  final today = DateTime.now();
  var age = today.year - birthDate.year;
  if (today.month < birthDate.month || (today.month == birthDate.month && today.day < birthDate.day)) {
    age--;
  }
  return age < 16;
}
