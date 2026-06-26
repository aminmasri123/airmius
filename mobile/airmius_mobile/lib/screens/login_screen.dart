import 'package:flutter/material.dart';

import '../core/airmius_auth_state.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'auth_flows_screen.dart';
import 'guest_portal_screen.dart';

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
    widget.onLogin(email, password);
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final isLoading = widget.authState.phase == AirmiusAuthPhase.loading;
    final error = widget.authState.phase == AirmiusAuthPhase.error ? widget.authState.error : null;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const AirmiusLogo(),
                  const SizedBox(height: 30),
                  AirmiusPanel(
                    gradient: true,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Eyebrow('Airmius Mobile'),
                        const SizedBox(height: 12),
                        Text(scope.t('login.title'), style: const TextStyle(color: AirmiusColors.text, fontSize: 34, fontWeight: FontWeight.w900, height: 1.04)),
                        const SizedBox(height: 12),
                        Text(scope.t('login.subtitle'), style: const TextStyle(color: AirmiusColors.muted, height: 1.45)),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        AirmiusTextField(
                          label: 'E-Mail',
                          hint: 'konto@example.com',
                          icon: Icons.mail_outline,
                          controller: _emailController,
                        ),
                        const SizedBox(height: 12),
                        AirmiusTextField(
                          label: 'Passwort',
                          hint: 'Passwort',
                          icon: Icons.lock_outline,
                          controller: _passwordController,
                          obscureText: !_isPasswordVisible,
                          suffixIcon: IconButton(
                            tooltip: _isPasswordVisible ? 'Passwort ausblenden' : 'Passwort einblenden',
                            icon: Icon(
                              _isPasswordVisible ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                              color: AirmiusColors.muted,
                            ),
                            onPressed: () => setState(() => _isPasswordVisible = !_isPasswordVisible),
                          ),
                        ),
                        const SizedBox(height: 16),
                        AirmiusButton(
                          label: 'Mit Google anmelden',
                          icon: Icons.g_mobiledata,
                          secondary: true,
                          onPressed: isLoading ? null : () => widget.onSocialLogin('google'),
                        ),
                        const SizedBox(height: 10),
                        AirmiusButton(
                          label: 'Mit Outlook anmelden',
                          icon: Icons.mail_outline,
                          secondary: true,
                          onPressed: isLoading ? null : () => widget.onSocialLogin('microsoft'),
                        ),
                        const SizedBox(height: 14),
                        if (error != null) ...[
                          Text(error, style: const TextStyle(color: AirmiusColors.red, fontWeight: FontWeight.w700)),
                          const SizedBox(height: 10),
                        ],
                        AirmiusButton(
                          label: isLoading ? 'Anmeldung läuft...' : scope.t('login.button'),
                          icon: Icons.login,
                          onPressed: isLoading ? null : _onSubmit,
                        ),
                        const SizedBox(height: 10),
                        AirmiusButton(
                          label: 'Registrieren / Passwort vergessen',
                          icon: Icons.manage_accounts_outlined,
                          secondary: true,
                          onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AuthFlowsScreen(onSocialLogin: widget.onSocialLogin))),
                        ),
                        const SizedBox(height: 10),
                        AirmiusButton(
                          label: 'Gastseite ansehen',
                          icon: Icons.open_in_new,
                          secondary: true,
                          onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestPortalScreen())),
                        ),
                        const SizedBox(height: 12),
                        const Text('Airmius App: Anmeldung direkt ueber das Laravel API', textAlign: TextAlign.center, style: TextStyle(color: AirmiusColors.mutedSoft, fontSize: 12)),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  const AirmiusThemeChooser(),
                  const SizedBox(height: 14),
                  const LanguageChooser(),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
