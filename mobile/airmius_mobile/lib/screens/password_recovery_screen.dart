import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class PasswordRecoveryScreen extends StatefulWidget {
  const PasswordRecoveryScreen({
    super.key,
    this.initialEmail,
    this.initialToken,
  });

  final String? initialEmail;
  final String? initialToken;

  @override
  State<PasswordRecoveryScreen> createState() => _PasswordRecoveryScreenState();
}

class _PasswordRecoveryScreenState extends State<PasswordRecoveryScreen> {
  late final TextEditingController _email;
  late final TextEditingController _token;
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _busy = false;
  bool _linkRequested = false;
  bool _completed = false;

  bool get _hasToken => _token.text.trim().isNotEmpty;

  @override
  void initState() {
    super.initState();
    _email = TextEditingController(text: widget.initialEmail ?? '');
    _token = TextEditingController(text: widget.initialToken ?? '');
  }

  @override
  void dispose() {
    _email.dispose();
    _token.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(title: Text(t('passwordRecovery.title'))),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 560),
              child: AirmiusPanel(
                gradient: true,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Icon(
                      _completed
                          ? Icons.check_circle_outline
                          : Icons.lock_reset_outlined,
                      size: 54,
                      color: _completed
                          ? AirmiusColors.green
                          : Theme.of(context).colorScheme.primary,
                    ),
                    const SizedBox(height: 14),
                    Text(
                      _completed
                          ? t('passwordRecovery.completed')
                          : t('passwordRecovery.heading'),
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _completed
                          ? t('passwordRecovery.completedDescription')
                          : t('passwordRecovery.description'),
                      textAlign: TextAlign.center,
                      style: Theme.of(
                        context,
                      ).textTheme.bodyMedium?.copyWith(height: 1.45),
                    ),
                    const SizedBox(height: 20),
                    if (_completed)
                      AirmiusButton(
                        label: t('passwordRecovery.backToLogin'),
                        icon: Icons.login,
                        onPressed: () => Navigator.pop(context),
                      )
                    else ...[
                      AirmiusTextField(
                        label: t('passwordRecovery.email'),
                        hint: 'konto@example.com',
                        icon: Icons.mail_outline,
                        controller: _email,
                        keyboardType: TextInputType.emailAddress,
                      ),
                      if (_hasToken) ...[
                        const SizedBox(height: 12),
                        AirmiusTextField(
                          label: t('passwordRecovery.newPassword'),
                          icon: Icons.password_outlined,
                          controller: _password,
                          obscureText: true,
                        ),
                        const SizedBox(height: 12),
                        AirmiusTextField(
                          label: t('passwordRecovery.confirmPassword'),
                          icon: Icons.check_circle_outline,
                          controller: _confirmation,
                          obscureText: true,
                        ),
                        const SizedBox(height: 16),
                        AirmiusButton(
                          label: t('passwordRecovery.reset'),
                          icon: Icons.lock_reset,
                          onPressed: _busy ? null : _resetPassword,
                        ),
                      ] else ...[
                        if (_linkRequested) ...[
                          const SizedBox(height: 12),
                          Semantics(
                            liveRegion: true,
                            child: Text(
                              t('passwordRecovery.sent'),
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: AirmiusColors.green,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        ],
                        const SizedBox(height: 16),
                        AirmiusButton(
                          label: t('passwordRecovery.send'),
                          icon: Icons.mark_email_read_outlined,
                          onPressed: _busy ? null : _requestLink,
                        ),
                      ],
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _requestLink() async {
    final email = _email.text.trim();
    if (!email.contains('@')) {
      _message(AirmiusScope.of(context).t('passwordRecovery.invalidEmail'));
      return;
    }
    await _run(() async {
      await _client.requestPasswordReset(email: email);
      if (mounted) setState(() => _linkRequested = true);
    });
  }

  Future<void> _resetPassword() async {
    final t = AirmiusScope.of(context).t;
    if (_password.text != _confirmation.text) {
      _message(t('passwordRecovery.passwordMismatch'));
      return;
    }
    await _run(() async {
      await _client.resetPassword(
        token: _token.text.trim(),
        email: _email.text.trim(),
        password: _password.text,
        passwordConfirmation: _confirmation.text,
      );
      if (mounted) setState(() => _completed = true);
    });
  }

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(null);
  }

  Future<void> _run(Future<void> Function() action) async {
    final fallbackError = AirmiusScope.of(context).t('passwordRecovery.error');
    setState(() => _busy = true);
    try {
      await action();
    } on AirmiusApiException catch (error) {
      _message(error.userMessage);
    } catch (_) {
      _message(fallbackError);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _message(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}
