import 'package:flutter/material.dart';

import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_theme_mode_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';
import 'account_operations_screen.dart';

class AuthFlowsScreen extends StatefulWidget {
  const AuthFlowsScreen({super.key, this.onSocialLogin});

  final void Function(String provider)? onSocialLogin;

  @override
  State<AuthFlowsScreen> createState() => _AuthFlowsScreenState();
}

class _AuthFlowsScreenState extends State<AuthFlowsScreen> {
  String _flow = 'Registrieren';
  bool _terms = false;
  String _gender = '';
  String? _registerError;
  final _firstNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _emailController = TextEditingController();
  final _countryController = TextEditingController(text: 'DE');
  final _streetController = TextEditingController();
  final _houseNumberController = TextEditingController();
  final _postalCodeController = TextEditingController();
  final _cityController = TextEditingController();
  final _stateController = TextEditingController();
  final _birthDateController = TextEditingController();
  final _guardianEmailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _passwordConfirmationController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _birthDateController.addListener(_onBirthDateChanged);
  }

  @override
  void dispose() {
    _birthDateController.removeListener(_onBirthDateChanged);
    _firstNameController.dispose();
    _lastNameController.dispose();
    _emailController.dispose();
    _countryController.dispose();
    _streetController.dispose();
    _houseNumberController.dispose();
    _postalCodeController.dispose();
    _cityController.dispose();
    _stateController.dispose();
    _birthDateController.dispose();
    _guardianEmailController.dispose();
    _passwordController.dispose();
    _passwordConfirmationController.dispose();
    super.dispose();
  }

  void _onBirthDateChanged() => setState(() {});

  bool get _requiresGuardianConsent {
    final birthDate = DateTime.tryParse(_birthDateController.text.trim());
    if (birthDate == null) return false;

    final today = DateTime.now();
    var age = today.year - birthDate.year;
    if (today.month < birthDate.month || (today.month == birthDate.month && today.day < birthDate.day)) {
      age -= 1;
    }

    return age < 16;
  }

  Future<void> _pickBirthDate() async {
    final now = DateTime.now();
    final initialDate = DateTime.tryParse(_birthDateController.text.trim()) ?? DateTime(now.year - 16, now.month, now.day);
    final picked = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: DateTime(1900),
      lastDate: now,
    );

    if (picked == null) return;
    _birthDateController.text = _formatDate(picked);
  }

  Future<void> _submitRegister() async {
    final services = AirmiusServicesScope.of(context);
    final language = Localizations.localeOf(context).languageCode;
    setState(() => _registerError = null);

    if (_passwordController.text != _passwordConfirmationController.text) {
      setState(() => _registerError = 'Passwort und Bestätigung stimmen nicht überein.');
      return;
    }

    if (_gender.isEmpty) {
      setState(() => _registerError = 'Bitte wähle dein Geschlecht aus.');
      return;
    }

    await services.authState.register(
      locale: language,
      payload: {
        'first_name': _firstNameController.text.trim(),
        'last_name': _lastNameController.text.trim(),
        'email': _emailController.text.trim(),
        'country': _countryController.text.trim().toUpperCase(),
        'street': _emptyToNull(_streetController.text),
        'house_number': _emptyToNull(_houseNumberController.text),
        'postal_code': _emptyToNull(_postalCodeController.text),
        'city': _emptyToNull(_cityController.text),
        'state': _emptyToNull(_stateController.text),
        'birth_date': _birthDateController.text.trim(),
        'gender': _gender,
        'guardian_email': _requiresGuardianConsent ? _guardianEmailController.text.trim() : null,
        'password': _passwordController.text,
        'password_confirmation': _passwordConfirmationController.text,
        'terms': _terms,
        'device_name': 'airmius-mobile',
      },
    );

    if (!mounted) return;
    final error = services.authState.error;
    if (error != null && error.isNotEmpty) {
      setState(() => _registerError = error);
      return;
    }

    if (services.authState.isAuthenticated) {
      Navigator.of(context).popUntil((route) => route.isFirst);
    }
  }

  @override
  Widget build(BuildContext context) {
    final accent = _authAccent(context);
    final surfaceSoft = _authSurfaceSoft(context);
    final text = _authText(context);
    final muted = _authMuted(context);
    final border = _authBorder(context);
    return Scaffold(
      backgroundColor: _authBackground(context),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: accent,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.manage_accounts_outlined),
        label: const Text('Konto Ops', style: TextStyle(fontWeight: FontWeight.w900)),
        onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => AccountOperationsScreen(initialTab: 'Auth'))),
      ),
      appBar: AppBar(
        backgroundColor: _authHeader(context),
        foregroundColor: text,
        surfaceTintColor: Colors.transparent,
        title: const Text('Konto & Sicherheit', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Konto & Sicherheit',
        subtitle: 'Registrierung, Passwort, 2FA, E-Mail und Profilabschluss',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Center(child: AirmiusLogo(size: 72)),
                  const SizedBox(height: 12),
                  const Eyebrow('Auth'),
                  const SizedBox(height: 8),
                  Text('Alle wichtigen Auth-Seiten der Web-App als native UI vorbereitet.', style: TextStyle(color: muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final flow in const ['Registrieren', 'Social', 'Passwort', '2FA', 'E-Mail', 'Profil', 'Gesperrt', 'Löschen'])
                        ChoiceChip(
                          selected: _flow == flow,
                          label: Text(flow),
                          onSelected: (_) => setState(() => _flow = flow),
                          selectedColor: accent.withValues(alpha: _authDarkUi(context) ? 0.22 : 0.14),
                          backgroundColor: surfaceSoft,
                          checkmarkColor: accent,
                          side: BorderSide(color: _flow == flow ? accent : border),
                          labelStyle: TextStyle(color: _flow == flow ? accent : muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _content(),
          ],
        ),
      ),
    );
  }

  Widget _content() {
    return switch (_flow) {
      'Registrieren' => _register(),
      'Social' => _socialLogin(),
      'Passwort' => _password(),
      '2FA' => _twoFactor(),
      'E-Mail' => _emailVerify(),
      'Profil' => _profileCompletion(),
      'Gesperrt' => _suspended(),
      'Löschen' => _deleteAccount(),
      _ => _register(),
    };
  }

  Widget _register() {
    final services = AirmiusServicesScope.of(context);
    final isLoading = services.authState.phase.name == 'loading';
    final error = _registerError ?? services.authState.error;
    final text = _authText(context);
    final muted = _authMuted(context);

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Registrieren'),
          const SizedBox(height: 12),
          AirmiusTextField(label: 'Vorname', hint: 'Max', icon: Icons.person_outline, controller: _firstNameController),
          const SizedBox(height: 12),
          AirmiusTextField(label: 'Nachname', hint: 'Mustermann', icon: Icons.person_outline, controller: _lastNameController),
          const SizedBox(height: 12),
          AirmiusTextField(label: 'E-Mail', hint: 'konto@example.com', icon: Icons.mail_outline, controller: _emailController, keyboardType: TextInputType.emailAddress),
          const SizedBox(height: 12),
          AirmiusTextField(label: 'Land', hint: 'DE', icon: Icons.public_outlined, controller: _countryController),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: AirmiusTextField(label: 'Straße', hint: 'Optional', icon: Icons.home_outlined, controller: _streetController)),
              const SizedBox(width: 10),
              SizedBox(width: 110, child: AirmiusTextField(label: 'Nr.', hint: '12a', controller: _houseNumberController)),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              SizedBox(width: 130, child: AirmiusTextField(label: 'PLZ', hint: '10115', controller: _postalCodeController, keyboardType: TextInputType.number)),
              const SizedBox(width: 10),
              Expanded(child: AirmiusTextField(label: 'Stadt', hint: 'Berlin', icon: Icons.location_city_outlined, controller: _cityController)),
            ],
          ),
          const SizedBox(height: 12),
          AirmiusTextField(label: 'Bundesland / Region', hint: 'Optional', icon: Icons.map_outlined, controller: _stateController),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: AirmiusTextField(label: 'Geburtsdatum', hint: 'JJJJ-MM-TT', icon: Icons.cake_outlined, controller: _birthDateController, keyboardType: TextInputType.datetime)),
              const SizedBox(width: 10),
              IconButton.filledTonal(
                tooltip: 'Datum wählen',
                onPressed: _pickBirthDate,
                icon: const Icon(Icons.calendar_month_outlined),
              ),
            ],
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _gender.isEmpty ? null : _gender,
            dropdownColor: _authSurface(context),
            decoration: InputDecoration(
              labelText: 'Geschlecht',
              prefixIcon: Icon(Icons.wc_outlined, color: muted),
            ),
            style: TextStyle(color: text, fontWeight: FontWeight.w800),
            items: const [
              DropdownMenuItem(value: 'female', child: Text('Weiblich')),
              DropdownMenuItem(value: 'male', child: Text('Männlich')),
              DropdownMenuItem(value: 'diverse', child: Text('Divers')),
              DropdownMenuItem(value: 'not_specified', child: Text('Keine Angabe')),
            ],
            onChanged: (value) => setState(() => _gender = value ?? ''),
          ),
          if (_requiresGuardianConsent) ...[
            const SizedBox(height: 12),
            const Text('Bei Nutzern unter 16 Jahren ist die E-Mail eines Erziehungsberechtigten erforderlich.', style: TextStyle(color: AirmiusColors.amber, fontWeight: FontWeight.w800, height: 1.35)),
            const SizedBox(height: 12),
            AirmiusTextField(label: 'E-Mail Erziehungsberechtigte/r', hint: 'eltern@example.com', icon: Icons.supervisor_account_outlined, controller: _guardianEmailController, keyboardType: TextInputType.emailAddress),
          ],
          const SizedBox(height: 12),
          AirmiusTextField(label: 'Passwort', hint: 'Sicheres Passwort', icon: Icons.lock_outline, controller: _passwordController, obscureText: true),
          const SizedBox(height: 12),
          AirmiusTextField(label: 'Passwort bestätigen', hint: 'Passwort wiederholen', icon: Icons.lock_reset_outlined, controller: _passwordConfirmationController, obscureText: true),
          const SizedBox(height: 12),
          CheckboxListTile(
            value: _terms,
            onChanged: (value) => setState(() => _terms = value ?? false),
            activeColor: _authAccent(context),
            contentPadding: EdgeInsets.zero,
            title: Text('AGB und Datenschutz akzeptieren', style: TextStyle(color: text, fontWeight: FontWeight.w800)),
          ),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _AuthAction(label: 'Mit Google registrieren', icon: Icons.g_mobiledata, onPressed: widget.onSocialLogin == null ? null : () => widget.onSocialLogin!('google')),
              _AuthAction(label: 'Mit Outlook registrieren', icon: Icons.mail_outline, onPressed: widget.onSocialLogin == null ? null : () => widget.onSocialLogin!('microsoft')),
            ],
          ),
          const SizedBox(height: 12),
          if (error != null && error.isNotEmpty) ...[
            Text(error, style: const TextStyle(color: AirmiusColors.red, fontWeight: FontWeight.w800)),
            const SizedBox(height: 10),
          ],
          AirmiusButton(label: isLoading ? 'Konto wird erstellt...' : 'Konto erstellen', icon: Icons.person_add_alt, onPressed: _terms && !isLoading ? _submitRegister : null),
        ],
      ),
    );
  }

  Widget _socialLogin() {
    final muted = _authMuted(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Social Login'),
          const SizedBox(height: 8),
          Text('Die Web-App besitzt OAuth-Redirects. In der Mobile-App wird daraus ein nativer Provider-Flow mit Account-Linking, Datenschutz und Fehlerstatus.', style: TextStyle(color: muted, height: 1.35)),
          const SizedBox(height: 12),
          const _AuthStatusLine(icon: Icons.account_circle_outlined, title: 'Google', body: 'OAuth, E-Mail-Abgleich und Profilanlage.', status: 'Provider'),
          const _AuthStatusLine(icon: Icons.phone_iphone_outlined, title: 'Apple', body: 'Sign in with Apple, Private Relay und Account-Linking.', status: 'iOS'),
          const _AuthStatusLine(icon: Icons.link_outlined, title: 'Konto verknuepfen', body: 'Bestehende Airmius-Konten mit Provider verbinden.', status: 'Linking'),
          const SizedBox(height: 12),
          const _AuthAction(label: 'Google Login starten', icon: Icons.account_circle_outlined),
          const SizedBox(height: 10),
          const _AuthAction(label: 'Apple Login starten', icon: Icons.phone_iphone_outlined),
        ],
      ),
    );
  }

  Widget _password() {
    return const AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow('Passwort vergessen'),
          SizedBox(height: 12),
          AirmiusTextField(label: 'E-Mail', hint: 'konto@example.com', icon: Icons.mail_outline),
          SizedBox(height: 12),
          _AuthAction(label: 'Reset-Link senden', icon: Icons.mark_email_read_outlined),
          SizedBox(height: 16),
          Eyebrow('Passwort zurücksetzen'),
          SizedBox(height: 12),
          AirmiusTextField(label: 'Code / Token', hint: 'Aus der E-Mail'),
          SizedBox(height: 12),
          AirmiusTextField(label: 'Neues Passwort', hint: 'Neues Passwort', icon: Icons.lock_outline),
        ],
      ),
    );
  }

  Widget _twoFactor() {
    final muted = _authMuted(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Zwei-Faktor-Authentifizierung'),
          const SizedBox(height: 8),
          Text('Code aus Authenticator-App oder Recovery-Code eingeben.', style: TextStyle(color: muted, height: 1.35)),
          const SizedBox(height: 12),
          const AirmiusTextField(label: '2FA Code', hint: '123456', icon: Icons.password_outlined),
          const SizedBox(height: 12),
          const AirmiusTextField(label: 'Recovery Code', hint: 'Optional'),
          const SizedBox(height: 12),
          const _AuthAction(label: 'Verifizieren', icon: Icons.verified_user_outlined),
        ],
      ),
    );
  }

  Widget _emailVerify() {
    final muted = _authMuted(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('E-Mail verifizieren'),
          const SizedBox(height: 8),
          Text('Bitte bestätige deine E-Mail-Adresse. Bei Bedarf kann eine neue Mail versendet werden.', style: TextStyle(color: muted, height: 1.35)),
          const SizedBox(height: 12),
          const _AuthStatusLine(icon: Icons.mail_outline, title: 'zbb.bop.it@gmail.com', body: 'Wartet auf Bestätigung', status: 'Offen'),
          const SizedBox(height: 12),
          const _AuthAction(label: 'Verifizierungslink erneut senden', icon: Icons.send_outlined),
        ],
      ),
    );
  }

  Widget _profileCompletion() {
    return const AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow('Profil vervollstaendigen'),
          SizedBox(height: 12),
          _AuthStatusLine(icon: Icons.person_outline, title: 'Personendaten', body: 'Name, Geburtsdatum und Profilbild', status: '80%'),
          _AuthStatusLine(icon: Icons.directions_run, title: 'Sportprofil', body: 'Sportarten, Level, Ziele und Skills', status: 'Offen'),
          _AuthStatusLine(icon: Icons.privacy_tip_outlined, title: 'Sichtbarkeit', body: 'Profil, Vereine und Kontakte', status: 'Prüfen'),
          SizedBox(height: 12),
          _AuthAction(label: 'Profil abschließen', icon: Icons.task_alt_outlined),
        ],
      ),
    );
  }

  Widget _suspended() {
    final muted = _authMuted(context);
    return AirmiusPanel(
      borderColor: AirmiusColors.red,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Konto eingeschraenkt'),
          const SizedBox(height: 8),
          Text('Der Zugriff kann durch Moderation, fehlende Verifizierung oder Sicherheitsregeln eingeschraenkt sein.', style: TextStyle(color: muted, height: 1.35)),
          const SizedBox(height: 12),
          const _AuthStatusLine(icon: Icons.report_outlined, title: 'Status', body: 'Support kann Details prüfen.', status: 'Gesperrt'),
          const SizedBox(height: 12),
          const _AuthAction(label: 'Support kontaktieren', icon: Icons.support_agent_outlined),
        ],
      ),
    );
  }

  Widget _deleteAccount() {
    final muted = _authMuted(context);
    return AirmiusPanel(
      borderColor: AirmiusColors.red,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Konto löschen'),
          const SizedBox(height: 8),
          Text('Die Web-App sendet zuerst einen Löschcode. Die native App zeigt Warnung, Code-Eingabe, Export-Hinweis und finale Bestätigung.', style: TextStyle(color: muted, height: 1.35)),
          const SizedBox(height: 12),
          const AirmiusTextField(label: 'Löschcode', hint: 'Code aus der E-Mail', icon: Icons.password_outlined),
          const SizedBox(height: 12),
          const _AuthStatusLine(icon: Icons.download_outlined, title: 'Datenexport', body: 'Profil, Mitgliedschaften, Zahlungen und Medien vor Löschung exportieren.', status: 'Empfohlen'),
          const _AuthStatusLine(icon: Icons.warning_amber_outlined, title: 'Endgültige Löschung', body: 'Konto wird erst nach API-Bestätigung final gelöscht.', status: 'Kritisch'),
          const SizedBox(height: 12),
          const _AuthAction(label: 'Löschcode senden', icon: Icons.mark_email_read_outlined),
          const SizedBox(height: 10),
          const _AuthAction(label: 'Konto endgültig löschen', icon: Icons.delete_forever_outlined),
        ],
      ),
    );
  }
}

