import 'package:flutter/material.dart';

import '../core/airmius_auth_state.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

enum _TwoFactorMethod { authenticator, email, recovery }

class TwoFactorChallengeScreen extends StatefulWidget {
  const TwoFactorChallengeScreen({super.key, required this.authState});

  final AirmiusAuthState authState;

  @override
  State<TwoFactorChallengeScreen> createState() =>
      _TwoFactorChallengeScreenState();
}

class _TwoFactorChallengeScreenState extends State<TwoFactorChallengeScreen> {
  final _code = TextEditingController();
  _TwoFactorMethod _method = _TwoFactorMethod.authenticator;
  bool _emailCodeSent = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final loading = widget.authState.phase == AirmiusAuthPhase.loading;
    final isRecovery = _method == _TwoFactorMethod.recovery;
    final isEmail = _method == _TwoFactorMethod.email;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: AirmiusPanel(
                gradient: true,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Icon(
                      Icons.phonelink_lock_outlined,
                      size: 56,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      t('auth2fa.heading'),
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      t('auth2fa.description'),
                      textAlign: TextAlign.center,
                      style: Theme.of(
                        context,
                      ).textTheme.bodyMedium?.copyWith(height: 1.45),
                    ),
                    const SizedBox(height: 20),
                    Wrap(
                      alignment: WrapAlignment.center,
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        ChoiceChip(
                          label: Text(t('auth2fa.useAuthenticator')),
                          selected: _method == _TwoFactorMethod.authenticator,
                          onSelected: loading
                              ? null
                              : (_) => _selectMethod(
                                  _TwoFactorMethod.authenticator,
                                ),
                        ),
                        if (widget.authState.supportsTwoFactorEmail)
                          ChoiceChip(
                            label: Text(t('auth2fa.useEmail')),
                            selected: isEmail,
                            onSelected: loading
                                ? null
                                : (_) => _selectMethod(_TwoFactorMethod.email),
                          ),
                        ChoiceChip(
                          label: Text(t('auth2fa.useRecovery')),
                          selected: isRecovery,
                          onSelected: loading
                              ? null
                              : (_) => _selectMethod(_TwoFactorMethod.recovery),
                        ),
                      ],
                    ),
                    if (isEmail) ...[
                      const SizedBox(height: 12),
                      AirmiusButton(
                        label: t(
                          _emailCodeSent
                              ? 'auth2fa.resendEmail'
                              : 'auth2fa.sendEmail',
                        ),
                        icon: Icons.email_outlined,
                        secondary: true,
                        onPressed: loading ? null : _sendEmailCode,
                      ),
                      if (_emailCodeSent) ...[
                        const SizedBox(height: 8),
                        Text(
                          t('auth2fa.emailSent'),
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                    ],
                    const SizedBox(height: 16),
                    AirmiusTextField(
                      label: t(
                        isRecovery
                            ? 'auth2fa.recoveryCode'
                            : isEmail
                            ? 'auth2fa.emailCode'
                            : 'auth2fa.code',
                      ),
                      hint: isRecovery ? 'xxxx-xxxx-xxxx' : '123456',
                      icon: isRecovery
                          ? Icons.key_outlined
                          : isEmail
                          ? Icons.email_outlined
                          : Icons.password_outlined,
                      controller: _code,
                      keyboardType: isRecovery
                          ? TextInputType.text
                          : TextInputType.number,
                    ),
                    if (widget.authState.error != null) ...[
                      const SizedBox(height: 12),
                      Semantics(
                        liveRegion: true,
                        child: Text(
                          widget.authState.error!,
                          style: const TextStyle(
                            color: AirmiusColors.red,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                    const SizedBox(height: 16),
                    AirmiusButton(
                      label: t('auth2fa.verify'),
                      icon: Icons.verified_user_outlined,
                      onPressed: loading
                          ? null
                          : () => widget.authState.completeTwoFactor(
                              value: _code.text,
                              recoveryCode: isRecovery,
                              emailCode: isEmail,
                            ),
                    ),
                    const SizedBox(height: 8),
                    TextButton.icon(
                      onPressed: loading
                          ? null
                          : widget.authState.cancelTwoFactor,
                      icon: const Icon(Icons.arrow_back),
                      label: Text(t('auth2fa.cancel')),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _selectMethod(_TwoFactorMethod method) {
    _code.clear();
    setState(() => _method = method);
  }

  Future<void> _sendEmailCode() async {
    final sent = await widget.authState.requestTwoFactorEmailCode();
    if (!mounted || !sent) return;
    setState(() => _emailCodeSent = true);
  }
}
