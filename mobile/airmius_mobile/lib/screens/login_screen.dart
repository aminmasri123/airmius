import 'package:flutter/material.dart';

import '../core/airmius_auth_state.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_theme_mode_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'auth_flows_screen.dart';
import 'guest_portal_screen.dart';
import 'password_recovery_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({
    super.key,
    required this.authState,
    required this.onLogin,
    required this.onSocialLogin,
  });

  final AirmiusAuthState authState;
  final void Function(String email, String password) onLogin;
  final void Function(String provider) onSocialLogin;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isPasswordVisible = false;
  String? _emailError;
  String? _passwordError;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  void _onSubmit() {
    if (widget.authState.phase == AirmiusAuthPhase.loading) {
      return;
    }
    final email = _emailController.text.trim();
    final password = _passwordController.text;
    final scope = AirmiusScope.of(context);
    final emailIsValid = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email);
    setState(() {
      _emailError = emailIsValid ? null : scope.t('login.invalidEmail');
      _passwordError = password.isEmpty
          ? scope.t('login.passwordRequired')
          : null;
    });
    if (_emailError != null || _passwordError != null) {
      return;
    }
    FocusManager.instance.primaryFocus?.unfocus();
    widget.onLogin(email, password);
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final isLoading = widget.authState.phase == AirmiusAuthPhase.loading;
    final error = widget.authState.phase == AirmiusAuthPhase.error
        ? widget.authState.error
        : null;
    final text = _loginText(context);
    final muted = _loginMuted(context);

    return Scaffold(
      backgroundColor: _loginBackground(context),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const _LoginTopBar(),
                  const SizedBox(height: 18),
                  AirmiusPanel(
                    gradient: true,
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Eyebrow('Airmius Mobile'),
                        const SizedBox(height: 10),
                        FittedBox(
                          alignment: Alignment.centerLeft,
                          fit: BoxFit.scaleDown,
                          child: Text(
                            scope.t('login.title'),
                            maxLines: 1,
                            style: TextStyle(
                              color: text,
                              fontSize: 30,
                              fontWeight: FontWeight.w900,
                              height: 1.04,
                            ),
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          scope.t('login.subtitle'),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(color: muted, height: 1.35),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  AirmiusPanel(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        AirmiusTextField(
                          label: scope.t('login.email'),
                          hint: scope.t('login.emailHint'),
                          icon: Icons.mail_outline,
                          controller: _emailController,
                          keyboardType: TextInputType.emailAddress,
                          onChanged: (_) {
                            if (_emailError != null) {
                              setState(() => _emailError = null);
                            }
                          },
                        ),
                        if (_emailError != null) _LoginFieldError(_emailError!),
                        const SizedBox(height: 12),
                        AirmiusTextField(
                          label: scope.t('login.password'),
                          hint: scope.t('login.passwordHint'),
                          icon: Icons.lock_outline,
                          controller: _passwordController,
                          obscureText: !_isPasswordVisible,
                          onChanged: (_) {
                            if (_passwordError != null) {
                              setState(() => _passwordError = null);
                            }
                          },
                          suffixIcon: IconButton(
                            tooltip: _isPasswordVisible
                                ? scope.t('login.hidePassword')
                                : scope.t('login.showPassword'),
                            icon: Icon(
                              _isPasswordVisible
                                  ? Icons.visibility_off_outlined
                                  : Icons.visibility_outlined,
                              color: muted,
                            ),
                            onPressed: () => setState(
                              () => _isPasswordVisible = !_isPasswordVisible,
                            ),
                          ),
                        ),
                        if (_passwordError != null)
                          _LoginFieldError(_passwordError!),
                        const SizedBox(height: 14),
                        Row(
                          children: [
                            Expanded(
                              child: AirmiusButton(
                                label: 'Google',
                                icon: Icons.g_mobiledata,
                                secondary: true,
                                onPressed: isLoading
                                    ? null
                                    : () => widget.onSocialLogin('google'),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: AirmiusButton(
                                label: 'Outlook',
                                icon: Icons.mail_outline,
                                secondary: true,
                                onPressed: isLoading
                                    ? null
                                    : () => widget.onSocialLogin('microsoft'),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        if (error != null) ...[
                          Text(
                            error,
                            style: const TextStyle(
                              color: AirmiusColors.red,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 10),
                        ],
                        AirmiusButton(
                          label: isLoading
                              ? scope.t('login.loading')
                              : scope.t('login.button'),
                          icon: Icons.login,
                          onPressed: isLoading ? null : _onSubmit,
                        ),
                        const SizedBox(height: 8),
                        Wrap(
                          alignment: WrapAlignment.center,
                          spacing: 6,
                          runSpacing: 2,
                          children: [
                            TextButton.icon(
                              onPressed: () => Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => AuthFlowsScreen(
                                    onSocialLogin: widget.onSocialLogin,
                                  ),
                                ),
                              ),
                              icon: const Icon(
                                Icons.manage_accounts_outlined,
                                size: 18,
                              ),
                              label: Text(scope.t('login.register')),
                            ),
                            TextButton.icon(
                              onPressed: () => Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => PasswordRecoveryScreen(
                                    initialEmail: _emailController.text.trim(),
                                  ),
                                ),
                              ),
                              icon: const Icon(Icons.help_outline, size: 18),
                              label: Text(scope.t('passwordRecovery.forgot')),
                            ),
                            TextButton.icon(
                              onPressed: () => Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => GuestPortalScreen(),
                                ),
                              ),
                              icon: const Icon(Icons.open_in_new, size: 18),
                              label: Text(scope.t('login.guest')),
                            ),
                          ],
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
    );
  }
}

class _LoginFieldError extends StatelessWidget {
  const _LoginFieldError(this.message);

  final String message;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      liveRegion: true,
      container: true,
      child: Padding(
        padding: const EdgeInsets.only(top: 6, left: 4),
        child: Text(
          message,
          style: TextStyle(
            color: Theme.of(context).colorScheme.error,
            fontWeight: FontWeight.w700,
            height: 1.25,
          ),
        ),
      ),
    );
  }
}

class _LoginTopBar extends StatelessWidget {
  const _LoginTopBar();

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Expanded(
          child: Align(
            alignment: Alignment.centerLeft,
            child: AirmiusLogo(compact: true),
          ),
        ),
        const SizedBox(width: 12),
        _LoginIconMenu<AirmiusLanguage>(
          tooltip: 'Sprache',
          icon: Icons.language_outlined,
          value: AirmiusScope.of(context).language,
          entries: [
            for (final language in AirmiusLanguage.values)
              PopupMenuItem(
                value: language,
                child: _MenuLine(
                  icon: Icons.translate_outlined,
                  label: language.label,
                  trailing: language.code,
                  selected: AirmiusScope.of(context).language == language,
                ),
              ),
          ],
          onSelected: AirmiusScope.of(context).setLanguage,
        ),
        const SizedBox(width: 8),
        _LoginIconMenu<ThemeMode>(
          tooltip: 'Design',
          icon: Icons.contrast_outlined,
          value: AirmiusThemeModeScope.of(context).mode,
          entries: [
            PopupMenuItem(
              value: ThemeMode.dark,
              child: _MenuLine(
                icon: Icons.dark_mode_outlined,
                label: 'Dunkel',
                selected:
                    AirmiusThemeModeScope.of(context).mode == ThemeMode.dark,
              ),
            ),
            PopupMenuItem(
              value: ThemeMode.light,
              child: _MenuLine(
                icon: Icons.light_mode_outlined,
                label: 'Normal',
                selected:
                    AirmiusThemeModeScope.of(context).mode == ThemeMode.light,
              ),
            ),
            PopupMenuItem(
              value: ThemeMode.system,
              child: _MenuLine(
                icon: Icons.phone_iphone_outlined,
                label: 'System',
                selected:
                    AirmiusThemeModeScope.of(context).mode == ThemeMode.system,
              ),
            ),
          ],
          onSelected: AirmiusThemeModeScope.of(context).setMode,
        ),
      ],
    );
  }
}