AirmiusThemePalette _authPalette(BuildContext context) {
  try {
    return AirmiusThemeModeScope.of(context).palette;
  } on StateError {
    return Theme.of(context).brightness == Brightness.dark ? AirmiusThemePalette.dark : AirmiusThemePalette.air;
  }
}

bool _authDarkUi(BuildContext context) {
  try {
    final mode = AirmiusThemeModeScope.of(context).mode;
    return switch (mode) {
      ThemeMode.dark => true,
      ThemeMode.light => false,
      ThemeMode.system => Theme.of(context).brightness == Brightness.dark,
    };
  } on StateError {
    return Theme.of(context).brightness == Brightness.dark;
  }
}

Color _authAccent(BuildContext context) {
  return _authPalette(context).primary;
}

Color _authBackground(BuildContext context) {
  final palette = _authPalette(context);
  return _authDarkUi(context) ? palette.darkBackground : palette.lightBackground;
}

Color _authHeader(BuildContext context) {
  final palette = _authPalette(context);
  return _authDarkUi(context) ? palette.darkHeader : palette.lightSurface;
}

Color _authSurface(BuildContext context) {
  final palette = _authPalette(context);
  return _authDarkUi(context) ? palette.darkSurface : palette.lightSurface;
}

Color _authSurfaceSoft(BuildContext context) {
  final palette = _authPalette(context);
  return _authDarkUi(context) ? palette.darkSurfaceSoft : palette.lightSurfaceSoft;
}

