import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_theme_mode_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'account_operations_screen.dart';
import 'email_verification_screen.dart';
import 'password_recovery_screen.dart';
import 'profile_completion_gate_screen.dart';
import 'support_helpdesk_screen.dart';
import 'two_factor_challenge_screen.dart';

class AuthFlowsScreen extends StatefulWidget {
  const AuthFlowsScreen({super.key, this.onSocialLogin, this.onSocialRegister});

  final void Function(String provider)? onSocialLogin;
  final void Function(String provider, String accountType)? onSocialRegister;

  @override
  State<AuthFlowsScreen> createState() => _AuthFlowsScreenState();
}

class _AuthFlowsScreenState extends State<AuthFlowsScreen> {
  final String _flow = 'register';
  String _accountType = 'athlete';
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
    if (today.month < birthDate.month ||
        (today.month == birthDate.month && today.day < birthDate.day)) {
      age -= 1;
    }

    return age < 16;
  }

  Future<void> _pickBirthDate() async {
    final now = DateTime.now();
    final initialDate =
        DateTime.tryParse(_birthDateController.text.trim()) ??
        DateTime(now.year - 16, now.month, now.day);
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
    final scope = AirmiusScope.of(context);
    final language = Localizations.localeOf(context).languageCode;
    setState(() => _registerError = null);

    if (_passwordController.text != _passwordConfirmationController.text) {
      setState(() => _registerError = scope.t('authFlow.passwordMismatch'));
      return;
    }

    if (_gender.isEmpty) {
      setState(() => _registerError = scope.t('authFlow.genderRequired'));
      return;
    }

    await services.authState.register(
      locale: language,
      payload: {
        'first_name': _firstNameController.text.trim(),
        'account_type': _accountType,
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
        'guardian_email': _requiresGuardianConsent
            ? _guardianEmailController.text.trim()
            : null,
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
    final scope = AirmiusScope.of(context);
    final text = _authText(context);
    final muted = _authMuted(context);
    return Scaffold(
      backgroundColor: _authBackground(context),
      appBar: AppBar(
        backgroundColor: _authHeader(context),
        foregroundColor: text,
        surfaceTintColor: Colors.transparent,
        title: Text(
          scope.t('authFlow.register'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: scope.t('authFlow.register'),
        subtitle: scope.t('authFlow.registerSubtitle'),
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
                  Eyebrow(scope.t('authFlow.register')),
                  const SizedBox(height: 8),
                  Text(
                    scope.t('authFlow.registerIntro'),
                    style: TextStyle(color: muted, height: 1.35),
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
      'register' => _register(),
      'social' => _socialLogin(),
      'password' => _password(),
      'twoFactor' => _twoFactor(),
      'email' => _emailVerify(),
      'profile' => _profileCompletion(),
      'suspended' => _suspended(),
      'delete' => _deleteAccount(),
      _ => _register(),
    };
  }

  Widget _register() {
    final services = AirmiusServicesScope.of(context);
    final scope = AirmiusScope.of(context);
    final isLoading = services.authState.phase.name == 'loading';
    final error = _registerError ?? services.authState.error;
    final text = _authText(context);
    final muted = _authMuted(context);
    final narrow = MediaQuery.sizeOf(context).width < 500;

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(scope.t('authFlow.register')),
          const SizedBox(height: 12),
          Text(
            scope.t('accountType.question'),
            style: TextStyle(color: text, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 4),
          Text(
            scope.t('accountType.hint'),
            style: TextStyle(color: muted, height: 1.35),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final type in const [
                ('athlete', Icons.directions_run_outlined),
                ('coach', Icons.sports_outlined),
                ('club', Icons.apartment_outlined),
                ('sponsor', Icons.handshake_outlined),
              ])
                ChoiceChip(
                  selected: _accountType == type.$1,
                  avatar: Icon(type.$2, size: 18),
                  label: Text(scope.t('accountType.${type.$1}')),
                  onSelected: (_) => setState(() => _accountType = type.$1),
                ),
            ],
          ),
          if (widget.onSocialRegister != null ||
              widget.onSocialLogin != null) ...[
            const SizedBox(height: 16),
            Text(
              scope.t('authFlow.quickRegister'),
              style: TextStyle(color: text, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                _AuthAction(
                  label: scope.t('authFlow.googleRegister'),
                  icon: Icons.g_mobiledata,
                  onPressed: () => _startSocialRegistration('google'),
                ),
                _AuthAction(
                  label: scope.t('authFlow.outlookRegister'),
                  icon: Icons.mail_outline,
                  onPressed: () => _startSocialRegistration('microsoft'),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                const Expanded(child: Divider()),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  child: Text(
                    scope.t('authFlow.orEmail'),
                    style: TextStyle(color: muted, fontWeight: FontWeight.w800),
                  ),
                ),
                const Expanded(child: Divider()),
              ],
            ),
          ],
          const SizedBox(height: 16),
          AirmiusTextField(
            label: scope.t('application.firstName'),
            hint: 'Alex',
            icon: Icons.person_outline,
            controller: _firstNameController,
          ),
          const SizedBox(height: 12),
          AirmiusTextField(
            label: scope.t('application.lastName'),
            hint: 'Smith',
            icon: Icons.person_outline,
            controller: _lastNameController,
          ),
          const SizedBox(height: 12),
          AirmiusTextField(
            label: scope.t('application.email'),
            hint: 'account@example.com',
            icon: Icons.mail_outline,
            controller: _emailController,
            keyboardType: TextInputType.emailAddress,
          ),
          const SizedBox(height: 12),
          AirmiusTextField(
            label: scope.t('profileGate.country'),
            hint: 'DE',
            icon: Icons.public_outlined,
            controller: _countryController,
          ),
          const SizedBox(height: 12),
          if (narrow) ...[
            AirmiusTextField(
              label: scope.t('authFlow.street'),
              hint: scope.t('application.optional'),
              icon: Icons.home_outlined,
              controller: _streetController,
            ),
            const SizedBox(height: 12),
            AirmiusTextField(
              label: scope.t('authFlow.number'),
              hint: '12a',
              controller: _houseNumberController,
            ),
          ] else
            Row(
              children: [
                Expanded(
                  child: AirmiusTextField(
                    label: scope.t('authFlow.street'),
                    hint: scope.t('application.optional'),
                    icon: Icons.home_outlined,
                    controller: _streetController,
                  ),
                ),
                const SizedBox(width: 10),
                SizedBox(
                  width: 110,
                  child: AirmiusTextField(
                    label: scope.t('authFlow.number'),
                    hint: '12a',
                    controller: _houseNumberController,
                  ),
                ),
              ],
            ),
          const SizedBox(height: 12),
          if (narrow) ...[
            AirmiusTextField(
              label: scope.t('authFlow.postal'),
              hint: '10115',
              controller: _postalCodeController,
              keyboardType: TextInputType.number,
            ),
            const SizedBox(height: 12),
            AirmiusTextField(
              label: scope.t('authFlow.city'),
              hint: 'Berlin',
              icon: Icons.location_city_outlined,
              controller: _cityController,
            ),
          ] else
            Row(
              children: [
                SizedBox(
                  width: 130,
                  child: AirmiusTextField(
                    label: scope.t('authFlow.postal'),
                    hint: '10115',
                    controller: _postalCodeController,
                    keyboardType: TextInputType.number,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: AirmiusTextField(
                    label: scope.t('authFlow.city'),
                    hint: 'Berlin',
                    icon: Icons.location_city_outlined,
                    controller: _cityController,
                  ),
                ),
              ],
            ),
          const SizedBox(height: 12),
          AirmiusTextField(
            label: scope.t('application.stateRegion'),
            hint: scope.t('application.optional'),
            icon: Icons.map_outlined,
            controller: _stateController,
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: AirmiusTextField(
                  label: scope.t('application.birthDate'),
                  hint: 'YYYY-MM-DD',
                  icon: Icons.cake_outlined,
                  controller: _birthDateController,
                  keyboardType: TextInputType.datetime,
                ),
              ),
              const SizedBox(width: 10),
              IconButton.filledTonal(
                tooltip: scope.t('authFlow.dateChoose'),
                onPressed: _pickBirthDate,
                icon: const Icon(Icons.calendar_month_outlined),
              ),
            ],
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: _gender.isEmpty ? null : _gender,
            isExpanded: true,
            dropdownColor: _authSurface(context),
            decoration: InputDecoration(
              labelText: scope.t('application.gender'),
              prefixIcon: Icon(Icons.wc_outlined, color: muted),
            ),
            style: TextStyle(color: text, fontWeight: FontWeight.w800),
            items: [
              DropdownMenuItem(
                value: 'female',
                child: Text(scope.t('application.gender.female')),
              ),
              DropdownMenuItem(
                value: 'male',
                child: Text(scope.t('application.gender.male')),
              ),
              DropdownMenuItem(
                value: 'diverse',
                child: Text(scope.t('application.gender.diverse')),
              ),
              DropdownMenuItem(
                value: 'not_specified',
                child: Text(scope.t('application.gender.unspecified')),
              ),
            ],
            onChanged: (value) => setState(() => _gender = value ?? ''),
          ),
          if (_requiresGuardianConsent) ...[
            const SizedBox(height: 12),
            Text(
              scope.t('authFlow.guardianMinor'),
              style: TextStyle(
                color: AirmiusColors.amber,
                fontWeight: FontWeight.w800,
                height: 1.35,
              ),
            ),
            const SizedBox(height: 12),
            AirmiusTextField(
              label: scope.t('application.guardianEmail'),
              hint: 'guardian@example.com',
              icon: Icons.supervisor_account_outlined,
              controller: _guardianEmailController,
              keyboardType: TextInputType.emailAddress,
            ),
          ],
          const SizedBox(height: 12),
          AirmiusTextField(
            label: scope.t('account.newPassword'),
            hint: scope.t('account.newPasswordHint'),
            icon: Icons.lock_outline,
            controller: _passwordController,
            obscureText: true,
          ),
          const SizedBox(height: 12),
          AirmiusTextField(
            label: scope.t('account.confirmPassword'),
            hint: scope.t('account.confirmPasswordHint'),
            icon: Icons.lock_reset_outlined,
            controller: _passwordConfirmationController,
            obscureText: true,
          ),
          const SizedBox(height: 12),
          Material(
            color: Colors.transparent,
            child: CheckboxListTile(
              value: _terms,
              onChanged: (value) => setState(() => _terms = value ?? false),
              activeColor: _authAccent(context),
              contentPadding: EdgeInsets.zero,
              title: Text(
                scope.t('authFlow.terms'),
                style: TextStyle(color: text, fontWeight: FontWeight.w800),
              ),
            ),
          ),
          if (error != null && error.isNotEmpty) ...[
            Text(
              error,
              style: const TextStyle(
                color: AirmiusColors.red,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 10),
          ],
          AirmiusButton(
            label: isLoading
                ? scope.t('authFlow.loading')
                : scope.t('authFlow.create'),
            icon: Icons.person_add_alt,
            onPressed: _terms && !isLoading ? _submitRegister : null,
          ),
        ],
      ),
    );
  }

  void _startSocialRegistration(String provider) {
    final register = widget.onSocialRegister;
    if (register != null) {
      register(provider, _accountType);
      return;
    }
    widget.onSocialLogin?.call(provider);
  }

  Widget _socialLogin() {
    final scope = AirmiusScope.of(context);
    final muted = _authMuted(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(scope.t('authFlow.social')),
          const SizedBox(height: 8),
          Text(
            scope.t('authFlow.socialBody'),
            style: TextStyle(color: muted, height: 1.35),
          ),
          const SizedBox(height: 12),
          _AuthStatusLine(
            icon: Icons.account_circle_outlined,
            title: 'Google',
            body: scope.t('authFlow.oauthStatus'),
            status: scope.t('authFlow.provider'),
          ),
          _AuthStatusLine(
            icon: Icons.phone_iphone_outlined,
            title: 'Apple',
            body: scope.t('authFlow.appleStatus'),
            status: scope.t('authFlow.ios'),
          ),
          _AuthStatusLine(
            icon: Icons.link_outlined,
            title: scope.t('authFlow.accountLinking'),
            body: scope.t('authFlow.accountLinking'),
            status: scope.t('authFlow.linking'),
          ),
          const SizedBox(height: 12),
          _AuthAction(
            label: scope.t('authFlow.startGoogle'),
            icon: Icons.account_circle_outlined,
            onPressed: widget.onSocialLogin == null
                ? null
                : () => widget.onSocialLogin!('google'),
          ),
          const SizedBox(height: 10),
          _AuthAction(
            label: scope.t('authFlow.startApple'),
            icon: Icons.phone_iphone_outlined,
            onPressed: widget.onSocialLogin == null
                ? null
                : () => widget.onSocialLogin!('apple'),
          ),
        ],
      ),
    );
  }

  Widget _password() {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(scope.t('passwordRecovery.forgot')),
          const SizedBox(height: 12),
          AirmiusTextField(
            label: scope.t('passwordRecovery.email'),
            hint: scope.t('login.emailHint'),
            icon: Icons.mail_outline,
          ),
          const SizedBox(height: 12),
          _AuthAction(
            label: scope.t('passwordRecovery.send'),
            icon: Icons.mark_email_read_outlined,
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const PasswordRecoveryScreen()),
            ),
          ),
          const SizedBox(height: 16),
          Eyebrow(scope.t('passwordRecovery.reset')),
          const SizedBox(height: 12),
          AirmiusTextField(
            label: scope.t('account.confirmationCode'),
            hint: scope.t('passwordRecovery.sent'),
          ),
          const SizedBox(height: 12),
          AirmiusTextField(
            label: scope.t('passwordRecovery.newPassword'),
            hint: scope.t('account.newPasswordHint'),
            icon: Icons.lock_outline,
          ),
        ],
      ),
    );
  }

  Widget _twoFactor() {
    final scope = AirmiusScope.of(context);
    final muted = _authMuted(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(scope.t('auth2fa.heading')),
          const SizedBox(height: 8),
          Text(
            scope.t('auth2fa.description'),
            style: TextStyle(color: muted, height: 1.35),
          ),
          const SizedBox(height: 12),
          AirmiusTextField(
            label: scope.t('auth2fa.code'),
            hint: '123456',
            icon: Icons.password_outlined,
          ),
          const SizedBox(height: 12),
          AirmiusTextField(
            label: scope.t('auth2fa.recoveryCode'),
            hint: scope.t('application.optional'),
          ),
          const SizedBox(height: 12),
          _AuthAction(
            label: scope.t('auth2fa.verify'),
            icon: Icons.verified_user_outlined,
            onPressed: () {
              final authState = AirmiusServicesScope.of(context).authState;
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) =>
                      TwoFactorChallengeScreen(authState: authState),
                ),
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _emailVerify() {
    final scope = AirmiusScope.of(context);
    final muted = _authMuted(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(scope.t('emailVerification.title')),
          const SizedBox(height: 8),
          Text(
            scope.t('emailVerification.description'),
            style: TextStyle(color: muted, height: 1.35),
          ),
          const SizedBox(height: 12),
          _AuthStatusLine(
            icon: Icons.mail_outline,
            title: scope.t('emailVerification.heading'),
            body: scope.t('emailVerification.sent'),
            status: scope.t('emailVerification.refresh'),
          ),
          const SizedBox(height: 12),
          _AuthAction(
            label: scope.t('emailVerification.resend'),
            icon: Icons.send_outlined,
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => const EmailVerificationScreen(),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _profileCompletion() {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(scope.t('profileGate.title')),
          const SizedBox(height: 12),
          _AuthStatusLine(
            icon: Icons.person_outline,
            title: scope.t('authFlow.profilePersonal'),
            body: scope.t('authFlow.profilePersonalBody'),
            status: '80%',
          ),
          _AuthStatusLine(
            icon: Icons.directions_run,
            title: scope.t('authFlow.profileSport'),
            body: scope.t('authFlow.profileSportBody'),
            status: scope.t('authFlow.open'),
          ),
          _AuthStatusLine(
            icon: Icons.privacy_tip_outlined,
            title: scope.t('authFlow.profileVisibility'),
            body: scope.t('authFlow.profileVisibilityBody'),
            status: scope.t('authFlow.profileCheck'),
          ),
          const SizedBox(height: 12),
          _AuthAction(
            label: scope.t('authFlow.profileComplete'),
            icon: Icons.task_alt_outlined,
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => ProfileCompletionGateScreen(
                  authState: AirmiusServicesScope.of(context).authState,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _suspended() {
    final scope = AirmiusScope.of(context);
    final muted = _authMuted(context);
    return AirmiusPanel(
      borderColor: AirmiusColors.red,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(scope.t('authFlow.suspended')),
          const SizedBox(height: 8),
          Text(
            scope.t('authFlow.suspendedBody'),
            style: TextStyle(color: muted, height: 1.35),
          ),
          const SizedBox(height: 12),
          _AuthStatusLine(
            icon: Icons.report_outlined,
            title: scope.t('authFlow.status'),
            body: scope.t('authFlow.supportDetails'),
            status: scope.t('authFlow.suspended'),
          ),
          const SizedBox(height: 12),
          _AuthAction(
            label: scope.t('authFlow.contactSupport'),
            icon: Icons.support_agent_outlined,
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const SupportHelpdeskScreen()),
            ),
          ),
        ],
      ),
    );
  }

  Widget _deleteAccount() {
    final scope = AirmiusScope.of(context);
    final muted = _authMuted(context);
    return AirmiusPanel(
      borderColor: AirmiusColors.red,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(scope.t('account.deleteAccount')),
          const SizedBox(height: 8),
          Text(
            scope.t('authFlow.deleteBody'),
            style: TextStyle(color: muted, height: 1.35),
          ),
          const SizedBox(height: 12),
          AirmiusTextField(
            label: scope.t('authFlow.deleteCode'),
            hint: scope.t('authFlow.deleteCodeHint'),
            icon: Icons.password_outlined,
          ),
          const SizedBox(height: 12),
          _AuthStatusLine(
            icon: Icons.download_outlined,
            title: scope.t('authFlow.export'),
            body: scope.t('authFlow.exportBody'),
            status: scope.t('authFlow.recommended'),
          ),
          _AuthStatusLine(
            icon: Icons.warning_amber_outlined,
            title: scope.t('authFlow.finalDelete'),
            body: scope.t('authFlow.finalDeleteBody'),
            status: scope.t('authFlow.critical'),
          ),
          const SizedBox(height: 12),
          _AuthAction(
            label: scope.t('authFlow.sendDelete'),
            icon: Icons.mark_email_read_outlined,
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) =>
                    const AccountOperationsScreen(initialTab: 'Delete'),
              ),
            ),
          ),
          const SizedBox(height: 10),
          _AuthAction(
            label: scope.t('authFlow.deleteFinal'),
            icon: Icons.delete_forever_outlined,
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) =>
                    const AccountOperationsScreen(initialTab: 'Delete'),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

AirmiusThemePalette _authPalette(BuildContext context) {
  try {
    return AirmiusThemeModeScope.of(context).palette;
  } on StateError {
    return Theme.of(context).brightness == Brightness.dark
        ? AirmiusThemePalette.dark
        : AirmiusThemePalette.air;
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
  return _authDarkUi(context)
      ? palette.darkBackground
      : palette.lightBackground;
}

Color _authHeader(BuildContext context) {
  final palette = _authPalette(context);
  return _authDarkUi(context) ? palette.darkHeader : palette.lightSurface;
}

Color _authSurface(BuildContext context) {
  final palette = _authPalette(context);
  return _authDarkUi(context) ? palette.darkSurface : palette.lightSurface;
}

Color _authText(BuildContext context) {
  final palette = _authPalette(context);
  return _authDarkUi(context) ? AirmiusColors.text : palette.lightText;
}

Color _authMuted(BuildContext context) {
  final palette = _authPalette(context);
  return _authDarkUi(context) ? AirmiusColors.muted : palette.lightMutedText;
}

class _AuthAction extends StatelessWidget {
  const _AuthAction({required this.label, required this.icon, this.onPressed});

  final String label;
  final IconData icon;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return AirmiusButton(
      label: label,
      icon: icon,
      // Unavailable provider actions stay disabled instead of opening a
      // misleading placeholder screen. Real actions are wired by the parent.
      onPressed: onPressed,
    );
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
  const _AuthStatusLine({
    required this.icon,
    required this.title,
    required this.body,
    required this.status,
  });

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
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: textColor,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(body, style: TextStyle(color: mutedColor, height: 1.3)),
              ],
            ),
          ),
          StatusPill(status),
        ],
      ),
    );
  }
}