class _LoginIconMenu<T> extends StatelessWidget {
  const _LoginIconMenu({
    required this.tooltip,
    required this.icon,
    required this.value,
    required this.entries,
    required this.onSelected,
  });

  final String tooltip;
  final IconData icon;
  final T value;
  final List<PopupMenuEntry<T>> entries;
  final ValueChanged<T> onSelected;

  @override
  Widget build(BuildContext context) {
    final foreground = _loginText(context);
    final border = _loginBorder(context);
    final surface = _loginSurface(context);
    final surfaceSoft = _loginSurfaceSoft(context);
    return PopupMenuButton<T>(
      tooltip: tooltip,
      initialValue: value,
      onSelected: onSelected,
      color: surface,
      surfaceTintColor: Colors.transparent,
      itemBuilder: (_) => entries,
      child: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: surfaceSoft,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: border),
        ),
        child: Icon(icon, color: foreground, size: 21),
      ),
    );
  }
}

class _MenuLine extends StatelessWidget {
  const _MenuLine({
    required this.icon,
    required this.label,
    this.trailing,
    this.selected = false,
  });

  final IconData icon;
  final String label;
  final String? trailing;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    final accent = _loginAccent(context);
    final text = _loginText(context);
    final muted = _loginMuted(context);
    final color = selected ? accent : text;
    return Row(
      children: [
        Icon(icon, color: color, size: 19),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            label,
            style: TextStyle(color: color, fontWeight: FontWeight.w900),
          ),
        ),
        if (trailing != null) ...[
          const SizedBox(width: 14),
          Text(
            trailing!,
            style: TextStyle(color: muted, fontWeight: FontWeight.w900),
          ),
        ],
        if (selected) ...[
          const SizedBox(width: 10),
          Icon(Icons.check_circle, color: accent, size: 18),
        ],
      ],
    );
  }
}

AirmiusThemePalette _loginPalette(BuildContext context) {
  try {
    return AirmiusThemeModeScope.of(context).palette;
  } on StateError {
    return Theme.of(context).brightness == Brightness.dark
        ? AirmiusThemePalette.dark
        : AirmiusThemePalette.air;
  }
}

bool _loginDarkUi(BuildContext context) {
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

Color _loginAccent(BuildContext context) {
  return _loginPalette(context).primary;
}

Color _loginBackground(BuildContext context) {
  final palette = _loginPalette(context);
  return _loginDarkUi(context)
      ? palette.darkBackground
      : palette.lightBackground;
}

Color _loginSurface(BuildContext context) {
  final palette = _loginPalette(context);
  return _loginDarkUi(context) ? palette.darkSurface : palette.lightSurface;
}

Color _loginSurfaceSoft(BuildContext context) {
  final palette = _loginPalette(context);
  return _loginDarkUi(context)
      ? palette.darkSurfaceSoft
      : palette.lightSurfaceSoft;
}

Color _loginText(BuildContext context) {
  final palette = _loginPalette(context);
  return _loginDarkUi(context) ? AirmiusColors.text : palette.lightText;
}

Color _loginMuted(BuildContext context) {
  final palette = _loginPalette(context);
  return _loginDarkUi(context) ? AirmiusColors.muted : palette.lightMutedText;
}

Color _loginBorder(BuildContext context) {
  final palette = _loginPalette(context);
  return _loginDarkUi(context) ? AirmiusColors.border : palette.lightBorder;
}