Color _authText(BuildContext context) {
  final palette = _authPalette(context);
  return _authDarkUi(context) ? AirmiusColors.text : palette.lightText;
}

Color _authMuted(BuildContext context) {
  final palette = _authPalette(context);
  return _authDarkUi(context) ? AirmiusColors.muted : palette.lightMutedText;
}

Color _authBorder(BuildContext context) {
  final palette = _authPalette(context);
  return _authDarkUi(context) ? AirmiusColors.border : palette.lightBorder;
}

class _AuthAction extends StatelessWidget {
  const _AuthAction({required this.label, required this.icon, this.onPressed});

  final String label;
  final IconData icon;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return AirmiusButton(label: label, icon: icon, onPressed: onPressed ?? () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: label, body: 'Auth-Aktion für $label vorbereiten und später mit Laravel Auth/API verbinden.', status: 'Auth', icon: icon))));
  }
}

String _formatDate(DateTime date) {
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '${date.year}-$month-$day';
}

String? _emptyToNull(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}

class _AuthStatusLine extends StatelessWidget {
  const _AuthStatusLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    final accent = _authAccent(context);
    final textColor = _authText(context);
    final mutedColor = _authMuted(context);
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: accent),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: TextStyle(color: textColor, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: TextStyle(color: mutedColor, height: 1.3))])),
          StatusPill(status),
        ],
      ),
    );
  }
}
